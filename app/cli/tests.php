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
 * Les tests de texte ne touchent pas à la base et peuvent tourner n'importe où.
 * La famille « journal » vérifie, elle, le journal des connexions : elle écrit
 * quelques lignes d'essai puis les efface et remet les réglages en place. Si la
 * table n'existe pas encore, elle s'arrête sans échouer.
 * La famille « rubriques » vérifie ce que voit un visiteur (menu public réduit,
 * rubriques vides masquées) : ses essais se font dans une transaction annulée,
 * la base est donc rendue intacte dans tous les cas.
 * La famille « editions » vérifie les filtres ?branche= et ?decennie= du
 * journal, et qu'un visiteur n'y voit jamais un article non public.
 * La famille « comptes » vérifie les comptes « personne » : identifiants,
 * analyse d'une liste collée, création (seul le hachage est stocké), droits,
 * désactivation, réinitialisation, changement de son mot de passe, signature
 * du tchat et CSRF. Tout se passe dans une transaction annulée.
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
// Calendrier et frise : lecture des dates de la famille
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'calendrier') {
    echo "\nCalendrier — dates et grille du mois\n";

    verifier(
        'date ISO',
        date_parts('1928-06-12') === ['annee' => 1928, 'mois' => 6, 'jour' => 12],
        'obtenu : ' . json_encode(date_parts('1928-06-12'))
    );
    verifier(
        'date à la française 12/06/1928',
        date_parts('12/06/1928') === ['annee' => 1928, 'mois' => 6, 'jour' => 12],
        'obtenu : ' . json_encode(date_parts('12/06/1928'))
    );
    verifier(
        'date écrite « 12 juin 1928 »',
        date_parts('12 juin 1928') === ['annee' => 1928, 'mois' => 6, 'jour' => 12],
        'obtenu : ' . json_encode(date_parts('12 juin 1928'))
    );
    verifier(
        'date écrite « 1er février 1975 »',
        date_parts('1er février 1975') === ['annee' => 1975, 'mois' => 2, 'jour' => 1],
        'obtenu : ' . json_encode(date_parts('1er février 1975'))
    );
    verifier(
        'mois sans jour : « juin 1928 »',
        date_parts('juin 1928') === ['annee' => 1928, 'mois' => 6, 'jour' => 0],
        'obtenu : ' . json_encode(date_parts('juin 1928'))
    );
    verifier(
        'année seule : « vers 1930 »',
        date_parts('vers 1930') === ['annee' => 1930, 'mois' => 0, 'jour' => 0],
        'obtenu : ' . json_encode(date_parts('vers 1930'))
    );
    verifier('date vide → rien', date_parts('') === null && date_parts(null) === null);
    verifier('texte sans année → rien', date_parts('inconnu') === null);
    verifier('31 février refusé', date_parts('31/02/1950') === null);
    verifier('mois 13 refusé', date_parts('1928-13-01') === null);

    $grille = calendrier_grille(2026, 11);
    verifier('grille de novembre 2026 : 6 semaines complètes', count($grille) === 6, 'obtenu : ' . count($grille));
    verifier('chaque semaine a 7 cases', count(array_filter($grille, static fn ($s) => count($s) === 7)) === count($grille));
    verifier('novembre 2026 commence un dimanche', $grille[0] === [0, 0, 0, 0, 0, 0, 1], 'obtenu : ' . json_encode($grille[0]));
    verifier('le mois compte 30 jours', count(array_filter(array_merge(...$grille), static fn ($j) => $j > 0)) === 30);

    verifier(
        'mois suivant passé correctement (décembre)',
        calendrier_mois_voisin(2026, 11, 1) === [2026, 12],
        'obtenu : ' . json_encode(calendrier_mois_voisin(2026, 11, 1))
    );
    verifier(
        'changement d’année (janvier précédent)',
        calendrier_mois_voisin(2026, 1, -1) === [2025, 12],
        'obtenu : ' . json_encode(calendrier_mois_voisin(2026, 1, -1))
    );
    verifier(
        'nombre du mois à partir du nom',
        calendrier_mois_numero('Août') === 8 && calendrier_mois_numero('fevrier') === 2
            && calendrier_mois_numero('inconnu') === 0
    );
    verifier(
        'âge lors d’une année',
        calendrier_age(1905, 1982) === 77 && calendrier_age(null, 1982) === null
            && calendrier_age(1990, 1982) === null
    );
}

// ---------------------------------------------------------------------------
// Export GEDCOM et recettes : préparation des textes
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'gedcom') {
    echo "\nExport GEDCOM — dates et textes\n";

    verifier(
        'date française écrite avec le mois en lettres',
        gedcom_export_date('14 mars 1901') === '14 MAR 1901',
        'obtenu : ' . gedcom_export_date('14 mars 1901')
    );
    verifier(
        'date ISO',
        gedcom_export_date('1928-06-12') === '12 JUN 1928',
        'obtenu : ' . gedcom_export_date('1928-06-12')
    );
    verifier(
        'mois seul',
        gedcom_export_date('juin 1928') === 'JUN 1928',
        'obtenu : ' . gedcom_export_date('juin 1928')
    );
    verifier(
        'année seule',
        gedcom_export_date('1901') === '1901',
        'obtenu : ' . gedcom_export_date('1901')
    );
    verifier(
        'date approximative : « vers 1930 » → ABT',
        gedcom_export_date('vers 1930') === 'ABT 1930',
        'obtenu : ' . gedcom_export_date('vers 1930')
    );
    verifier(
        'date vide → vide',
        gedcom_export_date('') === '' && gedcom_export_date(null) === ''
    );
    verifier(
        'texte incompris laissé tel quel',
        gedcom_export_date('après la guerre') === 'après la guerre',
        'obtenu : ' . gedcom_export_date('après la guerre')
    );

    verifier(
        'retours à la ligne remplacés par des espaces',
        gedcom_export_text("Première ligne\nSeconde ligne") === 'Première ligne Seconde ligne',
        'obtenu : « ' . gedcom_export_text("Première ligne\nSeconde ligne") . ' »'
    );
    verifier(
        'arobase doublée (règle GEDCOM)',
        gedcom_export_text('écrire à contact@exemple.fr') === 'écrire à contact@@exemple.fr',
        'obtenu : « ' . gedcom_export_text('écrire à contact@exemple.fr') . ' »'
    );
    verifier(
        'espaces multiples réduits',
        gedcom_export_text('  Jean    MARTIN  ') === 'Jean MARTIN',
        'obtenu : « ' . gedcom_export_text('  Jean    MARTIN  ') . ' »'
    );
    verifier(
        'ligne GEDCOM au bon format',
        gedcom_export_line(1, 'NAME', 'Jean /MARTIN/') === "1 NAME Jean /MARTIN/\r\n",
        'obtenu : ' . json_encode(gedcom_export_line(1, 'NAME', 'Jean /MARTIN/'))
    );
    verifier(
        'ligne GEDCOM sans valeur',
        gedcom_export_line(0, 'TRLR') === "0 TRLR\r\n",
        'obtenu : ' . json_encode(gedcom_export_line(0, 'TRLR'))
    );
}

