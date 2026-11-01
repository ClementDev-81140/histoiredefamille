<?php
/**
 * Petits tests automatiques, sans dépendance ni installation.
 *
 * Utilisation :
 *   php app/cli/tests.php            (tout)
 *   php app/cli/tests.php tchat      (une famille de tests seulement)
 *
 * Chaque test affiche « ok » ou « ÉCHEC » ; le script termine avec le code 1
 * si au moins un test a échoué, pour pouvoir l'enchaîner dans une commande.
 *
 * Aucun accès à la base : ces tests vérifient des fonctions de texte, ils
 * peuvent donc tourner n'importe où, même sur une base vide.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

$filtre = $argv[1] ?? '';

$reussis = 0;
$echecs  = [];

/**
 * Compare le résultat d'un test à ce qui est attendu.
 *
 * @param string $nom     ce que l'on vérifie, en français simple
 * @param bool   $ok      le test est-il passé ?
 * @param string $detail  explication en cas d'échec
 */
function verifier(string $nom, bool $ok, string $detail = ''): void
{
    global $reussis, $echecs;

    if ($ok) {
        $reussis++;
        echo "  ok      ", $nom, "\n";
        return;
    }

    $echecs[] = $nom;
    echo "  ÉCHEC   ", $nom, "\n";
    if ($detail !== '') {
        echo "          ", str_replace("\n", "\n          ", $detail), "\n";
    }
}

/** Vérifie qu'un texte contient (ou non) un morceau, en le montrant en cas d'échec. */
function verifier_contient(string $nom, string $aiguille, string $foin, bool $attendu = true): void
{
    $present = str_contains($foin, $aiguille);
    verifier(
        $nom,
        $present === $attendu,
        ($attendu ? 'attendu : « ' : 'ne devait pas contenir : « ') . $aiguille . " »\nobtenu  : " . $foin
    );
}

