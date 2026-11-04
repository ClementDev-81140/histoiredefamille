<?php
/**
 * Carte des lieux de la famille — dessinée par le serveur, sans service
 * externe, sans JavaScript et sans une seule requête réseau.
 *
 * Principes :
 *
 *  - le fond de carte est un fichier embarqué (app/data/carte_fond.php),
 *    fabriqué à partir de Natural Earth (domaine public) — voir docs/carte.md ;
 *  - la projection est une simple équirectangulaire, calculée en PHP, la même
 *    que celle qui a servi à fabriquer le fond : les points tombent juste ;
 *  - un lieu n'apparaît que s'il contient au moins une personne **visible**
 *    pour la personne qui regarde (visibility_clause sur les personnes) ;
 *  - les textes de lieu du site ne sont jamais modifiés : un texte sans
 *    coordonnées est simplement ignoré sur la carte (il reste dans les fiches) ;
 *  - le résultat est mis en cache quelques minutes, par groupe de droits et par
 *    filtres, pour que le Raspberry Pi reste vif.
 */

declare(strict_types=1);

/** Durée du cache (secondes). Court : une personne ajoutée apparaît vite. */
const CARTE_TTL = 300;

/** Rayon des points (pixels de la vue), selon le nombre de personnes. */
const CARTE_RAYON_MIN = 4.0;
const CARTE_RAYON_MAX = 15.0;

/** Nombre de suggestions de communes renvoyées. */
const CARTE_SUGGESTIONS_MAX = 10;

// ---------------------------------------------------------------------------
// Fond de carte et projection
// ---------------------------------------------------------------------------

/**
 * Fond embarqué (chargé une seule fois par requête).
 *
 * @return array{source: string, vues: array<string, array>}
 */
function carte_fond(): array
{
    static $fond = null;

    if ($fond === null) {
        /** @var array $charge */
        $charge = require dirname(__DIR__) . '/data/carte_fond.php';
        $fond   = $charge;
    }

    return $fond;
}

/** Une vue du fond (« france » ou « monde »). */
function carte_vue(string $vue): array
{
    $vues = carte_fond()['vues'] ?? [];
    $choisie = $vues[$vue] ?? $vues['france'] ?? null;

    return $choisie ?? [
        'lon_min' => -180.0, 'lon_max' => 180.0, 'lat_min' => -58.0, 'lat_max' => 84.0,
        'largeur' => 1000, 'hauteur' => 400, 'trace' => '',
    ];
}

/** Les vues proposées (libellé lisible pour la page). */
function carte_vues(): array
{
    return ['france' => 'France', 'monde' => 'Monde entier'];
}

/**
 * Projette un point géographique dans une vue.
 *
 * @return array{x: float, y: float, dedans: bool}
 */
function carte_projeter(string $vue, float $lat, float $lon): array
{
    $v = carte_vue($vue);

    $largeurLon = (float) $v['lon_max'] - (float) $v['lon_min'];
    $hauteurLat = (float) $v['lat_max'] - (float) $v['lat_min'];
    if ($largeurLon <= 0.0 || $hauteurLat <= 0.0) {
        return ['x' => 0.0, 'y' => 0.0, 'dedans' => false];
    }

    $x = (($lon - (float) $v['lon_min']) / $largeurLon) * (float) $v['largeur'];
    $y = (((float) $v['lat_max'] - $lat) / $hauteurLat) * (float) $v['hauteur'];

    $dedans = $lat >= (float) $v['lat_min'] && $lat <= (float) $v['lat_max']
        && $lon >= (float) $v['lon_min'] && $lon <= (float) $v['lon_max'];

    return ['x' => round($x, 2), 'y' => round($y, 2), 'dedans' => $dedans];
}

/** Coordonnées valides ? (aucune coordonnée inventée n'entre en base) */
function carte_coordonnees_valides(mixed $lat, mixed $lon): bool
{
    if (!is_numeric($lat) || !is_numeric($lon)) {
        return false;
    }
    $lat = (float) $lat;
    $lon = (float) $lon;

    return is_finite($lat) && is_finite($lon)
        && $lat >= -90.0 && $lat <= 90.0
        && $lon >= -180.0 && $lon <= 180.0;
}

