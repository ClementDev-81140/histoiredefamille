<?php
/**
 * Administration des albums photos et des personnes identifiées.
 *
 * Un album ne déplace ni ne copie aucun fichier : il range des documents
 * existants. Supprimer un album ne supprime donc jamais les photos.
 */

declare(strict_types=1);

/** Liste de tous les albums (même privés), avec le nombre de photos. */
function admin_albums(): void
{
    require_admin();

    // Filtre « Visibilité » en GET (même principe que la liste des articles).
    $params     = [];
    $where      = ['1 = 1'];
    $visibility = query('visibilite');
    if (array_key_exists($visibility, visibility_levels())) {
        $where[] = 'a.visibility = :vis';
        $params[':vis'] = $visibility;
    }
    $whereSql = implode(' AND ', $where);

    $albums = db_all(
        "SELECT a.*,
                (SELECT COUNT(*) FROM album_documents ad WHERE ad.album_id = a.id) AS nb_photos,
                (SELECT COUNT(*) FROM content_branches cb
                  WHERE cb.kind = 'album' AND cb.content_id = a.id) AS nb_branches
         FROM albums a
         WHERE {$whereSql}
         ORDER BY a.position, a.title COLLATE NOCASE",
        $params
    );

    render('admin/albums', [
        'titre'      => 'Albums photos',
        'albums'     => $albums,
        'visibility' => $visibility,
    ], 'layout_admin');
}

/** Formulaire de création / modification d'un album. */
function admin_album_form(array $params = []): void
{
    require_admin();

    $id    = (int) ($params['id'] ?? 0);
    $album = $id > 0 ? find_album($id) : null;
    if ($id > 0 && !$album) {
        http_response_code(404);
        render('erreur', ['titre' => 'Album introuvable', 'message' => 'Cet album n’existe pas.']);
        return;
    }

    // Choix des photos : on cherche parmi les photos déjà envoyées.
    $q     = query('q');
    $paramsQuery = [];
    $where = ["d.kind = 'photo'", 'd.is_attachment = 0'];
    if ($q !== '') {
        $where[] = '(d.title LIKE :q OR d.description LIKE :q)';
        $paramsQuery[':q'] = '%' . $q . '%';
    }
    $whereSql = implode(' AND ', $where);
    $photos = db_all(
        "SELECT d.id, d.title, d.doc_date, d.thumb_path, d.visibility, d.created_at
         FROM documents d WHERE {$whereSql}
         ORDER BY d.doc_date_sort IS NULL, d.doc_date_sort DESC, d.created_at DESC
         LIMIT 120",
        $paramsQuery
    );

    $choisies = $album ? album_photo_ids((int) $album['id']) : [];

    render('admin/album_form', [
        'titre'    => $album ? 'Modifier « ' . $album['title'] . ' »' : 'Nouvel album',
        'album'    => $album,
        'photos'   => $photos,
        'choisies' => $choisies,
        'q'        => $q,
        'branches' => branches_list(true),
        'albumBranches' => $album ? content_branch_ids('album', $id) : [],
        'erreurs'  => form_errors(),
        'old'      => form_old(),
    ], 'layout_admin');
}

/** Enregistrement d'un album. */
function admin_album_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id    = (int) ($params['id'] ?? 0);
    $album = $id > 0 ? find_album($id) : null;
    $back  = $id > 0 ? '/admin/albums/' . $id . '/modifier' : '/admin/albums/nouveau';

    if ($id > 0 && !$album) {
        http_response_code(404);
        render('erreur', ['titre' => 'Album introuvable', 'message' => 'Cet album n’existe pas.']);
        return;
    }

    $old = [
        'title'       => post('title'),
        'description' => post('description'),
        'visibility'  => sanitize_visibility(post('visibility')),
        'branches'    => array_map('intval', post_array('branches')),
        'position'    => (int) post('position'),
        'photos'      => array_map('intval', post_array('photos')),
        'couverture'  => (int) post('couverture'),
    ];

    $errors = [];

    if ($old['title'] === '' || mb_strlen($old['title']) > 200) {
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

    // Les photos choisies doivent exister et être des photos.
    $photosValides = [];
    foreach ($old['photos'] as $documentId) {
        if ($documentId <= 0) {
            continue;
        }
        $doc = db_one("SELECT id FROM documents WHERE id = ? AND kind = 'photo'", [$documentId]);
        if ($doc) {
            $photosValides[] = $documentId;
        }
    }

    $couverture = $old['couverture'] > 0 && in_array($old['couverture'], $photosValides, true)
        ? $old['couverture'] : null;

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    // Adresse unique, dérivée du titre : « souvenirs-1965 », puis « -2 ».
    $slug = slugify($old['title']);
    if ($slug === '') {
        $slug = 'album';
    }
    $base  = $slug;
    $index = 2;
    while (db_one('SELECT id FROM albums WHERE slug = ? AND id <> ?', [$slug, $id])) {
        $slug = $base . '-' . $index++;
    }

    $data = [
        'title'             => $old['title'],
        'slug'              => $slug,
        'description'       => $old['description'] !== '' ? $old['description'] : null,
        'cover_document_id' => $couverture,
        'visibility'        => $visibility,
        'position'          => $old['position'],
        'updated_at'        => time(),
    ];

    if ($id > 0) {
        db_update('albums', $id, $data);
        $albumId = $id;
    } else {
        $data['created_by'] = (int) current_account()['id'];
        $data['created_at'] = time();
        $albumId = db_insert('albums', $data);
    }

    set_album_photos($albumId, $photosValides);
    set_content_branches('album', $albumId, $visibility === 'branches' ? $branches : []);

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'album_modifie' : 'album_ajoute',
        $old['title'] . ' (#' . $albumId . ')'
    );

    flash('succes', $id > 0 ? 'L’album a été mis à jour.' : 'L’album a été créé.');
    redirect('/albums/' . $slug);
}

