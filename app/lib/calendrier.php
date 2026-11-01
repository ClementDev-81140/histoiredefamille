<?php
/**
 * Calendrier familial et frise chronologique.
 *
 * Les dates de l'arbre sont du texte libre (« 1901 », « 12 juin 1928 »,
 * « 1928-06-12 », « vers 1930 »). Une date sans jour ni mois ne peut pas être
 * placée dans un calendrier : elle reste dans la frise, à son année.
 *
 * Aucun accès à la base dans les fonctions de calcul : elles sont testables
 * partout, et le Raspberry Pi n'a jamais tout l'arbre en mémoire.
 */

declare(strict_types=1);

/** Noms des mois en français, index 1 à 12. */
function calendrier_mois_noms(): array
{
    return [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
}

/** Nom d'un mois (1 à 12). */
function calendrier_mois_nom(int $mois): string
{
    return calendrier_mois_noms()[$mois] ?? '';
}

/** Noms des jours, du lundi au dimanche. */
function calendrier_jours_noms(): array
{
    return ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
}

/**
 * Décompose une date écrite par la famille.
 *
 * Accepte « 1928-06-12 », « 12/06/1928 », « 12 juin 1928 », « 1er juin 1928 »,
 * « juin 1928 » (sans jour) et « 1901 » (année seule).
 *
 * @return array{annee:int, mois:int, jour:int}|null  mois et jour valent 0 si inconnus
 */
function date_parts(?string $date): ?array
{
    $texte = trim((string) $date);
    if ($texte === '') {
        return null;
    }

    // 1928-06-12
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $texte, $m)) {
        return date_parts_valider((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    // 12/06/1928
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texte, $m)) {
        return date_parts_valider((int) $m[3], (int) $m[2], (int) $m[1]);
    }

    // 12 juin 1928, 1er juin 1928
    if (preg_match('/^(\d{1,2})(?:er)?\s+([a-zéûôîà]+)\s+(\d{4})$/iu', $texte, $m)) {
        $mois = calendrier_mois_numero($m[2]);
        if ($mois > 0) {
            return date_parts_valider((int) $m[3], $mois, (int) $m[1]);
        }
    }

    // juin 1928 (sans jour). « vers 1930 » ne doit pas être pris pour un mois :
    // si le premier mot n'est pas un mois, on continue plus bas.
    if (preg_match('/^([a-zéûôîà]+)\s+(\d{4})$/iu', $texte, $m)) {
        $mois = calendrier_mois_numero($m[1]);
        if ($mois > 0) {
            return date_parts_valider((int) $m[2], $mois, 0);
        }
    }

    // 1901, vers 1901, 1901 (environ)
    if (preg_match('/(\d{4})/', $texte, $m)) {
        $annee = (int) $m[1];
        return ($annee > 1000 && $annee < 2200) ? ['annee' => $annee, 'mois' => 0, 'jour' => 0] : null;
    }

    return null;
}

/** Vérifie qu'un jour existe bien dans son mois. */
function date_parts_valider(int $annee, int $mois, int $jour): ?array
{
    if ($annee < 1000 || $annee > 2199 || $mois < 0 || $mois > 12) {
        return null;
    }
    if ($jour < 0 || $jour > 31) {
        return null;
    }
    if ($mois > 0 && $jour > 0 && !checkdate($mois, $jour, $annee)) {
        return null;
    }
    if ($mois === 0) {
        $jour = 0;
    }

    return ['annee' => $annee, 'mois' => $mois, 'jour' => $jour];
}

/** Numéro du mois à partir de son nom français (0 si inconnu). */
function calendrier_mois_numero(string $nom): int
{
    $nom = mb_strtolower(trim($nom), 'UTF-8');
    $nom = str_replace(['é', 'û', 'ô', 'î'], ['e', 'u', 'o', 'i'], $nom);

    foreach (calendrier_mois_noms() as $numero => $francais) {
        $simple = str_replace(['é', 'û', 'ô'], ['e', 'u', 'o'], mb_strtolower($francais, 'UTF-8'));
        if ($nom === $simple) {
            return $numero;
        }
    }

    return 0;
}

/**
 * Grille d'un mois, semaine par semaine, du lundi au dimanche.
 * Chaque case contient le numéro du jour, ou 0 pour une case vide.
 *
 * @return array<int, array<int, int>>
 */
function calendrier_grille(int $annee, int $mois): array
{
    if ($mois < 1 || $mois > 12) {
        return [];
    }

    $premier    = (int) date('N', mktime(0, 0, 0, $mois, 1, $annee)); // 1 = lundi
    $nbJours    = (int) date('t', mktime(0, 0, 0, $mois, 1, $annee));
    $cases      = array_fill(0, $premier - 1, 0);

    for ($jour = 1; $jour <= $nbJours; $jour++) {
        $cases[] = $jour;
    }
    while (count($cases) % 7 !== 0) {
        $cases[] = 0;
    }

    return array_chunk($cases, 7);
}

