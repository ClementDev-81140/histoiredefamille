<?php
/**
 * L'arbre « chêne gravé » de la page /arbre (1.33.0).
 *
 * Le dessin suit la gravure du chêne : les **ancêtres** sont dans les branches
 * (en haut), la personne choisie — et son conjoint — sur le **tronc**, les
 * **descendants** dans les racines (en bas). Trois générations d'ancêtres et
 * deux de descendants, comme demandé.
 *
 * Deux règles tenues ici, et nulle part ailleurs :
 *
 *   1. **Aucune personne invisible ne sort d'ici.** Chaque personne passe par
 *      `filter_visible_persons()` (donc `can_view()`), et les compteurs — le
 *      nombre de personnes d'une génération, le « voir les N autres » — sont
 *      calculés **après** ce filtre : un compte ne peut pas déduire l'existence
 *      d'une fiche privée par un nombre ou par un lien.
 *   2. **Aucune position en style en ligne.** Une génération est une rangée
 *      souple ; le nombre de cartes de la rangée donne une classe CSS
 *      prédéfinie (`arbre-chene__rangee--1` à `--8`), la feuille de style
 *      s'occupant de la largeur. Au-delà de huit cartes, on ne tasse rien :
 *      on propose « voir plus ».
 *
 * Le rendu ne dépend d'aucun JavaScript : tout est écrit dans la page.
 */
declare(strict_types=1);

/** Générations d'ancêtres au-dessus du tronc. */
const ARBRE_CHENE_ANCETRES = 3;

/** Générations de descendants sous le tronc. */
const ARBRE_CHENE_DESCENDANTS = 2;

/** Cartes au plus dans une rangée : au-delà, « voir plus ». */
const ARBRE_CHENE_MAX_RANGEE = 8;

/** Personnes proposées dans le sélecteur (au-delà, la recherche sert). */
const ARBRE_CHENE_MAX_CHOIX = 300;

// ---------------------------------------------------------------------------
// Libellés
// ---------------------------------------------------------------------------

/**
 * Nom de la génération d'ancêtres : 1 = parents, 2 = grands-parents…
 * (le nombre de générations est borné par ARBRE_CHENE_ANCETRES).
 */
function arbre_chene_libelle_ascendant(int $niveau): string
{
    return [
        1 => 'Parents',
        2 => 'Grands-parents',
        3 => 'Arrière-grands-parents',
    ][$niveau] ?? ('Ascendants, génération ' . $niveau);
}

/** Nom de la génération de descendants : 1 = enfants, 2 = petits-enfants… */
function arbre_chene_libelle_descendant(int $niveau): string
{
    return [
        1 => 'Enfants',
        2 => 'Petits-enfants',
    ][$niveau] ?? ('Descendants, génération ' . $niveau);
}

// ---------------------------------------------------------------------------
// Nœuds
// ---------------------------------------------------------------------------

/**
 * Ce qu'une carte de l'arbre montre d'une personne : son nom, ses dates, son
 * lien vers sa fiche. Rien d'autre n'est transmis à la vue, pour qu'aucune
 * donnée non prévue ne puisse s'afficher par accident.
 *
 * @return array{id:int, nom:string, dates:string, sexe:string, url:string}
 */
function arbre_chene_noeud(array $person): array
{
    $id = (int) $person['id'];

    return [
        'id'    => $id,
        'nom'   => person_full_name($person),
        'dates' => person_dates($person),
        'sexe'  => (string) ($person['sex'] ?? ''),
        'url'   => '/arbre/' . $id,
    ];
}

/**
 * Trie et dédoublonne des personnes, puis ne garde que celles que le compte
 * courant a le droit de voir.
 *
 * @param array<int, array> $persons
 * @return array<int, array>
 */
function arbre_chene_visibles(array $persons): array
{
    $uniques = [];
    foreach ($persons as $person) {
        if (!isset($person['id'])) {
            continue;
        }
        $uniques[(int) $person['id']] = $person;
    }

    return filter_visible_persons(array_values($uniques));
}

// ---------------------------------------------------------------------------
// Générations
// ---------------------------------------------------------------------------

/**
 * Les générations d'ancêtres d'une personne, de la plus proche à la plus
 * lointaine.
 *
 * Un remontée en largeur : niveau 1 = parents, niveau 2 = grands-parents…
 * Les personnes déjà vues (la personne centrale comprise) ne reviennent
 * jamais, ce qui protège des boucles d'un arbre mal saisi.
 *
 * @return array<int, array<int, array>> niveau => personnes
 */
