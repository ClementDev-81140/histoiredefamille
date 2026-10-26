<?php
/**
 * Administration des documents (photos, vidéos, PDF, fichiers) et des catégories.
 */

declare(strict_types=1);

/** Liste complète des documents, avec filtres et visibilité réelle. */
function admin_documents(): void
{
    require_admin();

    $params = [];
    $where  = ['1 = 1'];

    $q = query('q');
    if ($q !== '') {
        $where[] = '(d.title LIKE :q OR d.description LIKE :q OR d.original_name LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    $type = query('type');
    if (in_array($type, ['photo', 'video', 'document'], true)) {
        $where[] = 'd.kind = :type';
        $params[':type'] = $type;
    }
    $visibility = query('visibilite');
    if (array_key_exists($visibility, visibility_levels())) {
        $where[] = 'd.visibility = :vis';
        $params[':vis'] = $visibility;
    }
    if (query('attachments') !== '1') {
        $where[] = 'd.is_attachment = 0';
    }

    $whereSql = implode(' AND ', $where);
    $total    = (int) db_value("SELECT COUNT(*) FROM documents d WHERE {$whereSql}", $params, 0);
    $pg       = pagination($total, 0, max(1, query_int('page', 1)));

    $documents = db_all(
        "SELECT d.*, c.name AS category_name FROM documents d
         LEFT JOIN categories c ON c.id = d.category_id
         WHERE {$whereSql}
         ORDER BY d.created_at DESC
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    render('admin/documents', [
        'titre'      => 'Documents',
        'documents'  => $documents,
        'pagination' => $pg,
        'total'      => $total,
        'q'          => $q,
        'type'       => $type,
        'visibility' => $visibility,
        'categories' => categories_list(),
    ], 'layout_admin');
}

/** Formulaire d'ajout ou de modification d'un document. */
function admin_document_form(array $params = []): void
{
    require_admin();

    $doc = null;
    $id  = (int) ($params['id'] ?? 0);
    if ($id > 0) {
        $doc = db_one('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$doc) {
            http_response_code(404);
            render('erreur', ['titre' => 'Document introuvable', 'message' => 'Ce document n’existe pas.']);
            return;
        }
        $docBranches = content_branch_ids('document', $id);
        $tags        = implode(', ', document_tags($doc));
    } else {
        $docBranches = [];
        $tags        = '';
    }

    render('admin/document_form', [
        'titre'       => $doc ? 'Modifier « ' . $doc['title'] . ' »' : 'Ajouter un document',
        'doc'         => $doc,
        'categories'  => categories_list(),
        'branches'    => branches_list(true),
        'docBranches' => $docBranches,
        'tags'        => $tags,
        'erreurs'     => form_errors(),
        'old'         => form_old(),
        'maxBytes'    => upload_max_bytes(),
    ], 'layout_admin');
}

/** Enregistrement d'un document (création ou modification). */
function admin_document_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id   = (int) ($params['id'] ?? 0);
    $doc  = $id > 0 ? db_one('SELECT * FROM documents WHERE id = ?', [$id]) : null;
    $back = $id > 0 ? '/admin/documents/' . $id . '/modifier' : '/admin/documents/nouveau';

    if ($id > 0 && !$doc) {
        http_response_code(404);
        render('erreur', ['titre' => 'Document introuvable', 'message' => 'Ce document n’existe pas.']);
        return;
    }

    $old = [
        'title'       => post('title'),
        'description' => post('description'),
        'doc_date'    => post('doc_date'),
        'category_id' => (int) post('category_id'),
        'visibility'  => post('visibility'),
        'branches'    => array_map('intval', post_array('branches')),
        'tags'        => post('tags'),
    ];

    $errors = [];

    $title = $old['title'];
    if ($title === '' || mb_strlen($title) > 200) {
        $errors[] = 'Le titre est obligatoire (200 caractères maximum).';
    }
    if (mb_strlen($old['description']) > 5000) {
        $errors[] = 'La description est trop longue (5000 caractères maximum).';
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }

    $categoryId = $old['category_id'] > 0 ? $old['category_id'] : null;
    if ($categoryId !== null && !find_category($categoryId)) {
        $errors[] = 'La catégorie sélectionnée n’existe pas.';
    }

    // --- Fichier envoyé ----------------------------------------------------
    $file    = $_FILES['fichier'] ?? null;
    $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($id === 0 && !$hasFile) {
        $errors[] = 'Choisissez un fichier à envoyer.';
    }

    $stored = null;
    if ($hasFile && $errors === []) {
        $valid = validate_uploaded_file($file);
        if (!$valid['ok']) {
            $errors[] = $valid['error'];
        } else {
            $stored = store_uploaded_file($valid);
            if (!$stored['ok']) {
                $errors[] = $stored['error'];
            }
        }
    }

    if ($errors !== []) {
        // Un fichier déjà enregistré ne doit pas rester orphelin.
        if (is_array($stored) && !empty($stored['ok'])) {
            storage_delete($stored['rel_path']);
            storage_delete($stored['thumb_path']);
        }
        form_fail($errors, $old, $back);
    }

    $docDate = $old['doc_date'] !== '' ? mb_substr($old['doc_date'], 0, 100) : null;

    $data = [
        'title'        => $title,
        'description'  => $old['description'] !== '' ? $old['description'] : null,
        'doc_date'     => $docDate,
        'doc_date_sort' => extract_year($docDate),
        'category_id'  => $categoryId,
        'visibility'   => $visibility,
        'updated_at'   => time(),
    ];

    if (is_array($stored) && !empty($stored['ok'])) {
        $data += [
            'rel_path'      => $stored['rel_path'],
            'thumb_path'    => $stored['thumb_path'],
            'original_name' => mb_substr((string) $file['name'], 0, 200),
            'mime'          => (string) $valid['mime'],
            'ext'           => (string) $valid['ext'],
            'size'          => (int) $valid['size'],
            'kind'          => (string) $valid['kind'],
            'width'         => $stored['width'],
            'height'        => $stored['height'],
        ];
    }

    if ($id > 0) {
        db_update('documents', $id, $data);
        $documentId = $id;
    } else {
        $data['created_by'] = (int) current_account()['id'];
        $data['created_at'] = time();
        $data['is_attachment'] = 0;
        $documentId = db_insert('documents', $data);
    }

    set_content_branches('document', $documentId, $visibility === 'branches' ? $branches : []);
    save_document_tags($documentId, $old['tags']);

    // Remplacement de fichier : on supprime l'ancien après succès.
    if ($id > 0 && is_array($stored) && !empty($stored['ok']) && $doc) {
        delete_document_files($doc);
    }

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'document_modifie' : 'document_ajoute',
        $title
    );

    flash('succes', $id > 0 ? 'Le document a été mis à jour.' : 'Le document a été ajouté.');
    redirect('/documents/' . $documentId);
}

