<?php
/**
 * Onglet « Vidéos ».
 *
 * Les vidéos sont des documents (table documents, kind = 'video') : aucun
 * second système de stockage. Les droits sont vérifiés par visibility.php
 * (un visiteur non connecté ne voit que les vidéos publiques) et la lecture
 * passe par /video/{id} (HTTP Range). L'onglet est visible par tout le monde ;
 * seuls les administrateurs peuvent ajouter des vidéos.
 */

declare(strict_types=1);

/** Liste des vidéos visibles (publiques pour un visiteur, plus selon les droits). */
function public_videos(): void
{
    rediriger_ancien_param_theme();
    // Filtre par thème (1.32.0) : seuls les thèmes visibles comptent,
    // et le total affiché suit le filtre.
    $categorie  = category_filtre_demande('video');
    $categories = categories_avec_compteur('video');

    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $catWhere = category_where('d', $categorie > 0 ? $categorie : null, $params, 'video');
    $filtre = $catWhere !== '' ? ' AND ' . $catWhere : '';

    $total = (int) db_value(
        "SELECT COUNT(*) FROM documents d
         WHERE d.kind = 'video' AND d.is_attachment = 0 {$filtre} AND {$clause}",
        $params,
        0
    );

    $pg     = pagination($total, (int) config('per_page', 24), max(1, query_int('page', 1)));
    $videos = videos_visible($pg['perPage'], $pg['offset'], $totalVideos, $categorie);

    $nbFeatured = (int) db_value(
        "SELECT COUNT(*) FROM documents d
         WHERE d.kind = 'video' AND d.is_attachment = 0 AND d.is_featured = 1 AND {$clause}",
        $params,
        0
    );

    render('public/videos', [
        'categorie'  => $categorie,
        'categories' => $categories,
        'titre'      => 'Vidéos',
        'videos'     => $videos,
        'pagination' => $pg,
        'total'      => $total,
        'nbFeatured' => $nbFeatured,
    ]);
}

/** Fiche d'une vidéo, avec lecteur. */
function public_video_detail(array $params): void
{
    $video = find_viewable_video((int) ($params['id'] ?? 0));
    if (!$video) {
        // Même réponse qu'un document interdit : on ne révèle pas l'existence.
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Vidéo introuvable',
            'message' => 'Cette vidéo n’existe pas, ou elle n’est pas accessible avec votre compte.',
        ]);
        return;
    }

    $maxStreams = max(1, (int) config('video.max_concurrent_streams', 2));
    $actifs     = stream_active_count();

    $branches = [];
    if (is_logged_in()) {
        foreach (content_branch_ids('document', (int) $video['id']) as $branchId) {
            $branch = find_branch($branchId);
            if ($branch) {
                $branches[] = $branch['name'];
            }
        }
    }

    render('public/video', [
        'titre'       => $video['title'],
        'video'       => $video,
        'branches'    => $branches,
        'downloadable' => document_download_allowed($video),
        'saturation'  => $actifs >= $maxStreams,
        'maxStreams'  => $maxStreams,
        // Conversion en MP4 : la famille ne voit pas cette vidéo tant qu'elle
        // n'est pas prête ; l'administrateur, lui, voit toujours l'état.
        'conversion'  => conversion_avertissement((int) $video['id']),
    ]);
}
