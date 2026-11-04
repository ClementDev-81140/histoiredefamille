<?php
/**
 * Tests du « privé par défaut » (lot 1.24.0).
 *
 * Règle du site : **un contenu qu'on vient de créer n'est visible de personne
 * tant qu'on n'a pas choisi qui peut le voir**. Ce fichier le vérifie de deux
 * façons :
 *
 *   1. en lisant les formulaires eux-mêmes : chaque écran de création doit
 *      présenter « Privé » coché, et chaque contrôleur doit ranger la valeur
 *      reçue par `sanitize_visibility()` (une valeur absente ou inconnue
 *      devient « prive », jamais « public ») ;
 *   2. en créant réellement des contenus, sans envoyer de visibilité : ils
 *      doivent tous arriver en privé dans la base.
 *
 * Le premier essai attrape un formulaire oublié (il échoue même si le
 * contrôleur est juste) ; le second attrape une valeur par défaut glissée dans
 * le contrôleur. Les deux ensemble ferment la porte.
 */

declare(strict_types=1);

/** Tous les écrans qui proposent un choix de visibilité. */
function defauts_formulaires(): array
{
    return [
        'app/views/admin/article_form.php',
        'app/views/admin/page_form.php',
        'app/views/admin/document_form.php',
        'app/views/admin/album_form.php',
        'app/views/admin/recette_form.php',
        'app/views/admin/video_form.php',
        'app/views/admin/personne_form.php',
        'app/views/admin/importer.php',
        'app/views/admin/envoi_lot.php',
        'app/views/propositions/form.php',
    ];
}

/** Tous les contrôleurs qui enregistrent une visibilité. */
function defauts_controleurs(): array
{
    return [
        'app/controllers/admin_albums.php',
        'app/controllers/admin_documents.php',
        'app/controllers/admin_envoi.php',
        'app/controllers/admin_import.php',
        'app/controllers/admin_pages.php',
        'app/controllers/admin_persons.php',
        'app/controllers/admin_recettes.php',
        'app/controllers/admin_videos.php',
        'app/controllers/admin_a_valider.php',
    ];
}

/** Toute la famille « défauts » : privé par défaut, partout. */
function tests_defauts(): void
{
    echo "Privé par défaut — formulaires et enregistrements\n";

    tests_defauts_formulaires();
    tests_defauts_controleurs();
    tests_defauts_enregistrement();
}

/** 1. Chaque formulaire de création propose « Privé » en premier. */
function tests_defauts_formulaires(): void
{
    $fautifs = [];
    foreach (defauts_formulaires() as $fichier) {
        $code = (string) @file_get_contents(dirname(__DIR__, 2) . '/' . $fichier);
        if ($code === '') {
            $fautifs[] = $fichier . ' (absent)';
            continue;
        }

        // La valeur par défaut du champ. Trois écritures possibles selon le
        // formulaire : gabarit à $v(), gabarit à $val(), ou bouton coché à la
        // main. Aucune ne doit proposer autre chose que « privé ».
        $defautPrive = str_contains($code, "visibility', 'prive'")
            || str_contains($code, "checked(\$value === 'prive')")
            || str_contains($code, "checked(\$valeur === 'prive')");
        if (!$defautPrive) {
            $fautifs[] = $fichier . ' (pas de défaut « privé »)';
        }

        // Aucun formulaire ne doit cocher un autre niveau par défaut.
        foreach (['public', 'famille', 'branches'] as $niveau) {
            if (str_contains($code, "visibility', '" . $niveau . "'")) {
                $fautifs[] = $fichier . ' (défaut « ' . $niveau . ' »)';
            }
        }
    }

    verifier(
        'défauts : chaque formulaire de création ouvre sur « Privé »',
        $fautifs === [],
        implode(', ', $fautifs)
    );
}

