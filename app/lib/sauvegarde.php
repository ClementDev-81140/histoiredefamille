<?php
/**
 * Sauvegarde des données (base + fichiers) vers la clé USB.
 *
 * Règles tenues par ce fichier :
 *  - la base est copiée avec l'API de SQLite (« VACUUM INTO ») : la copie est
 *    cohérente même pendant que le site tourne. On ne fait JAMAIS « cp » d'un
 *    fichier SQLite en mode WAL (le fichier seul serait incomplet) ;
 *  - l'intégrité de la copie est vérifiée (PRAGMA integrity_check) avant de la
 *    garder ;
 *  - les fichiers (photos, vidéos, PDF, témoignages, dossier arrivée) sont
 *    copiés de façon incrémentale : seuls les fichiers nouveaux ou modifiés
 *    sont réécrits ;
 *  - RIEN n'est écrit si la clé USB n'est pas montée : sans cette précaution,
 *    la sauvegarde remplirait la carte SD du Raspberry Pi ;
 *  - une sauvegarde en cours est fabriquée dans un dossier temporaire, puis
 *    déplacée d'un bloc : l'ancienne sauvegarde reste intacte jusqu'au bout ;
 *  - `config.local.php` n'est JAMAIS copié avec les données (il contient les
 *    chemins et les réglages de sécurité ; il se conserve à part).
 *
 * Les fonctions reçoivent leurs réglages en paramètre (voir sauvegarde_options)
 * pour rester testables sans toucher à la vraie configuration.
 */

declare(strict_types=1);

/** Réglages effectifs de la sauvegarde (configuration, puis $override). */
function sauvegarde_options(array $override = []): array
{
    $dir = rtrim((string) config('maintenance.backup_dir', ''), '/');

    $options = [
        'backup_dir'      => $dir,
        'log'             => (string) config('maintenance.backup_log', $dir . '/journal-sauvegarde.log'),
        'state'           => (string) config('maintenance.backup_state', $dir . '/dernier-etat.json'),
        'restore_state'   => (string) config('maintenance.restore_state', $dir . '/derniere-restauration.json'),
        'database'        => (string) config('database'),
        'storage_root'    => rtrim((string) config('storage.root'), '/'),
        'exclude'         => ['tmp', 'verrous'],
        // La base est souvent à l'intérieur du dossier de données : elle est
        // sauvegardée à part (copie cohérente), jamais par la copie de fichiers.
        'exclude_files'   => [],
        'quotidiennes'    => (int) config('maintenance.backup_daily', 7),
        'hebdomadaires'   => (int) config('maintenance.backup_weekly', 4),
        'mensuelles'      => (int) config('maintenance.backup_monthly', 6),
        'min_free_mb'     => (int) config('maintenance.backup_min_free_mb', 512),
        'exiger_montage'  => (bool) config('maintenance.backup_require_mount', true),
        'utiliser_rsync'  => true,
    ];

    return array_replace($options, $override);
}

/**
 * Fichiers du stockage à ne pas copier avec la sauvegarde de fichiers :
 * la base de données (et ses fichiers WAL/SHM), qu'on sauvegarde à part,
 * de façon cohérente.
 *
 * @return string[] chemins relatifs au dossier de stockage
 */
function sauvegarde_fichiers_exclus(array $options): array
{
    $racine = rtrim((string) $options['storage_root'], '/');
    $base   = (string) $options['database'];

    $exclus = (array) ($options['exclude_files'] ?? []);
    if ($racine !== '' && str_starts_with($base, $racine . '/')) {
        $relatif = ltrim(substr($base, strlen($racine)), '/');
        $exclus[] = $relatif;
        $exclus[] = $relatif . '-wal';
        $exclus[] = $relatif . '-shm';
        $exclus[] = $relatif . '-journal';
    }

    return array_values(array_unique(array_filter($exclus, static fn ($f) => (string) $f !== '')));
}

/** Taille lisible en français (2,4 Mo). */
function sauvegarde_taille(int $octets): string
{
    if ($octets <= 0) {
        return '0 o';
    }
    if ($octets >= 1073741824) {
        return number_format($octets / 1073741824, 1, ',', ' ') . ' Go';
    }
    if ($octets >= 1048576) {
        return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
    }
    if ($octets >= 1024) {
        return number_format($octets / 1024, 0, ',', ' ') . ' Ko';
    }
    return $octets . ' o';
}

/**
 * Premier dossier existant en remontant depuis $path (pour connaître le
 * disque d'un dossier qui n'existe pas encore).
 */
