<?php
/**
 * Configuration de l'application — MODÈLE À COPIER
 * -------------------------------------------------
 * 1. Copiez ce fichier en « config.local.php » (dans le même dossier).
 * 2. Adaptez les chemins et les valeurs.
 * 3. Ne mettez JAMAIS config.local.php dans Git (il est déjà dans .gitignore).
 *
 * config.local.php ne contient pas de mot de passe : les comptes sont créés
 * par la commande « php app/cli/create-admin.php » et stockés dans la base.
 */

return [

    // ------------------------------------------------------------------
    // Identité du site
    // ------------------------------------------------------------------
    'site' => [
        'name'        => 'Histoire de famille',            // titre affiché
        'subtitle'    => 'Les souvenirs de notre famille', // sous-titre
        'url'         => 'https://histoiredefamille.eu',   // adresse publique (sans /)
        'description' => 'Documents, photos, vidéos et arbre généalogique de la famille.',
        'contact'     => '',                               // adresse e-mail de contact (facultatif)
    ],

    // Mettre à true en production (le site est servi en HTTPS par Cloudflare).
    // Met le drapeau « Secure » sur le cookie de session.
    'https_only' => true,

    // Mettre à false en production : n'affiche jamais les détails d'une erreur PHP.
    'debug' => false,

    // Fuseau horaire utilisé pour l'affichage des dates.
    'timezone' => 'Europe/Paris',

    // ------------------------------------------------------------------
    // Base de données et stockage (sur le SSD USB, HORS de la racine web)
    // ------------------------------------------------------------------
    'database' => '/mnt/ssd/histoiredefamille/histoire.sqlite',

    'storage' => [
        // Dossier racine du stockage (sur le SSD). Tout ce qui est dedans
        // est inaccessible par URL : les fichiers passent par PHP.
        'root'     => '/mnt/ssd/histoiredefamille',
        'medias'   => 'medias',     // fichiers originaux (documents, photos, vidéos)
        'thumbs'   => 'miniatures', // miniatures JPEG générées à l'envoi
        'incoming' => 'arrivee',    // dossier « arrivée » (dépôt SFTP/Samba des gros films)
        'tmp'      => 'tmp',        // fichiers temporaires (import GEDCOM, etc.)
        'locks'    => 'verrous',    // verrous des lectures vidéo simultanées
    ],

    // ------------------------------------------------------------------
    // Envoi de fichiers par le navigateur
    // ------------------------------------------------------------------
    'uploads' => [
        // Taille maximale d'un fichier envoyé par le navigateur (Mo).
        // Cloudflare limite à ~100 Mo par requête : ne pas dépasser 90.
        'max_mb' => 50,

        // Extensions acceptées (le type réel est vérifié en plus avec finfo).
        'allowed_extensions' => [
            'pdf', 'txt',
            'jpg', 'jpeg', 'png', 'webp', 'gif',
            'mp4', 'm4v',
            'docx', 'xlsx', 'odt', 'ods',
            'mp3', 'm4a', 'ogg',
        ],

        // Sécurité mémoire (le Pi 3B+ n'a que 1 Go de RAM) : au-delà de ce
        // nombre de pixels, la photo est refusée (la librairie GD doit charger
        // l'image entière en mémoire). 24 Mpx ≈ 4000 × 6000.
        'max_image_pixels' => 24000000,

        // Taille maximale de la miniature générée (bord le plus long, en px).
        'thumb_max' => 480,
        'thumb_quality' => 80,

        // Taille maximale (Mo) d'un fichier GEDCOM importé.
        'max_gedcom_mb' => 25,
    ],

    // ------------------------------------------------------------------
    // Vidéos
    // ------------------------------------------------------------------
    'video' => [
        // Nombre de lectures simultanées autorisées (le Pi doit aussi servir
        // les pages). 2 est raisonnable sur un Pi 3B+ avec de l'USB 2.
        'max_concurrent_streams' => 2,

        // Taille des morceaux envoyés au navigateur (octets).
        'chunk_size' => 262144, // 256 Ko

        // Durée (secondes) pendant laquelle un verrou de lecture est
        // considéré comme actif sans nouvelle activité.
        'lock_timeout' => 120,
    ],

    // ------------------------------------------------------------------
    // Sécurité / connexion
    // ------------------------------------------------------------------
    'security' => [
        'login_max_attempts'    => 5,   // échecs autorisés
        'login_window_minutes'  => 15,  // fenêtre d'observation
        'login_block_minutes'   => 15,  // durée du blocage
        'session_idle_minutes'  => 240, // déconnexion après inactivité (4 h)
        'session_absolute_hours' => 168, // durée maximale d'une session (7 jours)

        // Argon2id si PHP le propose, sinon bcrypt.
        'password_algorithm' => 'auto',
        'bcrypt_cost' => 12,
        'argon2_memory_kb' => 16384,
        'argon2_time_cost' => 2,
        'argon2_threads' => 1,
    ],

    // ------------------------------------------------------------------
    // Sauvegarde (utilisé par deploy/backup.sh et la page « État du site »)
    // ------------------------------------------------------------------
    'maintenance' => [
        'backup_dir'   => '/mnt/sauvegarde/histoiredefamille',
        'backup_log'   => '/mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log',
        'backup_state' => '/mnt/sauvegarde/histoiredefamille/dernier-etat.json',
    ],

    // Nombre d'éléments par page dans les listes.
    'per_page' => 24,
];
