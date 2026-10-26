<?php
/**
 * Authentification et rôles.
 *
 * Deux rôles :
 *  - « admin »   : comptes personnels, voient et gèrent tout ;
 *  - « famille » : un compte par branche familiale, mot de passe partagé
 *                  par la branche, visibilité limitée aux contenus autorisés.
 */

declare(strict_types=1);

/** Compte connecté (ligne accounts + branche éventuelle), ou null. */
function current_account(): ?array
{
    static $cached = false;
    static $account = null;

    if ($cached) {
        return $account;
    }
    $cached = true;

    $id = $_SESSION['account_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }

    $account = db_one(
        'SELECT a.*, b.code AS branch_code, b.name AS branch_name, b.see_all AS branch_see_all,
                b.is_active AS branch_active
         FROM accounts a
         LEFT JOIN branches b ON b.id = a.branch_id
         WHERE a.id = ?',
        [(int) $id]
    );

    // Compte supprimé, désactivé ou branche désactivée : on déconnecte.
    if (!$account || (int) $account['is_active'] !== 1
        || ($account['branch_id'] !== null && (int) ($account['branch_active'] ?? 0) !== 1)) {
        auth_logout();
        $account = null;
    }

    return $account;
}

/** Force le rechargement du compte (après modification). */
function current_account_refresh(): ?array
{
    // Astuce simple : on repart d'une session propre côté cache statique
    // en relisant directement la base.
    $id = $_SESSION['account_id'] ?? null;
    if (!$id) {
        return null;
    }
    return db_one('SELECT * FROM accounts WHERE id = ?', [(int) $id]);
}

function is_logged_in(): bool
{
    return current_account() !== null;
}

function is_admin(): bool
{
    $account = current_account();
    return $account !== null && $account['role'] === 'admin';
}

/** L'utilisateur voit-il tous les contenus (admin ou branche « voit tout ») ? */
function can_see_all(): bool
{
    $account = current_account();
    if ($account === null) {
        return false;
    }
    if ($account['role'] === 'admin') {
        return true;
    }
    return (int) ($account['branch_see_all'] ?? 0) === 1;
}

/** Branches auxquelles l'utilisateur a accès (vide si visiteur). */
function accessible_branch_ids(): array
{
    $account = current_account();
    if ($account === null) {
        return [];
    }
    if ($account['role'] === 'admin' || (int) ($account['branch_see_all'] ?? 0) === 1) {
        $ids = db_all('SELECT id FROM branches WHERE is_active = 1 ORDER BY code');
        return array_map(static fn ($r) => (int) $r['id'], $ids);
    }
    return $account['branch_id'] !== null ? [(int) $account['branch_id']] : [];
}

/** Redirige vers la connexion si l'utilisateur n'est pas connecté. */
function require_login(): void
{
    if (!is_logged_in()) {
        $next = $_SERVER['REQUEST_URI'] ?? '/famille';
        flash('info', 'Merci de vous connecter pour accéder à cet espace.');
        redirect('/connexion?next=' . rawurlencode($next));
    }
}

/** Réserve une page aux administrateurs. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Accès refusé',
            'message' => 'Cette page est réservée aux administrateurs.',
        ]);
        exit;
    }
}

/** Adresse « espace famille » ou « admin » selon le rôle. */
function home_for_account(): string
{
    return is_admin() ? '/admin' : '/famille';
}

/**
 * Tente une connexion.
 *
 * @return array{ok:bool, message:string, account:?array}
 */
function auth_attempt(string $username, string $password): array
{
    $username = trim($username);
    $ip       = client_ip();

    if ($username === '' || $password === '') {
        return ['ok' => false, 'message' => 'Identifiant et mot de passe sont obligatoires.', 'account' => null];
    }

    // 1. Blocage temporaire après trop d'échecs (par identifiant ET par IP).
    $block = login_block_status($username, $ip);
    if ($block['blocked']) {
        $minutes = max(1, (int) ceil(($block['until'] - time()) / 60));
        return [
            'ok'      => false,
            'message' => sprintf(
                'Trop de tentatives. Réessayez dans %d minute%s.',
                $minutes,
                $minutes > 1 ? 's' : ''
            ),
            'account' => null,
        ];
    }

    // 2. Recherche du compte (admin ou branche).
    $account = db_one(
        'SELECT a.*, b.is_active AS branch_active, b.code AS branch_code
         FROM accounts a
         LEFT JOIN branches b ON b.id = a.branch_id
         WHERE a.username = ? COLLATE NOCASE',
        [$username]
    );

    $hash = $account['password_hash'] ?? '';
    $ok   = password_check($password, $hash); // compare toujours un hachage (temps constant)

    if (!$account || !$ok || (int) $account['is_active'] !== 1
        || ($account['branch_id'] !== null && (int) ($account['branch_active'] ?? 0) !== 1)) {
        login_record_attempt($username, $ip, false);

        // Message générique : on n'indique pas si l'identifiant existe.
        $block = login_block_status($username, $ip);
        $message = 'Identifiant ou mot de passe incorrect.';
        if ($block['blocked']) {
            $minutes = max(1, (int) ceil(($block['until'] - time()) / 60));
            $message .= sprintf(' Trop de tentatives : réessayez dans %d minute%s.', $minutes, $minutes > 1 ? 's' : '');
        }
        return ['ok' => false, 'message' => $message, 'account' => null];
    }

    // 3. Connexion réussie.
    login_record_attempt($username, $ip, true);

    // Nouvel identifiant de session (protection contre la fixation de session).
    session_regenerate_id(true);
    $_SESSION['account_id']    = (int) $account['id'];
    $_SESSION['login_at']      = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['_csrf']); // nouveau jeton pour la nouvelle session

    db_update('accounts', (int) $account['id'], ['last_login_at' => time()]);
    log_activity((int) $account['id'], 'connexion', 'Connexion réussie');

    // On recharge le compte avec sa branche.
    return ['ok' => true, 'message' => '', 'account' => current_account()];
}

/** Déconnecte l'utilisateur et détruit la session. */
function auth_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
