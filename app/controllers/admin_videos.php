<?php
/**
 * Administration des vidéos.
 *
 * Les vidéos restent des documents (kind = 'video') : on réutilise le stockage,
 * les miniatures, la visibilité et la lecture en flux. Cette page ajoute
 * seulement les réglages propres à l'onglet Vidéos : mise en avant, ordre
 * d'affichage, durée et autorisation de téléchargement.
 */

declare(strict_types=1);

/** Liste de toutes les vidéos (même privées) + fichiers en attente. */
function admin_videos(): void
{
    require_admin();

    $params = [];
    $where  = ["d.kind = 'video'", 'd.is_attachment = 0'];

    $q = query('q');
    if ($q !== '') {
        $where[] = '(d.title LIKE :q OR d.description LIKE :q OR d.original_name LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    $visibility = query('visibilite');
    if (array_key_exists($visibility, visibility_levels())) {
        $where[] = 'd.visibility = :vis';
        $params[':vis'] = $visibility;
    }

    $whereSql = implode(' AND ', $where);
    $total    = (int) db_value("SELECT COUNT(*) FROM documents d WHERE {$whereSql}", $params, 0);
    $pg       = pagination($total, 0, max(1, query_int('page', 1)));

    $videos = db_all(
        "SELECT d.* FROM documents d
         WHERE {$whereSql}
         ORDER BY d.is_featured DESC, (d.display_order = 0), d.display_order ASC, d.created_at DESC
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    // Fichiers déjà déposés dans le dossier « arrivée » (dépôt SFTP/Samba).
    $enAttente = array_values(array_filter(
        incoming_files(),
        static fn (array $f): bool => in_array(strtolower((string) $f['ext']), extensions_video(), true)
    ));

    render('admin/videos', [
        'titre'      => 'Vidéos',
        'videos'     => $videos,
        'pagination' => $pg,
        'total'      => $total,
        'q'          => $q,
        'visibility' => $visibility,
        'enAttente'  => $enAttente,
        'maxStreams' => max(1, (int) config('video.max_concurrent_streams', 2)),
    ], 'layout_admin');
}

/** Formulaire d'ajout ou de modification d'une vidéo. */
function admin_video_form(array $params = []): void
{
    require_admin();

    $id = (int) ($params['id'] ?? 0);
    $video = null;
    if ($id > 0) {
        $video = db_one("SELECT * FROM documents WHERE id = ? AND kind = 'video'", [$id]);
        if (!$video) {
            http_response_code(404);
            render('erreur', [
                'titre'   => 'Vidéo introuvable',
                'message' => 'Cette vidéo n’existe pas.',
            ], 'layout_admin');
            return;
        }
        $videoBranches = content_branch_ids('document', $id);
        $tags = implode(', ', document_tags($video));
    } else {
        $videoBranches = [];
        $tags = '';
    }

    render('admin/video_form', [
        'titre'         => $video ? 'Modifier « ' . $video['title'] . ' »' : 'Ajouter une vidéo',
        'video'         => $video,
        'categories'    => categories_list(),
        'branches'      => branches_list(true),
        'videoBranches' => $videoBranches,
        'tags'          => $tags,
        'erreurs'       => form_errors(),
        'old'           => form_old(),
        'maxBytes'      => upload_max_bytes(),
    ], 'layout_admin');
}

/** Enregistrement d'une vidéo (création ou modification). */
function admin_video_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id    = (int) ($params['id'] ?? 0);
    $video = $id > 0 ? db_one("SELECT * FROM documents WHERE id = ? AND kind = 'video'", [$id]) : null;
    $back  = $id > 0 ? '/admin/videos/' . $id : '/admin/videos/nouvelle';

    if ($id > 0 && !$video) {
        http_response_code(404);
        render('erreur', ['titre' => 'Vidéo introuvable', 'message' => 'Cette vidéo n’existe pas.'], 'layout_admin');
        return;
    }

    $dureeSaisie = post('duration');

    $old = [
        'title'         => post('title'),
        'description'   => post('description'),
        'doc_date'      => post('doc_date'),
        'category_id'   => (int) post('category_id'),
        'visibility'    => post('visibility'),
        'branches'      => array_map('intval', post_array('branches')),
        'tags'          => post('tags'),
        'is_featured'   => post('is_featured') === '1' ? '1' : '0',
        'display_order' => post('display_order'),
        'allow_download' => post('allow_download') === '1' ? '1' : '0',
        'duration'      => $dureeSaisie,
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

    $order = (int) $old['display_order'];
    if ($order < 0 || $order > 9999) {
        $errors[] = 'L’ordre d’affichage doit être compris entre 0 et 9999.';
    }

    $duration = parse_duration($old['duration']);
    if (trim((string) $old['duration']) !== '' && $duration === null) {
        $errors[] = 'La durée n’est pas comprise. Exemples : 12:30, 1:02:03 ou 750 (secondes).';
    }

    // --- Fichier envoyé ----------------------------------------------------
    $file    = $_FILES['fichier'] ?? null;
    $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    // Gros film déjà assemblé par l'envoi par morceaux.
    $envoiPrepare = null;
    if (!$hasFile && post('envoi') !== '') {
        $envoiPrepare = envoi_fichier_pret((int) current_account()['id'], post('envoi'));
        if ($envoiPrepare === null) {
            $errors[] = 'Le fichier envoyé par morceaux n’est plus disponible. Redéposez-le.';
        } elseif ((string) $envoiPrepare['kind'] !== 'video') {
            $errors[] = 'Ce fichier n’est pas une vidéo. Utilisez la page Documents pour les autres formats.';
            $envoiPrepare = null;
        }
    }

    if ($id === 0 && !$hasFile && $envoiPrepare === null) {
        $errors[] = 'Choisissez un fichier vidéo à envoyer (ou passez par le dossier arrivée pour les gros films).';
    }

    $stored = null;
    $valid  = null;
    if ($hasFile && $errors === []) {
        $valid = validate_uploaded_file($file);
        if (!$valid['ok']) {
            $errors[] = $valid['error'];
        } elseif (($valid['kind'] ?? '') !== 'video') {
            $errors[] = 'Ce fichier n’est pas une vidéo. Utilisez la page Documents pour les autres formats.';
        } else {
            $stored = store_uploaded_file($valid);
            if (!$stored['ok']) {
                $errors[] = $stored['error'];
            }
        }
    }

    if ($errors !== []) {
        if (is_array($stored) && !empty($stored['ok'])) {
            storage_delete($stored['rel_path']);
            storage_delete($stored['thumb_path']);
        }
        form_fail($errors, $old, $back);
    }

    $docDate = $old['doc_date'] !== '' ? mb_substr($old['doc_date'], 0, 100) : null;

    $data = [
        'title'           => $title,
        'description'     => $old['description'] !== '' ? $old['description'] : null,
        'doc_date'        => $docDate,
        'doc_date_sort'   => extract_year($docDate),
        'category_id'     => $categoryId,
        'visibility'      => $visibility,
        'is_featured'     => $old['is_featured'] === '1' ? 1 : 0,
        'display_order'   => $order,
        'allow_download'  => $old['allow_download'] === '1' ? 1 : 0,
        'duration_seconds' => $duration,
        'updated_at'      => time(),
    ];

    if (is_array($stored) && !empty($stored['ok'])) {
        $data += [
            'rel_path'      => $stored['rel_path'],
            'thumb_path'    => $stored['thumb_path'],
            'original_name' => mb_substr((string) $file['name'], 0, 200),
            'mime'          => (string) $valid['mime'],
            'ext'           => (string) $valid['ext'],
            'size'          => (int) $valid['size'],
            'kind'          => 'video',
            'width'         => $stored['width'],
            'height'        => $stored['height'],
        ];
    }

    // Gros film assemblé par l'envoi par morceaux : même fiche, même contrôle.
    if ($envoiPrepare !== null) {
        $data += envoi_champs_document($envoiPrepare);
        $data['kind'] = 'video';
    }

    if ($id > 0) {
        db_update('documents', $id, $data);
        $videoId = $id;
    } else {
        $data['created_by']    = (int) current_account()['id'];
        $data['created_at']    = time();
        $data['is_attachment'] = 0;
        $videoId = db_insert('documents', $data);
    }

    if ($envoiPrepare !== null) {
        envoi_marquer_utilise($envoiPrepare, $videoId);
    }

    set_content_branches('document', $videoId, $visibility === 'branches' ? $branches : []);
    save_document_tags($videoId, $old['tags']);

    // Format non lisible par les navigateurs : conversion en MP4 en arrière-plan.
    conversion_planifier_document($videoId);

    if ($id > 0 && is_array($stored) && !empty($stored['ok']) && $video) {
        delete_document_files($video);
    }

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'video_modifiee' : 'video_ajoutee',
        $title
    );

    flash('succes', $id > 0 ? 'La vidéo a été mise à jour.' : 'La vidéo a été ajoutée.');
    redirect('/admin/videos');
}

/**
 * Importe une vidéo déjà déposée dans le dossier « arrivée » (SFTP/Samba).
 *
 * Aucun fichier n'est lu en mémoire : store_incoming_file() déplace le fichier
 * sur le disque (rename), puis on enregistre la fiche.
 */
function admin_video_import(): void
{
    require_admin();
    csrf_check();

    $fileName = basename(post('fichier'));
    $source   = incoming_path() . '/' . $fileName;
    $back     = '/admin/videos';

    $old = [
        'fichier'    => $fileName,
        'title'      => post('title'),
        'visibility' => post('visibility'),
        'branches'   => array_map('intval', post_array('branches')),
        'duration'   => post('duration'),
    ];

    if ($fileName === '' || str_contains($fileName, '..') || !is_file($source)) {
        form_fail(['Fichier introuvable dans le dossier arrivée.'], $old, $back);
    }

    $valid = validate_local_file($source);
    if (!$valid['ok']) {
        form_fail(['Fichier refusé : ' . $valid['error']], $old, $back);
    }
    if (($valid['kind'] ?? '') !== 'video') {
        form_fail(['Ce fichier n’est pas une vidéo. Le dossier arrivée accepte aussi les autres formats : utilisez « Dossier arrivée ».'], $old, $back);
    }

    $errors = [];

    $title = $old['title'] !== '' ? $old['title'] : pathinfo($fileName, PATHINFO_FILENAME);
    if (mb_strlen($title) > 200) {
        $errors[] = 'Le titre est limité à 200 caractères.';
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }

    $duration = parse_duration($old['duration']);
    if (trim((string) $old['duration']) !== '' && $duration === null) {
        $errors[] = 'La durée n’est pas comprise. Exemples : 12:30, 1:02:03 ou 750 (secondes).';
    }

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    $stored = store_incoming_file($valid);
    if (!$stored['ok']) {
        form_fail(['Déplacement impossible : ' . $stored['error']], $old, $back);
    }

    $videoId = db_insert('documents', [
        'title'            => $title,
        'kind'             => 'video',
        'visibility'       => $visibility,
        'rel_path'         => $stored['rel_path'],
        'thumb_path'       => $stored['thumb_path'],
        'original_name'    => mb_substr($fileName, 0, 200),
        'mime'             => (string) $valid['mime'],
        'ext'              => (string) $valid['ext'],
        'size'             => (int) $valid['size'],
        'width'            => $stored['width'],
        'height'           => $stored['height'],
        'duration_seconds' => $duration,
        'is_featured'      => 0,
        'display_order'    => 0,
        'allow_download'   => 1,
        'is_attachment'    => 0,
        'created_by'       => (int) current_account()['id'],
        'created_at'       => time(),
        'updated_at'       => time(),
    ]);

    set_content_branches('document', $videoId, $visibility === 'branches' ? $branches : []);

    // Un fichier déposé par le réseau local peut aussi avoir besoin d'être
    // converti : il entre dans la même file que les envois par le navigateur.
    conversion_planifier_document($videoId);

    log_activity((int) current_account()['id'], 'video_importee', $fileName . ' → ' . $title);
    flash('succes', sprintf('« %s » a été importée (%s). Complétez la fiche si besoin.', $title, format_size((int) $valid['size'])));
    redirect('/admin/videos/' . $videoId);
}

/** Met en avant ou retire de la une (bascule en un clic). */
function admin_video_feature(array $params): void
{
    require_admin();
    csrf_check();

    $id    = (int) ($params['id'] ?? 0);
    $video = db_one("SELECT * FROM documents WHERE id = ? AND kind = 'video'", [$id]);
    if (!$video) {
        flash('erreur', 'Vidéo introuvable.');
        redirect('/admin/videos');
    }

    $nouvelle = (int) $video['is_featured'] === 1 ? 0 : 1;
    db_update('documents', $id, ['is_featured' => $nouvelle, 'updated_at' => time()]);

    log_activity(
        (int) current_account()['id'],
        $nouvelle ? 'video_mise_en_avant' : 'video_retiree_une',
        (string) $video['title']
    );
    flash('succes', $nouvelle
        ? '« ' . $video['title'] . ' » est désormais en tête de l’onglet Vidéos.'
        : '« ' . $video['title'] . ' » n’est plus mise en avant.');
    redirect('/admin/videos');
}

/** Suppression d'une vidéo (fichier compris). */
function admin_video_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id    = (int) ($params['id'] ?? 0);
    $video = db_one("SELECT * FROM documents WHERE id = ? AND kind = 'video'", [$id]);
    if (!$video) {
        flash('erreur', 'Vidéo introuvable.');
        redirect('/admin/videos');
    }

    db_transaction(static function () use ($id): void {
        db_exec('DELETE FROM document_tags WHERE document_id = ?', [$id]);
        db_exec("DELETE FROM content_branches WHERE kind = 'document' AND content_id = ?", [$id]);
        db_exec('DELETE FROM documents WHERE id = ?', [$id]);
    });

    delete_document_files($video);

    log_activity((int) current_account()['id'], 'video_supprimee', (string) $video['title']);
    flash('succes', 'La vidéo « ' . $video['title'] . ' » a été supprimée.');
    redirect('/admin/videos');
}
