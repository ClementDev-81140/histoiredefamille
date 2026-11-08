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

// Sauvegarde avant migration : s'il reste des migrations à appliquer et que
// la base existe déjà, on en garde une copie datée à côté (jamais écrasée).
// C'est cette commande que lance deploy/install.sh : un « git pull » suivi
// d'install.sh applique donc la migration avec sa copie de sécurité.
$enAttente = db_migrations_en_attente();
$base = (string) config('database');
if ($enAttente !== [] && $base !== '' && is_file($base) && filesize($base) > 0) {
    $copie = $base . '.avant-migration-' . date('Ymd-His');
    if (@copy($base, $copie)) {
        echo 'Copie de sécurité avant migration : ' . $copie . "\n\n";
    } else {
        cli_err("Attention : copie de sécurité impossible avant migration (" . $copie . ").\n");
    }
}

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
