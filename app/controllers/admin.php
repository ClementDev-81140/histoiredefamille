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
    ], 'layout_admin');
}

/** Lit l'état de la dernière sauvegarde (écrit par deploy/backup.sh). */
function backup_state(): ?array
{
    $file = (string) config('maintenance.backup_state');
    if (!is_readable($file)) {
        return null;
    }
    $json = json_decode((string) file_get_contents($file), true);
    return is_array($json) ? $json : null;
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
            'description' => site_setting('description'),
            'contact'     => site_setting('contact'),
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

    if ($errors !== []) {
        form_fail($errors, $values, '/admin/reglages');
    }

    foreach ($values as $key => $value) {
        save_site_setting($key, $value);
    }

    log_activity((int) current_account()['id'], 'reglages', 'Modification des réglages du site');
    flash('succes', 'Les réglages ont été enregistrés.');
    redirect('/admin/reglages');
}

/** Compte personnel de l'administrateur : changement de mot de passe. */
function admin_account(): void
{
    require_admin();

    render('admin/mon_compte', [
        'titre'   => 'Mon compte',
        'erreurs' => form_errors(),
        'account' => current_account(),
    ], 'layout_admin');
}

/** Traitement du changement de mot de passe personnel. */
function admin_account_save(): void
{
    require_admin();
    csrf_check();

    $account  = current_account();
    $current  = (string) ($_POST['current_password'] ?? '');
    $new      = (string) ($_POST['new_password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    $errors = [];
    if (!password_check($current, (string) $account['password_hash'])) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
    }
    if (mb_strlen($new) < 10) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
    }
    if ($new !== $confirm) {
        $errors[] = 'Les deux nouveaux mots de passe ne sont pas identiques.';
    }
    if ($new !== '' && $new === $current) {
        $errors[] = 'Le nouveau mot de passe doit être différent de l’ancien.';
    }

    if ($errors !== []) {
        form_fail($errors, [], '/admin/mon-compte');
    }

    db_update('accounts', (int) $account['id'], [
        'password_hash' => password_make($new),
        'updated_at'    => time(),
    ]);

    log_activity((int) $account['id'], 'mot_de_passe', 'Changement de son propre mot de passe');
    flash('succes', 'Votre mot de passe a été modifié.');
    redirect('/admin/mon-compte');
}