/** 2. Chaque contrôleur range la visibilité reçue sans jamais l'inventer. */
function tests_defauts_controleurs(): void
{
    $fautifs = [];
    foreach (defauts_controleurs() as $fichier) {
        $code  = (string) @file_get_contents(dirname(__DIR__, 2) . '/' . $fichier);
        $bruts = substr_count($code, "post('visibility')") - substr_count($code, "sanitize_visibility(post('visibility'))");
        if ($bruts > 0) {
            $fautifs[] = $fichier . ' (' . $bruts . ' valeur(s) brute(s))';
        }
    }

    verifier(
        'défauts : la visibilité reçue passe toujours par sanitize_visibility()',
        $fautifs === [],
        implode(', ', $fautifs)
    );

    // Une valeur absente ou inconnue devient « prive », jamais « public ».
    verifier(
        'défauts : une visibilité absente ou inventée devient privée',
        sanitize_visibility('') === 'prive'
            && sanitize_visibility(null) === 'prive'
            && sanitize_visibility('tout-le-monde') === 'prive'
            && sanitize_visibility('public') === 'public'
    );
}

/** 3. En vrai : créer sans rien choisir donne du privé. */
function tests_defauts_enregistrement(): void
{
    $hachage = (string) db_value('SELECT password_hash FROM accounts LIMIT 1', [], '');
    if ($hachage === '') {
        $hachage = password_make(bin2hex(random_bytes(8)));
    }

    $suffixe = bin2hex(random_bytes(3));
    $admin = db_insert('accounts', [
        'username'      => 'defauts.admin.' . $suffixe,
        'display_name'  => 'Défauts (essai)',
        'password_hash' => $hachage,
        'role'          => 'admin',
        'account_type'  => 'admin',
        'first_name'    => '',
        'last_name'     => '',
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);

    $deja = $_SESSION;

    try {
        $_SESSION = ['account_id' => $admin, '_csrf' => csrf_token()];

        $avantArticles = (int) db_value('SELECT COUNT(*) FROM articles', [], 0);
        $avantPages    = (int) db_value('SELECT COUNT(*) FROM pages', [], 0);

        // Aucun champ « visibility » dans les données envoyées : c'est le cas
        // d'un formulaire bricolé, ou d'un envoi sans JavaScript.
        tests_envoi_requete('POST', '/admin/articles/nouveau', $admin, [
            '_csrf'    => csrf_token(),
            'title'    => 'Article sans visibilité ' . $suffixe,
            'slug'     => 'article-sans-visibilite-' . $suffixe,
            'summary'  => '',
            'body'     => 'Texte.',
            'status'   => 'publie',
            'notify'   => '0',
        ]);
        $avant = db_one('SELECT visibility FROM articles ORDER BY id DESC LIMIT 1');
        verifier(
            'défauts : un article créé sans visibilité est privé',
            (int) db_value('SELECT COUNT(*) FROM articles', [], 0) === $avantArticles + 1
                && $avant !== null
                && (string) $avant['visibility'] === 'prive',
            'visibilité enregistrée : « ' . (string) ($avant['visibility'] ?? '') . ' »'
        );

        tests_envoi_requete('POST', '/admin/pages/nouvelle', $admin, [
            '_csrf'  => csrf_token(),
            'title'  => 'Page sans visibilité ' . $suffixe,
            'slug'   => 'page-sans-visibilite-' . $suffixe,
            'body'   => 'Texte.',
            'publication' => '1',
        ]);
        $page = db_one('SELECT visibility FROM pages ORDER BY id DESC LIMIT 1');
        verifier(
            'défauts : une page créée sans visibilité est privée',
            (int) db_value('SELECT COUNT(*) FROM pages', [], 0) === $avantPages + 1
                && $page !== null
                && (string) $page['visibility'] === 'prive',
            'visibilité enregistrée : « ' . (string) ($page['visibility'] ?? '') . ' »'
        );

        // Une visibilité inventée ne devient pas publique non plus.
        tests_envoi_requete('POST', '/admin/articles/nouveau', $admin, [
            '_csrf'      => csrf_token(),
            'title'      => 'Article visibilité inventée ' . $suffixe,
            'slug'       => 'article-visibilite-inventee-' . $suffixe,
            'summary'    => '',
            'body'       => 'Texte.',
            'status'     => 'publie',
            'notify'     => '0',
            'visibility' => 'tout-le-monde',
        ]);
        $inventee = db_one('SELECT visibility FROM articles ORDER BY id DESC LIMIT 1');
        verifier(
            'défauts : une visibilité inventée retombe sur « privé »',
            $inventee !== null && (string) $inventee['visibility'] === 'prive',
            'visibilité enregistrée : « ' . (string) ($inventee['visibility'] ?? '') . ' »'
        );
    } finally {
        $_SESSION = $deja;
    }
}