/** Suppression d'un document (et de ses fichiers). */
function admin_document_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id  = (int) ($params['id'] ?? 0);
    $doc = db_one('SELECT * FROM documents WHERE id = ?', [$id]);
    if (!$doc) {
        http_response_code(404);
        render('erreur', ['titre' => 'Document introuvable', 'message' => 'Ce document n’existe pas.']);
        return;
    }

    db_transaction(static function () use ($id): void {
        db_exec('DELETE FROM document_tags WHERE document_id = ?', [$id]);
        db_exec("DELETE FROM content_branches WHERE kind = 'document' AND content_id = ?", [$id]);
        db_exec('DELETE FROM documents WHERE id = ?', [$id]);
    });

    delete_document_files($doc);

    log_activity((int) current_account()['id'], 'document_supprime', (string) $doc['title']);
    flash('succes', 'Le document « ' . $doc['title'] . ' » a été supprimé.');
    redirect('/admin/documents');
}

// ---------------------------------------------------------------------------
// Catégories
// ---------------------------------------------------------------------------

/** Page de gestion des catégories. */
function admin_categories(): void
{
    require_admin();

    $categories = db_all(
        'SELECT c.*, (SELECT COUNT(*) FROM documents d WHERE d.category_id = c.id) AS nb
         FROM categories c ORDER BY c.position, c.name'
    );

    render('admin/categories', [
        'titre'      => 'Catégories',
        'categories' => $categories,
        'erreurs'    => form_errors(),
        'old'        => form_old(),
    ], 'layout_admin');
}

/** Création d'une catégorie. */
function admin_category_create(): void
{
    require_admin();
    csrf_check();

    $name = post('name');
    $position = (int) post('position');

    if ($name === '' || mb_strlen($name) > 80) {
        form_fail(['Le nom de la catégorie est obligatoire (80 caractères maximum).'], ['name' => $name], '/admin/categories');
    }

    $slug = slugify($name);
    $exists = db_one('SELECT id FROM categories WHERE slug = ?', [$slug]);
    if ($exists) {
        form_fail(['Une catégorie porte déjà ce nom.'], ['name' => $name], '/admin/categories');
    }

    $id = db_insert('categories', [
        'name'       => $name,
        'slug'       => $slug,
        'position'   => $position,
        'created_at' => time(),
    ]);

    log_activity((int) current_account()['id'], 'categorie_ajoutee', $name . ' (#' . $id . ')');
    flash('succes', 'La catégorie a été créée.');
    redirect('/admin/categories');
}

/** Modification d'une catégorie. */
function admin_category_update(array $params): void
{
    require_admin();
    csrf_check();

    $id   = (int) ($params['id'] ?? 0);
    $name = post('name');

    if (!db_one('SELECT id FROM categories WHERE id = ?', [$id])) {
        flash('erreur', 'Catégorie introuvable.');
        redirect('/admin/categories');
    }
    if ($name === '' || mb_strlen($name) > 80) {
        form_fail(['Le nom de la catégorie est obligatoire (80 caractères maximum).'], ['name' => $name], '/admin/categories');
    }

    db_update('categories', $id, [
        'name'     => $name,
        'slug'     => slugify($name),
        'position' => (int) post('position'),
    ]);

    log_activity((int) current_account()['id'], 'categorie_modifiee', $name . ' (#' . $id . ')');
    flash('succes', 'La catégorie a été modifiée.');
    redirect('/admin/categories');
}

/** Suppression d'une catégorie (les documents ne sont pas supprimés). */
function admin_category_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id = (int) ($params['id'] ?? 0);
    $category = db_one('SELECT * FROM categories WHERE id = ?', [$id]);
    if (!$category) {
        flash('erreur', 'Catégorie introuvable.');
        redirect('/admin/categories');
    }

    db_exec('DELETE FROM categories WHERE id = ?', [$id]);
    log_activity((int) current_account()['id'], 'categorie_supprimee', (string) $category['name']);
    flash('succes', 'La catégorie a été supprimée. Les documents concernés n’ont plus de catégorie.');
    redirect('/admin/categories');
}
