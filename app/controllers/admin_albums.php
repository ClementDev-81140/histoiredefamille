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
    // Filtre « Catégorie » (1.32.0), en plus de la visibilité.
    $categorie = category_filtre_demande('album');
    if ($categorie > 0) {
        $where[] = category_where('a', $categorie, $params, "'album'");
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
        'visibility'       => $visibility,
        'filtreCategories' => categories_avec_compteur_kinds(['album']),
        'filtreCategorie'  => $categorie,
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
        'categorieIds' => $album ? category_ids_pour('album', $id) : [],
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
    // Catégories de l'album (1.32.0).
    category_enregistrer('album', $albumId, categories_saisies());

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
// Ajouter des photos directement dans un album
// ---------------------------------------------------------------------------

/**
 * Page « Ajouter des photos » d'un album.
 *
 * Le bloc d'envoi par morceaux est celui de tout le site : glisser-déposer,
 * plusieurs fichiers, barre de progression par fichier, reprise après coupure.
 * La seule différence est la destination : chaque photo arrive déjà dans cet
 * album, à la fin, avec la visibilité de l'album.
 */
function admin_album_photos(array $params = []): void
{
    require_admin();

    $album = find_album((int) ($params['id'] ?? 0));
    if ($album === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Album introuvable', 'message' => 'Cet album n’existe pas.']);
        return;
    }

    // Ce que CE compte a ajouté à CET album : c'est la liste que l'on corrige
    // juste après l'envoi (titre, date) et d'où l'on retire une photo.
    $ajoutees = envoi_photos_album((int) $album['id'], (int) current_account()['id']);

    // Documents déjà sur le site, que l'on peut verser dans l'album sans les
    // renvoyer (1.30.0). Les filtres viennent du formulaire GET ; la liste est
    // celle de l'administration, mais chaque document garde sa visibilité.
    $filtres   = album_documents_filtres();
    $candidats = album_documents_candidats((int) $album['id'], $filtres);

    // Juste après un ajout, la page reçoit les identifiants ajoutés
    // (`?ajoutes=…`). Ils sont revérifiés : seuls ceux qui sont vraiment dans
    // cet album comptent, et l'on ne retient que ceux qui restent moins
    // visibles que lui — c'est le seul cas qui demande une décision.
    $ajoutesRecents = [];
    foreach (explode(',', query('ajoutes')) as $valeur) {
        $id = (int) $valeur;
        if ($id > 0 && in_array($id, $candidats['dansAlbum'], true)) {
            $ajoutesRecents[] = $id;
        }
    }

    $moinsVisibles = [];
    if ($ajoutesRecents !== []) {
        $documents = db_all(
            "SELECT id, title, kind, visibility, thumb_path, doc_date
             FROM documents WHERE id IN (" . implode(',', array_fill(0, count($ajoutesRecents), '?')) . ")
             ORDER BY id",
            $ajoutesRecents
        );
        $moinsVisibles = array_values(array_filter(
            $documents,
            static fn (array $document): bool => document_moins_visible_que_album($document, $album)
        ));
    }

    render('admin/album_photos', [
        'titre'    => 'Ajouter des photos à « ' . $album['title'] . ' »',
        'album'    => $album,
        'ajoutees' => $ajoutees,
        'erreurs'  => form_errors(),
        'candidats'      => $candidats['documents'],
        'totalCandidats' => $candidats['total'],
        'pagination'     => $candidats['pagination'],
        'dansAlbum'      => $candidats['dansAlbum'],
        'filtres'        => $filtres,
        'categories'     => categories_list(),
        'ajoutesRecents' => $ajoutesRecents,
        'moinsVisibles'  => $moinsVisibles,
        'tousMoinsVisibles' => album_documents_moins_visibles((int) $album['id']),
    ], 'layout_admin');
}

/**
 * Ajoute à l'album des documents cochés dans la liste (1.30.0).
 *
 * Aucun fichier n'est renvoyé : les identifiants désignent des documents qui
 * existent déjà, et chacun est vérifié côté serveur. Ceux qui sont déjà dans
 * l'album sont ignorés sans erreur. « Tout ajouter sur cette page » reprend la
 * liste telle que le serveur l'a calculée, avec les mêmes filtres.
 */
function admin_album_photos_ajouter(array $params = []): void
{
    require_admin();
    csrf_check();

    $album = find_album((int) ($params['id'] ?? 0));
    if ($album === null) {
        flash('erreur', 'Album introuvable.');
        redirect('/admin/albums');
    }
    $albumId = (int) $album['id'];

    $demandes = array_map('intval', post_array('documents'));

    if (post('tout') === '1') {
        $filtres              = album_documents_filtres();
        $filtres['pas_album'] = true;
        $page                 = album_documents_candidats($albumId, $filtres);
        foreach ($page['documents'] as $document) {
            $demandes[] = (int) $document['id'];
        }
    }

    $demandes = array_values(array_filter(array_unique($demandes)));
    if ($demandes === []) {
        flash('erreur', 'Aucun document n’a été coché.');
        redirect('/admin/albums/' . $albumId . '/photos');
    }

    $resultat = album_ajouter_documents($albumId, $demandes);

    // Catégories du lot (1.32.0) : ajoutées aux documents réellement ajoutés,
    // sans toucher à leur visibilité.
    $categoriesLot = categories_saisies();
    if ($categoriesLot !== [] && $resultat['ajoutes'] !== []) {
        foreach ($resultat['ajoutes'] as $idAjoute) {
            $kindAjoute = (string) db_value('SELECT kind FROM documents WHERE id = ?', [(int) $idAjoute], 'document');
            category_enregistrer(
                in_array($kindAjoute, ['photo', 'video'], true) ? $kindAjoute : 'document',
                (int) $idAjoute,
                $categoriesLot
            );
        }
    }

    if ($resultat['ajoutes'] === []) {
        flash('erreur', 'Aucun document n’a été ajouté : ' . count($resultat['ignores'])
            . ' élément(s) étaient déjà dans l’album ou n’existent plus.');
        redirect('/admin/albums/' . $albumId . '/photos');
    }

    $n = count($resultat['ajoutes']);
    $message = $n . ' élément' . ($n > 1 ? 's' : '') . ' ajouté' . ($n > 1 ? 's' : '') . ' à l’album.';
    if ($resultat['ignores'] !== []) {
        $message .= ' ' . count($resultat['ignores']) . ' élément(s) déjà dans l’album ont été ignorés.';
    }
    $message .= ' Leur visibilité n’a pas changé.';

    log_activity(
        (int) current_account()['id'],
        'album_modifie',
        $album['title'] . ' (' . $n . ' document(s) déjà présents ajoutés)'
    );

    flash('succes', $message);
    redirect(
        '/admin/albums/' . $albumId . '/photos?ajoutes=' . implode(',', $resultat['ajoutes'])
        . '#ajouter-documents'
    );
}

/**
 * Donne à des documents de l'album la visibilité de l'album (1.30.0).
 *
 * Action séparée, confirmée dans le navigateur : elle n'est jamais faite
 * automatiquement au moment de l'ajout. Les identifiants sont vérifiés (le
 * document doit être dans cet album).
 */
function admin_album_photos_visibilite(array $params = []): void
{
    require_admin();
    csrf_check();

    $album = find_album((int) ($params['id'] ?? 0));
    if ($album === null) {
        flash('erreur', 'Album introuvable.');
        redirect('/admin/albums');
    }
    $albumId = (int) $album['id'];

    $documents = array_values(array_filter(array_map('intval', post_array('documents'))));
    $modifies  = album_documents_donner_visibilite($albumId, $documents);

    if ($modifies === 0) {
        flash('erreur', 'Aucun document n’a été modifié.');
        redirect('/admin/albums/' . $albumId . '/photos');
    }

    log_activity(
        (int) current_account()['id'],
        'document_modifie',
        $modifies . ' document(s) de l’album « ' . $album['title'] . ' » : visibilité de l’album'
    );

    flash('succes', $modifies . ' document' . ($modifies > 1 ? 's' : '')
        . ' de cet album ' . ($modifies > 1 ? 'ont' : 'a') . ' maintenant la visibilité de l’album ('
        . visibility_label((string) $album['visibility']) . ').');
    redirect('/admin/albums/' . $albumId . '/photos');
}

/**
 * Corrige le titre et la date d'une photo de l'album, sans quitter la page.
 *
 * Formulaire ordinaire (aucun JavaScript nécessaire) ; la photo doit être dans
 * cet album, sinon la demande est refusée.
 */
function admin_album_photo_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $album = find_album((int) ($params['id'] ?? 0));
    $docId = (int) post('document_id');

    if ($album === null) {
        flash('erreur', 'Album introuvable.');
        redirect('/admin/albums');
    }

    if ($docId <= 0 || !in_array($docId, album_photo_ids((int) $album['id']), true)) {
        flash('erreur', 'Cette photo ne fait pas partie de cet album.');
        redirect('/admin/albums/' . (int) $album['id'] . '/photos');
    }

    $photo = db_one('SELECT id, title, doc_date FROM documents WHERE id = ?', [$docId]);
    if ($photo === null) {
        flash('erreur', 'Cette photo n’existe plus.');
        redirect('/admin/albums/' . (int) $album['id'] . '/photos');
    }

    $titre = trim(post('title'));
    if ($titre === '') {
        $titre = (string) $photo['title'];
    }
    if (mb_strlen($titre) > 200) {
        $titre = mb_substr($titre, 0, 200, 'UTF-8');
    }

    $date = trim(post('doc_date'));

    db_update('documents', $docId, [
        'title'         => $titre,
        'doc_date'      => $date !== '' ? mb_substr($date, 0, 100, 'UTF-8') : null,
        'doc_date_sort' => extract_year($date),
        'updated_at'    => time(),
    ]);

    log_activity((int) current_account()['id'], 'document_modifie', $titre);

    flash('succes', 'La photo a été mise à jour.');
    redirect('/admin/albums/' . (int) $album['id'] . '/photos');
}

