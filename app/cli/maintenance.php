<?php
/**
 * Maintenance quotidienne (à lancer par cron, voir deploy/crontab.example).
 *
 *  - supprime les tentatives de connexion anciennes ;
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

stream_purge_stale_locks();
echo "  · lectures vidéo en cours : " . stream_active_count() . "\n";

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

try {
    db()->exec('PRAGMA optimize');
    db()->exec('PRAGMA wal_checkpoint(PASSIVE)');
    echo "  · base optimisée (WAL)\n";
} catch (Throwable $e) {
    cli_err("  ! optimisation impossible : " . $e->getMessage() . "\n");
}

echo "Terminé.\n";
