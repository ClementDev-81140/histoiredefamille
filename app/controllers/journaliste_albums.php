<?php
/**
 * Albums des journalistes (1.37.0).
 *
 * Création et correction d'albums hors administration : les siens, et ceux
 * qui relèvent des branches de son périmètre. « Sans validation » : publié
 * directement, action journalisée. « Avec validation » (1.37.1) : la
 * création naît privée et « en attente » (invisible de tous les autres)
 * jusqu'à validation par un administrateur, et la modification directe
 * d'un album est refusée (403). Aucune suppression.
 */

declare(strict_types=1);

function journaliste_album_garde(): array
{
    require_login();
    $compte = current_account();
    if ($compte === null || (($compte['role'] ?? '') !== 'admin' && !compte_est_journaliste($compte))) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Accès refusé',
            'message' => 'Seuls les journalistes peuvent créer des albums par ici.',
        ]);
        hdf_terminer();
    }
    return $compte;
}

/** Photos que le compte peut voir (choix limité : aucun nom invisible). */
function journaliste_photos_visibles(): array
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    return db_all(
        "SELECT d.id, d.title FROM documents d
         WHERE {$clause} AND d.kind = 'photo' AND d.moderation_state = 'publie'
         ORDER BY d.created_at DESC LIMIT 200",
        $params
    );
}

function journaliste_album_form(array $params = []): void
{
    $compte = journaliste_album_garde();
    $id     = (int) ($params['id'] ?? 0);
    $album  = $id > 0 ? find_album($id) : null;

    if ($id > 0 && ($album === null || !journaliste_peut_modifier('album', $album, $compte))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Album introuvable', 'message' => 'Cet album n’existe pas, ou il ne relève pas des branches où vous pouvez écrire.']);
        return;
    }

    // 1.37.1 : un journaliste « avec validation » ne modifie jamais un
    // album directement (ni le sien, ni celui d'un autre). Refus net.
    if ($id > 0 && ($compte['role'] ?? '') !== 'admin' && journaliste_avec_validation($compte)) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Modification non autorisée',
            'message' => 'Votre réglage est « avec validation » : un album ne peut pas être modifié directement. '
                . 'Demandez à un administrateur de le corriger, ou de passer votre compte « sans validation ».',
        ]);
        return;
    }

    render('public/album_modifier', [
        'titre'    => $album !== null ? 'Corriger l’album' : 'Nouvel album',
        'album'    => $album,
        'photos'   => journaliste_photos_visibles(),
        'photosChoisies' => $album !== null ? album_photo_ids($id) : [],
        'branches' => branches_list(true),
        'branchesChoisies' => $album !== null ? content_branch_ids('album', $id) : [],
        'peutBranches' => static fn (int $b): bool => ($compte['role'] ?? '') === 'admin' || journaliste_dans_perimetre($b, $compte),
        // Création « avec validation » : l'album partira en attente (1.37.1).
        'enAttente' => $album === null && ($compte['role'] ?? '') !== 'admin' && journaliste_avec_validation($compte),
        'erreurs'  => form_errors(),
        'old'      => form_old(),
    ]);
}

