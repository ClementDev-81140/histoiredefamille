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
        // Phrase de la page d'accueil et description pour les moteurs de
        // recherche. Elle ne parle pas de l'arbre : un visiteur ne le voit pas.
        'description' => 'Les souvenirs de la famille : documents, photos, récits et nouvelles.',
        // Adresses AFFICHÉES seulement (le site ne s'en sert jamais pour
        // écrire). Elles se règlent dans Administration → Réglages, qui les
        // range dans la base ; ces deux lignes ne servent que de valeurs de
        // départ si le réglage n'existe pas encore. Un champ vide dans les
        // réglages remet l'adresse livrée avec le site.
        'contact'     => 'contact@histoiredefamille.eu',   // « Une question ? », accueil
        'support'     => 'support@histoiredefamille.eu',   // mot de passe oublié, aide
        // Le site n'envoie aucun courriel par défaut (voir README § 14). Ne
        // passez à '1' qu'après avoir rempli le bloc « mail » plus bas.
        'courriels_actifs' => '0',
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
            // Vidéos d'archives (converties en MP4) et sons.
            'mov', 'avi', 'mkv', 'webm', '3gp', '3g2', 'mpg', 'mpeg',
            'mts', 'm2ts', 'wmv', 'flv', 'vob', 'wav', 'amr', 'aac', 'flac',
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
    // Sauvegarde (commande php app/cli/sauvegarde.php et page « État du site »)
    // ------------------------------------------------------------------
    // La clé USB est montée sur /mnt/sauvegarde (voir deploy/montage-disques.md).
    // Le dossier ci-dessous doit se trouver SUR LA CLÉ, jamais sur la carte SD.
    'maintenance' => [
        'backup_dir'   => '/mnt/sauvegarde/histoiredefamille',
        'backup_log'   => '/mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log',
        'backup_state' => '/mnt/sauvegarde/histoiredefamille/dernier-etat.json',
        // Date du dernier essai de restauration (écrit par restauration.php).
        'restore_state' => '/mnt/sauvegarde/histoiredefamille/derniere-restauration.json',

        // Rotation : 7 copies quotidiennes, 4 hebdomadaires, 6 mensuelles.
        'backup_daily'   => 7,
        'backup_weekly'  => 4,
        'backup_monthly' => 6,

        // Marge libre exigée sur la clé avant de commencer (Mo).
        'backup_min_free_mb' => 512,

        // true = refuse d'écrire si la clé n'est pas montée (recommandé :
        // sinon la sauvegarde atterrit sur la carte SD et la remplit).
        'backup_require_mount' => true,

        // Premiers jours sans disque externe : décommenter la ligne suivante
        // pour accepter une sauvegarde écrite sur la carte SD, à côté du site.
        // Ce n'est PAS une sauvegarde : elle ne protège d'aucune panne de la
        // carte. L'administration affiche un bandeau rouge tant que le mode
        // est actif, et `php app/cli/verifier-mise-en-ligne.php` le signale
        // en avertissement (jamais bloquant). À retirer dès que le disque
        // arrive.
        // 'backup_allow_same_disk' => true,

        // Alerte rouge dans l'administration : sauvegarde trop ancienne
        // (jours) ou espace libre faible (Mo).
        'backup_alert_days'    => 3,
        'backup_alert_free_mb' => 512,
    ],

    // ------------------------------------------------------------------
    // Courriels (facultatif)
    // ------------------------------------------------------------------
    // Le site n'envoie des courriels que si ce bloc est rempli ET si la
    // personne a coché son accord. **Le site n'envoie aucun courriel par
    // défaut** (depuis la 1.28.0) : remplir ce bloc ne suffit donc pas, il faut
    // aussi cocher « Le site envoie des courriels » dans Administration →
    // Réglages. Tant que l'envoi est désactivé, rien n'entre dans la file et
    // tout le reste du site fonctionne (tchat, notifications dans le site).
    //
    // Où trouver ces renseignements ? Voir README, section
    // « Envoyer des courriels » : ce sont les paramètres SMTP de votre
    // fournisseur de messagerie (Orange, Free, Gmail, OVH, Infomaniak…).
    'mail' => [
        // Serveur d'envoi (exemple : « smtp.exemple.fr »).
        'host'       => '',

        // Port : 587 avec STARTTLS (le plus courant), 465 avec TLS implicite.
        'port'       => 587,

        // 'tls' (STARTTLS), 'ssl' (TLS implicite, port 465) ou 'none'.
        // « none » n'est accepté que si le serveur est sur votre réseau local.
        'encryption' => 'tls',

        // Identifiant et mot de passe fournis par le service d'envoi.
        // Ils restent dans ce fichier, jamais dans la base ni dans les journaux.
        'username'   => '',
        'password'   => '',

        // Adresse d'expéditeur : elle doit être autorisée par le service
        // d'envoi (souvent la même adresse que l'identifiant ci-dessus).
        'from_address' => '',
        'from_name'    => 'Histoire de famille',

        // Délai d'attente maximum, en secondes (3 à 30).
        'timeout' => 10,
    ],

    // Nombre d'éléments par page dans les listes.
    'per_page' => 24,
];
