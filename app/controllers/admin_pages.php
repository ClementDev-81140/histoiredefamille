<?php
/**
 * Administration des pages d'informations (histoire de la famille, annonces…).
 */

declare(strict_types=1);

/** Liste des pages. */
function admin_pages(): void
{
    require_admin();

    $pages = db_all(
        'SELECT p.*, a.username AS auteur
         FROM pages p LEFT JOIN accounts a ON a.id = p.updated_by
         ORDER BY p.position, p.title COLLATE NOCASE'
    );

    render('admin/pages', [
        'titre' => 'Pages d’informations',
        'pages' => $pages,
    ], 'layout_admin');
}

/** Formulaire de création / modification d'une page. */
function admin_page_form(array $params = []): void
{
    require_admin();

    $id   = (int) ($params['id'] ?? 0);
    $page = $id > 0 ? db_one('SELECT * FROM pages WHERE id = ?', [$id]) : null;
    if ($id > 0 && !$page) {
        http_response_code(404);
        render('erreur', ['titre' => 'Page introuvable', 'message' => 'Cette page n’existe pas.']);
        return;
    }

    render('admin/page_form', [
        'titre'      => $page ? 'Modifier « ' . $page['title'] . ' »' : 'Nouvelle page',
        'page'       => $page,
        'branches'   => branches_list(true),
        'pageBranches' => $page ? content_branch_ids('page', $id) : [],
        'erreurs'    => form_errors(),
        'old'        => form_old(),
    ], 'layout_admin');
}

/** Enregistrement d'une page. */
function admin_page_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id   = (int) ($params['id'] ?? 0);
    $page = $id > 0 ? db_one('SELECT * FROM pages WHERE id = ?', [$id]) : null;
    $back = $id > 0 ? '/admin/pages/' . $id : '/admin/pages/nouvelle';

    if ($id > 0 && !$page) {
        http_response_code(404);
        render('erreur', ['titre' => 'Page introuvable', 'message' => 'Cette page n’existe pas.']);
        return;
    }

    $old = [
        'title'         => post('title'),
        'slug'          => strtolower(post('slug')),
        'body'          => post('body'),
        'visibility'    => post('visibility'),
        'branches'      => array_map('intval', post_array('branches')),
        'is_published'  => post('is_published') === '1',
        'show_in_nav'   => post('show_in_nav') === '1',
        'position'      => (int) post('position'),
    ];

    $errors = [];

    if ($old['title'] === '' || mb_strlen($old['title']) > 200) {
        $errors[] = 'Le titre est obligatoire (200 caractères maximum).';
    }
    if ($old['slug'] === '') {
        $old['slug'] = slugify($old['title']);
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $old['slug'])) {
        $errors[] = 'L’adresse de la page doit contenir uniquement des lettres minuscules, chiffres et tirets.';
    }
    $conflict = db_one('SELECT id FROM pages WHERE slug = ? AND id <> ?', [$old['slug'], $id]);
    if ($conflict) {
        $errors[] = 'Une autre page utilise déjà cette adresse.';
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }
    if (mb_strlen($old['body']) > 200000) {
        $errors[] = 'Le texte est trop long.';
    }

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    $data = [
        'title'        => $old['title'],
        'slug'         => $old['slug'],
        'body'         => $old['body'],
        'visibility'   => $visibility,
        'is_published' => $old['is_published'] ? 1 : 0,
        'show_in_nav'  => $old['show_in_nav'] ? 1 : 0,
        'position'     => $old['position'],
        'updated_at'   => time(),
        'updated_by'   => (int) current_account()['id'],
    ];

    if ($id > 0) {
        db_update('pages', $id, $data);
        $pageId = $id;
    } else {
        $data['created_at'] = time();
        $pageId = db_insert('pages', $data);
    }

    set_content_branches('page', $pageId, $visibility === 'branches' ? $branches : []);

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'page_modifiee' : 'page_ajoutee',
        $old['title']
    );

    flash('succes', $id > 0 ? 'La page a été mise à jour.' : 'La page a été créée.');
    redirect('/admin/pages');
}

/** Suppression d'une page. */
function admin_page_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id   = (int) ($params['id'] ?? 0);
    $page = db_one('SELECT * FROM pages WHERE id = ?', [$id]);
    if (!$page) {
        flash('erreur', 'Page introuvable.');
        redirect('/admin/pages');
    }

    db_transaction(static function () use ($id): void {
        db_exec("DELETE FROM content_branches WHERE kind = 'page' AND content_id = ?", [$id]);
        db_exec('DELETE FROM pages WHERE id = ?', [$id]);
    });

    log_activity((int) current_account()['id'], 'page_supprimee', (string) $page['title']);
    flash('succes', 'La page « ' . $page['title'] . ' » a été supprimée.');
    redirect('/admin/pages');
}
