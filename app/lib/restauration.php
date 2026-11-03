<?php
/**
 * Restauration des données à partir d'une sauvegarde.
 *
 * Trois principes, dans cet ordre :
 *  1. on ne touche JAMAIS à la base en service sans une confirmation explicite
 *     (l'utilisateur doit taper « OUI ») ;
 *  2. par défaut, la restauration se fait dans un dossier SÉPARÉ (essai), pour
 *     regarder le résultat avant de remplacer quoi que ce soit ;
 *  3. si l'on remplace, l'ancienne base est d'abord mise de côté et les
 *     fichiers qui disparaîtraient sont déplacés, jamais effacés.
 *
 * Les fonctions reçoivent leurs chemins en paramètre pour rester testables
 * dans des dossiers temporaires : la vraie base n'est utilisée que par la
 * commande `app/cli/restauration.php`, après confirmation.
 */

declare(strict_types=1);

/**
 * La confirmation saisie autorise-t-elle le remplacement ?
 *
 * Seul « OUI », en majuscules, compte. « oui », « Oui », « ok » et une ligne
 * vide sont refusés : une fausse manœuvre ne doit pas pouvoir effacer le site.
 */
function restauration_confirmation_valide(string $saisie): bool
{
    return trim($saisie) === 'OUI';
}

/** Dossier d'une sauvegarde : la sous-arborescence « base », « fichiers »… */
function restauration_est_sauvegarde(string $dossier): bool
{
    return is_dir(rtrim($dossier, '/') . '/base')
        || is_file(rtrim($dossier, '/') . '/histoire.sqlite.gz');
}

/**
 * Dernière sauvegarde de la base d'un dossier (la plus récente).
 *
 * Accepte le dossier de sauvegarde (« base/histoire-….sqlite.gz ») ou
 * directement un fichier d'archive.
 */
