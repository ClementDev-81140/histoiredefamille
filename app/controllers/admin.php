<?php
/**
 * Administration : tableau de bord, état du site, réglages, compte personnel.
 */

declare(strict_types=1);

/**
 * Tableau de bord : deux parties clairement séparées,
 * « Partie publique » et « Partie famille ».
 */
function admin_dashboard(): void
{
    require_admin();

    $stats = [
        'public_documents' => (int) db_value("SELECT COUNT(*) FROM documents WHERE visibility = 'public' AND is_attachment = 0", [], 0),
        'public_pages'     => (int) db_value("SELECT COUNT(*) FROM pages WHERE visibility = 'public' AND is_published = 1", [], 0),
        'public_persons'   => (int) db_value("SELECT COUNT(*) FROM persons WHERE visibility = 'public'", [], 0),
        'family_documents' => (int) db_value("SELECT COUNT(*) FROM documents WHERE visibility IN ('famille','branches') AND is_attachment = 0", [], 0),
        'private_documents' => (int) db_value("SELECT COUNT(*) FROM documents WHERE visibility = 'prive' AND is_attachment = 0", [], 0),
        'family_pages'     => (int) db_value("SELECT COUNT(*) FROM pages WHERE visibility IN ('famille','branches')", [], 0),
        'persons'          => (int) db_value('SELECT COUNT(*) FROM persons', [], 0),
        'families'         => (int) db_value('SELECT COUNT(*) FROM families', [], 0),
        'branches'         => (int) db_value('SELECT COUNT(*) FROM branches', [], 0),
        'branches_active'  => (int) db_value('SELECT COUNT(*) FROM branches WHERE is_active = 1', [], 0),
        'admins'           => (int) db_value("SELECT COUNT(*) FROM accounts WHERE role = 'admin' AND is_active = 1", [], 0),
        'documents_total'  => (int) db_value('SELECT COUNT(*) FROM documents WHERE is_attachment = 0', [], 0),
        'incoming'         => count(incoming_files()),
        'storage_free'     => storage_free_space(),
        'last_backup'      => backup_state(),
    ];

    $documents = db_all(
        'SELECT d.*, c.name AS category_name FROM documents d
         LEFT JOIN categories c ON c.id = d.category_id
         WHERE d.is_attachment = 0
         ORDER BY d.created_at DESC LIMIT 6'
    );

    $activity = db_all(
        'SELECT al.*, a.username FROM activity_log al
         LEFT JOIN accounts a ON a.id = al.account_id
         ORDER BY al.created_at DESC LIMIT 8'
    );

    render('admin/tableau', [
        'titre'     => 'Tableau de bord',
        'stats'     => $stats,
        'documents' => $documents,
        'activity'  => $activity,
        'alertes'   => journal_alertes(),
    ], 'layout_admin');
}

