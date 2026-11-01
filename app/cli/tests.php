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