function sauvegarde_ancetre_existant(string $path): string
{
    $courant = rtrim($path, '/');
    while ($courant !== '' && !is_dir($courant)) {
        $parent = dirname($courant);
        if ($parent === $courant) {
            break;
        }
        $courant = $parent;
    }
    return is_dir($courant) ? $courant : '';
}

/**
 * Point de montage d'un dossier (le plus précis), d'après /proc/self/mounts.
 * Renvoie '' si l'information n'est pas disponible.
 */
function sauvegarde_point_de_montage(string $path): string
{
    $reel = realpath($path);
    if ($reel === false) {
        return '';
    }

    $lignes = @file('/proc/self/mounts', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lignes)) {
        return '';
    }

    $meilleur = '';
    foreach ($lignes as $ligne) {
        $parts = preg_split('/\s+/', trim($ligne)) ?: [];
        if (count($parts) < 2) {
            continue;
        }
        // Les espaces sont écrits « \040 » dans /proc/self/mounts.
        $point = str_replace('\\040', ' ', $parts[1]);
        $point = rtrim($point, '/');
        if ($point === '') {
            $point = '/';
        }
        if ($reel === $point || str_starts_with($reel, ($point === '/' ? '' : $point) . '/')) {
            if (strlen($point) > strlen($meilleur)) {
                $meilleur = $point;
            }
        }
    }

    return $meilleur;
}

/**
 * Le dossier de sauvegarde est-il sur un disque distinct des données ?
 *
 * C'est la sécurité la plus importante : si la clé USB n'est pas montée, son
 * dossier d'accueil existe quand même sur la carte SD. Écrire là remplirait la
 * carte et ferait perdre à la fois le site et sa sauvegarde.
 *
 * @return array{ok: bool, raison: string, montage: string}
 */
function sauvegarde_support_ok(array $options): array
{
    $dir      = (string) $options['backup_dir'];
    $donnees  = (string) $options['storage_root'];
    $base     = (string) $options['database'];

    if ($dir === '') {
        return ['ok' => false, 'raison' => 'Le dossier de sauvegarde n’est pas indiqué (maintenance.backup_dir).', 'montage' => ''];
    }

    $reelDir = realpath(sauvegarde_ancetre_existant($dir));
    if ($reelDir === false || $reelDir === '') {
        return ['ok' => false, 'raison' => 'Le dossier de sauvegarde n’existe pas encore : ' . $dir, 'montage' => ''];
    }

    foreach ([$donnees, dirname($base)] as $interdit) {
        $reelInterdit = realpath($interdit);
        if ($reelInterdit === false) {
            continue;
        }
        if ($reelDir === $reelInterdit || str_starts_with($reelDir . '/', rtrim($reelInterdit, '/') . '/')) {
            return [
                'ok'     => false,
                'raison' => 'Le dossier de sauvegarde est à l’intérieur des données du site ('
                    . $reelInterdit . '). Choisissez la clé USB.',
                'montage' => '',
            ];
        }
    }

    $montage = sauvegarde_point_de_montage($reelDir);

    if (empty($options['exiger_montage'])) {
        return ['ok' => is_dir($reelDir) && is_writable($reelDir),
            'raison' => is_dir($reelDir) && is_writable($reelDir) ? '' : 'Dossier non inscriptible : ' . $reelDir,
            'montage' => $montage];
    }

    if ($montage === '') {
        return [
            'ok'     => false,
            'raison' => 'Impossible de savoir si la clé USB est montée. Vérifiez « df -h ' . $dir . ' ».',
            'montage' => '',
        ];
    }

    if ($montage === '/') {
        return [
            'ok'     => false,
            'raison' => 'La clé USB n’est pas montée : ' . $dir . ' se trouve sur la carte SD du Raspberry Pi.',
            'montage' => $montage,
        ];
    }

    // Ceinture et bretelles : le disque de la clé doit être différent de
    // celui des données (même si le point de montage semble bon).
    $donneesExistantes = sauvegarde_ancetre_existant($donnees);
    $statCle   = @stat($reelDir);
    $statDonnees = $donneesExistantes !== '' ? @stat($donneesExistantes) : false;
    if (is_array($statCle) && is_array($statDonnees) && $statCle['dev'] === $statDonnees['dev']) {
        return [
            'ok'     => false,
            'raison' => 'La sauvegarde et les données sont sur le même disque : la clé USB n’est pas montée.',
            'montage' => $montage,
        ];
    }

    if (!is_writable($reelDir)) {
        return ['ok' => false, 'raison' => 'Dossier de sauvegarde non inscriptible : ' . $dir, 'montage' => $montage];
    }

    return ['ok' => true, 'raison' => '', 'montage' => $montage];
}