function arbre_chene_ascendants(int $personId, int $profondeur = ARBRE_CHENE_ANCETRES): array
{
    $niveaux = [];
    $vus     = [$personId => true];
    $courant = [$personId];

    for ($niveau = 1; $niveau <= $profondeur; $niveau++) {
        $suivants = [];
        foreach ($courant as $id) {
            foreach (person_parents((int) $id) as $parent) {
                $pid = (int) $parent['id'];
                if ($pid <= 0 || isset($vus[$pid])) {
                    continue;
                }
                $vus[$pid]  = true;
                $suivants[] = $parent;
            }
        }

        $suivants = arbre_chene_visibles($suivants);
        if ($suivants === []) {
            break;
        }

        $niveaux[$niveau] = $suivants;
        $courant          = array_map(static fn (array $p): int => (int) $p['id'], $suivants);
    }

    return $niveaux;
}

/**
 * Les générations de descendants, de la plus proche à la plus lointaine
 * (1 = enfants, 2 = petits-enfants…).
 *
 * @return array<int, array<int, array>> niveau => personnes
 */
function arbre_chene_descendants(int $personId, int $profondeur = ARBRE_CHENE_DESCENDANTS): array
{
    $niveaux = [];
    $vus     = [$personId => true];
    $courant = [$personId];

    for ($niveau = 1; $niveau <= $profondeur; $niveau++) {
        $suivants = [];
        foreach ($courant as $id) {
            foreach (person_unions((int) $id) as $union) {
                foreach ($union['children'] as $child) {
                    $cid = (int) $child['id'];
                    if ($cid <= 0 || isset($vus[$cid])) {
                        continue;
                    }
                    $vus[$cid]  = true;
                    $suivants[] = $child;
                }
            }
        }

        $suivants = arbre_chene_visibles($suivants);
        if ($suivants === []) {
            break;
        }

        $niveaux[$niveau] = $suivants;
        $courant          = array_map(static fn (array $p): int => (int) $p['id'], $suivants);
    }

    return $niveaux;
}

/**
 * Les conjoints visibles d'une personne (ceux qui partagent le tronc avec
 * elle).
 *
 * @return array<int, array>
 */
function arbre_chene_conjoints(int $personId): array
{
    $conjoints = [];
    foreach (person_unions($personId) as $union) {
        if (is_array($union['spouse'] ?? null)) {
            $conjoints[] = $union['spouse'];
        }
    }

    return arbre_chene_visibles($conjoints);
}

// ---------------------------------------------------------------------------
// Rangées et « voir plus »
// ---------------------------------------------------------------------------

/**
 * Découpe une génération en rangées de ARBRE_CHENE_MAX_RANGEE cartes au plus.
 *
 * Sans « voir plus », on ne garde que la première rangée et l'on dit combien de
 * personnes restent — ce nombre ne compte que des personnes visibles.
 *
 * @param array<int, array> $personnes
 * @return array{rangees: array<int, array<int, array>>, reste: int}
 */
function arbre_chene_rangees(array $personnes, bool $tout): array
{
    $personnes = array_values($personnes);

    // Au-delà de huit cartes sur une rangée, la largeur d'une carte deviendrait
    // illisible sur téléphone : on coupe en rangées, et « voir plus » décide si
    // l'on montre les suivantes.
    $rang           = array_slice($personnes, 0, ARBRE_CHENE_MAX_RANGEE);
    $rangees        = [$rang];
    $restePersonnes = array_slice($personnes, ARBRE_CHENE_MAX_RANGEE);

    if ($restePersonnes !== []) {
        if ($tout) {
            foreach (array_chunk($restePersonnes, ARBRE_CHENE_MAX_RANGEE) as $morceau) {
                $rangees[] = $morceau;
            }
        }
    }

    return [
        'rangees' => $rangees,
        // Avec « voir plus », tout est affiché : il ne reste rien à annoncer.
        'reste'   => $tout ? 0 : count($restePersonnes),
    ];
}

/**
 * Met une génération en forme pour la vue : ses rangées de **cartes** (et non
 * de lignes de base), son reste éventuel et son total.
 *
 * @param array{rangees: array<int, array<int, array>>, reste: int} $part
 * @return array{niveau:int, libelle:string, rangees:array, reste:int, total:int}
 */
function arbre_chene_generation(int $niveau, string $libelle, array $part, int $total): array
{
    $rangees = [];
    foreach ($part['rangees'] as $rangee) {
        $rangees[] = array_map('arbre_chene_noeud', $rangee);
    }

    return [
        'niveau'  => $niveau,
        'libelle' => $libelle,
        'rangees' => $rangees,
        'reste'   => $part['reste'],
        'total'   => $total,
    ];
}

// ---------------------------------------------------------------------------
// L'arbre complet
// ---------------------------------------------------------------------------

/**
 * Toutes les données de l'arbre d'une personne, prêtes pour la vue.
 *
 * Les générations d'ancêtres sont rendues **de la plus lointaine à la plus
 * proche** (les branches du haut portent les aïeux), les descendants de la plus
 * proche à la plus lointaine (les racines s'éloignent vers le bas).
 *
 * @return array{
 *     racine: array, conjoints: array<int, array>, ancetres: array<int, array>,
 *     descendants: array<int, array>, reste: int, tout: bool
 * }
 */