function restauration_derniere_archive(string $dossier, int $rang = 0): ?string
{
    $dossier = rtrim($dossier, '/');
    if (is_file($dossier)) {
        return $dossier;
    }

    $archives = glob($dossier . '/base/histoire-*.sqlite.gz') ?: [];
    if ($archives === [] && is_dir($dossier)) {
        $archives = glob($dossier . '/*.sqlite.gz') ?: [];
    }
    if ($archives === []) {
        return null;
    }

    usort($archives, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
    return $archives[$rang] ?? null;
}

/**
 * Décompresse une archive et vérifie son intégrité.
 *
 * @return array{ok: bool, message: string, base: string, compteurs: array}
 */
function restauration_extraire(string $archive, string $baseDestination): array
{
    if (!is_file($archive)) {
        return ['ok' => false, 'message' => 'Archive introuvable : ' . $archive, 'base' => '', 'compteurs' => []];
    }
    if (!is_dir(dirname($baseDestination))
        && !@mkdir(dirname($baseDestination), 0770, true)
        && !is_dir(dirname($baseDestination))) {
        return ['ok' => false, 'message' => 'Impossible de créer ' . dirname($baseDestination), 'base' => '', 'compteurs' => []];
    }
    if (file_exists($baseDestination)) {
        @unlink($baseDestination);
    }

    $entree = @gzopen($archive, 'rb');
    if ($entree === false) {
        return ['ok' => false, 'message' => 'Archive illisible (fichier abîmé ?) : ' . basename($archive), 'base' => '', 'compteurs' => []];
    }
    $sortie = @fopen($baseDestination, 'wb');
    if ($sortie === false) {
        gzclose($entree);
        return ['ok' => false, 'message' => 'Impossible d’écrire ' . $baseDestination, 'base' => '', 'compteurs' => []];
    }
    while (!gzeof($entree)) {
        $bloc = gzread($entree, 1048576);
        if ($bloc === false) {
            break;
        }
        if ($bloc !== '') {
            fwrite($sortie, $bloc);
        }
    }
    gzclose($entree);
    fclose($sortie);

    try {
        $pdo = new PDO('sqlite:' . $baseDestination, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $resultat = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        $compteurs = [];
        if (strtolower(trim($resultat)) === 'ok') {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                ->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $compteurs[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
            }
        }
        $pdo = null;
    } catch (Throwable $e) {
        @unlink($baseDestination);
        return ['ok' => false, 'message' => 'Base illisible : ' . $e->getMessage(), 'base' => '', 'compteurs' => []];
    }

    if (strtolower(trim($resultat)) !== 'ok') {
        @unlink($baseDestination);
        return [
            'ok'      => false,
            'message' => 'La sauvegarde est abîmée (contrôle d’intégrité : ' . $resultat . '). Rien n’a été restauré.',
            'base'    => '',
            'compteurs' => [],
        ];
    }

    return ['ok' => true, 'message' => '', 'base' => $baseDestination, 'compteurs' => $compteurs];
}

/** Compte les lignes de chaque table d'une base (pour comparer). */
function restauration_compteurs(string $base): array
{
    if (!is_file($base)) {
        return [];
    }
    try {
        $pdo = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);
        $compteurs = [];
        foreach ($tables as $table) {
            $compteurs[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
        }
        return $compteurs;
    } catch (Throwable) {
        return [];
    }
}

/**
 * Empreinte d'un dossier de fichiers : nombre, taille totale et somme de
 * contrôle (chemin + contenu). Sert à comparer avant/après une restauration.
 *
 * @return array{fichiers: int, octets: int, somme: string}
 */
function restauration_empreinte_fichiers(string $dossier): array
{
    $empreintes = [];
    $octets = 0;

    if (is_dir($dossier)) {
        $elements = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($elements as $element) {
            if ($element->isDir()) {
                continue;
            }
            $relatif = ltrim(substr($element->getPathname(), strlen(rtrim($dossier, '/'))), '/');
            $empreintes[$relatif] = hash_file('sha256', $element->getPathname()) ?: '';
            $octets += (int) $element->getSize();
        }
    }

    ksort($empreintes);
    return [
        'fichiers' => count($empreintes),
        'octets'   => $octets,
        'somme'    => hash('sha256', (string) json_encode($empreintes, JSON_UNESCAPED_SLASHES)),
    ];
}

/** Copie récursive d'un dossier, en écrasant les fichiers existants. */
function restauration_copier_fichiers(string $source, string $destination): array
{
    $copies = 0;
    $erreurs = [];

    if (!is_dir($source)) {
        return ['ok' => true, 'copies' => 0, 'erreurs' => []];
    }
    if (!is_dir($destination) && !@mkdir($destination, 0770, true) && !is_dir($destination)) {
        return ['ok' => false, 'copies' => 0, 'erreurs' => ['dossier non créé : ' . $destination]];
    }

    $elements = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($elements as $element) {
        $relatif = ltrim(substr($element->getPathname(), strlen(rtrim($source, '/'))), '/');
        $cible   = rtrim($destination, '/') . '/' . $relatif;
        if ($element->isDir()) {
            if (!is_dir($cible) && !@mkdir($cible, 0770, true) && !is_dir($cible)) {
                $erreurs[] = 'dossier non créé : ' . $relatif;
            }
            continue;
        }
        if (!@copy($element->getPathname(), $cible)) {
            $erreurs[] = 'fichier non copié : ' . $relatif;
            continue;
        }
        @touch($cible, (int) $element->getMTime());
        $copies++;
    }

    return ['ok' => $erreurs === [], 'copies' => $copies, 'erreurs' => $erreurs];
}

/**
 * Restauration d'ESSAI : tout est écrit dans un dossier séparé, rien n'est
 * touché dans le site en service.
 *
 * @return array{ok: bool, message: string, base: string, dossier: string, compteurs: array, fichiers: int}
 */
function restauration_essai(string $source, string $cible): array
{
    $archive = restauration_derniere_archive($source);
    if ($archive === null) {
        return ['ok' => false, 'message' => 'Aucune sauvegarde de base dans ' . $source . '.',
            'base' => '', 'dossier' => $cible, 'compteurs' => [], 'fichiers' => 0];
    }

    if (!is_dir($cible) && !@mkdir($cible, 0770, true) && !is_dir($cible)) {
        return ['ok' => false, 'message' => 'Impossible de créer ' . $cible . '.',
            'base' => '', 'dossier' => $cible, 'compteurs' => [], 'fichiers' => 0];
    }

    $extraction = restauration_extraire($archive, $cible . '/histoire.sqlite');
    if (!$extraction['ok']) {
        return ['ok' => false, 'message' => $extraction['message'],
            'base' => '', 'dossier' => $cible, 'compteurs' => [], 'fichiers' => 0];
    }

    $copie = restauration_copier_fichiers(rtrim($source, '/') . '/fichiers', $cible . '/storage');

    $lisezMoi = "Restauration d’essai — " . date('d/m/Y à H:i') . "\n"
        . str_repeat('=', 46) . "\n\n"
        . "Ce dossier a été fabriqué à partir de la sauvegarde :\n  " . $archive . "\n\n"
        . "Il contient :\n"
        . "  · histoire.sqlite  — la base de données restaurée\n"
        . "  · storage/         — les photos, vidéos et documents restaurés\n\n"
        . "RIEN n’a été remplacé dans le site en service.\n\n"
        . "Pour vérifier que tout est là :\n"
        . "  sqlite3 " . $cible . "/histoire.sqlite 'PRAGMA integrity_check;'\n\n"
        . "Pour remplacer réellement le site (à faire seulement après vérification),\n"
        . "arrêtez d’abord Apache, puis relancez la commande avec --remplacer :\n"
        . "  sudo systemctl stop apache2\n"
        . "  sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \\\n"
        . "      " . $source . " --remplacer\n";
    @file_put_contents($cible . '/LISEZ-MOI.txt', $lisezMoi);

    return [
        'ok'       => $copie['ok'],
        'message'  => $copie['ok'] ? '' : implode(' ; ', $copie['erreurs']),
        'base'     => $cible . '/histoire.sqlite',
        'dossier'  => $cible,
        'compteurs' => $extraction['compteurs'],
        'fichiers' => $copie['copies'],
        'archive'  => $archive,
    ];
}

/**
 * Déplace les fichiers présents sur le disque mais absents de la sauvegarde
 * vers un dossier « mis de côté » (aucune suppression).
 *
 * @return int nombre de fichiers déplacés
 */
function restauration_mettre_de_cote(
    string $sourceSauvegarde,
    string $donnees,
    string $cote,
    array $ignorerFichiers = [],
    array $ignorerDossiers = []
): int {
    if (!is_dir($donnees) || !is_dir($sourceSauvegarde)) {
        return 0;
    }
    $deplaces = 0;
    $elements = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($donnees, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($elements as $element) {
        $relatif = ltrim(substr($element->getPathname(), strlen(rtrim($donnees, '/'))), '/');
        if ($element->isDir()) {
            continue;
        }
        // La base (et son journal WAL) n'est jamais dans le miroir des
        // fichiers : elle ne doit donc pas être « mise de côté » ici. Les
        // dossiers jetables (tmp, verrous) sont ignorés de la même façon.
        $premier = explode('/', $relatif)[0];
        if (in_array($relatif, $ignorerFichiers, true) || in_array($premier, $ignorerDossiers, true)) {
            continue;
        }
        if (is_file(rtrim($sourceSauvegarde, '/') . '/' . $relatif)) {
            continue;
        }
        $cible = rtrim($cote, '/') . '/' . $relatif;
        if (!is_dir(dirname($cible)) && !@mkdir(dirname($cible), 0770, true) && !is_dir(dirname($cible))) {
            continue;
        }
        if (@rename($element->getPathname(), $cible)) {
            $deplaces++;
        } elseif (@copy($element->getPathname(), $cible)) {
            @unlink($element->getPathname());
            $deplaces++;
        }
    }
    return $deplaces;
}

/**
 * Remplacement réel du site par une sauvegarde.
 *
 * PRÉREQUIS : l'appelant a obtenu la confirmation explicite (« OUI »). Cette
 * fonction ne pose aucune question (elle est aussi utilisée par les tests).
 *
 * @return array{ok: bool, message: string, ancienne_base: string, compteurs: array, fichiers: int}
 */
function restauration_remplacer(array $options, string $source): array
{
    $options = array_replace(sauvegarde_options(), $options);
    $base    = (string) $options['database'];
    $donnees = rtrim((string) $options['storage_root'], '/');
    $dir     = rtrim((string) $options['backup_dir'], '/');
    $horodatage = date('Y-m-d_His');
    $cote    = $dir . '/avant-restauration/' . $horodatage;

    $archive = restauration_derniere_archive($source);
    if ($archive === null) {
        return ['ok' => false, 'message' => 'Aucune sauvegarde de base dans ' . $source . '.',
            'ancienne_base' => '', 'compteurs' => [], 'fichiers' => 0];
    }

    // 1. Vérifier la sauvegarde AVANT de toucher au site.
    $travail = rtrim(sys_get_temp_dir(), '/') . '/hdf-restauration-' . bin2hex(random_bytes(4));
    $extraction = restauration_extraire($archive, $travail . '/histoire.sqlite');
    if (!$extraction['ok']) {
        return ['ok' => false, 'message' => $extraction['message'],
            'ancienne_base' => '', 'compteurs' => [], 'fichiers' => 0];
    }

    // 2. Mettre l'ancienne base de côté (copie cohérente, pas un déplacement
    //    à l'aveugle). Le site doit être arrêté, mais la copie reste sûre.
    $ancienne = '';
    if (is_file($base)) {
        if (!is_dir($cote) && !@mkdir($cote, 0770, true) && !is_dir($cote)) {
            @unlink($travail . '/histoire.sqlite');
            return ['ok' => false, 'message' => 'Impossible de créer ' . $cote . '. Rien n’a été remplacé.',
                'ancienne_base' => '', 'compteurs' => [], 'fichiers' => 0];
        }
        $copieAncienne = sauvegarde_copier_base($base, $cote . '/histoire.sqlite');
        if ($copieAncienne['ok']) {
            $ancienne = $copieAncienne['chemin'];
            @unlink($base . '-wal');
            @unlink($base . '-shm');
        } else {
            @unlink($travail . '/histoire.sqlite');
            return ['ok' => false, 'message' => 'Impossible de mettre l’ancienne base de côté : '
                . $copieAncienne['message'] . ' Rien n’a été remplacé.',
                'ancienne_base' => '', 'compteurs' => [], 'fichiers' => 0];
        }
    }

    // 3. Installer la base restaurée (déplacement sur place : atomique).
    if (!@rename($travail . '/histoire.sqlite', $base)) {
        if (!@copy($travail . '/histoire.sqlite', $base)) {
            return ['ok' => false, 'message' => 'Impossible d’écrire la base restaurée. Votre ancienne base est intacte.',
                'ancienne_base' => $ancienne, 'compteurs' => [], 'fichiers' => 0];
        }
    }
    @unlink($base . '-wal');
    @unlink($base . '-shm');
    @chmod($base, 0640);

    // 4. Fichiers : les fichiers absents de la sauvegarde sont MIS DE CÔTÉ
    //    (jamais effacés), puis la sauvegarde est recopiée par-dessus.
    $coteFichiers = $cote . '/fichiers-retires';
    $deplaces = restauration_mettre_de_cote(
        rtrim($source, '/') . '/fichiers',
        $donnees,
        $coteFichiers,
        sauvegarde_fichiers_exclus($options),
        (array) ($options['exclude'] ?? [])
    );
    $copie = restauration_copier_fichiers(rtrim($source, '/') . '/fichiers', $donnees);

    // 5. Nettoyage du dossier de travail.
    @unlink($travail . '/histoire.sqlite');
    @rmdir($travail);

    // 6. Date du dernier essai de restauration (affichée dans l'administration).
    $etat = [
        'date'         => time(),
        'date_humain'  => date('d/m/Y à H:i'),
        'archive'      => basename($archive),
        'compteurs'    => restauration_compteurs($base),
        'fichiers'     => $copie['copies'],
        'mis_de_cote'  => $deplaces,
        'ancienne_base' => $ancienne,
        'status'       => $copie['ok'] ? 'ok' : 'erreur',
    ];
    sauvegarde_etat_ecrire($etat, (string) $options['restore_state']);

    return [
        'ok'            => $copie['ok'],
        'message'       => $copie['ok'] ? '' : implode(' ; ', $copie['erreurs']),
        'ancienne_base' => $ancienne,
        'compteurs'     => $etat['compteurs'],
        'fichiers'      => $copie['copies'],
        'mis_de_cote'   => $deplaces,
    ];
}

/**
 * Contrôle en lecture seule de la dernière sauvegarde (aucune écriture dans le
 * site) ; note la date du contrôle pour la page « État du site ».
 *
 * @return array{ok: bool, message: string, archive: string, compteurs: array, fichiers: int}
 */
function restauration_verifier(array $options, ?string $source = null): array
{
    $options = array_replace(sauvegarde_options(), $options);
    $source  = $source ?? (string) $options['backup_dir'];

    $archive = restauration_derniere_archive($source);
    if ($archive === null) {
        return ['ok' => false, 'message' => 'Aucune sauvegarde de base dans ' . $source . '.',
            'archive' => '', 'compteurs' => [], 'fichiers' => 0];
    }

    $travail = rtrim(sys_get_temp_dir(), '/') . '/hdf-verif-' . bin2hex(random_bytes(4));
    $extraction = restauration_extraire($archive, $travail . '/histoire.sqlite');
    if (!$extraction['ok']) {
        return ['ok' => false, 'message' => $extraction['message'],
            'archive' => $archive, 'compteurs' => [], 'fichiers' => 0];
    }
    @unlink($travail . '/histoire.sqlite');
    @rmdir($travail);

    $empreinte = restauration_empreinte_fichiers(rtrim($source, '/') . '/fichiers');

    $etat = [
        'date'        => time(),
        'date_humain' => date('d/m/Y à H:i'),
        'archive'     => basename($archive),
        'compteurs'   => $extraction['compteurs'],
        'fichiers'    => $empreinte['fichiers'],
        'status'      => 'ok',
        'mode'        => 'controle',
    ];
    sauvegarde_etat_ecrire($etat, (string) $options['restore_state']);

    return [
        'ok'        => true,
        'message'   => '',
        'archive'   => $archive,
        'compteurs' => $extraction['compteurs'],
        'fichiers'  => $empreinte['fichiers'],
    ];
}

/** Date du dernier contrôle de restauration, pour l'administration. */
function restauration_etat_lire(?string $fichier = null): ?array
{
    $fichier = $fichier ?? (string) sauvegarde_options()['restore_state'];
    if (!is_readable($fichier)) {
        return null;
    }
    $json = json_decode((string) @file_get_contents($fichier), true);
    return is_array($json) ? $json : null;
}
