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
 * lien vers sa fiche, et sa relation avec la personne sélectionnée. Rien
 * d'autre n'est transmis à la vue, pour qu'aucune donnée non prévue ne puisse
 * s'afficher par accident.
 *
 * @return array{id:int, nom:string, dates:string, sexe:string, url:string,
 *               relation:string, relation_libelle:string, couple_id:int,
 *               trait_couple:bool}
 */
function arbre_chene_noeud(array $person, int $racineId = 0): array
{
    $id = (int) $person['id'];

    $relation = $racineId > 0
        ? arbre_chene_relation($racineId, $person)
        : ['cle' => '', 'libelle' => ''];

    return [
        'id'               => $id,
        'nom'              => person_full_name($person),
        'dates'            => person_dates($person),
        'sexe'             => (string) ($person['sex'] ?? ''),
        'url'              => '/arbre/' . $id,
        'relation'         => $relation['cle'],
        'relation_libelle' => $relation['libelle'],
        'couple_id'        => 0,
        'trait_couple'     => false,
    ];
}

// ---------------------------------------------------------------------------
// Parenté : la relation d'une carte avec la personne sélectionnée (1.35.0)
// ---------------------------------------------------------------------------

/**
 * Le contexte de parenté d'une personne centrale, calculé une fois : qui sont
 * ses conjoints, ses parents, ses ancêtres (avec leur distance), ses
 * descendants, sa fratrie et ses beaux-parents.
 *
 * L'ordre des contrôles dans arbre_chene_relation() fait la règle demandée :
 * **un conjoint est d'abord un conjoint**. Deux personnes qui forment un
 * couple ne sont donc jamais présentées comme frère et sœur, même si les
 * données les disaient aussi enfants des mêmes parents. Et les ancêtres sont
 * ceux de la personne sélectionnée, jamais ceux de son conjoint : la famille
 * du conjoint, venue d'ailleurs, n'invente aucun aïeul sur cette page.
 *
 * @return array{conjoints:array<int,bool>, parents:array<int,bool>,
 *               ancetres:array<int,int>, descendants:array<int,int>,
 *               fratrie:array<int,bool>, beaux_parents:array<int,bool>}
 */
function arbre_chene_contexte_parente(int $racineId): array
{
    static $cache = [];
    if (isset($cache[$racineId])) {
        return $cache[$racineId];
    }

    $conjoints = [];
    foreach (person_unions($racineId) as $union) {
        if (is_array($union['spouse'] ?? null)) {
            $conjoints[(int) $union['spouse']['id']] = true;
        }
    }

    $parents = [];
    foreach (person_parents($racineId) as $parent) {
        $parents[(int) $parent['id']] = true;
    }

    // Distances d'ascendance : 1 = parent, 2 = grand-parent, 3 = arrière-…
    $ancetres = [];
    $vus = [$racineId => true];
    $courant = [$racineId];
    for ($niveau = 1; $niveau <= ARBRE_CHENE_ANCETRES; $niveau++) {
        $suivants = [];
        foreach ($courant as $id) {
            foreach (person_parents((int) $id) as $parent) {
                $pid = (int) $parent['id'];
                if ($pid <= 0 || isset($vus[$pid])) {
                    continue;
                }
                $vus[$pid] = true;
                $ancetres[$pid] = $niveau;
                $suivants[] = $pid;
            }
        }
        if ($suivants === []) {
            break;
        }
        $courant = $suivants;
    }

    // Distances de descendance : 1 = enfant, 2 = petit-enfant.
    $descendants = [];
    $vus = [$racineId => true];
    $courant = [$racineId];
    for ($niveau = 1; $niveau <= ARBRE_CHENE_DESCENDANTS; $niveau++) {
        $suivants = [];
        foreach ($courant as $id) {
            foreach (person_unions((int) $id) as $union) {
                foreach ($union['children'] as $enfant) {
                    $cid = (int) $enfant['id'];
                    if ($cid <= 0 || isset($vus[$cid])) {
                        continue;
                    }
                    $vus[$cid] = true;
                    $descendants[$cid] = $niveau;
                    $suivants[] = $cid;
                }
            }
        }
        if ($suivants === []) {
            break;
        }
        $courant = $suivants;
    }

    $fratrie = [];
    foreach (person_siblings($racineId) as $personne) {
        $fratrie[(int) $personne['id']] = true;
    }

    // Beaux-parents : conjoints d'un parent qui ne sont pas eux-mêmes parents
    // de la personne sélectionnée.
    $beauxParents = [];
    foreach (array_keys($parents) as $parentId) {
        foreach (person_unions((int) $parentId) as $union) {
            if (!is_array($union['spouse'] ?? null)) {
                continue;
            }
            $sid = (int) $union['spouse']['id'];
            if ($sid !== $racineId && !isset($parents[$sid])) {
                $beauxParents[$sid] = true;
            }
        }
    }

    return $cache[$racineId] = [
        'conjoints'     => $conjoints,
        'parents'       => $parents,
        'ancetres'      => $ancetres,
        'descendants'   => $descendants,
        'fratrie'       => $fratrie,
        'beaux_parents' => $beauxParents,
    ];
}

