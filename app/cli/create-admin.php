<?php
/**
 * Crée un compte administrateur.
 *
 * Le mot de passe est saisi en ligne de commande : il n'est jamais écrit
 * dans le code ni dans un fichier. L'affichage est masqué si le terminal
 * le permet (stty).
 *
 * Utilisation :
 *   php app/cli/create-admin.php
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

echo "Histoire de famille — création d'un administrateur\n";
echo "--------------------------------------------------\n\n";

/**
 * Lit une ligne au clavier.
 * Un seul descripteur est ouvert pour toute la commande : sinon les lignes
 * déjà lues par un premier descripteur seraient perdues pour le suivant.
 */
function stdin_line(): string|false
{
    static $stdin = null;
    if ($stdin === null) {
        $stdin = fopen('php://stdin', 'rb') ?: false;
    }
    if ($stdin === false) {
        return false;
    }
    return fgets($stdin);
}

/** Lit une ligne au clavier (identifiant, nom affiché). */
function prompt(string $question): string
{
    cli_out($question);
    $line = stdin_line();
    if ($line === false) {
        cli_err("\nEntrée standard fermée : commande interrompue.\n");
        exit(1);
    }
    return trim($line);
}

/** Lit un mot de passe sans l'afficher (si possible). */
function prompt_password(string $question): string
{
    cli_out($question);

    $canHide = function_exists('shell_exec') && DIRECTORY_SEPARATOR === '/';
    if ($canHide) {
        @shell_exec('stty -echo 2>/dev/null');
    }

    $line = stdin_line();

    if ($canHide) {
        @shell_exec('stty echo 2>/dev/null');
        cli_out("\n");
    }

    if ($line === false) {
        cli_err("\nEntrée standard fermée : commande interrompue.\n");
        exit(1);
    }

    return rtrim($line, "\r\n");
}

// --- Identifiant ------------------------------------------------------------
$username = '';
while (true) {
    $username = strtolower(prompt('Identifiant de connexion (minuscules, sans espace) : '));
    if (preg_match('/^[a-z][a-z0-9._-]{2,29}$/', $username)) {
        break;
    }
    echo "  → 3 à 30 caractères : lettres minuscules, chiffres, point, tiret ou souligné.\n";
}

if (db_one('SELECT id FROM accounts WHERE username = ? COLLATE NOCASE', [$username])) {
    cli_err("Ce compte existe déjà.\n");
    exit(1);
}

$display = prompt('Nom affiché (facultatif) : ');
if ($display === '') {
    $display = $username;
}

// --- Mot de passe ----------------------------------------------------------
$password = '';
while (true) {
    $password = prompt_password('Mot de passe (12 caractères minimum) : ');
    if (mb_strlen($password) < 12) {
        echo "  → trop court, recommencez.\n";
        continue;
    }
    $confirm = prompt_password('Répétez le mot de passe : ');
    if ($confirm !== $password) {
        echo "  → les deux saisies ne correspondent pas, recommencez.\n";
        continue;
    }
    break;
}

// --- Enregistrement --------------------------------------------------------
$now = time();
db_insert('accounts', [
    'username'      => $username,
    'display_name'  => mb_substr($display, 0, 100),
    'password_hash' => password_make($password),
    'role'          => 'admin',
    'branch_id'     => null,
    'is_active'     => 1,
    'created_at'    => $now,
    'updated_at'    => $now,
]);

log_activity(null, 'admin_cree', 'Création du compte administrateur ' . $username);

echo "\nCompte administrateur « {$username} » créé.\n";
echo "Connectez-vous sur /connexion (ou /admin une fois le site en ligne).\n";
