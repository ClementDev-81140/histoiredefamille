<?php
/**
 * Tests des lots « mise en ligne » : sauvegarde, restauration, matrice d'accès
 * et vérification avant ouverture.
 *
 * Ce fichier est inclus par app/cli/tests.php ; il ne s'exécute pas seul.
 * Tous les essais destructifs se font dans des dossiers TEMPORAIRES créés puis
 * supprimés par le test : la base du site et les vrais fichiers ne sont jamais
 * touchés.
 */

declare(strict_types=1);

/** Crée un dossier temporaire vide et renvoie son chemin. */
function tests_dossier_temp(string $prefixe = 'hdf-essai-'): string
{
    $chemin = rtrim(sys_get_temp_dir(), '/') . '/' . $prefixe . bin2hex(random_bytes(4));
    if (!mkdir($chemin, 0700, true) && !is_dir($chemin)) {
        throw new RuntimeException('Impossible de créer le dossier temporaire ' . $chemin);
    }
    return $chemin;
}

/** Supprime un dossier et tout son contenu (silencieux). */
function tests_supprimer_dossier(string $dossier): void
{
    if (!is_dir($dossier)) {
        return;
    }
    $elements = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($elements as $element) {
        if ($element->isDir() && !$element->isLink()) {
            @rmdir($element->getPathname());
        } else {
            @unlink($element->getPathname());
        }
    }
    @rmdir($dossier);
}

/** Écrit un fichier en créant ses dossiers. */
function tests_ecrire(string $chemin, string $contenu): void
{
    if (!is_dir(dirname($chemin))) {
        mkdir(dirname($chemin), 0700, true);
    }
    file_put_contents($chemin, $contenu);
}

/** Liste triée des chemins relatifs d'un dossier. */
function tests_lister(string $dossier): array
{
    if (!is_dir($dossier)) {
        return [];
    }
    $liste = [];
    $elements = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($elements as $element) {
        $liste[] = ltrim(substr($element->getPathname(), strlen(rtrim($dossier, '/'))), '/') . ($element->isDir() ? '/' : '');
    }
    sort($liste);
    return $liste;
}

/** Somme de contrôle stable d'une arborescence (fichiers : chemin + taille + contenu). */
function tests_somme_arborescence(string $dossier): string
{
    $liste = [];
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
            $liste[$relatif] = hash_file('sha256', $element->getPathname()) ?: '';
        }
    }
    ksort($liste);
    return hash('sha256', json_encode($liste, JSON_UNESCAPED_SLASHES) ?: '');
}

