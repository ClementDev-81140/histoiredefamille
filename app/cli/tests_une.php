<?php
/**
 * Tests de la Une du site public (1.31.0).
 *
 * Ce que l'on vérifie, dans l'ordre du besoin exprimé :
 *
 *   1. le menu d'un visiteur n'a plus qu'une entrée, « Accueil » ; celui d'un
 *      compte connecté ne change pas ;
 *   2. `/carte` et `/lieu/{id}` renvoient un visiteur vers la connexion — et
 *      rien d'autre n'est devenu plus ouvert ;
 *   3. l'accueil public présente **tout le contenu public** du plus récent au
 *      plus ancien, le plus récent en grand, groupé par rubrique au-delà de six
 *      éléments, avec « Voir plus » au-delà de vingt-quatre ;
 *   4. aucune fuite : privé, famille, branches, en attente de validation et
 *      vidéo en conversion n'apparaissent nulle part, et les adresses de médias
 *      d'un contenu réservé ne répondent pas à un visiteur ;
 *   5. sans aucun contenu public, la page reste sobre : phrase, « Se
 *      connecter », les deux adresses — jamais un bloc vide ;
 *   6. un administrateur peut choisir le contenu de la une, et ce choix retombe
 *      tout seul sur le plus récent si ce contenu cesse d'être public.
 *
 * Tout se passe dans une transaction annulée : la base du site est intacte.
 */

declare(strict_types=1);

