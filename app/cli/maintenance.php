<?php
/**
 * Maintenance quotidienne (à lancer par cron, voir deploy/crontab.example).
 *
 *  - supprime les tentatives de connexion anciennes ;
 *  - efface les messages de tchat plus anciens que la durée de conservation ;
 *  - reconstruit l'index de recherche (FTS5) ;
 *  - nettoie les verrous de lecture vidéo périmés ;
 *  - supprime les fichiers temporaires de plus de 48 h ;
 *  - compacte légèrement la base (PRAGMA optimize + checkpoint WAL).
 *
 * Utilisation : php app/cli/maintenance.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

if (!db_is_installed()) {
    cli_err("La base n'est pas encore initialisée. Lancez d'abord :\n  php app/cli/migrate.php\n");
    exit(1);
}

echo "Maintenance — " . date('d/m/Y H:i') . "\n";

$attempts = login_purge_attempts(30);
echo "  · tentatives de connexion supprimées : {$attempts}\n";

// Tchat : messages plus anciens que la durée de conservation choisie dans
// Administration → Réglages (par défaut 1 an ; « jamais » = aucune suppression).
try {
    $mois = tchat_conservation_mois();
    if ($mois === 0) {
        echo "  · tchat : conservation illimitée, rien à supprimer\n";
    } else {
        echo "  · messages de tchat supprimés : " . tchat_purger() . "\n";
    }
} catch (Throwable $e) {
    cli_err("  ! purge du tchat impossible : " . $e->getMessage() . "\n");
}

// Journal des connexions : lignes plus anciennes que la durée choisie dans
// Administration → Journal des connexions (90 jours par défaut).
try {
    echo "  · lignes du journal des connexions supprimées : " . journal_purger() . "\n";
} catch (Throwable $e) {
    cli_err("  ! purge du journal impossible : " . $e->getMessage() . "\n");
}

stream_purge_stale_locks();
echo "  · lectures vidéo en cours : " . stream_active_count() . "\n";

// Envois abandonnés (morceaux d'un fichier jamais terminé) : plus de 48 h.
try {
    $purge = envoi_purge(48);
    echo "  · envois abandonnés supprimés : {$purge['supprimes']}"
        . ($purge['octets'] > 0 ? ' (' . format_size($purge['octets']) . ' libérés)' : '') . "\n";
} catch (Throwable $e) {
    cli_err("  ! purge des envois impossible : " . $e->getMessage() . "\n");
}

// Conversions : vieilles lignes terminées, et originaux libérés (sept jours
// après une conversion réussie, si le réglage le demande).
try {
    $vieux   = conversion_purger(90);
    $effaces = conversion_liberer_originaux(7);
    echo "  · conversions : {$vieux} ancienne(s) ligne(s) effacée(s), "
        . ($effaces > 0 ? "{$effaces} original(aux) libéré(s)" : 'aucun original à effacer') . "\n";
} catch (Throwable $e) {
    cli_err("  ! entretien des conversions impossible : " . $e->getMessage() . "\n");
}

// Fichiers temporaires (aperçus GEDCOM abandonnés, etc.).
$tmpDir = storage_path((string) config('storage.tmp', 'tmp'));
$removed = 0;
foreach (glob($tmpDir . '/*') ?: [] as $file) {
    if (is_file($file) && filemtime($file) < time() - 48 * 3600) {
        @unlink($file);
        $removed++;
    }
}
echo "  · fichiers temporaires supprimés : {$removed}\n";

// Recherche : l'index FTS est reconstruit une fois par semaine. Les
// déclencheurs le tiennent déjà à jour ; ceci rattrape les cas particuliers
// (restauration d'une sauvegarde, base modifiée à la main).
try {
    $indexees = recherche_reindexer();
    echo $indexees > 0
        ? "  · index de recherche reconstruit : {$indexees} entrées\n"
        : "  · recherche : mode sans index (SQLite sans FTS5)\n";
} catch (Throwable $e) {
    cli_err("  ! réindexation impossible : " . $e->getMessage() . "\n");
}

try {
    db()->exec('PRAGMA optimize');
    db()->exec('PRAGMA wal_checkpoint(PASSIVE)');
    echo "  · base optimisée (WAL)\n";
} catch (Throwable $e) {
    cli_err("  ! optimisation impossible : " . $e->getMessage() . "\n");
}

echo "Terminé.\n";
