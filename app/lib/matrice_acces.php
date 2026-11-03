<?php
/**
 * Matrice d'accès : qui peut ouvrir quoi ?
 *
 * Le test lit les routes dans `app/routes.php` (elles ne sont jamais recopiées
 * ici), crée des contenus fictifs dans toutes les visibilités, puis appelle
 * chaque page avec cinq profils :
 *
 *   1. visiteur (personne non connectée) ;
 *   2. branche A (compte de branche) ;
 *   3. personne de la branche B ;
 *   4. personne qui voit toutes les branches ;
 *   5. administrateur.
 *
 * Les pages sont appelées DANS LE MÊME PROCESSUS que le test (aucun serveur
 * web n'est nécessaire), avec une sortie capturée. Les données de test sont
 * supprimées à la fin : le test tourne dans une transaction annulée et les
 * fichiers sont effacés.
 *
 * Le rapport (`docs/matrice-acces.md`) est régénéré par :
 *
 *      php app/cli/tests.php matrice
 */

declare(strict_types=1);

/**
 * Routes déclarées dans app/routes.php, dans l'ordre du fichier.
 *
 * @return array<int, array{methode: string, motif: string, controleur: string}>
 */
function matrice_routes(): array
{
    $fichier = HDF_APP . '/routes.php';
    $code    = (string) @file_get_contents($fichier);

    preg_match_all(
        "/route\\(\\s*'(GET|POST)'\\s*,\\s*'([^']+)'\\s*,\\s*'([^']+)'\\s*\\)/",
        $code,
        $trouves,
        PREG_SET_ORDER
    );

    $routes = [];
    foreach ($trouves as $trouve) {
        $routes[] = [
            'methode'    => $trouve[1],
            'motif'      => $trouve[2],
            'controleur' => $trouve[3],
        ];
    }

    return $routes;
}

/**
 * Toutes les routes sont-elles reconnues ? (une route écrite autrement — par
 * exemple avec une closure — doit faire échouer le test plutôt que disparaître
 * silencieusement de la matrice.)
 *
 * @return string[] problèmes constatés
 */
function matrice_routes_problemes(): array
{
    $code    = (string) @file_get_contents(HDF_APP . '/routes.php');
    $routes  = matrice_routes();
    $appels  = substr_count($code, 'route(');
    $problèmes = [];

    if ($appels !== count($routes)) {
        $problèmes[] = $appels . ' appel(s) route() dans routes.php, '
            . count($routes) . ' reconnu(s) par la matrice';
    }
    foreach ($routes as $route) {
        if (matrice_famille($route['methode'], $route['motif']) === '') {
            $problèmes[] = 'route sans politique d’accès connue : '
                . $route['methode'] . ' ' . $route['motif'];
        }
    }

    return $problèmes;
}

/**
 * Famille d'accès d'une route (règle générale, jamais une liste de routes).
 *
 * Une nouvelle zone du site (par exemple /espace-prive) ne rentre dans aucune
 * famille : le test échoue et demande une décision explicite.
 */
function matrice_famille(string $methode, string $motif): string
{
    // « Mon compte » existe sous /mon-compte et sous /admin/mon-compte : c'est
    // la même page, la même fonction et les mêmes droits (chacun modifie SON
    // mot de passe). Le menu d'administration n'est affiché qu'aux
    // administrateurs. Cette adresse n'est donc pas une page d'administration.
    if ($motif === '/admin/mon-compte') {
        return 'famille';
    }
    if (str_starts_with($motif, '/admin') || $motif === '/tchat/{id}/supprimer') {
        return 'administration';
    }
    if ($motif === '/connexion' || $motif === '/deconnexion') {
        return 'connexion';
    }
    if ($motif === '/famille' || $motif === '/mon-compte' || str_starts_with($motif, '/mon-compte/')
        || str_starts_with($motif, '/tchat')) {
        return 'famille';
    }
    // Désinscription par courriel : ouverte à tous (le jeton signé fait office
    // d'authentification), mais une action se fait toujours en POST.
    if ($motif === '/desinscription') {
        return 'desinscription';
    }
    if (str_contains($motif, '{id}') || str_contains($motif, '{slug}')) {
        return 'contenu';
    }
    return 'public';
}

/** Les cinq profils testés, dans l'ordre des colonnes du rapport. */
function matrice_profils(): array
{
    return [
        'visiteur'   => 'Visiteur (non connecté)',
        'branche_a'  => 'Branche A',
        'personne_b' => 'Personne de la branche B',
        'voit_tout'  => 'Voit toutes les branches',
        'admin'      => 'Administrateur',
    ];
}