/**
 * Retire une photo de l'album — sans supprimer le document.
 *
 * La photo reste dans « Documents & photos », avec sa visibilité, ses étiquettes
 * et les personnes identifiées dessus.
 */
function admin_album_photo_remove(array $params = []): void
{
    require_admin();
    csrf_check();

    $album = find_album((int) ($params['id'] ?? 0));
    $docId = (int) post('document_id');

    if ($album === null) {
        flash('erreur', 'Album introuvable.');
        redirect('/admin/albums');
    }

    if ($docId <= 0 || !in_array($docId, album_photo_ids((int) $album['id']), true)) {
        flash('erreur', 'Cette photo ne fait pas partie de cet album.');
        redirect('/admin/albums/' . (int) $album['id'] . '/photos');
    }

    db_exec(
        'DELETE FROM album_documents WHERE album_id = ? AND document_id = ?',
        [(int) $album['id'], $docId]
    );

    // La couverture ne peut plus désigner une photo qui n'est plus dans l'album.
    if ((int) ($album['cover_document_id'] ?? 0) === $docId) {
        db_update('albums', (int) $album['id'], [
            'cover_document_id' => null,
            'updated_at'        => time(),
        ]);
    }

    log_activity(
        (int) current_account()['id'],
        'album_modifie',
        $album['title'] . ' (photo retirée de l’album)'
    );

    flash('succes', 'La photo a été retirée de l’album. Elle reste dans « Documents & photos ».');
    redirect('/admin/albums/' . (int) $album['id'] . '/photos');
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
