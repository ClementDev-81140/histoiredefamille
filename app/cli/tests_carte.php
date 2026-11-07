<?php
/**
 * Tests de la carte des lieux (lot 3).
 *
 * Trois familles :
 *   1. la projection et les coordonnées (aucune base) ;
 *   2. les noms de lieux et les suggestions de communes (fichier embarqué) ;
 *   3. les droits, les filtres, le cache et la fiche d'un lieu — dans une
 *      transaction annulée : la base du site est rendue intacte.
 *
 * Aucun accès réseau : la carte n'en fait jamais.
 */

declare(strict_types=1);

/** Personne fictive pour les tests de la carte. */
function tests_carte_personne(array $champs): int
{
    $maintenant = time();

    return db_insert('persons', array_merge([
        'first_name' => 'Essai',
        'last_name'  => 'Carte',
        'sex'        => null,
        'birth_year' => null,
        'birth_place' => null,
        'death_place' => null,
        'visibility' => 'public',
        'created_at' => $maintenant,
        'updated_at' => $maintenant,
    ], $champs));
}

/** Un lieu placé, nommé de façon unique pour ne gêner aucun autre test. */
function tests_carte_lieu(string $nom, float $lat = 44.0, float $lon = 1.0): int
{
    $id = carte_place_enregistrer($nom, 'France', $lat, $lon);

    return (int) $id;
}

/** Le point correspondant à un nom de lieu dans un résultat d'agrégation. */
function tests_carte_point(array $donnees, string $nom): ?array
{
    $cle = carte_normaliser($nom);
    foreach ($donnees['points'] as $point) {
        if (carte_normaliser((string) $point['nom']) === $cle) {
            return $point;
        }
    }

    return null;
}

/**
 * Compte de famille pour les essais de la carte (1.31.0).
 *
 * La carte et les fiches de lieux ne s'ouvrent plus à un visiteur : il faut un
 * compte connecté pour vérifier qu'une fois connecté, tout fonctionne comme
 * avant. Le compte est créé dans la transaction annulée du test.
 */
