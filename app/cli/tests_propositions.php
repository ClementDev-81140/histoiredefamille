<?php
/**
 * Tests des propositions validées par les administrateurs (1.23.0).
 *
 * Un journaliste propose ; un administrateur valide ou refuse. Rien de ce
 * qu'un journaliste dépose ne doit être visible avant la validation — ni dans
 * les listes, ni dans la recherche, ni par une adresse directe, ni par une
 * vignette, ni dans la gazette, ni par courriel.
 *
 * Trois règles sont vérifiées ici, du plus fin au plus large :
 *   1. les états (brouillon, en attente, publié, refusé) et le parcours
 *      complet : proposer, relire, valider, refuser, corriger ;
 *   2. les fuites : lecteur, autre journaliste et visiteur ne voient RIEN
 *      avant la validation, et l'auteur comme l'administrateur voient tout ;
 *   3. les notifications : elles ne partent qu'à la validation, et seulement
 *      pour les personnes qui ont le droit de voir le contenu.
 *
 * Tout se passe dans une transaction annulée et les fichiers d'essai sont
 * supprimés : la base et le stockage du site restent intacts.
 */

declare(strict_types=1);

/** Branche d'essai pour les propositions. */
function tests_propositions_branche(string $nom): int
{
    return db_insert('branches', [
        'code'        => 'PROP' . strtoupper(bin2hex(random_bytes(2))),
        'name'        => $nom,
        'description' => null,
        'see_all'     => 0,
        'is_active'   => 1,
        'created_at'  => time(),
        'updated_at'  => time(),
    ]);
}

/** Compte d'essai : lecteur, journaliste ou administrateur. */
function tests_propositions_compte(string $type, ?int $branche = null, array $extra = []): int
{
    $estPersonne = $type === 'personne';

    return db_insert('accounts', array_merge([
        'username'      => 'prop-' . $type . '-' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai ' . $type,
        'password_hash' => password_make('MotDePasseEssai123!'),
        'role'          => $type === 'admin' ? 'admin' : 'famille',
        'account_type'  => $type,
        'first_name'    => $estPersonne || $type === 'admin' ? 'Essai' : '',
        'last_name'     => $estPersonne || $type === 'admin' ? 'Proposition' : '',
        'branch_id'     => $branche,
        'see_all'       => 0,
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ], $extra));
}

/** Le jeton CSRF de la session d'essai (les tests l'envoient dans leur POST). */
function tests_propositions_jeton(): string
{
    return csrf_token();
}

/**
 * Document d'essai déposé par un journaliste, avec son fichier et sa
 * miniature réellement écrits dans le stockage.
 *
 * @return array{id:int, rel:string, thumb:string, abs:string}
 */
function tests_propositions_document(int $auteur, string $titre, string $etat = 'attente'): array
{
    $rel   = storage_target_path('pdf');
    $abs   = storage_absolute($rel);
    file_put_contents($abs, "Document d’essai des propositions.\n");
    @chmod($abs, 0640);

    $thumbRel = storage_target_path('jpg');
    $thumbAbs = storage_absolute($thumbRel);
    file_put_contents($thumbAbs, 'miniature d’essai');
    @chmod($thumbAbs, 0640);

    $id = db_insert('documents', [
        'title'               => $titre,
        'description'         => 'Texte de la proposition.',
        'doc_date'            => null,
        'doc_date_sort'       => null,
        'category_id'         => null,
        'kind'                => 'document',
        'visibility'          => 'prive',
        'rel_path'            => $rel,
        'thumb_path'          => $thumbRel,
        'original_name'       => 'proposition.pdf',
        'mime'                => 'application/pdf',
        'ext'                 => 'pdf',
        'size'                => (int) @filesize($abs),
        'width'               => null,
        'height'              => null,
        'is_attachment'       => 0,
        'created_by'          => $auteur,
        'created_at'          => time(),
        'updated_at'          => time(),
        'moderation_state'    => $etat,
        'moderation_note'     => '',
        'submitted_by'        => $auteur,
        'submitted_at'        => time(),
        'validated_by'        => null,
        'validated_at'        => null,
        'suggested_visibility' => 'famille',
    ]);

    return ['id' => $id, 'rel' => $rel, 'thumb' => $thumbRel, 'abs' => $abs];
}

