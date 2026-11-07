<?php
/**
 * État de l'installation, lu par deploy/install.sh.
 *
 *   sudo -u www-data php app/cli/etat-installation.php
 *
 * Affiche deux lignes, faciles à lire dans un script shell :
 *
 *   courriels_actifs=0|1
 *   sauvegarde_sans_disque=0|1
 *
 * Elles disent seulement ce que l'installateur a besoin de savoir :
 *
 *   - « courriels_actifs=0 » (le cas normal depuis la 1.28.0) : le site
 *     n'envoie aucun courriel, l'installateur installe le minuteur d'envoi
 *     mais le laisse ARRÊTÉ — il tournerait toutes les cinq minutes pour rien ;
 *   - « sauvegarde_sans_disque=1 » : le mode « sans disque externe »
 *     (backup_allow_same_disk) est actif, la sauvegarde écrit sur la carte SD ;
 *     l'installateur le dit sans crier « ATTENTION ».
 *
 * Code de sortie : 0 si la base est lisible, 1 sinon (l'installateur traite
 * alors les deux valeurs comme « non »).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

if (!db_is_installed()) {
    cli_err("La base n'est pas encore initialisée : lancez php app/cli/migrate.php\n");
    exit(1);
}

$etat = installation_etat();

echo 'courriels_actifs=', $etat['courriels_actifs'] ? '1' : '0', "\n";
echo 'sauvegarde_sans_disque=', $etat['sauvegarde_sans_disque'] ? '1' : '0', "\n";

exit(0);