/** Code HTTP attendu selon la famille de la route et le profil. */
function matrice_attendu(string $methode, string $motif, string $profil): int
{
    $famille = matrice_famille($methode, $motif);
    $connecte = $profil !== 'visiteur';

    switch ($famille) {
        case 'administration':
            if (!$connecte) {
                return 303; // renvoi vers la page de connexion
            }
            // POST sans jeton CSRF : refusé pour tout le monde ; une page
            // d'administration n'est ouverte qu'à un administrateur.
            return $profil === 'admin' && $methode === 'GET' ? 200 : 403;

        case 'famille':
            if (!$connecte) {
                return 303;
            }
            // POST sans jeton CSRF : refusé.
            return $methode === 'POST' ? 403 : 200;

        case 'desinscription':
            // Sans jeton dans l'adresse : « lien invalide ». Sans jeton
            // anti-CSRF : l'action est refusée.
            return $methode === 'POST' ? 403 : 404;

        case 'connexion':
            if ($methode === 'POST') {
                return 403; // sans jeton CSRF
            }
            if ($motif === '/deconnexion') {
                return 405; // la déconnexion est une action POST
            }
            // Déjà connecté : la page de connexion renvoie vers l'accueil.
            return $connecte ? 303 : 200;

        default:
            return 200;
    }
}

/**
 * Adresse réelle d'une route, à partir des contenus fictifs.
 */
function matrice_url(string $motif, array $f): string
{
    $url = $motif;

    if (str_contains($url, '{slug}')) {
        $slug = 'page';
        if (str_starts_with($url, '/articles')) {
            $slug = $f['article_slug'];
        } elseif (str_starts_with($url, '/albums')) {
            $slug = $f['album_slug'];
        } elseif (str_starts_with($url, '/recettes')) {
            $slug = $f['recipe_slug'];
        } elseif (str_starts_with($url, '/admin/comptes/resultat')) {
            $slug = 'jeton-de-test';
        } else {
            $slug = $f['page_slug'];
        }
        $url = str_replace('{slug}', $slug, $url);
    }

    if (str_contains($url, '{id}')) {
        $id = match (true) {
            str_starts_with($url, '/media/')                    => $f['doc_public'],
            str_starts_with($url, '/f/'),
            str_starts_with($url, '/documents/')                => $f['doc_pdf'],
            str_starts_with($url, '/video/'),
            str_starts_with($url, '/videos/'),
            str_starts_with($url, '/admin/videos/')             => $f['doc_video'],
            str_starts_with($url, '/api/arbre/'),
            str_starts_with($url, '/arbre/'),
            str_starts_with($url, '/admin/arbre/personne/')     => $f['person'],
            str_starts_with($url, '/admin/arbre/couple/')       => $f['family'],
            str_starts_with($url, '/admin/documents/')          => $f['doc_pdf'],
            str_starts_with($url, '/admin/albums/')             => $f['album'],
            str_starts_with($url, '/admin/articles/')           => $f['article'],
            str_starts_with($url, '/admin/recettes/')           => $f['recipe'],
            // La fiche d'un compte « personne » a un écran ; les comptes de
            // branche et les administrateurs sont renvoyés vers leur liste.
            str_starts_with($url, '/admin/comptes/')            => $f['compte_personne_b'],
            str_starts_with($url, '/admin/branches/')           => $f['branch_a'],
            str_starts_with($url, '/admin/admins/')             => $f['account_admin'],
            str_starts_with($url, '/admin/pages/')              => $f['page'],
            str_starts_with($url, '/admin/categories/')         => $f['category'],
            str_starts_with($url, '/tchat/')                    => $f['chat_message'],
            default                                             => $f['doc_pdf'],
        };
        $url = str_replace('{id}', (string) $id, $url);
    }

    return $url;
}

/**
 * Crée les comptes et contenus fictifs de la matrice.
 *
 * IMPORTANT : cette fonction suppose que l'appelant a ouvert une transaction
 * (elle est annulée à la fin du test) et que les fichiers créés sont supprimés
 * par l'appelant (voir matrice_supprimer_fichiers).
 *
 * @return array<string, mixed>
 */