/**
 * La relation d'une personne avec la personne sélectionnée : une clé courte
 * (pour les attributs et les essais) et le libellé affiché sur la carte.
 *
 * @return array{cle:string, libelle:string}
 */
function arbre_chene_relation(int $racineId, array $person): array
{
    $id = (int) ($person['id'] ?? 0);
    $sexe = (string) ($person['sex'] ?? '');
    $contexte = arbre_chene_contexte_parente($racineId);

    if ($id === $racineId) {
        return ['cle' => 'selection', 'libelle' => 'Personne sélectionnée'];
    }
    // Le couple d'abord : jamais « frère / sœur » pour un conjoint.
    if (isset($contexte['conjoints'][$id])) {
        $libelle = $sexe === 'F' ? 'Conjointe' : ($sexe === 'M' ? 'Conjoint' : 'Conjoint / conjointe');
        return ['cle' => 'conjoint', 'libelle' => $libelle];
    }
    if (isset($contexte['ancetres'][$id])) {
        return match ($contexte['ancetres'][$id]) {
            1 => ['cle' => 'parent', 'libelle' => 'Parent'],
            2 => ['cle' => 'grand-parent', 'libelle' => 'Grand-parent'],
            3 => ['cle' => 'arriere-grand-parent', 'libelle' => 'Arrière-grand-parent'],
            default => ['cle' => 'famille', 'libelle' => 'Famille'],
        };
    }
    if (isset($contexte['descendants'][$id])) {
        return match ($contexte['descendants'][$id]) {
            1 => ['cle' => 'enfant', 'libelle' => 'Enfant'],
            2 => ['cle' => 'petit-enfant', 'libelle' => 'Petit-enfant'],
            default => ['cle' => 'famille', 'libelle' => 'Famille'],
        };
    }
    if (isset($contexte['beaux_parents'][$id])) {
        return ['cle' => 'beau-parent', 'libelle' => 'Beau-parent'];
    }
    if (isset($contexte['fratrie'][$id])) {
        $libelle = $sexe === 'F' ? 'Sœur' : ($sexe === 'M' ? 'Frère' : 'Frère / sœur');
        return ['cle' => 'fratrie', 'libelle' => $libelle];
    }

    return ['cle' => 'famille', 'libelle' => 'Famille'];
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
function arbre_chene_generation(int $niveau, string $libelle, array $part, int $total, int $racineId = 0): array
{
    $rangees = [];
    foreach ($part['rangees'] as $rangee) {
        $rangees[] = array_map(
            static fn (array $personne): array => arbre_chene_noeud($personne, $racineId),
            $rangee
        );
    }

    // Les couples de la génération : deux cartes voisines qui forment un
    // couple reçoivent le trait en pointillés qui les relie. Le conjoint doit
    // être dans la même génération **et** juste à côté : sinon, pas de trait —
    // on ne relie jamais vers une carte absente, ni vers une personne que le
    // compte n'a pas le droit de voir (elle n'est simplement pas là).
    $idsGeneration = [];
    foreach ($rangees as $rangee) {
        foreach ($rangee as $noeud) {
            $idsGeneration[(int) $noeud['id']] = true;
        }
    }
    foreach ($rangees as &$rangee) {
        foreach ($rangee as &$noeud) {
            foreach (person_unions((int) $noeud['id']) as $union) {
                if (is_array($union['spouse'] ?? null)
                    && isset($idsGeneration[(int) $union['spouse']['id']])) {
                    $noeud['couple_id'] = (int) $union['spouse']['id'];
                    break;
                }
            }
        }
        unset($noeud);
        for ($i = 1, $n = count($rangee); $i < $n; $i++) {
            if ((int) $rangee[$i]['couple_id'] === (int) $rangee[$i - 1]['id']) {
                $rangee[$i]['trait_couple'] = true;
            }
        }
    }
    unset($rangee);

    return [
        'niveau'      => $niveau,
        'libelle'     => $libelle,
        'rangees'     => $rangees,
        'reste'       => $part['reste'],
        'total'       => $total,
        'traits'      => [],
        'traits_avant' => [],
    ];
}

// ---------------------------------------------------------------------------
// Traits de liaison entre les cartes (1.35.0)
// ---------------------------------------------------------------------------

/** Le centre horizontal (en %) de la carte n° $index dans une rangée de $total. */
function arbre_chene_centre(int $index, int $total): float
{
    return ($index + 0.5) * 100 / max(1, $total);
}

/** Un nombre pour un attribut SVG : deux décimales au plus, sans zéros inutiles. */
function arbre_chene_pct(float $valeur): string
{
    return rtrim(rtrim(number_format($valeur, 2, '.', ''), '0'), '.');
}

/**
 * Les segments qui relient des parents affichés à leurs enfants affichés.
 *
 * Le serveur connaît le nombre de cartes de chaque rangée et leur ordre : il
 * calcule donc les centres en pourcentages et écrit un petit SVG (voir la vue)
 * qu'aucun JavaScript n'a besoin de mesurer. Un parent ou un enfant qui n'est
 * pas affiché — rangée suivante du « voir plus », personne invisible pour le
 * compte — ne reçoit **aucun** trait : le dessin ne laisse pas deviner ce qui
 * n'est pas là.
 *
 * Forme classique : un trait vertical depuis le point central du couple (ou
 * le centre du parent seul), une barre horizontale, puis un trait vers
 * chaque enfant.
 *
 * @param array<int, array> $parents  cartes affichées de la génération du haut
 * @param array<int, array> $enfants  cartes affichées de la génération du bas
 * @return array<int, array{x1:string, y1:int, x2:string, y2:int}>
 */
function arbre_chene_traits_liaison(array $parents, array $enfants): array
{
    if ($parents === [] || $enfants === []) {
        return [];
    }

    $indexParents = [];
    foreach (array_values($parents) as $i => $noeud) {
        $indexParents[(int) $noeud['id']] = $i;
    }

    // Regrouper les enfants par leurs parents affichés.
    $groupes = [];
    foreach (array_values($enfants) as $j => $enfant) {
        $presents = [];
        foreach (person_parents((int) $enfant['id']) as $parent) {
            $pid = (int) $parent['id'];
            if (isset($indexParents[$pid])) {
                $presents[] = $pid;
            }
        }
        if ($presents === []) {
            continue;
        }
        sort($presents);
        $groupes[implode('-', $presents)][] = $j;
    }

    $segments = [];
    foreach ($groupes as $cle => $enfantsDuGroupe) {
        $parentIds = array_map('intval', explode('-', (string) $cle));
        $centresParents = array_map(
            static fn (int $pid): float => arbre_chene_centre($indexParents[$pid], count($parents)),
            $parentIds
        );
        $xSource = array_sum($centresParents) / count($centresParents);
        $xsEnfants = array_map(
            static fn (int $j): float => arbre_chene_centre($j, count($enfants)),
            $enfantsDuGroupe
        );

        $segments[] = ['x1' => arbre_chene_pct($xSource), 'y1' => 0, 'x2' => arbre_chene_pct($xSource), 'y2' => 12];
        if (count($xsEnfants) > 1 || abs($xSource - $xsEnfants[0]) > 0.01) {
            $segments[] = [
                'x1' => arbre_chene_pct(min(array_merge([$xSource], $xsEnfants))),
                'y1' => 12,
                'x2' => arbre_chene_pct(max(array_merge([$xSource], $xsEnfants))),
                'y2' => 12,
            ];
        }
        foreach ($xsEnfants as $x) {
            $segments[] = ['x1' => arbre_chene_pct($x), 'y1' => 12, 'x2' => arbre_chene_pct($x), 'y2' => 24];
        }
    }

    return $segments;
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
            count($personnes),
            $racineId
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
            count($personnes),
            $racineId
        );
    }
    $descendants = $parts;

    $noeudRacine = arbre_chene_noeud($racine, $racineId);
    $noeudsConjoints = [];
    foreach (arbre_chene_conjoints($racineId) as $conjoint) {
        $noeud = arbre_chene_noeud($conjoint, $racineId);
        $noeud['couple_id'] = $racineId;
        // Le premier conjoint est juste à droite de la personne sélectionnée :
        // le trait en pointillés du couple les relie (les suivants gardent leur
        // carte, sans trait vers quelqu'un qui n'est pas leur voisin).
        $noeud['trait_couple'] = $noeudsConjoints === [];
        $noeudsConjoints[] = $noeud;
    }
    $tronc = array_merge([$noeudRacine], $noeudsConjoints);

    // Les traits, génération par génération, entre cartes affichées seulement
    // (la première rangée de chaque génération : au-delà du « voir plus », les
    // cartes ne sont pas là, les traits non plus).
    foreach ($ancetres as $niveau => $generation) {
        $enfantsAffiches = $niveau === 1
            ? [$noeudRacine]
            : ($ancetres[$niveau - 1]['rangees'][0] ?? []);
        $ancetres[$niveau]['traits'] = arbre_chene_traits_liaison(
            $generation['rangees'][0] ?? [],
            $enfantsAffiches
        );
    }
    $precedent = $tronc;
    foreach ($descendants as $niveau => $generation) {
        $descendants[$niveau]['traits_avant'] = arbre_chene_traits_liaison(
            $precedent,
            $generation['rangees'][0] ?? []
        );
        $precedent = $generation['rangees'][0] ?? [];
    }

    return [
        'racine'      => $noeudRacine,
        'conjoints'   => $noeudsConjoints,
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
