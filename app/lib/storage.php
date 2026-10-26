<?php
/**
 * Stockage des fichiers, HORS de la racine web.
 *
 * Règles appliquées :
 *  - le type réel du fichier est vérifié avec finfo (pas seulement l'extension) ;
 *  - seule une liste blanche d'extensions est acceptée ;
 *  - le nom sur le disque est aléatoire (aucun nom d'origine exécutable) ;
 *  - permissions 0640, aucun fichier exécutable ;
 *  - le nom d'origine n'est conservé qu'en base de données.
 */

declare(strict_types=1);

/** Chemin absolu d'un sous-dossier du stockage (créé si nécessaire). */
function storage_path(string $sub = ''): string
{
    $root = rtrim((string) config('storage.root'), '/');
    $path = $sub === '' ? $root : $root . '/' . trim($sub, '/');

    if (!is_dir($path)) {
        if (!@mkdir($path, 0770, true) && !is_dir($path)) {
            throw new RuntimeException('Dossier de stockage inaccessible : ' . $path);
        }
    }
    return $path;
}

/** Crée l'arborescence du stockage. */
function storage_ensure_dirs(): array
{
    $created = [];
    foreach (['medias', 'thumbs', 'incoming', 'tmp', 'locks'] as $key) {
        $sub = (string) config('storage.' . $key, $key);
        storage_path($sub);
        $created[] = $sub;
    }
    return $created;
}

/** Chemin absolu d'un fichier du stockage à partir de son chemin relatif. */
function storage_absolute(string $relPath): string
{
    return rtrim((string) config('storage.root'), '/') . '/' . ltrim($relPath, '/');
}

/** Chemin du dossier « arrivée » (dépôt SFTP/Samba). */
function incoming_path(): string
{
    return storage_path((string) config('storage.incoming', 'arrivee'));
}

/** Liste des fichiers déposés dans le dossier arrivée. */
function incoming_files(): array
{
    $dir   = incoming_path();
    $files = [];

    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
            continue;
        }
        $path = $dir . '/' . $name;
        if (!is_file($path)) {
            continue;
        }
        $files[] = [
            'name'     => $name,
            'size'     => (int) filesize($path),
            'modified' => (int) filemtime($path),
            'ext'      => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
        ];
    }

    usort($files, static fn ($a, $b) => $b['modified'] <=> $a['modified']);
    return $files;
}

/** Extensions autorisées (depuis la configuration). */
function allowed_extensions(): array
{
    $exts = config('uploads.allowed_extensions', []);
    return is_array($exts) ? array_map(static fn ($e) => strtolower((string) $e), $exts) : [];
}

/** Correspondance extension → types MIME acceptés (vérification du type réel). */
function mime_map(): array
{
    return [
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'mp4'  => ['video/mp4', 'application/mp4'],
        'm4v'  => ['video/mp4', 'video/x-m4v', 'application/mp4'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/octet-stream'],
        'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip', 'application/octet-stream'],
        'mp3'  => ['audio/mpeg', 'audio/mp3'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a'],
        'ogg'  => ['audio/ogg', 'application/ogg'],
    ];
}

/** Taille maximale d'envoi en octets (limitée aussi par php.ini). */
function upload_max_bytes(): int
{
    $max = (int) config('uploads.max_mb', 50) * 1024 * 1024;

    foreach (['upload_max_filesize', 'post_max_size'] as $iniKey) {
        $value = php_ini_bytes((string) ini_get($iniKey));
        if ($value > 0 && $value < $max) {
            $max = $value;
        }
    }
    return $max;
}

/** Convertit une valeur php.ini (« 64M ») en octets. */
function php_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return 0;
    }
    $unit   = strtolower(substr($value, -1));
    $number = (float) $value;
    return (int) match ($unit) {
        'g'     => $number * 1024 * 1024 * 1024,
        'm'     => $number * 1024 * 1024,
        'k'     => $number * 1024,
        default => $number,
    };
}

/** Libellé français d'une erreur d'envoi PHP. */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur ('
            . format_size(upload_max_bytes()) . ').',
        UPLOAD_ERR_PARTIAL  => 'L’envoi a été interrompu. Réessayez.',
        UPLOAD_ERR_NO_FILE  => 'Aucun fichier n’a été sélectionné.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
        UPLOAD_ERR_CANT_WRITE => 'Le serveur n’a pas pu écrire le fichier (droits du disque ?).',
        UPLOAD_ERR_EXTENSION => 'L’envoi a été bloqué par une extension PHP.',
        default => 'Erreur inconnue lors de l’envoi (code ' . $code . ').',
    };
}

