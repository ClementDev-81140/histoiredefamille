<?php
/**
 * Envoi des courriels en attente.
 *
 * Utilisation :
 *
 *     php app/cli/envoyer-courriels.php
 *
 * Cette commande est lancée par le minuteur systemd toutes les 5 minutes
 * (deploy/systemd/histoiredefamille-courriels.timer). Elle peut aussi être
 * lancée à la main, sans risque : elle ne renvoie jamais un message déjà
 * envoyé.
 *
 * Ce qu'elle fait, dans l'ordre :
 *   1. met en file les notifications des articles programmés dont l'heure est
 *      arrivée (jamais au moment de la création de l'article) ;
 *   2. envoie au maximum 10 messages par passage, avec une pause d'une seconde
 *      entre deux envois (le Raspberry Pi reste réactif) ;
 *   3. respecte le plafond quotidien réglé dans l'administration (50 par
 *      défaut) ;
 *   4. réessaie plus tard un envoi raté (3 essais au total, puis abandon) ;
 *   5. purge les messages terminés de plus de 90 jours.
 *
 * Aucun envoi ne se fait pendant une visite du site : le navigateur ne fait
 * que remplir la file.
 *
 * Code de sortie : 0 = tout s'est bien passé (même s'il n'y avait rien à
 * envoyer), 1 = au moins un envoi a échoué.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

$lot    = 10;
$pause  = 1;      // secondes entre deux envois
$essais = 3;      // tentatives avant abandon

echo "Envoi des courriels — ", date('d/m/Y à H:i'), "\n";
echo "--------------------------------\n\n";

// Tout le travail se fait dans courriel_traiter_file() : cette commande ne
// fait que choisir les réglages et rapporter ce qui a été fait.
$resultat = courriel_traiter_file(
    ['lot' => $lot, 'pause' => $pause, 'essais' => $essais, 'purger' => 90],
    static function (string $ligne): void {
        echo $ligne, "\n";
    }
);

if ($resultat['arret'] === '') {
    echo "\nRésultat : ", $resultat['envoyes'], " envoyé(s), ", $resultat['echecs'], " en échec.\n";
    echo 'Plafond : ', $resultat['aujourdhui'] + $resultat['envoyes'], '/',
        $resultat['plafond'], " aujourd’hui.\n";
}

echo "\nDétail dans le journal : journalctl -u histoiredefamille-courriels.service -n 50\n";

exit($resultat['echecs'] > 0 ? 1 : 0);
