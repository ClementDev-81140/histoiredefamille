<?php
/**
 * Données de démonstration : les repérer, les inventorier, les purger.
 *
 * Pourquoi ce fichier ? `php app/cli/demo.php` remplit le site de contenus
 * fictifs pour les essais. Le jour de l'ouverture à la famille, il ne doit
 * plus en rester la moindre trace. Une suppression « à la main » oublie
 * toujours quelque chose (une photo de fiche, un message de tchat, une
 * personne isolée dans l'arbre), et une suppression automatique trop large
 * effacerait de vrais souvenirs.
 *
 * D'où deux règles, tenues ici :
 *   1. **Ce que l'on sait**, on l'efface. `demo.php` note dans
 *      `demo-registry.json` (hors racine web) les identifiants qu'il a créés,
 *      table par table. La même liste est recopiée dans les réglages du site :
 *      ainsi, une sauvegarde de la seule base (ou un dossier `storage`
 *      reconstruit) ne fait pas perdre la trace. La purge s'en sert : elle ne
 *      touche qu'à ces lignes.
 *   2. **Ce que l'on ne sait pas**, on le dit. Pour une base remplie par une
 *      version antérieure du jeu de démonstration (pas de registre), la purge
 *      reconnaît les lignes qui portent le marqueur « (démo) » et signale les
 *      autres au lieu de les supprimer au hasard.
 *
 * Elle ne supprime JAMAIS un compte : un compte de démonstration se désactive
 * depuis l'administration, et cette décision reste humaine.
 */

declare(strict_types=1);

/** Fichier du registre des données de démonstration (hors racine web). */
function demo_registry_path(): string
{
    return rtrim((string) config('storage.root'), '/') . '/demo-registry.json';
}

/**
 * Lit le registre.
 *
 * Le fichier fait foi ; s'il a disparu (dossier `storage` reconstruit, seule la
 * base restaurée), on relit la copie gardée dans les réglages du site.
 *
 * @return array{tables:array<string, array{min:int, max:int}>, date:int, version:string}
 */
function demo_registry_read(?string $fichier = null): array
{
    $vide = ['tables' => [], 'date' => 0, 'version' => ''];

    if ($fichier === null) {
        $fichier = demo_registry_path();
    }

    $registre = demo_registry_decode(
        is_file($fichier) ? (string) @file_get_contents($fichier) : ''
    );
    if ($registre['tables'] !== []) {
        return $registre;
    }

    // Repli : la copie en base, écrite en même temps que le fichier.
    if ($fichier === demo_registry_path()) {
        try {
            $copie = demo_registry_decode(site_setting('demo_registry'));
            if ($copie['tables'] !== []) {
                return $copie;
            }
        } catch (Throwable) {
            // Base pas encore installée : pas de registre, c'est tout.
        }
    }

    return $vide;
}

/**
 * Décode un registre (fichier ou copie en base), jamais d'erreur.
 *
 * @return array{tables:array<string, array{min:int, max:int}>, date:int, version:string}
 */
function demo_registry_decode(string $brut): array
{
    $vide = ['tables' => [], 'date' => 0, 'version' => ''];
    if (trim($brut) === '') {
        return $vide;
    }

    $donnees = json_decode($brut, true);
    if (!is_array($donnees) || !isset($donnees['tables']) || !is_array($donnees['tables'])) {
        return $vide;
    }

    return [
        'tables'  => is_array($donnees['tables']) ? $donnees['tables'] : [],
        'date'    => (int) ($donnees['date'] ?? 0),
        'version' => (string) ($donnees['version'] ?? ''),
    ];
}

/**
 * Écrit le registre (appelé par demo.php à la fin de son travail).
 *
 * @param array<string, array{min:int, max:int}> $tables
 */
