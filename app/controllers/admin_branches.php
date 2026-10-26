<?php
/**
 * Administration des branches familiales (et de leur compte de connexion)
 * et des comptes administrateurs.
 */

declare(strict_types=1);

/** Liste des branches et de leurs comptes. */
function admin_branches(): void
{
    require_admin();

    $branches = db_all(
        "SELECT b.*, a.id AS account_id, a.username, a.is_active AS account_active, a.last_login_at,
                (SELECT COUNT(*) FROM content_branches cb WHERE cb.branch_id = b.id AND cb.kind = 'document') AS nb_documents,
                (SELECT COUNT(*) FROM content_branches cb WHERE cb.branch_id = b.id AND cb.kind = 'person') AS nb_personnes,
                (SELECT COUNT(*) FROM content_branches cb WHERE cb.branch_id = b.id AND cb.kind = 'page') AS nb_pages
         FROM branches b
         LEFT JOIN accounts a ON a.branch_id = b.id
         ORDER BY b.name COLLATE NOCASE"
    );

    // Mot de passe généré : affiché une seule fois, puis effacé de la session.
    $newPassword = $_SESSION['_new_password'] ?? null;
    unset($_SESSION['_new_password']);

    render('admin/branches', [
        'titre'       => 'Branches & comptes de famille',
        'branches'    => $branches,
        'newPassword' => is_array($newPassword) ? $newPassword : null,
    ], 'layout_admin');
}

/** Formulaire de création / modification d'une branche. */
function admin_branch_form(array $params = []): void
{
    require_admin();

    $id     = (int) ($params['id'] ?? 0);
    $branch = $id > 0 ? find_branch($id) : null;
    if ($id > 0 && !$branch) {
        http_response_code(404);
        render('erreur', ['titre' => 'Branche introuvable', 'message' => 'Cette branche n’existe pas.']);
        return;
    }

    $account = $id > 0
        ? db_one("SELECT * FROM accounts WHERE branch_id = ? AND role = 'famille'", [$id])
        : null;

    render('admin/branch_form', [
        'titre'    => $branch ? 'Modifier la branche ' . $branch['name'] : 'Nouvelle branche',
        'branch'   => $branch,
        'account'  => $account,
        'erreurs'  => form_errors(),
        'old'      => form_old(),
        'generated' => password_generate(),
    ], 'layout_admin');
}

