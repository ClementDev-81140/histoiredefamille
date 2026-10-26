<?php
/**
 * Routeur minimaliste (aucun framework).
 *
 * Une route = méthode HTTP + motif (/admin/documents/{id}) + fonction.
 * Les motifs acceptent {id} (nombre) et {slug} (lettres, chiffres, tirets).
 */

declare(strict_types=1);

/** Enregistre une route. */
function route(string $method, string $pattern, callable $handler): void
{
    $GLOBALS['hdf_routes'][] = [
        'method'  => strtoupper($method),
        'pattern' => $pattern,
        'handler' => $handler,
    ];
}

/** Convertit un motif en expression régulière. */
function route_to_regex(string $pattern): array
{
    $names = [];
    $regex = preg_replace_callback(
        '/\{(\w+)\}/',
        static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return $m[1] === 'slug' ? '([a-z0-9][a-z0-9\-]*)' : '(\d+)';
        },
        $pattern
    ) ?? $pattern;

    return ['#^' . $regex . '$#', $names];
}

/** Chemin demandé, normalisé (sans slash final, sans paramètres). */
function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }
    $path = '/' . trim($path, '/');
    return $path === '//' ? '/' : $path;
}

/** Exécute la route correspondant à la requête, ou affiche une 404. */
function dispatch_request(): void
{
    $path   = request_path();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'HEAD') {
        $method = 'GET';
    }

    $patternMatched = false;

    foreach ($GLOBALS['hdf_routes'] ?? [] as $route) {
        [$regex, $names] = route_to_regex($route['pattern']);
        if (!preg_match($regex, $path, $matches)) {
            continue;
        }
        $patternMatched = true;
        if ($route['method'] !== $method) {
            continue;
        }

        array_shift($matches);
        $params = [];
        foreach ($names as $index => $name) {
            $params[$name] = $matches[$index] ?? '';
        }

        ($route['handler'])($params);
        return;
    }

    if ($patternMatched) {
        http_response_code(405);
        header('Allow: GET, POST');
        render('erreur', [
            'titre'   => 'Méthode non autorisée',
            'message' => 'Cette action n’est pas disponible de cette manière.',
        ]);
        return;
    }

    http_response_code(404);
    render('erreur', [
        'titre'   => 'Page introuvable',
        'message' => 'La page demandée n’existe pas ou n’est plus disponible.',
    ]);
}
