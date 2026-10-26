<?php
/**
 * Administration de l'arbre généalogique : personnes, couples (familles),
 * import GEDCOM.
 */

declare(strict_types=1);

/** Liste des personnes et accès aux couples / import. */
function admin_persons(): void
{
    require_admin();

    $params = [];
    $where  = ['1 = 1'];
    $q      = query('q');
    if ($q !== '') {
        $where[] = "(p.first_name LIKE :q OR p.last_name LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    $whereSql = implode(' AND ', $where);

    $total = (int) db_value("SELECT COUNT(*) FROM persons p WHERE {$whereSql}", $params, 0);
    $pg    = pagination($total, 0, max(1, query_int('page', 1)));

    $persons = db_all(
        "SELECT p.*,
                (SELECT COUNT(*) FROM family_children fc WHERE fc.person_id = p.id) AS nb_familles_parentes,
                (SELECT COUNT(*) FROM families f WHERE f.husband_id = p.id OR f.wife_id = p.id) AS nb_unions
         FROM persons p WHERE {$whereSql}
         ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    render('admin/personnes', [
        'titre'      => 'Arbre généalogique',
        'persons'    => $persons,
        'pagination' => $pg,
        'total'      => $total,
        'q'          => $q,
        'nbCouples'  => (int) db_value('SELECT COUNT(*) FROM families', [], 0),
    ], 'layout_admin');
}

/** Formulaire de création / modification d'une personne. */
function admin_person_form(array $params = []): void
{
    require_admin();

    $id     = (int) ($params['id'] ?? 0);
    $person = $id > 0 ? db_one('SELECT * FROM persons WHERE id = ?', [$id]) : null;
    if ($id > 0 && !$person) {
        http_response_code(404);
        render('erreur', ['titre' => 'Personne introuvable', 'message' => 'Cette fiche n’existe pas.']);
        return;
    }

    $photo = null;
    if ($person && !empty($person['photo_document_id'])) {
        $photo = db_one('SELECT * FROM documents WHERE id = ?', [(int) $person['photo_document_id']]);
    }

    render('admin/personne_form', [
        'titre'     => $person ? 'Modifier ' . person_full_name($person) : 'Nouvelle personne',
        'person'    => $person,
        'photo'     => $photo,
        'branches'  => branches_list(true),
        'personBranches' => $person ? content_branch_ids('person', $id) : [],
        'erreurs'   => form_errors(),
        'old'       => form_old(),
        'couples'   => $person ? person_families($id) : [],
    ], 'layout_admin');
}

/** Enregistrement d'une personne. */
function admin_person_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $person = $id > 0 ? db_one('SELECT * FROM persons WHERE id = ?', [$id]) : null;
    $back   = $id > 0 ? '/admin/arbre/personne/' . $id : '/admin/arbre/personne/nouvelle';

    if ($id > 0 && !$person) {
        http_response_code(404);
        render('erreur', ['titre' => 'Personne introuvable', 'message' => 'Cette fiche n’existe pas.']);
        return;
    }

    $old = [
        'first_name'  => post('first_name'),
        'last_name'   => post('last_name'),
        'sex'         => post('sex'),
        'birth_date'  => post('birth_date'),
        'birth_place' => post('birth_place'),
        'death_date'  => post('death_date'),
        'death_place' => post('death_place'),
        'notes'       => post('notes'),
        'visibility'  => post('visibility'),
        'branches'    => array_map('intval', post_array('branches')),
        'remove_photo' => post('remove_photo') === '1',
    ];

    $errors = [];
    if ($old['first_name'] === '' && $old['last_name'] === '') {
        $errors[] = 'Indiquez au moins un prénom ou un nom.';
    }
    if (mb_strlen($old['first_name']) > 100 || mb_strlen($old['last_name']) > 100) {
        $errors[] = 'Le prénom et le nom sont limités à 100 caractères.';
    }
    if (!in_array($old['sex'], ['', 'M', 'F'], true)) {
        $old['sex'] = '';
    }
    if (mb_strlen($old['notes']) > 20000) {
        $errors[] = 'Les notes sont trop longues.';
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }

    // --- Photo (facultative) ----------------------------------------------
    $file    = $_FILES['photo'] ?? null;
    $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    $stored  = null;
    $valid   = null;
    if ($hasFile && $errors === []) {
        $valid = validate_uploaded_file($file);
        if (!$valid['ok']) {
            $errors[] = 'Photo : ' . $valid['error'];
        } elseif ($valid['kind'] !== 'photo') {
            $errors[] = 'Le fichier envoyé n’est pas une image.';
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

    $data = [
        'first_name'  => $old['first_name'],
        'last_name'   => $old['last_name'],
        'sex'         => $old['sex'] !== '' ? $old['sex'] : null,
        'birth_date'  => $old['birth_date'] !== '' ? mb_substr($old['birth_date'], 0, 100) : null,
        'birth_year'  => extract_year($old['birth_date']),
        'birth_place' => $old['birth_place'] !== '' ? mb_substr($old['birth_place'], 0, 200) : null,
        'death_date'  => $old['death_date'] !== '' ? mb_substr($old['death_date'], 0, 100) : null,
        'death_year'  => extract_year($old['death_date']),
        'death_place' => $old['death_place'] !== '' ? mb_substr($old['death_place'], 0, 200) : null,
        'notes'       => $old['notes'] !== '' ? $old['notes'] : null,
        'visibility'  => $visibility,
        'updated_at'  => time(),
    ];

    if ($id > 0) {
        db_update('persons', $id, $data);
        $personId = $id;
    } else {
        $data['created_at'] = time();
        $personId = db_insert('persons', $data);
    }

    set_content_branches('person', $personId, $visibility === 'branches' ? $branches : []);

    // --- Photo : ajout, remplacement ou suppression ------------------------
    $oldPhotoId = $person['photo_document_id'] ?? null;

    if (is_array($stored) && !empty($stored['ok'])) {
        $photoId = db_insert('documents', [
            'title'         => 'Photo de ' . trim($old['first_name'] . ' ' . $old['last_name']),
            'description'   => null,
            'doc_date'      => $old['birth_date'] !== '' ? $old['birth_date'] : null,
            'category_id'   => null,
            'kind'          => 'photo',
            'visibility'    => $visibility,
            'rel_path'      => $stored['rel_path'],
            'thumb_path'    => $stored['thumb_path'],
            'original_name' => mb_substr((string) $file['name'], 0, 200),
            'mime'          => (string) $valid['mime'],
            'ext'           => (string) $valid['ext'],
            'size'          => (int) $valid['size'],
            'width'         => $stored['width'],
            'height'        => $stored['height'],
            'is_attachment' => 1,
            'created_by'    => (int) current_account()['id'],
            'created_at'    => time(),
            'updated_at'    => time(),
        ]);
        db_update('persons', $personId, ['photo_document_id' => $photoId]);
        set_content_branches('document', $photoId, $visibility === 'branches' ? $branches : []);

        if ($oldPhotoId) {
            delete_attachment_document((int) $oldPhotoId);
        }
    } elseif ($old['remove_photo'] && $oldPhotoId) {
        db_update('persons', $personId, ['photo_document_id' => null]);
        delete_attachment_document((int) $oldPhotoId);
    }

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'personne_modifiee' : 'personne_ajoutee',
        trim($old['first_name'] . ' ' . $old['last_name'])
    );

    flash('succes', $id > 0 ? 'La fiche a été mise à jour.' : 'La personne a été ajoutée.');
    redirect('/admin/arbre/personne/' . $personId);
}

/** Supprime un document utilisé comme photo de personne. */
function delete_attachment_document(int $documentId): void
{
    $doc = db_one('SELECT * FROM documents WHERE id = ?', [$documentId]);
    if (!$doc) {
        return;
    }
    db_exec('DELETE FROM document_tags WHERE document_id = ?', [$documentId]);
    db_exec("DELETE FROM content_branches WHERE kind = 'document' AND content_id = ?", [$documentId]);
    db_exec('DELETE FROM documents WHERE id = ?', [$documentId]);
    delete_document_files($doc);
}

/** Suppression d'une personne (et de ses liens de parenté). */
function admin_person_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $person = db_one('SELECT * FROM persons WHERE id = ?', [$id]);
    if (!$person) {
        flash('erreur', 'Personne introuvable.');
        redirect('/admin/arbre');
    }

    $photoId = $person['photo_document_id'] ? (int) $person['photo_document_id'] : null;

    db_transaction(static function () use ($id): void {
        db_exec('DELETE FROM family_children WHERE person_id = ?', [$id]);
        db_exec('UPDATE families SET husband_id = NULL WHERE husband_id = ?', [$id]);
        db_exec('UPDATE families SET wife_id = NULL WHERE wife_id = ?', [$id]);
        db_exec("DELETE FROM content_branches WHERE kind = 'person' AND content_id = ?", [$id]);
        db_exec('DELETE FROM persons WHERE id = ?', [$id]);
        // Familles devenues vides : on les retire.
        db_exec('DELETE FROM families WHERE husband_id IS NULL AND wife_id IS NULL
                 AND id NOT IN (SELECT family_id FROM family_children)');
    });

    if ($photoId) {
        delete_attachment_document($photoId);
    }

    log_activity((int) (current_account()['id'] ?? 0), 'personne_supprimee', person_full_name($person));
    flash('succes', 'La fiche a été supprimée. Les liens de parenté associés ont été retirés.');
    redirect('/admin/arbre');
}

// ---------------------------------------------------------------------------
// Couples (familles)
// ---------------------------------------------------------------------------

/** Liste des couples. */
function admin_couples(): void
{
    require_admin();

    $families = db_all(
        'SELECT f.*,
                h.first_name AS h_first, h.last_name AS h_last,
                w.first_name AS w_first, w.last_name AS w_last,
                (SELECT COUNT(*) FROM family_children fc WHERE fc.family_id = f.id) AS nb_enfants
         FROM families f
         LEFT JOIN persons h ON h.id = f.husband_id
         LEFT JOIN persons w ON w.id = f.wife_id
         ORDER BY COALESCE(h.last_name, w.last_name) COLLATE NOCASE, f.id'
    );

    render('admin/couples', [
        'titre'    => 'Couples & enfants',
        'families' => $families,
    ], 'layout_admin');
}

/** Formulaire de création / modification d'un couple. */
function admin_couple_form(array $params = []): void
{
    require_admin();

    $id     = (int) ($params['id'] ?? 0);
    $family = $id > 0 ? db_one('SELECT * FROM families WHERE id = ?', [$id]) : null;
    if ($id > 0 && !$family) {
        http_response_code(404);
        render('erreur', ['titre' => 'Couple introuvable', 'message' => 'Ce couple n’existe pas.']);
        return;
    }

    $children = $id > 0
        ? db_all(
            'SELECT p.* FROM persons p JOIN family_children fc ON fc.person_id = p.id
             WHERE fc.family_id = ? ORDER BY p.birth_year IS NULL, p.birth_year, p.first_name',
            [$id]
        )
        : [];

    $persons = db_all(
        'SELECT id, first_name, last_name, birth_year, sex FROM persons
         ORDER BY last_name COLLATE NOCASE, first_name COLLATE NOCASE'
    );

    render('admin/couple_form', [
        'titre'         => $family ? 'Modifier le couple' : 'Nouveau couple',
        'family'        => $family,
        'children'      => $children,
        'childIds'      => array_map(static fn ($c) => (int) $c['id'], $children),
        'persons'       => $persons,
        'erreurs'       => form_errors(),
        'old'           => form_old(),
    ], 'layout_admin');
}

/** Enregistrement d'un couple et de ses enfants. */
function admin_couple_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $family = $id > 0 ? db_one('SELECT * FROM families WHERE id = ?', [$id]) : null;
    $back   = $id > 0 ? '/admin/arbre/couple/' . $id : '/admin/arbre/couple/nouveau';

    $old = [
        'husband_id'     => (int) post('husband_id'),
        'wife_id'        => (int) post('wife_id'),
        'marriage_date'  => post('marriage_date'),
        'marriage_place' => post('marriage_place'),
        'notes'          => post('notes'),
        'children'       => array_map('intval', post_array('children')),
    ];

    $errors = [];
    if ($old['husband_id'] > 0 && $old['wife_id'] > 0 && $old['husband_id'] === $old['wife_id']) {
        $errors[] = 'Les deux conjoints ne peuvent pas être la même personne.';
    }
    foreach (['husband_id', 'wife_id'] as $key) {
        if ($old[$key] > 0 && !db_one('SELECT id FROM persons WHERE id = ?', [$old[$key]])) {
            $errors[] = 'Une des personnes sélectionnées n’existe pas.';
        }
    }
    if ($old['husband_id'] === 0 && $old['wife_id'] === 0 && $old['children'] === []) {
        $errors[] = 'Indiquez au moins un conjoint ou un enfant.';
    }
    if (mb_strlen($old['notes']) > 20000) {
        $errors[] = 'Les notes sont trop longues.';
    }

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    $data = [
        'husband_id'     => $old['husband_id'] > 0 ? $old['husband_id'] : null,
        'wife_id'        => $old['wife_id'] > 0 ? $old['wife_id'] : null,
        'marriage_date'  => $old['marriage_date'] !== '' ? mb_substr($old['marriage_date'], 0, 100) : null,
        'marriage_place' => $old['marriage_place'] !== '' ? mb_substr($old['marriage_place'], 0, 200) : null,
        'notes'          => $old['notes'] !== '' ? $old['notes'] : null,
        'updated_at'     => time(),
    ];

    $familyId = 0;
    db_transaction(static function () use ($id, $data, $old, &$familyId): void {
        if ($id > 0) {
            db_update('families', $id, $data);
            $familyId = $id;
            db_exec('DELETE FROM family_children WHERE family_id = ?', [$familyId]);
        } else {
            $data['created_at'] = time();
            $familyId = db_insert('families', $data);
        }

        foreach (array_unique($old['children']) as $childId) {
            if ($childId > 0 && db_one('SELECT id FROM persons WHERE id = ?', [$childId])) {
                db_exec('INSERT OR IGNORE INTO family_children (family_id, person_id) VALUES (?, ?)', [$familyId, $childId]);
            }
        }
    });

    log_activity((int) current_account()['id'], $id > 0 ? 'couple_modifie' : 'couple_ajoute', 'Couple #' . $familyId);
    flash('succes', $id > 0 ? 'Le couple a été mis à jour.' : 'Le couple a été créé.');
    redirect('/admin/arbre/couples');
}

/** Suppression d'un couple. */
function admin_couple_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $family = db_one('SELECT * FROM families WHERE id = ?', [$id]);
    if (!$family) {
        flash('erreur', 'Couple introuvable.');
        redirect('/admin/arbre/couples');
    }

    db_exec('DELETE FROM families WHERE id = ?', [$id]); // les liens enfants suivent (CASCADE)
    log_activity((int) current_account()['id'], 'couple_supprime', 'Couple #' . $id);
    flash('succes', 'Le couple a été supprimé. Les personnes ne sont pas supprimées.');
    redirect('/admin/arbre/couples');
}

