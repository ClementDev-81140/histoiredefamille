<?php
/**
 * Tests de la 1.32.0 — thèmes par type de contenu et calendrier.
 *
 * Deux familles :
 *   « categories_types »  un thème sert à un ou plusieurs types, un
 *                         contenu peut en porter plusieurs, le compteur ne
 *                         compte que ce que la personne peut voir ;
 *   « evenements »        les événements de famille : privés par défaut,
 *                         droits des quatre profils, répétition annuelle,
 *                         suppression confirmée.
 *
 * Tout se passe dans une transaction annulée : la base est rendue intacte.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Thèmes par type de contenu
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'categories_types') {
    echo "Thèmes par type de contenu\n";

    $pdo = db();
    $transaction = !$pdo->inTransaction();
    if ($transaction) {
        $pdo->beginTransaction();
    }
    $sessionAvant = $_SESSION;

    try {
        $suffixe = bin2hex(random_bytes(3));
        $maintenant = time();
        $adminId = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);

        // 1. Un thème neuve s'applique aux six types par défaut.
        $catTous = db_insert('categories', [
            'name' => 'Tous types ' . $suffixe, 'slug' => 'tous-' . $suffixe, 'position' => 0,
            'created_at' => $maintenant,
        ]);
        $catTousLigne = find_category((int) $catTous);
        verifier(
            'thèmes : un thème neuve vaut pour les six types de contenu',
            count(category_kinds($catTousLigne)) === count(CATEGORY_KINDS)
        );

        // 2. Restreindre les types, et le respecter dans les listes de formulaires.
        db_update('categories', (int) $catTous, ['kinds' => 'article,recette', 'position' => 5]);
        $catTousLigne = find_category((int) $catTous);
        verifier(
            'thèmes : un thème peut être réservée à certains types',
            category_applicable($catTousLigne, 'article') && category_applicable($catTousLigne, 'recette')
                && !category_applicable($catTousLigne, 'photo')
                && in_array((int) $catTousLigne['id'], array_map('intval', array_column(categories_list('article'), 'id')), true)
                && !in_array((int) $catTous, array_map('intval', array_column(categories_list('photo'), 'id')), true)
        );

        // 3. Plusieurs thèmes pour un même contenu.
        $catBis = db_insert('categories', [
            'name' => 'Bis ' . $suffixe, 'slug' => 'bis-' . $suffixe, 'position' => 6,
            'kinds' => 'article', 'created_at' => $maintenant,
        ]);
        $articlePublic = db_insert('articles', [
            'slug' => 'cat-public-' . $suffixe, 'title' => 'Article public de thème',
            'summary' => '', 'body' => 'Texte.', 'category' => '', 'visibility' => 'public',
            'status' => 'publie', 'published_at' => $maintenant - 60,
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
        $articlePrive = db_insert('articles', [
            'slug' => 'cat-prive-' . $suffixe, 'title' => 'Article privé de thème',
            'summary' => '', 'body' => 'Texte.', 'category' => '', 'visibility' => 'prive',
            'status' => 'publie', 'published_at' => $maintenant - 60,
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
        category_enregistrer('article', (int) $articlePublic, [(int) $catTous, (int) $catBis]);
        $catPrivee = db_insert('categories', [
            'name' => 'Privée ' . $suffixe, 'slug' => 'privee-' . $suffixe, 'position' => 7,
            'kinds' => 'article', 'created_at' => $maintenant,
        ]);
        category_enregistrer('article', (int) $articlePrive, [(int) $catTous, (int) $catPrivee]);

        verifier(
            'thèmes : un contenu peut porter plusieurs thèmes',
            category_ids_pour('article', (int) $articlePublic) === [(int) $catTous, (int) $catBis]
        );
        verifier(
            'thèmes : un thème d’un autre type n’est jamais rattachée par erreur',
            category_ids_pour('photo', (int) $articlePublic) === []
        );

        // 4. Le compteur ne compte que ce que la personne peut voir.
        unset($_SESSION['account_id']);
        $compteurVisiteur = category_compteur('article', (int) $catTous);
        $listeVisiteur    = array_column(categories_avec_compteur('article'), 'nb', 'id');
        $_SESSION['account_id'] = $adminId;
        $compteurAdmin = category_compteur('article', (int) $catTous);

        verifier(
            'thèmes : le compteur d’un visiteur ne compte que les contenus publics',
            $compteurVisiteur === 1 && $compteurAdmin === 2,
            'visiteur ' . $compteurVisiteur . ', admin ' . $compteurAdmin
        );
        verifier(
            'thèmes : un thème dont rien n’est visible n’apparaît pas au visiteur',
            !array_key_exists((int) $catPrivee, $listeVisiteur),
            'thème ' . $catPrivee . ' présente : ' . (array_key_exists((int) $catPrivee, $listeVisiteur) ? 'oui' : 'non')
        );

        // 5. Le filtre de la liste publique respecte les droits.
        $_SESSION['account_id'] = null;
        $visiteurFiltre = matrice_requete('GET', '/articles?theme=' . (int) $catTous, null);
        verifier(
            'thèmes : le filtre d’une liste publique ne montre que le contenu autorisé',
            $visiteurFiltre['code'] === 200
                && str_contains($visiteurFiltre['corps'], 'Article public de thème')
                && !str_contains($visiteurFiltre['corps'], 'Article privé de thème')
        );

        // 6. Une valeur d'adresse inconnue ne filtre rien (et ne casse rien).
        $fauxFiltre = matrice_requete('GET', '/articles?theme=99999999', null);
        verifier(
            'thèmes : un thème inventé dans l’adresse est ignoré',
            $fauxFiltre['code'] === 200 && str_contains($fauxFiltre['corps'], 'Article public de thème')
        );

        // 6 bis (1.36.0). L'ancienne adresse « ?categorie=… » redirige vers
        // « ?theme=… », et l'ancien « ?categorie_id=… » des articles aussi.
        $ancien = matrice_requete('GET', '/documents?categorie=' . (int) $catTous, null);
        verifier(
            'thèmes : « ?categorie= » redirige vers « ?theme= »',
            $ancien['code'] === 301 && str_contains((string) ($ancien['location'] ?? ''), 'theme=' . (int) $catTous)
        );
        $ancienId = matrice_requete('GET', '/articles?categorie_id=' . (int) $catTous, null);
        verifier(
            'thèmes : « ?categorie_id= » redirige aussi vers « ?theme= »',
            $ancienId['code'] === 301 && str_contains((string) ($ancienId['location'] ?? ''), 'theme=' . (int) $catTous)
        );

        // 7. Les formulaires proposent bien des cases.
        $_SESSION['account_id'] = $adminId;
        foreach ([['/admin/documents/nouveau', 'un document'], ['/admin/videos/nouvelle', 'une vidéo'], ['/admin/articles/nouveau', 'un article'], ['/admin/albums/nouveau', 'un album'], ['/admin/recettes/nouvelle', 'une recette']] as [$url, $libelle]) {
            $page = matrice_requete('GET', $url, $adminId);
            verifier(
                'thèmes : le formulaire d’' . $libelle . ' propose des cases « Thèmes »',
                $page['code'] === 200 && str_contains($page['corps'], 'name="categories[]"')
            );
        }

        // 8. La migration recopie les thèmes déjà posées sur les documents,
        //    et la fiche enregistrée garde les deux écritures synchronisées.
        $migration = (string) @file_get_contents(HDF_APP . '/migrations/021_categories_types.sql');
        verifier(
            'thèmes : la migration recopie les thèmes déjà posées sur les documents',
            str_contains($migration, 'INSERT OR IGNORE INTO category_links')
                && str_contains($migration, 'FROM documents')
                && str_contains($migration, 'WHERE category_id IS NOT NULL')
        );

        $catDoc = db_insert('categories', [
            'name' => 'Documentaire ' . $suffixe, 'slug' => 'doc-' . $suffixe, 'position' => 8,
            'created_at' => $maintenant,
        ]);
        $docDemo = db_one("SELECT * FROM documents WHERE is_attachment = 0 ORDER BY id LIMIT 1");
        if ($docDemo !== null) {
            $docId = (int) $docDemo['id'];
            matrice_requete('GET', '/admin/documents/' . $docId . '/modifier', $adminId);
            $jeton = (string) ($_SESSION['_csrf'] ?? '');
            $enregistre = matrice_requete(
                'POST',
                '/admin/documents/' . $docId . '/modifier',
                $adminId,
                [],
                [
                    '_csrf'          => $jeton,
                    'title'          => (string) $docDemo['title'],
                    'visibility'     => (string) $docDemo['visibility'],
                    'vintage_aspect' => 'suivre',
                    'categories'     => [(string) $catDoc, (string) $catTous],
                ]
            );
            $ligne = db_one('SELECT category_id, kind FROM documents WHERE id = ?', [$docId]);
            verifier(
                'thèmes : la fiche enregistrée garde l’ancienne colonne et la liaison',
                $enregistre['code'] === 303
                    && (int) ($ligne['category_id'] ?? 0) === (int) $catDoc
                    && category_ids_pour((string) ($ligne['kind'] ?? 'document'), $docId) === [(int) $catDoc],
                'code ' . $enregistre['code'] . ' / colonne ' . (int) ($ligne['category_id'] ?? 0)
                    . ' / liaisons ' . implode(',', category_ids_pour((string) ($ligne['kind'] ?? 'document'), $docId))
                    . ' / attendu ' . (int) $catTous
            );
        }

        // 9. Une vidéo enregistrée par le formulaire garde ses thèmes, et
        //    jamais celle d'un autre type.
        $video = db_one("SELECT * FROM documents WHERE kind = 'video' AND is_attachment = 0 ORDER BY id LIMIT 1");
        if ($video !== null) {
            $catVideo = db_insert('categories', [
                'name' => 'Film d’archive ' . $suffixe, 'slug' => 'film-' . $suffixe, 'position' => 9,
                'kinds' => 'video', 'created_at' => $maintenant,
            ]);
            $videoId = (int) $video['id'];
            matrice_requete('GET', '/admin/videos/' . $videoId, $adminId);
            $jetonVideo = (string) ($_SESSION['_csrf'] ?? '');
            $envoiVideo = matrice_requete('POST', '/admin/videos/' . $videoId, $adminId, [], [
                '_csrf'         => $jetonVideo,
                'title'         => (string) $video['title'],
                'visibility'    => (string) $video['visibility'],
                'is_featured'   => (string) (int) $video['is_featured'],
                'display_order' => (string) (int) $video['display_order'],
                'categories'    => [(string) $catVideo, (string) $catTous],
            ]);
            verifier(
                'thèmes : une vidéo garde sa thème, jamais celle d’un autre type',
                $envoiVideo['code'] === 303
                    && category_ids_pour('video', $videoId) === [(int) $catVideo]
                    && (int) db_value('SELECT category_id FROM documents WHERE id = ?', [$videoId], 0) === (int) $catVideo
            );
        }

        // 10. L'administration des thèmes montre les types et l'ordre.
        $pageCats = matrice_requete('GET', '/admin/categories', $adminId);
        verifier(
            'thèmes : l’administration propose les types et l’ordre d’affichage',
            $pageCats['code'] === 200 && str_contains($pageCats['corps'], 'name="kinds[]"')
                && str_contains($pageCats['corps'], 'name="position"')
        );
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION = $sessionAvant;
    }
}

// ---------------------------------------------------------------------------
// Événements du calendrier
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'evenements') {
    echo "Événements du calendrier\n";

    $pdo = db();
    $transaction = !$pdo->inTransaction();
    if ($transaction) {
        $pdo->beginTransaction();
    }
    $sessionAvant = $_SESSION;

    try {
        $suffixe    = bin2hex(random_bytes(3));
        $maintenant = time();
        $adminId    = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);
        $compteFamille = db_one("SELECT * FROM accounts WHERE role IN ('famille', 'lecteur', 'branche') ORDER BY id LIMIT 1");
        $familleId = $compteFamille !== null ? (int) $compteFamille['id'] : $adminId;

        // 1. Un événement est privé par défaut.
        [$valeurs, $erreurs] = evenement_valider([
            'title' => 'Réunion de famille ' . $suffixe,
            'start_date' => date('Y-m-d'),
            'place' => 'Toulouse',
            'description' => 'Tout le monde est invité.',
        ]);
        verifier(
            'événements : la visibilité par défaut est « privé »',
            $erreurs === [] && $valeurs['visibility'] === 'prive'
        );

        // 2. Les saisies invalides sont refusées avec un message.
        [$vInvalide, $eInvalide] = evenement_valider([
            'title' => '', 'start_date' => '2026-13-40', 'start_time' => '99:99',
        ]);
        verifier(
            'événements : une date ou une heure impossible est refusée',
            $eInvalide !== [] && $vInvalide['start_date'] === ''
        );

        // 3. Quatre visibilités, quatre profils.
        $jour = date('Y-m-d');
        $ids = [];
        foreach (['public', 'famille', 'prive'] as $niveau) {
            [$valeurs, $erreurs] = evenement_valider([
                'title' => 'Événement ' . $niveau . ' ' . $suffixe,
                'start_date' => $jour,
                'visibility' => $niveau,
            ]);
            $ids[$niveau] = evenement_creer($valeurs, $adminId);
        }

        unset($_SESSION['account_id']);
        $vusVisiteur = array_map('intval', array_column(evenements_visibles(500), 'id'));
        verifier(
            'événements : un visiteur ne voit que l’événement public',
            in_array($ids['public'], $vusVisiteur, true)
                && !in_array($ids['famille'], $vusVisiteur, true)
                && !in_array($ids['prive'], $vusVisiteur, true)
        );

        $_SESSION['account_id'] = $familleId;
        $vusFamille = array_map('intval', array_column(evenements_visibles(500), 'id'));
        verifier(
            'événements : un compte de famille voit le public et « famille », jamais le privé',
            in_array($ids['public'], $vusFamille, true)
                && in_array($ids['famille'], $vusFamille, true)
                && !in_array($ids['prive'], $vusFamille, true)
        );

        $_SESSION['account_id'] = $adminId;
        $vusAdmin = array_map('intval', array_column(evenements_visibles(500), 'id'));
        verifier(
            'événements : un administrateur voit tout, y compris le privé',
            in_array($ids['prive'], $vusAdmin, true) && in_array($ids['famille'], $vusAdmin, true)
        );

        // 4. Le calendrier du mois affiche l'événement, et « Dans la famille » aussi.
        unset($_SESSION['account_id']);
        $page = matrice_requete('GET', '/calendrier', null);
        verifier(
            'événements : le calendrier public affiche l’événement public',
            $page['code'] === 200 && str_contains($page['corps'], 'Événement public ' . $suffixe)
                && !str_contains($page['corps'], 'Événement privé ' . $suffixe)
        );

        $_SESSION['account_id'] = $familleId;
        $prochains = evenements_prochains(365, 50);
        $titres = array_column($prochains, 'titre');
        verifier(
            'événements : « Dans la famille » propose les événements à venir visibles',
            in_array('Événement famille ' . $suffixe, $titres, true)
        );

        // 5. Répétition annuelle : l'événement revient chaque année.
        [$valeurs, $erreurs] = evenement_valider([
            'title' => 'Fête annuelle ' . $suffixe,
            'start_date' => '2001-06-12',
            'annual' => '1',
            'visibility' => 'public',
        ]);
        $idAnnuel = evenement_creer($valeurs, $adminId);
        $evenement = evenement_par_id($idAnnuel);
        $prochaine = evenement_prochaine_occurrence($evenement, new DateTimeImmutable('2026-01-01'));
        verifier(
            'événements : un événement annuel revient chaque année à la même date',
            $prochaine !== null && $prochaine->format('Y-m-d') === '2026-06-12'
        );
        $memeEvenement = evenement_par_id($idAnnuel);
        $prochaineApres = evenement_prochaine_occurrence($memeEvenement, new DateTimeImmutable('2026-07-01'));
        verifier(
            'événements : une fois passée, la prochaine occurrence est l’année suivante',
            $prochaineApres !== null && $prochaineApres->format('Y') === '2027'
        );

        // 6. Un événement non annuel n'apparaît que dans son année.
        [$valeurs, $erreurs] = evenement_valider([
            'title' => 'Unique ' . $suffixe, 'start_date' => '2001-06-12', 'visibility' => 'public',
        ]);
        $idUnique = evenement_creer($valeurs, $adminId);
        $en2001 = evenements_du_mois(6, 2001);
        $en2026 = evenements_du_mois(6, 2026);
        $titrePrecedent = static function (array $jours) use ($suffixe): bool {
            foreach ($jours as $liste) {
                foreach ($liste as $evenement) {
                    if (str_contains((string) $evenement['title'], 'Unique ' . $suffixe)) {
                        return true;
                    }
                }
            }
            return false;
        };
        verifier(
            'événements : un événement sans répétition n’existe que dans son année',
            $titrePrecedent($en2001) && !$titrePrecedent($en2026)
        );

        // 7. L'administration : liste, création, suppression confirmée.
        $_SESSION['account_id'] = $adminId;
        $listeAdmin = matrice_requete('GET', '/admin/calendrier', $adminId);
        verifier(
            'événements : l’administration liste les événements et propose « Nouvel événement »',
            $listeAdmin['code'] === 200 && str_contains($listeAdmin['corps'], 'Nouvel événement')
                && str_contains($listeAdmin['corps'], 'Fête annuelle ' . $suffixe)
        );
        verifier(
            'événements : la suppression demande une confirmation dans le navigateur',
            str_contains($listeAdmin['corps'], 'data-confirmation="Supprimer cet événement')
                && str_contains($listeAdmin['corps'], '/admin/calendrier/' . $idAnnuel . '/supprimer')
        );

        $formulaire = matrice_requete('GET', '/admin/calendrier/nouveau', $adminId);
        verifier(
            'événements : le formulaire de création est réservé et complet',
            $formulaire['code'] === 200 && str_contains($formulaire['corps'], 'name="start_date"')
                && str_contains($formulaire['corps'], 'name="annual"')
                && str_contains($formulaire['corps'], 'name="visibility"')
        );

        $modif = matrice_requete('GET', '/admin/calendrier/' . $idAnnuel . '/modifier', $adminId);
        verifier(
            'événements : la modification d’un événement existant s’ouvre',
            $modif['code'] === 200 && str_contains($modif['corps'], 'Fête annuelle ' . $suffixe)
        );

        // 8. Création et suppression par les vraies pages (POST + jeton CSRF).
        matrice_requete('GET', '/admin/calendrier/nouveau', $adminId);
        $jeton = (string) ($_SESSION['_csrf'] ?? '');
        $creation = matrice_requete('POST', '/admin/calendrier/nouveau', $adminId, [], [
            '_csrf'       => $jeton,
            'title'       => 'Créé par le formulaire ' . $suffixe,
            'start_date'  => date('Y-m-d'),
            'place'       => 'Auch',
            'description' => 'Créé depuis la page.',
        ]);
        $cree = db_one('SELECT * FROM events WHERE title = ?', ['Créé par le formulaire ' . $suffixe]);
        verifier(
            'événements : le formulaire crée un événement privé',
            $creation['code'] === 303 && $cree !== null && (string) $cree['visibility'] === 'prive'
        );

        if ($cree !== null) {
            $idCree = (int) $cree['id'];
            $suppression = matrice_requete('POST', '/admin/calendrier/' . $idCree . '/supprimer', $adminId, [], [
                '_csrf' => $jeton,
            ]);
            verifier(
                'événements : la suppression confirmée retire l’événement',
                $suppression['code'] === 303 && evenement_par_id($idCree) === null
            );
            $sansJeton = matrice_requete('POST', '/admin/calendrier/' . $idCree . '/supprimer', $adminId);
            verifier(
                'événements : une suppression sans jeton CSRF est refusée',
                $sansJeton['code'] === 403
            );
        }

        $compteNonAdmin = (int) db_value("SELECT id FROM accounts WHERE role <> 'admin' ORDER BY id LIMIT 1", [], 0);
        if ($compteNonAdmin > 0) {
            $refus = matrice_requete('GET', '/admin/calendrier/nouveau', $compteNonAdmin);
            verifier(
                'événements : un compte non administrateur ne peut pas créer d’événement',
                $refus['code'] === 403
            );
        }

        // 9. Suppression : la fonction enlève aussi les branches.
        evenement_supprimer($idAnnuel);
        verifier(
            'événements : supprimer un événement le retire vraiment',
            evenement_par_id($idAnnuel) === null
                && content_branch_ids('event', $idAnnuel) === []
        );

        // 10. Aucune page d'administration n'est ouverte à un visiteur.
        unset($_SESSION['account_id']);
        $adminVisiteur = matrice_requete('GET', '/admin/calendrier', null);
        verifier(
            'événements : /admin/calendrier renvoie un visiteur vers la connexion',
            $adminVisiteur['code'] === 303
        );
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION = $sessionAvant;
    }
}
