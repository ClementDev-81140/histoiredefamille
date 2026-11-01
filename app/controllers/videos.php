<?php
/**
 * Onglet « Vidéos » : liste et lecteur (partie publique) et gestion (admin).
 *
 * Les vidéos sont des documents de type « video » : mêmes droits de
 * visibilité, même stockage, même diffusion par morceaux (HTTP Range).
 * Un visiteur non connecté ne voit que les vidéos publiques.
 */

declare(strict_types=1);

/** Liste des vidéos visibles par l'utilisateur courant. */
function public_videos(): void
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $where  = ["d.kind = 'video'", 'd.is_attachment = 0', $clause];

    $q = query('q');
    if ($q !== '') {
        $where[] = '(d.title LIKE :q OR d.description LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);
    $total    = (int) db_value("SELECT COUNT(*) FROM documents d WHERE {$whereSql}", $params, 0);
    $pg       = pagination($total, 0, max(1, query_int('page', 1)));

    // Vidéos mises en avant d'abord, puis ordre choisi, puis plus récentes.
    $videos = db_all(
        "SELECT d.* FROM documents d WHERE {$whereSql}
         ORDER BY d.featured DESC, d.sort_order ASC,
                  (d.doc_date_sort IS NULL), d.doc_date_sort DESC, d.created_at DESC
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    render('public/videos', [
        'titre'      => 'Vidéos',
        'videos'     => $videos,
        'pagination' => $pg,
        'q'          => $q,
        'total'      => $total,
    ]);
}

/** Lecteur d'une vidéo (/videos/{id}). */
function public_video_detail(array $params): void
{
    $doc = find_viewable_document((int) ($params['id'] ?? 0));

    // Même réponse qu'un document inexistant : on ne révèle rien.
    if (!$doc || ($doc['kind'] ?? '') !== 'video' || (int) $doc['is_attachment'] === 1) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Vidéo introuvable',
            'message' => 'Cette vidéo n’existe pas, ou elle n’est pas accessible avec votre compte.',
        ]);
        return;
    }

    // Autres vidéos visibles (colonne « À voir aussi »).
    $otherParams = [];
    $otherClause = visibility_clause('document', 'd', $otherParams);
    $others = db_all(
        "SELECT d.* FROM documents d
         WHERE d.kind = 'video' AND d.is_attachment = 0 AND d.id <> :id AND {$otherClause}
         ORDER BY d.featured DESC, d.sort_order ASC, d.created_at DESC
         LIMIT 4",
        $otherParams + [':id' => (int) $doc['id']]
    );

    render('public/video', [
        'titre'    => $doc['title'],
        'doc'      => $doc,
        'branches' => content_branch_ids('document', (int) $doc['id']),
        'others'   => $others,
    ]);
}

/** Gestion des vidéos (administration). */
function admin_videos(): void
{
    require_admin();

    $videos = db_all(
        "SELECT d.* FROM documents d
         WHERE d.kind = 'video' AND d.is_attachment = 0
         ORDER BY d.featured DESC, d.sort_order ASC, d.created_at DESC"
    );

    render('admin/videos', [
        'titre'  => 'Vidéos',
        'videos' => $videos,
    ], 'layout_admin');
}

/** Enregistre la mise en avant et l'ordre d'une vidéo. */
function admin_video_save(array $params): void
{
    require_admin();
    csrf_check();

    $id  = (int) ($params['id'] ?? 0);
    $doc = db_one("SELECT id, title FROM documents WHERE id = ? AND kind = 'video'", [$id]);
    if (!$doc) {
        flash('erreur', 'Vidéo introuvable.');
        redirect('/admin/videos');
    }

    $featured = post('featured') === '1' ? 1 : 0;
    $order    = max(0, min(9999, (int) post('sort_order', '0')));

    db_update('documents', $id, [
        'featured'   => $featured,
        'sort_order' => $order,
        'updated_at' => time(),
    ]);

    log_activity((int) current_account()['id'], 'video_ordre', (string) $doc['title']);
    flash('succes', 'La vidéo « ' . $doc['title'] . ' » a été mise à jour.');
    redirect('/admin/videos');
}
