<?php
/**
 * Diffusion des fichiers (documents, photos, vidéos).
 *
 * Aucun fichier n'est accessible directement par URL : tout passe par PHP,
 * qui vérifie les droits AVANT d'ouvrir le fichier.
 *
 * Pour les vidéos, le script gère les requêtes HTTP « Range » (lecture
 * progressive, avance rapide) et envoie le fichier par morceaux : le fichier
 * n'est jamais chargé entièrement en mémoire (indispensable sur un Pi 3B+).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Gestion du nombre de lectures vidéo simultanées (verrous par fichiers)
// ---------------------------------------------------------------------------

function stream_lock_dir(): string
{
    return storage_path((string) config('storage.locks', 'verrous'));
}

/** Supprime les verrous laissés par un processus interrompu. */
function stream_purge_stale_locks(): void
{
    $dir   = stream_lock_dir();
    $limit = time() - max(30, (int) config('video.lock_timeout', 120));

    foreach (glob($dir . '/lecture-*.lock') ?: [] as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime < $limit) {
            @unlink($file);
        }
    }
}

/** Nombre de lectures en cours. */
function stream_active_count(): int
{
    stream_purge_stale_locks();
    return count(glob(stream_lock_dir() . '/lecture-*.lock') ?: []);
}

/**
 * Réserve un créneau de lecture vidéo.
 *
 * @return array{ok:bool, token:string, message:string}
 */
function stream_lock_acquire(): array
{
    $max = max(1, (int) config('video.max_concurrent_streams', 2));
    $dir = stream_lock_dir();
    $mutexPath = $dir . '/compteur.lock';

    $mutex = @fopen($mutexPath, 'c');
    if ($mutex === false) {
        // Sans verrou on laisse passer : mieux vaut diffuser que bloquer.
        return ['ok' => true, 'token' => bin2hex(random_bytes(8)), 'message' => ''];
    }

    try {
        if (!flock($mutex, LOCK_EX)) {
            return ['ok' => true, 'token' => bin2hex(random_bytes(8)), 'message' => ''];
        }

        stream_purge_stale_locks();
        $active = count(glob($dir . '/lecture-*.lock') ?: []);

        if ($active >= $max) {
            flock($mutex, LOCK_UN);
            return [
                'ok'      => false,
                'token'   => '',
                'message' => sprintf(
                    'Trop de vidéos sont lues en même temps (%d maximum). Réessayez dans quelques instants.',
                    $max
                ),
            ];
        }

        $token = bin2hex(random_bytes(12));
        $file  = $dir . '/lecture-' . $token . '.lock';
        file_put_contents($file, json_encode([
            'pid'   => getmypid(),
            'start' => time(),
            'ip'    => client_ip(),
        ]));

        flock($mutex, LOCK_UN);
        return ['ok' => true, 'token' => $token, 'message' => ''];
    } finally {
        fclose($mutex);
    }
}

/** Signale que la lecture est toujours active (évite la purge). */
function stream_lock_heartbeat(string $token): void
{
    if ($token === '') {
        return;
    }
    @touch(stream_lock_dir() . '/lecture-' . $token . '.lock');
}

/** Libère le créneau de lecture. */
function stream_lock_release(string $token): void
{
    if ($token === '') {
        return;
    }
    @unlink(stream_lock_dir() . '/lecture-' . $token . '.lock');
}

// ---------------------------------------------------------------------------
// En-têtes de réponse
// ---------------------------------------------------------------------------

/**
 * Prépare l'envoi du fichier.
 * @return array{exists:bool, path:string, size:int}
 */
function prepare_file_response(array $doc, bool $forceDownload = false): array
{
    $path = storage_absolute((string) $doc['rel_path']);
    $size = is_file($path) ? (int) filesize($path) : 0;

    // Ces réponses sont privées : aucun cache intermédiaire.
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');

    if (!$size) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fichier introuvable sur le disque.';
        return ['exists' => false, 'path' => $path, 'size' => 0];
    }

    $mime = (string) ($doc['mime'] ?? 'application/octet-stream');
    header('Content-Type: ' . $mime);

    $name    = (string) ($doc['original_name'] ?? 'fichier');
    $name    = preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $name) ?: 'fichier';
    $disposition = $forceDownload ? 'attachment' : 'inline';
    header(sprintf(
        'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
        $disposition,
        str_replace('"', '', $name),
        rawurlencode($name)
    ));

    return ['exists' => true, 'path' => $path, 'size' => $size];
}