/** Espace libre en octets sur un dossier (null si inconnu). */
function sauvegarde_espace_libre(string $path): ?int
{
    $existant = sauvegarde_ancetre_existant($path);
    if ($existant === '') {
        return null;
    }
    $libre = @disk_free_space($existant);
    return $libre === false ? null : (int) $libre;
}

/**
 * Copie cohérente de la base avec « VACUUM INTO », puis contrôle d'intégrité.
 *
 * @return array{ok: bool, message: string, chemin: string, taille: int}
 */
function sauvegarde_copier_base(string $base, string $destination, ?callable $trace = null): array
{
    if (!is_file($base)) {
        return ['ok' => false, 'message' => 'Base de données introuvable : ' . $base, 'chemin' => '', 'taille' => 0];
    }
    if (file_exists($destination)) {
        @unlink($destination);
    }
    if (!is_dir(dirname($destination)) && !@mkdir(dirname($destination), 0770, true) && !is_dir(dirname($destination))) {
        return ['ok' => false, 'message' => 'Impossible de créer ' . dirname($destination), 'chemin' => '', 'taille' => 0];
    }

    try {
        $source = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $source->exec('PRAGMA busy_timeout = 10000');
        // VACUUM INTO : SQLite écrit une copie complète et cohérente, sans
        // bloquer longtemps le site (les lectures continuent).
        $source->exec('VACUUM INTO ' . $source->quote($destination));
        $source = null;
    } catch (Throwable $e) {
        return [
            'ok'      => false,
            'message' => 'La copie de la base a échoué : ' . $e->getMessage(),
            'chemin'  => '',
            'taille'  => 0,
        ];
    }

    if ($trace !== null) {
        $trace('Base copiée, contrôle d’intégrité…');
    }

    try {
        $copie = new PDO('sqlite:' . $destination, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $resultat = $copie->query('PRAGMA integrity_check')->fetchColumn();
        $copie = null;
    } catch (Throwable $e) {
        @unlink($destination);
        return ['ok' => false, 'message' => 'Contrôle d’intégrité impossible : ' . $e->getMessage(), 'chemin' => '', 'taille' => 0];
    }

    if (strtolower(trim((string) $resultat)) !== 'ok') {
        @unlink($destination);
        return [
            'ok'      => false,
            'message' => 'La copie de la base est abîmée (contrôle d’intégrité : ' . $resultat . '). Elle n’est pas conservée.',
            'chemin'  => '',
            'taille'  => 0,
        ];
    }

    return ['ok' => true, 'message' => '', 'chemin' => $destination, 'taille' => (int) filesize($destination)];
}

/**
 * Copie incrémentale des fichiers du stockage.
 *
 * rsync est utilisé s'il est installé (il ne réécrit que le changement) ;
 * sinon une copie en PHP fait le même travail, fichier par fichier.
 *
 * @return array{ok: bool, copies: int, supprimes: int, erreurs: int, message: string, methode: string}
 */
function sauvegarde_copier_fichiers(
    string $source,
    string $destination,
    array $exclude = [],
    bool $utiliserRsync = true,
    array $excludeFiles = []
): array {
    if (!is_dir($source)) {
        return ['ok' => false, 'copies' => 0, 'supprimes' => 0, 'erreurs' => 1,
            'message' => 'Dossier de données introuvable : ' . $source, 'methode' => ''];
    }
    if (!is_dir($destination) && !@mkdir($destination, 0770, true) && !is_dir($destination)) {
        return ['ok' => false, 'copies' => 0, 'supprimes' => 0, 'erreurs' => 1,
            'message' => 'Impossible de créer ' . $destination, 'methode' => ''];
    }

    $rsync = $utiliserRsync ? sauvegarde_rsync_disponible() : '';
    if ($rsync !== '') {
        $resultat = sauvegarde_copier_fichiers_rsync($rsync, $source, $destination, $exclude, $excludeFiles);
        if ($resultat['ok']) {
            return $resultat;
        }
        // rsync présent mais en échec : on refait la copie en PHP plutôt que
        // de laisser une sauvegarde incomplète.
        $repli = sauvegarde_copier_fichiers_php($source, $destination, $exclude, $excludeFiles);
        if ($repli['ok']) {
            $repli['methode'] = 'php (repli après rsync)';
        }
        return $repli;
    }

    return sauvegarde_copier_fichiers_php($source, $destination, $exclude, $excludeFiles);
}

/**
 * Chemin de rsync s'il est installé, sinon ''.
 *
 * On regarde dans le PATH sans lancer de commande : aucune fonction de
 * « shell » n'est nécessaire, et l'essai reste sans effet sur le système.
 */
function sauvegarde_rsync_disponible(): string
{
    $path = (string) getenv('PATH');
    if ($path === '') {
        $path = '/usr/local/bin:/usr/bin:/bin';
    }
    foreach (explode(PATH_SEPARATOR, $path) as $dossier) {
        if ($dossier === '') {
            continue;
        }
        $candidat = rtrim($dossier, '/') . '/rsync';
        if (is_file($candidat) && is_executable($candidat)) {
            return $candidat;
        }
    }
    return '';
}

/** Copie des fichiers avec rsync (incrémentale, avec suppression des doublons). */
function sauvegarde_copier_fichiers_rsync(
    string $rsync,
    string $source,
    string $destination,
    array $exclude,
    array $excludeFiles = []
): array {
    $commande = escapeshellarg($rsync) . ' -a --delete --stats';
    foreach ($exclude as $dossier) {
        $commande .= ' --exclude ' . escapeshellarg(trim((string) $dossier, '/') . '/');
    }
    foreach ($excludeFiles as $fichier) {
        // « /nom » = à la racine du dossier copié uniquement.
        $commande .= ' --exclude ' . escapeshellarg('/' . ltrim((string) $fichier, '/'));
    }
    $commande .= ' ' . escapeshellarg(rtrim($source, '/') . '/') . ' ' . escapeshellarg(rtrim($destination, '/') . '/');
    $commande .= ' 2>&1';

    $sortie = [];
    $code   = 1;
    try {
        @exec($commande, $sortie, $code);
    } catch (Throwable $e) {
        // Certains environnements (tests en WebAssembly) interdisent de lancer
        // un programme : on le signale, l'appelant fera la copie en PHP.
        return ['ok' => false, 'copies' => 0, 'supprimes' => 0, 'erreurs' => 1,
            'message' => 'rsync n’a pas pu être lancé.', 'methode' => 'rsync'];
    }
    $texte = implode("\n", $sortie);

    $copies = 0;
    if (preg_match('/Number of regular files transferred:\s*([\d.,]+)/', $texte, $m) === 1) {
        $copies = (int) str_replace([',', '.'], '', $m[1]);
    }

    return [
        'ok'        => $code === 0,
        'copies'    => $copies,
        'supprimes' => 0,
        'erreurs'   => $code === 0 ? 0 : 1,
        'message'   => $code === 0 ? '' : 'rsync a renvoyé le code ' . $code . ' : ' . trim($texte),
        'methode'   => 'rsync',
    ];
}

/** Copie des fichiers en PHP (repli quand rsync n'est pas installé). */
function sauvegarde_copier_fichiers_php(
    string $source,
    string $destination,
    array $exclude,
    array $excludeFiles = []
): array {
    $exclus = array_map(static fn ($d) => trim((string) $d, '/'), $exclude);
    $exclusFichiers = array_map(static fn ($f) => ltrim((string) $f, '/'), $excludeFiles);
    $copies = 0;
    $erreurs = 0;
    $vus = [];
    $messages = [];

    $racine = rtrim($source, '/');
    $iterateur = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterateur as $item) {
        $relatif = ltrim(substr($item->getPathname(), strlen($racine)), '/');
        $premier = explode('/', $relatif)[0];
        if (in_array($premier, $exclus, true)) {
            continue;
        }
        if (in_array($relatif, $exclusFichiers, true)) {
            continue;
        }
        $cible = rtrim($destination, '/') . '/' . $relatif;

        if ($item->isDir()) {
            if (!is_dir($cible) && !@mkdir($cible, 0770, true) && !is_dir($cible)) {
                $erreurs++;
                $messages[] = 'dossier non créé : ' . $relatif;
            }
            continue;
        }

        $vus[$relatif] = true;

        $tailleSource = (int) $item->getSize();
        $mtimeSource  = (int) $item->getMTime();
        if (is_file($cible) && filesize($cible) === $tailleSource && filemtime($cible) === $mtimeSource) {
            continue;
        }

        $tmp = $cible . '.partiel';
        if (@copy($item->getPathname(), $tmp) && @rename($tmp, $cible)) {
            @touch($cible, $mtimeSource);
            $copies++;
        } else {
            @unlink($tmp);
            $erreurs++;
            $messages[] = 'fichier non copié : ' . $relatif;
        }
    }

    // Suppression des fichiers qui n'existent plus sur le site (comme rsync
    // --delete) : la sauvegarde reste le reflet exact des données.
    $supprimes = 0;
    if ($erreurs === 0 && is_dir($destination)) {
        $iterateurCible = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(rtrim($destination, '/'), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterateurCible as $item) {
            $relatif = ltrim(substr($item->getPathname(), strlen(rtrim($destination, '/'))), '/');
            $premier = explode('/', $relatif)[0];
            if (in_array($premier, $exclus, true) || in_array($relatif, $exclusFichiers, true)) {
                continue;
            }
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }
            if (!isset($vus[$relatif])) {
                if (@unlink($item->getPathname())) {
                    $supprimes++;
                }
            }
        }
    }

    return [
        'ok'        => $erreurs === 0,
        'copies'    => $copies,
        'supprimes' => $supprimes,
        'erreurs'   => $erreurs,
        'message'   => implode(' ; ', array_slice($messages, 0, 5)),
        'methode'   => 'php',
    ];
}