/**
 * Valide un fichier envoyé par le navigateur ($_FILES[...]).
 *
 * @return array{ok:bool, error:string, ext:string, mime:string, size:int, tmp:string, original:string, kind:string}
 */
function validate_uploaded_file(array $file): array
{
    $result = [
        'ok' => false, 'error' => '', 'ext' => '', 'mime' => '', 'size' => 0,
        'tmp' => '', 'original' => '', 'kind' => 'document',
    ];

    if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
        $result['error'] = 'Fichier invalide.';
        return $result;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = upload_error_message((int) $file['error']);
        return $result;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        $result['error'] = 'Fichier temporaire invalide.';
        return $result;
    }

    $size = (int) $file['size'];
    if ($size <= 0) {
        $result['error'] = 'Le fichier est vide.';
        return $result;
    }
    if ($size > upload_max_bytes()) {
        $result['error'] = 'Fichier trop volumineux (maximum ' . format_size(upload_max_bytes()) . ').';
        return $result;
    }

    $original = (string) ($file['name'] ?? 'fichier');
    $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if ($ext === '' || !in_array($ext, allowed_extensions(), true)) {
        $result['error'] = 'Extension non autorisée. Extensions acceptées : '
            . implode(', ', allowed_extensions()) . '.';
        return $result;
    }

    if (!class_exists('finfo')) {
        $result['error'] = 'L’extension PHP « fileinfo » est requise sur le serveur.';
        return $result;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);
    $map   = mime_map();
    $known = $map[$ext] ?? [];

    if ($known !== [] && !in_array($mime, $known, true)) {
        $result['error'] = 'Le contenu du fichier ne correspond pas à son extension (type détecté : '
            . ($mime !== '' ? $mime : 'inconnu') . ').';
        return $result;
    }

    $result['ok']       = true;
    $result['ext']      = $ext;
    $result['mime']     = $mime !== '' ? $mime : 'application/octet-stream';
    $result['size']     = $size;
    $result['tmp']      = (string) $file['tmp_name'];
    $result['original'] = mb_substr($original, 0, 200, 'UTF-8');
    $result['kind']     = kind_for($mime, $ext);
    return $result;
}

/**
 * Valide un fichier déjà présent sur le disque (dossier arrivée).
 * Même liste blanche, même vérification du type réel.
 *
 * @return array{ok:bool, error:string, ext:string, mime:string, size:int, path:string, original:string, kind:string}
 */
function validate_local_file(string $path): array
{
    $result = [
        'ok' => false, 'error' => '', 'ext' => '', 'mime' => '', 'size' => 0,
        'path' => $path, 'original' => basename($path), 'kind' => 'document',
    ];

    if (!is_file($path) || !is_readable($path)) {
        $result['error'] = 'Fichier illisible.';
        return $result;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, allowed_extensions(), true)) {
        $result['error'] = 'Extension non autorisée (' . ($ext !== '' ? $ext : 'aucune') . ').';
        return $result;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($path);
    $map   = mime_map();
    if (isset($map[$ext]) && !in_array($mime, $map[$ext], true)) {
        $result['error'] = 'Type réel non conforme (' . $mime . ').';
        return $result;
    }

    $result['ok']       = true;
    $result['ext']      = $ext;
    $result['mime']     = $mime !== '' ? $mime : 'application/octet-stream';
    $result['size']     = (int) filesize($path);
    $result['original'] = basename($path);
    $result['kind']     = kind_for($mime, $ext);
    return $result;
}

/** Détermine le type de contenu à partir du MIME et de l'extension. */
function kind_for(string $mime, string $ext): string
{
    if (str_starts_with($mime, 'image/')) {
        return 'photo';
    }
    if (str_starts_with($mime, 'video/')) {
        return 'video';
    }
    if (in_array($ext, ['mp4', 'm4v'], true)) {
        return 'video';
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return 'photo';
    }
    return 'document';
}

/** Génère un chemin de miniature unique (dossier miniatures/année/mois). */
function storage_target_thumb_path(): string
{
    $sub    = (string) config('storage.thumbs', 'miniatures');
    $relDir = sprintf('%s/%s/%s', $sub, date('Y'), date('m'));
    storage_path($relDir);

    do {
        $rel = $relDir . '/' . bin2hex(random_bytes(16)) . '.jpg';
    } while (file_exists(storage_absolute($rel)));

    return $rel;
}

