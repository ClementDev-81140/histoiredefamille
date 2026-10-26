<?php
/**
 * Import depuis le dossier « arrivée ».
 *
 * Les fichiers déposés sur le Raspberry Pi (SFTP/Samba) sont listés ici ;
 * l'administrateur leur donne un titre, une catégorie et une visibilité,
 * puis le site les déplace dans le stockage définitif.
 */

declare(strict_types=1);

/** Liste des fichiers en attente dans le dossier arrivée. */
function admin_import(): void
{
    require_admin();

    render('admin/importer', [
        'titre'      => 'Importer depuis le dossier arrivée',
        'files'      => incoming_files(),
        'categories' => categories_list(),
        'branches'   => branches_list(true),
        'erreurs'    => form_errors(),
        'old'        => form_old(),
        'incoming'   => incoming_path(),
        'allowed'    => allowed_extensions(),
    ], 'layout_admin');
}

/** Enregistre un fichier du dossier arrivée comme document. */
function admin_import_store(): void
{
    require_admin();
    csrf_check();

    $fileName = basename(post('fichier'));
    $source   = incoming_path() . '/' . $fileName;

    $old = [
        'fichier'     => $fileName,
        'title'       => post('title'),
        'description' => post('description'),
        'doc_date'    => post('doc_date'),
        'category_id' => (int) post('category_id'),
        'visibility'  => post('visibility'),
        'branches'    => array_map('intval', post_array('branches')),
        'tags'        => post('tags'),
    ];

    if ($fileName === '' || str_contains($fileName, '..') || !is_file($source)) {
        form_fail(['Fichier introuvable dans le dossier arrivée.'], $old, '/admin/importer');
    }

    $valid = validate_local_file($source);
    if (!$valid['ok']) {
        form_fail(['Fichier refusé : ' . $valid['error']], $old, '/admin/importer');
    }

    $errors = [];
    $title  = $old['title'] !== '' ? $old['title'] : pathinfo($fileName, PATHINFO_FILENAME);
    if (mb_strlen($title) > 200) {
        $errors[] = 'Le titre est limité à 200 caractères.';
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }

    $categoryId = $old['category_id'] > 0 ? $old['category_id'] : null;
    if ($categoryId !== null && !find_category($categoryId)) {
        $categoryId = null;
    }

    if ($errors !== []) {
        form_fail($errors, $old, '/admin/importer');
    }

    $stored = store_incoming_file($valid);
    if (!$stored['ok']) {
        form_fail(['Déplacement impossible : ' . $stored['error']], $old, '/admin/importer');
    }

    $docDate = $old['doc_date'] !== '' ? mb_substr($old['doc_date'], 0, 100) : null;

    $documentId = db_insert('documents', [
        'title'         => $title,
        'description'   => $old['description'] !== '' ? $old['description'] : null,
        'doc_date'      => $docDate,
        'doc_date_sort' => extract_year($docDate),
        'category_id'   => $categoryId,
        'kind'          => (string) $valid['kind'],
        'visibility'    => $visibility,
        'rel_path'      => $stored['rel_path'],
        'thumb_path'    => $stored['thumb_path'],
        'original_name' => mb_substr($fileName, 0, 200),
        'mime'          => (string) $valid['mime'],
        'ext'           => (string) $valid['ext'],
        'size'          => (int) $valid['size'],
        'width'         => $stored['width'],
        'height'        => $stored['height'],
        'is_attachment' => 0,
        'created_by'    => (int) current_account()['id'],
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);

    set_content_branches('document', $documentId, $visibility === 'branches' ? $branches : []);
    save_document_tags($documentId, $old['tags']);

    log_activity((int) current_account()['id'], 'import_fichier', $fileName . ' → ' . $title);
    flash('succes', sprintf('« %s » a été importé (%s).', $title, format_size((int) $valid['size'])));
    redirect('/admin/importer');
}

/** Supprime un fichier du dossier arrivée (sans l'importer). */
function admin_import_delete(): void
{
    require_admin();
    csrf_check();

    $fileName = basename(post('fichier'));
    $path     = incoming_path() . '/' . $fileName;

    if ($fileName === '' || str_contains($fileName, '..') || !is_file($path)) {
        flash('erreur', 'Fichier introuvable.');
        redirect('/admin/importer');
    }

    @unlink($path);
    log_activity((int) current_account()['id'], 'arrivee_supprimee', $fileName);
    flash('succes', 'Le fichier a été supprimé du dossier arrivée.');
    redirect('/admin/importer');
}
