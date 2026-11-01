<?php
/**
 * Albums photos (partie publique).
 *
 * Un album est visible selon sa propre visibilité ; les photos qu'il contient
 * gardent la leur. Un album vide n'affiche donc rien de confidentiel.
 */

declare(strict_types=1);

/** Liste des albums visibles. */
function public_albums(): void
{
    $total = 0;
    albums_visible(0, 0, $total);
    $pg = pagination($total, 12, max(1, query_int('page', 1)));

    $albums = albums_visible($pg['perPage'], $pg['offset'], $total);
    foreach ($albums as $index => $album) {
        $albums[$index]['nb_photos']  = album_photo_count((int) $album['id']);
        $albums[$index]['couverture'] = album_cover($album);
    }

    render('public/albums', [
        'titre'      => 'Albums photos',
        'albums'     => $albums,
        'pagination' => $pg,
        'total'      => $total,
    ]);
}

/** Un album et ses photos. */
function public_album(array $params): void
{
    $album = find_viewable_album((string) ($params['slug'] ?? ''));
    if (!$album) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Album introuvable',
            'message' => 'Cet album n’existe pas, ou il n’est pas accessible avec votre compte.',
        ]);
        return;
    }

    $total = album_photo_count((int) $album['id']);
    $pg    = pagination($total, 24, max(1, query_int('page', 1)));

    $photos = album_photos((int) $album['id'], $pg['perPage'], $pg['offset'], $total);

    // Qui figure sur chaque photo : une petite requête par photo, bornée par
    // la pagination (24 au maximum).
    $personnes = [];
    foreach ($photos as $photo) {
        $personnes[(int) $photo['id']] = document_persons((int) $photo['id']);
    }

    render('public/album', [
        'titre'      => (string) $album['title'],
        'album'      => $album,
        'photos'     => $photos,
        'personnes'  => $personnes,
        'pagination' => $pg,
        'total'      => $total,
    ]);
}