function arbre_chene_donnees(int $racineId, bool $tout = false): ?array
{
    $racine = find_viewable_person($racineId);
    if ($racine === null) {
        return null;
    }

    $ancetres   = arbre_chene_ascendants($racineId);
    $descendants = arbre_chene_descendants($racineId);

    // Les branches se lisent du plus lointain au plus proche.
    krsort($ancetres);
    ksort($descendants);

    $reste = 0;
    $parts = [];
    foreach ($ancetres as $niveau => $personnes) {
        $part           = arbre_chene_rangees($personnes, $tout);
        $reste         += $part['reste'];
        $parts[$niveau] = arbre_chene_generation(
            (int) $niveau,
            arbre_chene_libelle_ascendant((int) $niveau),
            $part,
            count($personnes)
        );
    }
    $ancetres = $parts;

    $parts = [];
    foreach ($descendants as $niveau => $personnes) {
        $part           = arbre_chene_rangees($personnes, $tout);
        $reste         += $part['reste'];
        $parts[$niveau] = arbre_chene_generation(
            (int) $niveau,
            arbre_chene_libelle_descendant((int) $niveau),
            $part,
            count($personnes)
        );
    }
    $descendants = $parts;

    return [
        'racine'      => arbre_chene_noeud($racine),
        'conjoints'   => array_map('arbre_chene_noeud', arbre_chene_conjoints($racineId)),
        'ancetres'    => $ancetres,
        'descendants' => $descendants,
        'reste'       => $reste,
        'tout'        => $tout,
    ];
}

// ---------------------------------------------------------------------------
// Choix de la personne centrale
// ---------------------------------------------------------------------------

/**
 * La personne centrale par défaut : la plus récemment mise à jour parmi celles
 * que le compte courant peut voir.
 */
function arbre_chene_racine_defaut(): ?array
{
    // Une seule requête par page, même si la vue la demande deux fois — et une
    // mémoire par compte, comme filter_visible_persons().
    static $cache = [];
    $compte  = current_account();
    $prefixe = $compte !== null ? 'compte:' . (int) $compte['id'] : 'visiteur';
    if (array_key_exists($prefixe, $cache)) {
        return $cache[$prefixe];
    }

    $params = [];
    $clause = visibility_clause('person', 'p', $params);

    // De préférence quelqu'un qui a de la famille autour de lui : une fiche
    // isolée donnerait un arbre vide, ce qui n'est pas une bonne première vue.
    // `EXISTS` s'arrête au premier lien trouvé — pas de comptage inutile.
    $lie = "(EXISTS (SELECT 1 FROM family_children fc WHERE fc.person_id = p.id)
             OR EXISTS (SELECT 1 FROM family_children fc
                        JOIN families f ON f.id = fc.family_id
                        WHERE f.husband_id = p.id OR f.wife_id = p.id))";

    $person = db_one(
        "SELECT p.* FROM persons p WHERE {$clause} AND {$lie}
         ORDER BY p.updated_at DESC, p.id DESC LIMIT 1",
        $params
    );

    if ($person === null) {
        $person = db_one(
            "SELECT p.* FROM persons p WHERE {$clause}
             ORDER BY p.updated_at DESC, p.id DESC LIMIT 1",
            $params
        );
    }

    $trouve = $person !== null && can_view((string) $person['visibility'], content_branch_ids('person', (int) $person['id']))
        ? $person
        : null;

    $cache[$prefixe] = $trouve;

    return $trouve;
}

/**
 * Les personnes proposées dans le sélecteur — **uniquement celles que le
 * compte courant a le droit de voir**.
 *
 * @return array{personnes: array<int, array>, tronquee: bool}
 */
function arbre_chene_choix(int $racineId = 0): array
{
    $params = [];
    $clause = visibility_clause('person', 'p', $params);

    $total = (int) db_value("SELECT COUNT(*) FROM persons p WHERE {$clause}", $params, 0);

    $personnes = db_all(
        "SELECT p.* FROM persons p WHERE {$clause}
         ORDER BY TRIM(p.last_name) COLLATE NOCASE, TRIM(p.first_name) COLLATE NOCASE, p.id
         LIMIT " . ARBRE_CHENE_MAX_CHOIX,
        $params
    );

    // La personne centrale choisie doit toujours figurer dans la liste, même si
    // elle arrive au-delà de la limite : sans cela, le formulaire la perdrait.
    if ($racineId > 0) {
        $dedans = false;
        foreach ($personnes as $personne) {
            if ((int) $personne['id'] === $racineId) {
                $dedans = true;
                break;
            }
        }
        if (!$dedans) {
            $racine = find_viewable_person($racineId);
            if ($racine !== null) {
                $personnes = array_merge([$racine], $personnes);
            }
        }
    }

    return [
        'personnes' => array_map('arbre_chene_noeud', $personnes),
        'tronquee'  => $total > count($personnes),
    ];
}