// ---------------------------------------------------------------------------
// Noms de lieux
// ---------------------------------------------------------------------------

/**
 * Clé de comparaison d'un nom de lieu : sans accent, sans casse, sans
 * ponctuation superflue. « Saint-Étienne », « SAINT ETIENNE » et
 * «  saint-étienne » se retrouvent ainsi sur la même fiche.
 */
function carte_normaliser(string $texte): string
{
    $texte = trim($texte);
    if ($texte === '') {
        return '';
    }
    $texte = str_replace(['’', '‘'], "'", $texte);

    // Sans accents (les lieux de famille sont souvent saisis sans soin).
    $converti = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
    if ($converti !== false && $converti !== '') {
        $texte = $converti;
    }

    $texte = mb_strtolower($texte, 'UTF-8');
    $texte = preg_replace('/[^a-z0-9]+/u', ' ', $texte) ?? '';
    $texte = trim(preg_replace('/\s+/', ' ', $texte) ?? '');

    return $texte;
}

/** Tous les lieux placés, indexés par nom normalisé.
 *
 * La liste est gardée en mémoire pour la durée de la page. Passer true la
 * relit depuis la base : après avoir placé ou retiré un lieu dans la même
 * requête, et dans les tests.
 */
function carte_places(bool $relire = false): array
{
    static $cache = null;
    if ($relire) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    foreach (db_all('SELECT * FROM places ORDER BY nom COLLATE NOCASE') as $ligne) {
        $cle = carte_normaliser((string) $ligne['nom']);
        if ($cle !== '' && !isset($cache[$cle])) {
            $cache[$cle] = $ligne;
        }
    }

    return $cache;
}

/** Un lieu placé par son identifiant. */
function carte_place(int $id): ?array
{
    return db_one('SELECT * FROM places WHERE id = ?', [$id]);
}

/** Un lieu placé à partir d'un texte libre (ou null s'il n'est pas placé). */
function carte_place_du_texte(string $texte): ?array
{
    $cle = carte_normaliser($texte);
    if ($cle === '') {
        return null;
    }
    $places = carte_places();

    return $places[$cle] ?? null;
}

/**
 * Crée un lieu, ou met à jour ses coordonnées s'il existe déjà.
 *
 * @return int identifiant du lieu (0 si les coordonnées sont invalides)
 */