/** Mois précédent et suivant, sous la forme [année, mois]. */
function calendrier_mois_voisin(int $annee, int $mois, int $decalage): array
{
    $stamp = mktime(0, 0, 0, $mois + $decalage, 1, $annee);
    return [(int) date('Y', $stamp), (int) date('n', $stamp)];
}

/** Âge qu'aurait (ou qu'avait) une personne lors d'une année donnée. */
function calendrier_age(?int $anneeNaissance, int $annee): ?int
{
    if ($anneeNaissance === null || $anneeNaissance <= 0 || $annee < $anneeNaissance) {
        return null;
    }
    return $annee - $anneeNaissance;
}

// ---------------------------------------------------------------------------
// Données : calendrier d'un mois, frise d'une période
// ---------------------------------------------------------------------------

/**
 * Événements d'un mois (1 à 12) : naissances, mariages et décès, toutes années
 * confondues, pour les personnes visibles par la personne connectée.
 *
 * @return array<int, array<int, array>> jour => liste d'événements
 */
function calendrier_evenements(int $mois): array
{
    if ($mois < 1 || $mois > 12) {
        return [];
    }

    $evenements = [];

    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $personnes = db_all(
        "SELECT p.id, p.first_name, p.last_name, p.birth_date, p.death_date
         FROM persons p
         WHERE {$clause}
           AND ((p.birth_date IS NOT NULL AND TRIM(p.birth_date) <> '')
             OR (p.death_date IS NOT NULL AND TRIM(p.death_date) <> ''))",
        $params
    );

    foreach ($personnes as $personne) {
        $naissance = date_parts((string) $personne['birth_date']);
        if ($naissance !== null && $naissance['mois'] === $mois && $naissance['jour'] > 0) {
            $evenements[$naissance['jour']][] = [
                'genre'    => 'naissance',
                'annee'    => $naissance['annee'],
                'personne' => $personne,
                'age'      => calendrier_age(extract_year((string) $personne['death_date']), $naissance['annee']),
            ];
        }

        $deces = date_parts((string) $personne['death_date']);
        if ($deces !== null && $deces['mois'] === $mois && $deces['jour'] > 0) {
            $evenements[$deces['jour']][] = [
                'genre'    => 'deces',
                'annee'    => $deces['annee'],
                'personne' => $personne,
                'age'      => calendrier_age(extract_year((string) $personne['birth_date']), $deces['annee']),
            ];
        }
    }

    // Mariages : seulement si les deux époux sont visibles.
    $paramsMariage = [];
    $clauseH = visibility_clause('person', 'h', $paramsMariage);
    $clauseF = visibility_clause('person', 'w', $paramsMariage);
    $mariages = db_all(
        "SELECT f.marriage_date, f.marriage_place,
                h.id AS h_id, h.first_name AS h_first, h.last_name AS h_last,
                w.id AS w_id, w.first_name AS w_first, w.last_name AS w_last
         FROM families f
         JOIN persons h ON h.id = f.husband_id
         JOIN persons w ON w.id = f.wife_id
         WHERE {$clauseH} AND {$clauseF}
           AND f.marriage_date IS NOT NULL AND TRIM(f.marriage_date) <> ''",
        $paramsMariage
    );

    foreach ($mariages as $mariage) {
        $date = date_parts((string) $mariage['marriage_date']);
        if ($date === null || $date['mois'] !== $mois || $date['jour'] < 1) {
            continue;
        }
        $evenements[$date['jour']][] = [
            'genre'  => 'mariage',
            'annee'  => $date['annee'],
            'epoux'  => [
                'id'         => (int) $mariage['h_id'],
                'first_name' => (string) $mariage['h_first'],
                'last_name'  => (string) $mariage['h_last'],
            ],
            'epouse' => [
                'id'         => (int) $mariage['w_id'],
                'first_name' => (string) $mariage['w_first'],
                'last_name'  => (string) $mariage['w_last'],
            ],
            'lieu'   => (string) $mariage['marriage_place'],
        ];
    }

    foreach ($evenements as $jour => $liste) {
        usort($liste, static fn (array $a, array $b): int => $a['annee'] <=> $b['annee']);
        $evenements[$jour] = $liste;
    }
    ksort($evenements);

    return $evenements;
}

/**
 * Années extrêmes connues (naissance, décès, date de document), pour proposer
 * une période de frise pertinente.
 *
 * @return array{0:int, 1:int}
 */