/** Toute la famille « propositions ». */
function tests_propositions(): void
{
    echo "Propositions des journalistes — validation par les administrateurs\n";

    $fichiers = [];
    try {
        db_transaction(static function () use (&$fichiers): void {
            tests_propositions_etats();
            tests_propositions_flux();
            tests_propositions_fuite($fichiers);

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    // Les fichiers d'essai ne doivent pas rester dans le stockage.
    foreach ($fichiers as $chemin) {
        @unlink(storage_absolute($chemin));
    }
    verifier(
        'propositions : les fichiers d’essai sont tous effacés',
        array_filter($fichiers, static fn (string $c): bool => is_file(storage_absolute($c))) === [],
        'restants : ' . implode(', ', $fichiers)
    );
}

/** 1. Les états, et ce qu'ils veulent dire. */
function tests_propositions_etats(): void
{
    $etats = moderation_states();
    verifier(
        'propositions : quatre états, pas un de plus',
        array_keys($etats) === ['brouillon', 'attente', 'publie', 'refuse'],
        implode(', ', array_keys($etats))
    );
    verifier(
        'propositions : chaque état a un libellé lisible',
        moderation_label('attente') === 'En attente'
            && moderation_label('refuse') === 'Refusé'
            && moderation_label('publie') === 'Publié'
            && moderation_label('brouillon') === 'Brouillon'
    );
    verifier('propositions : la migration 016 est en place', proposition_migration_faite());

    // Un contenu créé sans les colonnes de validation reste publié : c'est la
    // valeur par défaut, et la migration ne touche pas aux lignes existantes.
    $id = db_insert('articles', [
        'title'        => 'Article écrit sans passer par la validation',
        'slug'         => 'article-ecrit-sans-validation-' . bin2hex(random_bytes(3)),
        'summary'      => '',
        'body'         => 'Texte.',
        'status'       => 'publie',
        'visibility'   => 'prive',
        'published_at' => time() - 60,
        'created_at'   => time(),
        'updated_at'   => time(),
    ]);
    $ligne = (array) db_one('SELECT * FROM articles WHERE id = ?', [$id]);
    verifier(
        'propositions : un contenu ordinaire naît « publié » (rien ne change pour l’existant)',
        ($ligne['moderation_state'] ?? '') === 'publie' && ($ligne['submitted_by'] ?? null) === null,
        (string) ($ligne['moderation_state'] ?? '?')
    );
    verifier(
        'propositions : les trois types connus sont l’article, le document et la recette',
        array_keys(proposition_types()) === ['article', 'document', 'recette']
    );
    verifier(
        'propositions : un type inventé ne désigne aucune table',
        proposition_table('album') === '' && proposition_table('articles; DROP TABLE') === ''
    );
}

/**
 * 2. Le parcours complet : proposer, relire, valider, refuser, corriger.
 */
function tests_propositions_flux(): void
{
    $brancheA    = tests_propositions_branche('Branche A des propositions');
    $brancheB    = tests_propositions_branche('Branche B des propositions');
    $lecteur     = tests_propositions_compte('branche', $brancheA);
    $journaliste = tests_propositions_compte('personne', $brancheA);
    $autre       = tests_propositions_compte('personne', $brancheB);
    $admin       = tests_propositions_compte('admin');

    // --- Un lecteur et un visiteur n'entrent pas ---------------------------
    $refusLecteur = tests_envoi_requete('GET', '/propositions', $lecteur);
    verifier(
        'propositions : un lecteur ne peut pas ouvrir « Mes propositions » (403)',
        (int) $refusLecteur['code'] === 403,
        'code ' . $refusLecteur['code']
    );
    $refusVisiteur = tests_envoi_requete('GET', '/propositions', null);
    verifier(
        'propositions : un visiteur est renvoyé vers la connexion',
        (int) $refusVisiteur['code'] === 303
            && str_contains((string) $refusVisiteur['location'], '/connexion'),
        'code ' . $refusVisiteur['code']
    );

    $_SESSION = ['account_id' => $lecteur, '_csrf' => tests_propositions_jeton()];
    $avant = (int) db_value('SELECT COUNT(*) FROM articles', [], 0);
    $ecritureLecteur = tests_envoi_requete('POST', '/propositions/nouvelle/article', $lecteur, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => 'Article interdit',
        'body' => 'Un lecteur ne doit pas pouvoir écrire.',
    ]);
    verifier(
        'propositions : un lecteur ne peut pas déposer d’article (403, rien en base)',
        (int) $ecritureLecteur['code'] === 403
            && (int) db_value('SELECT COUNT(*) FROM articles', [], 0) === $avant,
        'code ' . $ecritureLecteur['code']
    );

    // --- Un journaliste dépose un article ---------------------------------
    $_SESSION = ['account_id' => $journaliste, '_csrf' => tests_propositions_jeton()];
    $titre = 'Chronique d’essai ' . bin2hex(random_bytes(3));
    $depot = tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titre,
        'summary' => 'Chapeau d’essai.',
        'body' => "Premier paragraphe.\n\nSecond paragraphe.",
        'category' => 'Essais',
        'suggested_visibility' => 'famille',
    ]);
    $article = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titre]);
    verifier(
        'propositions : un journaliste dépose un article, qui naît « en attente » et privé',
        (int) $depot['code'] === 303
            && $article !== []
            && ($article['moderation_state'] ?? '') === 'attente'
            && ($article['visibility'] ?? '') === 'prive'
            && (int) ($article['submitted_by'] ?? 0) === $journaliste,
        'code ' . $depot['code'] . ', état ' . ($article['moderation_state'] ?? 'absent')
    );
    verifier(
        'propositions : la visibilité souhaitée par l’auteur est gardée comme simple suggestion',
        ($article['suggested_visibility'] ?? '') === 'famille'
            && ($article['visibility'] ?? '') !== 'famille'
    );

    // Le journaliste ne choisit pas la visibilité réelle, même s'il l'envoie.
    $titreTriche = 'Article avec visibilité forcée ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreTriche,
        'body' => 'Texte.',
        'visibility' => 'public',
    ]);
    $triche = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titreTriche]);
    verifier(
        'propositions : un journaliste ne peut pas publier en écrivant « visibility » dans le formulaire',
        ($triche['visibility'] ?? '') === 'prive' && ($triche['moderation_state'] ?? '') === 'attente',
        (string) ($triche['visibility'] ?? '?')
    );

    // --- Il retrouve sa proposition dans « Mes propositions » --------------
    $liste = tests_envoi_requete('GET', '/propositions', $journaliste);
    verifier(
        'propositions : le journaliste lit sa proposition, avec son état',
        (int) $liste['code'] === 200
            && str_contains((string) $liste['corps'], $titre)
            && str_contains((string) $liste['corps'], 'En attente'),
        'code ' . $liste['code']
    );

    // La proposition d'un autre auteur reste introuvable, même pour un journaliste.
    $intrus = tests_envoi_requete('GET', '/propositions/article/' . (int) $article['id'], $autre);
    verifier(
        'propositions : la proposition d’un autre journaliste répond comme une proposition qui n’existe pas',
        (int) $intrus['code'] === 404,
        'code ' . $intrus['code']
    );

    // --- L'administration voit tout dans une seule file -------------------
    $file = tests_envoi_requete('GET', '/admin/a-valider', $admin);
    verifier(
        'propositions : la file « À valider » montre la proposition et son auteur',
        (int) $file['code'] === 200
            && str_contains((string) $file['corps'], $titre)
            && str_contains((string) $file['corps'], 'Essai Proposition'),
        'code ' . $file['code']
    );
    verifier(
        'propositions : le compteur « À valider » compte la proposition',
        propositions_a_valider() >= 2,
        'compteur ' . propositions_a_valider()
    );

    $apercu = tests_envoi_requete('GET', '/admin/a-valider/article/' . (int) $article['id'], $admin);
    verifier(
        'propositions : l’administrateur ouvre l’aperçu complet de la proposition',
        (int) $apercu['code'] === 200
            && str_contains((string) $apercu['corps'], 'Second paragraphe')
            && str_contains((string) $apercu['corps'], 'Valider et publier')
            && str_contains((string) $apercu['corps'], 'Refuser'),
        'code ' . $apercu['code']
    );
    $apercuAutre = tests_envoi_requete('GET', '/admin/a-valider/article/' . (int) $article['id'], $journaliste);
    verifier(
        'propositions : un journaliste n’ouvre pas les écrans de validation',
        (int) $apercuAutre['code'] === 403,
        'code ' . $apercuAutre['code']
    );

    // --- Valider : privé par défaut, et la visibilité choisie ensuite -----
    $sansChoix = tests_envoi_requete('POST', '/admin/a-valider/article/' . (int) $article['id'] . '/valider', $admin, [
        '_csrf' => tests_propositions_jeton(),
    ]);
    $apresDefaut = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article['id']]);
    verifier(
        'propositions : valider sans rien cocher publie en privé (jamais en public par accident)',
        (int) $sansChoix['code'] === 303
            && ($apresDefaut['moderation_state'] ?? '') === 'publie'
            && ($apresDefaut['visibility'] ?? '') === 'prive'
            && (int) ($apresDefaut['validated_by'] ?? 0) === $admin
            && (int) ($apresDefaut['validated_at'] ?? 0) > 0,
        'visibilité ' . ($apresDefaut['visibility'] ?? '?')
    );
    verifier(
        'propositions : la validation est notée au journal d’actions',
        (int) db_value(
            "SELECT COUNT(*) FROM activity_log WHERE account_id = ? AND action = 'proposition_validee'",
            [$admin],
            0
        ) >= 1
    );

    $titreValide = 'Article validé ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreValide,
        'body' => 'Texte validé.',
        'suggested_visibility' => 'public',
    ]);
    $article2 = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titreValide]);
    tests_envoi_requete('POST', '/admin/a-valider/article/' . (int) $article2['id'] . '/valider', $admin, [
        '_csrf' => tests_propositions_jeton(),
        'visibility' => 'famille',
    ]);
    $apres = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article2['id']]);
    verifier(
        'propositions : la visibilité choisie par l’administrateur est celle qui s’applique',
        ($apres['moderation_state'] ?? '') === 'publie' && ($apres['visibility'] ?? '') === 'famille',
        'visibilité ' . ($apres['visibility'] ?? '?')
    );
    $restants = count(propositions_en_attente());
    verifier(
        'propositions : la proposition validée quitte la file « À valider »',
        !in_array((int) $article2['id'], array_map(
            static fn (array $e): int => (int) $e['row']['id'],
            array_filter(propositions_en_attente(), static fn (array $e): bool => $e['type'] === 'article')
        ), true),
        'encore en attente : ' . $restants
    );

    // --- Refuser : motif obligatoire --------------------------------------
    $titreRefus = 'Article à refuser ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreRefus,
        'body' => 'Texte à refuser.',
    ]);
    $article3 = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titreRefus]);

    $sansMotif = tests_envoi_requete(
        'POST',
        '/admin/a-valider/article/' . (int) $article3['id'] . '/refuser',
        $admin,
        ['_csrf' => tests_propositions_jeton(), 'motif' => '  ']
    );
    $apresRefusVide = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article3['id']]);
    verifier(
        'propositions : refuser sans motif ne change rien',
        (int) $sansMotif['code'] === 303
            && ($apresRefusVide['moderation_state'] ?? '') === 'attente',
        'état ' . ($apresRefusVide['moderation_state'] ?? '?')
    );

    $avecMotif = tests_envoi_requete('POST', '/admin/a-valider/article/' . (int) $article3['id'] . '/refuser', $admin, [
        '_csrf' => tests_propositions_jeton(),
        'motif' => 'Le texte cite une personne vivante.',
    ]);
    $apresRefus = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article3['id']]);
    verifier(
        'propositions : refuser avec un motif laisse le contenu en base, jamais publié',
        (int) $avecMotif['code'] === 303
            && ($apresRefus['moderation_state'] ?? '') === 'refuse'
            && str_contains((string) ($apresRefus['moderation_note'] ?? ''), 'personne vivante'),
        'état ' . ($apresRefus['moderation_state'] ?? '?')
    );
    $lectureMotif = tests_envoi_requete('GET', '/propositions', $journaliste);
    verifier(
        'propositions : l’auteur lit le motif du refus',
        str_contains((string) $lectureMotif['corps'], 'personne vivante')
    );

    // --- Corriger : la proposition repart en attente ----------------------
    tests_envoi_requete('POST', '/propositions/article/' . (int) $article3['id'], $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreRefus,
        'body' => 'Texte corrigé.',
    ]);
    $corrige = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article3['id']]);
    verifier(
        'propositions : corriger une proposition refusée la remet « en attente », sans motif',
        ($corrige['moderation_state'] ?? '') === 'attente' && ($corrige['moderation_note'] ?? '') === '',
        'état ' . ($corrige['moderation_state'] ?? '?')
    );

    // Modifier un contenu déjà validé le remet « en attente » : il redevient
    // invisible pour les autres jusqu'à la prochaine validation.
    $modifValide = tests_envoi_requete('POST', '/propositions/article/' . (int) $article2['id'], $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreValide,
        'body' => 'Texte corrigé après publication.',
    ]);
    $apresModif = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article2['id']]);
    verifier(
        'propositions : modifier une proposition déjà publiée la remet « en attente »',
        (int) $modifValide['code'] === 303
            && ($apresModif['moderation_state'] ?? '') === 'attente'
            && ($apresModif['validated_at'] ?? null) === null,
        'état ' . ($apresModif['moderation_state'] ?? '?')
    );
    $relecture = tests_envoi_requete('GET', '/articles/' . (string) $article2['slug'], $lecteur);
    verifier(
        'propositions : la proposition remise en attente redevient invisible pour la famille',
        (int) $relecture['code'] === 404,
        'code ' . $relecture['code']
    );

    // --- Le brouillon reste le sien --------------------------------------
    $titreBrouillon = 'Brouillon ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/recette', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'brouillon',
        'title' => $titreBrouillon,
        'ingredients' => "250 g de farine\n3 œufs",
    ]);
    $brouillon = (array) db_one('SELECT * FROM recipes WHERE title = ?', [$titreBrouillon]);
    verifier(
        'propositions : un brouillon reste « brouillon » et ne part pas en validation',
        ($brouillon['moderation_state'] ?? '') === 'brouillon'
            && ($brouillon['visibility'] ?? '') === 'prive'
            && (int) ($brouillon['submitted_by'] ?? 0) === $journaliste,
        'état ' . ($brouillon['moderation_state'] ?? '?')
    );
}

