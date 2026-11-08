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
    foreach (['medias', 'thumbs', 'incoming', 'tmp', 'locks', 'envois'] as $key) {
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
    $exts = is_array($exts) ? array_map(static fn ($e) => strtolower((string) $e), $exts) : [];

    // Les formats que le code sait réellement traiter (mime_map) sont toujours
    // acceptés, même si config.local.php contient une ancienne liste : sinon un
    // site déjà installé refuserait les fichiers .mov que la conversion sait
    // désormais traiter (les deux listes disent la même chose, en pratique).
    // Chaque extension ajoutée ici a un type réel déclaré : le contrôle par
    // finfo reste donc aussi strict.
    return array_values(array_unique(array_merge($exts, array_keys(mime_map()))));
}

/**
 * Extensions qui désignent une vidéo, même quand fileinfo ne tranche pas.
 *
 * Une seule liste pour tout le site : le type de la fiche (kind_for), les
 * fichiers en attente d'import et les filtres des pages vidéo.
 *
 * @return string[]
 */
function extensions_video(): array
{
    return ['mp4', 'm4v', 'mov', '3gp', '3g2', 'mkv', 'avi', 'mpg', 'mpeg',
        'mts', 'm2ts', 'wmv', 'flv', 'vob', 'webm'];
}

/** Extensions qui désignent un son. */
function extensions_audio(): array
{
    return ['mp3', 'm4a', 'ogg', 'wav', 'amr', 'aac', 'flac'];
}

/**
 * Correspondance extension → types MIME acceptés (vérification du type réel).
 *
 * Pour les conteneurs vidéo et audio, on raisonne en **familles** : un même
 * conteneur peut être annoncé sous plusieurs types selon la marque écrite
 * dans le fichier (un MP4 dont la marque est « M4A » est détecté
 * `audio/mp4` alors qu'il contient une vraie piste vidéo — c'était le refus
 * à tort de la 1.35.1). La famille dit quels types sont *possibles* pour ce
 * conteneur ; quand ffprobe est là, la présence réelle d'une piste vidéo
 * tranche (voir verifier_contenu_fichier()). Aucun type « passe-partout »
 * n'est admis : un exécutable, un script, un PDF ou une archive renommés
 * gardent leur type réel, qui n'est dans aucune famille.
 */
