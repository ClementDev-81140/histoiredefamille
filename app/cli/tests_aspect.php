<?php
/**
 * Tests de la 1.32.0 — aspect « photo ancienne » et articles sans photo.
 *
 * Deux familles :
 *   « aspect »      le filtre sépia (réglage global, choix par photo, classes
 *                   CSS, aperçu de la page d'apparence, original intact) ;
 *   « sans_photo »  un article sans photo n'affiche ni carré, ni lettre, ni
 *                   cadre vide — et « Grande photo » retombe sur « Journal ».
 *
 * La famille « aspect » range ses réglages et remet tout en place à la fin.
 */

declare(strict_types=1);

/** Une carte d'article rendue par le vrai gabarit. */
function tests_aspect_carte(array $article, bool $grand = false): string
{
    ob_start();
    require dirname(__DIR__) . '/views/partials/carte_article.php';
    return (string) ob_get_clean();
}

// ---------------------------------------------------------------------------
// Aspect « photo ancienne »
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'aspect') {
    echo "Aspect « photo ancienne »\n";

    $reglageAvant = site_setting('apparence_aspect_photo');
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');

    try {
        // 1. Valeur par défaut : aucun filtre.
        db_exec("DELETE FROM settings WHERE key = 'apparence_aspect_photo'");
        site_settings_oublier();
        apparence_brut(true);
        verifier(
            'aspect : « Aucun » est le réglage par défaut (photos en couleurs)',
            apparence_aspect_photo() === 'aucun' && aspect_photo_classe(null) === ''
        );

        // 2. Les trois choix existent dans la page d'apparence.
        verifier(
            'aspect : les trois aspects sont proposés (Aucun, Doux, Marqué)',
            array_keys(APPARENCE_ASPECTS) === ['aucun', 'doux', 'marque']
        );

        // 3. Réglage global « doux » : la classe arrive sur les images.
        save_site_setting('apparence_aspect_photo', 'doux');
        site_settings_oublier();
        apparence_brut(true);
        verifier(
            'aspect : le réglage « Doux » pose la classe CSS sur les photos',
            apparence_aspect_photo() === 'doux'
                && aspect_photo_classe(null) === 'aspect-photo aspect-photo--doux'
        );

        // 4. Choix par document : suivre, forcer, désactiver.
        verifier(
            'aspect : une photo peut suivre, forcer ou désactiver le réglage',
            aspect_photo_classe(['vintage_aspect' => 'suivre']) === 'aspect-photo aspect-photo--doux'
                && aspect_photo_classe(['vintage_aspect' => 'desactiver']) === ''
                && aspect_photo_classe(['vintage_aspect' => 'n’importe quoi']) === 'aspect-photo aspect-photo--doux'
        );

        save_site_setting('apparence_aspect_photo', 'marque');
        site_settings_oublier();
        apparence_brut(true);
        verifier(
            'aspect : « Marqué » donne le filtre le plus appuyé, et « désactiver » le garde en couleurs',
            aspect_photo_classe(null) === 'aspect-photo aspect-photo--marque'
                && aspect_photo_classe(['vintage_aspect' => 'desactiver']) === ''
        );

        save_site_setting('apparence_aspect_photo', 'aucun');
        site_settings_oublier();
        apparence_brut(true);
        verifier(
            'aspect : « forcer » applique un filtre même quand le site est en « Aucun »',
            aspect_photo_classe(['vintage_aspect' => 'forcer']) === 'aspect-photo aspect-photo--doux'
        );

        // 5. Le filtre est une classe CSS, jamais un attribut style.
        verifier(
            'aspect : les deux filtres sont des classes CSS (sépia + contraste)',
            str_contains($css, '.aspect-photo--doux {') && str_contains($css, 'sepia(0.35)')
                && str_contains($css, '.aspect-photo--marque {') && str_contains($css, 'sepia(0.68)')
        );
        verifier(
            'aspect : l’impression ne garde aucun filtre (l’original reste en couleurs)',
            (bool) preg_match('/@media print.*\.aspect-photo--marque \{\s*filter: none !important;/s', $css)
        );

        // 6. La page d'apparence : trois boutons radio, un aperçu, aucun style en ligne.
        $adminId = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);
        $_SESSION['account_id'] = $adminId;
        $page = matrice_requete('GET', '/admin/apparence', $adminId);
        verifier(
            'aspect : l’apparence propose les trois aspects et un aperçu vivant',
            $page['code'] === 200
                && substr_count($page['corps'], 'name="aspect_photo"') === 3
                && str_contains($page['corps'], 'id="apercu-aspect"')
                && str_contains($page['corps'], 'data-apercu-aspect-image')
        );
        verifier(
            'aspect : la page d’apparence n’utilise ni style ni script en ligne',
            !str_contains($page['corps'], ' style=') && !str_contains($page['corps'], '<script>')
        );

        // 7. Une valeur inconnue est refusée, pas enregistrée en silence.
        [$valeurs, $erreurs] = apparence_lire_saisie(
            ['aspect_photo' => 'sepia-max', 'numero_mode' => 'auto'] + array_fill_keys(array_keys(APPARENCE_LONGUEURS), ''),
            (string) site_setting('name'),
            (string) site_setting('devise'),
            (string) site_setting('fondee')
        );
        verifier(
            'aspect : une valeur inconnue est refusée avec un message clair',
            $erreurs !== [] && ($valeurs['aspect_photo'] ?? '') === 'aucun'
        );

        // 8. L'image d'un article porte la classe, l'original et son téléchargement restent intacts.
        $maintenant = time();
        $photoId = db_insert('documents', [
            'title' => 'Photo aspect essai', 'kind' => 'photo', 'visibility' => 'public',
            'rel_path' => 'essai/aspect.jpg', 'thumb_path' => 'essai/aspect-thumb.jpg',
            'original_name' => 'aspect.jpg', 'mime' => 'image/jpeg', 'ext' => 'jpg',
            'size' => 10, 'vintage_aspect' => 'desactiver',
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
        $image = article_image(['image_id' => $photoId]);
        verifier(
            'aspect : « désactiver » sur une photo la laisse en couleurs, même sur l’article',
            $image !== null && ($image['aspect'] ?? 'x') === ''
                && $image['full'] === '/f/' . $photoId
        );
        db_exec("UPDATE documents SET vintage_aspect = 'suivre' WHERE id = ?", [$photoId]);
        $imageSuivie = article_image(['image_id' => $photoId]);
        verifier(
            'aspect : une photo qui suit le site prend la classe du réglage global',
            ($imageSuivie['aspect'] ?? '') === '' // le site est en « Aucun » ici
        );
        db_exec('DELETE FROM documents WHERE id = ?', [$photoId]);
    } finally {
        save_site_setting('apparence_aspect_photo', $reglageAvant);
        site_settings_oublier();
        apparence_brut(true);
    }
}

// ---------------------------------------------------------------------------
// Articles sans photo : aucun carré, aucune lettre, aucun cadre vide
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'sans_photo') {
    echo "Articles sans photo\n";

    $articleSansPhoto = [
        'id' => 0, 'slug' => 'sans-photo-essai', 'title' => 'Souvenirs sans photo',
        'summary' => '', 'body' => 'Un texte sans image, comme un article de journal.',
        'category' => 'Chronique', 'visibility' => 'public', 'published_at' => time(),
        'image_id' => null, 'author' => '', 'status' => 'publie',
    ];

    $carteSimple = tests_aspect_carte($articleSansPhoto);
    verifier(
        'sans photo : la carte d’article ne contient ni image, ni plaque, ni lettre',
        !str_contains($carteSimple, 'article-carte__image')
            && !str_contains($carteSimple, 'article-carte__plaque')
            && !str_contains($carteSimple, 'plaque__lettre')
    );
    verifier(
        'sans photo : la carte est marquée « sans-image » pour que la mise en page se réorganise',
        str_contains($carteSimple, 'article-carte--sans-image')
    );

    $carteUne = tests_aspect_carte($articleSansPhoto, true);
    verifier(
        'sans photo : l’article à la une sans photo n’affiche aucun cadre vide',
        !str_contains($carteUne, 'article-carte__image')
            && !str_contains($carteUne, 'plaque')
            && str_contains($carteUne, 'article-carte--une article-carte--sans-image')
    );

    // Le carré, la lettre et la plaque ont aussi disparu de la feuille de style.
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');
    verifier(
        'sans photo : les styles de la plaque de repli ont été retirés (plus aucun carré)',
        !str_contains($css, 'article-carte__plaque') && !str_contains($css, 'plaque__lettre')
    );
    verifier(
        'sans photo : la une sans image repasse sur une seule colonne',
        str_contains($css, '.article-carte--une.article-carte--sans-image')
    );

    // « Grande photo » sans photo retombe sur « Journal » (aucun cadre vide).
    $article = $articleSansPhoto;
    $article['modele'] = 'photo';
    $vue = '';
    $image = null;
    $branches = [];
    $branchesNoms = [];
    $others = [];
    $auteur = '';
    $millesime = (int) date('Y');
    $decennie = 0;
    ob_start();
    require dirname(__DIR__) . '/views/public/article.php';
    $vue = (string) ob_get_clean();
    verifier(
        'sans photo : « Grande photo » sans photo retombe sur « Journal », sans image',
        !str_contains($vue, 'article--modele-photo')
            && !str_contains($vue, 'article__image')
            && str_contains($vue, 'article--presse')
    );

    // Les pages réelles ne contiennent plus aucune plaque.
    $adminId = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);
    $_SESSION['account_id'] = $adminId;
    foreach (['/', '/articles'] as $url) {
        $page = matrice_requete('GET', $url, $adminId);
        verifier(
            'sans photo : la page ' . $url . ' ne contient aucune plaque de repli',
            $page['code'] === 200 && !str_contains($page['corps'], 'plaque')
        );
    }
}
