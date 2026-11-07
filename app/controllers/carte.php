<?php
/**
 * Carte des lieux (page publique et fiche d'un lieu).
 *
 * Aucun service externe : le fond vient d'un fichier embarqué, les points sont
 * calculés en PHP, et seules les personnes visibles par la personne qui
 * regarde sont comptées.
 */

declare(strict_types=1);

/** Page « Carte des lieux ». */
function public_carte(): void
{
    // La carte n'est plus publique (1.31.0) : elle s'ouvre une fois connecté,
    // comme l'arbre. Un visiteur est renvoyé vers la connexion.
    require_login();

    $filtres  = carte_filtres();
    // Le cache est propre à chaque groupe de droits ; le paramètre permet de
    // forcer un recalcul (utile après avoir placé un lieu, et pour les tests).
    $donnees  = carte_donnees($filtres, isset($_GET['recalculer']));

    // Choix des branches : on ne propose que celles que l'on peut voir.
    $branches = [];
    foreach (accessible_branch_ids() as $id) {
        $branche = find_branch((int) $id);
        if ($branche !== null) {
            $branches[(int) $branche['id']] = (string) $branche['name'];
        }
    }
    if (is_admin()) {
        $branches = [];
        foreach (db_all('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name COLLATE NOCASE') as $branche) {
            $branches[(int) $branche['id']] = (string) $branche['name'];
        }
    }

    render('public/carte', [
        'titre'    => 'Carte des lieux',
        'filtres'  => $filtres,
        'donnees'  => $donnees,
        'svg'      => carte_svg($donnees, $filtres['vue'], $filtres['lieu']),
        'tableau'  => carte_tableau($donnees),
        'branches' => $branches,
        'decennies' => carte_decennies(),
        'source'   => carte_fond()['source'] ?? 'Natural Earth',
        'aPlacer'  => is_admin() ? count(carte_lieux_a_placer()) : 0,
    ]);
}

/** Fiche d'un lieu : qui y est né, qui y est mort, qui s'y est marié. */
function public_lieu(array $params): void
{
    // Même règle que la carte : la fiche d'un lieu n'est plus publique.
    require_login();

    $id    = (int) ($params['id'] ?? 0);
    $place = carte_place($id);

    // Un lieu inconnu, un lieu sans coordonnées et un lieu dont aucune personne
    // n'est visible pour ce visiteur reçoivent exactement la même réponse : on
    // ne peut pas deviner, en la lisant, que le lieu existe.
    $introuvable = 'Ce lieu n’existe pas sur la carte, ou aucune personne visible n’y est rattachée.';

    if ($place === null || !carte_coordonnees_valides($place['lat'], $place['lon'])) {
        http_response_code(404);
        render('erreur', ['titre' => 'Lieu introuvable', 'message' => $introuvable]);
        return;
    }

    $personnes = carte_personnes_du_lieu($place);
    $mariages  = carte_mariages_du_lieu($place);

    // Un lieu qui n'apparaît que par un mariage compte aussi (c'est ce que
    // montre la carte) : il ne suffit donc pas qu'il ait des naissances.
    if ($personnes === [] && $mariages === []) {
        http_response_code(404);
        render('erreur', ['titre' => 'Lieu introuvable', 'message' => $introuvable]);
        return;
    }

    render('public/lieu', [
        'titre'     => (string) $place['nom'],
        'place'     => $place,
        'personnes' => $personnes,
        'mariages'  => $mariages,
    ]);
}
