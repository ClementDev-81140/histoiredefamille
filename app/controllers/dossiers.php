<?php
/**
 * Page publique d'un dossier d'archives (1.36.0).
 *
 * Le dossier n'a pas de visibilité propre : il n'apparaît que par les
 * documents que le compte peut voir dedans. Un dossier sans document
 * visible pour ce compte répond exactement comme un dossier inexistant
 * (404) : ni son nom ni son nombre ne fuient.
 */

declare(strict_types=1);

/** Page d'un dossier : son contenu visible par le compte courant. */
function public_dossier(array $params): void
{
    $id = (int) ($params['id'] ?? 0);
    $dossier = dossier_par_id($id);

    if ($dossier === null || !dossier_visible_pour_compte($id)) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Dossier introuvable',
            'message' => 'Ce dossier n’existe pas, ou il ne contient rien que votre compte puisse voir.',
        ]);
        return;
    }

    // La personne liée n'est nommée que si le compte peut voir sa fiche.
    $personne = null;
    if ((int) ($dossier['person_id'] ?? 0) > 0) {
        $personne = find_viewable_person((int) $dossier['person_id']);
    }

    render('public/dossier', [
        'titre'     => (string) $dossier['name'],
        'dossier'   => $dossier,
        'documents' => dossier_documents_visibles($id),
        'personne'  => $personne,
    ]);
}