// ---------------------------------------------------------------------------
// Tchat : mise en forme du texte des messages
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'tchat') {
    echo "Tchat — mise en forme du texte\n";

    // 1. Adresse contenant « & » : le texte affiché ne doit pas être échappé
    //    deux fois (« &amp;amp; »), le lien doit porter « &amp; » une fois.
    $avecEt = tchat_texte('Voir https://exemple.fr/p?a=1&b=2 merci');
    verifier_contient('adresse avec & — lien correct', '<a href="https://exemple.fr/p?a=1&amp;b=2"', $avecEt);
    verifier_contient('adresse avec & — texte du lien échappé une seule fois', '>https://exemple.fr/p?a=1&amp;b=2</a>', $avecEt);
    verifier_contient('adresse avec & — aucun double échappement', '&amp;amp;', $avecEt, false);

    // 2. Adresse entre guillemets : les guillemets restent du texte (ils sont
    //    affichés « &quot; » une seule fois, en dehors du lien).
    $entreGuillemets = tchat_texte('Le site "https://exemple.fr/p?a=1&b=2" est en ligne.');
    $lienAttendu = '<a href="https://exemple.fr/p?a=1&amp;b=2" rel="noopener noreferrer" target="_blank">'
        . 'https://exemple.fr/p?a=1&amp;b=2</a>';
    verifier_contient('adresse entre guillemets — lien exact, sans &quot; à l’intérieur', $lienAttendu, $entreGuillemets);
    verifier_contient('adresse entre guillemets — guillemet d’ouverture conservé', '&quot;' . $lienAttendu, $entreGuillemets);
    verifier_contient('adresse entre guillemets — guillemet de fermeture conservé', $lienAttendu . '&quot; est en ligne.', $entreGuillemets);

    // 3. Ponctuation collée : le point et la parenthèse restent dehors.
    $pointFinal = tchat_texte('Voir https://exemple.fr/page.');
    verifier_contient('adresse suivie d’un point — le point sort du lien', '</a>.', $pointFinal);
    verifier_contient('adresse suivie d’un point — pas de point dans l’adresse', 'page.</a>', $pointFinal, false);

    $parenthese = tchat_texte('Voir (https://exemple.fr/page) ici');
    verifier_contient('adresse entre parenthèses — la parenthèse fermante reste dehors', 'page</a>) ici', $parenthese);

    // 4. « javascript: » n'est jamais transformé en lien.
    $javascript = tchat_texte('javascript:alert(1)');
    verifier_contient('javascript: — aucun lien créé', '<a ', $javascript, false);
    verifier_contient('javascript: — texte conservé', 'javascript:alert(1)', $javascript);

    // 5. Le HTML saisi n'est jamais interprété.
    $script = tchat_texte('<script>alert(1)</script>');
    verifier_contient('balise <script> — échappée', '&lt;script&gt;alert(1)&lt;/script&gt;', $script);
    verifier_contient('balise <script> — aucune balise active', '<script', $script, false);

    // 6. Message de 1000 caractères sans espace : tout reste sur une ligne.
    $sansEspace = str_repeat('a', 1000);
    $resultat   = tchat_texte($sansEspace);
    verifier('1000 caractères sans espace — texte intégral', $resultat === $sansEspace, 'longueur obtenue : ' . strlen($resultat));
    verifier('1000 caractères sans espace — aucun lien', str_contains($resultat, '<a ') === false);

    // 7. Cas courants qui doivent continuer de fonctionner.
    verifier_contient(
        'www. — lien en https',
        '<a href="https://www.exemple.fr/page" rel="noopener noreferrer" target="_blank">www.exemple.fr/page</a>',
        tchat_texte('Aller sur www.exemple.fr/page')
    );
    verifier_contient(
        'www. — adresse suivie d’une virgule',
        '</a>, puis la suite',
        tchat_texte('Aller sur www.exemple.fr/page, puis la suite')
    );
    verifier_contient(
        'liens externes — rel="noopener noreferrer" et nouvel onglet',
        'rel="noopener noreferrer" target="_blank"',
        tchat_texte('https://exemple.fr/')
    );
    verifier(
        'sauts de ligne conservés',
        tchat_texte("Ligne 1\nLigne 2") === "Ligne 1<br>\nLigne 2",
        'obtenu : ' . tchat_texte("Ligne 1\nLigne 2")
    );
    verifier(
        'texte sans adresse — inchangé',
        tchat_texte('Bonjour à tous !') === 'Bonjour à tous !',
        'obtenu : ' . tchat_texte('Bonjour à tous !')
    );
    verifier(
        'accents et apostrophes — conservés',
        tchat_texte("L’été d’Hélène") === 'L’été d’Hélène',
        'obtenu : ' . tchat_texte("L’été d’Hélène")
    );
    verifier(
        'adresse seule — un seul lien',
        substr_count(tchat_texte('https://exemple.fr'), '<a ') === 1
    );
    verifier(
        'adresse incomplète — pas de lien cassé',
        tchat_texte('https://') === 'https://',
        'obtenu : ' . tchat_texte('https://')
    );
}