function tests_carte_compte_famille(): int
{
    return db_insert('accounts', [
        'username'      => 'carte-essai-' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai carte',
        'password_hash' => password_make('MotDePasseEssai123!'),
        'role'          => 'famille',
        'account_type'  => 'branche',
        'first_name'    => '',
        'last_name'     => '',
        'branch_id'     => null,
        'see_all'       => 0,
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
}

function tests_carte(): void
{
    echo "Carte des lieux — projection, communes, droits, filtres, cache, dessin\n";

    // -----------------------------------------------------------------------
    // 1. Projection et coordonnées
    // -----------------------------------------------------------------------
    $france = carte_vue('france');
    $monde  = carte_vue('monde');

    verifier(
        'carte : les deux vues sont proposées, et une vue inconnue retombe sur la France',
        array_keys(carte_vues()) === ['france', 'monde']
            && carte_vue('n’importe quoi') === $france
    );
    verifier(
        'carte : le fond embarqué est bien là (France et monde, avec un tracé)',
        strlen((string) $france['trace']) > 1000 && strlen((string) $monde['trace']) > 1000
            && $france['largeur'] > 0 && $monde['hauteur'] > 0
    );
    verifier(
        'carte : le fond tient dans le budget annoncé (moins de 300 Ko à deux vues)',
        strlen((string) $france['trace']) + strlen((string) $monde['trace']) < 300 * 1024,
        strlen((string) $france['trace']) + strlen((string) $monde['trace']) . ' octets'
    );

    // Régression : un anneau qui passe le méridien 180 se replie sur lui-même
    // et laisse un trait horizontal en travers de la carte (Chukotka, Fidji).
    // Chaque tracé est donc vérifié : aucun segment ne doit traverser la vue.
    $traversant = [];
    foreach (['france' => $france, 'monde' => $monde] as $nomVue => $vue) {
        foreach (explode('Z', (string) $vue['trace']) as $sousChemin) {
            preg_match_all('/([ML])\s*(-?[\d.]+)\s+(-?[\d.]+)/', $sousChemin, $points, PREG_SET_ORDER);
            for ($i = 1; $i < count($points); $i++) {
                $dx = abs((float) $points[$i][2] - (float) $points[$i - 1][2]);
                $dy = abs((float) $points[$i][3] - (float) $points[$i - 1][3]);
                // Un segment posé sur le bord du cadre est normal : c'est la
                // terre qui touche le bord de la vue.
                $surLeBord = abs((float) $points[$i][3]) < 1.5
                    || abs((float) $points[$i][3] - (float) $vue['hauteur']) < 1.5;
                if (!$surLeBord && $dx > 0.3 * (float) $vue['largeur'] && $dy < 2.0) {
                    $traversant[] = $nomVue . ' : ' . round($dx) . ' px';
                }
            }
        }
    }
    verifier(
        'carte : aucun tracé ne traverse la carte (passage du méridien 180 coupé)',
        $traversant === [],
        implode(', ', array_slice($traversant, 0, 5))
    );

    // Les bords du cadre tombent exactement sur les bords de la vue.
    $hautGauche = carte_projeter('france', (float) $france['lat_max'], (float) $france['lon_min']);
    $basDroite  = carte_projeter('france', (float) $france['lat_min'], (float) $france['lon_max']);
    verifier(
        'carte : le coin haut-gauche et le coin bas-droit tombent sur les bords du dessin',
        $hautGauche['x'] === 0.0 && $hautGauche['y'] === 0.0
            && $basDroite['x'] === (float) $france['largeur']
            && $basDroite['y'] === (float) $france['hauteur']
            && $hautGauche['dedans'] && $basDroite['dedans']
    );

    $toulouse = carte_projeter('france', 43.6047, 1.4442);
    verifier(
        'carte : Toulouse tombe au milieu de la vue France',
        $toulouse['dedans']
            && $toulouse['x'] > 300 && $toulouse['x'] < 600
            && $toulouse['y'] > 400 && $toulouse['y'] < 620,
        'x = ' . $toulouse['x'] . ', y = ' . $toulouse['y']
    );

    verifier(
        'carte : un point hors du cadre est signalé comme dehors (et non dessiné)',
        carte_projeter('france', 43.6047, 40.0)['dedans'] === false
            && carte_projeter('france', 10.0, 1.4442)['dedans'] === false
            && carte_projeter('monde', 43.6047, 1.4442)['dedans'] === true
            && carte_projeter('monde', 0.0, 0.0)['dedans'] === true
    );

    verifier(
        'carte : les bords extrêmes du monde restent dans le dessin',
        carte_projeter('monde', 84.0, -180.0)['dedans']
            && carte_projeter('monde', -58.0, 180.0)['dedans']
            && carte_projeter('monde', 85.0, 0.0)['dedans'] === false
    );

    verifier(
        'carte : des coordonnées invalides sont refusées',
        carte_coordonnees_valides('abc', '1') === false
            && carte_coordonnees_valides('', '') === false
            && carte_coordonnees_valides('43,6', '1') === false
            && carte_coordonnees_valides('91', '1') === false
            && carte_coordonnees_valides('1', '-181') === false
            && carte_coordonnees_valides(null, null) === false
    );
    verifier(
        'carte : les coordonnées limites (90 et 180) sont acceptées',
        carte_coordonnees_valides('90', '180')
            && carte_coordonnees_valides('-90', '-180')
            && carte_coordonnees_valides('43.6047', '1.4442')
    );

    // -----------------------------------------------------------------------
    // 2. Noms de lieux et suggestions de communes
    // -----------------------------------------------------------------------
    verifier(
        'carte : accents, casse et ponctuation ne changent pas le nom d’un lieu',
        carte_normaliser('Saint-Étienne') === carte_normaliser('  SAINT ETIENNE ')
            && carte_normaliser('Saint-Étienne') === carte_normaliser('saint-étienne')
            && carte_normaliser('L’Isle-Jourdain') === carte_normaliser('l isle jourdain')
    );
    verifier(
        'carte : un texte vide n’a pas de nom',
        carte_normaliser('') === '' && carte_normaliser('   ') === ''
    );

    $communes = carte_communes();
    verifier(
        'carte : la liste des communes est embarquée (plus de 8 000 communes de France)',
        count($communes) > 8000,
        count($communes) . ' commune(s)'
    );
    $coordonneesCommunes = true;
    foreach (array_slice($communes, 0, 500) as $commune) {
        if (!carte_coordonnees_valides($commune['lat'], $commune['lon'])) {
            $coordonneesCommunes = false;
            break;
        }
    }
    verifier('carte : chaque commune a des coordonnées valides', $coordonneesCommunes);

    $suggestions = carte_suggestions('toulou');
    verifier(
        'carte : chercher « toulou » propose Toulouse en premier',
        $suggestions !== [] && $suggestions[0]['nom'] === 'Toulouse',
        $suggestions === [] ? 'aucune suggestion' : $suggestions[0]['nom']
    );
    $sansAccent = array_column(carte_suggestions('saint etienne'), 'nom');
    verifier(
        'carte : chercher sans accent trouve la commune accentuée',
        in_array('Saint-Étienne', $sansAccent, true),
        implode(', ', array_slice($sansAccent, 0, 5))
    );
    verifier(
        'carte : une lettre seule ne propose rien (et ne lit pas 8 000 lignes)',
        carte_suggestions('') === [] && carte_suggestions('a') === []
    );
    verifier(
        'carte : le nombre de suggestions demandé est respecté',
        count(carte_suggestions('saint', 5)) <= 5 && count(carte_suggestions('saint', 3)) <= 3
    );
    $rechercheImpossible = carte_suggestions('zzzzzzzz');
    verifier('carte : une recherche sans résultat ne propose rien', $rechercheImpossible === []);

    // -----------------------------------------------------------------------
    // 3. Filtres lus dans l'adresse
    // -----------------------------------------------------------------------
    $getAvant = $_GET;

    $_GET = ['vue' => 'inconnue', 'branche' => '12', 'decennie' => '1234', 'lieu' => '  Toulouse  '];
    $filtres = carte_filtres();
    verifier(
        'carte : une vue inconnue et une décennie mal écrite sont ignorées',
        $filtres['vue'] === 'france' && $filtres['decennie'] === 0 && $filtres['branche'] === 12,
        json_encode($filtres) ?: ''
    );
    verifier(
        'carte : le nom du lieu mis en avant est nettoyé et borné',
        $filtres['lieu'] === 'Toulouse'
            && mb_strlen(carte_filtres()['lieu']) <= 120
    );

    $_GET = ['decennie' => '1950', 'vue' => 'monde'];
    $filtres = carte_filtres();
    verifier(
        'carte : une décennie correcte et la vue « monde » sont gardées',
        $filtres['decennie'] === 1950 && $filtres['vue'] === 'monde' && $filtres['branche'] === 0
    );

    $_GET = [];
    $debordement = carte_filtres();
    $_GET = ['lieu' => str_repeat('a', 300)];
    verifier(
        'carte : une adresse vide donne des filtres neutres, et un lieu trop long est coupé',
        $debordement['vue'] === 'france' && $debordement['decennie'] === 0
            && $debordement['branche'] === 0 && $debordement['lieu'] === ''
            && mb_strlen(carte_filtres()['lieu']) === 120
    );

    $_GET = $getAvant;

    // -----------------------------------------------------------------------
    // 4. Le dessin SVG : aucun style en ligne, aucun script, aucune ressource
    //    extérieure (la règle de sécurité du site vaut aussi pour la carte).
    // -----------------------------------------------------------------------
    $donneesEssai = [
        'points' => [
            ['id' => 12, 'nom' => 'Toulouse', 'pays' => 'France', 'lat' => 43.6047, 'lon' => 1.4442, 'personnes' => 9],
            ['id' => 13, 'nom' => 'Cahors', 'pays' => 'France', 'lat' => 44.4491, 'lon' => 1.4409, 'personnes' => 2],
        ],
        'sans_lieu' => 4,
        'total'     => 11,
    ];
    $svg = carte_svg($donneesEssai, 'france', 'Toulouse');

    verifier(
        'carte : le dessin est un SVG complet, sans style en ligne ni script',
        str_starts_with($svg, '<svg class="carte-lieux"')
            && str_contains($svg, 'viewBox="0 0 ' . (int) $france['largeur'] . ' ' . (int) $france['hauteur'] . '"')
            && str_contains($svg, 'role="img"')
            && !str_contains($svg, 'style=')
            && !str_contains($svg, '<style')
            && !str_contains($svg, '<script')
            && !str_contains($svg, ' on')
    );
    verifier(
        'carte : le dessin n’appelle aucune ressource extérieure',
        !preg_match('#https?://#i', $svg)
            && !str_contains($svg, 'url(')
            && !str_contains($svg, '<image')
            && !str_contains($svg, 'xlink:href')
            && substr_count($svg, 'href="/lieu/') === 2
    );
    verifier(
        'carte : chaque point mène à la fiche du lieu et porte son nom en clair',
        str_contains($svg, 'href="/lieu/12"')
            && str_contains($svg, 'href="/lieu/13"')
            && str_contains($svg, '<title>Toulouse — 9 personnes</title>')
            && str_contains($svg, '<title>Cahors — 2 personnes</title>')
    );
    verifier(
        'carte : le lieu mis en avant est distingué, et le plus peuplé a le plus gros point',
        str_contains($svg, 'carte-lieux__point--actif')
            && carte_rayon(9, 9) > carte_rayon(2, 9)
            && carte_rayon(1, 9) >= CARTE_RAYON_MIN
            && carte_rayon(9, 9) <= CARTE_RAYON_MAX
            && carte_rayon(5, 0) === CARTE_RAYON_MIN
    );
    verifier(
        'carte : un point placé hors du cadre n’est pas dessiné du tout',
        substr_count(carte_svg([
            'points' => [['id' => 14, 'nom' => 'Ailleurs', 'pays' => 'France', 'lat' => 10.0, 'lon' => 1.0, 'personnes' => 3]],
            'sans_lieu' => 0, 'total' => 3,
        ], 'france'), '<circle') === 0
    );
    verifier(
        'carte : une carte sans aucun lieu reste un dessin valide, avec un texte de remplacement',
        str_contains(carte_svg(['points' => [], 'sans_lieu' => 0, 'total' => 0], 'france'), 'Aucun lieu placé')
            && substr_count(carte_svg(['points' => [], 'sans_lieu' => 0, 'total' => 0], 'france'), '<circle') === 0
    );
    verifier(
        'carte : le tableau de secours reprend exactement les mêmes lieux',
        array_column(carte_tableau($donneesEssai), 'nom') === ['Toulouse', 'Cahors']
            && array_column(carte_tableau($donneesEssai), 'url') === ['/lieu/12', '/lieu/13']
            && array_column(carte_tableau($donneesEssai), 'personnes') === [9, 2]
    );

    // -----------------------------------------------------------------------
    // 5. Droits, filtres, cache et fiche d'un lieu (transaction annulée)
    // -----------------------------------------------------------------------
    $pdo          = db();
    $transaction  = false;
    $sessionAvant = $_SESSION;

    try {
        $pdo->beginTransaction();
        $transaction = true;

        $suffixe = bin2hex(random_bytes(3));
        $nomA    = 'Toulouse essai ' . $suffixe;
        $nomB    = 'Cahors essai ' . $suffixe;
        $nomC    = 'Ailleurs essai ' . $suffixe;   // lieu placé, sans personne
        $nomD    = 'Secret essai ' . $suffixe;     // lieu d'une personne privée
        $nomInconnu = 'Inconnu essai ' . $suffixe; // texte jamais placé

        $lieuA = tests_carte_lieu($nomA, 43.6047, 1.4442);
        $lieuB = tests_carte_lieu($nomB, 44.4491, 1.4409);
        $lieuC = tests_carte_lieu($nomC, 45.0, 2.0);
        $lieuD = tests_carte_lieu($nomD, 46.0, 3.0);
        verifier(
            'carte : les quatre lieux d’essai sont enregistrés',
            $lieuA > 0 && $lieuB > 0 && $lieuC > 0 && $lieuD > 0
        );
        verifier(
            'carte : un lieu se retrouve à partir du texte d’une fiche, quelle que soit la casse',
            (carte_place_du_texte('  ' . mb_strtoupper($nomA) . ' ')['id'] ?? 0) === $lieuA
                && carte_place_du_texte($nomInconnu) === null
        );
        $memeLieu = carte_place_enregistrer($nomA, 'France', 43.7, 1.5);
        verifier(
            'carte : placer deux fois le même lieu met à jour ses coordonnées sans doublon',
            $memeLieu === $lieuA
                && (int) db_value('SELECT COUNT(*) FROM places WHERE nom = ? COLLATE NOCASE', [$nomA], 0) === 1
                && abs((float) db_value('SELECT lat FROM places WHERE id = ?', [$lieuA], 0.0) - 43.7) < 0.001
        );
        verifier(
            'carte : un lieu sans nom ou avec des coordonnées impossibles est refusé',
            carte_place_enregistrer('   ', 'France', 43.0, 1.0) === 0
                && carte_place_enregistrer('Perdu', 'France', 'abc', '1') === 0
                && carte_place_enregistrer('Perdu', 'France', 95.0, 1.0) === 0
        );

        // Personnes : une publique, une privée, une de famille, une sans lieu
        // placé. Toutes rattachées aux lieux d'essai par les champs habituels.
        $brancheA = db_insert('branches', [
            'code' => 'CARTE-A-' . $suffixe, 'name' => 'Branche A (carte)',
            'description' => '', 'see_all' => 0, 'is_active' => 1,
            'created_at' => time(), 'updated_at' => time(),
        ]);
        $brancheB = db_insert('branches', [
            'code' => 'CARTE-B-' . $suffixe, 'name' => 'Branche B (carte)',
            'description' => '', 'see_all' => 0, 'is_active' => 1,
            'created_at' => time(), 'updated_at' => time(),
        ]);

        $personnePublique = tests_carte_personne([
            'first_name' => 'Publique', 'last_name' => 'Carte-' . $suffixe,
            'visibility' => 'public', 'birth_place' => $nomA, 'birth_year' => 1950,
        ]);
        $personnePrivee = tests_carte_personne([
            'first_name' => 'Privée', 'last_name' => 'Carte-' . $suffixe,
            'visibility' => 'prive', 'birth_place' => $nomA, 'death_place' => $nomD,
            'birth_year' => 1950,
        ]);
        $personneFamille = tests_carte_personne([
            'first_name' => 'Famille', 'last_name' => 'Carte-' . $suffixe,
            'visibility' => 'famille', 'death_place' => $nomB, 'birth_year' => 1900,
        ]);
        tests_carte_personne([
            'first_name' => 'Sans', 'last_name' => 'Lieu-' . $suffixe,
            'visibility' => 'public', 'birth_place' => $nomInconnu,
        ]);
        db_insert('content_branches', ['kind' => 'person', 'content_id' => $personnePublique, 'branch_id' => $brancheA]);
        db_insert('content_branches', ['kind' => 'person', 'content_id' => $personneFamille, 'branch_id' => $brancheB]);

        // Un mariage au lieu B, entre la personne publique et la personne privée.
        db_insert('families', [
            'husband_id' => $personnePublique, 'wife_id' => $personnePrivee,
            'marriage_date' => '1975', 'marriage_place' => $nomB,
            'created_at' => time(), 'updated_at' => time(),
        ]);
        carte_places(true);

        $adminId = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);
        $_GET = [];

        // --- Vu par un visiteur non connecté -------------------------------
        unset($_SESSION['account_id']);
        $visiteur = carte_agreger(['branche' => 0, 'decennie' => 0, 'vue' => 'france', 'lieu' => '']);
        $pointA = tests_carte_point($visiteur, $nomA);

        verifier(
            'carte : un visiteur ne compte que les personnes publiques',
            $pointA !== null && (int) $pointA['personnes'] === 1,
            $pointA === null ? 'lieu absent' : (string) $pointA['personnes']
        );
        verifier(
            'carte : un lieu dont aucune personne n’est visible pour le visiteur n’apparaît pas',
            tests_carte_point($visiteur, $nomC) === null
                && tests_carte_point($visiteur, $nomD) === null
        );
        verifier(
            'carte : un mariage compte quand l’un des deux époux est visible',
            (int) (tests_carte_point($visiteur, $nomB)['personnes'] ?? 0) === 1
        );
        verifier(
            'carte : un texte de lieu jamais placé est compté « sans lieu », sans casser la carte',
            (int) $visiteur['sans_lieu'] === 1,
            (string) $visiteur['sans_lieu']
        );

        // --- Vu par un administrateur --------------------------------------
        $_SESSION['account_id'] = $adminId;
        $admin = carte_agreger(['branche' => 0, 'decennie' => 0, 'vue' => 'france', 'lieu' => '']);
        verifier(
            'carte : un administrateur voit toutes les personnes, y compris privées',
            (int) (tests_carte_point($admin, $nomA)['personnes'] ?? 0) === 2
                && (int) (tests_carte_point($admin, $nomD)['personnes'] ?? 0) === 1
        );
        verifier(
            'carte : les lieux les plus peuplés passent devant',
            $admin['points'] !== []
                && (int) $admin['points'][0]['personnes'] >= (int) end($admin['points'])['personnes']
        );

        // --- Filtres -------------------------------------------------------
        $parBranche = carte_agreger(['branche' => (int) $brancheA, 'decennie' => 0, 'vue' => 'france', 'lieu' => '']);
        verifier(
            'carte : le filtre « branche » ne garde que les personnes de cette branche',
            (int) (tests_carte_point($parBranche, $nomA)['personnes'] ?? 0) === 1
                && tests_carte_point($parBranche, $nomD) === null,
            json_encode(array_column($parBranche['points'], 'personnes')) ?: ''
        );

        $parDecennie = carte_agreger(['branche' => 0, 'decennie' => 1900, 'vue' => 'france', 'lieu' => '']);
        verifier(
            'carte : le filtre « décennie » ne garde que les personnes nées dans ces années',
            tests_carte_point($parDecennie, $nomA) === null
                && (int) (tests_carte_point($parDecennie, $nomB)['personnes'] ?? 0) === 1,
            json_encode(array_column($parDecennie['points'], 'nom')) ?: ''
        );
        $decennies = carte_decennies();
        verifier(
            'carte : les décennies proposées sont des dizaines d’années réelles',
            $decennies !== [] && min($decennies) >= 1000 && max($decennies) <= (int) date('Y') + 1
                && count(array_filter($decennies, static fn (int $d): bool => $d % 10 !== 0)) === 0,
            implode(', ', array_slice($decennies, 0, 6))
        );

        // --- Cache ---------------------------------------------------------
        unset($_SESSION['account_id']);
        $cléSansCompte = carte_cle_cache(['branche' => 0, 'decennie' => 0, 'vue' => 'france', 'lieu' => '']);
        $cléVisiteur   = carte_cle_cache(['branche' => 0, 'decennie' => 0, 'vue' => 'france', 'lieu' => '']);
        $_SESSION['account_id'] = $adminId;
        $cléAdmin = carte_cle_cache(['branche' => 0, 'decennie' => 0, 'vue' => 'france', 'lieu' => '']);
        $cléAutreBranche = carte_cle_cache(['branche' => (int) $brancheA, 'decennie' => 0, 'vue' => 'france', 'lieu' => '']);
        verifier(
            'carte : la clé de cache change avec le groupe de droits et avec les filtres',
            $cléVisiteur === $cléSansCompte
                && $cléVisiteur !== $cléAdmin
                && $cléAdmin !== $cléAutreBranche
                && strlen($cléAdmin) === 64
        );

        $filtresCache = ['branche' => 0, 'decennie' => 0, 'vue' => 'france', 'lieu' => ''];
        $fichierCache = carte_dossier_cache() . '/' . carte_cle_cache($filtresCache) . '.json';
        @unlink($fichierCache);

        $premier  = carte_donnees($filtresCache);
        $second   = carte_donnees($filtresCache);
        verifier(
            'carte : le premier calcul est gardé, le second est relu depuis le cache',
            $premier['cache'] === false && $second['cache'] === true
                && $second['points'] === $premier['points']
                && is_file($fichierCache)
        );
        $relu = carte_donnees($filtresCache, true);
        verifier(
            'carte : le cache peut être forcé à recalculer, et il se vide d’un coup',
            $relu['cache'] === false && $relu['points'] === $premier['points']
        );
        carte_vider_cache();
        verifier(
            'carte : vider le cache efface les fichiers gardés',
            !is_file($fichierCache)
        );

        // --- La fiche d'un lieu -------------------------------------------
        $_SESSION['account_id'] = $adminId;
        $ficheAdmin = matrice_requete('GET', '/lieu/' . $lieuD, $adminId);
        $ficheVisiteur = matrice_requete('GET', '/lieu/' . $lieuD, null);
        $ficheInconnue = matrice_requete('GET', '/lieu/99999999', null);
        verifier(
            'carte : la fiche d’un lieu est ouverte à qui peut voir ses personnes',
            $ficheAdmin['code'] === 200 && $ficheAdmin['erreur'] === ''
                && str_contains($ficheAdmin['corps'], 'Privée')
        );
        verifier(
            'carte : un visiteur ne peut pas deviner l’existence d’un lieu (même réponse partout)',
            $ficheVisiteur['code'] === 303
                && $ficheInconnue['code'] === 303
                && $ficheVisiteur['corps'] === $ficheInconnue['corps'],
            'codes ' . $ficheVisiteur['code'] . ' / ' . $ficheInconnue['code']
        );

        // 1.31.0 : la carte et les fiches de lieux ne sont plus publiques.
        $fichePublique = matrice_requete('GET', '/lieu/' . $lieuA, null);
        verifier(
            'carte : la fiche d’un lieu renvoie un visiteur vers la connexion (1.31.0)',
            $fichePublique['code'] === 303
                && str_contains((string) ($fichePublique['location'] ?? ''), '/connexion'),
            'code ' . $fichePublique['code']
        );

        $famille = tests_carte_compte_famille();
        $ficheFamille = matrice_requete('GET', '/lieu/' . $lieuA, $famille);
        verifier(
            'carte : un compte connecté ouvre la fiche d’un lieu public, et y retrouve les personnes',
            $ficheFamille['code'] === 200
                && str_contains($ficheFamille['corps'], 'Publique')
                && !str_contains($ficheFamille['corps'], 'Privée')
        );
        verifier(
            'carte : la fiche d’un lieu ne montre jamais un secret d’une personne privée',
            !str_contains($ficheFamille['corps'], $nomD)
        );

        $carteVisiteur = matrice_requete('GET', '/carte', null);
        $adminLieux    = matrice_requete('GET', '/admin/lieux', $adminId);
        $adminLieuxVisiteur = matrice_requete('GET', '/admin/lieux', null);
        // 1.31.0 : la carte s'ouvre une fois connecté, comme l'arbre.
        $carteFamille = matrice_requete('GET', '/carte', $famille);
        verifier(
            'carte : un visiteur est renvoyé vers la connexion, la carte reste ouverte une fois connecté',
            $carteVisiteur['code'] === 303
                && str_contains((string) ($carteVisiteur['location'] ?? ''), '/connexion')
                && $carteFamille['code'] === 200
                && str_contains($carteFamille['corps'], 'Carte des lieux')
                && !str_contains($carteFamille['corps'], $nomD)
                && $adminLieux['code'] === 200
                && $adminLieuxVisiteur['code'] === 303
        );
        verifier(
            'carte : la page ne charge aucune ressource extérieure',
            !preg_match('#(src|href)="https?://#i', $carteFamille['corps'])
                && !preg_match('#<[^>]+\sstyle=#i', $carteFamille['corps'])
                && !str_contains($carteFamille['corps'], '<style')
                && !str_contains($carteFamille['corps'], 'carte-lieux" style')
        );

        // --- Un lieu retiré quitte la carte, sans toucher aux fiches --------
        carte_place_supprimer($lieuC);
        verifier(
            'carte : retirer un lieu ne change aucun texte de fiche',
            carte_place($lieuC) === null
                && db_value('SELECT birth_place FROM persons WHERE id = ?', [$personnePublique], '') === $nomA
                && db_value('SELECT death_place FROM persons WHERE id = ?', [$personneFamille], '') === $nomB
        );
        verifier(
            'carte : la liste « à placer » ne garde que les textes sans coordonnées',
            in_array($nomInconnu, array_column(carte_lieux_a_placer(), 'nom'), true)
                && !in_array($nomA, array_column(carte_lieux_a_placer(), 'nom'), true)
        );
        // 1.31.0 : le menu du visiteur ne contient plus la carte du tout ;
        // un compte connecté, lui, la garde quand un lieu est placé.
        $compte = ['role' => 'famille', 'branch_id' => null];
        verifier(
            'carte : le menu d’un compte connecté propose la carte si un lieu est placé',
            carte_exist(true) === true
                && in_array('/carte', array_column(menu_rubriques($compte, ['carte' => true]), 'href'), true)
                && !in_array('/carte', array_column(menu_rubriques($compte, ['carte' => false]), 'href'), true)
                && !in_array('/carte', array_column(menu_rubriques(null, ['carte' => true]), 'href'), true)
        );

        // Le tchat n'est jamais proposé à un visiteur, même avec la carte.
        verifier(
            'carte : le menu public reste sans tchat',
            !in_array('tchat', array_column(menu_rubriques(null, ['carte' => true, 'tchat' => true]), 'cle'), true)
        );

    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Le cache est un simple accélérateur : on le vide par prudence (les
        // fichiers ne contiennent que des compteurs, jamais un nom de personne).
        carte_vider_cache();
        carte_places(true);
        $_GET     = $getAvant;
        $_SESSION = $sessionAvant;
    }
}

// ---------------------------------------------------------------------------
// Carte désactivée (1.32.0) : plus rien ne la montre, à personne
// ---------------------------------------------------------------------------
if ($filtre === '' || $filtre === 'carte_desactivee') {
    echo "Carte des lieux — désactivée par défaut\n";

    $pdo         = db();
    $transaction = !$pdo->inTransaction();
    if ($transaction) {
        $pdo->beginTransaction();
    }
    $sessionAvant = $_SESSION;
    $getAvant     = $_GET;

    try {
        // Aucun réglage enregistré : c'est l'état d'un site neuf.
        db_exec("DELETE FROM settings WHERE key = 'carte_active'");
        site_settings_oublier();
        carte_active(true);
        verifier(
            'carte désactivée : fermée par défaut (aucun réglage enregistré)',
            carte_active() === false
        );

        $lieu    = tests_carte_lieu('Lieu fermé 1.32');
        $adminId = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);
        $famille = tests_carte_compte_famille();
        $placesAvant = (int) db_value('SELECT COUNT(*) FROM places', [], 0);

        $carteVisiteur = matrice_requete('GET', '/carte', null);
        $carteFamille  = matrice_requete('GET', '/carte', $famille);
        $carteAdmin    = matrice_requete('GET', '/carte', $adminId);
        $inconnue         = matrice_requete('GET', '/page-qui-n-existe-vraiment-pas', $adminId);
        $inconnueVisiteur = matrice_requete('GET', '/page-qui-n-existe-vraiment-pas', null);

        verifier(
            'carte désactivée : /carte répond 404 à tout le monde, administrateur compris',
            $carteVisiteur['code'] === 404 && $carteFamille['code'] === 404 && $carteAdmin['code'] === 404,
            'codes ' . $carteVisiteur['code'] . ' / ' . $carteFamille['code'] . ' / ' . $carteAdmin['code']
        );
        // Le jeton CSRF du menu change à chaque requête : on compare le corps
        // de la page d'erreur, pas les deux pages entières.
        $erreurDe = static function (string $corps): string {
            $debut = strpos($corps, '<main');
            $fin   = strpos($corps, '</main>');
            return $debut !== false && $fin !== false ? substr($corps, $debut, $fin - $debut) : $corps;
        };
        verifier(
            'carte désactivée : la réponse est celle d’une page introuvable, sans indice',
            $erreurDe($carteAdmin['corps']) === $erreurDe($inconnue['corps'])
                && !str_contains($carteAdmin['corps'], 'Carte des lieux')
        );

        $lieuVisiteur = matrice_requete('GET', '/lieu/' . $lieu, null);
        $lieuFamille  = matrice_requete('GET', '/lieu/' . $lieu, $famille);
        $lieuAdmin    = matrice_requete('GET', '/lieu/' . $lieu, $adminId);
        verifier(
            'carte désactivée : /lieu/{id} répond 404 à tout le monde, comme une page inconnue',
            $lieuVisiteur['code'] === 404 && $lieuFamille['code'] === 404
                && $lieuAdmin['code'] === 404
                && $erreurDe($lieuAdmin['corps']) === $erreurDe($inconnue['corps'])
                && $erreurDe($lieuVisiteur['corps']) === $erreurDe($inconnueVisiteur['corps']),
            'codes ' . $lieuVisiteur['code'] . ' / ' . $lieuFamille['code'] . ' / ' . $lieuAdmin['code']
                . ' — admin ' . $erreurDe($lieuAdmin['corps']) . ' — inconnue ' . $erreurDe($inconnue['corps'])
        );

        $accueil = matrice_requete('GET', '/', $famille);
        verifier(
            'carte désactivée : aucun lien « Carte » dans les menus d’un compte connecté',
            $accueil['code'] === 200 && !str_contains($accueil['corps'], 'href="/carte"')
        );

        $tableauAdmin = matrice_requete('GET', '/admin', $adminId);
        verifier(
            'carte désactivée : « Lieux à placer » quitte le menu d’administration',
            $tableauAdmin['code'] === 200 && !str_contains($tableauAdmin['corps'], '/admin/lieux')
        );

        $adminLieux = matrice_requete('GET', '/admin/lieux', $adminId);
        verifier(
            'carte désactivée : /admin/lieux renvoie vers les réglages',
            $adminLieux['code'] === 303
                && str_contains((string) ($adminLieux['location'] ?? ''), '/admin/reglages'),
            'code ' . $adminLieux['code'] . ' → ' . (string) ($adminLieux['location'] ?? '')
        );

        $reglages = matrice_requete('GET', '/admin/reglages', $adminId);
        verifier(
            'carte désactivée : le réglage « Carte des lieux » est bien proposé',
            $reglages['code'] === 200 && str_contains($reglages['corps'], 'Carte des lieux')
                && str_contains($reglages['corps'], 'name="carte_active"')
        );
        verifier(
            'carte désactivée : le réglage dit qu’il est décoché par défaut et que tout est conservé',
            str_contains($reglages['corps'], 'Décochée (par défaut)')
                && str_contains($reglages['corps'], 'sont conservés')
        );

        verifier(
            'carte désactivée : les données et la table « places » sont conservées',
            (int) db_value('SELECT COUNT(*) FROM places', [], 0) >= $placesAvant
                && carte_place($lieu) !== null
        );

        // Réactiver par la case suffit : rien d'autre à faire.
        tests_carte_activer();
        $carteRouverte = matrice_requete('GET', '/carte', $adminId);
        verifier(
            'carte : cocher la case la rouvre telle quelle, sans migration',
            carte_active() === true && $carteRouverte['code'] === 200
        );
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        carte_vider_cache();
        carte_places(true);
        $_GET     = $getAvant;
        $_SESSION = $sessionAvant;
        tests_carte_activer();
    }
}
