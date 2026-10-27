<?php
/**
 * Petites fonctions utilitaires partagées par toute l'application.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Échappement et URL
// ---------------------------------------------------------------------------

/** Échappe une chaîne pour l'afficher dans du HTML (protection XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construit une URL interne.
 * url('/documents') → « /documents » (ou « https://…/documents » si
 * « site.url » est défini et que l'on veut une URL absolue).
 */
function url(string $path = '/', bool $absolute = false): string
{
    $path = '/' . ltrim($path, '/');
    if ($absolute) {
        $base = rtrim((string) config('site.url', ''), '/');
        if ($base !== '') {
            return $base . $path;
        }
    }
    return $path;
}

/**
 * Chemin de redirection interne, ou '' si la valeur est suspecte.
 *
 * Sert au paramètre « next » du formulaire de connexion : on ne redirige que
 * vers une page du site. Sont refusés :
 *  - tout ce qui ne commence pas par « / » (donc « https://exemple.test ») ;
 *  - « // » et « /\ » en tête, que les navigateurs transforment en
 *    « //site.com » (redirection ouverte vers un autre domaine) ;
 *  - l'antislash et les caractères de contrôle, qui permettent cette même
 *    transformation ou une injection d'en-tête.
 */
function safe_next_path(string $path): string
{
    if ($path === '' || strlen($path) > 512) {
        return '';
    }
    if (!str_starts_with($path, '/')) {
        return '';
    }
    // « // » ou « /\ » : le navigateur y voit « //autre-site.com ».
    if (preg_match('#^/[/\\\\]#', $path) === 1) {
        return '';
    }
    if (str_contains($path, '\\') || preg_match('#[\x00-\x1F\x7F]#', $path) === 1) {
        return '';
    }
    if (str_contains($path, '://')) {
        return '';
    }
    return $path;
}

/** Redirige puis arrête le script. */
function redirect(string $path, int $code = 303): never
{
    header('Location: ' . url($path), true, $code);
    exit;
}

/** Valeur d'un paramètre GET (chaîne nettoyée). */
function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Valeur d'un paramètre POST (chaîne nettoyée). */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Tableau de paramètres POST (cases à cocher multiples). */
function post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        return [];
    }
    return array_values(array_filter(array_map(
        static fn ($v) => is_string($v) ? trim($v) : '',
        $value
    ), static fn ($v) => $v !== ''));
}

/** Paramètre d'URL entier (identifiant). */
function query_int(string $key, int $default = 0): int
{
    return (int) ($_GET[$key] ?? $default);
}

// ---------------------------------------------------------------------------
// Messages flash (stockés une seule fois en session)
// ---------------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Récupère et vide les messages flash. */
function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($flashes) ? $flashes : [];
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

/** Jeton CSRF de la session (créé à la première demande). */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Champ caché à insérer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Vérifie le jeton CSRF de la requête POST courante.
 * En cas d'échec : erreur 403 et arrêt immédiat.
 */
function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Session expirée',
            'message' => 'Le formulaire a expiré ou la requête est invalide. '
                . 'Rechargez la page et réessayez.',
        ]);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Affichage
// ---------------------------------------------------------------------------

/**
 * Affiche une vue (app/views/xxx.php) dans une mise en page.
 *
 * @param string $view   nom de la vue, ex. « public/accueil »
 * @param array  $data   variables transmises à la vue
 * @param string|null $layout mise en page (null = aucune)
 */
function render(string $view, array $data = [], ?string $layout = 'layout'): void
{
    $file = dirname(__DIR__) . '/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Vue introuvable : ' . $view);
    }

    // Les variables sont extraites dans un contexte isolé.
    $render = static function (string $__file, array $__data): void {
        extract($__data, EXTR_SKIP);
        require $__file;
    };

    if ($layout === null) {
        $render($file, $data);
        return;
    }

    // Le contenu de la vue est capturé puis inséré dans la mise en page.
    ob_start();
    $render($file, $data);
    $contenu = ob_get_clean();

    $render(dirname(__DIR__) . '/views/' . $layout . '.php', $data + ['contenu' => $contenu]);
}