function matrice_fixtures(): array
{
    $maintenant = time();
    $suffixe    = bin2hex(random_bytes(3));

    // Un hachage de mot de passe existant suffit : aucun mot de passe réel
    // n'est créé, écrit ou affiché par ce test.
    $hachage = (string) db_value('SELECT password_hash FROM accounts LIMIT 1', [], '');
    if ($hachage === '') {
        $hachage = password_make(bin2hex(random_bytes(16)));
    }

    // --- Branches et comptes ------------------------------------------------
    $branchA = db_insert('branches', [
        'code' => 'MATR-A-' . $suffixe, 'name' => 'Branche A (matrice)',
        'description' => 'Branche fictive du test d’accès.', 'see_all' => 0,
        'is_active' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $branchB = db_insert('branches', [
        'code' => 'MATR-B-' . $suffixe, 'name' => 'Branche B (matrice)',
        'description' => 'Branche fictive du test d’accès.', 'see_all' => 0,
        'is_active' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $branchVoirTout = db_insert('branches', [
        'code' => 'MATR-T-' . $suffixe, 'name' => 'Branche « voit tout » (matrice)',
        'description' => 'Branche fictive du test d’accès.', 'see_all' => 1,
        'is_active' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    $compteBrancheA = db_insert('accounts', [
        'username' => 'matrice.a.' . $suffixe, 'display_name' => 'Branche A (essai)',
        'password_hash' => $hachage, 'role' => 'famille', 'account_type' => 'branche',
        'branch_id' => $branchA, 'is_active' => 1,
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $comptePersonneB = db_insert('accounts', [
        'username' => 'matrice.b.' . $suffixe, 'display_name' => 'Personne B (essai)',
        'password_hash' => $hachage, 'role' => 'famille', 'account_type' => 'personne',
        'first_name' => 'Personne', 'last_name' => 'B (essai)',
        'branch_id' => $branchB, 'is_active' => 1, 'see_all' => 0,
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $compteVoitTout = db_insert('accounts', [
        'username' => 'matrice.t.' . $suffixe, 'display_name' => 'Voit tout (essai)',
        'password_hash' => $hachage, 'role' => 'famille', 'account_type' => 'personne',
        'first_name' => 'Voit', 'last_name' => 'Tout (essai)',
        'branch_id' => $branchVoirTout, 'is_active' => 1, 'see_all' => 1,
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $compteAdmin = db_insert('accounts', [
        'username' => 'matrice.admin.' . $suffixe, 'display_name' => 'Administrateur (essai)',
        'password_hash' => $hachage, 'role' => 'admin', 'account_type' => 'admin',
        'is_active' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    // --- Fichiers fictifs sur le disque (supprimés après le test) -----------
    $dossier = rtrim((string) config('storage.root'), '/') . '/matrice-' . $suffixe;
    if (!is_dir($dossier) && !@mkdir($dossier, 0770, true) && !is_dir($dossier)) {
        throw new RuntimeException('Impossible de créer le dossier de test ' . $dossier);
    }
    $relatif = 'matrice-' . $suffixe;

    $fichiers = [
        'photo.jpg'  => ['mime' => 'image/jpeg', 'ext' => 'jpg', 'contenu' => str_repeat('PHOTO', 20)],
        'thumb.jpg'  => ['mime' => 'image/jpeg', 'ext' => 'jpg', 'contenu' => 'MINIATURE'],
        'video.mp4'  => ['mime' => 'video/mp4', 'ext' => 'mp4', 'contenu' => str_repeat('VIDEO', 100)],
        'audio.mp3'  => ['mime' => 'audio/mpeg', 'ext' => 'mp3', 'contenu' => str_repeat('AUDIO', 40)],
        'acte.pdf'   => ['mime' => 'application/pdf', 'ext' => 'pdf', 'contenu' => '%PDF-1.4 test'],
    ];
    foreach ($fichiers as $nom => $info) {
        file_put_contents($dossier . '/' . $nom, $info['contenu']);
    }

    $categorie = db_insert('categories', [
        'name' => 'Catégorie (matrice)', 'slug' => 'matrice-' . $suffixe,
        'position' => 0, 'created_at' => $maintenant,
    ]);

    $creerDocument = static function (
        string $titre,
        string $visibilite,
        string $genre,
        string $fichier,
        ?string $miniature,
        array $branches
    ) use ($maintenant, $relatif, $fichiers, $categorie, $compteAdmin): int {
        $id = db_insert('documents', [
            'title'       => $titre,
            'description' => 'Contenu fictif du test d’accès.',
            'category_id' => $categorie,
            'kind'        => $genre,
            'visibility'  => $visibilite,
            'rel_path'    => $relatif . '/' . $fichier,
            'thumb_path'  => $miniature !== null ? $relatif . '/' . $miniature : null,
            'original_name' => $fichier,
            'mime'        => $fichiers[$fichier]['mime'],
            'ext'         => $fichiers[$fichier]['ext'],
            'size'        => (int) filesize(rtrim((string) config('storage.root'), '/') . '/' . $relatif . '/' . $fichier),
            'created_by'  => $compteAdmin,
            'created_at'  => $maintenant,
            'updated_at'  => $maintenant,
        ]);
        foreach ($branches as $branche) {
            db_insert('content_branches', ['kind' => 'document', 'content_id' => $id, 'branch_id' => $branche]);
        }
        return $id;
    };

    $docPublic  = $creerDocument('Photo publique (essai)', 'public', 'photo', 'photo.jpg', 'thumb.jpg', []);
    $docFamille = $creerDocument('Photo famille (essai)', 'famille', 'photo', 'photo.jpg', 'thumb.jpg', []);
    $docBrancheA = $creerDocument('Photo branche A (essai)', 'branches', 'photo', 'photo.jpg', 'thumb.jpg', [$branchA]);
    $docBrancheB = $creerDocument('Photo branche B (essai)', 'branches', 'photo', 'photo.jpg', 'thumb.jpg', [$branchB]);
    $docPrive   = $creerDocument('Photo privée (essai)', 'prive', 'photo', 'photo.jpg', 'thumb.jpg', []);
    $docVideo   = $creerDocument('Vidéo publique (essai)', 'public', 'video', 'video.mp4', null, []);
    $docAudio   = $creerDocument('Témoignage audio (essai)', 'public', 'document', 'audio.mp3', null, []);
    $docPdf     = $creerDocument('Acte public (essai)', 'public', 'document', 'acte.pdf', null, []);

    // --- Album, article, page, recette, personne, couple, tchat -------------
    $album = db_insert('albums', [
        'title' => 'Album (essai)', 'slug' => 'album-' . $suffixe,
        'description' => 'Album fictif du test d’accès.', 'cover_document_id' => $docPublic,
        'visibility' => 'public', 'position' => 0, 'created_by' => $compteAdmin,
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    db_insert('album_documents', ['album_id' => $album, 'document_id' => $docPublic, 'position' => 0]);
    db_insert('album_documents', ['album_id' => $album, 'document_id' => $docPrive, 'position' => 1]);

    $article = db_insert('articles', [
        'slug' => 'article-' . $suffixe, 'title' => 'Article public (essai)',
        'summary' => 'Article fictif.', 'body' => 'Texte public du test d’accès.',
        'category' => '', 'visibility' => 'public', 'status' => 'publie',
        'published_at' => $maintenant - 60, 'author_id' => $compteAdmin,
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    db_insert('articles', [
        'slug' => 'article-prive-' . $suffixe, 'title' => 'SECRET-PRIVE-' . $suffixe,
        'summary' => 'Ne doit jamais apparaître chez un visiteur.', 'body' => 'SECRET-PRIVE-' . $suffixe,
        'category' => '', 'visibility' => 'prive', 'status' => 'publie',
        'published_at' => $maintenant - 60, 'author_id' => $compteAdmin,
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    $page = db_insert('pages', [
        'slug' => 'page-' . $suffixe, 'title' => 'Page publique (essai)',
        'body' => 'Page fictive du test d’accès.', 'visibility' => 'public',
        'is_published' => 1, 'show_in_nav' => 0, 'position' => 0,
        'created_at' => $maintenant, 'updated_at' => $maintenant, 'updated_by' => $compteAdmin,
    ]);
    $pagePrivee = db_insert('pages', [
        'slug' => 'page-privee-' . $suffixe, 'title' => 'SECRET-PAGE-' . $suffixe,
        'body' => 'SECRET-PAGE-' . $suffixe, 'visibility' => 'prive',
        'is_published' => 1, 'show_in_nav' => 0, 'position' => 0,
        'created_at' => $maintenant, 'updated_at' => $maintenant, 'updated_by' => $compteAdmin,
    ]);

    $recipe = db_insert('recipes', [
        'title' => 'Recette (essai)', 'slug' => 'recette-' . $suffixe,
        'origin' => 'Test', 'servings' => '2', 'ingredients' => 'Eau',
        'preparation' => 'Mélanger.', 'visibility' => 'public',
        'created_by' => $compteAdmin, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    $person = db_insert('persons', [
        'first_name' => 'Camille', 'last_name' => 'Essai-' . $suffixe,
        'sex' => 'F', 'birth_year' => 1900, 'visibility' => 'public',
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $person2 = db_insert('persons', [
        'first_name' => 'Dominique', 'last_name' => 'Essai-' . $suffixe,
        'sex' => 'M', 'birth_year' => 1898, 'visibility' => 'public',
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $family = db_insert('families', [
        'husband_id' => $person2, 'wife_id' => $person, 'marriage_date' => '1920',
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    $chat = db_insert('chat_messages', [
        'account_id' => $compteBrancheA, 'author_label' => 'Branche A (essai)',
        'author_branch' => 'MATR-A', 'body' => 'Message de test.',
        'created_at' => $maintenant,
    ]);

    return [
        'branches' => [$branchA, $branchB, $branchVoirTout],
        'branch_a' => $branchA,
        'branch_b' => $branchB,
        'compte_branche_a' => $compteBrancheA,
        'compte_personne_b' => $comptePersonneB,
        'compte_voit_tout' => $compteVoitTout,
        'account_admin' => $compteAdmin,
        'account_branche_a' => $compteBrancheA,
        'doc_public' => $docPublic,
        'doc_famille' => $docFamille,
        'doc_branche_a' => $docBrancheA,
        'doc_branche_b' => $docBrancheB,
        'doc_prive' => $docPrive,
        'doc_video' => $docVideo,
        'doc_audio' => $docAudio,
        'doc_pdf' => $docPdf,
        'album' => $album,
        'album_slug' => 'album-' . $suffixe,
        'article' => $article,
        'article_slug' => 'article-' . $suffixe,
        'page' => $page,
        'page_slug' => 'page-' . $suffixe,
        'page_privee' => $pagePrivee,
        'page_privee_slug' => 'page-privee-' . $suffixe,
        'recipe' => $recipe,
        'recipe_slug' => 'recette-' . $suffixe,
        'person' => $person,
        'person2' => $person2,
        'family' => $family,
        'category' => $categorie,
        'chat_message' => $chat,
        'secret_article' => 'SECRET-PRIVE-' . $suffixe,
        'secret_page' => 'SECRET-PAGE-' . $suffixe,
        'dossier_fichiers' => $dossier,
    ];
}

/** Supprime les fichiers fictifs de la matrice. */
function matrice_supprimer_fichiers(array $f): void
{
    $dossier = (string) ($f['dossier_fichiers'] ?? '');
    if ($dossier === '' || !is_dir($dossier)) {
        return;
    }
    foreach (glob($dossier . '/*') ?: [] as $fichier) {
        @unlink($fichier);
    }
    @rmdir($dossier);
}

/** Le compte correspondant à un profil. */
function matrice_compte(string $profil, array $f): ?int
{
    return match ($profil) {
        'branche_a'  => (int) $f['compte_branche_a'],
        'personne_b' => (int) $f['compte_personne_b'],
        'voit_tout'  => (int) $f['compte_voit_tout'],
        'admin'      => (int) $f['account_admin'],
        default      => null,
    };
}

/**
 * Envoie une requête à l'application, dans le processus courant.
 *
 * @param array<string, string> $entetes en-têtes HTTP (ex. Range)
 * @param array<string, string> $post    données POST
 * @return array{code: int, location: string, corps: string, erreur: string}
 */
function matrice_requete(string $methode, string $url, ?int $compte, array $entetes = [], array $post = []): array
{
    require_once HDF_APP . '/router.php';
    if (empty($GLOBALS['hdf_routes'])) {
        require_once HDF_APP . '/routes.php';
    }

    $parties = parse_url($url);
    $_SESSION = $compte === null ? [] : ['account_id' => $compte];
    $_GET     = [];
    if (isset($parties['query'])) {
        parse_str((string) $parties['query'], $_GET);
    }
    $_POST    = $post;
    $_FILES   = [];
    $_SERVER['REQUEST_METHOD'] = $methode;
    $_SERVER['REQUEST_URI']    = $url;
    $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    unset($_SERVER['HTTP_RANGE'], $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE']);
    foreach ($entetes as $nom => $valeur) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $nom))] = $valeur;
    }

    header_remove();
    http_response_code(200);

    $niveau = ob_get_level();
    ob_start();
    $erreur = '';
    try {
        dispatch_request();
    } catch (HdfTestArret) {
        // Fin normale d'une page (redirection, refus, réponse JSON…).
    } catch (Throwable $e) {
        $erreur = get_class($e) . ' : ' . $e->getMessage();
    }
    $corps = '';
    while (ob_get_level() > $niveau) {
        $corps = (string) ob_get_clean() . $corps;
    }

    $location = '';
    foreach (headers_list() as $entete) {
        if (stripos($entete, 'Location:') === 0) {
            $location = trim(substr($entete, 9));
        }
    }

    return [
        'code'     => (int) http_response_code(),
        'location' => $location,
        'corps'    => $corps,
        'erreur'   => $erreur,
    ];
}

/**
 * Exécute la matrice complète.
 *
 * @return array<int, array{methode: string, motif: string, controleur: string, famille: string, url: string, attendu: array<string,int>, obtenu: array<string,int>, erreurs: array<string,string>}>
 */
function matrice_executer(array $f): array
{
    $lignes = [];

    foreach (matrice_routes() as $route) {
        $url    = matrice_url($route['motif'], $f);
        $famille = matrice_famille($route['methode'], $route['motif']);
        $ligne  = [
            'methode'    => $route['methode'],
            'motif'      => $route['motif'],
            'controleur' => $route['controleur'],
            'famille'    => $famille,
            'url'        => $url,
            'attendu'    => [],
            'obtenu'     => [],
            'erreurs'    => [],
        ];

        foreach (array_keys(matrice_profils()) as $profil) {
            $compte   = matrice_compte($profil, $f);
            $reponse  = matrice_requete($route['methode'], $url, $compte);
            $attendu  = matrice_attendu($route['methode'], $route['motif'], $profil);
            $ligne['attendu'][$profil] = $attendu;
            $ligne['obtenu'][$profil]  = $reponse['code'];
            if ($reponse['erreur'] !== '') {
                $ligne['erreurs'][$profil] = $reponse['erreur'];
            }
        }

        $lignes[] = $ligne;
    }

    return $lignes;
}

/** Une ligne de la matrice est-elle conforme ? */
function matrice_ligne_ok(array $ligne): bool
{
    return $ligne['attendu'] === $ligne['obtenu'] && $ligne['erreurs'] === [];
}

/**
 * Anomalies : lignes non conformes, avec une explication en français.
 *
 * @return array<int, array{route: string, constat: string, attendu: string, obtenu: string}>
 */
function matrice_anomalies(array $lignes): array
{
    $anomalies = [];
    $noms      = matrice_profils();

    foreach ($lignes as $ligne) {
        foreach ($noms as $profil => $libelle) {
            $attendu = $ligne['attendu'][$profil] ?? 0;
            $obtenu  = $ligne['obtenu'][$profil] ?? 0;
            if ($attendu !== $obtenu) {
                $anomalies[] = [
                    'route'   => $ligne['methode'] . ' ' . $ligne['motif'],
                    'profil'  => $libelle,
                    'attendu' => matrice_code_libelle($attendu),
                    'obtenu'  => matrice_code_libelle($obtenu),
                ];
            }
            if (isset($ligne['erreurs'][$profil])) {
                $anomalies[] = [
                    'route'   => $ligne['methode'] . ' ' . $ligne['motif'],
                    'profil'  => $libelle,
                    'attendu' => 'page affichée',
                    'obtenu'  => 'erreur : ' . $ligne['erreurs'][$profil],
                ];
            }
        }
    }

    return $anomalies;
}

/** Traduction lisible d'un code HTTP. */
function matrice_code_libelle(int $code): string
{
    return match ($code) {
        200 => '200 (page)',
        206 => '206 (morceau de fichier)',
        302, 303 => $code . ' (renvoi)',
        403 => '403 (refusé)',
        404 => '404 (introuvable)',
        405 => '405 (méthode refusée)',
        416 => '416 (plage invalide)',
        503 => '503 (indisponible)',
        default => (string) $code,
    };
}

/**
 * Contrôles ciblés demandés par la matrice : fichiers et identifiants,
 * export GEDCOM, recherche, calendrier, albums, tchat, pages système.
 *
 * @return array<int, array{controle: string, attendu: string, obtenu: string, ok: bool}>
 */
function matrice_controles(array $f): array
{
    $controles = [];

    $ajouter = static function (string $nom, string $attendu, string $obtenu) use (&$controles): void {
        $controles[] = [
            'controle' => $nom,
            'attendu'  => $attendu,
            'obtenu'   => $obtenu,
            'ok'       => $attendu === $obtenu,
        ];
    };

    // 1. Identifiants de fichiers : chaque visibilité, chaque profil.
    $visibilites = [
        'public'    => 'doc_public',
        'famille'   => 'doc_famille',
        'branche A' => 'doc_branche_a',
        'branche B' => 'doc_branche_b',
        'privé'     => 'doc_prive',
    ];
    $profils = array_keys(matrice_profils());
    $visible = static function (string $visibilite, string $profil) use ($f): bool {
        if ($visibilite === 'public') {
            return true;
        }
        if ($profil === 'visiteur') {
            return false;
        }
        if ($visibilite === 'privé') {
            // « Privé » n'est visible que par un administrateur : même un
            // compte qui voit toutes les branches n'y a pas accès.
            return $profil === 'admin';
        }
        if ($profil === 'admin' || $profil === 'voit_tout') {
            return true;
        }
        if ($visibilite === 'famille') {
            return true;
        }
        if ($visibilite === 'branche A') {
            return $profil === 'branche_a';
        }
        if ($visibilite === 'branche B') {
            return $profil === 'personne_b';
        }
        return false;
    };

    foreach ($visibilites as $libelle => $cle) {
        $id = (int) $f[$cle];
        foreach ($profils as $profil) {
            $attendu = $visible($libelle, $profil) ? 200 : 404;
            $compte  = matrice_compte($profil, $f);

            $reponses = [
                '/f/' . $id                      => matrice_requete('GET', '/f/' . $id, $compte)['code'],
                '/f/' . $id . '/telecharger'     => matrice_requete('GET', '/f/' . $id . '/telecharger', $compte)['code'],
                '/media/miniature/' . $id        => matrice_requete('GET', '/media/miniature/' . $id, $compte)['code'],
            ];
            foreach ($reponses as $url => $code) {
                $ajouter(
                    'fichier ' . $libelle . ' — ' . $url . ' — ' . matrice_profils()[$profil],
                    (string) $attendu,
                    (string) $code
                );
            }
        }
    }

    // 2. Flux vidéo et audio, avec et sans « Range ».
    //    La vidéo passe par /video/{id} ; l'audio, comme les autres documents,
    //    par /f/{id} (c'est le même envoi, avec gestion des morceaux).
    foreach ([['/video/', 'doc_video', 'vidéo publique'], ['/f/', 'doc_audio', 'audio public']] as $cas) {
        [$prefixe, $cle, $libelle] = $cas;
        $id = (int) $f[$cle];

        $sans = matrice_requete('GET', $prefixe . $id, null);
        $ajouter($libelle . ' — lecture complète (visiteur)', '200', (string) $sans['code']);

        $avec = matrice_requete('GET', $prefixe . $id, null, ['Range' => 'bytes=0-3']);
        $ajouter($libelle . ' — lecture par morceaux (Range)', '206', (string) $avec['code']);

        // Une plage absurde est refusée proprement.
        $invalide = matrice_requete('GET', $prefixe . $id, null, ['Range' => 'bytes=999999-']);
        $ajouter($libelle . ' — plage invalide refusée', '416', (string) $invalide['code']);
    }
    $prive = matrice_requete('GET', '/video/' . (int) $f['doc_prive'], null);
    $ajouter('vidéo privée — refusée au visiteur', '404', (string) $prive['code']);

    // 3. Export GEDCOM : réservé aux administrateurs.
    $ajouter(
        'export GEDCOM — visiteur renvoyé vers la connexion',
        '303',
        (string) matrice_requete('GET', '/admin/export/gedcom', null)['code']
    );
    $ajouter(
        'export GEDCOM — compte de branche refusé',
        '403',
        (string) matrice_requete('GET', '/admin/export/gedcom', (int) $f['compte_branche_a'])['code']
    );
    $ajouter(
        'export GEDCOM — administrateur autorisé',
        '200',
        (string) matrice_requete('GET', '/admin/export/gedcom', (int) $f['account_admin'])['code']
    );

    // 4. Recherche : le contenu privé ne doit jamais sortir.
    $rechercheVisiteur = matrice_requete('GET', '/recherche?q=SECRET-PRIVE', null);
    $ajouter(
        'recherche — visiteur : aucun contenu privé',
        'aucun secret',
        str_contains($rechercheVisiteur['corps'], (string) $f['secret_article']) ? 'SECRET DIVULGUÉ' : 'aucun secret'
    );
    $rechercheAdmin = matrice_requete('GET', '/recherche?q=SECRET-PRIVE', (int) $f['account_admin']);
    $ajouter(
        'recherche — administrateur : le contenu privé est trouvé',
        'trouvé',
        str_contains($rechercheAdmin['corps'], (string) $f['secret_article']) ? 'trouvé' : 'introuvable'
    );
    $ajouter(
        'page privée — introuvable pour un visiteur',
        '404',
        (string) matrice_requete('GET', '/pages/' . $f['page_privee_slug'], null)['code']
    );
    $ajouter(
        'page privée — visible par l’administrateur',
        '200',
        (string) matrice_requete('GET', '/pages/' . $f['page_privee_slug'], (int) $f['account_admin'])['code']
    );

    // 5. Calendrier et albums : aucun contenu privé chez le visiteur.
    $calendrier = matrice_requete('GET', '/calendrier', null);
    $ajouter(
        'calendrier — visiteur : aucun titre privé',
        'aucun secret',
        str_contains($calendrier['corps'], 'Photo privée (essai)') ? 'TITRE PRIVÉ DIVULGUÉ' : 'aucun secret'
    );
    $album = matrice_requete('GET', '/albums/' . $f['album_slug'], null);
    $ajouter(
        'album public — la photo privée n’est pas montrée au visiteur',
        'aucun secret',
        str_contains($album['corps'], 'Photo privée (essai)') ? 'PHOTO PRIVÉE DIVULGUÉE' : 'aucun secret'
    );
    $ajouter(
        'album public — la photo publique est bien montrée',
        'montrée',
        str_contains($album['corps'], 'Photo publique (essai)') ? 'montrée' : 'absente'
    );

    // 6. Tchat : jamais accessible à un visiteur.
    foreach (['/tchat', '/tchat/messages.json', '/famille', '/mon-compte'] as $url) {
        $ajouter(
            'tchat / espace famille — ' . $url . ' — visiteur renvoyé vers la connexion',
            '303',
            (string) matrice_requete('GET', $url, null)['code']
        );
    }

    // 7. Actions d'administration en POST : sans jeton CSRF et pour un
    //    non-administrateur, rien ne doit passer.
    $postsAdmin = array_values(array_filter(
        matrice_routes(),
        static fn (array $r): bool => $r['methode'] === 'POST' && matrice_famille('POST', $r['motif']) === 'administration'
    ));
    foreach ($postsAdmin as $route) {
        $url = matrice_url($route['motif'], $f);
        $ajouter(
            'POST ' . $route['motif'] . ' — sans jeton CSRF (administrateur)',
            '403',
            (string) matrice_requete('POST', $url, (int) $f['account_admin'])['code']
        );
        $ajouter(
            'POST ' . $route['motif'] . ' — compte de branche',
            '403',
            (string) matrice_requete('POST', $url, (int) $f['compte_branche_a'])['code']
        );
    }

    // 8. Pages système : aucun contenu sensible.
    $theme = matrice_requete('GET', '/theme.css', null);
    $ajouter(
        'theme.css — feuille de style servie au visiteur',
        '200',
        (string) $theme['code']
    );
    $ajouter(
        'theme.css — aucun contenu d’administration',
        'aucune fuite',
        preg_match('/admin|mot de passe|password/i', $theme['corps']) === 1 ? 'FUITE' : 'aucune fuite'
    );
    $maintenance = matrice_requete('GET', '/maintenance', null);
    $ajouter(
        'maintenance — page d’attente sans contenu privé',
        'aucune fuite',
        preg_match('/SECRET-|Photo privée|password/i', $maintenance['corps']) === 1 ? 'FUITE' : 'aucune fuite'
    );

    return $controles;
}

/** Tableau markdown de la matrice (profils en colonnes, routes en lignes). */
function matrice_tableau_markdown(array $lignes): string
{
    $profils = matrice_profils();
    $entete  = '| Méthode | Route |';
    $sep     = '|---|---|';
    foreach ($profils as $libelle) {
        $entete .= ' ' . $libelle . ' |';
        $sep    .= '---|';
    }
    $entete .= ' Famille |';
    $sep    .= '---|';

    $texte = $entete . "\n" . $sep . "\n";
    foreach ($lignes as $ligne) {
        $texte .= '| ' . $ligne['methode'] . ' | `' . $ligne['motif'] . '` |';
        foreach (array_keys($profils) as $profil) {
            $code = (int) ($ligne['obtenu'][$profil] ?? 0);
            $texte .= ' ' . $code . ' |';
        }
        $texte .= ' ' . $ligne['famille'] . " |\n";
    }

    return $texte;
}

/** Rapport complet, en français, prêt à être enregistré dans docs/. */
function matrice_rapport(array $lignes, array $anomalies, array $controles): string
{
    $total   = count($lignes) * count(matrice_profils());
    $anomaliesTexte = $anomalies === []
        ? "Aucune anomalie : pour chaque route, le code obtenu correspond au code attendu.\n"
        : '';

    if ($anomalies !== []) {
        $anomaliesTexte = "| Route | Profil | Attendu | Obtenu |\n|---|---|---|---|\n";
        foreach ($anomalies as $anomalie) {
            $anomaliesTexte .= '| `' . $anomalie['route'] . '` | ' . $anomalie['profil']
                . ' | ' . $anomalie['attendu'] . ' | ' . $anomalie['obtenu'] . " |\n";
        }
    }

    $controlesKo = array_values(array_filter($controles, static fn (array $c): bool => !$c['ok']));
    $controlesTexte = "| Contrôle | Attendu | Obtenu |\n|---|---|---|\n";
    foreach ($controles as $controle) {
        $controlesTexte .= '| ' . $controle['controle'] . ' | ' . $controle['attendu']
            . ' | ' . ($controle['ok'] ? $controle['obtenu'] : '**' . $controle['obtenu'] . '**') . " |\n";
    }

    $notes = "| Point | Décision |\n|---|---|\n"
        . "| `GET` et `POST /admin/mon-compte` | ouverts à tout compte connecté : c'est la page « Mon compte » (chacun modifie **son** mot de passe), pas une page d'administration. Le menu d'administration n'est affiché qu'aux administrateurs. |\n"
        . "| `GET /admin/comptes/{id}` | renvoie vers « Branches & comptes » ou « Administrateurs » pour un compte de branche : la fiche détaillée n'existe que pour les comptes « personne ». |\n"
        . "| `GET /famille`, `/tchat`, `/admin` pour un visiteur | **303** : renvoi vers `/connexion` (avec le chemin de retour), jamais la page elle-même. |\n"
        . "| Contenu non autorisé | **404** identique à une page qui n'existe pas : l'existence d'un document privé n'est pas révélée. |\n"
        . "| `POST` sans jeton CSRF | **403**, y compris pour un administrateur. |\n";

    return "# Matrice d'accès\n\n"
        . "> **Document engendré automatiquement.** Ne pas modifier à la main : il est\n"
        . "> réécrit par `php app/cli/tests.php matrice`. Il décrit ce que le site\n"
        . "> répond réellement, route par route et profil par profil.\n\n"
        . "Date de génération : " . date('d/m/Y à H:i') . "\n\n"
        . "## Profils testés\n\n"
        . "| Profil | Qui c'est |\n|---|---|\n"
        . "| Visiteur | personne non connectée |\n"
        . "| Branche A | compte de la branche A (ne voit pas la branche B) |\n"
        . "| Personne de la branche B | compte « personne » rattaché à la branche B |\n"
        . "| Voit toutes les branches | compte « personne » coché « voit toutes les branches » |\n"
        . "| Administrateur | compte administrateur actif |\n\n"
        . "## Codes attendus\n\n"
        . "- **200** : la page s'affiche ;\n"
        . "- **303** : renvoi vers la page de connexion (ou vers l'accueil) ;\n"
        . "- **403** : accès refusé (compte connecté mais sans le droit, ou jeton CSRF absent) ;\n"
        . "- **404** : introuvable — c'est aussi la réponse pour un contenu que l'on n'a pas le droit de voir (aucune fuite d'existence) ;\n"
        . "- **405** : méthode refusée (une action POST appelée en GET).\n\n"
        . "## Résultat\n\n"
        . "- Routes lues dans `app/routes.php` : **" . count($lignes) . "** ;\n"
        . "- Vérifications (route × profil) : **" . $total . "** ;\n"
        . "- Anomalies : **" . count($anomalies) . "** ;\n"
        . "- Contrôles ciblés : **" . count($controles) . "**, dont **" . count($controlesKo) . "** en échec.\n\n"
        . "## Points examinés\n\n" . $notes . "\n"
        . "## Anomalies constatées\n\n" . $anomaliesTexte . "\n"
        . "## Contrôles ciblés\n\n" . $controlesTexte . "\n"
        . "## Tableau complet\n\n" . matrice_tableau_markdown($lignes);
}