if ($filtre === '' || $filtre === 'recettes') {
    echo "\nRecettes — ingrédients\n";

    $lignes = recipe_ingredient_lines("250 g de farine\n3 œufs\n\n1 pincée de sel");
    verifier(
        'une ligne = un ingrédient, lignes vides ignorées',
        $lignes === ['250 g de farine', '3 œufs', '1 pincée de sel'],
        'obtenu : ' . json_encode($lignes, JSON_UNESCAPED_UNICODE)
    );
    verifier(
        'tirets en début de ligne retirés',
        recipe_ingredient_lines("- 2 carottes\n* 1 poireau") === ['2 carottes', '1 poireau'],
        'obtenu : ' . json_encode(recipe_ingredient_lines("- 2 carottes\n* 1 poireau"), JSON_UNESCAPED_UNICODE)
    );
    verifier('ingrédients vides → liste vide', recipe_ingredient_lines('   ') === []);
}

// ---------------------------------------------------------------------------
// Journal des connexions : enregistrement, alertes, purge
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'journal') {
    echo "\nJournal des connexions\n";

    // Première instruction d'une fonction de contrôleur : sert à vérifier que
    // la page est bien réservée aux administrateurs.
    $premiereInstruction = static function (string $fichier, string $fonction): string {
        $source = (string) file_get_contents(dirname(__DIR__) . '/controllers/' . $fichier);
        $motif  = '/function\s+' . preg_quote($fonction, '/') . '\s*\(\s*\)\s*:\s*void\s*\{/';
        if (!preg_match($motif, $source, $trouve, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $corps = substr($source, (int) $trouve[0][1] + strlen((string) $trouve[0][0]), 300);
        foreach (preg_split('/\R/', $corps) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne !== '' && !str_starts_with($ligne, '*') && !str_starts_with($ligne, '/*')) {
                return $ligne;
            }
        }
        return '';
    };

    // 1. Accès : les trois actions du journal sont réservées aux administrateurs.
    foreach (['admin_connexions', 'admin_alertes_acquitter', 'admin_connexions_reglage'] as $action) {
        verifier(
            'action réservée aux administrateurs : ' . $action,
            $premiereInstruction('admin_journal.php', $action) === 'require_admin();',
            'première instruction obtenue : « ' . $premiereInstruction('admin_journal.php', $action) . ' »'
        );
    }
    $routesSource = (string) file_get_contents(dirname(__DIR__) . '/routes.php');
    verifier(
        'route /admin/connexions déclarée',
        str_contains($routesSource, "route('GET', '/admin/connexions', 'admin_connexions')")
    );
    verifier(
        'acquittement en POST avec jeton CSRF',
        str_contains($routesSource, "route('POST', '/admin/alertes/acquitter', 'admin_alertes_acquitter')")
            && str_contains((string) file_get_contents(dirname(__DIR__) . '/controllers/admin_journal.php'), 'csrf_check();')
    );

    // 2. Durées de conservation proposées.
    verifier(
        'quatre durées de conservation, 90 jours par défaut',
        array_keys(journal_conservations()) === [30, 90, 180, 365],
        'obtenu : ' . implode(', ', array_keys(journal_conservations()))
    );

    $journalPrete = db_value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'login_log'") !== null;

    if (!$journalPrete) {
        echo "  (table login_log absente : lancez d'abord les migrations)\n";
    } else {
        verifier(
            'conservation enregistrée valide',
            array_key_exists(journal_conservation_jours(), journal_conservations()),
            'obtenu : ' . journal_conservation_jours() . ' jours'
        );

        // On note ce qui existait avant, pour tout effacer à la fin.
        $marqueur     = (int) db_value('SELECT COALESCE(MAX(id), 0) FROM login_log', [], 0);
        $comptesAvant = db_value('SELECT id FROM accounts WHERE username = ?', ['essai-journal-tests']);
        if ($comptesAvant !== null) {
            db_exec('DELETE FROM accounts WHERE id = ?', [(int) $comptesAvant]);
        }
        $reglage = static fn (string $cle): string
            => (string) db_value('SELECT value FROM settings WHERE key = ?', [$cle], '');
        $ecrireReglage = static function (string $cle, string $valeur): void {
            db_exec(
                'INSERT INTO settings (key, value) VALUES (?, ?)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value',
                [$cle, $valeur]
            );
        };
        $vuesAvant = $reglage('journal_alertes_vues');

        $derniere = static fn (): ?array => db_one('SELECT * FROM login_log ORDER BY id DESC LIMIT 1');

        // 3. Un échec est enregistré avec sa raison.
        journal_enregistrer('inconnu-essai-tests', '203.0.113.10', false, 'identifiant inconnu');
        $ligne = $derniere();
        verifier(
            'échec enregistré avec sa raison',
            $ligne !== null && (int) $ligne['success'] === 0
                && $ligne['detail'] === 'identifiant inconnu'
                && $ligne['identifier'] === 'inconnu-essai-tests'
                && $ligne['ip'] === '203.0.113.10'
                && empty($ligne['account_id']),
            'obtenu : ' . json_encode($ligne, JSON_UNESCAPED_UNICODE)
        );

        // 4. Un blocage est enregistré comme tel.
        journal_enregistrer('bloque-essai-tests', '203.0.113.11', false, 'bloqué (trop d’essais)');
        $ligne = $derniere();
        verifier(
            'blocage enregistré',
            $ligne !== null && (int) $ligne['success'] === 0
                && str_starts_with((string) $ligne['detail'], 'bloqué')
                && $ligne['identifier'] === 'bloque-essai-tests',
            'obtenu : ' . json_encode($ligne, JSON_UNESCAPED_UNICODE)
        );

        // 4 bis. Un robot qui insiste sur un compte bloqué n'écrit qu'une ligne
        //        toutes les cinq minutes (sinon le journal grossit sans fin).
        journal_enregistrer('bloque-essai-tests', '203.0.113.11', false, 'bloqué (trop d’essais)');
        verifier(
            'tentative bloquée répétée : pas de doublon immédiat',
            (int) db_value(
                'SELECT COUNT(*) FROM login_log WHERE identifier = ?',
                ['bloque-essai-tests'],
                0
            ) === 1,
            'nombre de lignes : ' . (int) db_value('SELECT COUNT(*) FROM login_log WHERE identifier = ?', ['bloque-essai-tests'], 0)
        );
        journal_enregistrer('bloque-essai-tests', '203.0.113.12', false, 'bloqué (trop d’essais)');
        verifier(
            'tentative bloquée depuis une autre adresse : nouvelle ligne',
            (int) db_value('SELECT COUNT(*) FROM login_log WHERE identifier = ?', ['bloque-essai-tests'], 0) === 2
        );

        // 5. Alertes : nouvelle adresse d'un administrateur, jamais la première.
        $compteEssai = db_insert('accounts', [
            'username'      => 'essai-journal-tests',
            'display_name'  => 'Compte de test (tests.php)',
            'password_hash' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
            'role'          => 'admin',
            'branch_id'     => null,
            'is_active'     => 1,
            'created_at'    => time(),
            'updated_at'    => time(),
        ]);
        $ecrireReglage('journal_alertes_vues', '0');

        journal_enregistrer('essai-journal-tests', '198.51.100.1', true, 'connexion réussie', $compteEssai, true);
        $ligne  = $derniere();
        $ipsAvant = array_column(journal_alertes()['nouvelles_ip'], 'ip');
        verifier(
            'première connexion d’un administrateur : pas d’alerte',
            $ligne !== null && (int) $ligne['new_ip'] === 0 && !in_array('198.51.100.1', $ipsAvant, true),
            'new_ip = ' . (int) ($ligne['new_ip'] ?? -1) . ' ; adresses alertées : ' . implode(', ', $ipsAvant)
        );

        journal_enregistrer('essai-journal-tests', '198.51.100.2', true, 'connexion réussie', $compteEssai, true);
        $ligne   = $derniere();
        $alertes = journal_alertes();
        verifier(
            'administrateur depuis une nouvelle adresse : alerte',
            $ligne !== null && (int) $ligne['new_ip'] === 1
                && in_array('198.51.100.2', array_column($alertes['nouvelles_ip'], 'ip'), true),
            'new_ip = ' . (int) ($ligne['new_ip'] ?? -1) . ' ; adresses alertées : '
                . implode(', ', array_column($alertes['nouvelles_ip'], 'ip'))
        );

        journal_enregistrer('essai-journal-tests', '198.51.100.3', true, 'connexion réussie', $compteEssai, false);
        verifier(
            'compte non administrateur : jamais d’alerte',
            (int) $derniere()['new_ip'] === 0,
            'new_ip = ' . (int) $derniere()['new_ip']
        );

        // 6. Seuil d'échecs : dix en une heure ne suffisent pas, onze déclenchent.
        db_exec('DELETE FROM login_log WHERE id > ?', [$marqueur]);
        $ecrireReglage('journal_alertes_vues', (string) (time() - 1));
        for ($i = 1; $i <= 10; $i++) {
            journal_enregistrer('seuil-essai-tests', '203.0.113.' . (20 + $i), false, 'mot de passe incorrect');
        }
        $alertes = journal_alertes();
        verifier(
            'dix échecs en une heure : pas encore d’alerte',
            $alertes['echecs'] === null,
            'obtenu : ' . json_encode($alertes['echecs'])
        );

        journal_enregistrer('seuil-essai-tests', '203.0.113.31', false, 'mot de passe incorrect');
        $alertes = journal_alertes();
        verifier(
            'plus de dix échecs en une heure : alerte',
            $alertes['echecs'] !== null && (int) $alertes['echecs']['nb'] === 11,
            'obtenu : ' . json_encode($alertes['echecs'])
        );

        // 7. « J'ai pris connaissance » : la date est enregistrée.
        journal_acquitter();
        $vuesApres = (int) $reglage('journal_alertes_vues');
        verifier(
            'acquittement enregistré',
            $vuesApres >= time() - 2,
            'réglage obtenu : ' . $vuesApres . ' (attendu : environ ' . time() . ')'
        );

        // 8. Purge selon la conservation en vigueur. On n'y touche pas : deux
        //    lignes sont placées de part et d'autre de la limite, une seule
        //    doit disparaître.
        $conservation = journal_conservation_jours();
        $ajouterLigne = static function (string $identifiant, string $ip, int $ageEnJours): void {
            db_exec(
                'INSERT INTO login_log (account_id, identifier, ip, success, detail, new_ip, created_at)
                 VALUES (NULL, ?, ?, 0, ?, 0, ?)',
                [$identifiant, $ip, 'identifiant inconnu', time() - $ageEnJours * 86400]
            );
        };
        $ajouterLigne('vieux-essai-tests', '203.0.113.40', $conservation + 1);
        $ajouterLigne('recent-essai-tests', '203.0.113.41', $conservation - 1);
        $supprimees = journal_purger();
        verifier(
            'purge : une ligne plus vieille que la conservation est effacée',
            $supprimees >= 1
                && db_value('SELECT COUNT(*) FROM login_log WHERE identifier = ?', ['vieux-essai-tests'], 0) === 0,
            'conservation : ' . $conservation . ' jours ; lignes supprimées : ' . $supprimees
        );
        verifier(
            'purge : une ligne plus récente est conservée',
            (int) db_value('SELECT COUNT(*) FROM login_log WHERE identifier = ?', ['recent-essai-tests'], 0) === 1
        );

        // Rangement : on rend la base exactement comme on l'a trouvée.
        db_exec('DELETE FROM login_log WHERE id > ?', [$marqueur]);
        db_exec('DELETE FROM accounts WHERE id = ?', [$compteEssai]);
        $ecrireReglage('journal_alertes_vues', $vuesAvant);
        verifier(
            'le test ne laisse aucune trace',
            (int) db_value('SELECT COUNT(*) FROM login_log WHERE id > ?', [$marqueur], 0) === 0
                && db_value('SELECT id FROM accounts WHERE username = ?', ['essai-journal-tests']) === null,
            'des lignes du journal de test sont restées'
        );
    }
}

// ---------------------------------------------------------------------------
// Rubriques publiques et menu (lot 7)
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'rubriques') {
    echo "Rubriques publiques et menu\n";

    $tablesPubliques = ['articles', 'documents', 'albums', 'pages', 'persons'];
    $tablesPresentes = true;
    foreach ($tablesPubliques as $table) {
        if ((int) db_value(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table],
            0
        ) === 0) {
            $tablesPresentes = false;
        }
    }

    if (!$tablesPresentes) {
        echo "  (base incomplète : migrations à lancer, tests ignorés)\n\n";
    } else {
        /**
         * Exécute des modifications puis les annule : la base est rendue
         * exactement dans l'état où on l'a trouvée, même si un test échoue.
         */
        $dansUneTransactionAnnulee = static function (callable $travail): void {
            try {
                db_transaction(static function () use ($travail): void {
                    $travail();
                    throw new RuntimeException('annulation volontaire du test');
                });
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== 'annulation volontaire du test') {
                    throw $e;
                }
            }
        };

        // 1. Aucun contenu public : il ne reste que les trois entrées de base.
        $sansRien     = null;
        $menuSansRien = null;
        $dansUneTransactionAnnulee(static function () use (&$sansRien, &$menuSansRien): void {
            db_exec("UPDATE articles SET status = 'brouillon'");
            db_exec("UPDATE documents SET visibility = 'prive'");
            db_exec("UPDATE albums SET visibility = 'prive'");
            db_exec("UPDATE pages SET visibility = 'prive'");
            db_exec("UPDATE persons SET visibility = 'prive'");
            $sansRien     = rubriques_publiques(true);
            $menuSansRien = array_column(menu_rubriques(null), 'cle');
        });

        verifier(
            'aucun contenu public : seulement documents, arbre et famille',
            !empty($sansRien['documents']) && !empty($sansRien['arbre']) && !empty($sansRien['famille'])
                && empty($sansRien['articles']) && empty($sansRien['videos'])
                && empty($sansRien['albums']) && empty($sansRien['calendrier'])
                && empty($sansRien['recherche']),
            'obtenu : ' . json_encode($sansRien, JSON_UNESCAPED_UNICODE)
        );
        verifier(
            'aucun contenu public : menu du visiteur réduit aux trois entrées',
            $menuSansRien === ['accueil', 'documents', 'arbre'],
            'obtenu : ' . implode(', ', (array) $menuSansRien)
        );

        // Hors transaction : on recalcule sur l'état réel de la base.
        rubriques_publiques(true);

        // 2. Un article public : la rubrique Articles revient, dans le menu aussi.
        $avecArticle = null;
        $menuArticle = null;
        $dansUneTransactionAnnulee(static function () use (&$avecArticle, &$menuArticle): void {
            db_exec("UPDATE articles SET status = 'brouillon'");
            db_exec(
                "INSERT INTO articles (slug, title, summary, body, category, visibility, status, published_at, created_at, updated_at)
                 VALUES ('article-essai-tests', 'Article de test', '', 'Texte', '', 'public', 'publie', ?, ?, ?)",
                [time() - 60, time(), time()]
            );
            $avecArticle = rubriques_publiques(true);
            $menuArticle = menu_rubriques(null);
        });
        verifier(
            'un article public : rubrique Articles affichée',
            !empty($avecArticle['articles']),
            'obtenu : ' . json_encode($avecArticle, JSON_UNESCAPED_UNICODE)
        );
        verifier(
            'un article public : lien Articles dans le menu du visiteur',
            in_array('articles', array_column($menuArticle, 'cle'), true),
            'menu : ' . implode(', ', array_column($menuArticle, 'cle'))
        );

        // 3. Article programmé (date future) : pas encore public.
        $programme = null;
        $dansUneTransactionAnnulee(static function () use (&$programme): void {
            db_exec("UPDATE articles SET status = 'brouillon'");
            db_exec(
                "INSERT INTO articles (slug, title, summary, body, category, visibility, status, published_at, created_at, updated_at)
                 VALUES ('article-programme-tests', 'Article à paraître', '', 'Texte', '', 'public', 'publie', ?, ?, ?)",
                [time() + 7 * 86400, time(), time()]
            );
            $programme = rubriques_publiques(true);
        });
        verifier(
            'article programmé : rubrique Articles masquée',
            empty($programme['articles']),
            'obtenu : ' . json_encode($programme, JSON_UNESCAPED_UNICODE)
        );

        // 4. Article réservé à la famille : masqué pour un visiteur.
        $familleSeule = null;
        $dansUneTransactionAnnulee(static function () use (&$familleSeule): void {
            db_exec("UPDATE articles SET status = 'brouillon'");
            db_exec(
                "INSERT INTO articles (slug, title, summary, body, category, visibility, status, published_at, created_at, updated_at)
                 VALUES ('article-famille-tests', 'Article de famille', '', 'Texte', '', 'famille', 'publie', ?, ?, ?)",
                [time() - 60, time(), time()]
            );
            $familleSeule = rubriques_publiques(true);
        });
        verifier(
            'article réservé à la famille : rubrique Articles masquée',
            empty($familleSeule['articles']),
            'obtenu : ' . json_encode($familleSeule, JSON_UNESCAPED_UNICODE)
        );

        // 5. Calendrier : seulement si une personne publique a des dates.
        $avecDates = null;
        $sansDates = null;
        $dansUneTransactionAnnulee(static function () use (&$avecDates, &$sansDates): void {
            db_exec("UPDATE persons SET visibility = 'prive'");
            db_exec(
                "INSERT INTO persons (first_name, last_name, birth_date, birth_year, visibility, created_at, updated_at)
                 VALUES ('Essai', 'Calendrier', '12/06/1900', 1900, 'public', ?, ?)",
                [time(), time()]
            );
            $avecDates = rubriques_publiques(true);
            db_exec("UPDATE persons SET birth_date = NULL, death_date = NULL WHERE last_name = 'Calendrier'");
            $sansDates = rubriques_publiques(true);
        });
        verifier(
            'personne publique avec des dates : calendrier affiché',
            !empty($avecDates['calendrier']),
            'obtenu : ' . json_encode($avecDates, JSON_UNESCAPED_UNICODE)
        );
        verifier(
            'personne publique sans date : calendrier masqué',
            empty($sansDates['calendrier']),
            'obtenu : ' . json_encode($sansDates, JSON_UNESCAPED_UNICODE)
        );

        // 6. Le tchat n'est jamais public.
        verifier(
            'tchat : jamais dans le menu public',
            rubriques_publiques(true)['tchat'] === false
                && !in_array('tchat', array_column(menu_rubriques(null), 'cle'), true)
        );

        // 7. Le menu de chaque profil.
        $compteFamille = ['role' => 'famille', 'username' => 'essai', 'branch_name' => 'Essai'];
        $compteAdmin   = ['role' => 'admin', 'username' => 'essai'];
        $extras        = ['recettes' => true, 'temoignages' => true, 'tchat' => true];

        rubriques_publiques(true);
        $menuVisiteur = array_column(menu_rubriques(null, $extras), 'cle');
        $menuFamille  = array_column(menu_rubriques($compteFamille, $extras), 'cle');
        $menuAdmin    = array_column(menu_rubriques($compteAdmin, $extras), 'cle');

        verifier(
            'menu visiteur : commence par accueil, documents, arbre',
            array_slice($menuVisiteur, 0, 3) === ['accueil', 'documents', 'arbre'],
            'obtenu : ' . implode(', ', $menuVisiteur)
        );
        verifier(
            'menu visiteur : jamais de tchat',
            !in_array('tchat', $menuVisiteur, true),
            'obtenu : ' . implode(', ', $menuVisiteur)
        );
        verifier(
            'menu visiteur : aucun lien vers une rubrique vide',
            (static function () use ($menuVisiteur): bool {
                $base = ['accueil', 'documents', 'arbre', 'recettes', 'temoignages'];
                foreach ($menuVisiteur as $cle) {
                    if (!in_array($cle, $base, true) && !rubrique_publique($cle)) {
                        return false;
                    }
                }
                return true;
            })(),
            'obtenu : ' . implode(', ', $menuVisiteur)
        );
        verifier(
            'menu compte famille : toutes les rubriques',
            array_values(array_intersect(
                ['articles', 'documents', 'videos', 'albums', 'arbre', 'calendrier', 'recherche', 'tchat'],
                $menuFamille
            )) === ['articles', 'documents', 'videos', 'albums', 'arbre', 'calendrier', 'recherche', 'tchat'],
            'obtenu : ' . implode(', ', $menuFamille)
        );
        verifier(
            'menu administrateur : identique au menu famille',
            $menuAdmin === $menuFamille,
            'obtenu : ' . implode(', ', $menuAdmin)
        );
        verifier(
            'menu connecté : tchat présent seulement si le tchat est ouvert',
            in_array('tchat', $menuFamille, true)
                && !in_array('tchat', array_column(menu_rubriques($compteFamille, ['tchat' => false]), 'cle'), true)
        );

        rubriques_publiques(true);

        // 8. La base n'a pas été modifiée par ces essais.
        verifier(
            'les essais de rubriques ne laissent aucune trace',
            (int) db_value("SELECT COUNT(*) FROM articles WHERE slug LIKE '%-essai-tests' OR slug LIKE '%-tests'", [], 0) === 0
                && (int) db_value("SELECT COUNT(*) FROM persons WHERE last_name = 'Calendrier'", [], 0) === 0,
            'des lignes d’essai sont restées en base'
        );
    }
}

