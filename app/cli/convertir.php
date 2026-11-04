<?php
/**
 * Convertit les vidéos en MP4 (H.264 + AAC), une seule à la fois.
 *
 * Utilisation :
 *
 *     php app/cli/convertir.php            (un seul fichier, puis s'arrête)
 *     php app/cli/convertir.php --lot=3     (jusqu'à trois fichiers)
 *     php app/cli/convertir.php --etat      (montre la file, ne convertit rien)
 *
 * Cette commande est lancée par le minuteur systemd toutes les 10 minutes
 * (deploy/systemd/histoiredefamille-conversion.timer). Elle peut aussi être
 * lancée à la main, sans risque : la prise d'un travail est atomique, deux
 * exécutions simultanées ne convertissent jamais le même fichier.
 *
 * Ce qu'elle fait, dans l'ordre :
 *   1. vérifie que ffmpeg et ffprobe sont là (sinon elle le dit et s'arrête) ;
 *   2. prend le travail le plus ancien de la file ;
 *   3. lit la durée du fichier, puis lance ffmpeg en notant l'avancement ;
 *   4. à la fin, range le fichier converti et met la fiche à jour (une seule
 *      transaction) : la famille ne voit jamais un fichier à moitié écrit ;
 *   5. en cas d'échec, garde l'original, note la raison, et réessaie plus tard
 *      (trois essais au total).
 *
 * Pendant tout le travail, la commande relit l'état du travail : si
 * l'administrateur clique sur « Annuler », ffmpeg est arrêté au palier suivant
 * et le fichier temporaire est effacé.
 *
 * Code de sortie : 0 = tout s'est bien passé (même s'il n'y avait rien à
 * faire), 1 = un fichier n'a pas pu être converti ou les outils manquent.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

$lot   = 1;
$etat  = false;
// $argv n'existe pas toujours (register_argc_argv peut être désactivé) : on
// lit les arguments là où ils se trouvent, sans jamais échouer.
$arguments = array_slice($argv ?? ($_SERVER['argv'] ?? []), 1);
foreach ($arguments as $argument) {
    if (preg_match('/^--lot=(\d+)$/', (string) $argument, $m) === 1) {
        $lot = max(1, min(20, (int) $m[1]));
    } elseif ($argument === '--etat') {
        $etat = true;
    } elseif ($argument === '--aide' || $argument === '-h') {
        echo "Utilisation : php app/cli/convertir.php [--lot=N] [--etat]\n";
        exit(0);
    }
}

echo 'Conversion des vidéos — ', date('d/m/Y à H:i'), "\n";
echo "--------------------------------------\n\n";

$resume = conversion_resume();
echo 'File : ', $resume['attente'], ' en attente, ', $resume['en_cours'], ' en cours, ',
    $resume['echec'], " en échec.\n";

if ($etat) {
    $lignes = conversion_file(50);
    if ($lignes === []) {
        echo "Aucun travail dans la file.\n";
        exit(0);
    }
    echo "\n";
    foreach ($lignes as $ligne) {
        echo '  · [', conversion_libelle_etat((string) $ligne['state']), '] ',
            (string) $ligne['title'], ' — ', (string) $ligne['original_name'],
            ' (', format_size((int) $ligne['source_size']), ')',
            (int) $ligne['attempts'] > 0 ? ' — ' . (int) $ligne['attempts'] . ' essai(s)' : '',
            "\n";
        if ((string) $ligne['message'] !== '') {
            echo '      ', (string) $ligne['message'], "\n";
        }
    }
    exit(0);
}

$reglages = conversion_reglages();

if (!$reglages['active']) {
    echo "La conversion est désactivée dans Administration → Conversions.\n";
    echo "Rien à faire : les fichiers envoyés restent tels quels.\n";
    exit(0);
}

if (!$reglages['disponible']) {
    cli_err("ffmpeg ou ffprobe est introuvable.\n");
    cli_err("Installez-le sur le Raspberry Pi :  sudo apt-get install -y ffmpeg\n");
    cli_err("Puis relancez cette commande (ou attendez le prochain passage du minuteur).\n");
    exit(1);
}

if (!function_exists('proc_open')) {
    cli_err("La fonction proc_open est désactivée dans PHP : impossible de lancer ffmpeg.\n");
    exit(1);
}

$faits   = 0;
$echecs  = 0;

for ($i = 0; $i < $lot; $i++) {
    $travail = conversion_prendre();
    if ($travail === null) {
        if ($i === 0) {
            echo "Aucun fichier à convertir.\n";
        }
        break;
    }

    $resultat = conversion_executer($travail, $reglages);
    if ($resultat === true) {
        $faits++;
    } elseif ($resultat === false) {
        $echecs++;
    } else {
        // Annulé pendant le travail : ce n'est pas un échec.
        break;
    }
}

echo "\nRésultat : ", $faits, " converti(s), ", $echecs, " en échec.\n";
echo "Détail dans le journal : journalctl -u histoiredefamille-conversion.service -n 50\n";

exit($echecs > 0 ? 1 : 0);

/**
 * Convertit un travail.
 *
 * @return bool|null true = réussi, false = échec, null = annulé
 */
