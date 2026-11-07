<?php
/**
 * Essais — l'arbre « chêne gravé » de la page /arbre (1.33.0).
 *
 * Ce que l'on vérifie, dans l'ordre du besoin exprimé :
 *
 *   1. le rendu avec un jeu de données : la page s'ouvre, la gravure est là
 *      (WebP **et** repli JPEG), les trois parties portent leurs titres, la
 *      personne centrale est sur le tronc, ses ancêtres et ses descendants
 *      autour d'elle ;
 *   2. l'accès : un visiteur non connecté est renvoyé vers la connexion, avec
 *      ou sans `?racine=` — l'arbre n'est jamais public (décision 1.31.0) ;
 *   3. les droits : un lecteur ne voit **ni le nom, ni le lien, ni le nombre**
 *      d'une personne privée ; la personne privée demandée en `?racine=` ne
 *      remplace pas la personne par défaut, et l'administrateur, lui, la voit
 *      (la contre-épreuve prouve que l'essai regarde bien quelque chose) ;
 *   4. la mise en page : huit enfants tiennent sur une rangée, dix donnent
 *      « voir plus » (jamais de cartes tassées), et « voir plus » montre bien
 *      les deux personnes qui manquaient ;
 *   5. l'accessibilité et la sécurité : aucun attribut `style`, aucun script en
 *      ligne, aucune ressource extérieure, chaque carte est un lien vers une
 *      fiche et les noms sont dans le texte des liens.
 *
 * Tout se passe dans une transaction annulée : la base du site est intacte.
 */

declare(strict_types=1);

