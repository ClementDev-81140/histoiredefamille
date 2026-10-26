<?php
/**
 * Amorçage de l'application : chargement de la configuration, des
 * bibliothèques, de la session et des en-têtes de sécurité.
 *
 * Tous les points d'entrée (public/index.php et les scripts CLI) incluent
 * ce fichier en premier.
 */

declare(strict_types=1);

define('HDF_ROOT', dirname(__DIR__));
define('HDF_APP', __DIR__);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/forms.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/visibility.php';
require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/images.php';
require_once __DIR__ . '/lib/text.php';
require_once __DIR__ . '/lib/stream.php';
require_once __DIR__ . '/lib/gedcom.php';
require_once __DIR__ . '/lib/content.php';

/**
 * Le script tourne-t-il en ligne de commande ?
 *
 * En production, PHP_SAPI vaut « cli » pour les scripts de app/cli/.
 * La variable d'environnement HDF_FORCE_CLI=1 sert uniquement aux tests
 * locaux (serveur d'essai PHP en WebAssembly) : elle n'a aucun effet sur
 * un serveur Apache, dont l'environnement n'est pas modifiable par un visiteur.
 */
function is_cli(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }
    return getenv('HDF_FORCE_CLI') === '1';
}

// ---------------------------------------------------------------------------
// Environnement
// ---------------------------------------------------------------------------

date_default_timezone_set((string) config('timezone', 'Europe/Paris'));
setlocale(LC_ALL, 'fr_FR.UTF-8', 'fr_FR', 'fr');

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

ini_set('log_errors', '1');

// Journalisation interne (facultatif, sans données personnelles).
$logDir = HDF_ROOT . '/var/log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0770, true);
}

/**
 * Gestionnaire d'erreurs non interceptées : page propre + journal.
 */
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf(
        "[histoiredefamille] %s : %s dans %s à la ligne %d",
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    if (is_cli()) {
        cli_err('Erreur : ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $details = config('debug') ? $e->getMessage() : null;
    try {
        render('erreur', [
            'titre'   => 'Erreur du serveur',
            'message' => 'Une erreur inattendue s’est produite. Réessayez dans quelques instants.',
            'details' => $details,
        ]);
    } catch (Throwable) {
        echo '<!doctype html><meta charset="utf-8"><h1>Erreur du serveur</h1>'
            . '<p>Consultez le journal d’erreurs d’Apache pour plus de détails.</p>';
    }
});

// ---------------------------------------------------------------------------
// Vérifications d'installation (avant tout accès à la base)
// ---------------------------------------------------------------------------

if (!is_configured()) {
    if (is_cli()) {
        cli_err("Configuration absente : copiez config.example.php en config.local.php." . PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Site en cours d’installation</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem;line-height:1.6;color:#222}</style>'
        . '<h1>Site en cours d’installation</h1>'
        . '<p>Le fichier <code>config.local.php</code> est absent. Copiez <code>config.example.php</code> '
        . 'en <code>config.local.php</code> puis adaptez les chemins, comme indiqué dans le README.</p>'
        . '</html>';
    exit;
}

// Session et sécurité.
if (!is_cli()) {
    session_start_secure();
    send_security_headers();
    session_enforce_timeouts();
} else {
    // En CLI, une session factice évite les avertissements des fonctions d'affichage.
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }
}

// La base doit être installée (migrations) avant d'afficher le site.
// Les scripts CLI vérifient eux-mêmes (migrate.php doit pouvoir s'exécuter
// justement quand la base n'existe pas encore).
if (!is_cli() && !db_is_installed()) {
    if (is_cli()) {
        cli_err("Base non initialisée : lancez « php app/cli/migrate.php »." . PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Installation à terminer</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem;line-height:1.6;color:#222}</style>'
        . '<h1>Installation à terminer</h1>'
        . '<p>La base de données n’est pas encore initialisée. Lancez sur le serveur :</p>'
        . '<p><code>php app/cli/migrate.php</code></p>'
        . '<p>puis créez le premier administrateur :</p>'
        . '<p><code>php app/cli/create-admin.php</code></p>';
    exit;
}