/** État technique du site (utile pour le dépannage). */
function admin_status(): void
{
    require_admin();

    $root      = rtrim((string) config('storage.root'), '/');
    $dbPath    = (string) config('database');
    $checks    = [];

    $checks[] = [
        'label' => 'Fichier de configuration',
        'value' => (string) config('_config_file'),
        'ok'    => is_file((string) config('_config_file')),
    ];
    $checks[] = ['label' => 'Dossier de stockage', 'value' => $root, 'ok' => is_dir($root) && is_writable($root)];
    $checks[] = ['label' => 'Dossier « arrivée »', 'value' => incoming_path(), 'ok' => is_dir(incoming_path()) && is_writable(incoming_path())];
    $checks[] = ['label' => 'Base de données', 'value' => $dbPath, 'ok' => is_file($dbPath) && is_writable($dbPath)];
    $checks[] = ['label' => 'Extension GD (miniatures)', 'value' => extension_loaded('gd') ? 'oui' : 'non', 'ok' => extension_loaded('gd')];
    $checks[] = ['label' => 'Extension fileinfo', 'value' => extension_loaded('fileinfo') ? 'oui' : 'non', 'ok' => extension_loaded('fileinfo')];
    $checks[] = ['label' => 'Extension pdo_sqlite', 'value' => extension_loaded('pdo_sqlite') ? 'oui' : 'non', 'ok' => extension_loaded('pdo_sqlite')];
    $checks[] = ['label' => 'Extension mbstring', 'value' => extension_loaded('mbstring') ? 'oui' : 'non', 'ok' => extension_loaded('mbstring')];

    $ini = [
        'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
        'post_max_size'       => (string) ini_get('post_max_size'),
        'memory_limit'        => (string) ini_get('memory_limit'),
        'max_execution_time'  => (string) ini_get('max_execution_time'),
        'output_buffering'    => (string) ini_get('output_buffering'),
    ];

    $logTail = [];
    $logFile = (string) config('maintenance.backup_log');
    if (is_readable($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $logTail = array_slice($lines, -15);
    }

    render('admin/etat', [
        'titre'    => 'État du site',
        'checks'   => $checks,
        'ini'      => $ini,
        'backup'   => backup_state(),
        'logTail'  => $logTail,
        'logFile'  => $logFile,
        'dbSize'   => is_file($dbPath) ? (int) filesize($dbPath) : 0,
        'walSize'  => is_file($dbPath . '-wal') ? (int) filesize($dbPath . '-wal') : 0,
        'free'     => storage_free_space(),
        'streams'  => stream_active_count(),
        'php'      => PHP_VERSION,
        'alerte'   => sauvegarde_alerte(),
        'verification' => mise_en_ligne_dernier(),
        'sauvegarde' => [
            'dossier'     => (string) config('maintenance.backup_dir'),
            'libre_cle'   => sauvegarde_espace_libre((string) config('maintenance.backup_dir')),
            'support'     => sauvegarde_support_ok(sauvegarde_options()),
            'rotation'    => backup_rotation_compte((string) config('maintenance.backup_dir')),
        ],
    ], 'layout_admin');
}

/** Lit l'état de la dernière sauvegarde (écrit par app/cli/sauvegarde.php). */
function backup_state(): ?array
{
    return sauvegarde_etat_lire((string) config('maintenance.backup_state'));
}

/**
 * Nombre de copies conservées, par dossier de rotation.
 *
 * @return array{quotidiennes: int, hebdomadaires: int, mensuelles: int}
 */
function backup_rotation_compte(string $dir): array
{
    $compter = static function (string $sous, string $motif) use ($dir): int {
        $fichiers = glob(rtrim($dir, '/') . '/' . $sous . '/' . $motif);
        return is_array($fichiers) ? count($fichiers) : 0;
    };

    return [
        'quotidiennes'  => $compter('base', 'histoire-*.sqlite.gz'),
        'hebdomadaires' => $compter('hebdo', 'histoire-*.sqlite.gz'),
        'mensuelles'    => $compter('mensuel', 'histoire-*.sqlite.gz'),
    ];
}

/** Réglages généraux du site. */
function admin_settings(): void
{
    require_admin();

    render('admin/reglages', [
        'titre'   => 'Réglages du site',
        'erreurs' => form_errors(),
        'old'     => form_old(),
        'values'  => [
            'name'        => site_setting('name'),
            'subtitle'    => site_setting('subtitle'),
            // La description affichée sur le site, même quand elle vient de
            // la phrase livrée par défaut : le champ dit ce que l'on voit.
            'description' => apparence_description_site(),
            'contact'     => site_setting('contact'),
            // Adresse publique : elle sert dans les messages d'accès envoyés
            // aux personnes. Vide, on reprend celle du fichier de configuration.
            'adresse'     => site_setting('adresse') !== ''
                ? site_setting('adresse')
                : rtrim((string) config('site.url', ''), '/'),
            'devise'      => site_setting('devise'),
            'fondee'      => site_setting('fondee'),
            'tchat_actif' => tchat_actif() ? '1' : '0',
            'tchat_conservation_mois' => (string) tchat_conservation_mois(),
            // Envoi de gros fichiers par morceaux.
            'envoi_morceau_mo' => (string) envoi_reglages()['morceau_mo'],
            'envoi_max_mo'     => (string) envoi_reglages()['max_mo'],
            // Conversion des vidéos en MP4.
            'conversion_active'             => conversion_reglages()['active'] ? '1' : '0',
            'conversion_qualite'            => (string) conversion_reglages()['qualite'],
            'conversion_supprimer_original' => conversion_reglages()['supprimer_original'] ? '1' : '0',
        ],
    ], 'layout_admin');
}

/** Enregistrement des réglages. */
function admin_settings_save(): void
{
    require_admin();
    csrf_check();

    $values = [
        'name'        => post('name'),
        'subtitle'    => post('subtitle'),
        'description' => post('description'),
        'contact'     => post('contact'),
        'adresse'     => rtrim(post('adresse'), '/'),
        // Ligne de conduite du journal (facultatif).
        'devise'      => post('devise'),
        'fondee'      => post('fondee'),
        // Case à cocher : absente de l'envoi quand elle est décochée.
        'tchat_actif' => post('tchat_actif') === '1' ? '1' : '0',
        'tchat_conservation_mois' => (string) post('tchat_conservation_mois', '12'),
        'envoi_morceau_mo' => (string) post('envoi_morceau_mo', '5'),
        'envoi_max_mo'     => (string) post('envoi_max_mo', '4096'),
        // Conversion des vidéos : la case absente vaut « non ».
        'conversion_active' => post('conversion_active') === '0' ? '0' : '1',
        'conversion_qualite' => (string) post('conversion_qualite', 'equilibre'),
        'conversion_supprimer_original' => post('conversion_supprimer_original') === '1' ? '1' : '0',
    ];

    $errors = [];
    if ($values['name'] === '' || mb_strlen($values['name']) > 100) {
        $errors[] = 'Le nom du site est obligatoire (100 caractères maximum).';
    }
    if (mb_strlen($values['subtitle']) > 150 || mb_strlen($values['description']) > 500) {
        $errors[] = 'Le sous-titre (150) ou la description (500) est trop long.';
    }
    if ($values['contact'] !== '' && !filter_var($values['contact'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L’adresse de contact n’est pas valide.';
    }
    if ($values['adresse'] !== '') {
        $parties = parse_url($values['adresse']);
        $schema  = is_array($parties) ? strtolower((string) ($parties['scheme'] ?? '')) : '';
        if (!in_array($schema, ['http', 'https'], true) || empty($parties['host'])) {
            $errors[] = 'L’adresse publique doit commencer par https:// (par exemple https://histoiredefamille.eu).';
        }
    }
    if (mb_strlen($values['devise']) > 120) {
        $errors[] = 'La devise est trop longue (120 caractères maximum).';
    }
    if ($values['fondee'] !== '' && preg_match('/^\d{4}$/', $values['fondee']) !== 1) {
        $errors[] = 'L’année de fondation doit s’écrire en quatre chiffres (par exemple 1889).';
    }
    if (!in_array($values['tchat_conservation_mois'], ['0', '3', '6', '12'], true)) {
        $errors[] = 'La durée de conservation du tchat n’est pas valide.';
    }
    // Envoi par morceaux : bornes strictes. Un morceau de plus de 20 Mo ne
    // passerait pas les limites de Cloudflare, donc on refuse.
    $morceau = (int) $values['envoi_morceau_mo'];
    if ($morceau < 1 || $morceau > 20) {
        $errors[] = 'La taille d’un morceau doit être comprise entre 1 et 20 Mo.';
    }
    $maxMo = (int) $values['envoi_max_mo'];
    if ($maxMo < 50 || $maxMo > 16384) {
        $errors[] = 'La taille maximale d’un fichier doit être comprise entre 50 Mo et 16 Go.';
    }
    if (!in_array($values['conversion_qualite'], ['leger', 'equilibre', 'haute'], true)) {
        $errors[] = 'La qualité de conversion n’est pas valide.';
    }

    if ($errors !== []) {
        form_fail($errors, $values, '/admin/reglages');
    }

    foreach ($values as $key => $value) {
        save_site_setting($key, $value);
    }

    log_activity(
        (int) current_account()['id'],
        'reglages',
        'Modification des réglages du site (tchat : retour en arrière possible dans « Réglages »)'
    );
    flash('succes', 'Les réglages ont été enregistrés.');
    redirect('/admin/reglages');
}