// ---------------------------------------------------------------------------
// Import GEDCOM
// ---------------------------------------------------------------------------

/** Formulaire d'import GEDCOM. */
function admin_gedcom_form(): void
{
    require_admin();

    render('admin/gedcom', [
        'titre'    => 'Importer un fichier GEDCOM',
        'preview'  => $_SESSION['_gedcom_preview'] ?? null,
        'erreurs'  => form_errors(),
        'maxMb'    => (int) config('uploads.max_gedcom_mb', 25),
    ], 'layout_admin');
}

/** Analyse du fichier GEDCOM envoyé (aperçu avant import). */
function admin_gedcom_upload(): void
{
    require_admin();
    csrf_check();

    $file = $_FILES['gedcom'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        form_fail(['Choisissez un fichier GEDCOM (.ged).'], [], '/admin/arbre/import');
    }

    $maxBytes = (int) config('uploads.max_gedcom_mb', 25) * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
        form_fail(['Fichier trop volumineux (maximum ' . format_size($maxBytes) . ').'], [], '/admin/arbre/import');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['ged', 'gedcom', 'txt'], true)) {
        form_fail(['Le fichier doit avoir l’extension .ged ou .gedcom.'], [], '/admin/arbre/import');
    }

    // Copie dans un dossier temporaire du stockage (jamais dans public/).
    $tmpDir = storage_path((string) config('storage.tmp', 'tmp'));
    $tmpPath = $tmpDir . '/gedcom-' . bin2hex(random_bytes(8)) . '.ged';
    if (!@move_uploaded_file((string) $file['tmp_name'], $tmpPath)) {
        form_fail(['Impossible d’enregistrer le fichier temporaire (droits du dossier ?).'], [], '/admin/arbre/import');
    }
    @chmod($tmpPath, 0640);

    $parsed = gedcom_parse_file($tmpPath);

    if (!$parsed['ok']) {
        @unlink($tmpPath);
        form_fail(['Analyse impossible : ' . $parsed['error']], [], '/admin/arbre/import');
    }

    $sample = [];
    foreach (array_slice($parsed['persons'], 0, 20, true) as $person) {
        $sample[] = trim(($person['first_name'] ?: 'Sans nom') . ' ' . $person['last_name'])
            . ($person['birth_date'] !== '' ? ' (' . $person['birth_date'] . ')' : '');
    }

    $preview = [
        'token'    => bin2hex(random_bytes(16)),
        'path'     => $tmpPath,
        'filename' => (string) $file['name'],
        'persons'  => count($parsed['persons']),
        'families' => count($parsed['families']),
        'encoding' => $parsed['encoding'],
        'warnings' => $parsed['warnings'],
        'sample'   => $sample,
        'created'  => time(),
    ];

    // Un seul aperçu à la fois : on supprime le précédent.
    if (isset($_SESSION['_gedcom_preview']['path']) && is_file((string) $_SESSION['_gedcom_preview']['path'])) {
        @unlink((string) $_SESSION['_gedcom_preview']['path']);
    }
    $_SESSION['_gedcom_preview'] = $preview;

    redirect('/admin/arbre/import');
}

