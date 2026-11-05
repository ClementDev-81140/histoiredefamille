<?php
/**
 * Administration des recettes de famille.
 */

declare(strict_types=1);

/** Liste de toutes les recettes (même privées). */
function admin_recettes(): void
{
    require_admin();

    // Filtre « Visibilité » en GET (même principe que la liste des articles).
    $params     = [];
    $where      = ['1 = 1'];
    $visibility = query('visibilite');
    if (array_key_exists($visibility, visibility_levels())) {
        $where[] = 'r.visibility = :vis';
        $params[':vis'] = $visibility;
    }
    $whereSql = implode(' AND ', $where);

    $recettes = db_all(
        "SELECT r.* FROM recipes r WHERE {$whereSql} ORDER BY r.title COLLATE NOCASE",
        $params
    );

    render('admin/recettes', [
        'titre'      => 'Recettes',
        'recettes'   => $recettes,
        'visibility' => $visibility,
    ], 'layout_admin');
}

/** Formulaire de création / modification d'une recette. */
function admin_recette_form(array $params = []): void
{
    require_admin();

    $id      = (int) ($params['id'] ?? 0);
    $recette = $id > 0 ? find_recipe($id) : null;
    if ($id > 0 && !$recette) {
        http_response_code(404);
        render('erreur', ['titre' => 'Recette introuvable', 'message' => 'Cette recette n’existe pas.']);
        return;
    }

    $photos = db_all(
        "SELECT id, title FROM documents
         WHERE kind = 'photo' AND is_attachment = 0
         ORDER BY doc_date_sort IS NULL, doc_date_sort DESC, created_at DESC
         LIMIT 200"
    );

    render('admin/recette_form', [
        'titre'    => $recette ? 'Modifier « ' . $recette['title'] . ' »' : 'Nouvelle recette',
        'recette'  => $recette,
        'photos'   => $photos,
        'branches' => branches_list(true),
        'recetteBranches' => $recette ? content_branch_ids('recipe', $id) : [],
        'erreurs'  => form_errors(),
        'old'      => form_old(),
    ], 'layout_admin');
}

/** Enregistrement d'une recette. */
function admin_recette_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $recette = $id > 0 ? find_recipe($id) : null;
    $back    = $id > 0 ? '/admin/recettes/' . $id : '/admin/recettes/nouvelle';

    if ($id > 0 && !$recette) {
        http_response_code(404);
        render('erreur', ['titre' => 'Recette introuvable', 'message' => 'Cette recette n’existe pas.']);
        return;
    }

    $old = [
        'title'        => post('title'),
        'origin'       => post('origin'),
        'servings'     => post('servings'),
        'ingredients'  => post('ingredients'),
        'preparation'  => post('preparation'),
        'notes'        => post('notes'),
        'photo_id'     => (int) post('photo_id'),
        'visibility'   => sanitize_visibility(post('visibility')),
        'branches'     => array_map('intval', post_array('branches')),
    ];

    $errors = [];

    if ($old['title'] === '' || mb_strlen($old['title']) > 200) {
        $errors[] = 'Le titre est obligatoire (200 caractères maximum).';
    }
    if ($old['ingredients'] === '') {
        $errors[] = 'Indiquez au moins un ingrédient.';
    }
    if (mb_strlen($old['ingredients']) > 20000 || mb_strlen($old['preparation']) > 40000) {
        $errors[] = 'La recette est trop longue.';
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }

    $photoId = null;
    if ($old['photo_id'] > 0) {
        $photo = db_one("SELECT id FROM documents WHERE id = ? AND kind = 'photo'", [$old['photo_id']]);
        $photoId = $photo !== null ? (int) $photo['id'] : null;
    }

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    // Adresse unique, dérivée du titre.
    $slug  = slugify($old['title']);
    if ($slug === '') {
        $slug = 'recette';
    }
    $base  = $slug;
    $index = 2;
    while (db_one('SELECT id FROM recipes WHERE slug = ? AND id <> ?', [$slug, $id])) {
        $slug = $base . '-' . $index++;
    }

    $data = [
        'title'             => $old['title'],
        'slug'              => $slug,
        'origin'            => $old['origin'] !== '' ? $old['origin'] : null,
        'servings'          => $old['servings'] !== '' ? $old['servings'] : null,
        'ingredients'       => $old['ingredients'],
        'preparation'       => $old['preparation'],
        'notes'             => $old['notes'] !== '' ? $old['notes'] : null,
        'photo_document_id' => $photoId,
        'visibility'        => $visibility,
        'updated_at'        => time(),
    ];

    if ($id > 0) {
        db_update('recipes', $id, $data);
        $recipeId = $id;
    } else {
        $data['created_by'] = (int) current_account()['id'];
        $data['created_at'] = time();
        $recipeId = db_insert('recipes', $data);
    }

    set_content_branches('recipe', $recipeId, $visibility === 'branches' ? $branches : []);

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'recette_modifiee' : 'recette_ajoutee',
        $old['title']
    );

    flash('succes', $id > 0 ? 'La recette a été mise à jour.' : 'La recette a été ajoutée.');
    redirect('/recettes/' . $slug);
}

/** Suppression d'une recette (la photo reste dans la médiathèque). */
function admin_recette_delete(array $params): void
{
    require_admin();
    csrf_check();

    $recette = find_recipe((int) ($params['id'] ?? 0));
    if (!$recette) {
        flash('erreur', 'Recette introuvable.');
        redirect('/admin/recettes');
    }

    db_transaction(static function () use ($recette): void {
        db_exec("DELETE FROM content_branches WHERE kind = 'recipe' AND content_id = ?", [(int) $recette['id']]);
        db_exec('DELETE FROM recipes WHERE id = ?', [(int) $recette['id']]);
    });

    log_activity((int) current_account()['id'], 'recette_supprimee', (string) $recette['title']);
    flash('succes', 'La recette « ' . $recette['title'] . ' » a été supprimée.');
    redirect('/admin/recettes');
}