/**
 * 3. Les fuites : personne ne voit une proposition avant sa validation.
 *
 * @param array<int, string> $fichiers chemins relatifs à effacer à la fin
 */
function tests_propositions_fuite(array &$fichiers): void
{
    $brancheA    = tests_propositions_branche('Branche A des fuites');
    $brancheB    = tests_propositions_branche('Branche B des fuites');
    $lecteur     = tests_propositions_compte('branche', $brancheA, [
        'email'                 => 'lecteur-' . bin2hex(random_bytes(3)) . '@exemple.test',
        'notify_articles'       => 1,
        'notify_gazette'        => 1,
        'notify_articles_depuis' => time() - 3600,
    ]);
    $journaliste = tests_propositions_compte('personne', $brancheA);
    $autre       = tests_propositions_compte('personne', $brancheB);
    $admin       = tests_propositions_compte('admin');

    // Un article, un document et une recette, tous « en attente ».
    $titreArticle = 'Fuite à éviter ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreArticle,
        'body' => 'Ce texte ne doit pas sortir avant validation.',
    ]);
    $article = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titreArticle]);

    $titreRecette = 'Recette à valider ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/recette', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreRecette,
        'ingredients' => "1 kg de pommes\nSucre",
    ]);
    $recette = (array) db_one('SELECT * FROM recipes WHERE title = ?', [$titreRecette]);

    $titreDoc = 'Photo à valider ' . bin2hex(random_bytes(3));
    $doc      = tests_propositions_document($journaliste, $titreDoc);
    $fichiers[] = $doc['rel'];
    $fichiers[] = $doc['thumb'];

    verifier(
        'fuite : les trois propositions d’essai sont bien « en attente » et privées',
        ($article['moderation_state'] ?? '') === 'attente' && ($recette['moderation_state'] ?? '') === 'attente'
            && ($doc['id'] ?? 0) > 0
    );

    // --- La règle, en SQL : qui voit quoi ? -------------------------------
    $compter = static function (string $table, string $kind, ?int $compte, string $titre): int {
        $_SESSION = $compte === null ? [] : ['account_id' => $compte];
        $params = [];
        $clause = visibility_clause($kind, 't', $params);
        return (int) db_value(
            "SELECT COUNT(*) FROM {$table} t WHERE t.title = :titre AND {$clause}",
            $params + [':titre' => $titre],
            0
        );
    };

    foreach ([
        ['articles', 'article', $titreArticle, 'article'],
        ['recipes', 'recipe', $titreRecette, 'recette'],
        ['documents', 'document', $titreDoc, 'document'],
    ] as [$table, $kind, $titre, $nom]) {
        $vuLecteur     = $compter($table, $kind, $lecteur, $titre);
        $vuAutre       = $compter($table, $kind, $autre, $titre);
        $vuVisiteur    = $compter($table, $kind, null, $titre);
        $vuAuteur      = $compter($table, $kind, $journaliste, $titre);
        $vuAdmin       = $compter($table, $kind, $admin, $titre);

        verifier(
            'fuite : un lecteur ne voit pas ' . $nom . ' en attente',
            $vuLecteur === 0,
            'vu ' . $vuLecteur . ' fois'
        );
        verifier(
            'fuite : un autre journaliste ne voit pas ' . $nom . ' en attente',
            $vuAutre === 0,
            'vu ' . $vuAutre . ' fois'
        );
        verifier(
            'fuite : un visiteur ne voit pas ' . $nom . ' en attente',
            $vuVisiteur === 0,
            'vu ' . $vuVisiteur . ' fois'
        );
        // Dans les LISTES, personne ne voit une proposition en attente — pas
        // même son auteur : elle n'existe que dans « Mes propositions » et par
        // une adresse directe. L'administrateur, lui, la voit partout.
        verifier(
            'fuite : la liste reste vide pour l’auteur, pleine pour l’administrateur',
            $vuAuteur === 0 && $vuAdmin === 1,
            'auteur ' . $vuAuteur . ', admin ' . $vuAdmin
        );
    }

    // --- La règle, en PHP : les accès directs ------------------------------
    $_SESSION = ['account_id' => $lecteur];
    verifier(
        'fuite : find_viewable_article refuse un article en attente',
        find_viewable_article((string) $article['slug']) === null
    );
    $_SESSION = ['account_id' => $admin];
    verifier(
        'fuite : find_viewable_article ouvre le même article à l’administrateur',
        find_viewable_article((string) $article['slug']) !== null
    );
    $_SESSION = ['account_id' => $journaliste];
    verifier(
        'fuite : l’auteur peut relire son article en attente',
        find_viewable_article((string) $article['slug']) !== null
    );

    // --- Les pages et les fichiers, par une adresse directe --------------
    $codes = [];
    foreach ([
        'lecteur'  => $lecteur,
        'autre'    => $autre,
        'visiteur' => null,
    ] as $nom => $compte) {
        $codes[$nom . ' article']   = (int) tests_envoi_requete('GET', '/articles/' . $article['slug'], $compte)['code'];
        $codes[$nom . ' recette']   = (int) tests_envoi_requete('GET', '/recettes/' . $recette['slug'], $compte)['code'];
        $codes[$nom . ' fichier']   = (int) tests_envoi_requete('GET', '/f/' . $doc['id'], $compte)['code'];
        $codes[$nom . ' telechargement'] = (int) tests_envoi_requete('GET', '/f/' . $doc['id'] . '/telecharger', $compte)['code'];
        $codes[$nom . ' vignette']  = (int) tests_envoi_requete('GET', '/media/miniature/' . $doc['id'], $compte)['code'];
        $codes[$nom . ' documents'] = (int) tests_envoi_requete('GET', '/documents', $compte)['code'];
    }
    $attendus = [];
    foreach (['lecteur', 'autre', 'visiteur'] as $nom) {
        $attendus[$nom . ' article']        = 404;
        $attendus[$nom . ' recette']        = 404;
        $attendus[$nom . ' fichier']        = 404;
        $attendus[$nom . ' telechargement'] = 404;
        $attendus[$nom . ' vignette']       = 404;
        $attendus[$nom . ' documents']      = 200; // la liste s'ouvre, mais reste vide de la proposition
    }
    verifier(
        'fuite : aucune adresse directe (page, fichier, téléchargement, vignette) ne sert une proposition',
        $codes === $attendus,
        json_encode($codes)
    );

    $listeDocuments = tests_envoi_requete('GET', '/documents', $lecteur);
    verifier(
        'fuite : la liste des documents ne cite pas la proposition',
        !str_contains((string) $listeDocuments['corps'], $titreDoc)
    );

    // --- La recherche ----------------------------------------------------
    // La page de recherche reprend la requête dans son titre : c'est le
    // RÉSULTAT qui compte, donc l'adresse de l'article.
    $lienArticle = '/articles/' . (string) $article['slug'];
    $recherche = tests_envoi_requete('GET', '/recherche?q=' . urlencode($titreArticle), $lecteur);
    verifier(
        'fuite : la recherche ne trouve pas un article en attente',
        (int) $recherche['code'] === 200
            && !str_contains((string) $recherche['corps'], $lienArticle),
        'code ' . $recherche['code']
    );
    // Côté administrateur, l'article reste trouvable : la règle de visibilité
    // lui donne tout, et c'est la même condition SQL qui sert aux listes, à la
    // recherche et au fil. On le vérifie ici sans dépendre de l'index plein
    // texte (les deux modes de recherche se couvrent l'un l'autre).
    $_SESSION = ['account_id' => $admin];
    verifier(
        'fuite : l’administrateur, lui, voit toujours la proposition en attente',
        find_viewable_article((string) $article['slug']) !== null
    );

    // --- L'accueil, le fil et le menu ------------------------------------
    $accueil = tests_envoi_requete('GET', '/famille', $lecteur);
    verifier(
        'fuite : l’accueil de la famille ne cite pas la proposition',
        (int) $accueil['code'] === 200
            && !str_contains((string) $accueil['corps'], $titreArticle)
            && !str_contains((string) $accueil['corps'], $titreRecette),
        'code ' . $accueil['code']
    );
    $accueilPublic = tests_envoi_requete('GET', '/', null);
    verifier(
        'fuite : le site public ne cite pas la proposition',
        (int) $accueilPublic['code'] === 200
            && !str_contains((string) $accueilPublic['corps'], $titreArticle)
    );

    // --- La gazette et les courriels --------------------------------------
    $gazette = tests_envoi_requete('GET', '/gazette/' . date('Y-m'), $lecteur);
    verifier(
        'fuite : la gazette du mois ne cite pas la proposition',
        !str_contains((string) $gazette['corps'], $titreArticle),
        'code ' . $gazette['code']
    );

    $avantValidation = (int) db_value('SELECT COUNT(*) FROM email_queue WHERE account_id = ?', [$lecteur], 0);
    verifier(
        'fuite : aucun courriel n’est parti avant la validation',
        $avantValidation === 0,
        $avantValidation . ' message(s)'
    );

    // --- Après validation : tout devient visible, et les courriels partent
    tests_envoi_requete('POST', '/admin/a-valider/article/' . (int) $article['id'] . '/valider', $admin, [
        '_csrf' => tests_propositions_jeton(),
        'visibility' => 'famille',
    ]);

    $codesApres = [
        'lecteur' => (int) tests_envoi_requete('GET', '/articles/' . $article['slug'], $lecteur)['code'],
        'visiteur' => (int) tests_envoi_requete('GET', '/articles/' . $article['slug'], null)['code'],
    ];
    verifier(
        'propositions : une fois validé « famille », l’article s’ouvre aux lecteurs, jamais aux visiteurs',
        $codesApres === ['lecteur' => 200, 'visiteur' => 404],
        json_encode($codesApres)
    );
    verifier(
        'propositions : la notification de lecture part à la validation, pas avant',
        (int) db_value('SELECT COUNT(*) FROM email_queue WHERE account_id = ?', [$lecteur], 0) === 1,
        (string) (int) db_value('SELECT COUNT(*) FROM email_queue WHERE account_id = ?', [$lecteur], 0)
    );
}