function journaliste_album_save(array $params = []): void
{
    $compte = journaliste_album_garde();
    csrf_check();
    $estAdmin = ($compte['role'] ?? '') === 'admin';

    $id    = (int) ($params['id'] ?? 0);
    $album = $id > 0 ? find_album($id) : null;
    $back  = $id > 0 ? '/albums/' . $id . '/modifier' : '/albums/nouveau';

    if ($id > 0 && ($album === null || !journaliste_peut_modifier('album', $album, $compte))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Album introuvable', 'message' => 'Cet album n’existe pas, ou il ne relève pas des branches où vous pouvez écrire.']);
        return;
    }

    // 1.37.1 : modification directe refusée « avec validation » (voir le
    // formulaire) ; la création, elle, part en attente (plus bas).
    if ($id > 0 && !$estAdmin && journaliste_avec_validation($compte)) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Modification non autorisée',
            'message' => 'Votre réglage est « avec validation » : un album ne peut pas être modifié directement. '
                . 'Demandez à un administrateur de le corriger, ou de passer votre compte « sans validation ».',
        ]);
        return;
    }

    $old = [
        'title'       => post('title'),
        'description' => post('description'),
        'visibility'  => sanitize_visibility(post('visibility')),
        'branches'    => array_map('intval', post_array('branches')),
        'photos'      => array_map('intval', post_array('photos')),
        'couverture'  => (int) post('couverture'),
    ];

    $errors = [];
    if ($old['title'] === '') {
        $errors[] = 'Le titre de l’album est obligatoire.';
    }
    if (mb_strlen($old['title']) > 200) {
        $errors[] = 'Le titre est limité à 200 caractères.';
    }
    if (mb_strlen($old['description']) > 5000) {
        $errors[] = 'La description est trop longue (5000 caractères maximum).';
    }
    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }
    if (!$estAdmin && $visibility === 'branches' && !journaliste_branches_permises($branches, $compte)) {
        $errors[] = 'Vous ne pouvez rattacher un album qu’aux branches de votre périmètre.';
    }

    $photosValides = [];
    foreach ($old['photos'] as $documentId) {
        if ($documentId > 0 && find_viewable_document($documentId) !== null) {
            $photosValides[] = $documentId;
        }
    }
    $couverture = $old['couverture'] > 0 && in_array($old['couverture'], $photosValides, true)
        ? $old['couverture'] : null;

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    if ($id > 0 && !$estAdmin) {
        contenu_historique_enregistrer('album', $album, (int) $compte['id'], 'modification_directe', contenu_historique_colonnes('album'));
    }

    $slug = slugify($old['title']);
    if ($slug === '') {
        $slug = 'album';
    }
    $base  = $slug;
    $index = 2;
    while (db_one('SELECT id FROM albums WHERE slug = ? AND id <> ?', [$slug, $id])) {
        $slug = $base . '-' . $index++;
    }

    // 1.37.1 : un journaliste « avec validation » ne publie jamais un album
    // directement. Il naît privé et « en attente » ; la visibilité choisie
    // est gardée comme suggestion, appliquée à la validation par un
    // administrateur (bouton « Valider » sur la liste des albums).
    $enAttente = $id === 0 && !$estAdmin && journaliste_avec_validation($compte) && album_moderation_faite();

    $data = [
        'title'             => $old['title'],
        'slug'              => $slug,
        'description'       => $old['description'] !== '' ? $old['description'] : null,
        'cover_document_id' => $couverture,
        'visibility'        => $enAttente ? 'prive' : $visibility,
        'updated_at'        => time(),
    ];
    if ($enAttente) {
        $data['moderation_state']      = 'attente';
        $data['suggested_visibility'] = $visibility;
    }

    if ($id > 0) {
        db_update('albums', $id, $data);
        $albumId = $id;
    } else {
        // Un album créé par un journaliste est sa propriété (colonne existante).
        $data['created_by'] = (int) $compte['id'];
        $data['created_at'] = time();
        $albumId = db_insert('albums', $data);
    }

    set_album_photos($albumId, $photosValides);
    set_content_branches('album', $albumId, $visibility === 'branches' ? $branches : []);

    if (!$estAdmin) {
        log_activity((int) $compte['id'], $id > 0 ? 'album_modifie' : 'album_ajoute', $old['title'] . ' (#' . $albumId . ')');
        if (!journaliste_avec_validation($compte)) {
            journaliste_journaliser($id > 0 ? 'modification' : 'publication', 'album', $albumId, $old['title'], $compte);
        }
    }

    if ($enAttente) {
        flash('succes', 'Album proposé : il paraîtra après validation par un administrateur. '
            . 'Vous pouvez le relire par son adresse ; personne d’autre ne le voit pour le moment.');
        redirect('/albums/' . $slug);
    }

    flash('succes', $id > 0 ? 'L’album a été mis à jour.' : 'L’album a été créé.');
    redirect('/albums/' . $slug);
}
