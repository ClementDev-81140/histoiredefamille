<?php
/**
 * Génération des miniatures avec GD.
 *
 * Contrainte forte : le Raspberry Pi 3B+ n'a que 1 Go de RAM et GD charge
 * l'image entière en mémoire. On vérifie donc la taille en pixels AVANT
 * de décoder, on refuse les images trop grandes, et on libère la mémoire
 * (imagedestroy) dès que possible.
 */

declare(strict_types=1);

/** Mémoire restante disponible (octets) d'après memory_limit. */
function memory_remaining(): int
{
    $limit = php_ini_bytes((string) ini_get('memory_limit'));
    if ($limit <= 0) {
        return PHP_INT_MAX; // pas de limite
    }
    return max(0, $limit - memory_get_usage(true));
}

/**
 * Crée une miniature JPEG à partir d'une image.
 *
 * @return array{ok:bool, error:string, width:int, height:int}
 */
function make_thumbnail(string $source, string $destination): array
{
    $result = ['ok' => false, 'error' => '', 'width' => 0, 'height' => 0];

    if (!extension_loaded('gd')) {
        $result['error'] = 'L’extension GD n’est pas installée sur le serveur.';
        return $result;
    }

    $info = @getimagesize($source);
    if (!is_array($info)) {
        $result['error'] = 'Image illisible.';
        return $result;
    }

    [$width, $height] = [(int) $info[0], (int) $info[1]];
    $mime = (string) ($info['mime'] ?? '');

    if ($width <= 0 || $height <= 0) {
        $result['error'] = 'Dimensions invalides.';
        return $result;
    }

    $maxPixels = (int) config('uploads.max_image_pixels', 24000000);
    if ($width * $height > $maxPixels) {
        $result['error'] = sprintf(
            'Photo trop grande (%d × %d pixels, maximum %d mégapixels).',
            $width,
            $height,
            (int) ($maxPixels / 1000000)
        );
        return $result;
    }

    // Estimation prudente : 5 octets par pixel (RGB + copie de travail) + 8 Mo.
    $needed = (int) ($width * $height * 5) + 8 * 1024 * 1024;
    if ($needed > memory_remaining()) {
        $result['error'] = 'Mémoire insuffisante pour traiter cette photo sur le serveur.';
        return $result;
    }

    $create = match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
        default      => null,
    };

    if ($create === null || !function_exists($create)) {
        $result['error'] = 'Format d’image non pris en charge pour la miniature.';
        return $result;
    }

    $image = @$create($source);
    if (!$image instanceof GdImage) {
        $result['error'] = 'Décodage de l’image impossible.';
        return $result;
    }

    $maxSide = max(64, (int) config('uploads.thumb_max', 480));
    $scale   = min(1.0, $maxSide / max($width, $height));
    $thumbW  = max(1, (int) round($width * $scale));
    $thumbH  = max(1, (int) round($height * $scale));

    $thumb = imagecreatetruecolor($thumbW, $thumbH);
    if (!$thumb instanceof GdImage) {
        imagedestroy($image);
        $result['error'] = 'Création de la miniature impossible.';
        return $result;
    }

    // Fond blanc puis transparence pour PNG/WebP/GIF.
    $white = imagecolorallocate($thumb, 255, 255, 255);
    imagefilledrectangle($thumb, 0, 0, $thumbW, $thumbH, $white);
    imagealphablending($thumb, true);

    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbW, $thumbH, $width, $height);

    $quality = max(50, min(95, (int) config('uploads.thumb_quality', 80)));
    $ok      = imagejpeg($thumb, $destination, $quality);

    imagedestroy($thumb);
    imagedestroy($image);

    if (!$ok) {
        $result['error'] = 'Écriture de la miniature impossible.';
        return $result;
    }
    @chmod($destination, 0640);

    $result['ok']     = true;
    $result['width']  = $thumbW;
    $result['height'] = $thumbH;
    return $result;
}
