<?php
/**
 * Sécurité : en-têtes HTTP, session, hachage des mots de passe,
 * limitation des essais de connexion.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// En-têtes HTTP
// ---------------------------------------------------------------------------

/**
 * Envoie les en-têtes de sécurité communs à toutes les pages.
 * (La configuration Apache en renvoie aussi, en doublon : ceinture et
 * bretelles, au cas où le site tournerait derrière un autre serveur.)
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');

    // Politique de sécurité du contenu : aucune ressource externe, aucun
    // script en ligne (tout le JS est dans /assets/app.js).
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "img-src 'self' data:; "
        . "media-src 'self'; "
        . "style-src 'self'; "
        . "script-src 'self'; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'"
    );

    // Les pages contiennent des données familiales : pas de cache partagé.
    header('Cache-Control: private, no-store, max-age=0');

    if (config('https_only')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

/** Démarre la session avec des réglages sûrs (cookie HttpOnly + Secure + Lax). */
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.sid_bits_per_character', '5');
    ini_set('session.sid_length', '52');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (bool) config('https_only'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('HDF_SESSION');
    session_start();
}

/**
 * Vérifie les délais d'expiration de la session.
 * Déconnecte automatiquement après inactivité ou au-delà de la durée max.
 */
function session_enforce_timeouts(): void
{
    if (empty($_SESSION['account_id'])) {
        return;
    }

    $now       = time();
    $idleSecs  = ((int) config('security.session_idle_minutes', 240)) * 60;
    $maxSecs   = ((int) config('security.session_absolute_hours', 168)) * 3600;
    $lastSeen  = (int) ($_SESSION['last_activity'] ?? $now);
    $loginAt   = (int) ($_SESSION['login_at'] ?? $now);

    if (($now - $lastSeen) > $idleSecs || ($now - $loginAt) > $maxSecs) {
        auth_logout();
        session_start_secure();
        flash('info', 'Votre session a expiré. Merci de vous reconnecter.');
        redirect('/connexion');
    }

    $_SESSION['last_activity'] = $now;
}

// ---------------------------------------------------------------------------
// Mots de passe
// ---------------------------------------------------------------------------

/**
 * Hache un mot de passe.
 * Argon2id si l'extension PHP le permet, sinon bcrypt (coût configurable).
 */
function password_make(string $password): string
{
    $algo = (string) config('security.password_algorithm', 'auto');

    if (($algo === 'auto' || $algo === 'argon2id') && defined('PASSWORD_ARGON2ID')) {
        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => (int) config('security.argon2_memory_kb', 16384),
            'time_cost'   => (int) config('security.argon2_time_cost', 2),
            'threads'     => (int) config('security.argon2_threads', 1),
        ]);
        if (is_string($hash)) {
            return $hash;
        }
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, [
        'cost' => max(10, (int) config('security.bcrypt_cost', 12)),
    ]);

    if (!is_string($hash)) {
        throw new RuntimeException('Impossible de hacher le mot de passe.');
    }
    return $hash;
}

/** Vérifie un mot de passe (compatible avec les hachages existants). */
function password_check(string $password, string $hash): bool
{
    if ($hash === '') {
        return false;
    }
    return password_verify($password, $hash);
}

/**
 * Génère un mot de passe aléatoire « lisible » : groupes de caractères
 * sans ambiguïté visuelle (pas de 0/O, 1/l/I).
 * Exemple : « k7fd-3mqp-9xte ».
 */
function password_generate(int $groups = 3, int $length = 4): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $max      = strlen($alphabet) - 1;
    $parts    = [];
    for ($g = 0; $g < $groups; $g++) {
        $part = '';
        for ($i = 0; $i < $length; $i++) {
            $part .= $alphabet[random_int(0, $max)];
        }
        $parts[] = $part;
    }
    return implode('-', $parts);
}

// ---------------------------------------------------------------------------
// Limitation des essais de connexion
// ---------------------------------------------------------------------------

/**
 * Un identifiant ou une IP est-il bloqué ?
 *
 * @return array{blocked:bool, until:int, reason:string}
 */
function login_block_status(string $identifier, string $ip): array
{
    $max      = max(1, (int) config('security.login_max_attempts', 5));
    $window   = max(1, (int) config('security.login_window_minutes', 15)) * 60;
    $blockFor = max(1, (int) config('security.login_block_minutes', 15)) * 60;
    $since    = time() - $window;
    $now      = time();

    $check = static function (string $sql, string $value) use ($max, $since, $blockFor, $now): int {
        $row = db_one($sql, [$value, $since]);
        if (!$row || (int) $row['nb'] < $max) {
            return 0;
        }
        $until = (int) $row['last'] + $blockFor;
        return $until > $now ? $until : 0;
    };

    $untilIdentifier = $check(
        'SELECT COUNT(*) AS nb, MAX(created_at) AS last FROM login_attempts
         WHERE success = 0 AND identifier = ? AND created_at >= ?',
        $identifier
    );
    $untilIp = $check(
        'SELECT COUNT(*) AS nb, MAX(created_at) AS last FROM login_attempts
         WHERE success = 0 AND ip = ? AND created_at >= ?',
        $ip
    );

    $until = max($untilIdentifier, $untilIp);
    if ($until > 0) {
        $reason = $untilIdentifier >= $untilIp ? 'identifier' : 'ip';
        return ['blocked' => true, 'until' => $until, 'reason' => $reason];
    }
    return ['blocked' => false, 'until' => 0, 'reason' => ''];
}

/** Enregistre une tentative de connexion. */
function login_record_attempt(string $identifier, string $ip, bool $success): void
{
    try {
        db_exec(
            'INSERT INTO login_attempts (identifier, ip, success, created_at) VALUES (?, ?, ?, ?)',
            [$identifier, $ip, $success ? 1 : 0, time()]
        );
    } catch (Throwable) {
        // Ne bloque jamais la connexion en cas de souci d'écriture.
    }

    if ($success) {
        // Une connexion réussie remet le compteur de l'identifiant à zéro.
        try {
            db_exec('DELETE FROM login_attempts WHERE identifier = ? AND success = 0', [$identifier]);
        } catch (Throwable) {
        }
    }
}

/** Supprime les tentatives anciennes (appelé par la maintenance). */
function login_purge_attempts(int $days = 30): int
{
    return db_exec('DELETE FROM login_attempts WHERE created_at < ?', [time() - $days * 86400]);
}
