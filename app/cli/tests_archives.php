<?php
/**
 * Essais — 1.36.0 : vocabulaire (Type / Thème), archives et dossiers.
 *
 *   1. la migration 023 est en ajout seul (colonnes et table nouvelles,
 *      documents existants toujours « journal ») ;
 *   2. un document d'archive : jamais à la une, jamais dans les nouveautés
 *      ni la gazette, jamais de pastille pour les lecteurs — mais présent
 *      dans Documents (filtre « Archives »), la recherche et son dossier,
 *      toujours selon sa visibilité ;
 *   3. un dossier vide pour un compte est invisible pour lui (ni nom ni
 *      nombre) ; supprimer un dossier ne supprime pas ses documents ;
 *   4. la case « Classer en archive » existe dans l'envoi par morceaux, le
 *      formulaire d'un document et celui d'une proposition ; un journaliste
 *      qui propose une archive reste en attente, invisible de tous ;
 *   5. le mot « thème » remplace « catégorie » dans l'interface, et aucune
 *      carte n'affiche deux fois le même mot (type et thème).
 *
 * Tout se passe dans une transaction annulée : la base est rendue intacte.
 */

declare(strict_types=1);

if ($filtre === '' || $filtre === 'archives36') {
    echo "Archives et dossiers (1.36.0)\n";

    $pdo = db();
    $transaction = !$pdo->inTransaction();
    if ($transaction) {
        $pdo->beginTransaction();
    }
    $sessionAvant = $_SESSION;
    $getAvant = $_GET;
    $postAvant = $_POST;
    $filesAvant = $_FILES;

    try {
        $suffixe = bin2hex(random_bytes(3));
        $maintenant = time();

        $adminId = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);
        $brancheId = db_insert('branches', [
            'code' => 'ARCH' . strtoupper($suffixe), 'name' => 'Branche archives ' . $suffixe,
            'description' => null, 'see_all' => 0, 'is_active' => 1,
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
        $lecteurId = db_insert('accounts', [
            'username' => 'arch-lecteur-' . $suffixe, 'display_name' => 'Lecteur archives',
            'password_hash' => password_make('MotDePasseEssai123!'), 'role' => 'famille',
            'account_type' => 'branche', 'branch_id' => $brancheId, 'see_all' => 0,
            'is_active' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
        $journalisteId = db_insert('accounts', [
            'username' => 'arch-journaliste-' . $suffixe, 'display_name' => 'Journaliste archives',
            'password_hash' => password_make('MotDePasseEssai123!'), 'role' => 'famille',
            'account_type' => 'personne', 'first_name' => 'Jules', 'last_name' => 'Archives',
            'branch_id' => $brancheId, 'see_all' => 0,
            'is_active' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);

        // --- 1. Migration en ajout seul ------------------------------------
        $colonnes = array_column(db_all('PRAGMA table_info(documents)'), 'name');
        $colonnesEnvois = array_column(db_all('PRAGMA table_info(uploads)'), 'name');
        verifier(
            'archives : la migration ajoute est_archive, dossier_id et la table dossiers, sans rien retirer',
            in_array('est_archive', $colonnes, true) && in_array('dossier_id', $colonnes, true)
                && in_array('archive', $colonnesEnvois, true)
                && db_one("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'dossiers'") !== null
                && (int) db_value('SELECT COUNT(*) FROM documents WHERE est_archive NOT IN (0, 1)', [], 0) === 0
        );

        verifier(
            'migration : la copie de sécurité précède toute migration en attente',
            db_migrations_en_attente() === []
                && str_contains((string) @file_get_contents(HDF_APP . '/cli/migrate.php'), 'avant-migration-')
                && str_contains((string) @file_get_contents(HDF_APP . '/cli/migrate.php'), 'db_migrations_en_attente()')
        );

        // --- Contenus d'essai ------------------------------------------------
        $personneId = db_insert('persons', [
            'first_name' => 'Michel', 'last_name' => 'Archive' . $suffixe,
            'sex' => 'M', 'birth_year' => 1920, 'visibility' => 'public',
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
        $dossierId = db_insert('dossiers', [
            'name' => 'Grand-père Michel ' . $suffixe, 'description' => 'Ses papiers.',
            'person_id' => $personneId, 'created_by' => $adminId,
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
        $dossierCacheId = db_insert('dossiers', [
            'name' => 'Dossier secret ' . $suffixe, 'description' => null,
            'person_id' => $personneId, 'created_by' => $adminId,
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);

        $creerDoc = static function (string $titre, string $visibilite, int $archive, ?int $dossier) use ($maintenant, $adminId): int {
            return db_insert('documents', [
                'title' => $titre, 'description' => 'Essai archives.', 'kind' => 'document',
                'visibility' => $visibilite, 'est_archive' => $archive, 'dossier_id' => $dossier,
                'rel_path' => 'essai/' . bin2hex(random_bytes(4)) . '.pdf', 'original_name' => 'essai.pdf',
                'mime' => 'application/pdf', 'ext' => 'pdf', 'size' => 1000,
                'is_attachment' => 0, 'created_by' => $adminId,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        };

        $docArchivePublic = $creerDoc('Acte archive public ' . $suffixe, 'public', 1, (int) $dossierId);
        $docArchivePrive  = $creerDoc('Acte archive privé ' . $suffixe, 'prive', 1, (int) $dossierCacheId);
        $docJournalPublic = $creerDoc('Acte journal public ' . $suffixe, 'public', 0, null);
        $docArchiveFamille = $creerDoc('Acte archive famille ' . $suffixe, 'famille', 1, (int) $dossierId);

        $themeId = db_insert('categories', [
            'name' => 'Actes et papiers ' . $suffixe, 'slug' => 'actes-' . $suffixe,
            'position' => 0, 'kinds' => 'document', 'created_at' => $maintenant,
        ]);
        category_enregistrer('document', (int) $docJournalPublic, [(int) $themeId]);
        category_enregistrer('document', (int) $docArchivePublic, [(int) $themeId]);

        // --- 2. Vitrines : une, nouveautés, gazette -------------------------
        unset($_SESSION['account_id']);
        $titresUne = array_column(une_publique_page(24, 1)['items'], 'titre');
        verifier(
            'archives : un document d’archive n’est jamais à la une d’un visiteur',
            !in_array('Acte archive public ' . $suffixe, $titresUne, true)
                && in_array('Acte journal public ' . $suffixe, $titresUne, true)
        );

        $titresRecents = array_column(recent_documents(50), 'title');
        verifier(
            'archives : les documents récents (accueils) excluent les archives',
            !in_array('Acte archive public ' . $suffixe, $titresRecents, true)
                && in_array('Acte journal public ' . $suffixe, $titresRecents, true)
        );

        $_SESSION['account_id'] = $lecteurId;
        $_SESSION['visite_precedente'] = $maintenant - 3600;
        $bloc = dans_la_famille(30, 10);
        $titresNouveautes = array_column($bloc['nouveautes'], 'titre');
        verifier(
            'archives : « Dans la famille » ne signale jamais une archive',
            !in_array('Acte archive famille ' . $suffixe, $titresNouveautes, true)
        );

        $periode = date('Y-m');
        $gazette = gazette_contenu($periode, $lecteurId);
        $titresGazette = [];
        foreach ($gazette['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $titresGazette[] = (string) $item['titre'];
            }
        }
        verifier(
            'archives : la gazette du mois ne contient aucune archive',
            !in_array('Acte archive public ' . $suffixe, $titresGazette, true)
                && !in_array('Acte archive famille ' . $suffixe, $titresGazette, true)
        );

        // --- 3. Retrouver une archive : Documents, recherche, dossier -------
        $pageArchives = matrice_requete('GET', '/documents?archives=1', null);
        verifier(
            'archives : le filtre « Archives » de Documents les montre selon les droits',
            $pageArchives['code'] === 200
                && str_contains($pageArchives['corps'], 'Acte archive public ' . $suffixe)
                && !str_contains($pageArchives['corps'], 'Acte journal public ' . $suffixe)
                && !str_contains($pageArchives['corps'], 'Acte archive privé ' . $suffixe)
        );

        $pageTheme = matrice_requete('GET', '/documents?theme=' . (int) $themeId, null);
        verifier(
            'thèmes : le filtre « Thème » de Documents ne compte que le visible',
            $pageTheme['code'] === 200
                && str_contains($pageTheme['corps'], 'Actes et papiers ' . $suffixe)
                && str_contains($pageTheme['corps'], 'name="theme"')
        );

        // Dans la suite complète, la famille « restauration » a pu remettre
        // une copie de la base sans l'index de recherche (dans un vrai
        // serveur, chaque page le recréerait d'elle-même) : on le refait ici.
        if (db_value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'search_fts'") === null) {
            recherche_fts_creer();
            save_site_setting('recherche_version', (string) recherche_version_index());
        }

        unset($_SESSION['account_id']);
        $resultats = recherche_documents(['Acte', 'archive', 'public', $suffixe]);
        $titresRecherche = array_column($resultats, 'title');
        verifier(
            'archives : la recherche retrouve une archive visible',
            in_array('Acte archive public ' . $suffixe, $titresRecherche, true)
        );

        $pageDossier = matrice_requete('GET', '/dossiers/' . (int) $dossierId, null);
        verifier(
            'dossiers : la page d’un dossier liste son contenu visible',
            $pageDossier['code'] === 200
                && str_contains($pageDossier['corps'], 'Grand-père Michel ' . $suffixe)
                && str_contains($pageDossier['corps'], 'Acte archive public ' . $suffixe)
                && !str_contains($pageDossier['corps'], 'Acte archive famille ' . $suffixe)
        );

        // --- 4. Dossier invisible pour un compte ----------------------------
        $pageDossierCache = matrice_requete('GET', '/dossiers/' . (int) $dossierCacheId, null);
        $pageDocumentsLecteur = matrice_requete('GET', '/documents', $lecteurId);
        verifier(
            'dossiers : un dossier sans rien de visible pour un compte répond 404 et ne fuit pas',
            $pageDossierCache['code'] === 404
                && !str_contains($pageDossierCache['corps'], 'Dossier secret ' . $suffixe)
                && !str_contains($pageDocumentsLecteur['corps'], 'Dossier secret ' . $suffixe)
        );

        $fichePersonne = matrice_requete('GET', '/arbre/' . (int) $personneId, $lecteurId);
        verifier(
            'dossiers : la fiche d’une personne ne propose que les dossiers visibles pour le compte',
            $fichePersonne['code'] === 200
                && str_contains($fichePersonne['corps'], 'Grand-père Michel ' . $suffixe)
                && !str_contains($fichePersonne['corps'], 'Dossier secret ' . $suffixe)
        );

        // --- 5. Administration : classement, déplacement, suppression -------
        $formDoc = matrice_requete('GET', '/admin/documents/' . (int) $docJournalPublic . '/modifier', $adminId);
        verifier(
            'archives : le formulaire d’un document a la case et le choix du dossier',
            $formDoc['code'] === 200
                && str_contains($formDoc['corps'], 'name="archive"')
                && str_contains($formDoc['corps'], 'Classer en archive')
                && str_contains($formDoc['corps'], 'name="dossier_id"')
                && str_contains($formDoc['corps'], 'Un document d’archive n’apparaît pas à la une')
        );

        $jeton = (string) ($_SESSION['_csrf'] ?? '');
        $classe = matrice_requete('POST', '/admin/documents/' . (int) $docJournalPublic . '/modifier', $adminId, [], [
            '_csrf' => $jeton, 'title' => 'Acte journal public ' . $suffixe,
            'visibility' => 'public', 'vintage_aspect' => 'suivre',
            'archive' => '1', 'dossier_id' => (string) $dossierId,
        ]);
        $ligneClassee = db_one('SELECT est_archive, dossier_id FROM documents WHERE id = ?', [(int) $docJournalPublic]);
        verifier(
            'archives : on peut classer en archive et ranger dans un dossier, puis revenir en journal',
            $classe['code'] === 303
                && (int) $ligneClassee['est_archive'] === 1
                && (int) $ligneClassee['dossier_id'] === (int) $dossierId
        );

        $jeton = (string) ($_SESSION['_csrf'] ?? '');
        matrice_requete('POST', '/admin/documents/' . (int) $docJournalPublic . '/modifier', $adminId, [], [
            '_csrf' => $jeton, 'title' => 'Acte journal public ' . $suffixe,
            'visibility' => 'public', 'vintage_aspect' => 'suivre',
            'categories' => [(string) $themeId],
        ]);
        $ligneJournal = db_one('SELECT est_archive, dossier_id FROM documents WHERE id = ?', [(int) $docJournalPublic]);
        verifier(
            'archives : repasser en « journal » retire le dossier',
            (int) $ligneJournal['est_archive'] === 0 && $ligneJournal['dossier_id'] === null
        );

        $avantSuppression = (int) db_value('SELECT COUNT(*) FROM documents WHERE dossier_id = ?', [(int) $dossierId], 0);
        $jeton = (string) ($_SESSION['_csrf'] ?? '');
        $suppression = matrice_requete('POST', '/admin/dossiers/' . (int) $dossierId . '/supprimer', $adminId, [], [
            '_csrf' => $jeton,
        ]);
        verifier(
            'dossiers : supprimer un dossier ne supprime jamais ses documents',
            $suppression['code'] === 303
                && dossier_par_id((int) $dossierId) === null
                && (int) db_value('SELECT COUNT(*) FROM documents WHERE id IN (?, ?)', [(int) $docArchivePublic, (int) $docArchiveFamille], 0) === 2
                && (int) db_value('SELECT COUNT(*) FROM documents WHERE dossier_id = ?', [(int) $dossierId], 0) === 0
                && $avantSuppression === 2
        );

        // --- 6. Envoi par morceaux et proposition de journaliste -------------
        verifier(
            'archives : la page « Envoyer des fichiers » propose la case dès l’envoi par morceaux',
            str_contains((string) @file_get_contents(HDF_APP . '/views/admin/envoyer.php'), "'archive'   => true")
                && str_contains((string) @file_get_contents(HDF_APP . '/views/admin/partials/envoi.php'), 'data-envoi-archive')
                && str_contains((string) @file_get_contents(HDF_ROOT . '/public/assets/envoi.js'), "donnees.append('archive', '1')")
        );

        $formProposition = matrice_requete('GET', '/propositions/nouvelle/document', $journalisteId);
        verifier(
            'archives : le formulaire d’une proposition a la case « Classer en archive »',
            $formProposition['code'] === 200
                && str_contains($formProposition['corps'], 'name="archive"')
                && str_contains($formProposition['corps'], 'soumise à la validation')
        );

        // Enregistrement réel de la proposition, par l'envoi par morceaux
        // (le chemin « fichier d'un coup » exige un vrai téléversement HTTP,
        // impossible dans ce banc : is_uploaded_file y est toujours faux).
        $contenuFictif = "Acte fictif proposé en archive.\n";
        $initProp = envoi_creer($journalisteId, 'acte-propose.txt', strlen($contenuFictif), 'text/plain', false, null, null, true);
        $envoiProp = $initProp['envoi'] ?? null;
        if ($envoiProp !== null) {
            $partiel = storage_path('tmp') . '/arch-' . bin2hex(random_bytes(6)) . '.part';
            file_put_contents($partiel, $contenuFictif);
            envoi_recevoir_morceau($envoiProp, 0, $partiel, strlen($contenuFictif));
            @unlink($partiel);
            $envoiProp = envoi_par_token((string) $envoiProp['token']);
            envoi_terminer($envoiProp);
            $envoiProp = envoi_par_token((string) $envoiProp['token']);
        }
        $_SESSION = ['account_id' => $journalisteId, '_csrf' => (string) ($_SESSION['_csrf'] ?? 'jeton-archives')];
        $_POST = [
            '_csrf' => (string) $_SESSION['_csrf'],
            'title' => 'Proposition archive ' . $suffixe,
            'description' => '', 'doc_date' => '', 'archive' => '1',
            'suggested_visibility' => 'famille', 'action' => 'envoyer',
            'envoi' => (string) ($envoiProp['token'] ?? ''),
        ];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/propositions/nouvelle/document';
        try {
            proposition_save(['type' => 'document']);
        } catch (Throwable) {
            // redirect() termine normalement la page.
        }
        $proposition = db_one('SELECT * FROM documents WHERE title = ?', ['Proposition archive ' . $suffixe]);
        verifier(
            'archives : une proposition d’archive d’un journaliste naît privée, en attente, classée archive',
            $proposition !== null
                && (int) $proposition['est_archive'] === 1
                && (string) $proposition['moderation_state'] === 'attente'
                && (string) $proposition['visibility'] === 'prive'
        );
        unset($_SESSION['account_id']);
        verifier(
            'archives : la proposition en attente est invisible pour un visiteur',
            $proposition !== null && find_viewable_document((int) $proposition['id']) === null
        );
        if ($proposition !== null) {
            // Le fichier d'essai ne reste pas dans le stockage.
            storage_delete((string) $proposition['rel_path']);
            storage_delete((string) ($proposition['thumb_path'] ?? ''));
        }

        // --- 7. Vocabulaire : plus de « catégorie » dans l'interface ---------
        $pagesInterface = [
            matrice_requete('GET', '/documents', null),
            matrice_requete('GET', '/articles', null),
            matrice_requete('GET', '/albums', null),
            matrice_requete('GET', '/recettes', null),
            matrice_requete('GET', '/admin/categories', $adminId),
            matrice_requete('GET', '/admin/dossiers', $adminId),
        ];
        $sansCategorie = true;
        foreach ($pagesInterface as $pageInterface) {
            // Les adresses (href, action…) ne sont pas du texte affiché :
            // « /admin/categories » et « ?categorie=Rubrique » restent des
            // noms internes, invisibles comme mots.
            $texteVisible = preg_replace('/(href|action|src)="[^"]*"/', '$1=""', $pageInterface['corps']);
            if (mb_stripos((string) $texteVisible, 'catégorie') !== false || mb_stripos((string) $texteVisible, 'categorie') !== false) {
                $sansCategorie = false;
            }
        }
        verifier(
            'thèmes : le mot « catégorie » n’apparaît plus dans l’interface',
            $sansCategorie
        );
        verifier_contient(
            'thèmes : l’administration s’appelle « Thèmes », avec sa phrase d’aide',
            'Un thème regroupe des contenus de tous types qui parlent du même sujet.',
            $pagesInterface[4]['corps']
        );

        // Cartes : le thème en surtitre, le type une seule fois.
        unset($_SESSION['account_id']);
        $doc = db_one('SELECT * FROM documents WHERE id = ?', [(int) $docJournalPublic]);
        $_GET = [];
        $carteHtml = '';
        if ($doc !== null) {
            $niveau = ob_get_level();
            ob_start();
            require HDF_APP . '/views/partials/carte_document.php';
            $carteHtml = (string) ob_get_clean();
            while (ob_get_level() > $niveau) {
                ob_end_clean();
            }
        }
        verifier(
            'thèmes : une carte montre le thème en surtitre et le type une seule fois',
            str_contains($carteHtml, 'Actes et papiers ' . $suffixe)
                && substr_count(mb_strtolower($carteHtml), 'pdf') <= 2
                && !str_contains($carteHtml, 'catégorie')
        );

        // Menu : mêmes règles, noms plus clairs.
        $menuLecteur = array_column(menu_rubriques(['role' => 'famille'], ['recettes' => true, 'temoignages' => true, 'tchat' => true]), 'libelle');
        $menuVisiteur = array_column(menu_rubriques(null), 'libelle');
        verifier(
            'menu : visiteur inchangé (Accueil), connecté avec les nouveaux noms',
            $menuVisiteur === ['Accueil']
                && in_array('Arbre généalogique', $menuLecteur, true)
                && in_array('Albums photos', $menuLecteur, true)
                && in_array('Témoignages (sons)', $menuLecteur, true)
                && in_array('Recherche', $menuLecteur, true)
                && in_array('Tchat', $menuLecteur, true)
        );
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION = $sessionAvant;
        $_GET = $getAvant;
        $_POST = $postAvant;
        $_FILES = $filesAvant;
    }
}
