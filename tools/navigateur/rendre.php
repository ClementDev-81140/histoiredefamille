<?php
/**
 * Rend une page du site par le routeur réel et l'écrit dans un fichier.
 *
 * Sert au contrôle « vrai navigateur » de tools/navigateur (voir README.md).
 * Ce n'est pas un point d'entrée web : le fichier est hors de public/.
 *
 *   HDF_CONFIG=… php tools/navigateur/rendre.php /admin/videos/nouvelle page.html [compte]
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

// Fin normale d'une page qui redirige : on s'arrête proprement.
class HdfRendreArret extends Exception
{
}

require_once HDF_APP . '/router.php';
require_once HDF_APP . '/routes.php';

$chemin = $argv[1] ?? '/';
$cible  = $argv[2] ?? '/tmp/page.html';
$compte = (int) ($argv[3] ?? 1);

$parties = parse_url($chemin);
$_GET    = [];
if (isset($parties['query'])) {
    parse_str((string) $parties['query'], $_GET);
}
$_POST    = [];
$_FILES   = [];
$_SESSION = ['account_id' => $compte, '_csrf' => 'jeton-d-essai-navigateur'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = $chemin;
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_HOST']      = 'exemple.test';
$_SERVER['HTTPS']          = 'on';

ob_start();
try {
    dispatch_request();
} catch (HdfRendreArret) {
    // Redirection : la page rendue est vide, c'est normal.
} catch (Throwable $e) {
    echo 'ERREUR ', get_class($e), ' : ', e($e->getMessage());
}
$corps = (string) ob_get_clean();

if (@file_put_contents($cible, $corps) === false) {
    fwrite(STDERR, 'Impossible d’écrire ' . $cible . "\n");
    exit(1);
}
echo $cible, ' : ', strlen($corps), " octets\n";