function frise_bornes(): array
{
    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $personnes = db_one(
        "SELECT MIN(MIN(COALESCE(p.birth_year, 9999)), MIN(COALESCE(p.death_year, 9999))) AS mini,
                MAX(MAX(COALESCE(p.birth_year, 0)), MAX(COALESCE(p.death_year, 0))) AS maxi
         FROM persons p WHERE {$clause}",
        $params
    );

    $paramsDoc = [];
    $clauseDoc = visibility_clause('document', 'd', $paramsDoc);
    $documents = db_one(
        "SELECT MIN(d.doc_date_sort) AS mini, MAX(d.doc_date_sort) AS maxi
         FROM documents d WHERE {$clauseDoc} AND d.doc_date_sort IS NOT NULL",
        $paramsDoc
    );

    $minis = [];
    $maxis = [];
    if ($personnes !== null && (int) $personnes['mini'] > 0 && (int) $personnes['mini'] < 9999) {
        $minis[] = (int) $personnes['mini'];
    }
    if ($personnes !== null && (int) $personnes['maxi'] > 0) {
        $maxis[] = (int) $personnes['maxi'];
    }
    if ($documents !== null && $documents['mini'] !== null) {
        $minis[] = (int) $documents['mini'];
    }
    if ($documents !== null && $documents['maxi'] !== null) {
        $maxis[] = (int) $documents['maxi'];
    }

    $debut = $minis === [] ? (int) date('Y') - 50 : min($minis);
    $fin   = $maxis === [] ? (int) date('Y') : max($maxis);

    return [$debut, $fin];
}

/**
 * Événements d'une période, du plus ancien au plus récent :
 * naissances, décès, mariages et documents datés.
 *
 * @param int $maximum nombre d'événements au plus (le Pi n'a jamais tout en mémoire)
 */
function frise_evenements(int $debut, int $fin, int $maximum = 400): array
{
    if ($fin < $debut) {
        [$debut, $fin] = [$fin, $debut];
    }

    $evenements = [];

    // Naissances
    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $lignes = db_all(
        "SELECT p.id, p.first_name, p.last_name, p.birth_date, p.death_date, p.birth_year
         FROM persons p
         WHERE {$clause} AND p.birth_year BETWEEN :debut AND :fin",
        $params + [':debut' => $debut, ':fin' => $fin]
    );
    foreach ($lignes as $personne) {
        $evenements[] = [
            'annee'    => (int) $personne['birth_year'],
            'genre'    => 'naissance',
            'personne' => $personne,
            'date'     => (string) $personne['birth_date'],
        ];
    }

    // Décès
    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $lignes = db_all(
        "SELECT p.id, p.first_name, p.last_name, p.birth_date, p.death_date, p.death_year
         FROM persons p
         WHERE {$clause} AND p.death_year BETWEEN :debut AND :fin",
        $params + [':debut' => $debut, ':fin' => $fin]
    );
    foreach ($lignes as $personne) {
        $evenements[] = [
            'annee'    => (int) $personne['death_year'],
            'genre'    => 'deces',
            'personne' => $personne,
            'date'     => (string) $personne['death_date'],
        ];
    }

    // Mariages (les deux époux visibles)
    $params = [];
    $clauseH = visibility_clause('person', 'h', $params);
    $clauseF = visibility_clause('person', 'w', $params);
    $lignes = db_all(
        "SELECT f.marriage_date, f.marriage_place,
                h.id AS h_id, h.first_name AS h_first, h.last_name AS h_last,
                w.id AS w_id, w.first_name AS w_first, w.last_name AS w_last
         FROM families f
         JOIN persons h ON h.id = f.husband_id
         JOIN persons w ON w.id = f.wife_id
         WHERE {$clauseH} AND {$clauseF}
           AND f.marriage_date IS NOT NULL AND TRIM(f.marriage_date) <> ''",
        $params
    );
    foreach ($lignes as $mariage) {
        $date = date_parts((string) $mariage['marriage_date']);
        if ($date === null || $date['annee'] < $debut || $date['annee'] > $fin) {
            continue;
        }
        $evenements[] = [
            'annee'  => $date['annee'],
            'genre'  => 'mariage',
            'date'   => (string) $mariage['marriage_date'],
            'epoux'  => ['id' => (int) $mariage['h_id'], 'first_name' => (string) $mariage['h_first'], 'last_name' => (string) $mariage['h_last']],
            'epouse' => ['id' => (int) $mariage['w_id'], 'first_name' => (string) $mariage['w_first'], 'last_name' => (string) $mariage['w_last']],
            'lieu'   => (string) $mariage['marriage_place'],
        ];
    }

    // Documents datés
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $lignes = db_all(
        "SELECT d.id, d.title, d.kind, d.ext, d.doc_date, d.doc_date_sort
         FROM documents d
         WHERE {$clause} AND d.is_attachment = 0
           AND d.doc_date_sort BETWEEN :debut AND :fin",
        $params + [':debut' => $debut, ':fin' => $fin]
    );
    foreach ($lignes as $document) {
        $evenements[] = [
            'annee'    => (int) $document['doc_date_sort'],
            'genre'    => 'document',
            'document' => $document,
            'date'     => (string) $document['doc_date'],
        ];
    }

    usort($evenements, static function (array $a, array $b): int {
        return [$a['annee'], $a['genre']] <=> [$b['annee'], $b['genre']];
    });

    return array_slice($evenements, 0, max(1, $maximum));
}

/** Période de la frise : « 1900 – 1949 ». */
function frise_periode_label(int $debut, int $fin): string
{
    return $debut . ' – ' . $fin;
}