// ---------------------------------------------------------------------------
// Éditions : filtre par branche et frise par décennie (lot 7)
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'editions') {
    echo "Éditions du journal (branche et décennie)\n";

    // 1. Millésime d'un article : l'année racontée, sinon l'année de publication.
    $article1958 = ['title' => 'Le mariage de 1958', 'summary' => '', 'published_at' => mktime(0, 0, 0, 6, 14, 2026)];
    verifier(
        'millésime : l’année du titre l’emporte sur la date de publication',
        article_millesime($article1958) === 1958,
        'obtenu : ' . article_millesime($article1958)
    );

    $articleSansAnnee = ['title' => 'Une soupe pour l’hiver', 'summary' => 'Rien à dater.', 'published_at' => mktime(0, 0, 0, 3, 2, 2019)];
    verifier(
        'millésime : sans année dans le texte, on prend la publication',
        article_millesime($articleSansAnnee) === 2019,
        'obtenu : ' . article_millesime($articleSansAnnee)
    );

    $articleResume = ['title' => 'Chronique', 'summary' => 'Souvenirs de 1928.', 'published_at' => time()];
    verifier(
        'millésime : une année dans le résumé suffit',
        article_millesime($articleResume) === 1928,
        'obtenu : ' . article_millesime($articleResume)
    );

    verifier(
        'millésime : un nombre à quatre chiffres hors bornes est ignoré',
        article_millesime(['title' => 'Numéro 1500 et 2150', 'summary' => '', 'published_at' => mktime(0, 0, 0, 1, 1, 2005)]) === 2005,
        'obtenu : ' . article_millesime(['title' => 'Numéro 1500 et 2150', 'summary' => '', 'published_at' => mktime(0, 0, 0, 1, 1, 2005)])
    );

    // 2. Décennie et liste des décennies.
    verifier(
        'décennie : 1958 → 1950',
        article_decennie($article1958) === 1950,
        'obtenu : ' . article_decennie($article1958)
    );

    $liste = [
        ['title' => 'A 1958', 'summary' => '', 'published_at' => time()],
        ['title' => 'B 1965', 'summary' => '', 'published_at' => time()],
        ['title' => 'C 1969', 'summary' => '', 'published_at' => time()],
        ['title' => 'D 1928', 'summary' => '', 'published_at' => time()],
    ];
    $decennies = editions_decennies($liste);
    verifier(
        'frise : les décennies sont comptées et triées de la plus récente à la plus ancienne',
        $decennies === [1960 => 2, 1950 => 1, 1920 => 1],
        'obtenu : ' . json_encode($decennies)
    );

    verifier(
        'filtre décennie : ne garde que les articles de la décennie demandée',
        array_column(editions_filtrer_decennie($liste, 1960), 'title') === ['B 1965', 'C 1969'],
        'obtenu : ' . implode(', ', array_column(editions_filtrer_decennie($liste, 1960), 'title'))
    );

    verifier(
        'filtre décennie : sans filtre, tout est conservé',
        count(editions_filtrer_decennie($liste, 0)) === 4
    );

    // 3. Filtre par branche (à partir d'une carte article → branches).
    $avecBranches = [
        ['id' => 10, 'title' => 'Article de la branche 2'],
        ['id' => 11, 'title' => 'Article sans branche'],
    ];
    $carte = [10 => [2, 3], 11 => []];
    verifier(
        'filtre branche : garde les articles rattachés à la branche',
        array_column(editions_filtrer_branche($avecBranches, 2, $carte), 'title') === ['Article de la branche 2'],
        'obtenu : ' . implode(', ', array_column(editions_filtrer_branche($avecBranches, 2, $carte), 'title'))
    );
    verifier(
        'filtre branche : une branche sans article ne renvoie rien',
        editions_filtrer_branche($avecBranches, 9, $carte) === []
    );
    verifier(
        'filtre branche : sans filtre, tout est conservé',
        count(editions_filtrer_branche($avecBranches, 0, $carte)) === 2
    );

    // 4. Visibilité : un visiteur ne voit jamais un article non public,
    //    même en forçant ?branche= ou ?decennie= dans l'adresse.
    $basePrete = (int) db_value(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ('articles', 'content_branches')",
        [],
        0
    ) === 2;

    if (!$basePrete) {
        echo "  (base incomplète : migrations à lancer, contrôles de visibilité ignorés)\n";
    } else {
        $resultats = null;
        try {
            db_transaction(static function () use (&$resultats): void {
                // Trois articles qui racontent 1965 : un public, un pour les
                // familles, un privé. Le compte courant est un visiteur (aucun
                // compte connecté en ligne de commande).
                foreach ([
                    ['essai-public-1965', 'public', null],
                    ['essai-famille-1965', 'famille', null],
                    ['essai-prive-1975', 'prive', null],
                ] as [$slug, $visibilite, $branche]) {
                    $id = db_insert('articles', [
                        'slug'         => $slug,
                        'title'        => 'Essai 1965 (' . $visibilite . ')',
                        'summary'      => '',
                        'body'         => 'Texte de test.',
                        'category'     => 'Essai',
                        'visibility'   => $visibilite,
                        'status'       => 'publie',
                        'published_at' => time() - 60,
                        'created_at'   => time(),
                        'updated_at'   => time(),
                    ]);
                    if ($branche !== null) {
                        set_content_branches('article', $id, [$branche]);
                    }
                }

                $params = [];
                $clause = article_visible_clause('a', $params);
                // On sélectionne les colonnes que lit article_millesime() :
                // le titre et le résumé (c'est là qu'est l'année racontée).
                $visibles = db_all(
                    "SELECT a.slug, a.title, a.summary, a.published_at FROM articles a
                     WHERE {$clause} AND a.slug LIKE 'essai-%-1965'
                     ORDER BY a.slug",
                    $params
                );

                $resultats = [
                    'slugs'     => array_column($visibles, 'slug'),
                    'decennies' => editions_decennies($visibles),
                    'branches'  => array_column(editions_branches(), 'nom'),
                ];

                // On annule tout : la base doit rester intacte.
                throw new RuntimeException('fin du test, annulation');
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'fin du test, annulation') {
                throw $e;
            }
        }

        verifier(
            'visiteur : seul l’article public apparaît dans une décennie',
            $resultats !== null && $resultats['slugs'] === ['essai-public-1965'],
            'obtenu : ' . json_encode($resultats['slugs'] ?? null)
        );
        verifier(
            'visiteur : la frise ne compte que les articles publics',
            $resultats !== null && ($resultats['decennies'][1960] ?? 0) === 1,
            'obtenu : ' . json_encode($resultats['decennies'] ?? null)
        );
        verifier(
            'visiteur : le filtre par branche ne révèle aucune branche',
            $resultats !== null && $resultats['branches'] === [],
            'obtenu : ' . json_encode($resultats['branches'] ?? null)
        );
        verifier(
            'les essais d’édition ne laissent aucune trace',
            (int) db_value("SELECT COUNT(*) FROM articles WHERE slug LIKE 'essai-%-19%'", [], 0) === 0,
            'des articles de test sont restés en base'
        );
    }
}

// ---------------------------------------------------------------------------
// Résumé
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// Photo d'un article : jamais d'image cassée pour un visiteur
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'editions') {
    echo "Photo d'un article (visibilité)\n";

    $maintenant = time();
    $idPhoto    = db_insert('documents', [
        'title' => 'Photo essai', 'kind' => 'photo', 'visibility' => 'famille',
        'rel_path' => 'essai/essai.jpg', 'original_name' => 'essai.jpg', 'mime' => 'image/jpeg',
        'ext' => 'jpg', 'size' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $articleEssai = ['image_id' => $idPhoto];

    verifier(
        'photo réservée aux familles : un visiteur ne reçoit pas d’image (plaque de repli)',
        current_account() !== null || article_image($articleEssai) === null
    );

    db_exec("UPDATE documents SET visibility = 'public' WHERE id = ?", [$idPhoto]);
    verifier(
        'photo publique : l’image est proposée au visiteur',
        article_image($articleEssai) !== null
    );

    db_exec('DELETE FROM documents WHERE id = ?', [$idPhoto]);
    verifier(
        'le test ne laisse aucune trace',
        (int) db_value('SELECT COUNT(*) FROM documents WHERE id = ?', [$idPhoto], 0) === 0
    );
}

// ---------------------------------------------------------------------------
// Comptes « personne » : identifiants, liste collée, droits, mot de passe
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'comptes') {
    echo "Comptes de la famille (personnes)\n";

    // 1. Fabrication des identifiants.
    verifier(
        'identifiant : accents, majuscules et espace → elise.martin',
        compte_identifiant_base('Élise', 'Martin') === 'elise.martin',
        'obtenu : ' . compte_identifiant_base('Élise', 'Martin')
    );
    verifier(
        'identifiant : prénom composé et nom en deux mots → jean-paul.legall',
        compte_identifiant_base('Jean-Paul', 'Le Gall') === 'jean-paul.legall',
        'obtenu : ' . compte_identifiant_base('Jean-Paul', 'Le Gall')
    );
    verifier(
        'identifiant : apostrophe retirée → fatou.ndiaye',
        compte_identifiant_base('Fatou', "N'Diaye") === 'fatou.ndiaye',
        'obtenu : ' . compte_identifiant_base('Fatou', "N'Diaye")
    );
    verifier(
        'identifiant : cédille et accent grave → francois.lefevre',
        compte_identifiant_base('François', 'Lefèvre') === 'francois.lefevre',
        'obtenu : ' . compte_identifiant_base('François', 'Lefèvre')
    );
    $identifiantLong = compte_identifiant_base('Anne-Marie', 'de La Rochefoucauld Montmorency');
    verifier(
        'identifiant : un nom très long est coupé à 30 caractères et reste valide',
        mb_strlen($identifiantLong) <= 30 && compte_identifiant_valide($identifiantLong),
        'obtenu : ' . $identifiantLong . ' (' . mb_strlen($identifiantLong) . ' caractères)'
    );
    verifier(
        'identifiant : la règle refuse un point au début, une majuscule ou un accent',
        !compte_identifiant_valide('.elise')
            && !compte_identifiant_valide('Elise.martin')
            && !compte_identifiant_valide('élise.martin')
    );

    // 2. Analyse d'une liste collée (aucune écriture en base).
    $liste = compte_analyser_liste("Martin;Élise\nPaul Durand\nLe Gall Jean-Paul\n\nsolo\nJean Paul Martin\n   \n");
    verifier(
        'liste : les lignes vides sont ignorées',
        count($liste['lignes']) === 5,
        'obtenu : ' . count($liste['lignes']) . ' ligne(s)'
    );
    verifier(
        'liste : « Nom;Prénom » est compris sans ambiguïté',
        $liste['lignes'][0]['nom'] === 'Martin'
            && $liste['lignes'][0]['prenom'] === 'Élise'
            && $liste['lignes'][0]['erreur'] === '',
        json_encode($liste['lignes'][0], JSON_UNESCAPED_UNICODE)
    );
    verifier(
        'liste : « Prénom Nom » sur deux mots est accepté avec une remarque',
        $liste['lignes'][1]['prenom'] === 'Paul'
            && $liste['lignes'][1]['nom'] === 'Durand'
            && $liste['lignes'][1]['remarque'] !== '',
        json_encode($liste['lignes'][1], JSON_UNESCAPED_UNICODE)
    );
    verifier(
        'liste : un nom en plusieurs mots sans point-virgule est refusé',
        $liste['lignes'][2]['erreur'] !== '',
        json_encode($liste['lignes'][2], JSON_UNESCAPED_UNICODE)
    );
    verifier(
        'liste : une ligne d’un seul mot est refusée',
        $liste['lignes'][3]['erreur'] !== ''
    );
    verifier(
        'liste : trois mots sans point-virgule sont refusés (ambiguïté)',
        $liste['lignes'][4]['erreur'] !== ''
    );

    $tropLongue = compte_analyser_liste(implode("\n", array_fill(0, 105, 'Martin;Élise')));
    verifier(
        'liste : au-delà de 100 lignes, l’envoi est refusé',
        count($tropLongue['lignes']) <= 100 && $tropLongue['erreurs'] !== [],
        count($tropLongue['lignes']) . ' ligne(s), erreurs : ' . json_encode($tropLongue['erreurs'])
    );

    // 3. Tout ce qui touche à la base, dans une transaction annulée.
    $basePrete = (int) db_value(
        "SELECT COUNT(*) FROM pragma_table_info('accounts') WHERE name = 'account_type'",
        [],
        0
    ) === 1;

    if (!$basePrete) {
        echo "  (migration 008 non appliquée : contrôles de comptes ignorés)\n";
    } else {
        $resultats = null;
        $avant     = [
            'comptes'  => (int) db_value("SELECT COUNT(*) FROM accounts WHERE username LIKE 'elise.martin%' OR username IN ('paul.durand', 'ZZTEST')", [], 0),
            'branches' => (int) db_value("SELECT COUNT(*) FROM branches WHERE code LIKE 'ZZ%'", [], 0),
        ];

        try {
            db_transaction(static function () use (&$resultats): void {
                $now = time();

                // On part d'une table propre : les lignes d'un essai précédent
                // (ou d'une base de démonstration) sont retirées, et reviendront
                // avec l'annulation de la transaction.
                db_exec("DELETE FROM accounts WHERE username LIKE 'elise.martin%' OR username IN ('paul.durand', 'ZZTEST')");
                db_exec("DELETE FROM branches WHERE code LIKE 'ZZ%'");

                // Deux branches d'essai : une simple, une qui « voit tout ».
                $brancheA = db_insert('branches', [
                    'code' => 'ZZTEST', 'name' => 'Branche d’essai', 'description' => null,
                    'see_all' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $brancheB = db_insert('branches', [
                    'code' => 'ZZAUTRE', 'name' => 'Autre branche d’essai', 'description' => null,
                    'see_all' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);

                // 3 a. Création d'une personne : mot de passe généré, haché.
                $creation = comptes_creer([[
                    'nom'         => 'Martin',
                    'prenom'      => 'Élise',
                    'identifiant' => 'elise.martin',
                    'branche'     => $brancheA,
                    'see_all'     => false,
                ]], 0);

                $compte   = $creation['crees'][0] ?? null;
                if ($compte === null) {
                    throw new RuntimeException('création impossible : ' . implode(' / ', $creation['erreurs']));
                }
                $motDePasse = (string) ($compte['mot_de_passe'] ?? '');
                $ligne    = $compte !== null ? db_one('SELECT * FROM accounts WHERE id = ?', [(int) $compte['id']]) : null;

                // 3 b. Un second compte du même nom : l'identifiant reçoit « 2 ».
                $libre = compte_identifiant_libre(compte_identifiant_base('Élise', 'Martin'));

                // 3 c. Doublon nom + prénom + branche.
                $doublon = compte_doublon('Martin', 'Élise', $brancheA);
                $pasDoublonAutreBranche = compte_doublon('Martin', 'Élise', $brancheB);

                // 3 d. Droits du compte personnel, branché sur la session.
                $_SESSION['account_id'] = (int) ($compte['id'] ?? 0);
                $droits = [
                    'branches'   => accessible_branch_ids(),
                    'public'     => can_view('public'),
                    'famille'    => can_view('famille'),
                    'sienne'     => can_view('branches', [$brancheA]),
                    'autre'      => can_view('branches', [$brancheB]),
                    'prive'      => can_view('prive'),
                    'admin'      => is_admin(),
                    'voit_tout'  => can_see_all(),
                    'signature'  => tchat_signature([
                        'role'        => 'famille',
                        'account_type' => 'personne',
                        'first_name'  => 'Élise',
                        'username'    => 'elise.martin',
                        'branch_code' => 'ZZTEST',
                    ]),
                ];

                // 3 e. « Voit toutes les branches » propre à la personne.
                $creationLarge = comptes_creer([[
                    'nom'         => 'Durand',
                    'prenom'      => 'Paul',
                    'identifiant' => 'paul.durand',
                    'branche'     => $brancheB,
                    'see_all'     => true,
                ]], 0);
                $compteLarge = $creationLarge['crees'][0] ?? null;
                $_SESSION['account_id'] = (int) ($compteLarge['id'] ?? 0);
                $droitsLarge = [
                    'branches'  => accessible_branch_ids(),
                    'autre'     => can_view('branches', [$brancheA]),
                    'voit_tout' => can_see_all(),
                ];

                // 3 f. Un compte de branche garde son fonctionnement.
                $compteBranche = db_insert('accounts', [
                    'username' => 'ZZTEST', 'display_name' => 'Branche d’essai',
                    'password_hash' => password_make('MotDePasseEssai123'),
                    'role' => 'famille', 'branch_id' => $brancheA, 'is_active' => 1,
                    'account_type' => 'branche', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $_SESSION['account_id'] = $compteBranche;
                $_SESSION['tchat_prenom'] = 'Jean';
                $droitsBranche = [
                    'branches'  => accessible_branch_ids(),
                    'signature' => tchat_signature([
                        'role'         => 'famille',
                        'account_type' => 'branche',
                        'username'     => 'ZZTEST',
                        'branch_code'  => 'ZZTEST',
                    ]),
                    'sans_prenom' => (function (): string {
                        $ancien = $_SESSION['tchat_prenom'] ?? '';
                        unset($_SESSION['tchat_prenom']);
                        $vide = tchat_signature([
                            'role' => 'famille', 'account_type' => 'branche', 'branch_code' => 'ZZTEST',
                        ]);
                        $_SESSION['tchat_prenom'] = $ancien;
                        return $vide;
                    })(),
                ];

                // 3 g. Désactivation : connexion refusée, session coupée.
                db_exec('UPDATE accounts SET is_active = 0 WHERE id = ?', [(int) ($compte['id'] ?? 0)]);
                $_SESSION['account_id'] = (int) ($compte['id'] ?? 0);
                $desactive = [
                    'connexion' => account_can_login(db_one('SELECT a.*, b.is_active AS branch_active FROM accounts a LEFT JOIN branches b ON b.id = a.branch_id WHERE a.id = ?', [(int) ($compte['id'] ?? 0)])),
                    'session'   => current_account(),
                ];
                db_exec('UPDATE accounts SET is_active = 1 WHERE id = ?', [(int) ($compte['id'] ?? 0)]);

                // 3 h. Réinitialisation : l'ancien ne marche plus, le nouveau oui.
                $nouveauMotDePasse = password_generate();
                db_update('accounts', (int) ($compte['id'] ?? 0), [
                    'password_hash' => password_make($nouveauMotDePasse),
                    'updated_at'    => time(),
                ]);
                $hachageApres = (string) db_value('SELECT password_hash FROM accounts WHERE id = ?', [(int) ($compte['id'] ?? 0)], '');
                $reinitialisation = [
                    'ancien'  => password_check($motDePasse, $hachageApres),
                    'nouveau' => password_check($nouveauMotDePasse, $hachageApres),
                ];

                // 3 i. Changement de mot de passe par la personne elle-même.
                $compteJour = db_one('SELECT * FROM accounts WHERE id = ?', [(int) ($compte['id'] ?? 0)]);
                $refusAncien = mot_de_passe_changer($compteJour, 'mauvais-mot-de-passe', 'UnePhraseSolide2026', 'UnePhraseSolide2026');
                $refusCourt  = mot_de_passe_changer($compteJour, $nouveauMotDePasse, 'court', 'court');
                $refusIdentifiant = mot_de_passe_changer($compteJour, $nouveauMotDePasse, 'elise.martin', 'elise.martin');
                $refusConfirmation = mot_de_passe_changer($compteJour, $nouveauMotDePasse, 'UnePhraseSolide2026', 'AutrePhraseSolide2026');
                $accepte = mot_de_passe_changer($compteJour, $nouveauMotDePasse, 'UnePhraseSolide2026', 'UnePhraseSolide2026');
                $hachageFinal = (string) db_value('SELECT password_hash FROM accounts WHERE id = ?', [(int) ($compte['id'] ?? 0)], '');

                $resultats = [
                    'brancheA'        => $brancheA,
                    'brancheB'        => $brancheB,
                    'compte'          => $compte,
                    'ligne'           => $ligne,
                    'motDePasse'      => $motDePasse,
                    'libre'           => $libre,
                    'doublon'         => $doublon,
                    'pasDoublonAutre' => $pasDoublonAutreBranche,
                    'droits'          => $droits,
                    'droitsLarge'     => $droitsLarge,
                    'droitsBranche'   => $droitsBranche,
                    'desactive'       => $desactive,
                    'reinitialisation' => $reinitialisation,
                    'refusAncien'     => $refusAncien,
                    'refusCourt'      => $refusCourt,
                    'refusIdentifiant' => $refusIdentifiant,
                    'refusConfirmation' => $refusConfirmation,
                    'accepte'         => $accepte,
                    'motDePasseChange' => password_check('UnePhraseSolide2026', $hachageFinal),
                    'rechercheMaj'    => find_account_by_username('ELISE.MARTIN'),
                ];

                // On annule tout : la base doit rester intacte.
                throw new RuntimeException('fin du test, annulation');
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'fin du test, annulation') {
                throw $e;
            }
        }

        // La session d'essai ne doit pas rester accrochée à un compte annulé.
        unset($_SESSION['account_id'], $_SESSION['tchat_prenom']);

        $ok = static fn (mixed $valeur): bool => is_array($resultats);

        verifier(
            'création : le compte est bien enregistré, avec son nom et son prénom',
            $ok($resultats) && $resultats['ligne'] !== null
                && $resultats['ligne']['account_type'] === 'personne'
                && $resultats['ligne']['first_name'] === 'Élise'
                && $resultats['ligne']['last_name'] === 'Martin',
            $ok($resultats) ? json_encode($resultats['ligne'], JSON_UNESCAPED_UNICODE) : 'aucun résultat'
        );
        verifier(
            'création : le nom affiché est le prénom (« Bonjour Élise »)',
            $ok($resultats) && ($resultats['ligne']['display_name'] ?? '') === 'Élise'
        );
        verifier(
            'création : seul le hachage est stocké, le mot de passe n’apparaît nulle part en clair',
            $ok($resultats)
                && $resultats['motDePasse'] !== ''
                && ($resultats['ligne']['password_hash'] ?? '') !== $resultats['motDePasse']
                && str_starts_with((string) ($resultats['ligne']['password_hash'] ?? ''), '$')
                && password_check($resultats['motDePasse'], (string) ($resultats['ligne']['password_hash'] ?? ''))
                && (int) db_value('SELECT COUNT(*) FROM accounts WHERE password_hash LIKE ?', ['%' . $resultats['motDePasse'] . '%'], 0) === 0,
            'hachage : ' . substr((string) ($resultats['ligne']['password_hash'] ?? ''), 0, 12) . '…'
        );
        verifier(
            'identifiant : une personne déjà prise reçoit le numéro suivant (elise.martin2)',
            $ok($resultats) && $resultats['libre'] === 'elise.martin2',
            $ok($resultats) ? $resultats['libre'] : ''
        );
        verifier(
            'doublon : même nom, même prénom et même branche signalés',
            $ok($resultats) && is_array($resultats['doublon'])
        );
        verifier(
            'doublon : un homonyme d’une autre branche n’est pas un doublon',
            $ok($resultats) && $resultats['pasDoublonAutre'] === null
        );
        verifier(
            'droits : la personne ne voit que les branches auxquelles elle a droit',
            $ok($resultats) && $resultats['droits']['branches'] === [$resultats['brancheA']],
            $ok($resultats) ? json_encode($resultats['droits']['branches']) : ''
        );
        verifier(
            'droits : public, familles et sa branche sont visibles ; les autres branches et le privé non',
            $ok($resultats)
                && $resultats['droits']['public'] === true
                && $resultats['droits']['famille'] === true
                && $resultats['droits']['sienne'] === true
                && $resultats['droits']['autre'] === false
                && $resultats['droits']['prive'] === false,
            $ok($resultats) ? json_encode($resultats['droits']) : ''
        );
        verifier(
            'droits : un compte personnel n’est pas administrateur',
            $ok($resultats) && $resultats['droits']['admin'] === false
        );
        verifier(
            '« voit toutes les branches » : la personne voit alors les autres branches',
            $ok($resultats)
                && $resultats['droitsLarge']['voit_tout'] === true
                && $resultats['droitsLarge']['autre'] === true
                && count($resultats['droitsLarge']['branches']) > 1,
            $ok($resultats) ? json_encode($resultats['droitsLarge']) : ''
        );
        verifier(
            'compte de branche : il continue de voir les autres branches s’il en a le droit',
            $ok($resultats) && is_array($resultats['droitsBranche']['branches'])
        );
        verifier(
            'tchat : une personne signe automatiquement « BRANCHE/Prénom »',
            $ok($resultats) && $resultats['droits']['signature'] === 'ZZTEST/Élise',
            $ok($resultats) ? $resultats['droits']['signature'] : ''
        );
        verifier(
            'tchat : un compte de branche demande toujours son prénom',
            $ok($resultats)
                && $resultats['droitsBranche']['signature'] === 'ZZTEST/Jean'
                && $resultats['droitsBranche']['sans_prenom'] === '',
            $ok($resultats) ? json_encode($resultats['droitsBranche']) : ''
        );
        verifier(
            'connexion : l’identifiant est retrouvé sans tenir compte des majuscules',
            $ok($resultats)
                && ($resultats['rechercheMaj']['id'] ?? 0) === ($resultats['compte']['id'] ?? -1),
            $ok($resultats) ? json_encode($resultats['rechercheMaj']['username'] ?? null) : ''
        );
        verifier(
            'désactivation : le compte ne peut plus se connecter',
            $ok($resultats) && $resultats['desactive']['connexion'] === false
        );
        verifier(
            'désactivation : la session en cours est coupée à la requête suivante',
            $ok($resultats) && $resultats['desactive']['session'] === null
        );
        verifier(
            'réinitialisation : l’ancien mot de passe ne fonctionne plus, le nouveau oui',
            $ok($resultats)
                && $resultats['reinitialisation']['ancien'] === false
                && $resultats['reinitialisation']['nouveau'] === true,
            $ok($resultats) ? json_encode($resultats['reinitialisation']) : ''
        );
        verifier(
            'changement de mot de passe : l’ancien mot de passe incorrect est refusé',
            $ok($resultats) && $resultats['refusAncien'] !== []
        );
        verifier(
            'changement de mot de passe : moins de 10 caractères est refusé',
            $ok($resultats) && $resultats['refusCourt'] !== []
        );
        verifier(
            'changement de mot de passe : identique à l’identifiant refusé',
            $ok($resultats) && $resultats['refusIdentifiant'] !== []
        );
        verifier(
            'changement de mot de passe : confirmation différente refusée',
            $ok($resultats) && $resultats['refusConfirmation'] !== []
        );
        verifier(
            'changement de mot de passe : accepté quand tout est correct',
            $ok($resultats) && $resultats['accepte'] === [] && $resultats['motDePasseChange'] === true,
            $ok($resultats) ? json_encode($resultats['accepte']) : ''
        );
        verifier(
            'les essais de comptes ne laissent aucune trace',
            (int) db_value("SELECT COUNT(*) FROM accounts WHERE username LIKE 'elise.martin%' OR username IN ('paul.durand', 'ZZTEST')", [], 0) === $avant['comptes']
                && (int) db_value("SELECT COUNT(*) FROM branches WHERE code LIKE 'ZZ%'", [], 0) === $avant['branches']
        );
    }

    // 4. CSRF : un jeton absent, vide ou faux est refusé.
    verifier('CSRF : le jeton de la session est accepté', csrf_valide(csrf_token()));
    verifier(
        'CSRF : un jeton absent, faux ou d’un autre type est refusé',
        !csrf_valide('') && !csrf_valide(null) && !csrf_valide('faux-jeton') && !csrf_valide(['x'])
    );

    // 5. Les pages d'administration des comptes exigent un administrateur.
    $codeControleur = (string) file_get_contents(dirname(__DIR__) . '/controllers/admin_comptes.php');
    $sansGarde = [];
    foreach (preg_split('/\nfunction\s+/', $codeControleur) ?: [] as $index => $morceau) {
        if ($index === 0 || preg_match('/^admin_compte\w*\s*\(/', $morceau) !== 1) {
            continue;
        }
        if (!str_contains($morceau, 'require_admin();')) {
            $sansGarde[] = strtok($morceau, '(');
        }
    }
    verifier(
        'accès : chaque page d’administration des comptes commence par require_admin()',
        $sansGarde === [],
        'sans garde : ' . implode(', ', $sansGarde)
    );

    $codePost = $codeControleur;
    verifier(
        'accès : chaque action qui modifie un compte vérifie le jeton CSRF',
        substr_count($codePost, 'csrf_check();') >= 6,
        'csrf_check() trouvé ' . substr_count($codePost, 'csrf_check();') . ' fois'
    );
}

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