/**
 * Analyse l'en-tête Range.
 *
 * @return array{status:string, start:int, end:int} status : none|ok|invalid
 */
function parse_range_header(?string $header, int $size): array
{
    if ($header === null || trim($header) === '') {
        return ['status' => 'none', 'start' => 0, 'end' => $size - 1];
    }

    // On ne traite que la première plage (suffisant pour les navigateurs).
    if (!preg_match('/^bytes=(\d*)-(\d*)/i', trim($header), $m)) {
        return ['status' => 'invalid', 'start' => 0, 'end' => 0];
    }

    $startText = $m[1];
    $endText   = $m[2];

    if ($startText === '' && $endText === '') {
        return ['status' => 'invalid', 'start' => 0, 'end' => 0];
    }

    if ($startText === '') {
        // bytes=-500 → les 500 derniers octets
        $suffix = (int) $endText;
        if ($suffix <= 0) {
            return ['status' => 'invalid', 'start' => 0, 'end' => 0];
        }
        $start = max(0, $size - $suffix);
        $end   = $size - 1;
    } else {
        $start = (int) $startText;
        $end   = $endText === '' ? $size - 1 : (int) $endText;
    }

    if ($start >= $size || $start < 0) {
        return ['status' => 'invalid', 'start' => 0, 'end' => 0];
    }
    if ($end >= $size) {
        $end = $size - 1;
    }
    if ($end < $start) {
        return ['status' => 'invalid', 'start' => 0, 'end' => 0];
    }

    return ['status' => 'ok', 'start' => $start, 'end' => $end];
}

/**
 * Envoie le fichier au navigateur (avec gestion du Range pour les vidéos).
 */
function send_file(array $doc, bool $forceDownload = false): void
{
    $isVideo = ($doc['kind'] ?? '') === 'video';
    $info    = prepare_file_response($doc, $forceDownload);
    if (!$info['exists']) {
        return;
    }

    $size  = $info['size'];
    $path  = $info['path'];
    $start = 0;
    $end   = $size - 1;
    $status = 200;

    if (!$forceDownload) {
        $range = parse_range_header($_SERVER['HTTP_RANGE'] ?? null, $size);
        if ($range['status'] === 'invalid') {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            return;
        }
        if ($range['status'] === 'ok') {
            $start  = $range['start'];
            $end    = $range['end'];
            $status = 206;
            header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
        }
    }

    http_response_code($status);
    header('Content-Length: ' . ($end - $start + 1));

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'HEAD') {
        return;
    }

    // Réservation d'un créneau pour les vidéos.
    $token = '';
    if ($isVideo) {
        $lock = stream_lock_acquire();
        if (!$lock['ok']) {
            http_response_code(503);
            header('Retry-After: 30');
            header('Content-Type: text/plain; charset=utf-8');
            echo $lock['message'];
            return;
        }
        $token = $lock['token'];
        register_shutdown_function(static fn () => stream_lock_release($token));
    }

    // Aucune mise en tampon : les octets partent au fur et à mesure.
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    @set_time_limit(0);
    ignore_user_abort(false);

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        http_response_code(500);
        return;
    }

    if ($start > 0) {
        fseek($handle, $start);
    }

    $chunkSize = max(8192, (int) config('video.chunk_size', 262144));
    $remaining = $end - $start + 1;
    $counter   = 0;

    while ($remaining > 0 && !feof($handle)) {
        $read = (int) min($chunkSize, $remaining);
        $data = fread($handle, $read);
        if ($data === false || $data === '') {
            break;
        }
        echo $data;
        flush();
        $remaining -= strlen($data);

        // Battement de cœur du verrou toutes les ~2 Mo.
        $counter += strlen($data);
        if ($isVideo && $counter > 2097152) {
            $counter = 0;
            stream_lock_heartbeat($token);
        }

        if (connection_aborted()) {
            break;
        }
    }

    fclose($handle);
    if ($isVideo) {
        stream_lock_release($token);
    }
}