function conversion_executer(array $travail, array $reglages): ?bool
{
    $id  = (int) $travail['id'];
    $doc = db_one('SELECT * FROM documents WHERE id = ?', [(int) $travail['document_id']]);

    echo "\n· ", (string) ($doc['title'] ?? 'Fiche supprimée'), ' — ', (string) $travail['original_name'],
        ' (', format_size((int) $travail['source_size']), ")\n";

    if ($doc === null) {
        conversion_echouer($id, 'La fiche a été supprimée : rien à convertir.');
        echo "  ! fiche supprimée\n";
        return false;
    }

    $source = storage_absolute((string) $doc['rel_path']);
    if (!is_file($source)) {
        conversion_echouer($id, 'Le fichier d’origine est introuvable sur le disque.');
        echo "  ! fichier introuvable\n";
        return false;
    }

    $duree = conversion_duree($source, $reglages['ffprobe']);
    $audio = conversion_est_audio($doc);
    if ($duree !== null) {
        echo '  durée : ', conversion_duree_lisible($duree), "\n";
    }

    $dossierTmp = storage_path((string) config('storage.tmp', 'tmp'));
    $temporaire = $dossierTmp . '/conversion-' . $id . '-' . bin2hex(random_bytes(4))
        . ($audio ? '.m4a' : '.mp4');
    $journal    = $dossierTmp . '/conversion-' . $id . '-' . bin2hex(random_bytes(4)) . '.log';

    $commande = $audio
        ? conversion_commande_audio($source, $temporaire, $reglages)
        : conversion_commande($source, $temporaire, $reglages);

    $descripteurs = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $journal, 'w'],
    ];

    $processus = @proc_open($commande, $descripteurs, $tuyaux, null, null);
    if (!is_resource($processus)) {
        conversion_echouer($id, 'Impossible de lancer ffmpeg (droits ou mémoire insuffisants ?).');
        echo "  ! ffmpeg n’a pas pu démarrer\n";
        return false;
    }

    $annule = false;
    $dernier = 0;

    if (isset($tuyaux[1]) && is_resource($tuyaux[1])) {
        stream_set_blocking($tuyaux[1], false);
        while (!feof($tuyaux[1])) {
            $ligne = fgets($tuyaux[1]);
            if ($ligne === false || $ligne === '') {
                // Rien à lire pour l'instant : on en profite pour regarder
                // l'état du travail et le temps écoulé.
                $statut = proc_get_status($processus);
                if (!$statut['running']) {
                    break;
                }
                $paliers = conversion_palier($id, $temporaire, $duree, $dernier);
                if ($paliers === null) {
                    $annule = true;
                    @proc_terminate($processus, 9);
                    break;
                }
                $dernier = $paliers;
                usleep(500000);
                continue;
            }

            $pourcent = conversion_lire_progression((string) $ligne, $duree);
            if ($pourcent !== null && $pourcent > $dernier) {
                $dernier = $pourcent;
                conversion_progresser($id, $pourcent);
            }

            if (conversion_abandonnee($id)) {
                $annule = true;
                @proc_terminate($processus, 9);
                break;
            }
        }
        @fclose($tuyaux[1]);
    }

    $code = proc_close($processus);
    $sortieErreur = @file_get_contents($journal) ?: '';
    @unlink($journal);

    if ($annule) {
        @unlink($temporaire);
        echo "  · conversion annulée, fichier temporaire effacé\n";
        return null;
    }

    if ($code !== 0 || !is_file($temporaire)) {
        @unlink($temporaire);
        $raison = conversion_lire_erreur($sortieErreur, $code);
        conversion_echouer($id, $raison);
        echo '  ! échec : ', $raison, "\n";
        return false;
    }

    $reussite = conversion_reussir($id, $temporaire, ['duration' => $duree ?? 0]);
    if (!$reussite['ok']) {
        @unlink($temporaire);
        conversion_echouer($id, $reussite['error']);
        echo '  ! ', $reussite['error'], "\n";
        return false;
    }

    $apres = conversion_par_id($id);
    $taille = (int) @filesize(storage_absolute((string) ($apres['target_path'] ?? '')));
    echo '  ok : converti en MP4 (', format_size($taille), ")\n";
    return true;
}