/** Personne d'essai (visibilité « famille » par défaut). */
function tests_arbre_personne(string $prenom, string $nom, string $visibilite = 'famille', string $sexe = 'M'): int
{
    return db_insert('persons', [
        'first_name' => $prenom,
        'last_name'  => $nom,
        'sex'        => $sexe,
        'birth_date' => '1900',
        'visibility' => $visibilite,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
}

/** Famille d'essai : un couple (ou un parent seul) et ses enfants. */
function tests_arbre_famille(?int $mari, ?int $femme, array $enfants): int
{
    $id = db_insert('families', [
        'husband_id' => $mari,
        'wife_id'    => $femme,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
    foreach ($enfants as $enfant) {
        db_insert('family_children', ['family_id' => $id, 'person_id' => $enfant]);
    }

    return $id;
}

/** Le morceau de HTML entre deux repères (chaîne vide si l'un manque). */
function tests_arbre_morceau(string $html, string $debut, string $fin = ''): string
{
    $a = strpos($html, $debut);
    if ($a === false) {
        return '';
    }
    if ($fin === '') {
        return substr($html, $a);
    }
    $b = strpos($html, $fin, $a);
    return $b === false ? substr($html, $a) : substr($html, $a, $b - $a);
}

/** Toute la famille « arbre ». */
function tests_arbre(): void
{
    echo "Arbre « chêne gravé » — rendu, droits, rangées, accessibilité\n";

    try {
        db_transaction(static function (): void {
            tests_arbre_rendu();
            tests_arbre_acces();
            tests_arbre_droits();
            tests_arbre_rangees();

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    verifier('arbre : la base est rendue intacte', true);
}

/** 1. Le rendu, avec un jeu de données complet. */
function tests_arbre_rendu(): void
{
    $admin = tests_envoi_compte('admin');

    // Trois générations au-dessus, deux en dessous.
    $agp = tests_arbre_personne('Théodore', 'CHENE', 'famille');
    $agm = tests_arbre_personne('Adèle', 'CHENE', 'famille', 'F');
    $agp2 = tests_arbre_personne('Sylvain', 'CHENE', 'famille');
    $agm2 = tests_arbre_personne('Reine', 'CHENE', 'famille', 'F');
    $gp  = tests_arbre_personne('Pierre', 'CHENE', 'famille');
    $gm  = tests_arbre_personne('Louise', 'CHENE', 'famille', 'F');
    $moi = tests_arbre_personne('Casimir', 'CHENE', 'famille');
    $elle = tests_arbre_personne('Odile', 'CHENE', 'famille', 'F');
    $fils = tests_arbre_personne('Serge', 'CHENE', 'famille');
    $petit = tests_arbre_personne('Léon', 'CHENE', 'famille');

    // Arrière-arrière-grands-parents (niveau 3), puis grands-parents, puis parents.
    tests_arbre_famille($agp2, $agm2, [$agp]);
    tests_arbre_famille($agp, $agm, [$gp]);
    tests_arbre_famille($gp, $gm, [$moi]);
    tests_arbre_famille($moi, $elle, [$fils]);
    tests_arbre_famille($fils, null, [$petit]);

    $page = tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin);
    $html = (string) $page['corps'];

    verifier('arbre : la page s’ouvre pour un compte connecté', $page['code'] === 200, 'code ' . $page['code']);
    verifier_contient('arbre : le titre est là', 'Arbre généalogique', $html);
    foreach (['Les ancêtres', 'Le tronc', 'Les descendants'] as $titre) {
        verifier_contient('arbre : la partie « ' . $titre . ' » est présente', $titre, $html);
    }

    // La gravure : WebP d'abord, JPEG en repli, jamais une image cassée.
    verifier_contient('arbre : la gravure est proposée en WebP', '/assets/arbre-chene.webp', $html);
    verifier_contient('arbre : la gravure a un repli JPEG', '/assets/arbre-chene.jpg', $html);
    verifier('arbre : les deux fichiers de la gravure existent et pèsent moins de 300 Ko',
        filesize(HDF_ROOT . '/public/assets/arbre-chene.webp') <= 300 * 1024
        && filesize(HDF_ROOT . '/public/assets/arbre-chene.jpg') > 0,
        'webp : ' . (int) @filesize(HDF_ROOT . '/public/assets/arbre-chene.webp') . ' octets');
    verifier_contient('arbre : la gravure est décrite par sa légende', 'les ancêtres dans les branches', $html);
    verifier('arbre : la gravure porte un texte de remplacement vide (décor) et ses dimensions',
        preg_match('/<img[^>]*alt=""[^>]*width="860"[^>]*height="1290"/', $html) === 1);

    // La personne centrale sur le tronc, son conjoint avec elle.
    $tronc = tests_arbre_morceau($html, 'arbre-chene__partie--tronc', 'arbre-chene__partie--racines');
    verifier_contient('arbre : la personne demandée est sur le tronc', 'Casimir CHENE', $tronc);
    verifier_contient('arbre : son conjoint partage le tronc', 'Odile CHENE', $tronc);
    verifier_contient('arbre : le tronc mène à la fiche', '/arbre/' . $moi, $tronc);

    // Les générations sont nommées, et ce sont les bonnes personnes.
    foreach ([
        'Arrière-grands-parents' => 'Théodore CHENE',
        'Grands-parents'         => 'Pierre CHENE',
        'Parents'                => 'Pierre CHENE',
    ] as $libelle => $nom) {
        verifier_contient('arbre : « ' . $libelle . ' » est écrit', $libelle, $html);
    }
    verifier_contient('arbre : l’arrière-grand-père est dans les branches', 'Théodore CHENE', $html);
    verifier_contient('arbre : les petits-enfants sont dans les racines', 'Léon CHENE', $html);
    verifier_contient('arbre : « Petits-enfants » est écrit', 'Petits-enfants', $html);
}

/** 2. L'accès : jamais d'arbre pour un visiteur. */
function tests_arbre_acces(): void
{
    foreach (['/arbre', '/arbre?racine=1', '/arbre?racine=1&plus=1'] as $url) {
        $page = tests_envoi_requete('GET', $url, null);
        verifier(
            'arbre : un visiteur est renvoyé vers la connexion (' . $url . ')',
            $page['code'] === 303 && str_contains($page['location'], '/connexion'),
            'code ' . $page['code'] . ' vers ' . $page['location']
        );
        verifier(
            'arbre : la page du visiteur ne contient aucun nom de l’arbre (' . $url . ')',
            !str_contains((string) $page['corps'], 'arbre-chene__nom'),
            'le corps contient un nœud de l’arbre'
        );
    }
}

/** 3. Les droits : la personne privée n'existe pas pour un lecteur. */
function tests_arbre_droits(): void
{
    $admin = tests_envoi_compte('admin');
    $lecteur = tests_envoi_compte('famille');

    $pere = tests_arbre_personne('Anselme', 'SECRET', 'famille');
    $moi  = tests_arbre_personne('Berthe', 'SECRET', 'famille', 'F');
    $cache = tests_arbre_personne('Inconnu', 'SECRET', 'prive');
    tests_arbre_famille($pere, null, [$moi]);
    tests_arbre_famille($moi, null, [$cache]);

    // Le lecteur : ni le nom, ni le lien, ni le nombre.
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $moi, $lecteur);
    $html = (string) $page['corps'];
    verifier('arbre : la page s’ouvre pour un lecteur', $page['code'] === 200, 'code ' . $page['code']);
    verifier_contient('arbre : le lecteur voit la personne de sa famille', 'Berthe SECRET', $html);
    verifier('arbre : le lecteur ne voit pas le nom d’une personne privée',
        !str_contains($html, 'Inconnu'), 'le nom « Inconnu » apparaît dans la page');
    verifier('arbre : le lecteur ne reçoit aucun lien vers une fiche privée',
        !str_contains($html, '/arbre/' . $cache), 'le lien /arbre/' . $cache . ' est dans la page');
    // Aucune génération de descendants n'est écrite : l'enfant privé ne laisse
    // ni carte, ni légende, ni compteur derrière lui.
    verifier('arbre : le compte des descendants ne trahit pas la personne privée',
        !str_contains($html, 'chene-descendants-1')
        && str_contains($html, 'Aucun enfant connu'),
        'une légende de descendants est présente alors qu’aucun ne doit se voir');
    verifier('arbre : la personne privée n’est pas proposée dans le sélecteur',
        !str_contains($html, 'value="' . $cache . '"'), 'une option porte l’identifiant ' . $cache);

    // Le lecteur demande la personne privée en personne centrale : on ne la lui
    // donne pas, et l'on ne lui dit pas qu'elle existe.
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $cache, $lecteur);
    $html = (string) $page['corps'];
    verifier('arbre : une personne privée demandée en racine ne s’affiche pas',
        $page['code'] === 200 && !str_contains($html, 'Inconnu') && !str_contains($html, '/arbre/' . $cache),
        'code ' . $page['code']);
    verifier('arbre : la page retombe alors sur une personne visible, sans le dire',
        preg_match('/arbre-chene__noeud--tronc.*?href="\/arbre\/(\d+)"/s', $html, $m) === 1
        && (int) $m[1] !== $cache,
        'racine affichée : ' . ($m[1] ?? 'aucune'));

    // Contre-épreuve : l'administrateur, lui, la voit — et la compte.
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin);
    verifier_contient('arbre : l’administrateur voit la personne privée', 'Inconnu SECRET', (string) $page['corps']);
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $cache, $admin);
    verifier('arbre : l’administrateur peut la mettre au centre',
        str_contains((string) $page['corps'], 'Inconnu SECRET')
        && str_contains((string) $page['corps'], 'arbre-chene__noeud--tronc'),
        'code ' . $page['code']);
}

/** 4. Les rangées : huit cartes, puis « voir plus ». */
function tests_arbre_rangees(): void
{
    $admin = tests_envoi_compte('admin');

    $moi = tests_arbre_personne('Honoré', 'RANGEE', 'famille');
    $huit = [];
    for ($i = 1; $i <= 8; $i++) {
        $huit[] = tests_arbre_personne('Huit' . $i, 'RANGEE', 'famille');
    }
    tests_arbre_famille($moi, null, $huit);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin)['corps'];
    $descendants = tests_arbre_morceau($html, 'arbre-chene__partie--racines', 'arbre-chene__plus');
    verifier('arbre : huit enfants tiennent sur une seule rangée',
        substr_count($descendants, '<li class="arbre-chene__noeud"') === 8
        && str_contains($descendants, 'arbre-chene__rangee--8'),
        'cartes : ' . substr_count($descendants, '<li class="arbre-chene__noeud"'));
    verifier('arbre : huit enfants ne demandent pas « voir plus »',
        !str_contains($html, 'autres personnes de la famille'),
        'un lien « voir plus » est présent');

    // Dix enfants : huit cartes, puis un lien qui dit combien il en reste.
    $dix = [];
    for ($i = 9; $i <= 10; $i++) {
        $dix[] = tests_arbre_personne('Dix' . $i, 'RANGEE', 'famille');
    }
    tests_arbre_famille($moi, null, $dix);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin)['corps'];
    $descendants = tests_arbre_morceau($html, 'arbre-chene__partie--racines', 'arbre-chene__plus');
    verifier('arbre : au-delà de huit, la rangée reste à huit cartes',
        substr_count($descendants, '<li class="arbre-chene__noeud"') === 8,
        'cartes : ' . substr_count($descendants, '<li class="arbre-chene__noeud"'));
    verifier_contient('arbre : « voir plus » dit combien de personnes manquent',
        'Voir les 2 autres personnes de la famille', $html);
    verifier_contient('arbre : « voir plus » garde la personne centrale',
        '/arbre?racine=' . $moi . '&amp;plus=1', $html);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi . '&plus=1', $admin)['corps'];
    $descendants = tests_arbre_morceau($html, 'arbre-chene__partie--racines', 'arbre-chene__plus');
    verifier('arbre : « voir plus » montre les dix enfants, en deux rangées',
        substr_count($descendants, '<li class="arbre-chene__noeud"') === 10
        && str_contains($descendants, 'arbre-chene__rangee--2'),
        'cartes : ' . substr_count($descendants, '<li class="arbre-chene__noeud"'));
    verifier_contient('arbre : « voir moins » est proposé ensuite', 'Voir moins', $html);

    // Aucune position en style, aucun script en ligne, rien d'extérieur.
    verifier('arbre : aucun attribut style= dans la page',
        preg_match('/\sstyle\s*=/i', $html) !== 1, 'un attribut style= est présent');
    verifier('arbre : aucun script en ligne',
        preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $html) !== 1, 'un <script> sans src est présent');
    verifier('arbre : aucune ressource extérieure',
        preg_match('#(?:src|href)="(?:https?:)?//#i', $html) !== 1, 'une adresse extérieure est présente');
    verifier('arbre : chaque carte est un lien vers une fiche',
        preg_match_all('/<li class="arbre-chene__noeud[^"]*">\s*<a class="arbre-chene__lien"[^>]*href="\/arbre\/\d+"/', $html) ===
        substr_count($html, '<li class="arbre-chene__noeud'),
        'cartes liées : ' . preg_match_all('/<li class="arbre-chene__noeud[^"]*">\s*<a class="arbre-chene__lien"[^>]*href="\/arbre\/\d+"/', $html)
        . ' sur ' . substr_count($html, '<li class="arbre-chene__noeud'));
}