/**
 * Rotation : ne garde que les $garder fichiers les plus récents d'un dossier.
 *
 * @return string[] noms des fichiers supprimés
 */
function sauvegarde_rotation(string $dossier, string $motif, int $garder): array
{
    if (!is_dir($dossier) || $garder < 1) {
        return [];
    }
    $fichiers = glob(rtrim($dossier, '/') . '/' . $motif) ?: [];
    usort($fichiers, static fn ($a, $b) => filemtime($b) <=> filemtime($a));

    $supprimes = [];
    foreach (array_slice($fichiers, $garder) as $vieux) {
        if (@unlink($vieux)) {
            $supprimes[] = basename($vieux);
        }
    }
    return $supprimes;
}

/** Copie d'une archive vers un dossier de rotation (lien dur si possible). */
function sauvegarde_rotation_copier(string $archive, string $dossier, string $nom): bool
{
    if (!is_dir($dossier) && !@mkdir($dossier, 0770, true) && !is_dir($dossier)) {
        return false;
    }
    $cible = rtrim($dossier, '/') . '/' . $nom;
    if (file_exists($cible)) {
        return true;
    }
    if (@link($archive, $cible)) {
        return true;
    }
    return @copy($archive, $cible);
}

/** Écrit le fichier d'état lu par la page « État du site » (JSON). */
function sauvegarde_etat_ecrire(array $etat, ?string $fichier = null): bool
{
    $fichier = $fichier ?? (string) (sauvegarde_options()['state']);
    $dossier = dirname($fichier);
    if (!is_dir($dossier) && !@mkdir($dossier, 0770, true) && !is_dir($dossier)) {
        return false;
    }

    $json = json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $tmp = $fichier . '.partiel';
    if (@file_put_contents($tmp, $json . "\n") === false) {
        return false;
    }
    @chmod($tmp, 0640);
    return @rename($tmp, $fichier);
}