function carte_place_enregistrer(string $nom, string $pays, mixed $lat, mixed $lon, string $code = ''): int
{
    $nom  = trim(preg_replace('/\s+/u', ' ', $nom) ?? '');
    $pays = trim(preg_replace('/\s+/u', ' ', $pays) ?? '');
    if ($pays === '') {
        $pays = 'France';
    }
    if ($nom === '' || mb_strlen($nom) > 120 || mb_strlen($pays) > 80) {
        return 0;
    }
    if (!carte_coordonnees_valides($lat, $lon)) {
        return 0;
    }
    $lat = round((float) $lat, 4);
    $lon = round((float) $lon, 4);

    $existant = db_one('SELECT * FROM places WHERE nom = ? COLLATE NOCASE AND pays = ? COLLATE NOCASE', [$nom, $pays]);
    if ($existant === null) {
        $existant = carte_place_du_texte($nom);
    }

    $maintenant = time();
    if ($existant !== null) {
        db_update('places', (int) $existant['id'], [
            'nom' => $nom, 'pays' => $pays, 'lat' => $lat, 'lon' => $lon,
            'code' => mb_substr($code, 0, 20), 'updated_at' => $maintenant,
        ]);
        carte_vider_cache();
        carte_places(true);

        return (int) $existant['id'];
    }

    $id = db_insert('places', [
        'nom' => $nom, 'pays' => $pays, 'lat' => $lat, 'lon' => $lon,
        'code' => mb_substr($code, 0, 20), 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    carte_vider_cache();
    carte_places(true);

    return $id;
}

/** Y a-t-il au moins un lieu placé ? (pour le menu) */
function carte_exist(bool $relire = false): bool
{
    static $existe = null;
    if ($relire) {
        $existe = null;
    }
    if ($existe !== null) {
        return $existe;
    }

    $existe = (int) db_value('SELECT COUNT(*) FROM places', [], 0) > 0;

    return $existe;
}

/** Supprime un lieu : les textes des fiches ne bougent pas, il quitte la carte. */
function carte_place_supprimer(int $id): void
{
    db_exec('DELETE FROM places WHERE id = ?', [$id]);
    carte_vider_cache();
    carte_places(true);
}

// ---------------------------------------------------------------------------
// Lieux en attente
// ---------------------------------------------------------------------------

/**
 * Textes de lieu du site qui n'ont pas encore de coordonnées.
 *
 * @return list<array{nom: string, personnes: int}>
 */
function carte_lieux_a_placer(): array
{
    $textes = [];

    foreach (db_all(
        "SELECT birth_place AS lieu, COUNT(*) AS n FROM persons
         WHERE birth_place IS NOT NULL AND TRIM(birth_place) <> ''
         GROUP BY birth_place"
    ) as $ligne) {
        $nom = trim((string) $ligne['lieu']);
        $textes[$nom] = ($textes[$nom] ?? 0) + (int) $ligne['n'];
    }
    foreach (db_all(
        "SELECT death_place AS lieu, COUNT(*) AS n FROM persons
         WHERE death_place IS NOT NULL AND TRIM(death_place) <> ''
         GROUP BY death_place"
    ) as $ligne) {
        $nom = trim((string) $ligne['lieu']);
        $textes[$nom] = ($textes[$nom] ?? 0) + (int) $ligne['n'];
    }
    foreach (db_all(
        "SELECT marriage_place AS lieu, COUNT(*) AS n FROM families
         WHERE marriage_place IS NOT NULL AND TRIM(marriage_place) <> ''
         GROUP BY marriage_place"
    ) as $ligne) {
        $nom = trim((string) $ligne['lieu']);
        $textes[$nom] = ($textes[$nom] ?? 0) + (int) $ligne['n'];
    }

    $aPlacer = [];
    foreach ($textes as $nom => $nombre) {
        if (carte_place_du_texte($nom) === null) {
            $aPlacer[] = ['nom' => $nom, 'personnes' => $nombre];
        }
    }

    usort($aPlacer, static fn (array $a, array $b): int => [$b['personnes'], $a['nom']] <=> [$a['personnes'], $b['nom']]);

    return $aPlacer;
}

// ---------------------------------------------------------------------------
// Suggestions de communes (liste embarquée, aucune requête réseau)
// ---------------------------------------------------------------------------

/**
 * Communes françaises dont le nom contient la recherche.
 *
 * @return list<array{nom: string, lat: float, lon: float, departement: string}>
 */
function carte_suggestions(string $recherche, int $limite = CARTE_SUGGESTIONS_MAX): array
{
    $recherche = carte_normaliser($recherche);
    if (mb_strlen($recherche) < 2) {
        return [];
    }

    $resultats = [];
    foreach (carte_communes() as $commune) {
        $position = mb_strpos($commune['cle'], $recherche);
        if ($position === false) {
            continue;
        }
        $commune['debut'] = $position;
        $resultats[] = $commune;
        if (count($resultats) >= 400) {
            break;
        }
    }

    // Les noms qui commencent par la recherche d'abord, les plus courts ensuite.
    usort($resultats, static fn (array $a, array $b): int => [$a['debut'], mb_strlen($a['nom']), $a['nom']]
        <=> [$b['debut'], mb_strlen($b['nom']), $b['nom']]);

    $resultats = array_slice($resultats, 0, max(1, min(50, $limite)));

    return array_map(static fn (array $c): array => [
        'nom'        => $c['nom'],
        'lat'        => $c['lat'],
        'lon'        => $c['lon'],
        'departement' => $c['departement'],
    ], $resultats);
}

/**
 * Liste embarquée des communes (fichier TSV, chargé une seule fois).
 *
 * @return list<array{nom: string, cle: string, lat: float, lon: float, departement: string}>
 */
function carte_communes(): array
{
    static $communes = null;
    if ($communes !== null) {
        return $communes;
    }

    $communes = [];
    $fichier  = dirname(__DIR__) . '/data/communes-fr.tsv';
    if (!is_file($fichier)) {
        return $communes;
    }
    $flux = @fopen($fichier, 'r');
    if ($flux === false) {
        return $communes;
    }
    while (($ligne = fgets($flux)) !== false) {
        $morceaux = explode("\t", rtrim($ligne, "\r\n"));
        if (count($morceaux) < 3) {
            continue;
        }
        $nom = trim($morceaux[0]);
        $communes[] = [
            'nom'         => $nom,
            'cle'         => carte_normaliser($nom),
            'lat'         => (float) $morceaux[1],
            'lon'         => (float) $morceaux[2],
            'departement' => (string) ($morceaux[3] ?? ''),
        ];
    }
    fclose($flux);

    return $communes;
}

// ---------------------------------------------------------------------------
// Données de la carte (personnes visibles seulement)
// ---------------------------------------------------------------------------

/** Filtres lus dans l'adresse (GET), toujours validés. */
function carte_filtres(): array
{
    $branche  = (int) ($_GET['branche'] ?? 0);
    $decennie = (int) ($_GET['decennie'] ?? 0);
    $vue      = (string) ($_GET['vue'] ?? 'france');
    $lieu     = trim((string) ($_GET['lieu'] ?? ''));

    if (!array_key_exists($vue, carte_vues())) {
        $vue = 'france';
    }
    if ($decennie > 0 && ($decennie < 1000 || $decennie % 10 !== 0)) {
        $decennie = 0;
    }

    return [
        'branche'  => $branche > 0 ? $branche : 0,
        'decennie' => $decennie,
        'vue'      => $vue,
        'lieu'     => mb_substr($lieu, 0, 120),
    ];
}

/** Décennies de naissance présentes dans l'arbre (pour le filtre). */
function carte_decennies(): array
{
    $lignes = db_all(
        "SELECT DISTINCT (birth_year / 10) * 10 AS decennie
         FROM persons WHERE birth_year IS NOT NULL AND birth_year > 1000
         ORDER BY decennie"
    );

    return array_map(static fn (array $l): int => (int) $l['decennie'], $lignes);
}

/** Clé de cache : groupe de droits + filtres + version des données. */
function carte_cle_cache(array $filtres): string
{
    $groupe = groupe_droits(current_account());

    return hash('sha256', json_encode([
        'groupe'   => $groupe,
        'filtres'  => [$filtres['branche'], $filtres['decennie']],
        'version'  => carte_version(),
    ], JSON_UNESCAPED_UNICODE) ?: '');
}

/** Empreinte des données : change dès qu'une personne ou un lieu change. */
function carte_version(): int
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }

    $personnes = (int) db_value('SELECT COALESCE(MAX(updated_at), 0) FROM persons', [], 0);
    $nombre    = (int) db_value('SELECT COUNT(*) FROM persons', [], 0);
    $lieux     = (int) db_value('SELECT COALESCE(MAX(updated_at), 0) FROM places', [], 0);

    $version = $personnes + $nombre + $lieux;

    return $version;
}