/** Import définitif après validation de l'aperçu. */
function admin_gedcom_confirm(): void
{
    require_admin();
    csrf_check();

    $preview = $_SESSION['_gedcom_preview'] ?? null;
    $token   = post('token');

    if (!is_array($preview) || empty($preview['path']) || !hash_equals((string) ($preview['token'] ?? ''), $token)) {
        flash('erreur', 'Aperçu expiré : envoyez de nouveau le fichier.');
        redirect('/admin/arbre/import');
    }

    $parsed = gedcom_parse_file((string) $preview['path']);
    if (!$parsed['ok']) {
        @unlink((string) $preview['path']);
        unset($_SESSION['_gedcom_preview']);
        flash('erreur', 'Le fichier n’a pas pu être relu : ' . $parsed['error']);
        redirect('/admin/arbre/import');
    }

    $stats = gedcom_import($parsed, (int) current_account()['id']);

    @unlink((string) $preview['path']);
    unset($_SESSION['_gedcom_preview']);

    flash('succes', sprintf(
        'Import terminé : %d personne(s) ajoutée(s), %d déjà présente(s), %d couple(s), %d lien(s) de parenté. '
        . 'Les nouvelles personnes sont visibles par « Toutes les familles » (jamais publiques par défaut).',
        $stats['persons'],
        $stats['persons_skipped'],
        $stats['families'],
        $stats['links']
    ));
    redirect('/admin/arbre');
}
