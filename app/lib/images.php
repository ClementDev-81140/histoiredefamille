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

// ---------------------------------------------------------------------------
// Miniatures des vidéos (1.32.0)
// ---------------------------------------------------------------------------
//
// Une vidéo n'a pas de miniature : ffmpeg en extrait une image, une seule fois,
// et le site la range comme les autres miniatures (dossier `thumbs`, même
// dimensionnement). Plus de carré gris dans les listes.
//
// Règles tenues ici :
//   - ffmpeg absent ou en échec n'empêche rien : l'appelant garde la vidéo
//     telle quelle et l'affichage retombe sur une icône de lecture sobre ;
//   - un seul fichier à la fois, et ffmpeg tourne en priorité basse (comme la
//     conversion) : le Pi doit rester utilisable ;
//   - la miniature suit EXACTEMENT la visibilité de la vidéo : elle est servie
//     par `/media/miniature/{id}`, qui applique la même règle que la vidéo.

/** Largeur de la miniature extraite (la hauteur suit les proportions). */
const VIDEO_MINIATURE_LARGEUR = 480;

/**
 * Instant de l'image extraite : 1 seconde, ou 10 % de la durée si la vidéo
 * fait moins de dix secondes (une image à 1 s serait alors la fin du film).
 */
function video_miniature_position(?int $duree): float
{
    if ($duree === null || $duree <= 0) {
        return 1.0;
    }
    if ($duree >= 10) {
        return 1.0;
    }

    return round(max(0.1, $duree * 0.1), 2);
}

/**
 * Commande ffmpeg qui sort UNE image, construite en tableau (aucun shell).
 *
 * @param list<string> $avant commande déjà préfixée (nice), facultatif
 * @return list<string>
 */
function video_miniature_commande(string $ffmpeg, string $source, string $destination, float $position): array
{
    return [
        $ffmpeg,
        '-nostdin',
        '-y',
        '-ss', number_format($position, 2, '.', ''),
        '-i', $source,
        '-frames:v', '1',
        '-vf', 'scale=' . VIDEO_MINIATURE_LARGEUR . ':-2',
        '-q:v', '4',
        '-f', 'image2',
        $destination,
    ];
}

/**
 * Extrait l'image d'une vidéo vers un fichier JPEG.
 *
 * @param string|null $ffmpeg chemin de ffmpeg (null = réglages du site)
 * @return array{ok:bool, error:string, absent:bool}
 */
function video_miniature_extraire(string $sourceAbs, string $destinationAbs, ?int $duree = null, ?string $ffmpeg = null): array
{
    $resultat = ['ok' => false, 'error' => '', 'absent' => false];

    if ($ffmpeg === null) {
        $outils = conversion_outils();
        $ffmpeg = $outils['ffmpeg'];
    }
    if ($ffmpeg === null || $ffmpeg === '') {
        return [
            'ok'     => false,
            'absent' => true,
            'error'  => 'ffmpeg n’est pas installé sur le serveur : les vidéos gardent une icône de lecture '
                . 'à la place d’une image. Installez-le avec « sudo apt-get install -y ffmpeg ».',
        ];
    }
    if (!is_file($sourceAbs)) {
        return ['ok' => false, 'absent' => false, 'error' => 'Le fichier de la vidéo est introuvable sur le disque.'];
    }
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'absent' => true, 'error' => 'La fonction proc_open est désactivée dans PHP : impossible de lancer ffmpeg.'];
    }

    @mkdir(dirname($destinationAbs), 0770, true);
    $commande = conversion_commande_nicee(
        video_miniature_commande($ffmpeg, $sourceAbs, $destinationAbs, video_miniature_position($duree)),
        conversion_reglages()
    );

    $sortie = [];
    $tube   = [];
    $processus = @proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tube);
    if (!is_resource($processus)) {
        return ['ok' => false, 'absent' => false, 'error' => 'ffmpeg n’a pas pu démarrer.'];
    }
    $sortie[] = (string) stream_get_contents($tube[1]);
    $sortie[] = (string) stream_get_contents($tube[2]);
    fclose($tube[1]);
    fclose($tube[2]);
    $code = proc_close($processus);

    if ($code !== 0 || !is_file($destinationAbs) || (int) @filesize($destinationAbs) <= 0) {
        @unlink($destinationAbs);
        return [
            'ok'     => false,
            'absent' => false,
            'error'  => 'ffmpeg n’a pas pu extraire d’image (code ' . $code . ') : '
                . mb_substr(trim(implode(' ', $sortie)), 0, 300, 'UTF-8'),
        ];
    }
    @chmod($destinationAbs, 0640);

    return ['ok' => true, 'absent' => false, 'error' => ''];
}

/**
 * Crée la miniature d'une vidéo déjà enregistrée et la note sur sa fiche.
 *
 * Ne touche à rien si la fiche n'est pas une vidéo, si elle a déjà une
 * miniature, ou si le fichier n'est pas encore prêt (conversion en cours).
 *
 * @return array{ok:bool, error:string, absent:bool, thumb_path:?string}
 */
function video_miniature_document(int $documentId, bool $remplacer = false): array
{
    $vide = ['ok' => false, 'error' => '', 'absent' => false, 'thumb_path' => null];

    $doc = db_one('SELECT * FROM documents WHERE id = ?', [$documentId]);
    if ($doc === null || (string) $doc['kind'] !== 'video') {
        return $vide + ['error' => 'Cette fiche n’est pas une vidéo.'];
    }
    if (!$remplacer && trim((string) ($doc['thumb_path'] ?? '')) !== '') {
        return ['ok' => true, 'error' => '', 'absent' => false, 'thumb_path' => (string) $doc['thumb_path']];
    }
    if (!conversion_document_pret($doc)) {
        return $vide + ['error' => 'La vidéo n’est pas encore prête (conversion en cours).'];
    }

    $duree = isset($doc['duration_seconds']) ? (int) $doc['duration_seconds'] : null;
    $rel   = storage_target_thumb_path();
    $essai = video_miniature_extraire(
        storage_absolute((string) $doc['rel_path']),
        storage_absolute($rel),
        $duree
    );
    if (!$essai['ok']) {
        return ['ok' => false, 'error' => $essai['error'], 'absent' => $essai['absent'], 'thumb_path' => null];
    }

    db_update('documents', $documentId, ['thumb_path' => $rel, 'updated_at' => time()]);

    return ['ok' => true, 'error' => '', 'absent' => false, 'thumb_path' => $rel];
}

/**
 * Vidéos prêtes qui n'ont pas encore de miniature (pour la commande de
 * rattrapage). Les plus récentes d'abord, une seule à la fois ensuite.
 *
 * @return list<array>
 */
function video_miniature_a_faire(int $limite = 500): array
{
    $limite = max(1, min(5000, $limite));

    return db_all(
        "SELECT d.* FROM documents d
         WHERE d.kind = 'video' AND d.is_attachment = 0
           AND (d.thumb_path IS NULL OR d.thumb_path = '')
           AND " . conversion_pretes_sql('d') . "
         ORDER BY d.created_at DESC
         LIMIT {$limite}"
    );
}
