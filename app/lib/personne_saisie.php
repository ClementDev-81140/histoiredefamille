<?php
/**
 * Contrôles de base pour la future saisie des liens d'une fiche personne.
 * Ces fonctions ne réalisent aucune écriture et ne remplacent pas les
 * contrôles d'accès et CSRF des contrôleurs qui les utiliseront.
 */

declare(strict_types=1);

/**
 * Personnes proposées pour une nouvelle relation. La visibilité est celle
 * du compte courant ; pour un journaliste, le rattachement explicite doit
 * aussi recouper son périmètre. Sans branche particulière, une personne
 * n'est proposée qu'à un journaliste « toutes les branches » ou un admin.
 *
 * Le contrôleur devra appeler sa garde avant cette fonction et revérifier
 * les personnes choisies à la confirmation, car droits et données peuvent
 * avoir changé depuis l'aperçu.
 *
 * @return list<array>
 */
function personne_saisie_suggestions(): array
{
    $compte = current_account();
    if ($compte === null || (($compte['role'] ?? '') !== 'admin'
        && (!compte_est_journaliste($compte) || journaliste_avec_validation($compte)))) {
        return [];
    }

    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $personnes = db_all(
        "SELECT p.id, p.first_name, p.last_name, p.birth_year, p.birth_place
         FROM persons p WHERE {$clause}
         ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE, p.id",
        $params
    );
    if (($compte['role'] ?? '') === 'admin') {
        return $personnes;
    }
    $perimetre = journaliste_perimetre($compte);
    if ($perimetre === null) {
        return $personnes;
    }
    return array_values(array_filter($personnes, static function (array $personne) use ($perimetre): bool {
        return array_intersect(contenu_releve_branches('person', $personne), $perimetre) !== [];
    }));
}

/**
 * Un lien parent → enfant proposé créerait-il un cycle ?
 *
 * On remonte tous les ancêtres du parent proposé, sans filtre de visibilité :
 * même un chemin privé doit empêcher un cycle. Le résultat est un booléen
 * seulement ; l'interface ne doit jamais révéler la chaîne trouvée. UNION
 * déduplique les personnes et termine même si la base contient déjà un cycle.
 * Cette vérification doit être répétée dans la transaction de confirmation.
 */
function personne_saisie_creerait_cycle(int $parentId, int $enfantId): bool
{
    if ($parentId <= 0 || $enfantId <= 0) {
        return false;
    }
    return (int) db_value(
        'WITH RECURSIVE ancetres(id) AS (
             SELECT CAST(:parent AS INTEGER)
             UNION
             SELECT f.husband_id FROM ancetres a
             JOIN family_children fc ON fc.person_id = a.id
             JOIN families f ON f.id = fc.family_id
             WHERE f.husband_id IS NOT NULL
             UNION
             SELECT f.wife_id FROM ancetres a
             JOIN family_children fc ON fc.person_id = a.id
             JOIN families f ON f.id = fc.family_id
             WHERE f.wife_id IS NOT NULL
         ) SELECT EXISTS(SELECT 1 FROM ancetres WHERE id = CAST(:enfant AS INTEGER))',
        [':parent' => $parentId, ':enfant' => $enfantId],
        0
    ) === 1;
}

/**
 * Vérifie simultanément tous les nouveaux liens proposés, avant écriture.
 *
 * Chaque élément est un couple [parent, enfant]. Des identifiants négatifs
 * distincts peuvent représenter des fiches nouvelles dans l'aperçu ; zéro
 * n'est pas un identifiant. Le graphe existant, même privé, et l'ensemble
 * des liens proposés sont parcourus ensemble. Cela empêche notamment deux
 * liens qui sont chacun inoffensifs isolément mais forment ensemble un cycle.
 * La fonction ne renvoie aucun nom ni aucune chaîne de parenté.
 *
 * @param list<array{0:int, 1:int}> $liens
 */
function personne_saisie_liens_creeraient_cycle(array $liens): bool
{
    $ajouts = [];
    foreach ($liens as [$parent, $enfant]) {
        if ($parent === 0 || $enfant === 0) {
            throw new InvalidArgumentException('Identifiant de personne invalide.');
        }
        $ajouts[$enfant][] = $parent;
    }
    $cache = [];
    foreach ($liens as [$parent, $enfant]) {
        $file = [$parent];
        $vus = [];
        while ($file !== []) {
            $courant = array_pop($file);
            if ($courant === $enfant) {
                return true;
            }
            if (isset($vus[$courant])) {
                continue;
            }
            $vus[$courant] = true;
            if ($courant > 0 && !isset($cache[$courant])) {
                $cache[$courant] = array_map(
                    static fn (array $p): int => (int) $p['id'],
                    person_parents($courant)
                );
            }
            foreach (array_merge($cache[$courant] ?? [], $ajouts[$courant] ?? []) as $ascendant) {
                if (!isset($vus[$ascendant])) {
                    $file[] = $ascendant;
                }
            }
        }
    }
    return false;
}