// ---------------------------------------------------------------------------
// Recherche : préparation du texte cherché
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'recherche') {
    echo "\nRecherche — texte cherché\n";

    verifier(
        'minuscules et accents retirés',
        recherche_normaliser('L’Hélène de Bègles') === 'l helene de begles',
        'obtenu : « ' . recherche_normaliser('L’Hélène de Bègles') . ' »'
    );
    verifier(
        'majuscules accentuées',
        recherche_normaliser('ÉCOLE PRIVÉE') === 'ecole privee',
        'obtenu : « ' . recherche_normaliser('ÉCOLE PRIVÉE') . ' »'
    );
    verifier(
        'ligatures (œ, æ)',
        recherche_normaliser('cœur et æquo') === 'coeur et aequo',
        'obtenu : « ' . recherche_normaliser('cœur et æquo') . ' »'
    );
    verifier(
        'ponctuation et guillemets ignorés',
        recherche_normaliser('« Martin, Jean ! » (1932)') === 'martin jean 1932',
        'obtenu : « ' . recherche_normaliser('« Martin, Jean ! » (1932)') . ' »'
    );
    verifier(
        'années séparées par un tiret',
        recherche_normaliser('1928-1930') === '1928 1930',
        'obtenu : « ' . recherche_normaliser('1928-1930') . ' »'
    );

    verifier(
        'découpage en mots',
        recherche_mots('  Jean   MARTIN ') === ['jean', 'martin'],
        'obtenu : ' . implode(', ', recherche_mots('  Jean   MARTIN '))
    );
    verifier('recherche vide → aucun mot', recherche_mots('   ') === []);
    verifier('recherche vide de sens → aucun mot', recherche_mots('!!! ???') === []);
    verifier(
        'six mots au maximum',
        count(recherche_mots('un deux trois quatre cinq six sept huit')) === 6
    );
    verifier(
        'mots répétés comptés une fois',
        recherche_mots('martin martin martin') === ['martin']
    );
    verifier(
        'mot très long coupé à 40 caractères',
        mb_strlen(recherche_mots(str_repeat('a', 80))[0], 'UTF-8') === 40
    );

    $motif = recherche_motif_glob('begles');
    verifier(
        'motif GLOB : variantes accentuées de chaque lettre',
        $motif === '*b[eéèêëEÉÈÊË]gl[eéèêëEÉÈÊË]s*',
        'obtenu : ' . $motif
    );
    verifier(
        'motif GLOB : chiffres conservés',
        recherche_motif_glob('1901') === '*1901*',
        'obtenu : ' . recherche_motif_glob('1901')
    );
    verifier(
        'motif GLOB : caractères spéciaux neutralisés',
        recherche_motif_glob('a*b') === '*[aàâäáãåAÀÂÄÁÃÅ]?b*',
        'obtenu : ' . recherche_motif_glob('a*b')
    );

    verifier(
        'requête FTS : chaque mot entre guillemets, en préfixe',
        recherche_requete_fts(['jean', 'martin']) === '"jean"* "martin"*',
        'obtenu : ' . recherche_requete_fts(['jean', 'martin'])
    );
    verifier(
        'catégories cherchées',
        array_keys(recherche_types()) === ['documents', 'articles', 'pages', 'personnes'],
        'obtenu : ' . implode(', ', array_keys(recherche_types()))
    );
}

// ---------------------------------------------------------------------------
// Fiche d'une personne : les repères de vie
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'personne') {
    echo "\nFiche d'une personne — repères de vie\n";

    $jean = [
        'id' => 1, 'first_name' => 'Jean', 'last_name' => 'Martin',
        'birth_date' => '1901', 'birth_place' => 'Bordeaux',
        'death_date' => '1975', 'death_place' => '',
    ];
    $unions = [
        [
            'spouse'  => ['first_name' => 'Marie', 'last_name' => 'Leroy'],
            'family'  => ['marriage_date' => '1928-06-12', 'marriage_place' => 'Bègles'],
            'children' => [
                ['id' => 4, 'first_name' => 'Paul', 'last_name' => 'Martin', 'birth_date' => '1930'],
                ['id' => 5, 'first_name' => 'Anne', 'last_name' => 'Martin', 'birth_date' => '1935'],
            ],
        ],
    ];

    $reperes = person_reperes($jean, $unions);

    verifier('un repère par naissance, mariage, enfant et décès', count($reperes) === 5, 'obtenu : ' . count($reperes));
    verifier(
        'repères classés du plus ancien au plus récent',
        array_column($reperes, 'tri') === [1901, 1928, 1930, 1935, 1975],
        'obtenu : ' . implode(', ', array_column($reperes, 'tri'))
    );
    verifier(
        'lieu de naissance ajouté',
        $reperes[0]['texte'] === 'Naissance à Bordeaux',
        'obtenu : « ' . $reperes[0]['texte'] . ' »'
    );
    verifier(
        'mariage nommé avec le conjoint et le lieu',
        $reperes[1]['texte'] === 'Mariage avec Marie LEROY à Bègles',
        'obtenu : « ' . $reperes[1]['texte'] . ' »'
    );
    verifier(
        'naissance d\'un enfant : texte et lien vers sa fiche',
        $reperes[2]['texte'] === 'Naissance : Paul MARTIN' && $reperes[2]['url'] === '/arbre/4',
        'obtenu : « ' . $reperes[2]['texte'] . ' », lien : ' . (string) $reperes[2]['url']
    );
    verifier(
        'décès sans lieu : simple',
        $reperes[4]['texte'] === 'Décès',
        'obtenu : « ' . $reperes[4]['texte'] . ' »'
    );

    verifier('personne sans aucune date → aucun repère', person_reperes(['id' => 9], []) === []);

    $inconnues = person_reperes(
        ['id' => 2, 'birth_date' => '', 'death_date' => 'vers 1950'],
        [
            [
                'spouse'  => null,
                'family'  => ['marriage_date' => '', 'marriage_place' => 'Pessac'],
                'children' => [['id' => 7, 'first_name' => 'Luc', 'last_name' => 'Durand', 'birth_date' => '']],
            ],
        ]
    );
    verifier(
        'année inconnue (« vers 1950 ») reconnue et placée à la fin',
        count($inconnues) === 1
            && $inconnues[0]['texte'] === 'Décès'
            && $inconnues[0]['tri'] === 1950,
        'obtenu : ' . json_encode($inconnues, JSON_UNESCAPED_UNICODE)
    );
    verifier(
        'mariage sans date de mariage : repère absent',
        array_column($inconnues, 'texte') === ['Décès']
    );
}