/** Où ranger le cache : storage/cache/carte. */
function carte_dossier_cache(): string
{
    $racine = rtrim((string) config('storage.root'), '/');
    return $racine . '/cache/carte';
}

/** Vide le cache de la carte (appelé dès qu'un lieu change). */
function carte_vider_cache(): void
{
    $dossier = carte_dossier_cache();
    foreach (glob($dossier . '/*.json') ?: [] as $fichier) {
        @unlink($fichier);
    }
}

/**
 * Agrège les lieux du site, vus par la personne connectée.
 *
 * Aucune coordonnée n'est calculée ici : on ne fait que compter des personnes
 * visibles par texte de lieu, puis rattacher ces textes aux lieux placés.
 *
 * @return array{points: list<array>, sans_lieu: int, total: int}
 */
function carte_agreger(array $filtres): array
{
    $params  = [];
    $clause  = visibility_clause('person', 'p', $params);
    $filtre  = '';

    if ($filtres['branche'] > 0) {
        $filtre .= " AND EXISTS (SELECT 1 FROM content_branches cb
                     WHERE cb.kind = 'person' AND cb.content_id = p.id
                       AND cb.branch_id = :carte_branche)";
        $params[':carte_branche'] = (int) $filtres['branche'];
    }
    if ($filtres['decennie'] > 0) {
        $filtre .= ' AND p.birth_year >= :carte_debut AND p.birth_year < :carte_fin';
        $params[':carte_debut'] = (int) $filtres['decennie'];
        $params[':carte_fin']   = (int) $filtres['decennie'] + 10;
    }

    $compteurs = [];
    $ajouter = static function (string $texte, int $nombre) use (&$compteurs): void {
        $cle = carte_normaliser($texte);
        if ($cle === '') {
            return;
        }
        $compteurs[$cle] = ($compteurs[$cle] ?? 0) + $nombre;
    };

    // 1. Lieux de naissance.
    foreach (db_all(
        "SELECT p.birth_place AS lieu, COUNT(*) AS n FROM persons p
         WHERE {$clause} AND p.birth_place IS NOT NULL AND TRIM(p.birth_place) <> ''{$filtre}
         GROUP BY p.birth_place",
        $params
    ) as $ligne) {
        $ajouter((string) $ligne['lieu'], (int) $ligne['n']);
    }

    // 2. Lieux de décès.
    foreach (db_all(
        "SELECT p.death_place AS lieu, COUNT(*) AS n FROM persons p
         WHERE {$clause} AND p.death_place IS NOT NULL AND TRIM(p.death_place) <> ''{$filtre}
         GROUP BY p.death_place",
        $params
    ) as $ligne) {
        $ajouter((string) $ligne['lieu'], (int) $ligne['n']);
    }

    // 3. Lieux de mariage : seulement si l'un des deux époux est visible.
    //
    //    On ne construit pas une condition SQL compliquée (les deux époux sont
    //    dans la même table, et un filtre sur l'un ne doit pas s'appliquer à
    //    l'autre) : on demande d'abord la liste des personnes visibles, puis on
    //    garde les couples dont l'un des deux en fait partie. Une seule requête
    //    de plus, et la règle reste exactement celle du site.
    $paramsVisibles = [];
    $clauseVisibles = visibility_clause('person', 'p', $paramsVisibles);
    $filtreVisibles = '';
    if ($filtres['branche'] > 0) {
        $filtreVisibles .= " AND EXISTS (SELECT 1 FROM content_branches cb
                              WHERE cb.kind = 'person' AND cb.content_id = p.id
                                AND cb.branch_id = :carte_branche_v)";
        $paramsVisibles[':carte_branche_v'] = (int) $filtres['branche'];
    }
    if ($filtres['decennie'] > 0) {
        $filtreVisibles .= ' AND p.birth_year >= :carte_debut_v AND p.birth_year < :carte_fin_v';
        $paramsVisibles[':carte_debut_v'] = (int) $filtres['decennie'];
        $paramsVisibles[':carte_fin_v']   = (int) $filtres['decennie'] + 10;
    }
    $visibles = [];
    foreach (db_all("SELECT p.id FROM persons p WHERE {$clauseVisibles}{$filtreVisibles}", $paramsVisibles) as $ligne) {
        $visibles[(int) $ligne['id']] = true;
    }

    $mariages = [];
    foreach (db_all(
        "SELECT marriage_place AS lieu, husband_id, wife_id FROM families
         WHERE marriage_place IS NOT NULL AND TRIM(marriage_place) <> ''"
    ) as $ligne) {
        $epoux  = (int) ($ligne['husband_id'] ?? 0);
        $epouse = (int) ($ligne['wife_id'] ?? 0);
        if (($epoux === 0 || !isset($visibles[$epoux])) && ($epouse === 0 || !isset($visibles[$epouse]))) {
            continue;
        }
        $cle = carte_normaliser((string) $ligne['lieu']);
        if ($cle === '') {
            continue;
        }
        $mariages[$cle] = ($mariages[$cle] ?? 0) + 1;
    }
    foreach ($mariages as $cle => $nombre) {
        $ajouter($cle, $nombre);
    }

    // 4. Rattachement aux lieux placés (les textes inconnus sont ignorés).
    $places  = carte_places();
    $points  = [];
    $sansLieu = 0;
    foreach ($compteurs as $cle => $nombre) {
        if (!isset($places[$cle])) {
            $sansLieu += $nombre;
            continue;
        }
        $place = $places[$cle];
        if (!carte_coordonnees_valides($place['lat'], $place['lon'])) {
            $sansLieu += $nombre;
            continue;
        }
        $points[] = [
            'id'        => (int) $place['id'],
            'nom'       => (string) $place['nom'],
            'pays'      => (string) $place['pays'],
            'lat'       => (float) $place['lat'],
            'lon'       => (float) $place['lon'],
            'personnes' => $nombre,
        ];
    }

    usort($points, static fn (array $a, array $b): int => [$b['personnes'], $a['nom']] <=> [$a['personnes'], $b['nom']]);

    $total = 0;
    foreach ($points as $point) {
        $total += $point['personnes'];
    }

    return ['points' => $points, 'sans_lieu' => $sansLieu, 'total' => $total];
}

