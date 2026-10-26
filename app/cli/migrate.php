<?php
/**
 * Applique les migrations de la base et prépare les dossiers de stockage.
 *
 * Utilisation :
 *   php app/cli/migrate.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

echo "Histoire de famille — migration de la base\n";
echo "-------------------------------------------\n";
echo 'Base de données : ' . config('database') . "\n";
echo 'Stockage        : ' . config('storage.root') . "\n\n";

$applied = db_migrate();

if ($applied === []) {
    echo "Aucune nouvelle migration : la base est à jour.\n";
} else {
    foreach ($applied as $name) {
        echo '  ✓ migration appliquée : ' . $name . "\n";
    }
}

try {
    $dirs = storage_ensure_dirs();
    echo "Dossiers de stockage prêts : " . implode(', ', $dirs) . "\n";
} catch (Throwable $e) {
    cli_err("Attention : " . $e->getMessage() . "\n");
}

$nbAdmins = (int) db_value("SELECT COUNT(*) FROM accounts WHERE role = 'admin'", [], 0);
if ($nbAdmins === 0) {
    echo "\nAucun administrateur : créez-en un avec\n";
    echo "  php app/cli/create-admin.php\n";
}

echo "\nTerminé.\n";