function mime_map(): array
{
    // Famille MP4 / QuickTime / 3GPP : mêmes boîtes ISO, marques différentes
    // (isom, mp42, M4A, qt, 3gp4…), types détectés variables.
    $familleMp4 = ['video/mp4', 'audio/mp4', 'audio/x-m4a', 'video/quicktime',
        'video/x-m4v', 'video/3gpp', 'audio/3gpp', 'application/mp4'];

    return [
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'mp4'  => $familleMp4,
        'm4v'  => $familleMp4,
        'mov'  => $familleMp4,
        '3gp'  => $familleMp4,
        '3g2'  => $familleMp4,
        'mkv'  => ['video/x-matroska', 'video/matroska', 'application/x-matroska'],
        'avi'  => ['video/x-msvideo', 'video/avi', 'video/vnd.avi', 'video/x-avi'],
        'mpg'  => ['video/mpeg', 'video/mpg'],
        'mpeg' => ['video/mpeg', 'video/mpg'],
        'mts'  => ['video/mp2t'],
        'm2ts' => ['video/mp2t'],
        'wmv'  => ['video/x-ms-wmv', 'video/x-ms-asf', 'video/x-ms-wm', 'application/vnd.ms-asf'],
        'flv'  => ['video/x-flv'],
        'vob'  => ['video/mpeg', 'video/dvd'],
        'webm' => ['video/webm', 'audio/webm'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/octet-stream'],
        'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip', 'application/octet-stream'],
        'mp3'  => ['audio/mpeg', 'audio/mp3'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a', 'audio/3gpp'],
        'ogg'  => ['audio/ogg', 'application/ogg', 'video/ogg'],
        // Formats que les navigateurs ne lisent pas toujours : acceptés pour
        // être convertis en M4A (AAC) par la file de conversion.
        'wav'  => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'amr'  => ['audio/amr', 'audio/3gpp', 'audio/amr-nb'],
        'aac'  => ['audio/aac', 'audio/x-aac'],
        'flac' => ['audio/flac', 'audio/x-flac'],
    ];
}

/**
 * Message simple quand le contenu réel ne correspond pas à l'extension.
 * Le détail technique (type détecté) va au journal, pas à l'écran : la
 * personne qui envoie a besoin de savoir **quoi faire**, pas un nom de type.
 */
function message_contenu_refuse(string $ext): string
{
    $extension = '.' . mb_strtoupper($ext, 'UTF-8');
    if (in_array($ext, extensions_video(), true)) {
        return 'Ce fichier a l’extension ' . $extension . ' mais son contenu réel n’est pas celui d’une vidéo. '
            . 'Vérifiez que c’est bien le bon fichier ; s’il s’agit d’un autre format, '
            . 'convertissez-le en MP4 (H.264) puis recommencez.';
    }
    if (in_array($ext, extensions_audio(), true)) {
        return 'Ce fichier a l’extension ' . $extension . ' mais son contenu réel n’est pas celui d’un son. '
            . 'Vérifiez que c’est bien le bon fichier ; sinon, convertissez-le en MP3 ou M4A puis recommencez.';
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return 'Ce fichier a l’extension ' . $extension . ' mais son contenu réel n’est pas celui d’une photo. '
            . 'Vérifiez que c’est bien le bon fichier puis recommencez.';
    }
    return 'Ce fichier a l’extension ' . $extension . ' mais son contenu réel ne correspond pas à ce format. '
        . 'Vérifiez que c’est bien le bon fichier puis recommencez.';
}

/**
 * Vérifie le contenu réel d'un fichier : type détecté dans la famille de
 * l'extension, et — quand ffprobe est disponible — pistes réellement
 * présentes pour les vidéos et les sons.
 *
 * @return array{ok:bool, error:string, mime:string, detail:string}
 */
function verifier_contenu_fichier(string $chemin, string $ext, string $mime): array
{
    $resultat = ['ok' => false, 'error' => '', 'mime' => $mime, 'detail' => ''];
    $map = mime_map();
    $connus = $map[$ext] ?? [];

    if ($connus !== [] && !in_array($mime, $connus, true)) {
        $resultat['detail'] = 'type détecté : ' . ($mime !== '' ? $mime : 'inconnu')
            . ' pour l’extension .' . $ext;
        error_log('Envoi refusé — ' . $resultat['detail'] . ' (' . basename($chemin) . ')');
        $resultat['error'] = message_contenu_refuse($ext);
        return $resultat;
    }

    // Le conteneur est le bon : reste à savoir ce qu'il contient vraiment.
    if (function_exists('conversion_sonder_media')
        && (in_array($ext, extensions_video(), true) || in_array($ext, extensions_audio(), true))) {
        $sonde = conversion_sonder_media($chemin);
        if ($sonde !== null) {
            $resultat['detail'] = 'pistes — vidéo : ' . ($sonde['video'] ? ($sonde['codec_video'] ?? 'oui') : 'aucune')
                . ', son : ' . ($sonde['audio'] ? ($sonde['codec_audio'] ?? 'oui') : 'aucun');
            if (in_array($ext, extensions_video(), true) && !$sonde['video']) {
                error_log('Envoi refusé (son seul sous extension vidéo) — ' . $resultat['detail']
                    . ' (' . basename($chemin) . ')');
                $resultat['error'] = $sonde['audio']
                    ? 'Ce fichier a l’extension .' . mb_strtoupper($ext, 'UTF-8')
                        . ' mais le site n’y a trouvé que du son. S’il contient bien une vidéo, '
                        . 'convertissez-la en MP4 (H.264) puis recommencez.'
                    : 'Ce fichier a l’extension .' . mb_strtoupper($ext, 'UTF-8')
                        . ' mais le site n’y a trouvé ni image ni son utilisable. Il est peut-être '
                        . 'abîmé ou incomplet : convertissez-le en MP4 (H.264) puis recommencez.';
                return $resultat;
            }
            if (in_array($ext, extensions_audio(), true) && $sonde['video']) {
                error_log('Envoi refusé (vidéo sous extension audio) — ' . $resultat['detail']
                    . ' (' . basename($chemin) . ')');
                $resultat['error'] = 'Ce fichier a l’extension .' . mb_strtoupper($ext, 'UTF-8')
                    . ' mais il contient une image de vidéo. Pour l’envoyer comme vidéo, '
                    . 'renommez-le en .MP4 ; pour n’en garder que le son, convertissez-le en MP3 ou M4A.';
                return $resultat;
            }
        }
    }

    $resultat['ok'] = true;
    return $resultat;
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
        $result['error'] = 'Le fichier est vide (0 octet) : il n’y a rien à envoyer. '
            . 'Choisissez un fichier complet puis recommencez.';
        return $result;
    }
    if ($size > upload_max_bytes()) {
        $result['error'] = 'Fichier trop volumineux (maximum ' . format_size(upload_max_bytes()) . '). '
            . 'Réduisez sa taille — par exemple en le convertissant — puis recommencez.';
        return $result;
    }

    $original = (string) ($file['name'] ?? 'fichier');
    $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if ($ext === '' || !in_array($ext, allowed_extensions(), true)) {
        $result['error'] = 'Extension non autorisée (' . ($ext !== '' ? $ext : 'aucune') . '). '
            . 'Vous pouvez envoyer des photos (JPG, PNG), des vidéos (MP4, MOV, AVI…), '
            . 'des sons (MP3, M4A…) et des documents (PDF, DOCX…). '
            . 'Convertissez votre fichier dans l’un de ces formats puis recommencez.';
        return $result;
    }

    if (!class_exists('finfo')) {
        $result['error'] = 'L’extension PHP « fileinfo » est requise sur le serveur.';
        return $result;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);

    $controle = verifier_contenu_fichier((string) $file['tmp_name'], $ext, $mime);
    if (!$controle['ok']) {
        $result['error'] = $controle['error'];
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
        $result['error'] = 'Extension non autorisée (' . ($ext !== '' ? $ext : 'aucune') . '). '
            . 'Convertissez le fichier dans un format accepté (MP4, MOV, MP3, PDF…) puis recommencez.';
        return $result;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($path);
    $controle = verifier_contenu_fichier($path, $ext, $mime);
    if (!$controle['ok']) {
        $result['error'] = $controle['error'];
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
    // Repli par l'extension : certains conteneurs (.mkv, .3gp) ne sont pas
    // toujours reconnus par fileinfo, et un film reste un film.
    if (in_array($ext, extensions_video(), true)) {
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
/**
 * État de l'espace disque, en trois couleurs.
 *
 * Trois seuils, lisibles par n'importe qui :
 *   - **rouge**  : moins de 5 Go libres, OU moins de 10 % de la carte ;
 *   - **orange** : moins de 20 % de la carte ;
 *   - **vert**   : le reste.
 *
 * Le disque d'un Pi 3B+ fait souvent 32 Go : 5 Go représentent alors 15 %.
 * Les deux règles se complètent donc : le pourcentage protège une grosse
 * carte, les 5 Go protègent une petite. Aucun blocage : l'alerte se contente
 * de prévenir avant que la base et les envois ne tombent en panne.
 *
 * Les deux paramètres servent aux tests : -1 (le défaut) signifie « interroge
 * le disque », null signifie « espace inconnu », toute autre valeur impose le
 * chiffre. Cela évite qu'un essai
 * dépende de la taille réelle du disque de la machine.
 *
 * @param int|null $libre espace libre à considérer (-1 = interroger, null = inconnu)
 * @param int $total taille totale à considérer (-1 = interroger le disque)
 * @return array{libre:?int, total:?int, pourcentage:?float, niveau:string, message:string}
 */
function storage_space_state(?int $libre = -1, int $total = -1): array
{
    $racine = (string) config('storage.root');
    $libre  = $libre === -1 ? storage_free_space() : $libre;

    $total  = $total !== -1 ? $total : null;
    if ($total === null && $racine !== '' && is_dir($racine)) {
        $valeur = @disk_total_space($racine);
        if ($valeur !== false) {
            $total = (int) $valeur;
        }
    }

    if ($libre === null) {
        return [
            'libre'       => null,
            'total'       => $total,
            'pourcentage' => null,
            'niveau'      => 'inconnu',
            'message'     => 'Espace disque inconnu : impossible de prévenir à l’avance.',
        ];
    }

    $pourcentage = $total !== null && $total > 0 ? ($libre * 100) / $total : null;
    // Le plancher en gigaoctets ne sert que si l'on connaît la taille de la
    // carte : sans elle, 5 Go sur un disque de 64 Go ne veulent rien dire.

    $seuilGo     = 5 * 1024 * 1024 * 1024;

    if (($total !== null && $libre < $seuilGo) || ($pourcentage !== null && $pourcentage < 10)) {
        return [
            'libre'       => $libre,
            'total'       => $total,
            'pourcentage' => $pourcentage,
            'niveau'      => 'rouge',
            'message'     => 'Espace disque presque plein : ' . format_size($libre) . ' libres'
                . ($pourcentage !== null ? ' (' . number_format($pourcentage, 0, ',', ' ') . ' %)' : ''),
        ];
    }

    if ($pourcentage !== null && $pourcentage < 20) {
        return [
            'libre'       => $libre,
            'total'       => $total,
            'pourcentage' => $pourcentage,
            'niveau'      => 'orange',
            'message'     => 'Espace disque qui baisse : ' . format_size($libre) . ' libres ('
                . number_format($pourcentage, 0, ',', ' ') . ' %)',
        ];
    }

    return [
        'libre'       => $libre,
        'total'       => $total,
        'pourcentage' => $pourcentage,
        'niveau'      => 'vert',
        'message'     => 'Espace disque suffisant : ' . format_size($libre) . ' libres'
            . ($pourcentage !== null ? ' (' . number_format($pourcentage, 0, ',', ' ') . ' %)' : ''),
    ];
}

function storage_free_space(): ?int
{
    $path = (string) config('storage.root');
    if (!is_dir($path)) {
        return null;
    }
    $free = @disk_free_space($path);
    return $free === false ? null : (int) $free;
}