/** Lit le fichier d'état de la dernière sauvegarde. */
function sauvegarde_etat_lire(?string $fichier = null): ?array
{
    $fichier = $fichier ?? (string) (sauvegarde_options()['state']);
    if (!is_readable($fichier)) {
        return null;
    }
    $json = json_decode((string) @file_get_contents($fichier), true);
    return is_array($json) ? $json : null;
}

/** Ajoute une ligne au journal de sauvegarde (sans donnée personnelle). */
function sauvegarde_journal(string $ligne, ?string $fichier = null): void
{
    $fichier = $fichier ?? (string) (sauvegarde_options()['log']);
    $dossier = dirname($fichier);
    if (!is_dir($dossier)) {
        return;
    }
    $texte = date('Y-m-d H:i:s') . '  ' . $ligne . "\n";
    if (@file_put_contents($fichier, $texte, FILE_APPEND | LOCK_EX) !== false) {
        @chmod($fichier, 0640);
    }
}

/**
 * Vérifie qu'un dossier de sauvegarde contient une base restaurable.
 *
 * @return array{ok: bool, message: string, base: string}
 */
function sauvegarde_verifier_archive(string $dossier, string $motif = '*.sqlite.gz'): array
{
    $archives = glob(rtrim($dossier, '/') . '/base/' . $motif) ?: [];
    if ($archives === []) {
        return ['ok' => false, 'message' => 'Aucune sauvegarde de base dans ' . $dossier . '/base.', 'base' => ''];
    }
    usort($archives, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
    $archive = $archives[0];

    $tmp = tempnam(sys_get_temp_dir(), 'hdf-verif-');
    if ($tmp === false) {
        return ['ok' => false, 'message' => 'Impossible de créer un fichier temporaire.', 'base' => $archive];
    }

    $entree = @gzopen($archive, 'rb');
    $sortie = @fopen($tmp, 'wb');
    if ($entree === false || $sortie === false) {
        @unlink($tmp);
        return ['ok' => false, 'message' => 'Archive illisible : ' . basename($archive), 'base' => $archive];
    }
    while (!gzeof($entree)) {
        fwrite($sortie, (string) gzread($entree, 1048576));
    }
    gzclose($entree);
    fclose($sortie);

    try {
        $pdo = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $resultat = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        $pdo = null;
    } catch (Throwable $e) {
        @unlink($tmp);
        return ['ok' => false, 'message' => 'Base illisible : ' . $e->getMessage(), 'base' => $archive];
    }
    @unlink($tmp);

    if (strtolower(trim($resultat)) !== 'ok') {
        return ['ok' => false, 'message' => 'Base abîmée (' . $resultat . ').', 'base' => $archive];
    }

    return ['ok' => true, 'message' => '', 'base' => $archive];
}

/**
 * Exécute une sauvegarde complète.
 *
 * @return array{ok: bool, annulee: bool, message: string, base: array, fichiers: array, etat: array, duree: int}
 */
function sauvegarde_executer(array $options, ?callable $trace = null): array
{
    // Les réglages non fournis viennent de la configuration du site.
    $options = array_replace(sauvegarde_options(), $options);
    $debut = microtime(true);
    $trace = $trace ?? static function (string $ligne): void {
        echo '  · ', $ligne, "\n";
    };

    $resultat = [
        'ok'       => false,
        'annulee'  => false,
        'message'  => '',
        'base'     => ['ok' => false, 'message' => '', 'chemin' => '', 'taille' => 0],
        'fichiers' => ['ok' => false, 'copies' => 0, 'supprimes' => 0, 'erreurs' => 0, 'message' => '', 'methode' => ''],
        'etat'     => [],
        'duree'    => 0,
    ];

    $dir = rtrim((string) $options['backup_dir'], '/');

    // 1. Le support (clé USB) est-il là ? Sinon, on n'écrit RIEN.
    $support = sauvegarde_support_ok($options);
    if (!$support['ok']) {
        $resultat['annulee'] = true;
        $resultat['message'] = 'Sauvegarde annulée : ' . $support['raison'] . ' Rien n’a été écrit.';
        $resultat['duree'] = (int) round(microtime(true) - $debut);
        return $resultat;
    }

    // 2. Espace libre : on refuse avant d'écrire quoi que ce soit (donc avant
    //    même de créer le dossier).
    $libreCle = sauvegarde_espace_libre($dir);
    $tailleBase = is_file((string) $options['database']) ? (int) filesize((string) $options['database']) : 0;
    $minimum = max((int) $options['min_free_mb'] * 1048576, $tailleBase * 2);
    if ($libreCle !== null && $libreCle < $minimum) {
        $resultat['annulee'] = true;
        $resultat['message'] = 'Sauvegarde annulée : il reste ' . sauvegarde_taille($libreCle)
            . ' sur la clé, il faut au moins ' . sauvegarde_taille($minimum) . '. Rien n’a été écrit.';
        $resultat['duree'] = (int) round(microtime(true) - $debut);
        return $resultat;
    }

    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        $resultat['annulee'] = true;
        $resultat['message'] = 'Sauvegarde annulée : impossible de créer ' . $dir . '. Rien n’a été écrit.';
        $resultat['duree'] = (int) round(microtime(true) - $debut);
        return $resultat;
    }

    // 3. Dossier temporaire : la sauvegarde n'est déplacée qu'une fois finie
    //    et contrôlée. L'ancienne reste intacte jusque-là.
    $temporaire = $dir . '/.en-cours-' . getmypid() . '-' . bin2hex(random_bytes(3));
    if (!@mkdir($temporaire, 0770, true) && !is_dir($temporaire)) {
        $resultat['message'] = 'Sauvegarde annulée : impossible de créer le dossier temporaire.';
        return $resultat;
    }

    $nettoyer = static function () use ($temporaire): void {
        if (is_dir($temporaire)) {
            foreach (glob($temporaire . '/*') ?: [] as $reste) {
                @unlink($reste);
            }
            @rmdir($temporaire);
        }
    };

    // 4. Base : copie cohérente + intégrité.
    $trace('Copie de la base de données…');
    $copie = sauvegarde_copier_base(
        (string) $options['database'],
        $temporaire . '/histoire.sqlite',
        $trace
    );
    $resultat['base'] = $copie;
    if (!$copie['ok']) {
        $nettoyer();
        $resultat['message'] = $copie['message'];
        $resultat['duree'] = (int) round(microtime(true) - $debut);
        return $resultat;
    }

    $horodatage = date('Y-m-d_His');
    $archive = 'histoire-' . $horodatage . '.sqlite.gz';
    $suffixe = 1;
    while (file_exists($dir . '/base/' . $archive)) {
        $suffixe++;
        $archive = 'histoire-' . $horodatage . '-' . $suffixe . '.sqlite.gz';
    }
    if (!sauvegarde_compresser($copie['chemin'], $temporaire . '/' . $archive)) {
        $nettoyer();
        $resultat['message'] = 'La compression de la copie de la base a échoué.';
        $resultat['duree'] = (int) round(microtime(true) - $debut);
        return $resultat;
    }
    @unlink($copie['chemin']);
    $tailleArchive = (int) filesize($temporaire . '/' . $archive);

    // 5. Fichiers : copie incrémentale.
    $trace('Copie des fichiers (photos, vidéos, documents)…');
    $fichiers = sauvegarde_copier_fichiers(
        (string) $options['storage_root'],
        $dir . '/fichiers',
        (array) $options['exclude'],
        (bool) $options['utiliser_rsync'],
        sauvegarde_fichiers_exclus($options)
    );
    $resultat['fichiers'] = $fichiers;

    // 6. Mise en place : la nouvelle base remplace la précédente seulement
    //    maintenant, une fois terminée et contrôlée.
    $dossierBase = $dir . '/base';
    if (!is_dir($dossierBase) && !@mkdir($dossierBase, 0770, true) && !is_dir($dossierBase)) {
        $nettoyer();
        $resultat['message'] = 'Impossible de créer ' . $dossierBase . '.';
        $resultat['duree'] = (int) round(microtime(true) - $debut);
        return $resultat;
    }
    if (!@rename($temporaire . '/' . $archive, $dossierBase . '/' . $archive)) {
        $nettoyer();
        $resultat['message'] = 'Impossible de déplacer la sauvegarde de la base.';
        $resultat['duree'] = (int) round(microtime(true) - $debut);
        return $resultat;
    }
    $nettoyer();

    // 7. Rotation : la nouvelle sauvegarde existe et est vérifiée ; on peut
    //    retirer les plus anciennes.
    $rotation = [];
    $rotation = array_merge($rotation, sauvegarde_rotation($dossierBase, 'histoire-*.sqlite.gz', (int) $options['quotidiennes']));

    $archiveComplete = $dossierBase . '/' . $archive;
    $isoSemaine = date('o-\WW');
    $mois = date('Y-m');
    if (sauvegarde_rotation_copier($archiveComplete, $dir . '/hebdo', 'histoire-' . $isoSemaine . '.sqlite.gz')) {
        $rotation = array_merge($rotation, sauvegarde_rotation($dir . '/hebdo', 'histoire-*.sqlite.gz', (int) $options['hebdomadaires']));
    }
    if (sauvegarde_rotation_copier($archiveComplete, $dir . '/mensuel', 'histoire-' . $mois . '.sqlite.gz')) {
        $rotation = array_merge($rotation, sauvegarde_rotation($dir . '/mensuel', 'histoire-*.sqlite.gz', (int) $options['mensuelles']));
    }

    // 8. Bilan et état.
    $resultat['ok'] = $copie['ok'] && $fichiers['ok'];
    $resultat['duree'] = (int) round(microtime(true) - $debut);
    $libreDonnees = sauvegarde_espace_libre((string) $options['storage_root']);

    $etat = [
        'date'          => time(),
        'date_humain'   => date('d/m/Y à H:i'),
        'status'        => $resultat['ok'] ? 'ok' : 'erreur',
        'database'      => (bool) $copie['ok'],
        'files'         => (bool) $fichiers['ok'],
        'message'       => $resultat['ok']
            ? 'Sauvegarde terminée.'
            : 'Sauvegarde incomplète : ' . trim($copie['message'] . ' ' . $fichiers['message']),
        'base_size'     => $tailleArchive,
        'db_size'       => $copie['taille'],
        'files_copied'  => (int) $fichiers['copies'],
        'files_deleted' => (int) $fichiers['supprimes'],
        'files_errors'  => (int) $fichiers['erreurs'],
        'method'        => (string) $fichiers['methode'],
        'duration'      => $resultat['duree'],
        'free_backup'   => $libreCle,
        'free_data'     => $libreDonnees,
        'archive'       => 'base/' . $archive,
        'rotation'      => $rotation,
        'version'       => 2,
    ];
    $resultat['etat'] = $etat;

    sauvegarde_etat_ecrire($etat, (string) $options['state']);

    if ($resultat['ok']) {
        sauvegarde_journal(
            'Sauvegarde réussie — base ' . sauvegarde_taille($tailleArchive)
            . ', ' . (int) $fichiers['copies'] . ' fichier(s) copié(s), ' . $resultat['duree'] . ' s.',
            (string) $options['log']
        );
    } else {
        sauvegarde_journal('ÉCHEC — ' . $etat['message'], (string) $options['log']);
    }

    return $resultat;
}