/** Génère un nom de stockage aléatoire, réparti en sous-dossiers (année/mois). */
function storage_target_path(string $ext): string
{
    $sub = (string) config('storage.medias', 'medias');
    $relDir = sprintf('%s/%s/%s', $sub, date('Y'), date('m'));
    $dir = storage_path($relDir);

    do {
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $path = $dir . '/' . $name;
    } while (file_exists($path));

    return $relDir . '/' . $name;
}

/**
 * Enregistre un fichier envoyé par le navigateur et renvoie ses métadonnées.
 *
 * @param array $valid résultat de validate_uploaded_file()
 * @return array{ok:bool, error:string, rel_path:string, thumb_path:?string, width:?int, height:?int}
 */
function store_uploaded_file(array $valid): array
{
    $result = ['ok' => false, 'error' => '', 'rel_path' => '', 'thumb_path' => null, 'width' => null, 'height' => null];

    if (empty($valid['ok'])) {
        $result['error'] = $valid['error'] ?? 'Fichier invalide.';
        return $result;
    }

    $rel  = storage_target_path((string) $valid['ext']);
    $dest = storage_absolute($rel);

    if (!@move_uploaded_file((string) $valid['tmp'], $dest)) {
        $result['error'] = 'Impossible d’enregistrer le fichier (droits du dossier de stockage ?).';
        return $result;
    }
    @chmod($dest, 0640);

    return finish_stored_file($rel, $valid);
}

/**
 * Déplace un fichier du dossier arrivée vers le stockage définitif.
 *
 * @param array $valid résultat de validate_local_file()
 */
function store_incoming_file(array $valid): array
{
    $result = ['ok' => false, 'error' => '', 'rel_path' => '', 'thumb_path' => null, 'width' => null, 'height' => null];

    if (empty($valid['ok'])) {
        $result['error'] = $valid['error'] ?? 'Fichier invalide.';
        return $result;
    }

    $rel  = storage_target_path((string) $valid['ext']);
    $dest = storage_absolute($rel);

    if (!@rename((string) $valid['path'], $dest)) {
        // Repli si le dossier arrivée n'est pas sur le même disque.
        if (!@copy((string) $valid['path'], $dest) || !@unlink((string) $valid['path'])) {
            $result['error'] = 'Impossible de déplacer le fichier vers le stockage.';
            return $result;
        }
    }
    @chmod($dest, 0640);

    return finish_stored_file($rel, $valid);
}

/**
 * Complète le stockage : miniature et dimensions pour les photos.
 *
 * @return array{ok:bool, error:string, rel_path:string, thumb_path:?string, width:?int, height:?int}
 */
function finish_stored_file(string $rel, array $valid): array
{
    $result = [
        'ok' => true, 'error' => '', 'rel_path' => $rel,
        'thumb_path' => null, 'width' => null, 'height' => null,
    ];

    $abs  = storage_absolute($rel);
    $kind = (string) ($valid['kind'] ?? 'document');

    if ($kind === 'photo') {
        $size = @getimagesize($abs);
        if (is_array($size)) {
            $result['width']  = (int) $size[0];
            $result['height'] = (int) $size[1];
        }

        $thumbRel = storage_target_thumb_path();
        $thumbAbs = storage_absolute($thumbRel);

        $thumb = make_thumbnail($abs, $thumbAbs);
        if ($thumb['ok']) {
            $result['thumb_path'] = $thumbRel;
        } else {
            // La miniature est un confort : on garde le fichier original.
            error_log('Miniature impossible pour ' . $rel . ' : ' . $thumb['error']);
        }
    }

    return $result;
}

/** Supprime un fichier du stockage (chemin relatif), en restant dans le dossier. */
function storage_delete(?string $relPath): void
{
    if (!$relPath) {
        return;
    }
    $root = realpath(rtrim((string) config('storage.root'), '/'));
    $abs  = storage_absolute($relPath);
    $real = realpath($abs);

    if ($root === false || $real === false) {
        return;
    }
    if (!str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        return; // tentative de sortie du dossier : on ignore
    }
    if (is_file($real)) {
        @unlink($real);
    }
}

/** Supprime tous les fichiers associés à un document. */
function delete_document_files(array $doc): void
{
    storage_delete($doc['rel_path'] ?? null);
    storage_delete($doc['thumb_path'] ?? null);
}

/** Espace disque disponible sur le stockage (octets), ou null. */
function storage_free_space(): ?int
{
    $path = (string) config('storage.root');
    if (!is_dir($path)) {
        return null;
    }
    $free = @disk_free_space($path);
    return $free === false ? null : (int) $free;
}