/** Taille lisible (1,2 Mo). */
function format_size(int|float|null $bytes): string
{
    $bytes = (float) ($bytes ?? 0);
    if ($bytes < 1024) {
        return number_format($bytes, 0, ',', ' ') . ' o';
    }
    foreach (['Ko', 'Mo', 'Go', 'To'] as $unit) {
        $bytes /= 1024;
        if ($bytes < 1024) {
            $decimals = $bytes < 10 ? 1 : 0;
            return number_format($bytes, $decimals, ',', ' ') . ' ' . $unit;
        }
    }
    return number_format($bytes, 1, ',', ' ') . ' Po';
}

/** Date lisible à partir d'un horodatage Unix. */
function format_date(?int $timestamp, bool $withTime = false): string
{
    if (!$timestamp) {
        return '—';
    }
    return date($withTime ? 'd/m/Y à H:i' : 'd/m/Y', $timestamp);
}

/** Nombre de jours depuis un horodatage. */
function days_since(?int $timestamp): ?int
{
    if (!$timestamp) {
        return null;
    }
    return (int) floor((time() - $timestamp) / 86400);
}

/** Retourne « sélectionné » si la condition est vraie (aide pour les formulaires). */
function selected(bool $condition): string
{
    return $condition ? ' selected' : '';
}

/** Retourne « checked » si la condition est vraie. */
function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/** Tronque un texte proprement (sans couper les caractères UTF-8). */
function excerpt(?string $text, int $length = 160): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    if (!function_exists('mb_substr')) {
        return strlen($text) <= $length ? $text : substr($text, 0, $length) . '…';
    }
    return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

/**
 * Calcule les bornes de pagination.
 * @return array{page:int, perPage:int, offset:int, pages:int}
 */
function pagination(int $total, int $perPage = 0, int $page = 1): array
{
    $perPage = $perPage > 0 ? $perPage : (int) config('per_page', 24);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page, $pages));
    return [
        'page'    => $page,
        'perPage' => $perPage,
        'offset'  => ($page - 1) * $perPage,
        'pages'   => $pages,
        'total'   => $total,
    ];
}

/** Construit une URL en conservant les paramètres GET actuels. */
function url_with(array $changes): string
{
    $params = array_merge($_GET, $changes);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $qs   = http_build_query($params);
    return $qs === '' ? $path : $path . '?' . $qs;
}

// ---------------------------------------------------------------------------
// Sortie en ligne de commande
// ---------------------------------------------------------------------------

/** Écrit sur la sortie standard (compatible CLI et environnements de test). */
function cli_out(string $text): void
{
    echo $text;
}

/** Écrit sur la sortie d'erreur, sans dépendre de la constante STDERR. */
function cli_err(string $text): void
{
    $handle = @fopen('php://stderr', 'w');
    if ($handle !== false) {
        fwrite($handle, $text);
        fclose($handle);
        return;
    }
    echo $text;
}

// ---------------------------------------------------------------------------
// Divers
// ---------------------------------------------------------------------------

/** Adresse IP du visiteur, en tenant compte de l'en-tête Cloudflare. */
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $remote = is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';

    // L'en-tête CF-Connecting-IP n'est digne de confiance que si la requête
    // arrive de cloudflared, c'est-à-dire de la boucle locale du Pi. Depuis le
    // réseau local, n'importe qui pourrait sinon envoyer cet en-tête à la main
    // et contourner la limitation par adresse IP.
    if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
        return $remote;
    }

    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (is_string($cf) && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }

    return $remote;
}

/** Journalise une action d'administration (table activity_log). */
function log_activity(?int $accountId, string $action, string $details = ''): void
{
    try {
        db_exec(
            'INSERT INTO activity_log (account_id, action, details, ip, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$accountId, $action, excerpt($details, 500), client_ip(), time()]
        );
    } catch (Throwable) {
        // Le journal ne doit jamais faire échouer une action.
    }
}

/** Réponse JSON (API de l'arbre). */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