function demo_registry_write(array $tables, ?string $fichier = null): bool
{
    $copieEnBase = $fichier === null;
    $fichier = $fichier ?? demo_registry_path();
    $contenu = json_encode(
        ['tables' => $tables, 'date' => time(), 'version' => '1'],
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    if (!is_string($contenu)) {
        return false;
    }

    $ecrit = @file_put_contents($fichier, $contenu . "\n") !== false;

    // La même liste part en base : c'est elle qui reste si le dossier
    // `storage` est reconstruit (seule la base restaurée, par exemple).
    if ($copieEnBase) {
        try {
            save_site_setting('demo_registry', $contenu);
            $ecrit = true;
        } catch (Throwable) {
            // Base pas encore installée : le fichier suffit.
        }
    }

    return $ecrit;
}

/** Efface le registre (fichier et copie en base) : plus rien à purger. */
function demo_registry_oublier(): void
{
    @unlink(demo_registry_path());
    try {
        save_site_setting('demo_registry', '');
    } catch (Throwable) {
        // Base absente : rien à oublier.
    }
}

/**
 * Identifiants maximaux actuels, table par table (avant le jeu de démo).
 *
 * @param string[] $tables
 * @return array<string, int>
 */
function demo_marques(array $tables): array
{
    $marques = [];
    foreach ($tables as $table) {
        try {
            $marques[$table] = (int) db_value('SELECT COALESCE(MAX(id), 0) FROM ' . $table, [], 0);
        } catch (Throwable) {
            $marques[$table] = 0;
        }
    }
    return $marques;
}

/** Tables que le jeu de démonstration remplit (jamais `accounts`). */
function demo_tables(): array
{
    return [
        'documents', 'articles', 'pages', 'albums', 'recipes',
        'persons', 'families', 'categories', 'chat_messages',
    ];
}

/**
 * Inventaire de ce que la purge effacerait.
 *
 * Chaque entrée porte : la table, les identifiants concernés, un libellé
 * lisible et quelques exemples pour que la personne qui lance la commande
 * voie exactement ce qu'elle s'apprête à perdre.
 *
 * @return array<int, array{table:string, libelle:string, ids:int[], exemples:string[], source:string}>
 */
function demo_inventaire(?array $registre = null): array
{
    $registre = $registre ?? demo_registry_read();
    $inventaire = [];

    $libelles = [
        'documents'     => 'documents (photos, vidéos, PDF, sons)',
        'articles'      => 'articles',
        'pages'         => 'pages d’information',
        'albums'        => 'albums photos',
        'recipes'       => 'recettes',
        'persons'       => 'personnes (arbre généalogique)',
        'families'      => 'couples',
        'categories'    => 'catégories',
        'chat_messages' => 'messages du tchat',
    ];

    foreach (demo_tables() as $table) {
        $ids      = [];
        $source   = '';
        $plage    = $registre['tables'][$table] ?? null;

        // 1. Ce que le registre connaît : les lignes créées par demo.php.
        if (is_array($plage) && (int) ($plage['max'] ?? 0) >= (int) ($plage['min'] ?? 0)) {
            try {
                $lignes = db_all(
                    'SELECT id FROM ' . $table . ' WHERE id BETWEEN ? AND ? ORDER BY id',
                    [(int) $plage['min'], (int) $plage['max']]
                );
                foreach ($lignes as $ligne) {
                    $ids[] = (int) $ligne['id'];
                }
                if ($ids !== []) {
                    $source = 'registre';
                }
            } catch (Throwable) {
                // Table absente : rien à faire.
            }
        }

        // 2. Repli : le marqueur « (démo) » écrit dans les titres. Pour les
        //    personnes (qui n'ont pas de titre) et les catégories, on lit la
        //    note et le nom : le jeu de démonstration y écrit « démonstration »
        //    ou « (démo) ».
        if ($table !== 'chat_messages' && $table !== 'families') {
            $filtre = match ($table) {
                'persons'    => "(notes LIKE '%(démo)%' OR notes LIKE '%démonstration%')",
                'categories' => "name LIKE '%(démo)%'",
                default      => "title LIKE '%(démo)%'",
            };
            try {
                $lignes = db_all('SELECT id FROM ' . $table . ' WHERE ' . $filtre . ' ORDER BY id');
                foreach ($lignes as $ligne) {
                    $id = (int) $ligne['id'];
                    if (!in_array($id, $ids, true)) {
                        $ids[] = $id;
                        $source = $source === '' ? 'marqueur « (démo) »' : $source;
                    }
                }
            } catch (Throwable) {
                // Colonne absente : tant pis.
            }
        }

        if ($ids === []) {
            continue;
        }

        $exemples = [];
        foreach (array_slice($ids, 0, 5) as $id) {
            $exemple = demo_exemple($table, $id);
            if ($exemple !== '') {
                $exemples[] = $exemple;
            }
        }

        $inventaire[] = [
            'table'    => $table,
            'libelle'  => $libelles[$table] ?? $table,
            'ids'      => $ids,
            'exemples' => $exemples,
            'source'   => $source !== '' ? $source : 'registre',
        ];
    }

    return $inventaire;
}

/** Titre lisible d'une ligne, pour l'inventaire. */
function demo_exemple(string $table, int $id): string
{
    try {
        $ligne = db_one('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
    } catch (Throwable) {
        return '';
    }
    if ($ligne === null) {
        return '';
    }

    foreach (['title', 'name', 'first_name'] as $cle) {
        if (isset($ligne[$cle]) && trim((string) $ligne[$cle]) !== '') {
            if ($cle === 'first_name') {
                return trim((string) $ligne[$cle] . ' ' . (string) ($ligne['last_name'] ?? ''));
            }
            return (string) $ligne[$cle];
        }
    }
    if (isset($ligne['body'])) {
        return excerpt((string) $ligne['body'], 40);
    }

    return 'n°' . $id;
}

/**
 * Comptes et branches portant le marqueur « (démo) ».
 *
 * La purge ne les supprime pas : elle les liste pour que l'administrateur les
 * désactive lui-même.
 *
 * @return string[]
 */
function demo_comptes_restants(): array
{
    $restants = [];
    try {
        foreach (db_all("SELECT username, display_name FROM accounts WHERE display_name LIKE '%(démo)%' ORDER BY username") as $ligne) {
            $restants[] = (string) $ligne['username'] . ' (' . (string) $ligne['display_name'] . ')';
        }
    } catch (Throwable) {
        // Base pas encore migrée.
    }

    return $restants;
}

/**
 * Supprime ce que l'inventaire a listé.
 *
 * Ordre imposé par les clés étrangères : les liens d'abord (album, personnes,
 * couples), les contenus ensuite. Chaque suppression de document efface son
 * fichier s'il n'est plus référencé : aucun orphelin ne reste sur la carte SD.
 *
 * @param array<int, array{table:string, ids:int[]}> $inventaire
 * @return array<string, int> nombre de lignes supprimées, table par table
 */
function demo_purger(array $inventaire): array
{
    $ordre = [
        'chat_messages', 'documents', 'articles', 'pages', 'albums', 'recipes',
        'families', 'persons', 'categories',
    ];
    $resultat = [];
    $parTable = [];
    foreach ($inventaire as $element) {
        $parTable[(string) $element['table']] = array_map('intval', (array) $element['ids']);
    }

    // Branches associées : `content_branches` n'a pas de clé étrangère sur le
    // contenu (seulement sur la branche), donc la suppression d'un contenu ne
    // l'efface pas tout seul. Sans ce nettoyage, la purge laisserait des liens
    // pointant vers des lignes disparues.
    $genre = [
        'documents' => 'document',
        'pages'     => 'page',
        'persons'   => 'person',
        'articles'  => 'article',
        'recipes'   => 'recipe',
        'albums'    => 'album',
    ];

    foreach ($ordre as $table) {
        $ids = $parTable[$table] ?? [];
        if ($ids === []) {
            continue;
        }
        $resultat[$table] = 0;

        foreach ($ids as $id) {
            if ($table === 'documents') {
                $doc = db_one('SELECT rel_path, thumb_path FROM documents WHERE id = ?', [$id]);
            }

            try {
                $efface = db_exec('DELETE FROM ' . $table . ' WHERE id = ?', [$id]);
            } catch (Throwable) {
                continue;
            }
            $resultat[$table] += $efface > 0 ? 1 : 0;

            if (isset($genre[$table])) {
                try {
                    db_exec(
                        'DELETE FROM content_branches WHERE kind = ? AND content_id = ?',
                        [$genre[$table], $id]
                    );
                } catch (Throwable) {
                    // Table absente : sans importance.
                }
            }

            if ($table === 'documents' && isset($doc)) {
                foreach ([
                    (string) ($doc['rel_path'] ?? ''),
                    (string) ($doc['thumb_path'] ?? ''),
                ] as $rel) {
                    if ($rel === '') {
                        continue;
                    }
                    $encoreUtilise = (int) db_value(
                        'SELECT COUNT(*) FROM documents WHERE rel_path = ? OR thumb_path = ?',
                        [$rel, $rel],
                        0
                    );
                    if ($encoreUtilise === 0) {
                        storage_delete($rel);
                    }
                }
            }
        }
    }

    return $resultat;
}

/**
 * Le jeu de démonstration est-il encore présent ?
 *
 * Utilisé par la vérification avant ouverture : un simple compteur, jamais un
 * blocage.
 *
 * @return array{contenus:int, comptes:int, message:string}
 */
function demo_present(): array
{
    $lignes = 0;
    foreach (demo_inventaire() as $element) {
        $lignes += count($element['ids']);
    }
    $comptes = count(demo_comptes_restants());

    $morceaux = [];
    if ($lignes > 0) {
        $morceaux[] = $lignes . ' contenu(s)';
    }
    if ($comptes > 0) {
        $morceaux[] = $comptes . ' compte(s)';
    }

    return [
        'contenus' => $lignes,
        'comptes'  => $comptes,
        'message'  => implode(' et ', $morceaux),
    ];
}