// ---------------------------------------------------------------------------
// Albums photos : phrase « qui figure sur cette photo »
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'albums') {
    echo "\nAlbums — personnes identifiées\n";

    $jean  = ['first_name' => 'Jean', 'last_name' => 'Martin'];
    $marie = ['first_name' => 'Marie', 'last_name' => 'Leroy'];
    $paul  = ['first_name' => 'Paul', 'last_name' => 'Martin'];
    $anne  = ['first_name' => 'Anne', 'last_name' => 'Martin'];
    $luc   = ['first_name' => 'Luc', 'last_name' => 'Martin'];

    verifier('aucune personne → texte vide', album_people_line([]) === '', 'obtenu : « ' . album_people_line([]) . ' »');
    verifier(
        'une personne',
        album_people_line([$jean]) === 'Jean MARTIN',
        'obtenu : « ' . album_people_line([$jean]) . ' »'
    );
    verifier(
        'deux personnes reliées par « et »',
        album_people_line([$jean, $marie]) === 'Jean MARTIN et Marie LEROY',
        'obtenu : « ' . album_people_line([$jean, $marie]) . ' »'
    );
    verifier(
        'trois personnes : virgules puis « et »',
        album_people_line([$jean, $marie, $paul]) === 'Jean MARTIN, Marie LEROY et Paul MARTIN',
        'obtenu : « ' . album_people_line([$jean, $marie, $paul]) . ' »'
    );
    verifier(
        'au-delà de trois : « et N autres »',
        album_people_line([$jean, $marie, $paul, $anne, $luc]) === 'Jean MARTIN, Marie LEROY, Paul MARTIN et 2 autres',
        'obtenu : « ' . album_people_line([$jean, $marie, $paul, $anne, $luc]) . ' »'
    );
    verifier(
        'quatre personnes (limite de trois)',
        album_people_line([$jean, $marie, $paul, $anne]) === 'Jean MARTIN, Marie LEROY, Paul MARTIN et 1 autre',
        'obtenu : « ' . album_people_line([$jean, $marie, $paul, $anne]) . ' »'
    );
    verifier(
        'personne sans nom ignorée',
        album_people_line([['first_name' => '', 'last_name' => ''], $jean]) === 'Jean MARTIN',
        'obtenu : « ' . album_people_line([['first_name' => '', 'last_name' => ''], $jean]) . ' »'
    );
    verifier(
        'adresse d’un album',
        album_url(['slug' => 'vacances-1965']) === '/albums/vacances-1965',
        'obtenu : ' . album_url(['slug' => 'vacances-1965'])
    );
    verifier(
        'un son est reconnu par son type de fichier',
        document_is_audio(['mime' => 'audio/mpeg']) === true
            && document_is_audio(['mime' => 'image/jpeg']) === false
            && document_is_audio([]) === false
    );
}

// ---------------------------------------------------------------------------
// Résumé
// ---------------------------------------------------------------------------
$total = $reussis + count($echecs);
echo "\n", $total, " test(s) — ", $reussis, " ok, ", count($echecs), " échec(s).\n";

if ($echecs !== []) {
    echo "\nTests en échec :\n";
    foreach ($echecs as $nom) {
        echo "  · ", $nom, "\n";
    }
    exit(1);
}

exit(0);