/**
 * Données prêtes à dessiner (avec cache court par groupe de droits).
 *
 * @return array{points: list<array>, sans_lieu: int, total: int, cache: bool}
 */
function carte_donnees(array $filtres, bool $rafraichir = false): array
{
    $dossier = carte_dossier_cache();
    $fichier = $dossier . '/' . carte_cle_cache($filtres) . '.json';

    if (!$rafraichir && is_file($fichier) && (time() - (int) @filemtime($fichier)) < CARTE_TTL) {
        $brut = @file_get_contents($fichier);
        if ($brut !== false) {
            $donnees = json_decode($brut, true);
            if (is_array($donnees) && isset($donnees['points'])) {
                $donnees['cache'] = true;
                return $donnees;
            }
        }
    }

    $donnees = carte_agreger($filtres);
    $donnees['cache'] = false;

    if (!is_dir($dossier)) {
        @mkdir($dossier, 0775, true);
    }
    // JSON_PRESERVE_ZERO_FRACTION : une latitude de 46.0 doit revenir en
    // nombre décimal (46.0) et non en entier (46), sinon les données relues ne
    // sont plus tout à fait celles qui ont été calculées.
    @file_put_contents(
        $fichier,
        json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
        LOCK_EX
    );

    return $donnees;
}

// ---------------------------------------------------------------------------
// Dessin
// ---------------------------------------------------------------------------

