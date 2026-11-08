<?php
/**
 * Administration des dossiers d'archives (1.36.0).
 *
 * Un dossier range des documents d'archive (par exemple « Grand-père
 * Michel ») : nom, description facultative, personne de l'arbre facultative.
 * Supprimer un dossier ne supprime jamais ses documents : ils deviennent
 * « sans dossier ». Le classement d'un document se fait sur sa fiche
 * (« Documents & photos » → Modifier) ou dès l'envoi.
 */

declare(strict_types=1);

/** Liste des dossiers, avec le formulaire de création. */
function admin_dossiers(): void
{
    require_admin();

    $dossiers = [];
    foreach (dossiers_liste() as $dossier) {
        $dossiers[] = $dossier + [
            'nb' => (int) db_value('SELECT COUNT(*) FROM documents WHERE dossier_id = ?', [(int) $dossier['id']], 0),
        ];
    }

    render('admin/dossiers', [
        'titre'    => 'Dossiers d’archives',
        'dossiers' => $dossiers,
        'personnes' => db_all('SELECT id, first_name, last_name FROM persons ORDER BY last_name COLLATE NOCASE, first_name COLLATE NOCASE'),
        'erreurs'  => form_errors(),
        'old'      => form_old(),
    ], 'layout_admin');
}

/** Lit et valide les champs d'un dossier. Renvoie [données, erreurs]. */
function admin_dossier_saisie(): array
{
    $nom = mb_substr(trim(post('name')), 0, 100, 'UTF-8');
    $description = trim(post('description'));
    $personId = (int) post('person_id');

    $erreurs = [];
    if ($nom === '') {
        $erreurs[] = 'Le nom du dossier est obligatoire (100 caractères maximum).';
    }
    if (mb_strlen($description) > 2000) {
        $erreurs[] = 'La description est trop longue (2000 caractères maximum).';
    }
    if ($personId > 0 && db_one('SELECT id FROM persons WHERE id = ?', [$personId]) === null) {
        $erreurs[] = 'Cette personne n’existe pas dans l’arbre.';
        $personId = 0;
    }

    return [[
        'name'        => $nom,
        'description' => $description !== '' ? mb_substr($description, 0, 2000, 'UTF-8') : null,
        'person_id'   => $personId > 0 ? $personId : null,
    ], $erreurs];
}

/** Création d'un dossier. */
function admin_dossier_create(): void
{
    require_admin();
    csrf_check();

    [$donnees, $erreurs] = admin_dossier_saisie();
    if ($erreurs !== []) {
        form_fail($erreurs, $_POST, '/admin/dossiers');
    }

    $donnees['created_by'] = (int) current_account()['id'];
    $donnees['created_at'] = time();
    $donnees['updated_at'] = time();
    db_insert('dossiers', $donnees);

    flash('succes', 'Le dossier « ' . $donnees['name'] . ' » a été créé. Rangez-y des documents depuis leur fiche, ou dès l’envoi en cochant « Classer en archive ».');
    redirect('/admin/dossiers');
}

/** Renommage / modification d'un dossier. */
function admin_dossier_update(array $params): void
{
    require_admin();
    csrf_check();

    $id = (int) ($params['id'] ?? 0);
    if (dossier_par_id($id) === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Dossier introuvable', 'message' => 'Ce dossier n’existe pas.'], 'layout_admin');
        return;
    }

    [$donnees, $erreurs] = admin_dossier_saisie();
    if ($erreurs !== []) {
        form_fail($erreurs, $_POST, '/admin/dossiers');
    }

    $donnees['updated_at'] = time();
    db_update('dossiers', $id, $donnees);

    flash('succes', 'Le dossier a été mis à jour.');
    redirect('/admin/dossiers');
}

/** Suppression d'un dossier : ses documents deviennent « sans dossier ». */
function admin_dossier_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id = (int) ($params['id'] ?? 0);
    $dossier = dossier_par_id($id);
    if ($dossier === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Dossier introuvable', 'message' => 'Ce dossier n’existe pas.'], 'layout_admin');
        return;
    }

    // Jamais de suppression de documents : ils redeviennent « sans dossier ».
    db_exec('UPDATE documents SET dossier_id = NULL, updated_at = ? WHERE dossier_id = ?', [time(), $id]);
    db_exec('DELETE FROM dossiers WHERE id = ?', [$id]);

    flash('succes', 'Le dossier « ' . (string) $dossier['name'] . ' » a été supprimé. Ses documents sont conservés, sans dossier.');
    redirect('/admin/dossiers');
}
