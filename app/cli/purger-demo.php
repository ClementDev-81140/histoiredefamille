<?php
/**
 * Purge des données de démonstration, avant d'ouvrir le site à la famille.
 *
 * Utilisation :
 *
 *     php app/cli/purger-demo.php             (montre, demande OUI, puis purge)
 *     php app/cli/purger-demo.php --liste     (montre seulement, ne touche à rien)
 *     php app/cli/purger-demo.php --oui       (sans question, pour un script)
 *
 * Trois garanties, dans cet ordre :
 *   1. elle COMMENCE par montrer la liste exacte de ce qu'elle supprimerait
 *      (titres, personnes, messages…), et ne fait rien tant que l'on n'a pas
 *      tapé OUI ;
 *   2. elle fait une SAUVEGARDE juste avant (et s'arrête si la sauvegarde
 *      échoue : on ne supprime jamais sans filet) ;
 *   3. elle ne touche ni aux comptes ni aux contenus qui ne viennent pas de la
 *      démonstration : tout est repéré par le registre écrit par demo.php, ou,
 *      à défaut, par le marqueur « (démo) ».
 *
 * Code de sortie : 0 = rien à faire ou purge faite, 1 = purge refusée ou
 * sauvegarde impossible.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

if (!db_is_installed()) {
    cli_err("La base n'est pas encore initialisée. Rien à purger.\n");
    exit(1);
}

$arguments = array_slice($argv ?? ($_SERVER['argv'] ?? []), 1);
$listeSeule = in_array('--liste', $arguments, true);
$sansQuestion = in_array('--oui', $arguments, true);
$aide = in_array('--aide', $arguments, true) || in_array('-h', $arguments, true);

if ($aide) {
    echo "Utilisation : php app/cli/purger-demo.php [--liste] [--oui]\n\n";
    echo "  --liste   montre ce qui serait supprimé, sans rien supprimer\n";
    echo "  --oui     ne pose pas la question (pour un enchaînement de commandes)\n";
    exit(0);
}

echo "Histoire de famille — purge des données de démonstration\n";
echo "--------------------------------------------------------\n\n";

$inventaire = demo_inventaire();
$registre   = demo_registry_read();

if ($inventaire === []) {
    echo "Aucune donnée de démonstration connue.\n";
    $comptes = demo_comptes_restants();
    if ($comptes !== []) {
        echo "\nComptes portant le marqueur « (démo) » (jamais supprimés par cette commande) :\n";
        foreach ($comptes as $compte) {
            echo '  · ', $compte, "\n";
        }
        echo "\nDésactivez-les depuis Administration → Comptes si ce sont des comptes d'essai.\n";
    }
    exit(0);
}

$total = 0;
echo "Ce qui serait supprimé :\n\n";
foreach ($inventaire as $element) {
    $nombre = count($element['ids']);
    $total += $nombre;
    printf("  %-3d %s   [%s]\n", $nombre, $element['libelle'], $element['source']);
    foreach ($element['exemples'] as $exemple) {
        echo '      · ', $exemple, "\n";
    }
    if ($nombre > count($element['exemples'])) {
        echo '      · … et ', $nombre - count($element['exemples']) > 0
            ? $nombre - count($element['exemples']) . " autre(s)\n"
            : "\n";
    }
}

echo "\nSoit ", $total, " ligne(s) au total.\n";

$comptes = demo_comptes_restants();
if ($comptes !== []) {
    echo "\nComptes de démonstration (NON supprimés — à désactiver à la main) :\n";
    foreach ($comptes as $compte) {
        echo '  · ', $compte, "\n";
    }
}

if ($registre['date'] > 0) {
    echo "\nRegistre du jeu de démonstration : ", date('d/m/Y à H:i', (int) $registre['date']), ".\n";
} else {
    echo "\nAucun registre : les lignes reconnues le sont par leur marqueur « (démo) ».\n";
    echo "Certaines fiches anciennes (personnes, couples) peuvent ne pas être reconnues :\n";
    echo "vérifiez l’arbre généalogique après la purge.\n";
}

echo "\nLes fichiers (photos, vidéos, PDF) des documents supprimés sont effacés\n";
echo "s’ils ne servent plus à aucune autre fiche. Une sauvegarde est faite AVANT.\n";

if ($listeSeule) {
    echo "\nMode « --liste » : rien n’a été supprimé.\n";
    exit(0);
}

if (!$sansQuestion) {
    echo "\nTapez OUI (en majuscules) pour confirmer la suppression : ";
    $reponse = trim((string) fgets(STDIN));
    if ($reponse !== 'OUI') {
        echo "\nAnnulé : rien n’a été supprimé.\n";
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Sauvegarde juste avant
// ---------------------------------------------------------------------------
echo "\nSauvegarde avant suppression…\n";
$sauvegarde = sauvegarde_executer(sauvegarde_options(), static function (string $ligne): void {
    echo '  ', $ligne, "\n";
});

if (!($sauvegarde['ok'] ?? false)) {
    cli_err("\nLa sauvegarde a échoué : " . (string) ($sauvegarde['error'] ?? 'raison inconnue'));
    cli_err("\nRien n’a été supprimé. Corrigez la sauvegarde (voir Administration → État du site), puis relancez.\n");
    exit(1);
}
echo "  sauvegarde faite : ", (string) ($sauvegarde['dossier'] ?? ''), "\n";

// ---------------------------------------------------------------------------
// Suppression
// ---------------------------------------------------------------------------
echo "\nSuppression…\n";
$resultat = demo_purger($inventaire);
$efface = 0;
foreach ($resultat as $table => $nombre) {
    if ($nombre > 0) {
        echo '  · ', $nombre, ' ', $table, "\n";
        $efface += $nombre;
    }
}

// Le registre ne décrit plus rien : on l'efface (fichier ET copie en base)
// pour ne pas laisser croire qu'il reste des données de démonstration.
demo_registry_oublier();

log_activity(null, 'purge_demo', $efface . ' ligne(s) de démonstration supprimée(s)');

echo "\nTerminé : ", $efface, " ligne(s) supprimée(s).\n";
if ($comptes !== []) {
    echo "Rappel : ", count($comptes), " compte(s) de démonstration restent à désactiver à la main\n";
    echo "(Administration → Comptes, ou « Réinitialiser le mot de passe » pour les réutiliser).\n";
}
echo "\nRelancez ensuite : php app/cli/verifier-mise-en-ligne.php\n";
exit(0);