/** Rayon d'un point, du plus petit au plus grand selon le nombre de personnes. */
function carte_rayon(int $personnes, int $maximum): float
{
    if ($maximum <= 0) {
        return CARTE_RAYON_MIN;
    }
    $part = min(1.0, max(0.0, $personnes / $maximum));

    return round(CARTE_RAYON_MIN + (CARTE_RAYON_MAX - CARTE_RAYON_MIN) * sqrt($part), 2);
}

/**
 * Le SVG complet de la carte.
 *
 * Aucun attribut style : les couleurs viennent des variables CSS du site.
 * Aucun script, aucune ressource externe, aucune image.
 */
function carte_svg(array $donnees, string $vue, string $lieuActif = ''): string
{
    $v       = carte_vue($vue);
    $largeur = (int) $v['largeur'];
    $hauteur = (int) $v['hauteur'];
    $maximum = 0;
    foreach ($donnees['points'] as $point) {
        $maximum = max($maximum, (int) $point['personnes']);
    }

    $lieuCle = carte_normaliser($lieuActif);

    $e = static fn (string $texte): string => htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $svg  = '<svg class="carte-lieux" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '"'
        . ' role="img" aria-labelledby="carte-titre carte-desc" preserveAspectRatio="xMidYMid meet">';
    $svg .= '<title id="carte-titre">Carte des lieux de la famille</title>';
    $svg .= '<desc id="carte-desc">' . $e(
        $donnees['points'] === []
            ? 'Aucun lieu placé pour le moment.'
            : count($donnees['points']) . ' lieu(x) placé(s) et ' . (int) $donnees['total']
                . ' personne(s) visible(s). Le tableau sous la carte donne le détail.'
    ) . '</desc>';

    $svg .= '<rect class="carte-lieux__fond" x="0" y="0" width="' . $largeur . '" height="' . $hauteur . '"></rect>';
    $svg .= '<path class="carte-lieux__terre" d="' . $e((string) $v['trace']) . '"></path>';

    $svg .= '<g class="carte-lieux__points">';
    foreach ($donnees['points'] as $point) {
        $position = carte_projeter($vue, (float) $point['lat'], (float) $point['lon']);
        if (!$position['dedans']) {
            continue;
        }
        $rayon = carte_rayon((int) $point['personnes'], $maximum);
        $actif = $lieuCle !== '' && carte_normaliser((string) $point['nom']) === $lieuCle;
        $classe = 'carte-lieux__point' . ($actif ? ' carte-lieux__point--actif' : '');
        $titre  = $point['nom'] . ' — ' . (int) $point['personnes'] . ' personne'
            . ((int) $point['personnes'] > 1 ? 's' : '');

        $svg .= '<a class="carte-lieux__lien" href="/lieu/' . (int) $point['id'] . '">'
            . '<circle class="' . $classe . '" cx="' . $position['x'] . '" cy="' . $position['y']
            . '" r="' . $rayon . '"><title>' . $e($titre) . '</title></circle>'
            . '</a>';
    }
    $svg .= '</g></svg>';

    return $svg;
}

