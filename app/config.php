<?php
/**
 * Chargement de la configuration.
 *
 * Les valeurs par défaut sont définies ici ; le fichier config.local.php
 * (non versionné) les remplace, clé par clé (fusion récursive).
 *
 * Variable d'environnement HDF_CONFIG : chemin vers un autre fichier de
 * configuration (utilisée pour les tests locaux).
 */

declare(strict_types=1);

/**
 * Valeurs par défaut : le site fonctionne sans config.local.php uniquement
 * pour l'installation locale (les chemins pointent alors vers ./storage).
 */
function config_defaults(): array
{
    $root = dirname(__DIR__);

    return [
        'site' => [
            'name'        => 'Histoire de famille',
            'subtitle'    => '',
            'url'         => '',
            'description' => '',
            'contact'     => '',
        ],
        'https_only' => true,
        'debug'      => false,
        'timezone'   => 'Europe/Paris',
        'database'   => $root . '/storage/histoire.sqlite',
        'storage'    => [
            'root'     => $root . '/storage',
            'medias'   => 'medias',
            'thumbs'   => 'miniatures',
            'incoming' => 'arrivee',
            'tmp'      => 'tmp',
            'locks'    => 'verrous',
        ],
        'uploads' => [
            'max_mb'             => 50,
            'allowed_extensions' => [
                'pdf', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif',
                'mp4', 'm4v', 'docx', 'xlsx', 'odt', 'ods', 'mp3', 'm4a', 'ogg',
            ],
            'max_image_pixels' => 24000000,
            'thumb_max'        => 480,
            'thumb_quality'    => 80,
            'max_gedcom_mb'    => 25,
        ],
        'video' => [
            'max_concurrent_streams' => 2,
            'chunk_size'             => 262144,
            'lock_timeout'           => 120,
        ],
        'security' => [
            'login_max_attempts'     => 5,
            'login_window_minutes'   => 15,
            'login_block_minutes'    => 15,
            'session_idle_minutes'   => 240,
            'session_absolute_hours' => 168,
            'password_algorithm'     => 'auto',
            'bcrypt_cost'            => 12,
            'argon2_memory_kb'       => 16384,
            'argon2_time_cost'       => 2,
            'argon2_threads'         => 1,
        ],
        'maintenance' => [
            'backup_dir'   => '/mnt/sauvegarde/histoiredefamille',
            'backup_log'   => '/mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log',
            'backup_state' => '/mnt/sauvegarde/histoiredefamille/dernier-etat.json',
        ],
        'per_page' => 24,
    ];
}

/** Chemin du fichier de configuration local. */
function config_file_path(): string
{
    $fromEnv = getenv('HDF_CONFIG');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }
    return dirname(__DIR__) . '/config.local.php';
}

/** Fusion récursive : les valeurs de $override écrasent celles de $base. */
function config_merge(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = config_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

/**
 * Retourne toute la configuration (ou une valeur via « a.b.c »).
 * Le résultat est mis en cache pour la durée de la requête.
 */
function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = config_defaults();
        $file   = config_file_path();
        if (is_file($file)) {
            /** @var array $local */
            $local  = require $file;
            $config = config_merge($config, $local);
        }
        $config['_config_file']       = $file;
        $config['_config_file_exists'] = is_file($file);
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Le fichier config.local.php est-il présent ? */
function is_configured(): bool
{
    return (bool) config('_config_file_exists');
}