/** Compresse un fichier en gzip (par blocs, sans tout charger en mémoire). */
function sauvegarde_compresser(string $source, string $destination): bool
{
    $entree = @fopen($source, 'rb');
    if ($entree === false) {
        return false;
    }
    $sortie = @gzopen($destination, 'wb9');
    if ($sortie === false) {
        fclose($entree);
        return false;
    }

    $ok = true;
    while (!feof($entree)) {
        $bloc = fread($entree, 1048576);
        if ($bloc === false) {
            $ok = false;
            break;
        }
        if ($bloc !== '' && gzwrite($sortie, $bloc) === false) {
            $ok = false;
            break;
        }
    }

    fclose($entree);
    gzclose($sortie);

    if (!$ok) {
        @unlink($destination);
    }
    return $ok;
}

/**
 * Alerte affichée en haut de l'administration (et sur « État du site »).
 *
 * Renvoie null si tout va bien, sinon un tableau lisible en français simple.
 *
 * @return array{niveau: string, message: string, details: string[]}|null
 */
function sauvegarde_alerte(array $options = []): ?array
{
    $options = $options === [] ? sauvegarde_options() : array_replace(sauvegarde_options(), $options);
    $jours   = (int) config('maintenance.backup_alert_days', 3);
    $minimum = (int) config('maintenance.backup_alert_free_mb', 512) * 1048576;

    $details = [];
    $etat    = sauvegarde_etat_lire((string) $options['state']);

    $support = sauvegarde_support_ok($options);
    if (!$support['ok']) {
        $details[] = $support['raison'];
    }

    if ($etat === null) {
        $details[] = 'Aucune sauvegarde connue. Lancez « php app/cli/sauvegarde.php ».';
    } else {
        $date = (int) ($etat['date'] ?? 0);
        $age  = $date > 0 ? (int) floor((time() - $date) / 86400) : 999;

        if (($etat['status'] ?? '') !== 'ok') {
            $details[] = 'La dernière sauvegarde a échoué : ' . (string) ($etat['message'] ?? 'raison inconnue');
        } elseif ($age > $jours) {
            $details[] = 'La dernière sauvegarde réussie date de ' . $age . ' jours (limite : ' . $jours . ').';
        }
    }

    $libreCle = sauvegarde_espace_libre((string) $options['backup_dir']);
    if ($libreCle !== null && $libreCle < $minimum) {
        $details[] = 'Espace libre faible sur la clé : ' . sauvegarde_taille($libreCle) . '.';
    }
    $libreDonnees = sauvegarde_espace_libre((string) $options['storage_root']);
    if ($libreDonnees !== null && $libreDonnees < $minimum) {
        $details[] = 'Espace libre faible sur le disque des données : ' . sauvegarde_taille($libreDonnees) . '.';
    }

    if ($details === []) {
        return null;
    }

    return [
        'niveau'  => 'rouge',
        'message' => 'Sauvegarde : ' . $details[0],
        'details' => $details,
    ];
}

/** Date de la dernière sauvegarde réussie, ou null. */
function sauvegarde_derniere_reussie(array $etat): ?int
{
    if (($etat['status'] ?? '') !== 'ok') {
        return null;
    }
    $date = (int) ($etat['date'] ?? 0);
    return $date > 0 ? $date : null;
}
