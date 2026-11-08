<?php
/**
 * Tests de la gazette du mois (lot 2).
 *
 * Tout se passe dans une transaction annulée : la base du site est rendue
 * intacte. Aucun courriel ne part : on ne fait que remplir la file.
 */

declare(strict_types=1);

/** Document fictif (aucun fichier sur le disque). */
function tests_gazette_document(string $titre, string $kind, string $visibilite, int $quand, string $mime = ''): int
{
    if ($mime === '') {
        $mime = match ($kind) {
            'photo'  => 'image/jpeg',
            'video'  => 'video/mp4',
            default  => 'application/pdf',
        };
    }

    return db_insert('documents', [
        'title'         => $titre,
        'description'   => '',
        'kind'          => $kind,
        'visibility'    => $visibilite,
        'rel_path'      => 'medias/essai-' . bin2hex(random_bytes(4)) . '.bin',
        'original_name' => 'essai.bin',
        'mime'          => $mime,
        'ext'           => 'bin',
        'size'          => 1024,
        'is_attachment' => 0,
        'created_at'    => $quand,
        'updated_at'    => $quand,
    ]);
}

function tests_gazette(): void
{
    echo "Gazette du mois — groupes de droits, préparation, validation, envoi\n";

    // -----------------------------------------------------------------------
    // 1. Le mois raconté
    // -----------------------------------------------------------------------
    $juin = mktime(12, 0, 0, 6, 15, 2024);
    verifier(
        'gazette : le numéro d’un mois raconte le mois écoulé',
        gazette_mois_ecoule($juin) === '2024-05',
        gazette_mois_ecoule($juin)
    );
    verifier(
        'gazette : le libellé est écrit en français',
        gazette_mois_libelle('2024-05') === 'mai 2024'
        && gazette_mois_libelle('2024-01') === 'janvier 2024'
        && gazette_mois_libelle('2024-12') === 'décembre 2024',
        gazette_mois_libelle('2024-05')
    );
    $bornes = gazette_mois_bornes('2024-05');
    verifier(
        'gazette : les bornes du mois sont justes (du 1er au 1er suivant)',
        date('Y-m-d H:i', $bornes['debut']) === '2024-05-01 00:00'
        && $bornes['fin'] - $bornes['debut'] === 31 * 86400,
        date('Y-m-d H:i', $bornes['debut']) . ' → ' . date('Y-m-d H:i', $bornes['fin'])
    );
    verifier(
        'gazette : une période mal écrite ne casse rien',
        gazette_mois_bornes('n’importe quoi') === ['debut' => 0, 'fin' => 0]
        && gazette_mois_libelle('n’importe quoi') === 'n’importe quoi'
    );

    $pdo = db();
    $transaction = false;

    try {
        $pdo->beginTransaction();
        $transaction = true;

        // La base de test doit rester sans gazette : on part d'un mois ancien.
        db_exec("DELETE FROM gazettes WHERE periode = '2024-05'");

        // -------------------------------------------------------------------
        // 2. Les groupes de droits
        // -------------------------------------------------------------------
        $brancheA = db_insert('branches', [
            'code' => 'GAZ-A', 'name' => 'Branche A (gazette)', 'description' => '',
            'see_all' => 0, 'is_active' => 1, 'created_at' => time(), 'updated_at' => time(),
        ]);
        $brancheB = db_insert('branches', [
            'code' => 'GAZ-B', 'name' => 'Branche B (gazette)', 'description' => '',
            'see_all' => 0, 'is_active' => 1, 'created_at' => time(), 'updated_at' => time(),
        ]);

        $inscritA   = tests_courriel_compte(['email' => 'gaz.a@exemple.fr', 'notify_gazette' => 1, 'branch_id' => $brancheA]);
        $inscritA2  = tests_courriel_compte(['email' => 'gaz.a2@exemple.fr', 'notify_gazette' => 1, 'branch_id' => $brancheA]);
        $inscritB   = tests_courriel_compte(['email' => 'gaz.b@exemple.fr', 'notify_gazette' => 1, 'branch_id' => $brancheB]);
        $voitTout   = tests_courriel_compte(['email' => 'gaz.tout@exemple.fr', 'notify_gazette' => 1,
            'branch_id' => $brancheB, 'see_all' => 1]);
        $adminGaz   = tests_courriel_compte(['email' => 'gaz.admin@exemple.fr', 'notify_gazette' => 1,
            'role' => 'admin', 'account_type' => 'admin']);
        // Sans accord (ou sans adresse) : hors des groupes.
        $sansAccord = tests_courriel_compte(['email' => 'gaz.non@exemple.fr', 'notify_gazette' => 0, 'branch_id' => $brancheA]);
        $sansAdresse = tests_courriel_compte(['notify_gazette' => 1, 'branch_id' => $brancheA]);
        // Administrateur sans accord : ne reçoit rien non plus.
        $adminSansAccord = tests_courriel_compte(['email' => 'gaz.admin2@exemple.fr', 'notify_gazette' => 0,
            'role' => 'admin', 'account_type' => 'admin']);

        $profils = gazette_profils();
        verifier(
            'gazette : un groupe de droits par façon de voir',
            isset($profils['branche:' . $brancheA], $profils['branche:' . $brancheB], $profils['tout']),
            implode(', ', array_keys($profils))
        );
        verifier(
            'gazette : chaque personne inscrite appartient à son groupe',
            in_array($inscritA, $profils['branche:' . $brancheA]['comptes'], true)
            && in_array($inscritA2, $profils['branche:' . $brancheA]['comptes'], true)
            && in_array($inscritB, $profils['branche:' . $brancheB]['comptes'], true)
            && in_array($voitTout, $profils['tout']['comptes'], true)
            && in_array($adminGaz, $profils['tout']['comptes'], true)
        );
        verifier(
            'gazette : sans accord, sans adresse ou désinscrit, on n’est dans aucun groupe',
            !in_array($sansAccord, $profils['branche:' . $brancheA]['comptes'], true)
            && !in_array($sansAdresse, $profils['branche:' . $brancheA]['comptes'], true)
            && !in_array($adminSansAccord, $profils['tout']['comptes'], true)
        );
        verifier(
            'gazette : le groupe « voit tout » n’est pas mélangé avec une branche',
            !in_array($voitTout, $profils['branche:' . $brancheB]['comptes'], true)
        );

        // -------------------------------------------------------------------
        // 3. Le contenu du mois, vu par chaque groupe
        // -------------------------------------------------------------------
        $mai = gazette_mois_bornes('2024-05');
        $dansLeMois = $mai['debut'] + 5 * 86400;
        $horsMois   = $mai['debut'] - 5 * 86400;
        $secretA    = 'SECRET-BRANCHE-A-' . bin2hex(random_bytes(3));
        $secretB    = 'SECRET-BRANCHE-B-' . bin2hex(random_bytes(3));
        $secretPrive = 'SECRET-PRIVE-' . bin2hex(random_bytes(3));

        $articlePublic = tests_courriel_article('Article public du mois', 'public', $dansLeMois);
        $articleA      = tests_courriel_article('Article de la branche A', 'branches', $dansLeMois);
        set_content_branches('article', $articleA, [$brancheA]);
        db_update('articles', $articleA, ['title' => 'Article ' . $secretA]);
        $articleB      = tests_courriel_article('Article de la branche B', 'branches', $dansLeMois);
        set_content_branches('article', $articleB, [$brancheB]);
        db_update('articles', $articleB, ['title' => 'Article ' . $secretB]);
        $articlePrive  = tests_courriel_article('Article privé du mois', 'prive', $dansLeMois);
        db_update('articles', $articlePrive, ['title' => 'Article ' . $secretPrive]);
        $articleVieux  = tests_courriel_article('Article du mois précédent', 'public', $horsMois);

        $photoPublique = tests_gazette_document('Photo du mois', 'photo', 'public', $dansLeMois);
        $video          = tests_gazette_document('Vidéo du mois', 'video', 'famille', $dansLeMois);
        $audio          = tests_gazette_document('Témoignage du mois', 'document', 'famille', $dansLeMois, 'audio/mpeg');
        $album          = db_insert('albums', [
            'title' => 'Album du mois', 'slug' => 'album-essai-' . bin2hex(random_bytes(3)),
            'description' => '', 'visibility' => 'public', 'position' => 0,
            'created_at' => $dansLeMois, 'updated_at' => $dansLeMois,
        ]);
        $recette        = db_insert('recipes', [
            'title' => 'Recette du mois', 'slug' => 'recette-essai-' . bin2hex(random_bytes(3)),
            'origin' => 'Famille', 'servings' => '4', 'ingredients' => 'farine',
            'preparation' => 'mélanger', 'visibility' => 'public',
            'created_at' => $dansLeMois, 'updated_at' => $dansLeMois,
        ]);
        $personne       = db_insert('persons', [
            'first_name' => 'Nouvelle', 'last_name' => 'Personne', 'visibility' => 'public',
            'created_at' => $dansLeMois, 'updated_at' => $dansLeMois,
        ]);

        $vueA = gazette_contenu('2024-05', $inscritA);
        $vueTout = gazette_contenu('2024-05', $adminGaz);

        $titreDes = static function (array $contenu): array {
            $titres = [];
            foreach ((array) $contenu['sections'] as $section) {
                foreach ((array) $section['items'] as $item) {
                    $titres[] = (string) $item['titre'];
                }
            }
            return $titres;
        };
        $titresA    = $titreDes($vueA);
        $titresTout = $titreDes($vueTout);

        verifier(
            'gazette : le contenu du mois est repris (articles, photos, vidéos, témoignages, recettes, personnes)',
            in_array('Article public du mois', $titresA, true)
            && in_array('Photo du mois', $titresA, true)
            && in_array('Vidéo du mois', $titresA, true)
            && in_array('Témoignage du mois', $titresA, true)
            && in_array('Recette du mois', $titresA, true)
            && in_array('Nouvelle PERSONNE', $titresA, true),
            implode(' | ', $titresA)
        );
        verifier(
            'gazette : ce qui date d’un autre mois n’est pas repris',
            !in_array('Article du mois précédent', $titresA, true)
        );

        verifier(
            'gazette : le numéro d’une branche ne contient rien de l’autre branche',
            in_array('Article ' . $secretA, $titresA, true)
            && !in_array('Article ' . $secretB, $titresA, true)
            && !in_array('Article ' . $secretPrive, $titresA, true),
            implode(' | ', $titresA)
        );
        verifier(
            'gazette : le numéro « voit tout » contient les trois articles réservés',
            in_array('Article ' . $secretA, $titresTout, true)
            && in_array('Article ' . $secretB, $titresTout, true)
            && in_array('Article ' . $secretPrive, $titresTout, true)
        );

        // L'ordre des encarts et les titres réglables.
        $encarts = array_column($vueA['sections'], 'cle');
        verifier(
            'gazette : les encarts sont toujours dans le même ordre',
            $encarts === ['articles', 'photos', 'videos', 'temoignages', 'recettes', 'personnes', 'annonces'],
            implode(', ', $encarts)
        );
        verifier(
            'gazette : un encart vide est ignoré à l’affichage',
            gazette_section_titre('recettes') === apparence_titre_encart('gazette_recettes')
        );

        // -------------------------------------------------------------------
        // 4. Préparation
        // -------------------------------------------------------------------
        db_exec("DELETE FROM gazettes WHERE periode = '2024-05'");
        $prep = gazette_preparer(mktime(12, 0, 0, 6, 1, 2024));
        verifier(
            'gazette : le 1er du mois prépare un numéro par groupe',
            $prep['prepares'] === count($profils) && count($prep['groupes']) === count($profils),
            $prep['prepares'] . ' numéro(s) : ' . implode(', ', $prep['groupes'])
        );

        $lignes = db_all("SELECT * FROM gazettes WHERE periode = '2024-05' ORDER BY groupe");
        verifier(
            'gazette : chaque numéro attend la validation de l’éditeur',
            $lignes !== [] && count(array_filter($lignes, static fn (array $l): bool => $l['status'] === 'a_valider')) === count($lignes)
        );
        verifier(
            'gazette : préparer deux fois ne crée pas de doublon',
            gazette_preparer(mktime(12, 0, 0, 6, 2, 2024))['prepares'] === 0
            && (int) db_value("SELECT COUNT(*) FROM gazettes WHERE periode = '2024-05'", [], 0) === count($lignes)
        );
        verifier(
            'gazette : un mois vide ne produit aucun numéro',
            gazette_preparer(mktime(12, 0, 0, 3, 1, 2024))['prepares'] === 0
            && (int) db_value("SELECT COUNT(*) FROM gazettes WHERE periode = '2024-02'", [], 0) === 0
        );

        $ligneA = null;
        foreach ($lignes as $ligne) {
            if ($ligne['groupe'] === 'branche:' . $brancheA) {
                $ligneA = $ligne;
            }
        }
        verifier('gazette : le numéro de la branche A existe', $ligneA !== null);

        $idA = (int) ($ligneA['id'] ?? 0);
        $gazetteA = gazette_trouver($idA);
        verifier(
            'gazette : le contenu est figé à la préparation',
            $gazetteA !== null
            && in_array('Article ' . $secretA, $titreDes($gazetteA['contenu_array']), true)
            && !in_array('Article ' . $secretB, $titreDes($gazetteA['contenu_array']), true)
        );

        // Un article publié plus tard dans le mois ne change pas le numéro
        // déjà préparé : c'est un instantané.
        $tardif = tests_courriel_article('Article arrivé après', 'public', $dansLeMois + 86400);
        $apres = gazette_trouver($idA);
        verifier(
            'gazette : un contenu ajouté après la préparation ne s’invite pas dans le numéro',
            !in_array('Article arrivé après', $titreDes($apres['contenu_array']), true)
        );
        db_update('articles', $tardif, ['status' => 'brouillon']);

        // -------------------------------------------------------------------
        // 5. Retirer un élément, mot de l'éditeur
        // -------------------------------------------------------------------
        $cleSecretA = '';
        foreach (gazette_cles($gazetteA['contenu_array']) as $cle => $titre) {
            if ($titre === 'Article ' . $secretA) {
                $cleSecretA = $cle;
            }
        }
        verifier('gazette : chaque élément a une clé stable', str_starts_with($cleSecretA, 'article:'));

        verifier(
            'gazette : retirer un élément le sort de l’aperçu',
            gazette_retirer($idA, $cleSecretA)
            && !in_array('Article ' . $secretA, $titreDes(gazette_trouver($idA)['apercu']), true)
        );
        verifier(
            'gazette : le contenu d’origine reste intact (on peut remettre)',
            gazette_retirer($idA, $cleSecretA, false)
            && in_array('Article ' . $secretA, $titreDes(gazette_trouver($idA)['apercu']), true)
        );
        verifier(
            'gazette : une clé inventée est refusée',
            !gazette_retirer($idA, 'article:999999')
        );

        verifier(
            'gazette : le mot de la famille est limité à 600 caractères',
            gazette_mot($idA, str_repeat('a', GAZETTE_MOT_MAX)) === true
            && gazette_mot($idA, str_repeat('a', GAZETTE_MOT_MAX + 1)) === false
        );
        gazette_mot($idA, 'Un mot simple pour ouvrir le numéro.');
        verifier(
            'gazette : le mot de la famille se retrouve dans le numéro',
            (string) gazette_trouver($idA)['mot'] === 'Un mot simple pour ouvrir le numéro.'
        );

        // -------------------------------------------------------------------
        // 6. Envoi
        // -------------------------------------------------------------------
        db_exec('DELETE FROM email_queue');
        gazette_retirer($idA, $cleSecretA); // l'élément retiré ne doit pas partir
        $mis = gazette_envoyer($idA);
        verifier(
            'gazette : l’envoi met un message par personne du groupe seulement',
            $mis === 2,
            (string) $mis
        );
        $destinataires = array_map(
            static fn (array $l): string => (string) $l['recipient'],
            db_all("SELECT recipient FROM email_queue WHERE kind = 'gazette' ORDER BY recipient")
        );
        verifier(
            'gazette : les bons destinataires, et personne d’autre',
            $destinataires === ['gaz.a2@exemple.fr', 'gaz.a@exemple.fr'],
            implode(', ', $destinataires)
        );
        verifier(
            'gazette : le numéro passe à « envoyé » avec sa date',
            (string) gazette_trouver($idA)['status'] === 'envoye'
            && (int) gazette_trouver($idA)['sent_at'] > 0
        );

        $texte = (string) db_value("SELECT body_text FROM email_queue WHERE kind = 'gazette' LIMIT 1", [], '');
        verifier(
            'gazette : le message contient le mot de la famille et le lien de lecture',
            str_contains($texte, 'Un mot simple pour ouvrir le numéro.')
            && str_contains($texte, '/gazette/2024-05')
        );
        verifier(
            'gazette : le message ne contient pas l’élément retiré',
            !str_contains($texte, 'Article ' . $secretA)
        );
        verifier(
            'gazette : le message ne contient rien d’une autre branche',
            !str_contains($texte, $secretB)
        );
        verifier(
            'gazette : le message porte le lien de désinscription de la gazette',
            str_contains($texte, '/desinscription')
            && str_contains($texte, 'Se désinscrire')
        );

        // Jamais deux fois le même mois.
        $avant = (int) db_value("SELECT COUNT(*) FROM email_queue WHERE kind = 'gazette'", [], 0);
        verifier(
            'gazette : un numéro déjà envoyé ne repart pas',
            gazette_envoyer($idA) === 0
            && (int) db_value("SELECT COUNT(*) FROM email_queue WHERE kind = 'gazette'", [], 0) === $avant
        );

        // Un compte désinscrit entre-temps ne reçoit rien de plus.
        db_update('accounts', $inscritA2, ['notify_gazette' => 0]);
        verifier(
            'gazette : une personne désinscrite n’est plus destinataire',
            !in_array($inscritA2, gazette_profils()['branche:' . $brancheA]['comptes'] ?? [], true)
        );
        db_update('accounts', $inscritA2, ['notify_gazette' => 1]);

        // -------------------------------------------------------------------
        // 7. Valider, ou passer
        // -------------------------------------------------------------------
        $ligneB = db_one("SELECT * FROM gazettes WHERE periode = '2024-05' AND groupe = ?", ['branche:' . $brancheB]);
        $idB = (int) ($ligneB['id'] ?? 0);
        verifier(
            'gazette : « passer ce mois-ci » n’envoie rien',
            gazette_passer($idB) && (string) gazette_trouver($idB)['status'] === 'passe'
            && gazette_envoyer($idB) === 0
            && (int) db_value("SELECT COUNT(*) FROM email_queue WHERE recipient = 'gaz.b@exemple.fr'", [], 0) === 0
        );

        // -------------------------------------------------------------------
        // 8. Envoi automatique et délai de grâce
        // -------------------------------------------------------------------
        save_site_setting('gazette_auto', '0');
        verifier('gazette : l’envoi automatique est désactivé par défaut', !gazette_auto_active());
        verifier(
            'gazette : désactivé, le délai de grâce n’envoie rien',
            gazette_valider_auto(mktime(12, 0, 0, 7, 20, 2024)) === 0
        );

        gazette_auto_regler(true);
        verifier('gazette : le réglage s’active et se lit', gazette_auto_active());

        $finMai = gazette_mois_bornes('2024-05')['fin'];
        $grace  = gazette_grace('2024-05');
        verifier(
            'gazette : le délai de grâce est de 3 jours après la fin du mois',
            $grace === (int) strtotime('+3 days', $finMai),
            date('d/m/Y H:i', $grace)
        );

        $avantGrace = (int) db_value("SELECT COUNT(*) FROM email_queue WHERE kind = 'gazette'", [], 0);
        verifier(
            'gazette : avant le délai de grâce, rien ne part tout seul',
            gazette_valider_auto($finMai + 86400) === 0
            && (int) db_value("SELECT COUNT(*) FROM email_queue WHERE kind = 'gazette'", [], 0) === $avantGrace
        );

        $envoyes = gazette_valider_auto($grace + 60);
        verifier(
            'gazette : passé le délai de grâce, le numéro non validé part tout seul',
            $envoyes >= 1,
            (string) $envoyes
        );
        $tout = db_one("SELECT * FROM gazettes WHERE periode = '2024-05' AND groupe = 'tout'");
        verifier(
            'gazette : le numéro « voit tout » a bien été envoyé automatiquement',
            $tout !== null && (string) $tout['status'] === 'envoye'
        );
        verifier(
            'gazette : un numéro passé reste passé (l’automatique ne le réveille pas)',
            (string) gazette_trouver($idB)['status'] === 'passe'
        );
        verifier(
            'gazette : un second passage n’envoie pas une deuxième fois',
            gazette_valider_auto($grace + 120) === 0
        );
        save_site_setting('gazette_auto', '0');

        // -------------------------------------------------------------------
        // 9. Lecture en ligne : chacun son numéro
        // -------------------------------------------------------------------
        $pageA = matrice_requete('GET', '/gazette/2024-05', $inscritA);
        verifier(
            'gazette en ligne : la personne retrouve le numéro de son groupe',
            $pageA['code'] === 200 && str_contains($pageA['corps'], 'La gazette de mai 2024'),
            'code ' . $pageA['code']
        );
        verifier(
            'gazette en ligne : elle n’y voit rien d’une autre branche',
            !str_contains($pageA['corps'], $secretB) && !str_contains($pageA['corps'], $secretPrive)
        );

        $pageB = matrice_requete('GET', '/gazette/2024-05', $inscritB);
        verifier(
            'gazette en ligne : un numéro passé n’est plus lisible',
            $pageB['code'] === 404,
            'code ' . $pageB['code']
        );

        $pageVisiteur = matrice_requete('GET', '/gazette/2024-05', null);
        verifier(
            'gazette en ligne : un visiteur est renvoyé vers la connexion',
            $pageVisiteur['code'] === 303,
            'code ' . $pageVisiteur['code']
        );

        $pageInconnu = matrice_requete('GET', '/gazette/1999-01', $inscritA);
        verifier(
            'gazette en ligne : un mois sans numéro répond 404',
            $pageInconnu['code'] === 404,
            'code ' . $pageInconnu['code']
        );

        // -------------------------------------------------------------------
        // 10. Administration
        // -------------------------------------------------------------------
        $adminPage = matrice_requete('GET', '/admin/gazette', $adminGaz);
        verifier(
            'gazette : la page d’administration liste les numéros',
            $adminPage['code'] === 200 && str_contains($adminPage['corps'], 'Gazette du mois'),
            'code ' . $adminPage['code']
        );
        $apercu = matrice_requete('GET', '/admin/gazette/' . $idA, $adminGaz);
        verifier(
            'gazette : l’aperçu d’un numéro est accessible à l’administrateur',
            $apercu['code'] === 200 && str_contains($apercu['corps'], 'Un mot simple pour ouvrir le numéro.'),
            'code ' . $apercu['code']
        );
        $refus = matrice_requete('GET', '/admin/gazette/' . $idA, $inscritA);
        verifier(
            'gazette : une personne de la famille n’entre pas dans l’administration',
            $refus['code'] === 403,
            'code ' . $refus['code']
        );
        $sansJeton = matrice_requete('POST', '/admin/gazette/' . $idA . '/envoyer', $adminGaz, [], []);
        verifier(
            'gazette : envoyer sans jeton anti-CSRF est refusé',
            $sansJeton['code'] === 403,
            'code ' . $sansJeton['code']
        );
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        save_site_setting('gazette_auto', '0');
        db_exec("DELETE FROM gazettes WHERE periode IN ('2024-05', '2024-02')");
    }
}
