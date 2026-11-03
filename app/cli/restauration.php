<?php
/**
 * Restauration des données à partir d'une sauvegarde.
 *
 * Utilisation :
 *
 *   # 1. Vérifier la dernière sauvegarde (ne modifie rien)
 *   php app/cli/restauration.php /mnt/sauvegarde/histoiredefamille --verifier
 *
 *   # 2. Restaurer dans un dossier séparé (essai, rien n'est remplacé)
 *   php app/cli/restauration.php /mnt/sauvegarde/histoiredefamille
 *
 *   # 3. Remplacer réellement le site (le site doit être arrêté)
 *   php app/cli/restauration.php /mnt/sauvegarde/histoiredefamille --remplacer
 *
 * Règles de sécurité :
 *  - par défaut, la restauration se fait dans un dossier SÉPARÉ : le site en
 *    service n'est pas touché ;
 *  - avec --remplacer, l'ancienne base est d'abord copiée dans
 *    « avant-restauration/ » sur la clé, et les fichiers qui disparaîtraient
 *    sont déplacés, jamais supprimés ;
 *  - RIEN n'est remplacé sans que vous tapiez « OUI » en toutes lettres.
 *
 * Code de sortie : 0 = réussi, 1 = refus ou échec.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

$arguments = array_slice($argv ?? [], 1);
$source    = '';
$remplacer = false;
$verifier  = false;
$reponse   = '';
$dossierCible = '';

foreach ($arguments as $argument) {
    if ($argument === '--remplacer') {
        $remplacer = true;
    } elseif ($argument === '--verifier' || $argument === '--test') {
        $verifier = true;
    } elseif (str_starts_with($argument, '--reponse=')) {
        $reponse = substr($argument, strlen('--reponse='));
    } elseif (str_starts_with($argument, '--dossier=')) {
        $dossierCible = substr($argument, strlen('--dossier='));
    } elseif (!str_starts_with($argument, '--')) {
        $source = $argument;
    }
}

if ($source === '') {
    echo "Restauration de l’histoire de famille\n";
    echo "-------------------------------------\n\n";
    echo "Utilisation :\n";
    echo "  php app/cli/restauration.php <dossier-de-sauvegarde> [options]\n\n";
    echo "Exemples :\n";
    echo "  # vérifier la dernière sauvegarde (ne modifie rien)\n";
    echo "  php app/cli/restauration.php /mnt/sauvegarde/histoiredefamille --verifier\n\n";
    echo "  # restaurer dans un dossier séparé, pour regarder le résultat\n";
    echo "  php app/cli/restauration.php /mnt/sauvegarde/histoiredefamille\n\n";
    echo "  # remplacer le site (arrêtez d’abord Apache, puis tapez OUI)\n";
    echo "  sudo systemctl stop apache2\n";
    echo "  sudo -u www-data php app/cli/restauration.php /mnt/sauvegarde/histoiredefamille --remplacer\n\n";
    echo "Options :\n";
    echo "  --verifier        contrôle l’intégrité de la dernière sauvegarde\n";
    echo "  --remplacer       remplace la base et les fichiers du site\n";
    echo "  --dossier=CHEMIN  dossier séparé pour l’essai (facultatif)\n";
    echo "  --reponse=OUI     réponse à la confirmation (pour un envoi automatique)\n";
    exit(1);
}

$options = sauvegarde_options();

// ---------------------------------------------------------------------------
// 1. Contrôle en lecture seule
// ---------------------------------------------------------------------------
if ($verifier) {
    echo "Contrôle de la dernière sauvegarde\n";
    echo "----------------------------------\n";
    echo 'Dossier : ', $source, "\n\n";

    $resultat = restauration_verifier($options, $source);
    if (!$resultat['ok']) {
        cli_err($resultat['message'] . "\n");
        exit(1);
    }

    echo 'Archive : ', basename($resultat['archive']), "\n";
    echo "Intégrité SQLite : ok\n";
    foreach ($resultat['compteurs'] as $table => $nombre) {
        echo '  · ', $table, ' : ', $nombre, " ligne(s)\n";
    }
    echo 'Fichiers sauvegardés : ', $resultat['fichiers'], "\n\n";
    echo "Cette sauvegarde est lisible. Rien n’a été modifié.\n";
    echo "Date du contrôle notée dans l’administration (État du site).\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// 2. Restauration d'essai (par défaut)
// ---------------------------------------------------------------------------
if (!$remplacer) {
    $cible = $dossierCible !== ''
        ? rtrim($dossierCible, '/')
        : dirname((string) $options['storage_root']) . '/restauration-' . date('Y-m-d_Hi');

    echo "Restauration d’essai\n";
    echo "--------------------\n";
    echo 'Sauvegarde : ', $source, "\n";
    echo 'Dossier d’essai : ', $cible, "\n\n";

    $resultat = restauration_essai($source, $cible);
    if (!$resultat['ok']) {
        cli_err('ÉCHEC : ' . $resultat['message'] . "\n");
        echo "Rien n’a été modifié dans le site.\n";
        exit(1);
    }

    echo 'Archive utilisée : ', basename($resultat['archive']), "\n";
    foreach ($resultat['compteurs'] as $table => $nombre) {
        echo '  · ', $table, ' : ', $nombre, " ligne(s)\n";
    }
    echo 'Fichiers copiés : ', $resultat['fichiers'], "\n\n";
    echo "La sauvegarde a été restaurée dans :\n  ", $cible, "\n\n";
    echo "RIEN n’a été remplacé dans le site en service.\n";
    echo "Vérifiez le dossier (ou ouvrez LISEZ-MOI.txt), puis, si tout est bien là :\n\n";
    echo "  sudo systemctl stop apache2\n";
    echo "  sudo -u www-data php app/cli/restauration.php ", $source, " --remplacer\n\n";
    echo "L’ancienne base sera copiée dans « avant-restauration/ » sur la clé.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// 3. Remplacement réel : confirmation explicite obligatoire
// ---------------------------------------------------------------------------
echo "Remplacement du site par une sauvegarde\n";
echo "---------------------------------------\n\n";
echo 'Sauvegarde : ', $source, "\n";
echo 'Base du site : ', $options['database'], "\n";
echo 'Données du site : ', $options['storage_root'], "\n\n";

$archive = restauration_derniere_archive($source);
if ($archive === null) {
    cli_err('Aucune sauvegarde de base dans ' . $source . ". Rien n’a été remplacé.\n");
    exit(1);
}
echo 'Archive retenue : ', basename($archive), "\n\n";

echo "ATTENTION : cette opération remplace la base et les fichiers du site.\n";
echo "L’ancienne base sera copiée dans « avant-restauration/ » sur la clé,\n";
echo "et les fichiers qui disparaîtraient y seront déplacés.\n\n";
echo "Avant de continuer, le site doit être arrêté :\n";
echo "  sudo systemctl stop apache2\n\n";

if ($reponse === '') {
    echo 'Pour confirmer, tapez OUI puis Entrée (autre chose annule) : ';
    $saisie = fgets(STDIN);
    $reponse = $saisie === false ? '' : trim($saisie);
}

if (!restauration_confirmation_valide($reponse)) {
    echo "\nRestauration annulée : la confirmation « OUI » n’a pas été saisie.\n";
    echo "Le site n’a pas été modifié.\n";
    exit(1);
}

echo "\nRestauration en cours…\n";
$resultat = restauration_remplacer($options, $source);

if (!$resultat['ok']) {
    cli_err('ÉCHEC : ' . $resultat['message'] . "\n");
    if ($resultat['ancienne_base'] !== '') {
        echo 'Votre ancienne base est conservée : ', $resultat['ancienne_base'], "\n";
    }
    exit(1);
}

echo "Restauration terminée.\n\n";
foreach ($resultat['compteurs'] as $table => $nombre) {
    echo '  · ', $table, ' : ', $nombre, " ligne(s)\n";
}
echo 'Fichiers restaurés : ', $resultat['fichiers'], "\n";
echo 'Fichiers mis de côté : ', $resultat['mis_de_cote'], "\n";
if ($resultat['ancienne_base'] !== '') {
    echo 'Ancienne base conservée : ', $resultat['ancienne_base'], "\n";
}
echo "\nRedémarrez le site et vérifiez qu’il s’ouvre :\n";
echo "  sudo systemctl start apache2\n";
echo "  curl -I http://localhost/\n";
exit(0);