/**
 * Tableau de secours (lisible sans voir la carte, et par un lecteur d'écran).
 *
 * @return list<array{nom: string, pays: string, personnes: int, url: string}>
 */
function carte_tableau(array $donnees): array
{
    return array_map(static fn (array $point): array => [
        'nom'       => (string) $point['nom'],
        'pays'      => (string) $point['pays'],
        'personnes' => (int) $point['personnes'],
        'url'       => '/lieu/' . (int) $point['id'],
    ], $donnees['points']);
}

// ---------------------------------------------------------------------------
// Fiche d'un lieu
// ---------------------------------------------------------------------------

/**
 * Mariages visibles célébrés dans un lieu.
 *
 * @return list<array{epoux: string, epouse: string, date: string}>
 */
function carte_mariages_du_lieu(array $place): array
{
    $cle = carte_normaliser((string) $place['nom']);
    if ($cle === '') {
        return [];
    }

    $mariages = [];
    foreach (db_all('SELECT * FROM families ORDER BY marriage_date DESC, id DESC') as $famille) {
        if (carte_normaliser((string) $famille['marriage_place']) !== $cle) {
            continue;
        }
        $epoux  = $famille['husband_id'] !== null ? find_viewable_person((int) $famille['husband_id']) : null;
        $epouse = $famille['wife_id'] !== null ? find_viewable_person((int) $famille['wife_id']) : null;
        if ($epoux === null && $epouse === null) {
            continue;
        }
        $mariages[] = [
            'epoux'  => $epoux !== null ? person_full_name($epoux) : '',
            'epouse' => $epouse !== null ? person_full_name($epouse) : '',
            'date'   => trim((string) $famille['marriage_date']),
        ];
    }

    return $mariages;
}

/**
 * Personnes visibles rattachées à un lieu (naissance, décès ou mariage).
 *
 * @return list<array{id: int, nom: string, quoi: string, annee: string}>
 */
function carte_personnes_du_lieu(array $place): array
{
    $cle = carte_normaliser((string) $place['nom']);
    if ($cle === '') {
        return [];
    }

    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $lignes = db_all(
        "SELECT p.*, p.birth_place AS lieu_naissance, p.death_place AS lieu_deces
         FROM persons p WHERE {$clause}
         ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE",
        $params
    );

    $personnes = [];
    foreach ($lignes as $personne) {
        $quoi = [];
        if (carte_normaliser((string) $personne['birth_place']) === $cle) {
            $quoi[] = 'Naissance';
        }
        if (carte_normaliser((string) $personne['death_place']) === $cle) {
            $quoi[] = 'Décès';
        }
        if ($quoi === []) {
            continue;
        }
        $annee = (string) ($personne['birth_year'] ?? '');
        $personnes[] = [
            'id'    => (int) $personne['id'],
            'nom'   => person_full_name($personne),
            'quoi'  => implode(' et ', $quoi),
            'annee' => $annee,
        ];
    }

    return $personnes;
}