/** Durée du fichier en secondes (ffprobe), ou null si on ne sait pas la lire. */
function conversion_duree(string $source, string $ffprobe): ?int
{
    $commande = [
        $ffprobe, '-v', 'error',
        '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1',
        $source,
    ];
    $sortie = conversion_lancer_court($commande);
    if ($sortie === null) {
        return null;
    }
    $valeur = trim($sortie);
    if ($valeur === '' || !is_numeric($valeur)) {
        return null;
    }
    $secondes = (int) round((float) $valeur);
    return $secondes > 0 ? $secondes : null;
}

/** Lance une commande courte et rend sa sortie standard (null si impossible). */
function conversion_lancer_court(array $commande): ?string
{
    $descripteurs = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']];
    $processus = @proc_open($commande, $descripteurs, $tuyaux);
    if (!is_resource($processus)) {
        return null;
    }
    $sortie = '';
    if (isset($tuyaux[1]) && is_resource($tuyaux[1])) {
        $sortie = (string) stream_get_contents($tuyaux[1]);
        @fclose($tuyaux[1]);
    }
    proc_close($processus);
    return $sortie;
}


/**
 * Palier d'avancement quand ffmpeg n'écrit rien (fichier très lent) : on se
 * sert de la taille du fichier temporaire, et on vérifie si le travail a été
 * annulé. Rend le pourcentage, ou null si le travail doit s'arrêter.
 */
function conversion_palier(int $id, string $temporaire, ?int $duree, int $dernier): ?int
{
    if (conversion_abandonnee($id)) {
        return null;
    }

    if ($duree === null || $duree <= 0 || !is_file($temporaire)) {
        return $dernier;
    }

    // Le MP4 grossit à peu près proportionnellement au temps converti ; on
    // reste sous 95 % pour ne pas annoncer « terminé » avant la fin réelle.
    $taille = (int) @filesize($temporaire);
    if ($taille <= 0) {
        return $dernier;
    }
    $cible = (int) db_value('SELECT source_size FROM conversions WHERE id = ?', [$id], 0);
    if ($cible <= 0) {
        return $dernier;
    }
    $estime = (int) min(95, floor($taille * 100 / max(1, $cible)));
    $valeur = max($dernier, $estime);
    conversion_progresser($id, $valeur);
    return $valeur;
}