/** Enregistrement d'une branche (création ou modification). */
function admin_branch_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $branch = $id > 0 ? find_branch($id) : null;
    $back   = $id > 0 ? '/admin/branches/' . $id : '/admin/branches/nouvelle';

    if ($id > 0 && !$branch) {
        http_response_code(404);
        render('erreur', ['titre' => 'Branche introuvable', 'message' => 'Cette branche n’existe pas.']);
        return;
    }

    $old = [
        'code'        => strtoupper(post('code')),
        'name'        => post('name'),
        'description' => post('description'),
        'see_all'     => post('see_all') === '1',
        'is_active'   => post('is_active') === '1',
        'password'    => (string) ($_POST['password'] ?? ''),
    ];

    $errors = [];

    if (!preg_match('/^[A-Z][A-Z0-9_]{1,29}$/', $old['code'])) {
        $errors[] = 'L’identifiant doit être en majuscules, commencer par une lettre et ne contenir '
            . 'que des lettres, chiffres et tirets bas (2 à 30 caractères). Exemple : RAVIART.';
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Le nom de la branche est obligatoire (100 caractères maximum).';
    }
    if ($old['password'] !== '' && mb_strlen($old['password']) < 10) {
        $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
    }

    $conflict = db_one(
        'SELECT id FROM branches WHERE code = ? COLLATE NOCASE AND id <> ?',
        [$old['code'], $id]
    );
    if ($conflict) {
        $errors[] = 'Une branche utilise déjà cet identifiant.';
    }
    $conflictAccount = db_one(
        'SELECT id FROM accounts WHERE username = ? COLLATE NOCASE AND (branch_id IS NULL OR branch_id <> ?)',
        [$old['code'], $id]
    );
    if ($conflictAccount) {
        $errors[] = 'Cet identifiant est déjà utilisé par un autre compte.';
    }

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    $now = time();
    $data = [
        'code'        => $old['code'],
        'name'        => $old['name'],
        'description' => $old['description'] !== '' ? $old['description'] : null,
        'see_all'     => $old['see_all'] ? 1 : 0,
        'is_active'   => $old['is_active'] ? 1 : 0,
        'updated_at'  => $now,
    ];

    $generatedPassword = null;

    db_transaction(static function () use ($id, $branch, $data, $old, $now, &$generatedPassword): void {
        if ($id > 0) {
            db_update('branches', $id, $data);
            $branchId = $id;
        } else {
            $data['created_at'] = $now;
            $branchId = db_insert('branches', $data);
        }

        $account = db_one("SELECT * FROM accounts WHERE branch_id = ? AND role = 'famille'", [$branchId]);

        // Mot de passe : saisi, ou généré aléatoirement à la création.
        $passwordHash = null;
        if ($old['password'] !== '') {
            $passwordHash = password_make($old['password']);
            $generatedPassword = null;
        } elseif ($account === null) {
            $generatedPassword = password_generate();
            $passwordHash     = password_make($generatedPassword);
        } elseif ($branch === null) {
            $generatedPassword = password_generate();
            $passwordHash     = password_make($generatedPassword);
        }

        if ($account === null) {
            db_insert('accounts', [
                'username'      => $data['code'],
                'display_name'  => $data['name'],
                'password_hash' => $passwordHash ?? password_make(password_generate()),
                'role'          => 'famille',
                'branch_id'     => $branchId,
                'is_active'     => $data['is_active'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } else {
            $accountData = [
                'username'     => $data['code'],
                'display_name' => $data['name'],
                'is_active'    => $data['is_active'],
                'updated_at'   => $now,
            ];
            if ($passwordHash !== null) {
                $accountData['password_hash'] = $passwordHash;
            }
            db_update('accounts', (int) $account['id'], $accountData);
        }
    });

    if ($generatedPassword !== null) {
        $_SESSION['_new_password'] = [
            'username' => $data['code'],
            'password' => $generatedPassword,
            'context'  => 'compte de la branche ' . $data['name'],
        ];
    }

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'branche_modifiee' : 'branche_ajoutee',
        $data['name'] . ' (' . $data['code'] . ')'
    );

    flash('succes', $id > 0 ? 'La branche a été modifiée.' : 'La branche et son compte ont été créés.');
    redirect('/admin/branches');
}

/** Génère un nouveau mot de passe pour le compte d'une branche. */
function admin_branch_password(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $branch = find_branch($id);
    $account = db_one("SELECT * FROM accounts WHERE branch_id = ? AND role = 'famille'", [$id]);

    if (!$branch || !$account) {
        flash('erreur', 'Branche ou compte introuvable.');
        redirect('/admin/branches');
    }

    $password = password_generate();

    db_update('accounts', (int) $account['id'], [
        'password_hash' => password_make($password),
        'updated_at'    => time(),
    ]);

    $_SESSION['_new_password'] = [
        'username' => (string) $account['username'],
        'password' => $password,
        'context'  => 'nouveau mot de passe pour la branche ' . $branch['name'],
    ];

    log_activity((int) current_account()['id'], 'mot_de_passe_branche', (string) $branch['code']);
    flash('succes', 'Un nouveau mot de passe a été généré pour la branche ' . $branch['name'] . '.');
    redirect('/admin/branches');
}

/** Active ou désactive une branche (et son compte). */
function admin_branch_toggle(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $branch = find_branch($id);
    if (!$branch) {
        flash('erreur', 'Branche introuvable.');
        redirect('/admin/branches');
    }

    $adminId  = (int) (current_account()['id'] ?? 0);
    $newState = (int) $branch['is_active'] === 1 ? 0 : 1;
    db_update('branches', $id, ['is_active' => $newState, 'updated_at' => time()]);
    db_exec("UPDATE accounts SET is_active = ?, updated_at = ? WHERE branch_id = ? AND role = 'famille'", [
        $newState,
        time(),
        $id,
    ]);

    // Un compte désactivé ne doit plus rester connecté.
    $account = current_account();
    if ($account && (int) ($account['branch_id'] ?? 0) === $id) {
        auth_logout();
    }

    log_activity(
        $adminId,
        $newState ? 'branche_activee' : 'branche_desactivee',
        (string) $branch['code']
    );
    flash('succes', $newState ? 'La branche est de nouveau active.' : 'La branche est désactivée : son compte ne peut plus se connecter.');
    redirect('/admin/branches');
}

/** Supprime une branche, son compte et les liens vers ses contenus. */
function admin_branch_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $branch = find_branch($id);
    if (!$branch) {
        flash('erreur', 'Branche introuvable.');
        redirect('/admin/branches');
    }

    $links = (int) db_value('SELECT COUNT(*) FROM content_branches WHERE branch_id = ?', [$id], 0);
    $force = post('force') === '1';

    if ($links > 0 && !$force) {
        flash(
            'erreur',
            sprintf(
                'La branche « %s » est utilisée par %d contenu(s). Confirmez la suppression pour détacher ces contenus.',
                $branch['name'],
                $links
            )
        );
        redirect('/admin/branches');
    }

    db_transaction(static function () use ($id): void {
        db_exec("DELETE FROM accounts WHERE branch_id = ? AND role = 'famille'", [$id]);
        db_exec('DELETE FROM content_branches WHERE branch_id = ?', [$id]);
        db_exec('DELETE FROM branches WHERE id = ?', [$id]);
    });

    log_activity((int) current_account()['id'], 'branche_supprimee', (string) $branch['code']);
    flash('succes', 'La branche a été supprimée. Les contenus qui lui étaient réservés sont désormais invisibles aux familles.');
    redirect('/admin/branches');
}