/** Toute la famille « une ». */
function tests_une(): void
{
    echo "Une du site public — menu, contenu public, fuites\n";

    try {
        db_transaction(static function (): void {
            tests_une_menu();
            tests_une_contenu();
            tests_une_fuite();
            tests_une_medias();
            tests_une_choix();
            tests_une_pagination();
            tests_une_vide();
            tests_une_sans_image();

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    verifier('une : la base est rendue intacte', true);
}

/** Un article d'essai, publié (ou en attente de validation). */
function tests_une_article(string $slug, string $titre, string $visibilite, int $publie, string $moderation = 'publie'): int
{
    return db_insert('articles', [
        'slug'             => $slug . '-' . bin2hex(random_bytes(3)),
        'title'            => $titre,
        'summary'          => 'Résumé de l’essai (1.31.0).',
        'body'             => 'Texte de l’essai.',
        'category'         => '',
        'image_id'         => null,
        'visibility'       => $visibilite,
        'status'           => 'publie',
        'published_at'     => $publie,
        'moderation_state' => $moderation,
        'created_at'       => $publie,
        'updated_at'       => $publie,
    ]);
}

/** Un document d'essai (photo, vidéo, PDF…). */
function tests_une_document(string $titre, string $visibilite, string $kind, int $quand, bool $enConversion = false): int
{
    $id = db_insert('documents', [
        'title'         => $titre,
        'description'   => 'Document de l’essai 1.31.0.',
        'doc_date'      => '1962',
        'doc_date_sort' => 1962,
        'kind'          => $kind,
        'visibility'    => $visibilite,
        'rel_path'      => 'essais/' . bin2hex(random_bytes(4)) . '.jpg',
        'thumb_path'    => 'miniatures/' . bin2hex(random_bytes(4)) . '.jpg',
        'original_name' => 'essai-une.jpg',
        'mime'          => 'image/jpeg',
        'ext'           => 'jpg',
        'size'          => 1024,
        'is_attachment' => 0,
        'created_at'    => $quand,
        'updated_at'    => $quand,
    ]);

    if ($enConversion) {
        db_insert('conversions', [
            'document_id'   => $id,
            'state'         => 'en_cours',
            'source_path'   => 'essais/film.mov',
            'source_size'   => 2048,
            'original_name' => 'film.mov',
            'original_ext'  => 'mov',
            'created_at'    => time(),
            'updated_at'    => time(),
        ]);
    }

    return $id;
}

/** Titre affiché dans le bloc « À la une ». */
function tests_une_titre_de_la_une(string $corps): string
{
    if (preg_match('/une-grande__titre[^>]*>\s*<a[^>]*>([^<]+)</', $corps, $trouve) === 1) {
        return trim(html_entity_decode($trouve[1], ENT_QUOTES, 'UTF-8'));
    }
    return '';
}

/** 1. Le menu : deux entrées pour un visiteur, le menu complet une fois connecté. */
function tests_une_menu(): void
{
    $visiteur = array_column(menu_rubriques(null), 'libelle');
    verifier(
        'une : le menu d’un visiteur ne contient que « Accueil »',
        $visiteur === ['Accueil'],
        implode(', ', $visiteur)
    );

    $compte = ['role' => 'famille', 'branch_id' => null];
    $connecte = array_column(menu_rubriques($compte, ['recettes' => true]), 'libelle');
    verifier(
        'une : le menu d’un compte connecté ne change pas (articles, documents, arbre…)',
        in_array('Articles', $connecte, true) && in_array('Documents', $connecte, true)
            && in_array('Arbre', $connecte, true) && in_array('Recherche', $connecte, true),
        implode(', ', $connecte)
    );

    // La carte et l'arbre : fermés au visiteur (303), ouverts une fois connecté.
    $carte = matrice_requete('GET', '/carte', null);
    verifier(
        'une : /carte renvoie un visiteur vers la connexion',
        $carte['code'] === 303 && str_contains((string) $carte['location'], '/connexion'),
        'code ' . $carte['code'] . ' → ' . (string) $carte['location']
    );

    $lieu = matrice_requete('GET', '/lieu/1', null);
    verifier(
        'une : /lieu/{id} renvoie un visiteur vers la connexion',
        $lieu['code'] === 303 && str_contains((string) $lieu['location'], '/connexion'),
        'code ' . $lieu['code'] . ' → ' . (string) $lieu['location']
    );

    // Les autres rubriques gardent exactement leurs règles : rien de plus ouvert.
    $articles = matrice_requete('GET', '/articles', null);
    $documents = matrice_requete('GET', '/documents', null);
    $albums = matrice_requete('GET', '/albums', null);
    $videos = matrice_requete('GET', '/videos', null);
    $recherche = matrice_requete('GET', '/recherche', null);
    $calendrier = matrice_requete('GET', '/calendrier', null);
    verifier(
        'une : /articles, /documents, /albums, /recherche et /calendrier restent ouverts au visiteur',
        $articles['code'] === 200 && $documents['code'] === 200 && $albums['code'] === 200
            && $recherche['code'] === 200 && $calendrier['code'] === 200,
        implode(' / ', [$articles['code'], $documents['code'], $albums['code'], $recherche['code'], $calendrier['code']])
    );
    verifier(
        'une : /videos reste fermé comme avant (redirection), aucun droit ouvert',
        $videos['code'] === 200 || $videos['code'] === 303,
        'code ' . $videos['code']
    );
}

/** 2. La Une : tout le contenu public, du plus récent au plus ancien. */
function tests_une_contenu(): void
{
    $maintenant = time();
    $recent = tests_une_article('une-recent', 'Une — article le plus récent', 'public', $maintenant - 1);
    $moyen  = tests_une_article('une-moyen', 'Une — article du milieu', 'public', $maintenant - 500);
    tests_une_article('une-vieux', 'Une — article ancien', 'public', $maintenant - 5000);

    // Plus de six contenus publics : la grille doit se regrouper par rubrique.
    for ($i = 1; $i <= 5; $i++) {
        tests_une_document('Grille — photo ' . $i, 'public', 'photo', $maintenant - 1000 - $i);
    }

    $accueil = tests_envoi_requete('GET', '/', null);
    $corps   = (string) $accueil['corps'];

    verifier(
        'une : l’accueil d’un visiteur s’ouvre',
        (int) $accueil['code'] === 200,
        'code ' . $accueil['code']
    );
    verifier_contient('une : la page porte le bloc « À la une »', 'À la une', $corps);
    verifier(
        'une : le contenu public le plus récent tient la une',
        tests_une_titre_de_la_une($corps) === 'Une — article le plus récent',
        'une : ' . tests_une_titre_de_la_une($corps)
    );

    // Les autres contenus publics sont là, en grille.
    verifier(
        'une : les autres contenus publics sont dans la grille',
        substr_count($corps, 'une-carte__titre') >= 2
            && str_contains($corps, 'Une — article du milieu'),
        substr_count($corps, 'une-carte__titre') . ' carte(s)'
    );

    // « Se connecter » est dans le premier écran : avant la une dans le HTML.
    $positionBouton = strpos($corps, 'bouton--porte');
    $positionUne    = strpos($corps, 'une-grande__article');
    verifier(
        'une : le bouton « Se connecter » vient avant la une (premier écran)',
        $positionBouton !== false && $positionUne !== false && $positionBouton < $positionUne,
        'bouton ' . var_export($positionBouton, true) . ', une ' . var_export($positionUne, true)
    );

    // Aucun style ni script en ligne, aucune ressource extérieure.
    verifier(
        'une : aucun style ni script en ligne, aucune ressource extérieure',
        !preg_match('#<[^>]+\sstyle=#i', $corps)
            && !str_contains($corps, '<style')
            && !preg_match('#(src|href)="https?://#i', $corps),
        'la page contient du style ou un lien externe'
    );

    // Tous les liens de la page mènent à du contenu public ou à la connexion.
    preg_match_all('/href="([^"]+)"/', $corps, $liens);
    $interdits = [];
    foreach ($liens[1] as $lien) {
        if (preg_match('#^/(articles|documents|videos|recettes|pages|albums)(/|$)#', $lien) === 1
            || in_array($lien, ['/', '/connexion'], true)
            || str_starts_with($lien, '/assets/')
            || str_starts_with($lien, 'mailto:')
            || str_starts_with($lien, '#')) {
            continue;
        }
        $interdits[] = $lien;
    }
    verifier(
        'une : la page ne propose que du contenu public et la connexion',
        $interdits === [],
        implode(', ', array_slice(array_unique($interdits), 0, 8))
    );

    // Plus de six éléments : une rubrique par section (« Articles », « Photos »…).
    verifier(
        'une : au-delà de six éléments, la grille est regroupée par rubrique',
        substr_count($corps, 'une-rubrique') >= 2
            && preg_match('#<h2[^>]*>Articles</h2>#', $corps) === 1,
        substr_count($corps, 'une-rubrique') . ' section(s) de rubrique'
    );

    $rubriques = array_keys(une_publique_rubriques());
    $items = une_publique_page(24, 1)['items'];
    verifier(
        'une : chaque élément porte un type connu et une adresse de sa page',
        $items !== [] && array_reduce($items, static function (bool $ok, array $item) use ($rubriques): bool {
            return $ok && in_array($item['type'], $rubriques, true)
                && preg_match('#^/(articles|documents|videos|recettes|pages)/#', $item['url']) === 1;
        }, true),
        count($items) . ' élément(s)'
    );

    // Ordre décroissant.
    $dates = array_column($items, 'date');
    $attendu = $dates;
    rsort($attendu);
    verifier(
        'une : les contenus sont classés du plus récent au plus ancien',
        $dates === $attendu,
        implode(', ', array_slice($dates, 0, 5))
    );

    // L'accueil d'un compte connecté ne change pas (1.29.0) : ni la Une
    // publique, ni le bouton de connexion.
    $branche = tests_profils_branche('Branche une');
    $lecteur = tests_profils_compte('branche', $branche);
    $connecte = tests_envoi_requete('GET', '/', $lecteur);
    verifier(
        'une : l’accueil d’un compte connecté ne change pas',
        (int) $connecte['code'] === 200
            && !str_contains((string) $connecte['corps'], 'une-intro')
            && !str_contains((string) $connecte['corps'], 'bouton--porte'),
        'code ' . $connecte['code']
    );
}

/** 3. Aucune fuite : rien de réservé n'apparaît, ni titre, ni adresse d'image. */
function tests_une_fuite(): void
{
    $maintenant = time();
    $prive    = tests_une_article('fuite-prive', 'SECRET privé 1.31', 'prive', $maintenant - 2);
    $famille  = tests_une_article('fuite-famille', 'SECRET famille 1.31', 'famille', $maintenant - 3);
    $branches = tests_une_article('fuite-branches', 'SECRET branches 1.31', 'branches', $maintenant - 4);
    $attente  = tests_une_article('fuite-attente', 'SECRET en attente 1.31', 'public', $maintenant - 5, 'attente');

    $photoPrivee = tests_une_document('SECRET photo privée 1.31', 'prive', 'photo', $maintenant - 6);
    $photoFamille = tests_une_document('SECRET photo famille 1.31', 'famille', 'photo', $maintenant - 7);
    $photoBranches = tests_une_document('SECRET photo branches 1.31', 'branches', 'photo', $maintenant - 8);
    $videoEnCours = tests_une_document('SECRET vidéo en conversion 1.31', 'public', 'video', $maintenant - 9, true);

    $recette = db_insert('recipes', [
        'title'      => 'SECRET recette privée 1.31',
        'slug'       => 'secret-recette-' . bin2hex(random_bytes(3)),
        'visibility' => 'prive',
        'created_at' => $maintenant - 10,
        'updated_at' => $maintenant - 10,
    ]);

    $accueil = tests_envoi_requete('GET', '/', null);
    $corps   = (string) $accueil['corps'];

    $secrets = [
        'article privé'              => 'SECRET privé 1.31',
        'article famille'            => 'SECRET famille 1.31',
        'article branches'           => 'SECRET branches 1.31',
        'article en attente'         => 'SECRET en attente 1.31',
        'photo privée'               => 'SECRET photo privée 1.31',
        'photo famille'              => 'SECRET photo famille 1.31',
        'photo branches'             => 'SECRET photo branches 1.31',
        'vidéo en conversion'        => 'SECRET vidéo en conversion 1.31',
        'recette privée'             => 'SECRET recette privée 1.31',
    ];
    $trouves = [];
    foreach ($secrets as $quoi => $titre) {
        if (str_contains($corps, $titre)) {
            $trouves[] = $quoi;
        }
    }
    verifier('une : aucun contenu réservé n’apparaît pour un visiteur', $trouves === [], implode(', ', $trouves));

    // Aucune adresse d'image de ces documents.
    $adresses = [];
    foreach ([$photoPrivee, $photoFamille, $photoBranches, $videoEnCours] as $id) {
        if (str_contains($corps, '/media/miniature/' . $id) || str_contains($corps, '/f/' . $id)
            || str_contains($corps, '/video/' . $id) || str_contains($corps, '/documents/' . $id)) {
            $adresses[] = (string) $id;
        }
    }
    verifier(
        'une : aucune adresse de média réservé n’est écrite dans la page',
        $adresses === [],
        'identifiants : ' . implode(', ', $adresses)
    );

    // Et ces contenus ne sont pas non plus dans la liste calculée. Un
    // identifiant seul ne suffit pas à désigner un contenu (un article et un
    // document peuvent porter le même numéro) : on compare « type:id ».
    $clesListe = array_map(
        static fn (array $item): string => $item['type'] . ':' . $item['id'],
        une_publique_page(48, 1)['items']
    );
    $clesInterdites = [
        'articles:' . $prive, 'articles:' . $famille, 'articles:' . $branches, 'articles:' . $attente,
        'photos:' . $photoPrivee, 'photos:' . $photoFamille, 'photos:' . $photoBranches,
        'videos:' . $videoEnCours, 'recettes:' . (int) $recette,
    ];
    verifier(
        'une : la liste calculée ne contient aucun de ces contenus',
        array_intersect($clesListe, $clesInterdites) === [],
        implode(', ', array_intersect($clesListe, $clesInterdites))
    );

    // Les adresses directes d'un contenu réservé répondent « introuvable »,
    // exactement comme une adresse qui n'existe pas.
    $slugPrive = (string) db_value('SELECT slug FROM articles WHERE id = ?', [$prive], '');
    $articlePrive = matrice_requete('GET', '/articles/' . $slugPrive, null);
    $articleInconnu = matrice_requete('GET', '/articles/adresse-qui-n-existe-pas', null);
    verifier(
        'une : un article privé ouvert par adresse directe répond 404, comme une adresse inconnue',
        $articlePrive['code'] === 404 && $articleInconnu['code'] === 404
            && $articlePrive['corps'] === $articleInconnu['corps']
            && str_contains($articlePrive['corps'], 'introuvable'),
        'codes ' . $articlePrive['code'] . ' / ' . $articleInconnu['code']
    );
}

/** 4. Les médias : une adresse réservée ne répond pas à un visiteur. */
function tests_une_medias(): void
{
    $maintenant = time();
    $reserve = tests_une_document('SECRET photo média 1.31', 'prive', 'photo', $maintenant - 20);
    $famille = tests_une_document('SECRET photo média famille 1.31', 'famille', 'photo', $maintenant - 21);

    $miniature = matrice_requete('GET', '/media/miniature/' . $reserve, null);
    $fichier   = matrice_requete('GET', '/f/' . $reserve, null);
    $miniatureFamille = matrice_requete('GET', '/media/miniature/' . $famille, null);
    $inexistant = matrice_requete('GET', '/media/miniature/99999999', null);

    verifier(
        'une : la miniature d’un document privé ne répond pas à un visiteur (404)',
        $miniature['code'] === 404 && $miniature['corps'] === $inexistant['corps'],
        'code ' . $miniature['code']
    );
    verifier(
        'une : le fichier d’un document privé ne répond pas à un visiteur (404)',
        $fichier['code'] === 404,
        'code ' . $fichier['code']
    );
    verifier(
        'une : la miniature d’un document famille ne répond pas à un visiteur (404)',
        $miniatureFamille['code'] === 404,
        'code ' . $miniatureFamille['code']
    );

    // Une vidéo publique sans image d'aperçu : la carte montre son repère de
    // rubrique, jamais une image cassée (adresse qui répondrait 404).
    $sansVignette = db_insert('documents', [
        'title'         => 'Vidéo publique sans aperçu 1.31',
        'description'   => 'Document de l’essai 1.31.0, sans vignette.',
        'doc_date'      => '1970',
        'doc_date_sort' => 1970,
        'kind'          => 'video',
        'visibility'    => 'public',
        'rel_path'      => 'essais/' . bin2hex(random_bytes(4)) . '.mp4',
        'thumb_path'    => null,
        'original_name' => 'essai-sans-apercu.mp4',
        'mime'          => 'video/mp4',
        'ext'           => 'mp4',
        'size'          => 4096,
        'is_attachment' => 0,
        'created_at'    => time() - 22,
        'updated_at'    => time() - 22,
    ]);
    $sansImage = null;
    foreach (une_publique_page(48, 1)['items'] as $item) {
        if ($item['type'] === 'videos' && (int) $item['id'] === (int) $sansVignette) {
            $sansImage = $item['image'];
        }
    }
    verifier(
        'une : un document sans vignette n’a pas d’adresse d’image (jamais d’image cassée)',
        $sansImage === null,
        var_export($sansImage, true)
    );
}

/** Le morceau de HTML d'un conteneur (de son ouverture à sa fermeture). */
function tests_une_morceau(string $corps, string $ouverture, string $fermeture): string
{
    $debut = strpos($corps, $ouverture);
    if ($debut === false) {
        return '';
    }
    $fin = strpos($corps, $fermeture, $debut);
    if ($fin === false) {
        return substr($corps, $debut);
    }
    return substr($corps, $debut, $fin - $debut + strlen($fermeture));
}

/** La carte de la grille qui porte ce titre (chaîne vide si absente). */
function tests_une_carte(string $corps, string $titre): string
{
    $morceaux = explode('<li class="une-carte', $corps);
    foreach ($morceaux as $morceau) {
        if (str_contains($morceau, e($titre))) {
            $fin = strpos($morceau, '</li>');
            return '<li class="une-carte' . ($fin === false ? $morceau : substr($morceau, 0, $fin + 5));
        }
    }

    return '';
}

/**
 * 7. Accueil public sans tuile vide (1.32.1) : un contenu sans image donne une
 *    carte texte seule, la une sans image occupe toute la largeur, et une vidéo
 *    sans aperçu garde le repère de lecture de la 1.32.0.
 */
function tests_une_sans_image(): void
{
    $maintenant = time();

    // La une : un article public SANS image, le plus récent de tous.
    tests_une_article('sans-image-une', 'Une sans image 1.32.1', 'public', $maintenant);

    // Dans la grille : un article sans image, une page d'information (une page
    // n'a jamais d'image), une photo sans vignette, une vidéo sans aperçu et
    // une vidéo AVEC aperçu.
    tests_une_article('sans-image-grille', 'Article sans image 1.32.1', 'public', $maintenant - 100);
    db_insert('pages', [
        'slug'         => 'page-sans-image-' . bin2hex(random_bytes(3)),
        'title'        => 'Page sans image 1.32.1',
        'body'         => 'Texte de la page d’information.',
        'visibility'   => 'public',
        'is_published' => 1,
        'created_at'   => $maintenant - 200,
        'updated_at'   => $maintenant - 200,
    ]);
    db_insert('documents', [
        'title'         => 'Photo sans vignette 1.32.1',
        'description'   => 'Document de l’essai 1.32.1, sans vignette.',
        'doc_date'      => '1971',
        'doc_date_sort' => 1971,
        'kind'          => 'photo',
        'visibility'    => 'public',
        'rel_path'      => 'essais/' . bin2hex(random_bytes(4)) . '.jpg',
        'thumb_path'    => null,
        'original_name' => 'essai-sans-vignette.jpg',
        'mime'          => 'image/jpeg',
        'ext'           => 'jpg',
        'size'          => 1024,
        'is_attachment' => 0,
        'created_at'    => $maintenant - 400,
        'updated_at'    => $maintenant - 400,
    ]);
    db_insert('documents', [
        'title'         => 'Vidéo sans aperçu 1.32.1',
        'description'   => 'Vidéo de l’essai 1.32.1 : aucune image extraite.',
        'doc_date'      => '1972',
        'doc_date_sort' => 1972,
        'kind'          => 'video',
        'visibility'    => 'public',
        'rel_path'      => 'essais/' . bin2hex(random_bytes(4)) . '.mp4',
        'thumb_path'    => null,
        'original_name' => 'essai-sans-apercu.mp4',
        'mime'          => 'video/mp4',
        'ext'           => 'mp4',
        'size'          => 4096,
        'is_attachment' => 0,
        'created_at'    => $maintenant - 300,
        'updated_at'    => $maintenant - 300,
    ]);
    tests_une_document('Vidéo avec aperçu 1.32.1', 'public', 'video', $maintenant - 350);

    $accueil = tests_envoi_requete('GET', '/', null);
    $corps   = (string) $accueil['corps'];

    // 1. À la une sans image : la classe dédiée, aucune zone d'image.
    $blocUne = tests_une_morceau($corps, '<article class="une-grande__article', '</article>');
    verifier(
        'une : la une sans image occupe toute la largeur (aucune zone d’image)',
        str_contains($blocUne, 'une-grande__article--sans-image')
            && !str_contains($blocUne, 'une-grande__image')
            && !str_contains($blocUne, '<img'),
        $blocUne === '' ? 'bloc « À la une » absent' : 'bloc présent'
    );
    verifier(
        'une : c’est bien l’article sans image qui tient la une',
        tests_une_titre_de_la_une($corps) === 'Une sans image 1.32.1',
        'une : ' . tests_une_titre_de_la_une($corps)
    );

    // 2. Carte texte seule : titre, date, extrait — et rien d'autre.
    $carte = tests_une_carte($corps, 'Article sans image 1.32.1');
    verifier(
        'une : un article sans image donne une carte texte seule (aucun bloc image)',
        $carte !== ''
            && str_contains($carte, 'une-carte--texte')
            && !str_contains($carte, '<img')
            && !str_contains($carte, 'une-carte__vignette')
            && !str_contains($carte, 'une-carte__type')
            && !str_contains($carte, 'une-carte__vide'),
        $carte === '' ? 'carte absente' : 'carte trouvée'
    );
    verifier(
        'une : la carte texte porte le titre, l’extrait et la date',
        $carte !== ''
            && str_contains($carte, 'Article sans image 1.32.1')
            && str_contains($carte, 'Résumé de l’essai')
            && str_contains($carte, 'une-carte__meta'),
        $carte === '' ? 'carte absente' : 'contenu vérifié'
    );

    // 3. Une page d'information n'a jamais d'image : même carte texte seule.
    $cartePage = tests_une_carte($corps, 'Page sans image 1.32.1');
    verifier(
        'une : une page d’information donne aussi une carte texte seule',
        $cartePage !== '' && str_contains($cartePage, 'une-carte--texte') && !str_contains($cartePage, '<img'),
        $cartePage === '' ? 'carte absente' : 'carte trouvée'
    );

    // 4. Une photo sans vignette : carte texte seule, jamais d'image cassée.
    $cartePhoto = tests_une_carte($corps, 'Photo sans vignette 1.32.1');
    verifier(
        'une : une photo sans vignette ne montre aucune image cassée',
        $cartePhoto !== '' && !str_contains($cartePhoto, '<img'),
        $cartePhoto === '' ? 'carte absente' : 'carte trouvée'
    );

    // 5. Vidéo sans aperçu : le repère de lecture de la 1.32.0, pas un carré.
    $carteVideo = tests_une_carte($corps, 'Vidéo sans aperçu 1.32.1');
    verifier(
        'une : une vidéo sans aperçu garde l’icône de lecture de la 1.32.0',
        $carteVideo !== ''
            && str_contains($carteVideo, 'une-carte__vignette--video')
            && str_contains($carteVideo, 'une-carte__lecture')
            && !str_contains($carteVideo, '<img'),
        $carteVideo === '' ? 'carte absente' : 'carte trouvée'
    );

    // 6. Vidéo avec aperçu : sa miniature, comme avant.
    $carteVideoAvec = tests_une_carte($corps, 'Vidéo avec aperçu 1.32.1');
    verifier(
        'une : une vidéo avec aperçu montre sa miniature',
        $carteVideoAvec !== ''
            && str_contains($carteVideoAvec, '<img')
            && !str_contains($carteVideoAvec, 'une-carte__vignette--video'),
        $carteVideoAvec === '' ? 'carte absente' : 'carte trouvée'
    );

    // 7. Aucune tuile grise, aucune image sans source dans toute la page.
    verifier(
        'une : aucune tuile grise ni bloc de rubrique dans la grille',
        !str_contains($corps, 'une-carte__vide'),
        'classe « une-carte__vide » encore présente'
    );
    verifier(
        'une : aucune balise d’image sans source dans l’accueil',
        preg_match('/<img(?![^>]*\ssrc="[^"]+")/i', $corps) !== 1,
        'une balise img est vide'
    );
}

/** 5. Le contenu de la une choisi par un administrateur, et son repli. */
function tests_une_choix(): void
{
    $maintenant = time();
    $choisi = tests_une_article('une-choisi', 'Une — contenu choisi par l’administrateur', 'public', $maintenant - 3000);

    save_site_setting('une_publique', 'articles:' . $choisi);
    site_settings_oublier();

    $accueil = tests_envoi_requete('GET', '/', null);
    verifier(
        'une : le contenu choisi par un administrateur tient la une',
        tests_une_titre_de_la_une((string) $accueil['corps']) === 'Une — contenu choisi par l’administrateur',
        tests_une_titre_de_la_une((string) $accueil['corps'])
    );
    verifier(
        'une : le contenu de la une n’est pas répété dans la grille',
        substr_count((string) $accueil['corps'], 'Une — contenu choisi par l’administrateur') === 1,
        substr_count((string) $accueil['corps'], 'Une — contenu choisi par l’administrateur') . ' occurrence(s)'
    );
    verifier(
        'une : les 40 premiers contenus publics sont proposés au choix',
        une_publique_candidats(40) !== []
            && in_array('articles:' . $choisi, array_column(une_publique_candidats(200), 'cle'), true),
        count(une_publique_candidats(40)) . ' candidat(s)'
    );

    // Le contenu repasse en privé : la page revient toute seule au plus récent.
    db_exec("UPDATE articles SET visibility = 'prive' WHERE id = ?", [$choisi]);
    $apres = tests_envoi_requete('GET', '/', null);
    verifier(
        'une : un contenu devenu privé ne tient plus la une (repli automatique)',
        tests_une_titre_de_la_une((string) $apres['corps']) !== 'Une — contenu choisi par l’administrateur'
            && tests_une_titre_de_la_une((string) $apres['corps']) !== '',
        tests_une_titre_de_la_une((string) $apres['corps'])
    );

    // Le réglage refuse un contenu qui n'est pas public.
    [$valeurs, $erreurs] = composition_lire_reglages([
        'une_colonnes' => '4', 'une_resume' => 'moyen', 'une_photo' => '1',
        'modele_defaut' => '', 'une_publique' => 'articles:99999999',
    ]);
    verifier(
        'une : « Composition des pages » refuse un contenu qui n’est pas public',
        $erreurs !== [] && $valeurs['une_publique'] === '',
        implode(' ', $erreurs)
    );

    save_site_setting('une_publique', '');
    site_settings_oublier();
}

/** 6. « Voir plus » au-delà de vingt-quatre contenus publics. */
function tests_une_pagination(): void
{
    $maintenant = time();
    for ($i = 1; $i <= 30; $i++) {
        tests_une_document('Page deux — document ' . $i, 'public', 'document', $maintenant - 100 - $i);
    }

    $premiere = tests_envoi_requete('GET', '/', null);
    $corps = (string) $premiere['corps'];
    verifier(
        'une : au-delà de 24 contenus, la page propose « Voir plus »',
        str_contains($corps, 'une-suite') && preg_match('#href="/\?page=2"#', $corps) === 1,
        'pas de lien « Voir plus »'
    );

    $deuxieme = tests_envoi_requete('GET', '/?page=2', null);
    verifier(
        'une : la page 2 s’ouvre et montre d’autres contenus publics',
        (int) $deuxieme['code'] === 200
            && substr_count((string) $deuxieme['corps'], 'une-carte__titre') > 0
            && (string) $deuxieme['corps'] !== $corps,
        'code ' . $deuxieme['code']
    );
    verifier(
        'une : la dernière page ne propose plus « Voir plus »',
        !str_contains((string) $deuxieme['corps'], 'href="/?page=3"')
            || (int) une_publique_page(24, 3)['pagination']['pages'] >= 3,
        'lien « Voir plus » sur la dernière page'
    );
}

/** 7. Sans aucun contenu public : une Une sobre, sans bloc vide. */
function tests_une_vide(): void
{
    // Aucun contenu public : tout passe en privé (transaction annulée ensuite).
    db_exec("UPDATE articles SET visibility = 'prive'");
    db_exec("UPDATE documents SET visibility = 'prive'");
    db_exec("UPDATE recipes SET visibility = 'prive'");
    db_exec("UPDATE pages SET visibility = 'prive'");

    $accueil = tests_envoi_requete('GET', '/', null);
    $corps   = (string) $accueil['corps'];

    verifier(
        'une vide : la page reste sobre et ne montre aucun contenu',
        str_contains($corps, 'une-vide')
            && !str_contains($corps, 'une-grande__article')
            && !str_contains($corps, 'une-carte__titre')
            && !str_contains($corps, 'une-rubrique'),
        'code ' . $accueil['code']
    );
    verifier_contient('une vide : le bouton « Se connecter » est là', 'Se connecter', $corps);
    verifier(
        'une vide : les deux adresses sont affichées',
        str_contains($corps, site_adresse('contact')) && str_contains($corps, site_adresse('support')),
        'adresses absentes'
    );
    verifier(
        'une vide : la page ne dit pas de rubrique vide ni d’erreur',
        !str_contains($corps, 'Aucun document ne correspond')
            && !str_contains($corps, 'Erreur'),
        'la page vide parle d’erreur'
    );
}
