<?php
/**
 * Donne une image d'aperçu aux vidéos qui n'en ont pas (1.32.0).
 *
 * Utilisation :
 *
 *     php app/cli/miniatures-videos.php             (liste ce qui manque)
 *     php app/cli/miniatures-videos.php --executer  (traite UNE vidéo)
 *     php app/cli/miniatures-videos.php --lot=3     (jusqu'à trois vidéos)
 *
 * La commande est RELANÇABLE sans risque : une vidéo qui a déjà une image est
 * ignorée ; ffmpeg tourne en priorité basse (comme la conversion) et un seul
 * fichier est traité à la fois, pour que le Raspberry Pi reste utilisable.
 *
 * Sans ffmpeg (ou si ffmpeg échoue), rien n'est cassé : la fiche garde son
 * icône de lecture, et la page « Conversions » de l'administration le dit.
 *
 * Code de sortie : 0 = tout s'est bien passé, 1 = au moins une vidéo a échoué
 * ou les outils manquent.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

$lot      = 0;
$executer = false;
$arguments = array_slice($argv ?? ($_SERVER['argv'] ?? []), 1);
foreach ($arguments as $argument) {
    if ($argument === '--executer') {
        $executer = true;
    } elseif (preg_match('/^--lot=(\d+)$/', (string) $argument, $m) === 1) {
        $executer = true;
        $lot      = max(1, min(50, (int) $m[1]));
    } elseif ($argument === '--aide' || $argument === '-h') {
        echo "Utilisation : php app/cli/miniatures-videos.php [--executer] [--lot=N]\n";
        exit(0);
    }
}
if ($executer && $lot === 0) {
    $lot = 1;
}

echo 'Miniatures des vidéos — ', date('d/m/Y à H:i'), "\n";
echo "----------------------------------------\n\n";

$outils = conversion_outils();
if ($outils['ffmpeg'] === null) {
    cli_err("ffmpeg est introuvable : aucune image ne peut être extraite.\n");
    cli_err("Les vidéos restent lisibles, avec une icône de lecture à la place de l'image.\n");
    cli_err("Installez-le sur le Raspberry Pi :  sudo apt-get install -y ffmpeg\n");
    exit(1);
}

$aFaire = video_miniature_a_faire(500);

if ($aFaire === []) {
    echo "Toutes les vidéos prêtes ont une image d'aperçu. Rien à faire.\n";
    exit(0);
}

echo count($aFaire), " vidéo(s) sans image d'aperçu.\n\n";

if (!$executer) {
    foreach (array_slice($aFaire, 0, 30) as $video) {
        echo '  · #', (int) $video['id'], '  ', (string) $video['title'],
            '  (', (string) $video['original_name'], ', ', format_size((int) $video['size']), ")\n";
    }
    if (count($aFaire) > 30) {
        echo '  … et ', count($aFaire) - 30, " autre(s).\n";
    }
    echo "\nPour les traiter : php app/cli/miniatures-videos.php --executer\n";
    exit(0);
}

$faits = 0;
$echecs = 0;
foreach (array_slice($aFaire, 0, $lot) as $video) {
    echo '· #', (int) $video['id'], '  ', (string) $video['title'], " … ";
    $resultat = video_miniature_document((int) $video['id']);
    if ($resultat['ok']) {
        $faits++;
        echo "image créée\n";
        continue;
    }
    $echecs++;
    echo "échec\n  ! ", $resultat['error'], "\n";
}

echo "\nRésultat : ", $faits, " image(s) créée(s), ", $echecs, " échec(s).\n";
if (count($aFaire) > $lot) {
    echo count($aFaire) - $lot, " vidéo(s) restante(s) : relancez la commande.\n";
}

exit($echecs > 0 ? 1 : 0);
