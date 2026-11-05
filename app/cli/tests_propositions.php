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
            tests_propositions_cas_limites($fichiers);

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

    // Modifier un contenu DÉJÀ PUBLIÉ ne change rien pour les lecteurs : la
    // modification devient une version en attente, et le texte en ligne reste
    // celui qui a été validé jusqu'à la prochaine relecture.
    $modifValide = tests_envoi_requete('POST', '/propositions/article/' . (int) $article2['id'], $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreValide,
        'body' => 'Texte corrigé après publication.',
    ]);
    $original = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article2['id']]);
    $version  = proposition_version_en_attente('article', (int) $article2['id']);
    verifier(
        'propositions : modifier un contenu publié crée une VERSION, sans toucher au texte en ligne',
        (int) $modifValide['code'] === 303
            && ($original['moderation_state'] ?? '') === 'publie'
            && (string) ($original['body'] ?? '') === 'Texte validé.'
            && $version !== null
            && (string) $version['body'] === 'Texte corrigé après publication.'
            && (string) $version['moderation_state'] === 'attente'
            && (int) $version['replaces_id'] === (int) $article2['id'],
        json_encode([$original['moderation_state'] ?? '?', $original['body'] ?? '?', (int) ($version['id'] ?? 0)])
    );
    $relecture = tests_envoi_requete('GET', '/articles/' . (string) $article2['slug'], $lecteur);
    verifier(
        'propositions : la famille lit toujours l’ANCIEN texte, publié, pendant la relecture',
        (int) $relecture['code'] === 200
            && str_contains((string) $relecture['corps'], 'Texte validé.')
            && !str_contains((string) $relecture['corps'], 'Texte corrigé après publication.'),
        'code ' . $relecture['code']
    );
    verifier(
        'propositions : la version attend dans « À valider » (le compteur la compte)',
        propositions_a_valider() >= 1
            && in_array((int) $version['id'], array_map(
                static fn (array $l): int => (int) ($l['row']['id'] ?? 0),
                array_values(array_filter(
                    propositions_en_attente(),
                    static fn (array $l): bool => (string) ($l['type'] ?? '') === 'article'
                ))
            ), true),
        'compteur ' . propositions_a_valider()
    );

    // Une seule version en attente à la fois : la seconde modification
    // reprend la version existante au lieu d'en créer une autre.
    tests_envoi_requete('POST', '/propositions/article/' . (int) $article2['id'], $journaliste, [
        '_csrf' => tests_propositions_jeton(),
        'action' => 'proposer',
        'title' => $titreValide,
        'body' => 'Deuxième correction.',
    ]);
    verifier(
        'propositions : une deuxième modification ne crée pas une seconde version (elle reprend celle qui attend)',
        (int) db_value(
            'SELECT COUNT(*) FROM articles WHERE replaces_id = ?',
            [(int) $article2['id']],
            0
        ) === 1
    );

    // La validation de la version met à jour l'original, et la version
    // disparaît : le contenu publié change à cet instant précis, pas avant.
    $versionId = (int) $version['id'];
    tests_envoi_requete('POST', '/admin/a-valider/article/' . $versionId . '/valider', $admin, [
        '_csrf' => tests_propositions_jeton(),
        'visibility' => 'famille',
    ]);
    $apresValidation = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $article2['id']]);
    verifier(
        'propositions : valider la version met à jour le contenu publié et efface la version',
        (string) $apresValidation['body'] === 'Texte corrigé après publication.'
            && (string) $apresValidation['moderation_state'] === 'publie'
            && db_one('SELECT * FROM articles WHERE id = ?', [$versionId]) === null,
        json_encode([$apresValidation['body'] ?? '?', $apresValidation['moderation_state'] ?? '?'])
    );
    $relectureApres = tests_envoi_requete('GET', '/articles/' . (string) $apresValidation['slug'], $lecteur);
    verifier(
        'propositions : après validation, la famille lit le nouveau texte (même adresse)',
        (int) $relectureApres['code'] === 200
            && str_contains((string) $relectureApres['corps'], 'Texte corrigé après publication.'),
        'code ' . $relectureApres['code']
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

/**
 * 4. Cas limites demandés avant l'ouverture (1.26.0) : retrait d'une
 * proposition, compte désactivé, motif de refus.
 *
 * @param array<int, string> $fichiers chemins relatifs à effacer à la fin
 */
function tests_propositions_cas_limites(array &$fichiers): void
{
    $admin       = tests_propositions_compte('admin');
    $journaliste = tests_propositions_compte('personne');
    $autre       = tests_propositions_compte('personne');
    $lecteur     = tests_propositions_compte('branche');

    // --- Retirer une proposition en attente --------------------------------
    $titreRetire = 'Article à retirer ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf'  => tests_propositions_jeton(),
        'action' => 'proposer',
        'title'  => $titreRetire,
        'body'   => 'Texte jamais publié.',
    ]);
    $aRetirer = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titreRetire]);
    verifier(
        'cas limite : la proposition à retirer existe et attend',
        (int) ($aRetirer['id'] ?? 0) > 0 && (string) $aRetirer['moderation_state'] === 'attente'
    );

    $retraitAutre = tests_envoi_requete('POST', '/propositions/article/' . (int) $aRetirer['id'] . '/retirer', $autre, [
        '_csrf' => tests_propositions_jeton(),
    ]);
    verifier(
        'cas limite : un autre journaliste ne peut pas retirer la proposition de quelqu’un d’autre',
        (int) $retraitAutre['code'] === 404
            && db_one('SELECT * FROM articles WHERE id = ?', [(int) $aRetirer['id']]) !== null,
        'code ' . $retraitAutre['code']
    );

    $retrait = tests_envoi_requete('POST', '/propositions/article/' . (int) $aRetirer['id'] . '/retirer', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
    ]);
    verifier(
        'cas limite : l’auteur retire sa proposition en attente, elle disparaît de « À valider »',
        (int) $retrait['code'] === 303
            && db_one('SELECT * FROM articles WHERE id = ?', [(int) $aRetirer['id']]) === null
            && !in_array('article', array_map(
                static fn (array $l): string => (string) ($l['row']['id'] ?? 0) === (string) $aRetirer['id'] ? 'article' : '',
                propositions_en_attente()
            ), true),
        'code ' . $retrait['code']
    );

    // Un document retiré ne laisse pas ses fichiers derrière lui.
    $titreDocRetire = 'Document à retirer ' . bin2hex(random_bytes(3));
    $docRetire = tests_propositions_document($journaliste, $titreDocRetire);
    verifier(
        'cas limite : les fichiers du document à retirer sont bien sur le disque avant retrait',
        is_file($docRetire['abs'])
    );
    $retraitDoc = tests_envoi_requete('POST', '/propositions/document/' . (int) $docRetire['id'] . '/retirer', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
    ]);
    verifier(
        'cas limite : retirer un document efface ses fichiers (aucun orphelin sur la carte SD)',
        (int) $retraitDoc['code'] === 303
            && db_one('SELECT * FROM documents WHERE id = ?', [(int) $docRetire['id']]) === null
            && !is_file($docRetire['abs'])
            && !is_file(storage_absolute($docRetire['thumb'])),
        'code ' . $retraitDoc['code'] . ' — fichier ' . var_export(is_file($docRetire['abs']), true)
    );

    // Un contenu publié ne se retire pas par ce chemin.
    $titrePublie = 'Article publié à ne pas retirer ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf'  => tests_propositions_jeton(),
        'action' => 'proposer',
        'title'  => $titrePublie,
        'body'   => 'Texte publié.',
    ]);
    $publie = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titrePublie]);
    tests_envoi_requete('POST', '/admin/a-valider/article/' . (int) $publie['id'] . '/valider', $admin, [
        '_csrf'      => tests_propositions_jeton(),
        'visibility' => 'famille',
    ]);
    $retraitPublie = tests_envoi_requete('POST', '/propositions/article/' . (int) $publie['id'] . '/retirer', $journaliste, [
        '_csrf' => tests_propositions_jeton(),
    ]);
    verifier(
        'cas limite : un contenu publié ne peut pas être retiré par son auteur (il faudrait le dépublier)',
        (int) $retraitPublie['code'] === 303
            && db_one('SELECT * FROM articles WHERE id = ?', [(int) $publie['id']]) !== null
    );

    // --- Motif de refus : auteur et administrateurs seulement --------------
    $titreRefus = 'Article refusé avec motif ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $journaliste, [
        '_csrf'  => tests_propositions_jeton(),
        'action' => 'proposer',
        'title'  => $titreRefus,
        'body'   => 'Texte à refuser.',
    ]);
    $aRefuser = (array) db_one('SELECT * FROM articles WHERE title = ?', [$titreRefus]);
    $motif = 'Il manque les dates et les lieux, mots-du-motif-' . bin2hex(random_bytes(2));
    tests_envoi_requete('POST', '/admin/a-valider/article/' . (int) $aRefuser['id'] . '/refuser', $admin, [
        '_csrf' => tests_propositions_jeton(),
        'motif' => $motif,
    ]);
    $vuAuteur = tests_envoi_requete('GET', '/propositions', $journaliste);
    verifier(
        'cas limite : l’auteur lit le motif du refus dans « Mes propositions »',
        str_contains((string) $vuAuteur['corps'], 'mots-du-motif-'),
        'code ' . $vuAuteur['code']
    );
    $vuAdmin = tests_envoi_requete('GET', '/admin/a-valider/article/' . (int) $aRefuser['id'], $admin);
    verifier(
        'cas limite : l’administrateur relit aussi le motif',
        (int) $vuAdmin['code'] === 200 && str_contains((string) $vuAdmin['corps'], 'mots-du-motif-'),
        'code ' . $vuAdmin['code']
    );
    $motifAilleurs = [];
    foreach ([
        'liste des articles' => tests_envoi_requete('GET', '/articles', $lecteur),
        'accueil famille'    => tests_envoi_requete('GET', '/famille', $lecteur),
        'site public'        => tests_envoi_requete('GET', '/', null),
        'recherche'          => tests_envoi_requete('GET', '/recherche?q=' . urlencode($titreRefus), $lecteur),
        'propositions'       => tests_envoi_requete('GET', '/propositions', $lecteur),
    ] as $nom => $reponse) {
        $motifAilleurs[$nom] = str_contains((string) $reponse['corps'], 'mots-du-motif-');
    }
    verifier(
        'cas limite : le motif du refus n’apparaît nulle part ailleurs (lecteur, visiteur)',
        array_filter($motifAilleurs) === [],
        json_encode($motifAilleurs)
    );

    // --- Journaliste désactivé --------------------------------------------
    $desactive = tests_propositions_compte('personne', null, [
        'username'     => 'desactive-' . bin2hex(random_bytes(3)),
        'password_hash' => password_make('MotDePasseEssai123!'),
    ]);
    $titreDesactive = 'Article d’un compte désactivé ' . bin2hex(random_bytes(3));
    tests_envoi_requete('POST', '/propositions/nouvelle/article', $desactive, [
        '_csrf'  => tests_propositions_jeton(),
        'action' => 'proposer',
        'title'  => $titreDesactive,
        'body'   => 'Texte déposé avant la désactivation.',
    ]);
    $articleDesactive = db_one('SELECT * FROM articles WHERE title = ?', [$titreDesactive]);
    db_update('accounts', $desactive, ['is_active' => 0]);
    $_SESSION = [];

    $identifiant = (string) db_one('SELECT username FROM accounts WHERE id = ?', [$desactive])['username'];
    $connexion = auth_attempt($identifiant, 'MotDePasseEssai123!');
    verifier(
        'cas limite : un journaliste désactivé ne peut plus se connecter',
        ($connexion['ok'] ?? true) === false && empty($_SESSION['account_id']),
        json_encode(['ok' => $connexion['ok'] ?? null, 'message' => $connexion['error'] ?? ''])
    );
    $enAttente = propositions_en_attente();
    $trouve = false;
    foreach ($enAttente as $element) {
        if ((int) ($element['row']['id'] ?? 0) === (int) ($articleDesactive['id'] ?? 0)
            && (string) ($element['type'] ?? '') === 'article') {
            $trouve = true;
        }
    }
    verifier(
        'cas limite : son article en attente reste visible des administrateurs',
        $articleDesactive !== null && $trouve,
        json_encode(array_map(static fn (array $l): string => (string) ($l['type'] ?? ''), $enAttente))
    );
    $fileAdmin = tests_envoi_requete('GET', '/admin/a-valider', $admin);
    verifier(
        'cas limite : la file « À valider » de l’administrateur cite encore ce dépôt',
        (int) $fileAdmin['code'] === 200 && str_contains((string) $fileAdmin['corps'], $titreDesactive),
        'code ' . $fileAdmin['code']
    );

    // Nettoyage : les fichiers créés ici sont effacés par l'appelant.
    $fichiers[] = $docRetire['rel'];
    $fichiers[] = $docRetire['thumb'];
}
