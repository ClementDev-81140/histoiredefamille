<?php
/**
 * Recettes de famille et témoignages sonores (partie publique).
 *
 * Deux pages simples, avec les mêmes règles de visibilité que le reste du site.
 */

declare(strict_types=1);

/** Liste des recettes visibles. */
function public_recettes(): void
{
    $total = 0;
    recipes_visible(0, 0, $total);
    $pg = pagination($total, 18, max(1, query_int('page', 1)));

    $recettes = recipes_visible($pg['perPage'], $pg['offset'], $total);

    $photos = [];
    foreach ($recettes as $recette) {
        $photos[(int) $recette['id']] = recipe_photo($recette);
    }

    render('public/recettes', [
        'titre'      => 'Recettes de famille',
        'recettes'   => $recettes,
        'photos'     => $photos,
        'pagination' => $pg,
        'total'      => $total,
    ]);
}

/** Une recette. */
function public_recette(array $params): void
{
    $recette = find_viewable_recipe((string) ($params['slug'] ?? ''));
    if (!$recette) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Recette introuvable',
            'message' => 'Cette recette n’existe pas, ou elle n’est pas accessible avec votre compte.',
        ]);
        return;
    }

    render('public/recette', [
        'titre'   => (string) $recette['title'],
        'recette' => $recette,
        'photo'   => recipe_photo($recette),
        'ingredients' => recipe_ingredient_lines((string) $recette['ingredients']),
    ]);
}

/** Témoignages sonores : les documents audio visibles. */
function public_temoignages(): void
{
    $total = 0;
    audio_documents(0, 0, $total);
    $pg = pagination($total, 12, max(1, query_int('page', 1)));

    $documents = audio_documents($pg['perPage'], $pg['offset'], $total);

    $personnes = [];
    foreach ($documents as $document) {
        $personnes[(int) $document['id']] = document_persons((int) $document['id']);
    }

    render('public/temoignages', [
        'titre'      => 'Témoignages',
        'documents'  => $documents,
        'personnes'  => $personnes,
        'pagination' => $pg,
        'total'      => $total,
    ]);
}
