<?php
/**
 * Sauvegarde de l'histoire de famille (base + fichiers) vers la clé USB.
 *
 * Utilisation :
 *
 *     php app/cli/sauvegarde.php
 *
 * C'est la seule commande à retenir. Elle est lancée automatiquement tous les
 * jours à 3 h 30 par le minuteur systemd (voir deploy/systemd/), et peut être
 * relancée à la main à tout moment : la sauvegarde est incrémentale, elle ne
 * recopie que ce qui a changé.
 *
 * Code de sortie :
 *   0 = sauvegarde réussie ;
 *   1 = échec (base non copiée, fichiers incomplets, clé absente…).
 *
 * Rien n'est jamais écrit si la clé USB n'est pas montée : le message le dit
 * clairement et la carte SD reste intacte.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

$options = sauvegarde_options();

echo "Sauvegarde de l’histoire de famille\n";
echo "-----------------------------------\n";
echo 'Clé de sauvegarde : ', $options['backup_dir'], "\n";
echo 'Données du site   : ', $options['storage_root'], "\n\n";

$resultat = sauvegarde_executer($options);

if ($resultat['annulee']) {
    cli_err($resultat['message'] . "\n");
    echo "\nAucune donnée n’a été modifiée. Branchez la clé USB, vérifiez avec :\n";
    echo "    df -h ", $options['backup_dir'], "\n";
    echo "puis relancez :\n";
    echo "    php app/cli/sauvegarde.php\n";
    exit(1);
}

$etat = $resultat['etat'];

echo "\nRésultat\n";
echo '  · Base : ', $resultat['base']['ok'] ? 'ok, ' . sauvegarde_taille((int) ($etat['base_size'] ?? 0)) . ' (intégrité vérifiée)' : 'ÉCHEC', "\n";
echo '  · Fichiers : ', (int) $resultat['fichiers']['copies'], ' copié(s), ',
    (int) $resultat['fichiers']['supprimes'], ' retiré(s), ',
    (int) $resultat['fichiers']['erreurs'], ' erreur(s)', "\n";
echo '  · Durée : ', $resultat['duree'], " s\n";
if (!empty($etat['free_backup'])) {
    echo '  · Espace libre sur la clé : ', sauvegarde_taille((int) $etat['free_backup']), "\n";
}
if (!empty($etat['free_data'])) {
    echo '  · Espace libre sur les données : ', sauvegarde_taille((int) $etat['free_data']), "\n";
}
echo "\n";

if (!$resultat['ok']) {
    cli_err("La sauvegarde est INCOMPLÈTE.\n");
    if ($resultat['base']['message'] !== '') {
        cli_err('  · ' . $resultat['base']['message'] . "\n");
    }
    if ($resultat['fichiers']['message'] !== '') {
        cli_err('  · ' . $resultat['fichiers']['message'] . "\n");
    }
    echo "\nDétail dans le journal :\n    journalctl -u histoiredefamille-sauvegarde.service -n 50\n";
    exit(1);
}

echo "Sauvegarde réussie.\n";
echo "Détail dans le journal : journalctl -u histoiredefamille-sauvegarde.service -n 50\n";
exit(0);