/** Compte les lignes de chaque table d'une base SQLite. */
function tests_compteurs_base(string $base): array
{
    $pdo = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
        ->fetchAll(PDO::FETCH_COLUMN);
    $compteurs = [];
    foreach ($tables as $table) {
        $compteurs[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
    }
    return $compteurs;
}

/**
 * Prépare un petit « site » de démonstration dans un dossier temporaire :
 * une base SQLite en WAL avec deux tables et des fichiers ressemblant aux
 * vrais (photo, miniature, document, dépôt dans « arrivée », fichiers jetables).
 *
 * @return array{racine: string, donnees: string, base: string, cle: string, options: array}
 */
function tests_preparer_site_temp(string $prefixe = 'hdf-sauvegarde-'): array
{
    $racine  = tests_dossier_temp($prefixe);
    $donnees = $racine . '/donnees';
    foreach (['medias/2026/11', 'miniatures/2026/11', 'arrivee', 'tmp', 'verrous'] as $sous) {
        mkdir($donnees . '/' . $sous, 0700, true);
    }

    tests_ecrire($donnees . '/medias/2026/11/photo.jpg', 'PHOTO-FICTIVE');
    tests_ecrire($donnees . '/miniatures/2026/11/photo.jpg', 'MINIATURE-FICTIVE');
    tests_ecrire($donnees . '/arrivee/depot.txt', 'DEPOT-FICTIF');
    tests_ecrire($donnees . '/tmp/jetable.tmp', 'JETABLE');
    tests_ecrire($donnees . '/verrous/lecture.lock', 'VERROU');

    $base = $donnees . '/histoire.sqlite';
    $pdo  = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('CREATE TABLE comptes (id INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE documents (id INTEGER PRIMARY KEY AUTOINCREMENT, titre TEXT NOT NULL)');
    $pdo->exec("INSERT INTO comptes (nom) VALUES ('FICTIF-1'), ('FICTIF-2'), ('FICTIF-3')");
    $pdo->exec("INSERT INTO documents (titre) VALUES ('Photo fictive'), ('Lettre fictive')");
    $pdo = null;

    $cle = $racine . '/cle';

    return [
        'racine'  => $racine,
        'donnees' => $donnees,
        'base'    => $base,
        'cle'     => $cle,
        'options' => [
            'backup_dir'     => $cle,
            'log'            => $cle . '/journal-sauvegarde.log',
            'state'          => $cle . '/dernier-etat.json',
            'restore_state'  => $cle . '/derniere-restauration.json',
            'database'       => $base,
            'storage_root'   => $donnees,
            'exiger_montage' => false,
            'utiliser_rsync' => false,
            'min_free_mb'    => 1,
        ],
    ];
}

// ---------------------------------------------------------------------------
// Lot 1 — Sauvegardes
// ---------------------------------------------------------------------------
function tests_sauvegarde(): void
{
    echo "Sauvegarde — copie cohérente, fichiers, rotation, clé absente\n";

    $site    = tests_preparer_site_temp();
    $options = $site['options'];

    try {
        // 1. La base est exclue de la copie de fichiers (elle a sa propre copie).
        $exclus = sauvegarde_fichiers_exclus($options);
        verifier(
            'sauvegarde : la base et son journal WAL ne sont pas copiés comme fichiers',
            in_array('histoire.sqlite', $exclus, true) && in_array('histoire.sqlite-wal', $exclus, true),
            'exclus : ' . implode(', ', $exclus)
        );

        // 2. Copie cohérente de la base pendant que la connexion est ouverte.
        $pdo = new PDO('sqlite:' . $site['base'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec("INSERT INTO comptes (nom) VALUES ('FICTIF-4')");

        $tmpBase = $site['racine'] . '/essai-base.sqlite';
        $copie   = sauvegarde_copier_base($site['base'], $tmpBase);
        verifier('sauvegarde : la copie de la base réussit', $copie['ok'], $copie['message']);
        verifier(
            'sauvegarde : contrôle d’intégrité de la copie (PRAGMA integrity_check)',
            $copie['ok'] && is_file($tmpBase),
            'copie absente'
        );

        $source   = tests_compteurs_base($site['base']);
        $copiee   = is_file($tmpBase) ? tests_compteurs_base($tmpBase) : [];
        verifier(
            'sauvegarde : la copie contient toutes les lignes (même écrites en WAL)',
            $copiee === $source && ($copiee['comptes'] ?? 0) === 4,
            'source : ' . json_encode($source) . ' — copie : ' . json_encode($copiee)
        );
        $pdo = null;

        $code = (string) file_get_contents(dirname(__DIR__) . '/lib/sauvegarde.php');
        verifier(
            'sauvegarde : la base est copiée par VACUUM INTO (jamais par copie de fichier)',
            str_contains($code, "VACUUM INTO") && !str_contains($code, 'copy($base')
        );

        // 3. Première sauvegarde complète.
        $resultat = sauvegarde_executer($options);
        verifier('sauvegarde : première sauvegarde réussie', $resultat['ok'], $resultat['message']);
        verifier(
            'sauvegarde : 3 fichiers copiés (photo, miniature, dossier arrivée)',
            (int) $resultat['fichiers']['copies'] === 3,
            'copiés : ' . (int) $resultat['fichiers']['copies'] . ' — ' . $resultat['fichiers']['message']
        );
        verifier(
            'sauvegarde : la base n’est pas dans le miroir des fichiers',
            !is_file($site['cle'] . '/fichiers/histoire.sqlite'),
            'base copiée deux fois'
        );
        verifier(
            'sauvegarde : les dossiers tmp/ et verrous/ sont exclus',
            !is_dir($site['cle'] . '/fichiers/tmp') && !is_dir($site['cle'] . '/fichiers/verrous'),
            implode(', ', tests_lister($site['cle'] . '/fichiers'))
        );
        verifier(
            'sauvegarde : aucune copie de config.local.php dans la sauvegarde',
            !is_file($site['cle'] . '/fichiers/config.local.php')
        );

        $archives = glob($site['cle'] . '/base/*.sqlite.gz') ?: [];
        verifier('sauvegarde : une archive de base compressée est conservée', count($archives) === 1, count($archives) . ' archive(s)');
        $verification = sauvegarde_verifier_archive($site['cle']);
        verifier('sauvegarde : l’archive se décompresse et passe le contrôle d’intégrité', $verification['ok'], $verification['message']);
        verifier(
            'sauvegarde : copies hebdomadaire et mensuelle présentes',
            count(glob($site['cle'] . '/hebdo/*.sqlite.gz') ?: []) === 1
                && count(glob($site['cle'] . '/mensuel/*.sqlite.gz') ?: []) === 1
        );

        // 4. Deuxième sauvegarde : incrémentale (rien à recopier).
        $deuxieme = sauvegarde_executer($options);
        verifier(
            'sauvegarde : deuxième passage — aucun fichier recopié (incrémental)',
            $deuxieme['ok'] && (int) $deuxieme['fichiers']['copies'] === 0,
            'copiés : ' . (int) $deuxieme['fichiers']['copies']
        );

        // 5. Un fichier modifié est recopié, un fichier supprimé disparaît.
        tests_ecrire($site['donnees'] . '/medias/2026/11/photo.jpg', 'PHOTO-MODIFIEE');
        $troisieme = sauvegarde_executer($options);
        verifier(
            'sauvegarde : un fichier modifié est recopié seul',
            $troisieme['ok'] && (int) $troisieme['fichiers']['copies'] === 1,
            'copiés : ' . (int) $troisieme['fichiers']['copies']
        );
        verifier(
            'sauvegarde : le contenu sauvegardé suit la modification',
            (string) @file_get_contents($site['cle'] . '/fichiers/medias/2026/11/photo.jpg') === 'PHOTO-MODIFIEE'
        );

        unlink($site['donnees'] . '/arrivee/depot.txt');
        $quatrieme = sauvegarde_executer($options);
        verifier(
            'sauvegarde : un fichier supprimé du site disparaît de la sauvegarde',
            $quatrieme['ok'] && (int) $quatrieme['fichiers']['supprimes'] === 1
                && !is_file($site['cle'] . '/fichiers/arrivee/depot.txt'),
            'retirés : ' . (int) $quatrieme['fichiers']['supprimes']
        );

        // 6. État JSON et journal.
        $etat = sauvegarde_etat_lire($site['cle'] . '/dernier-etat.json');
        verifier(
            'sauvegarde : l’état est écrit pour la page « État du site »',
            is_array($etat) && ($etat['status'] ?? '') === 'ok' && (int) ($etat['date'] ?? 0) > time() - 120,
            json_encode($etat, JSON_UNESCAPED_UNICODE)
        );
        verifier(
            'sauvegarde : l’état indique la taille de la base et le nombre de fichiers',
            is_array($etat) && (int) ($etat['base_size'] ?? 0) > 0 && isset($etat['files_copied']),
            json_encode($etat, JSON_UNESCAPED_UNICODE)
        );

        $journal = (string) @file_get_contents($site['cle'] . '/journal-sauvegarde.log');
        verifier('sauvegarde : le journal note la réussite', str_contains($journal, 'Sauvegarde réussie'), $journal);
        verifier(
            'sauvegarde : ni mot de passe ni jeton dans le journal ou l’état',
            !preg_match('/mot.?de.?passe|password|csrf|token/i', $journal . json_encode($etat)) === true
        );

        // 7. Rotation : 7 quotidiennes, 4 hebdomadaires, 6 mensuelles.
        $fabriquer = static function (string $dossier, string $modele, int $nombre): void {
            @mkdir($dossier, 0700, true);
            foreach (glob($dossier . '/*') ?: [] as $vieux) {
                @unlink($vieux);
            }
            for ($i = 0; $i < $nombre; $i++) {
                $fichier = $dossier . '/' . sprintf($modele, $i);
                file_put_contents($fichier, 'x');
                touch($fichier, time() - (($nombre - $i) * 3600));
            }
        };

        $fabriquer($site['cle'] . '/base', 'histoire-essai-%02d.sqlite.gz', 10);
        $supprimes = sauvegarde_rotation($site['cle'] . '/base', 'histoire-*.sqlite.gz', 7);
        verifier(
            'rotation : 7 copies quotidiennes conservées au maximum',
            count(glob($site['cle'] . '/base/*.sqlite.gz') ?: []) === 7 && count($supprimes) === 3,
            'restantes : ' . count(glob($site['cle'] . '/base/*.sqlite.gz') ?: [])
        );

        $fabriquer($site['cle'] . '/hebdo', 'histoire-essai-%02d.sqlite.gz', 6);
        $supprimes = sauvegarde_rotation($site['cle'] . '/hebdo', 'histoire-*.sqlite.gz', 4);
        verifier(
            'rotation : 4 copies hebdomadaires conservées au maximum',
            count(glob($site['cle'] . '/hebdo/*.sqlite.gz') ?: []) === 4 && count($supprimes) === 2,
            'restantes : ' . count(glob($site['cle'] . '/hebdo/*.sqlite.gz') ?: [])
        );

        $fabriquer($site['cle'] . '/mensuel', 'histoire-essai-%02d.sqlite.gz', 8);
        $supprimes = sauvegarde_rotation($site['cle'] . '/mensuel', 'histoire*.sqlite.gz', 6);
        verifier(
            'rotation : 6 copies mensuelles conservées au maximum',
            count(glob($site['cle'] . '/mensuel/*.sqlite.gz') ?: []) === 6 && count($supprimes) === 2,
            'restantes : ' . count(glob($site['cle'] . '/mensuel/*.sqlite.gz') ?: [])
        );

        // 8. Une ancienne sauvegarde n'est jamais écrasée par une nouvelle
        //    incomplète : les archives portent un nom daté et restent distinctes.
        $avant = glob($site['cle'] . '/base/*.sqlite.gz') ?: [];
        usort($avant, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $plusRecente = $avant[0] ?? '';
        $apres = sauvegarde_executer($options);
        $maintenant = glob($site['cle'] . '/base/*.sqlite.gz') ?: [];
        $nouvelles = array_values(array_diff($maintenant, $avant));
        verifier(
            'sauvegarde : une nouvelle archive est ajoutée sans écraser la précédente',
            $apres['ok'] && $nouvelles !== [] && $plusRecente !== '' && in_array($plusRecente, $maintenant, true),
            'avant : ' . count($avant) . ' — après : ' . count($maintenant)
        );

        // 9. Clé USB absente : refus propre, RIEN n'est écrit.
        $absente = $site['racine'] . '/cle-absente';
        $refus = sauvegarde_executer(array_replace($options, [
            'backup_dir'     => $absente,
            'state'          => $absente . '/dernier-etat.json',
            'log'            => $absente . '/journal-sauvegarde.log',
            'exiger_montage' => true,
        ]));
        verifier('sauvegarde : clé absente — la sauvegarde est annulée', $refus['annulee'] === true && $refus['ok'] === false);
        verifier(
            'sauvegarde : clé absente — rien n’est créé sur le disque',
            !is_dir($absente) && !is_file($absente . '/dernier-etat.json'),
            'le dossier a été créé'
        );
        verifier_contient('sauvegarde : clé absente — le message explique pourquoi', 'Rien n’a été écrit', $refus['message']);

        // 10. Espace insuffisant : refus avant toute écriture.
        $petite = $site['racine'] . '/cle-pleine';
        $refusEspace = sauvegarde_executer(array_replace($options, [
            'backup_dir'  => $petite,
            'state'       => $petite . '/dernier-etat.json',
            'log'         => $petite . '/journal-sauvegarde.log',
            'min_free_mb' => 999999999,
        ]));
        verifier(
            'sauvegarde : espace insuffisant — annulée, rien n’est écrit',
            $refusEspace['annulee'] === true && !is_dir($petite),
            $refusEspace['message']
        );

        // 11. Base introuvable : échec signalé (code de sortie non nul côté commande).
        $refusBase = sauvegarde_executer(array_replace($options, [
            'database' => $site['donnees'] . '/absente.sqlite',
            'state'    => $site['racine'] . '/etat-absente.json',
            'log'      => $site['racine'] . '/journal-absente.log',
        ]));
        verifier(
            'sauvegarde : base introuvable — échec clair, pas de fausse réussite',
            $refusBase['ok'] === false && str_contains($refusBase['message'], 'introuvable'),
            $refusBase['message']
        );

        // 12. Alerte de l'administration.
        $frais = sauvegarde_alerte(array_replace($options, ['state' => $site['cle'] . '/dernier-etat.json']));
        verifier('alerte : aucune alerte quand la sauvegarde est récente', $frais === null, json_encode($frais));

        $etatVieux = sauvegarde_etat_lire($site['cle'] . '/dernier-etat.json') ?? [];
        $etatVieux['date'] = time() - 5 * 86400;
        sauvegarde_etat_ecrire($etatVieux, $site['cle'] . '/etat-vieux.json');
        $vieille = sauvegarde_alerte(array_replace($options, ['state' => $site['cle'] . '/etat-vieux.json']));
        verifier(
            'alerte : sauvegarde de plus de 3 jours — alerte rouge',
            is_array($vieille) && $vieille['niveau'] === 'rouge'
                && str_contains(implode(' ', $vieille['details']), '5 jours'),
            json_encode($vieille, JSON_UNESCAPED_UNICODE)
        );

        $etatEchec = $etatVieux;
        $etatEchec['date'] = time();
        $etatEchec['status'] = 'erreur';
        $etatEchec['message'] = 'Disque plein (essai).';
        sauvegarde_etat_ecrire($etatEchec, $site['cle'] . '/etat-echec.json');
        $echec = sauvegarde_alerte(array_replace($options, ['state' => $site['cle'] . '/etat-echec.json']));
        verifier(
            'alerte : dernière sauvegarde en échec — alerte rouge',
            is_array($echec) && str_contains(implode(' ', $echec['details']), 'échoué'),
            json_encode($echec, JSON_UNESCAPED_UNICODE)
        );

        $sansCle = sauvegarde_alerte(array_replace($options, [
            'state'          => $site['cle'] . '/dernier-etat.json',
            'backup_dir'     => $site['racine'] . '/cle-absente',
            'exiger_montage' => true,
        ]));
        verifier(
            'alerte : clé absente — alerte rouge',
            is_array($sansCle) && $sansCle['niveau'] === 'rouge',
            json_encode($sansCle, JSON_UNESCAPED_UNICODE)
        );
    } finally {
        tests_supprimer_dossier($site['racine']);
    }

    // 13. Installation : minuteur systemd et commande unique.
    $service = dirname(__DIR__, 2) . '/deploy/systemd/histoiredefamille-sauvegarde.service';
    $timer   = dirname(__DIR__, 2) . '/deploy/systemd/histoiredefamille-sauvegarde.timer';
    verifier('installation : unité systemd de sauvegarde fournie', is_file($service), $service);
    $contenuTimer = is_file($timer) ? (string) file_get_contents($timer) : '';
    verifier_contient('installation : timer quotidien à 3 h 30', 'OnCalendar=*-*-* 03:30:00', $contenuTimer);
    verifier_contient('installation : timer rattrapé si le Pi était éteint', 'Persistent=true', $contenuTimer);

    $install = (string) file_get_contents(dirname(__DIR__, 2) . '/deploy/install.sh');
    verifier_contient('installation : le minuteur est activé par install.sh', 'systemctl enable --now histoiredefamille-sauvegarde.timer', $install);
    verifier(
        'installation : l’ancienne sauvegarde par cron n’est plus installée',
        !str_contains($install, '15 3 * * *'),
        'ancienne ligne cron encore présente'
    );

    $commande = dirname(__DIR__) . '/cli/sauvegarde.php';
    $codeCommande = is_file($commande) ? (string) file_get_contents($commande) : '';
    verifier('commande : php app/cli/sauvegarde.php existe', is_file($commande));
    verifier_contient('commande : code de sortie non nul en cas d’échec', 'exit(1)', $codeCommande);
    verifier(
        'commande : la clé absente est expliquée en français simple',
        str_contains($codeCommande, 'Aucune donnée n’a été modifiée'),
        'message de refus absent'
    );
}