// ---------------------------------------------------------------------------
// Comptes administrateurs
// ---------------------------------------------------------------------------

/** Liste et création des comptes administrateurs. */
function admin_admins(): void
{
    require_admin();

    $admins = db_all("SELECT * FROM accounts WHERE role = 'admin' ORDER BY username COLLATE NOCASE");
    $nbActive = count(array_filter($admins, static fn ($a) => (int) $a['is_active'] === 1));

    $newPassword = $_SESSION['_new_password'] ?? null;
    unset($_SESSION['_new_password']);

    render('admin/admins', [
        'titre'       => 'Comptes administrateurs',
        'admins'      => $admins,
        'nbActive'    => $nbActive,
        'newPassword' => is_array($newPassword) ? $newPassword : null,
        'erreurs'     => form_errors(),
        'old'         => form_old(),
        'generated'   => password_generate(),
    ], 'layout_admin');
}

/** Création d'un compte administrateur. */
function admin_admin_create(): void
{
    require_admin();
    csrf_check();

    $username = strtolower(post('username'));
    $display  = post('display_name');
    $password = (string) ($_POST['password'] ?? '');
    $generate = post('generate') === '1';

    $errors = [];
    if (!preg_match('/^[a-z][a-z0-9._-]{2,29}$/', $username)) {
        $errors[] = 'L’identifiant doit contenir de 3 à 30 caractères : lettres minuscules, chiffres, point, tiret ou souligné.';
    }
    if (mb_strlen($display) > 100) {
        $errors[] = 'Le nom affiché est trop long.';
    }
    if (db_one('SELECT id FROM accounts WHERE username = ? COLLATE NOCASE', [$username])) {
        $errors[] = 'Cet identifiant est déjà utilisé.';
    }

    $plain = null;
    if ($generate) {
        $plain = password_generate();
    } else {
        if (mb_strlen($password) < 10) {
            $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
        }
        $plain = $password;
    }

    if ($errors !== []) {
        form_fail($errors, ['username' => $username, 'display_name' => $display], '/admin/admins');
    }

    $now = time();
    db_insert('accounts', [
        'username'      => $username,
        'display_name'  => $display !== '' ? $display : $username,
        'password_hash' => password_make($plain),
        'role'          => 'admin',
        'branch_id'     => null,
        'is_active'     => 1,
        'created_at'    => $now,
        'updated_at'    => $now,
    ]);

    $_SESSION['_new_password'] = [
        'username' => $username,
        'password' => $plain,
        'context'  => 'nouveau compte administrateur',
    ];

    log_activity((int) current_account()['id'], 'admin_ajoute', $username);
    flash('succes', 'Le compte administrateur a été créé.');
    redirect('/admin/admins');
}

/** Réinitialise le mot de passe d'un administrateur. */
function admin_admin_password(array $params): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $account = db_one("SELECT * FROM accounts WHERE id = ? AND role = 'admin'", [$id]);
    if (!$account) {
        flash('erreur', 'Compte introuvable.');
        redirect('/admin/admins');
    }

    $password = password_generate();
    db_update('accounts', $id, ['password_hash' => password_make($password), 'updated_at' => time()]);

    $_SESSION['_new_password'] = [
        'username' => (string) $account['username'],
        'password' => $password,
        'context'  => 'nouveau mot de passe administrateur',
    ];

    log_activity((int) current_account()['id'], 'mot_de_passe_admin', (string) $account['username']);
    flash('succes', 'Un nouveau mot de passe a été généré.');
    redirect('/admin/admins');
}

/** Active ou désactive un administrateur. */
function admin_admin_toggle(array $params): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $account = db_one("SELECT * FROM accounts WHERE id = ? AND role = 'admin'", [$id]);
    $me      = current_account();

    if (!$account || !$me) {
        flash('erreur', 'Compte introuvable.');
        redirect('/admin/admins');
    }
    if ((int) $account['id'] === (int) $me['id']) {
        flash('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
        redirect('/admin/admins');
    }

    $newState = (int) $account['is_active'] === 1 ? 0 : 1;

    if ($newState === 0) {
        $active = (int) db_value("SELECT COUNT(*) FROM accounts WHERE role = 'admin' AND is_active = 1", [], 0);
        if ($active <= 1) {
            flash('erreur', 'Il doit rester au moins un administrateur actif.');
            redirect('/admin/admins');
        }
    }

    db_update('accounts', $id, ['is_active' => $newState, 'updated_at' => time()]);
    log_activity((int) $me['id'], $newState ? 'admin_active' : 'admin_desactive', (string) $account['username']);
    flash('succes', $newState ? 'Le compte est actif.' : 'Le compte est désactivé.');
    redirect('/admin/admins');
}
