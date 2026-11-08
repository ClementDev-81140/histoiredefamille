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
// Réglage « décès dans le calendrier » (1.34.0)
// ---------------------------------------------------------------------------

/**
 * Les dates de décès apparaissent-elles dans le calendrier ?
 *
 * Réglage « Afficher les dates de décès dans le calendrier »
 * (Administration → Réglages), DÉSACTIVÉ par défaut : le calendrier montre
 * les anniversaires et les anniversaires de mariage ; un décès n'y figure,
 * sous le libellé sobre « In memoriam », que si un administrateur a coché
 * la case. La frise chronologique, elle, reste un relevé historique et
 * n'est pas concernée.
 */
function calendrier_afficher_deces(bool $relire = false): bool
{
    static $affiche = null;
    if ($relire) {
        $affiche = null;
    }
    if ($affiche === null) {
        $affiche = site_setting('calendrier_deces') === '1';
    }

    return $affiche;
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

        // 1.34.0 : aucun décès dans le calendrier tant que le réglage
        // « Afficher les dates de décès » n'est pas activé par un
        // administrateur (décoché par défaut).
        $deces = calendrier_afficher_deces() ? date_parts((string) $personne['death_date']) : null;
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

    // Événements de famille saisis dans l'administration (1.32.0) : ils
    // suivent exactement la visibilité choisie, et un événement annuel revient
    // chaque année.
    foreach (evenements_du_mois($mois, (int) date('Y')) as $jour => $liste) {
        foreach ($liste as $evenement) {
            $debut = date_parts((string) $evenement['start_date']);
            $evenements[$jour][] = [
                'genre'    => 'evenement',
                'annee'    => $debut['annee'] ?? (int) date('Y'),
                'id'       => (int) $evenement['id'],
                'type'     => (string) ($evenement['type'] ?? ''),
                'titre'    => (string) $evenement['title'],
                'heure'    => (string) ($evenement['start_time'] ?? ''),
                'lieu'     => (string) $evenement['place'],
                'annuel'   => (int) $evenement['annual'] === 1,
            ];
        }
    }

    foreach ($evenements as $jour => $liste) {
        usort($liste, static fn (array $a, array $b): int => $a['annee'] <=> $b['annee']);
        $evenements[$jour] = $liste;
    }
    ksort($evenements);

    return $evenements;
}

/**
 * Les événements de l'arbre dans les prochains jours, du plus proche au plus
 * lointain (naissances, mariages et décès), pour les personnes visibles par la
 * personne connectée — les droits sont ceux de calendrier_evenements().
 *
 * @return list<array{genre:string, jour:int, mois:int, annee:int, ans:int, nom:string, lien:string, date:string}>
 */
function calendrier_prochains(int $jours = 30, int $limite = 40): array
{
    $jours      = max(1, $jours);
    $aujourdhui = new DateTimeImmutable('today');
    $fin        = $aujourdhui->modify('+' . $jours . ' days');
    $prochains  = [];

    // Le mois d'aujourd'hui et, si la fenêtre le dépasse, le mois suivant.
    $mois = [(int) $aujourdhui->format('n')];
    if ((int) $fin->format('n') !== $mois[0]) {
        $mois[] = (int) $fin->format('n');
    }

    foreach ($mois as $m) {
        // Année courante ou suivante selon le mois (passage décembre → janvier).
        $annee = $m < (int) $aujourdhui->format('n') ? (int) $aujourdhui->format('Y') + 1 : (int) $aujourdhui->format('Y');
        foreach (calendrier_evenements($m) as $jour => $liste) {
            try {
                $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $annee, $m, (int) $jour));
            } catch (Throwable) {
                continue;
            }
            if ($date < $aujourdhui || $date > $fin) {
                continue;
            }
            foreach ($liste as $evenement) {
                $ans = $annee - (int) $evenement['annee'];
                if ($evenement['genre'] === 'evenement') {
                    // Événement de famille : pas de personne à citer.
                    $prochains[] = [
                        'genre' => 'evenement',
                        'jour'  => (int) $jour,
                        'mois'  => $m,
                        'annee' => $annee,
                        'ans'   => 0,
                        'nom'   => (string) ($evenement['titre'] ?? ''),
                        'lien'  => '/calendrier?mois=' . sprintf('%04d-%02d', $annee, $m),
                        'date'  => $date->format('Y-m-d'),
                    ];
                    continue;
                }
                if ($evenement['genre'] === 'mariage') {
                    $nom  = person_full_name($evenement['epoux']) . ' et ' . person_full_name($evenement['epouse']);
                    $lien = '/arbre/' . (int) $evenement['epoux']['id'];
                } else {
                    $nom  = person_full_name($evenement['personne']);
                    $lien = '/arbre/' . (int) $evenement['personne']['id'];
                }
                $prochains[] = [
                    'genre' => (string) $evenement['genre'],
                    'jour'  => (int) $jour,
                    'mois'  => $m,
                    'annee' => $annee,
                    'ans'   => max(0, $ans),
                    'nom'   => $nom,
                    'lien'  => $lien,
                    'date'  => $date->format('Y-m-d'),
                ];
            }
        }
    }

    usort(
        $prochains,
        static fn (array $a, array $b): int => [$a['date'], $a['nom']] <=> [$b['date'], $b['nom']]
    );

    return array_slice($prochains, 0, max(1, $limite));
}

/** Date lisible d'un événement proche : « 12 novembre ». */
function calendrier_jour_lisible(array $evenement): string
{
    $horodatage = strtotime((string) ($evenement['date'] ?? '') . ' 12:00:00');
    return $horodatage === false ? '' : french_day_month($horodatage);
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