/** Suppression d'un album (les photos restent dans la médiathèque). */
function admin_album_delete(array $params): void
{
    require_admin();
    csrf_check();

    $album = find_album((int) ($params['id'] ?? 0));
    if (!$album) {
        flash('erreur', 'Album introuvable.');
        redirect('/admin/albums');
    }

    db_transaction(static function () use ($album): void {
        db_exec('DELETE FROM album_documents WHERE album_id = ?', [(int) $album['id']]);
        db_exec("DELETE FROM content_branches WHERE kind = 'album' AND content_id = ?", [(int) $album['id']]);
        db_exec('DELETE FROM albums WHERE id = ?', [(int) $album['id']]);
    });

    log_activity((int) current_account()['id'], 'album_supprime', (string) $album['title']);
    flash('succes', 'L’album « ' . $album['title'] . ' » a été supprimé. Les photos sont conservées.');
    redirect('/admin/albums');
}

// ---------------------------------------------------------------------------
// Personnes identifiées sur un document
// ---------------------------------------------------------------------------

/** Page « Qui figure sur cette photo ? ». */
function admin_document_persons(array $params): void
{
    require_admin();

    $doc = db_one('SELECT * FROM documents WHERE id = ?', [(int) ($params['id'] ?? 0)]);
    if (!$doc) {
        http_response_code(404);
        render('erreur', ['titre' => 'Document introuvable', 'message' => 'Ce document n’existe pas.']);
        return;
    }

    $q      = query('q');
    $trouves = [];
    if ($q !== '') {
        $trouves = db_all(
            "SELECT * FROM persons
             WHERE (first_name LIKE :q OR last_name LIKE :q
                    OR (first_name || ' ' || last_name) LIKE :q)
             ORDER BY last_name COLLATE NOCASE, first_name COLLATE NOCASE
             LIMIT 20",
            [':q' => '%' . $q . '%']
        );
    }

    $identifiees = array_map(
        static fn (int $id): ?array => db_one('SELECT * FROM persons WHERE id = ?', [$id]),
        document_person_ids((int) $doc['id'])
    );
    $identifiees = array_values(array_filter($identifiees));

    render('admin/document_persons', [
        'titre'       => 'Personnes sur « ' . $doc['title'] . ' »',
        'doc'         => $doc,
        'identifiees' => $identifiees,
        'trouves'     => $trouves,
        'q'           => $q,
    ], 'layout_admin');
}

/** Ajoute une personne identifiée sur un document. */
function admin_document_person_add(array $params): void
{
    require_admin();
    csrf_check();

    $docId    = (int) ($params['id'] ?? 0);
    $personId = (int) post('person_id');
    $doc      = db_one('SELECT id FROM documents WHERE id = ?', [$docId]);
    $person   = db_one('SELECT * FROM persons WHERE id = ?', [$personId]);

    if (!$doc || !$person) {
        flash('erreur', 'Document ou personne introuvable.');
        redirect('/admin/documents');
    }

    db_exec(
        'INSERT OR IGNORE INTO document_persons (document_id, person_id) VALUES (?, ?)',
        [$docId, $personId]
    );
    log_activity(
        (int) current_account()['id'],
        'personne_identifiee',
        person_full_name($person) . ' sur le document #' . $docId
    );
    flash('succes', person_full_name($person) . ' a été ajouté sur ce document.');

    redirect('/admin/documents/' . $docId . '/personnes');
}

/** Retire une personne identifiée sur un document. */
function admin_document_person_remove(array $params): void
{
    require_admin();
    csrf_check();

    $docId    = (int) ($params['id'] ?? 0);
    $personId = (int) post('person_id');
    $person   = db_one('SELECT * FROM persons WHERE id = ?', [$personId]);

    db_exec('DELETE FROM document_persons WHERE document_id = ? AND person_id = ?', [$docId, $personId]);
    log_activity(
        (int) current_account()['id'],
        'personne_retiree',
        ($person ? person_full_name($person) : 'personne #' . $personId) . ' sur le document #' . $docId
    );
    flash('succes', 'La personne a été retirée de ce document.');

    redirect('/admin/documents/' . $docId . '/personnes');
}
