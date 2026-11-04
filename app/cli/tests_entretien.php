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

// Les fonctions de la matrice d'accès vivent dans app/lib/ : elles servent
// uniquement aux tests, donc elles ne sont pas chargées par le site.
require_once dirname(__DIR__) . '/lib/matrice_acces.php';

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

        // Le titre « Sauvegarde : » est posé par les vues, jamais par le
        // message : sinon le bandeau affichait « Sauvegarde : Sauvegarde : … ».
        verifier(
            'alerte : le message ne répète pas « Sauvegarde : » (la vue s’en charge)',
            is_array($vieille) && !str_starts_with((string) $vieille['message'], 'Sauvegarde')
                && str_contains((string) $vieille['message'], '5 jours'),
            (string) ($vieille['message'] ?? '')
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

// ---------------------------------------------------------------------------
// Lot 2 — Restauration
// ---------------------------------------------------------------------------
function tests_restauration(): void
{
    echo "Restauration — essai séparé, remplacement confirmé, comparaison\n";

    $site    = tests_preparer_site_temp('hdf-restauration-');
    $options = $site['options'];

    try {
        // 1. Sauvegarde de référence.
        $sauvegarde = sauvegarde_executer($options);
        verifier('restauration : sauvegarde de référence réussie', $sauvegarde['ok'], $sauvegarde['message']);

        $compteursSauvegardes = tests_compteurs_base($site['base']);
        $empreinteSauvegardee = restauration_empreinte_fichiers($site['cle'] . '/fichiers');

        // 2. Le site vit après la sauvegarde : nouvelles lignes, fichier
        //    modifié, fichier supprimé, fichier ajouté après coup.
        $pdo = new PDO('sqlite:' . $site['base'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec("INSERT INTO comptes (nom) VALUES ('APRES-SAUVERGARDE')");
        $pdo->exec("UPDATE documents SET titre = 'Modifié après sauvegarde' WHERE id = 1");
        $pdo = null;
        tests_ecrire($site['donnees'] . '/medias/2026/11/photo.jpg', 'PHOTO-D-APRES');
        unlink($site['donnees'] . '/medias/2026/11/photo.jpg');
        tests_ecrire($site['donnees'] . '/medias/2026/11/nouveau.jpg', 'NOUVEAU-FICHIER');

        $compteursAvant = tests_compteurs_base($site['base']);
        verifier(
            'restauration : le site a bien changé depuis la sauvegarde',
            $compteursAvant !== $compteursSauvegardes,
            'compteurs identiques : ' . json_encode($compteursAvant)
        );

        // 3. Restauration d'ESSAI dans un dossier séparé.
        $essai = $site['racine'] . '/essai-restauration';
        $resultatEssai = restauration_essai($site['cle'], $essai);
        verifier('restauration : l’essai réussit', $resultatEssai['ok'], $resultatEssai['message']);
        verifier(
            'restauration : l’essai recrée la base et les fichiers dans un dossier séparé',
            is_file($essai . '/histoire.sqlite') && is_file($essai . '/LISEZ-MOI.txt')
                && is_file($essai . '/storage/medias/2026/11/photo.jpg')
        );
        verifier(
            'restauration : le nombre de lignes par table est identique à la sauvegarde',
            $resultatEssai['compteurs'] === $compteursSauvegardes,
            'sauvegarde : ' . json_encode($compteursSauvegardes) . ' — essai : ' . json_encode($resultatEssai['compteurs'])
        );
        verifier(
            'restauration : la somme de contrôle des fichiers restaurés est identique',
            restauration_empreinte_fichiers($essai . '/storage') === $empreinteSauvegardee,
            'empreintes différentes'
        );
        verifier(
            'restauration : l’essai ne touche pas au site en service',
            tests_compteurs_base($site['base']) === $compteursAvant
                && !is_file($site['donnees'] . '/medias/2026/11/photo.jpg'),
            'la base en service a été modifiée'
        );

        // 4. Aucune restauration ne se fait sans « OUI ».
        verifier('restauration : « OUI » est exigé', restauration_confirmation_valide('OUI'));
        foreach (['', 'oui', 'Oui', 'ok', 'OUI svp'] as $fausse) {
            verifier(
                'restauration : « ' . $fausse . ' » n’autorise pas le remplacement',
                !restauration_confirmation_valide($fausse)
            );
        }
        $codeCli = (string) file_get_contents(dirname(__DIR__) . '/cli/restauration.php');
        verifier(
            'restauration : la commande demande la confirmation avant de remplacer',
            str_contains($codeCli, 'restauration_confirmation_valide')
                && strpos($codeCli, 'restauration_confirmation_valide') < strpos($codeCli, 'restauration_remplacer'),
            'ordre des appels incorrect'
        );

        // 5. Remplacement réel (la confirmation est testée ci-dessus ; ici la
        //    fonction de bibliothèque est appelée directement, comme après un OUI).
        $remplacement = restauration_remplacer($options, $site['cle']);
        verifier('restauration : le remplacement réussit', $remplacement['ok'], $remplacement['message']);
        verifier(
            'restauration : la base du site est revenue à l’état sauvegardé',
            tests_compteurs_base($site['base']) === $compteursSauvegardes,
            'obtenu : ' . json_encode(tests_compteurs_base($site['base']))
        );
        verifier(
            'restauration : l’ancienne base est mise de côté, pas effacée',
            $remplacement['ancienne_base'] !== '' && is_file($remplacement['ancienne_base'])
                && (int) (tests_compteurs_base($remplacement['ancienne_base'])['comptes'] ?? 0) === 4,
            $remplacement['ancienne_base']
        );
        verifier(
            'restauration : un fichier absent de la sauvegarde est déplacé, jamais supprimé',
            !is_file($site['donnees'] . '/medias/2026/11/nouveau.jpg')
                && is_file($site['cle'] . '/avant-restauration/' . basename(dirname($remplacement['ancienne_base'])) . '/fichiers-retires/medias/2026/11/nouveau.jpg')
        );
        verifier(
            'restauration : le fichier restauré a le contenu de la sauvegarde',
            (string) @file_get_contents($site['donnees'] . '/medias/2026/11/photo.jpg') === 'PHOTO-FICTIVE'
        );
        $identique = true;
        foreach (['medias', 'miniatures', 'arrivee'] as $sous) {
            if (restauration_empreinte_fichiers($site['donnees'] . '/' . $sous)
                !== restauration_empreinte_fichiers($site['cle'] . '/fichiers/' . $sous)) {
                $identique = false;
            }
        }
        verifier(
            'restauration : l’empreinte des fichiers du site est celle de la sauvegarde',
            $identique,
            'empreintes différentes'
        );
        verifier(
            'restauration : la base en service n’a pas été déplacée par la restauration',
            is_file($site['base'])
        );

        // 6. Une archive abîmée est refusée avant tout remplacement.
        $abime = $site['racine'] . '/sauvegarde-abimee';
        @mkdir($abime . '/base', 0700, true);
        file_put_contents($abime . '/base/histoire-2000-01-01_000000.sqlite.gz', 'ceci n’est pas une archive gzip');

        $compteursAvantAbime = tests_compteurs_base($site['base']);
        $refus = restauration_remplacer($options, $abime);
        verifier('restauration : archive abîmée — refusée', $refus['ok'] === false, $refus['message']);
        verifier(
            'restauration : archive abîmée — le site n’est pas modifié',
            tests_compteurs_base($site['base']) === $compteursAvantAbime
        );

        // 7. Contrôle en lecture seule et date du dernier contrôle.
        $optionsControle = array_replace($options, ['restore_state' => $site['cle'] . '/derniere-restauration.json']);
        $controle = restauration_verifier($optionsControle, $site['cle']);
        verifier('restauration : contrôle de la dernière sauvegarde réussi', $controle['ok'], $controle['message']);
        $etatControle = restauration_etat_lire($site['cle'] . '/derniere-restauration.json');
        verifier(
            'restauration : la date du contrôle est enregistrée',
            is_array($etatControle) && ($etatControle['status'] ?? '') === 'ok'
                && (int) ($etatControle['date'] ?? 0) > time() - 120,
            json_encode($etatControle, JSON_UNESCAPED_UNICODE)
        );
        verifier(
            'restauration : le contrôle ne modifie pas la base en service',
            tests_compteurs_base($site['base']) === $compteursAvantAbime
        );
    } finally {
        tests_supprimer_dossier($site['racine']);
    }

    $commande = dirname(__DIR__) . '/cli/restauration.php';
    verifier('restauration : la commande php app/cli/restauration.php existe', is_file($commande));
    $codeCommande = is_file($commande) ? (string) file_get_contents($commande) : '';
    verifier(
        'restauration : par défaut, la commande restaure dans un dossier séparé',
        str_contains($codeCommande, 'restauration_essai') && str_contains($codeCommande, 'RIEN n’a été remplacé'),
        'comportement par défaut incorrect'
    );
    verifier(
        'restauration : le message de refus dit que rien n’a été modifié',
        str_contains($codeCommande, 'Le site n’a pas été modifié'),
        'message de refus absent'
    );
}

// ---------------------------------------------------------------------------
// Lot 4 — Vérification avant ouverture
// ---------------------------------------------------------------------------

function tests_verification(): void
{
    echo "Vérification avant ouverture — mêmes contrôles que la commande\n";

    $option = static function (array $controles, string $cle): ?array {
        foreach ($controles as $controle) {
            if ($controle['cle'] === $cle) {
                return $controle;
            }
        }
        return null;
    };

    // 1. La liste elle-même.
    $controles = mise_en_ligne_controles(['http' => false, 'systeme' => false]);
    $cles = array_column($controles, 'cle');
    $valides = ['ok', 'corriger', 'info'];
    $bienFormes = array_filter($controles, static fn (array $c): bool =>
        $c['titre'] !== '' && $c['detail'] !== '' && in_array($c['statut'], $valides, true));
    verifier(
        'vérification : chaque contrôle a un titre, un état et une explication',
        count($bienFormes) === count($controles) && count($controles) >= 15,
        count($controles) . ' contrôle(s)'
    );
    verifier('vérification : aucun contrôle en double', count($cles) === count(array_unique($cles)));
    verifier(
        'vérification : chaque point à corriger indique quoi faire',
        array_filter($controles, static fn (array $c): bool => $c['statut'] === 'corriger' && $c['remede'] === '') === [],
        implode(', ', array_map(static fn (array $c): string => $c['cle'],
            array_filter($controles, static fn (array $c): bool => $c['statut'] === 'corriger' && $c['remede'] === '')))
    );

    // 2. Les points bloquants sont vus comme bloquants.
    $ko = mise_en_ligne_controles([
        'https_only' => false,
        'adresse'    => 'http://exemple.test',
        'http' => false, 'systeme' => false,
    ]);
    verifier('vérification : https_only à false est un point à corriger',
        ($option($ko, 'https_only')['statut'] ?? '') === 'corriger');
    verifier('vérification : une adresse publique en http est un point à corriger',
        ($option($ko, 'adresse')['statut'] ?? '') === 'corriger');

    $ok = mise_en_ligne_controles([
        'https_only' => true,
        'adresse'    => 'https://exemple.test',
        'http' => false, 'systeme' => false,
    ]);
    verifier('vérification : https_only à true et adresse en https sont au vert',
        ($option($ok, 'https_only')['statut'] ?? '') === 'ok'
        && ($option($ok, 'adresse')['statut'] ?? '') === 'ok');

    // 3. Clé de sauvegarde absente / présente.
    $racine = tests_dossier_temp('hdf-verif-');
    try {
        $absente = mise_en_ligne_controles([
            'http' => false, 'systeme' => false,
            'sauvegarde' => [
                'backup_dir' => $racine . '/cle-absente',
                'state' => $racine . '/cle-absente/dernier-etat.json',
                'restore_state' => $racine . '/cle-absente/derniere-restauration.json',
                'exiger_montage' => true,
            ],
        ]);
        verifier('vérification : clé absente → « à corriger »',
            ($option($absente, 'sauvegarde_support')['statut'] ?? '') === 'corriger',
            (string) ($option($absente, 'sauvegarde_support')['detail'] ?? ''));
        verifier('vérification : clé absente → aucune sauvegarde enregistrée n’est signalée comme récente',
            ($option($absente, 'sauvegarde_recente')['statut'] ?? '') === 'corriger');

        $dossierCle = $racine . '/cle';
        mkdir($dossierCle, 0700, true);
        $presente = mise_en_ligne_controles([
            'http' => false, 'systeme' => false,
            'sauvegarde' => [
                'backup_dir' => $dossierCle,
                'state' => $dossierCle . '/dernier-etat.json',
                'restore_state' => $dossierCle . '/derniere-restauration.json',
                'exiger_montage' => false,
            ],
        ]);
        verifier('vérification : clé présente et inscriptible → au vert',
            ($option($presente, 'sauvegarde_support')['statut'] ?? '') === 'ok',
            (string) ($option($presente, 'sauvegarde_support')['detail'] ?? ''));

        // 4. Sauvegarde et restauration datées.
        sauvegarde_etat_ecrire([
            'date' => time(), 'date_humain' => date('d/m/Y à H:i'),
            'status' => 'ok', 'message' => '',
        ], $dossierCle . '/dernier-etat.json');
        sauvegarde_etat_ecrire([
            'date' => time(), 'date_humain' => date('d/m/Y à H:i'),
            'status' => 'ok', 'mode' => 'controle',
        ], $dossierCle . '/derniere-restauration.json');
        $recentes = mise_en_ligne_controles([
            'http' => false, 'systeme' => false,
            'sauvegarde' => [
                'backup_dir' => $dossierCle,
                'state' => $dossierCle . '/dernier-etat.json',
                'restore_state' => $dossierCle . '/derniere-restauration.json',
                'exiger_montage' => false,
            ],
        ]);
        verifier('vérification : sauvegarde du jour → au vert',
            ($option($recentes, 'sauvegarde_recente')['statut'] ?? '') === 'ok');
        verifier('vérification : test de restauration du jour → au vert',
            ($option($recentes, 'restauration')['statut'] ?? '') === 'ok');

        sauvegarde_etat_ecrire([
            'date' => time() - 5 * 86400, 'date_humain' => date('d/m/Y à H:i'),
            'status' => 'ok', 'message' => '',
        ], $dossierCle . '/dernier-etat.json');
        $vieille = mise_en_ligne_controles([
            'http' => false, 'systeme' => false,
            'sauvegarde' => [
                'backup_dir' => $dossierCle,
                'state' => $dossierCle . '/dernier-etat.json',
                'restore_state' => $dossierCle . '/derniere-restauration.json',
                'exiger_montage' => false,
            ],
        ]);
        verifier('vérification : sauvegarde vieille de cinq jours → « à corriger »',
            ($option($vieille, 'sauvegarde_recente')['statut'] ?? '') === 'corriger');

        // 5. En-têtes HTTP et cookies, sur une réponse réelle simulée.
        $reponseOk = mise_en_ligne_analyser_reponse(
            "HTTP/1.1 200 OK\r\n"
            . "Content-Security-Policy: default-src 'self'; frame-ancestors 'none'\r\n"
            . "X-Frame-Options: DENY\r\n"
            . "X-Content-Type-Options: nosniff\r\n"
            . "Referrer-Policy: strict-origin-when-cross-origin\r\n"
            . "Strict-Transport-Security: max-age=31536000; includeSubDomains\r\n"
            . "Set-Cookie: HDF_SESSION=abc; path=/; secure; HttpOnly; SameSite=Lax\r\n"
            . "\r\n<html>ok</html>"
        );
        verifier('vérification : la réponse HTTP est bien décodée (code, en-têtes, corps)',
            $reponseOk['statut'] === 200
            && str_contains($reponseOk['entetes']['content-security-policy'] ?? '', "default-src 'self'")
            && $reponseOk['corps'] === '<html>ok</html>');

        $entetes = mise_en_ligne_controles([
            'http' => true, 'systeme' => false, 'https_only' => true,
            'reponse_http' => $reponseOk,
        ]);
        verifier('vérification : en-têtes complets et cookie sûr → au vert',
            ($option($entetes, 'entetes')['statut'] ?? '') === 'ok'
            && ($option($entetes, 'cookies')['statut'] ?? '') === 'ok'
            && ($option($entetes, 'signature')['statut'] ?? '') === 'ok',
            (string) ($option($entetes, 'entetes')['detail'] ?? '')
            . ' / ' . (string) ($option($entetes, 'cookies')['detail'] ?? ''));

        $reponseFaible = mise_en_ligne_analyser_reponse(
            "HTTP/1.1 200 OK\r\n"
            . "Server: Apache/2.4.62 (Raspbian)\r\n"
            . "X-Powered-By: PHP/8.2.29\r\n"
            . "Set-Cookie: HDF_SESSION=abc; path=/\r\n"
            . "\r\n<html>pas d’en-têtes</html>"
        );
        $faibles = mise_en_ligne_controles([
            'http' => true, 'systeme' => false, 'https_only' => true,
            'reponse_http' => $reponseFaible,
        ]);
        verifier('vérification : en-têtes manquants → « à corriger »',
            ($option($faibles, 'entetes')['statut'] ?? '') === 'corriger');
        verifier('vérification : cookie sans Secure ni HttpOnly → « à corriger »',
            ($option($faibles, 'cookies')['statut'] ?? '') === 'corriger');
        verifier('vérification : version d’Apache et de PHP annoncée → « à corriger »',
            ($option($faibles, 'signature')['statut'] ?? '') === 'corriger');

        $sansVersion = mise_en_ligne_analyser_reponse(
            "HTTP/1.1 200 OK\r\n"
            . "Server: Apache\r\n"
            . "Content-Security-Policy: default-src 'self'\r\n"
            . "X-Frame-Options: DENY\r\n"
            . "X-Content-Type-Options: nosniff\r\n"
            . "Referrer-Policy: strict-origin-when-cross-origin\r\n"
            . "Set-Cookie: HDF_SESSION=abc; path=/; secure; HttpOnly; SameSite=Lax\r\n"
            . "\r\n<html>ok</html>"
        );
        $propre = mise_en_ligne_controles([
            'http' => true, 'systeme' => false, 'https_only' => false,
            'reponse_http' => $sansVersion,
        ]);
        verifier('vérification : « Server: Apache » sans numéro de version n’est pas une fuite',
            ($option($propre, 'signature')['statut'] ?? '') === 'ok',
            (string) ($option($propre, 'signature')['detail'] ?? ''));

        // 6. Apache injoignable : message clair, pas de plantage.
        $muet = mise_en_ligne_controles([
            'http' => true, 'systeme' => false,
            'reponse_http' => ['ok' => false, 'erreur' => 'Apache ne répond pas sur 127.0.0.1:80 (essai).',
                'statut' => 0, 'entetes' => [], 'corps' => ''],
        ]);
        verifier('vérification : Apache muet → « à corriger » avec la marche à suivre',
            ($option($muet, 'entetes')['statut'] ?? '') === 'corriger'
            && str_contains((string) ($option($muet, 'entetes')['remede'] ?? ''), 'systemctl status apache2'));

        // 7. Fichiers sensibles dans public/.
        $public = $racine . '/public';
        mkdir($public . '/.git', 0700, true);
        tests_ecrire($public . '/.git/config', '[core]');
        tests_ecrire($public . '/config.local.php', '<?php return [];');
        tests_ecrire($public . '/histoire.sqlite', 'SQLITE');
        tests_ecrire($public . '/app.js', 'console.log(1);');
        tests_ecrire($public . '/.htaccess', 'RewriteEngine On');
        $trouves = mise_en_ligne_fichiers_sensibles($public);
        sort($trouves);
        verifier(
            'vérification : configuration, base et .git dans public/ sont signalés',
            $trouves === ['.git/config', 'config.local.php', 'histoire.sqlite'],
            implode(', ', $trouves)
        );
        verifier(
            'vérification : .htaccess et les fichiers du site ne sont pas signalés',
            !in_array('.htaccess', $trouves, true) && !in_array('app.js', $trouves, true)
        );

        // 8. Enregistrement du résultat et relecture par la page.
        $fichierEtat = $racine . '/mise-en-ligne.json';
        $enregistre = mise_en_ligne_enregistrer($recentes, $fichierEtat);
        $relu = mise_en_ligne_dernier($fichierEtat);
        verifier('vérification : le résultat est enregistré puis relu',
            $enregistre && is_array($relu) && count((array) ($relu['controles'] ?? [])) === count($recentes));
        $contenu = (string) @file_get_contents($fichierEtat);
        verifier(
            'vérification : le fichier de résultat ne contient aucun mot de passe',
            !str_contains($contenu, 'password') && !str_contains($contenu, 'motdepasse')
            && !str_contains($contenu, 'password_hash')
        );
        verifier('vérification : le fichier de résultat est lisible par le seul propriétaire et le groupe',
            (substr(sprintf('%o', fileperms($fichierEtat)), -4)) === '0640',
            substr(sprintf('%o', fileperms($fichierEtat)), -4));

        // 9. Verdict.
        $pret = mise_en_ligne_verdict([
            ['cle' => 'a', 'titre' => 'A', 'statut' => 'ok', 'detail' => 'x', 'remede' => ''],
            ['cle' => 'b', 'titre' => 'B', 'statut' => 'info', 'detail' => 'y', 'remede' => ''],
        ]);
        verifier('vérification : tout au vert → « PRÊT À OUVRIR »',
            $pret['pret'] && $pret['texte'] === 'PRÊT À OUVRIR');
        $pasPret = mise_en_ligne_verdict([
            ['cle' => 'a', 'titre' => 'A', 'statut' => 'corriger', 'detail' => 'x', 'remede' => 'z'],
            ['cle' => 'b', 'titre' => 'B', 'statut' => 'corriger', 'detail' => 'y', 'remede' => 'z'],
        ]);
        verifier('vérification : deux points à corriger → « PAS PRÊT : 2 point(s) à corriger »',
            !$pasPret['pret'] && $pasPret['texte'] === 'PAS PRÊT : 2 point(s) à corriger',
            $pasPret['texte']);

        // 10. La page « État du site » reprend le résultat.
        ob_start();
        render('admin/etat', [
            'titre'    => 'État du site',
            'checks'   => [['label' => 'Fichier de configuration', 'value' => 'config.local.php', 'ok' => true]],
            'ini'      => [
                'upload_max_filesize' => '50M', 'post_max_size' => '52M', 'memory_limit' => '256M',
                'max_execution_time' => '30', 'output_buffering' => '4096',
            ],
            'backup'   => null,
            'logTail'  => [],
            'logFile'  => '',
            'dbSize'   => 0,
            'walSize'  => 0,
            'free'     => null,
            'streams'  => 0,
            'php'      => PHP_VERSION,
            'alerte'   => [
                'niveau'  => 'rouge',
                'message' => 'La dernière sauvegarde réussie date de 5 jours (limite : 3).',
                'details' => [
                    'La dernière sauvegarde réussie date de 5 jours (limite : 3).',
                    'Espace libre faible sur la clé : 120 Mo.',
                ],
            ],
            'sauvegarde' => [
                'dossier' => '/mnt/sauvegarde/histoiredefamille',
                'libre_cle' => null,
                'support' => ['ok' => false, 'montage' => '', 'raison' => 'La clé USB n’est pas montée.'],
                'rotation' => ['quotidiennes' => 0, 'hebdomadaires' => 0, 'mensuelles' => 0],
            ],
            'verification' => [
                'date' => time(), 'date_humain' => '01/01/2026 à 10:00',
                'verdict' => 'PAS PRÊT : 1 point(s) à corriger', 'pret' => false,
                'controles' => [
                    ['cle' => 'https_only', 'titre' => 'HTTPS obligatoire', 'statut' => 'corriger',
                        'detail' => 'Le réglage https_only est à false.', 'remede' => 'Mettez https_only à true.'],
                    ['cle' => 'admins', 'titre' => 'Administrateurs actifs', 'statut' => 'ok',
                        'detail' => '3 comptes administrateur actifs.', 'remede' => ''],
                ],
            ],
        ]);
        $page = (string) ob_get_clean();

        verifier('page « État du site » : la section de vérification est présente',
            str_contains($page, 'Vérification avant ouverture'));
        verifier('page « État du site » : le verdict enregistré est affiché',
            str_contains($page, 'PAS PRÊT : 1 point(s) à corriger'));
        verifier('page « État du site » : le point à corriger et son remède sont affichés',
            str_contains($page, 'À CORRIGER') && str_contains($page, 'Mettez https_only à true.'));
        verifier('page « État du site » : la date de la vérification est affichée',
            str_contains($page, '01/01/2026 à 10:00'));
        verifier(
            'page « État du site » : l’alerte de sauvegarde n’affiche « Sauvegarde : » qu’une fois',
            substr_count($page, 'Sauvegarde :') === 1
                && str_contains($page, 'La dernière sauvegarde réussie date de 5 jours (limite : 3).')
                && str_contains($page, 'Espace libre faible sur la clé : 120 Mo.'),
            (string) substr_count($page, 'Sauvegarde :')
        );
        verifier(
            'bandeau de l’administration : le titre « Sauvegarde : » est posé par la vue, pas par le message',
            substr_count((string) @file_get_contents(HDF_APP . '/views/layout_admin.php'), '<strong>Sauvegarde :</strong>') === 1
                && !str_contains((string) @file_get_contents(HDF_APP . '/lib/sauvegarde.php'), "'Sauvegarde : ' .")
        );
    } finally {
        tests_supprimer_dossier($racine);
    }
}

// ---------------------------------------------------------------------------
// Lot 3 — Matrice d'accès
// ---------------------------------------------------------------------------

/**
 * Exception levée à la place de `exit` quand une page se termine
 * (redirection, refus, réponse JSON). Voir hdf_terminer().
 */
class HdfTestArret extends Exception
{
}

function tests_matrice(bool $ecrireRapport = false): void
{
    echo "Matrice d’accès — toutes les routes × quatre profils\n";

    $problemes = matrice_routes_problemes();
    verifier(
        'matrice : toutes les routes de app/routes.php sont reconnues',
        $problemes === [],
        implode("\n", $problemes)
    );

    $routes = matrice_routes();
    verifier(
        'matrice : les routes sont lues dans le fichier (aucune recopie)',
        count($routes) >= 100,
        count($routes) . ' route(s) lues'
    );

    $f      = [];
    $lignes = [];
    $anomalies = [];
    $controles = [];
    $pdo = db();
    $transaction = false;

    try {
        // Tout est créé puis annulé : la base du site n'est pas modifiée.
        $pdo->beginTransaction();
        $transaction = true;

        $f         = matrice_fixtures();
        $lignes    = matrice_executer($f);
        $anomalies = matrice_anomalies($lignes);
        $controles = matrice_controles($f);
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        matrice_supprimer_fichiers($f);
    }

    // Les données de test ont bien disparu.
    $restes = 0;
    if (!empty($f['compte_branche_a'])) {
        $restes = (int) db_value('SELECT COUNT(*) FROM accounts WHERE id IN (?, ?, ?, ?)', [
            (int) $f['compte_branche_a'], (int) $f['compte_personne_b'],
            (int) $f['compte_voit_tout'], (int) $f['account_admin'],
        ], 0);
    }
    verifier('matrice : les comptes de test ont été supprimés', $restes === 0, $restes . ' compte(s) restant(s)');
    verifier(
        'matrice : les fichiers de test ont été supprimés',
        empty($f['dossier_fichiers']) || !is_dir((string) $f['dossier_fichiers'])
    );

    verifier(
        'matrice : chaque route répond comme le prévoit la règle d’accès',
        $anomalies === [],
        count($anomalies) . " anomalie(s) :\n"
            . implode("\n", array_map(
                static fn (array $a): string => $a['route'] . ' — ' . $a['profil']
                    . ' : attendu ' . $a['attendu'] . ', obtenu ' . $a['obtenu'],
                array_slice($anomalies, 0, 15)
            ))
    );

    $controlesKo = array_values(array_filter($controles, static fn (array $c): bool => !$c['ok']));
    verifier(
        'matrice : les contrôles ciblés (fichiers, recherche, album, tchat, POST) passent',
        $controlesKo === [],
        count($controlesKo) . " contrôle(s) en échec :\n"
            . implode("\n", array_map(
                static fn (array $c): string => $c['controle'] . ' : attendu ' . $c['attendu']
                    . ', obtenu ' . $c['obtenu'],
                array_slice($controlesKo, 0, 15)
            ))
    );

    $nbProfils = count(matrice_profils());
    verifier(
        'matrice : tous les profils ont un code attendu pour chaque route',
        count($lignes) === count($routes)
            && count(array_filter($lignes, static fn (array $l): bool => count($l['obtenu']) === $nbProfils)) === count($routes),
        $nbProfils . ' profil(s) attendu(s) par route'
    );

    if ($ecrireRapport) {
        $rapport = matrice_rapport($lignes, $anomalies, $controles);
        $cible   = dirname(__DIR__, 2) . '/docs/matrice-acces.md';
        $ecrit   = @file_put_contents($cible, $rapport) !== false;
        verifier('matrice : rapport écrit dans docs/matrice-acces.md', $ecrit, $cible);
        echo '  rapport : ', $cible, "\n";
    }
}
