<?php
/**
 * Recherche générale du site (documents, articles, pages, personnes).
 *
 * Deux façons de chercher, choisies automatiquement :
 *
 *  1. **FTS5** (la bonne) : SQLite sait indexer le texte et retrouver un mot
 *     sans tenir compte des accents ni des majuscules. L'index est une table
 *     `search_fts` tenue à jour par des déclencheurs SQL, créés au premier
 *     usage. Rien à lancer à la main.
 *  2. **À défaut** (SQLite compilé sans FTS5) : comparaison avec `GLOB` sur
 *     une version en minuscules du texte, en acceptant toutes les variantes
 *     accentuées de chaque lettre (« begles » trouve « Bègles », « BÈGLES »).
 *
 * Dans les deux cas, la visibilité est vérifiée par les mêmes fonctions que le
 * reste du site : un visiteur ne trouve que ce qu'il a le droit de voir.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Normalisation du texte cherché
// ---------------------------------------------------------------------------

/**
 * Met un texte en minuscules, sans accents, avec des espaces simples.
 * « L’Hélène  de Bègles » → « l helene de begles ».
 */
function recherche_normaliser(string $texte): string
{
    $texte = mb_strtolower(trim($texte), 'UTF-8');

    $texte = strtr($texte, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ñ' => 'n',
        'œ' => 'oe', 'æ' => 'ae',
        'ø' => 'o', 'ł' => 'l', 'ß' => 'ss',
    ]);

    // Tout ce qui n'est pas lettre ou chiffre devient une espace : les
    // guillemets, la ponctuation et les symboles ne gênent pas la recherche.
    $texte = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texte) ?? '';

    return trim(preg_replace('/\s+/u', ' ', $texte) ?? '');
}

/**
 * Découpe une recherche en mots normalisés.
 *
 * @return string[] au maximum 6 mots, chacun de 1 à 40 caractères
 */
function recherche_mots(string $recherche): array
{
    $propre = recherche_normaliser($recherche);
    if ($propre === '') {
        return [];
    }

    $mots = [];
    foreach (explode(' ', $propre) as $mot) {
        $mot = mb_substr($mot, 0, 40, 'UTF-8');
        if ($mot !== '' && !in_array($mot, $mots, true)) {
            $mots[] = $mot;
        }
        if (count($mots) >= 6) {
            break;
        }
    }

    return $mots;
}

/**
 * Variantes accentuées de chaque lettre, utilisées par la recherche de secours.
 * La minuscule est fournie par LOWER() ; les majuscules accentuées doivent
 * être listées, car LOWER() de SQLite ne traite que l'ASCII.
 */
function recherche_lettres_accentuees(): array
{
    return [
        'a' => 'aàâäáãåAÀÂÄÁÃÅ',
        'c' => 'cçCÇ',
        'e' => 'eéèêëEÉÈÊË',
        'i' => 'iîïíIÎÏÍ',
        'o' => 'oôöóõOÔÖÓÕ',
        'u' => 'uùûüúUÙÛÜÚ',
        'y' => 'yÿŸY',
        'n' => 'nñNÑ',
    ];
}

/**
 * Transforme un mot normalisé en motif `GLOB` acceptant les accents.
 * « begles » → « *b[eéèêëEÉÈÊË]*gl[eéèêëEÉÈÊË]s* »
 */
function recherche_motif_glob(string $mot): string
{
    $lettres = recherche_lettres_accentuees();
    $motif   = '*';

    foreach (preg_split('//u', $mot, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $caractere) {
        if (isset($lettres[$caractere])) {
            $motif .= '[' . $lettres[$caractere] . ']';
            continue;
        }
        // Caractères spéciaux de GLOB : on les neutralise (ils ne devraient
        // pas arriver ici, la normalisation les a déjà retirés).
        $motif .= str_contains('*?[]', $caractere) ? '?' : $caractere;
    }

    return $motif . '*';
}

/** Requête FTS5 : chaque mot entre guillemets, recherché aussi en préfixe. */
function recherche_requete_fts(array $mots): string
{
    $parties = [];
    foreach ($mots as $mot) {
        $parties[] = '"' . str_replace('"', '""', $mot) . '"*';
    }
    return implode(' ', $parties);
}

// ---------------------------------------------------------------------------
// Index FTS5 (facultatif, créé tout seul quand SQLite sait le faire)
// ---------------------------------------------------------------------------

/**
 * Version de l'index. À augmenter si l'on change ce qui est indexé : l'index
 * est alors reconstruit tout seul au premier usage, sans commande à lancer.
 */
function recherche_version_index(): int
{
    return 2;
}

/** Noms des déclencheurs créés par cette bibliothèque. */
function recherche_fts_noms_declencheurs(): array
{
    $noms = [];
    foreach (['documents', 'articles', 'pages', 'persons'] as $table) {
        foreach (['ajout', 'modification', 'suppression'] as $evenement) {
            $noms[] = 'recherche_' . $table . '_' . $evenement;
        }
    }
    $noms[] = 'recherche_etiquettes_ajout';
    $noms[] = 'recherche_etiquettes_suppression';

    return $noms;
}

/** SQLite sait-il utiliser FTS5, et l'index est-il prêt ? */
function recherche_fts_disponible(): bool
{
    static $disponible = null;

    if ($disponible !== null) {
        return $disponible;
    }

    // Réglage d'essai (ligne de commande seulement) : force la recherche de
    // secours pour pouvoir la tester sur une base où FTS5 existe.
    if (getenv('HDF_SEARCH_MODE') === 'like') {
        return $disponible = false;
    }

    $existe  = db_value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'search_fts'") !== null;
    $version = (int) site_setting('recherche_version');

    if ($existe && $version === recherche_version_index()) {
        return $disponible = true;
    }

    try {
        recherche_fts_creer();
        save_site_setting('recherche_version', (string) recherche_version_index());
        return $disponible = true;
    } catch (Throwable $e) {
        // SQLite sans FTS5 : la recherche de secours prend le relais.
        error_log('[histoiredefamille] index de recherche indisponible : ' . $e->getMessage());
        return $disponible = false;
    }
}

/** Mode de recherche courant : « fts » ou « like ». */
function recherche_mode(): string
{
    return recherche_fts_disponible() ? 'fts' : 'like';
}

/** Expression SQL du texte indexé pour une table : sert au remplissage. */
function recherche_sql_ligne(string $kind): string
{
    return match ($kind) {
        'document' => "SELECT 'document', d.id, coalesce(d.title, ''),
                coalesce(d.doc_date, '') || ' ' || coalesce(d.description, '') || ' ' || coalesce((
                    SELECT group_concat(t.name, ' ')
                    FROM document_tags dt JOIN tags t ON t.id = dt.tag_id
                    WHERE dt.document_id = d.id
                ), '')
            FROM documents d",
        'article' => "SELECT 'article', a.id, coalesce(a.title, ''),
                coalesce(a.summary, '') || ' ' || coalesce(a.body, '') || ' ' || coalesce(a.category, '')
            FROM articles a",
        'page' => "SELECT 'page', p.id, coalesce(p.title, ''), coalesce(p.body, '')
            FROM pages p",
        'person' => "SELECT 'person', p.id, coalesce(p.first_name, '') || ' ' || coalesce(p.last_name, ''),
                coalesce(p.birth_date, '') || ' ' || coalesce(p.death_date, '') || ' '
                || coalesce(p.birth_place, '') || ' ' || coalesce(p.death_place, '') || ' ' || coalesce(p.notes, '')
            FROM persons p",
        default => '',
    };
}

/** Recalcule complètement l'index (utilisé aussi par la maintenance). */
function recherche_fts_remplir(): int
{
    db()->exec('DELETE FROM search_fts');

    $lignes = 0;
    foreach (['document', 'article', 'page', 'person'] as $kind) {
        $sql = recherche_sql_ligne($kind);
        if ($sql === '') {
            continue;
        }
        $lignes += db_exec('INSERT INTO search_fts (kind, content_id, titre, corps) ' . $sql);
    }

    return $lignes;
}

/**
 * Création de l'index et des déclencheurs qui le tiennent à jour.
 * Appelée une seule fois par base (elle ne fait rien si la table existe).
 */
function recherche_fts_creer(): void
{
    db_transaction(static function (): void {
        // On repart d'un index neuf : anciens déclencheurs, ancienne table.
        foreach (recherche_fts_noms_declencheurs() as $nom) {
            db()->exec("DROP TRIGGER IF EXISTS {$nom}");
        }
        db()->exec('DROP TABLE IF EXISTS search_fts');

        db()->exec(
            'CREATE VIRTUAL TABLE search_fts USING fts5(
                kind UNINDEXED,
                content_id UNINDEXED,
                titre,
                corps,
                tokenize = "unicode61 remove_diacritics 2"
            )'
        );

        // Déclencheurs : à chaque ajout, modification ou suppression, la ligne
        // correspondante de l'index est réécrite. Aucune action à faire à la
        // main, et aucun risque d'index désynchronisé.
        foreach (recherche_fts_declencheurs() as $sql) {
            db()->exec($sql);
        }

        recherche_fts_remplir();
    });
}

/** Liste des déclencheurs SQL (un par table et par type d'opération). */
function recherche_fts_declencheurs(): array
{
    $tables = [
        'documents' => [
            'kind'   => 'document',
            'titre'  => "coalesce(new.title, '')",
            'corps'  => "coalesce(new.doc_date, '') || ' ' || coalesce(new.description, '') || ' ' || coalesce((
                SELECT group_concat(t.name, ' ')
                FROM document_tags dt JOIN tags t ON t.id = dt.tag_id
                WHERE dt.document_id = new.id
            ), '')",
        ],
        'articles' => [
            'kind'  => 'article',
            'titre' => "coalesce(new.title, '')",
            'corps' => "coalesce(new.summary, '') || ' ' || coalesce(new.body, '') || ' ' || coalesce(new.category, '')",
        ],
        'pages' => [
            'kind'  => 'page',
            'titre' => "coalesce(new.title, '')",
            'corps' => "coalesce(new.body, '')",
        ],
        'persons' => [
            'kind'  => 'person',
            'titre' => "coalesce(new.first_name, '') || ' ' || coalesce(new.last_name, '')",
            'corps' => "coalesce(new.birth_date, '') || ' ' || coalesce(new.death_date, '') || ' '
                || coalesce(new.birth_place, '') || ' ' || coalesce(new.death_place, '') || ' ' || coalesce(new.notes, '')",
        ],
    ];

    $sql = [];
    foreach ($tables as $table => $regles) {
        $kind   = $regles['kind'];
        $titre  = $regles['titre'];
        $corps  = $regles['corps'];
        $nom    = 'recherche_' . $table;

        $sql[] = "CREATE TRIGGER IF NOT EXISTS {$nom}_ajout AFTER INSERT ON {$table} BEGIN
            DELETE FROM search_fts WHERE kind = '{$kind}' AND content_id = new.id;
            INSERT INTO search_fts (kind, content_id, titre, corps)
            VALUES ('{$kind}', new.id, {$titre}, {$corps});
        END";

        $sql[] = "CREATE TRIGGER IF NOT EXISTS {$nom}_modification AFTER UPDATE ON {$table} BEGIN
            DELETE FROM search_fts WHERE kind = '{$kind}' AND content_id = new.id;
            INSERT INTO search_fts (kind, content_id, titre, corps)
            VALUES ('{$kind}', new.id, {$titre}, {$corps});
        END";

        $sql[] = "CREATE TRIGGER IF NOT EXISTS {$nom}_suppression AFTER DELETE ON {$table} BEGIN
            DELETE FROM search_fts WHERE kind = '{$kind}' AND content_id = old.id;
        END";
    }

    // Une étiquette ajoutée ou retirée à un document change son texte indexé.
    foreach (['ajout' => 'new', 'suppression' => 'old'] as $evenement => $ligne) {
        $sql[] = "CREATE TRIGGER IF NOT EXISTS recherche_etiquettes_{$evenement}
            AFTER " . ($evenement === 'ajout' ? 'INSERT' : 'DELETE') . " ON document_tags BEGIN
            DELETE FROM search_fts WHERE kind = 'document' AND content_id = {$ligne}.document_id;
            INSERT INTO search_fts (kind, content_id, titre, corps)
            SELECT 'document', d.id, coalesce(d.title, ''),
                coalesce(d.doc_date, '') || ' ' || coalesce(d.description, '') || ' ' || coalesce((
                    SELECT group_concat(t.name, ' ')
                    FROM document_tags dt JOIN tags t ON t.id = dt.tag_id
                    WHERE dt.document_id = d.id
                ), '')
            FROM documents d WHERE d.id = {$ligne}.document_id;
        END";
    }

    return $sql;
}

/** Recalcule l'index s'il existe (appelé par la maintenance hebdomadaire). */
function recherche_reindexer(): int
{
    if (!recherche_fts_disponible()) {
        return 0;
    }
    return recherche_fts_remplir();
}

// ---------------------------------------------------------------------------
// Condition SQL de recherche
// ---------------------------------------------------------------------------

/**
 * Prépare la lecture SQL d'un type de contenu selon les mots cherchés.
 *
 * Renvoie deux morceaux à insérer dans la requête :
 *   - « from » : la ou les tables à lire (avec l'index FTS quand il existe) ;
 *   - « where » : la condition de recherche.
 *
 * En mode FTS5, l'index est la table de départ : SQLite fait UNE recherche
 * dans l'index puis rejoint la table par son identifiant. C'est indispensable :
 * une sous-requête FTS évaluée pour chaque ligne serait très lente.
 *
 * @param string   $kind     'document', 'article', 'page' ou 'person'
 * @param string   $table    table réelle (documents, articles…)
 * @param string   $alias    alias SQL utilisé dans la requête
 * @param string[] $colonnes colonnes de secours (mode sans FTS5)
 * @param string[] $mots     mots normalisés (voir recherche_mots())
 * @param array    $params   paramètres SQL, complétés par référence
 * @return array{from:string, where:string}
 */
function recherche_source(
    string $kind,
    string $table,
    string $alias,
    array $colonnes,
    array $mots,
    array &$params
): array {
    if ($mots === []) {
        return ['from' => "{$table} {$alias}", 'where' => '1 = 0'];
    }

    static $sequence = 0;
    $sequence++;

    if (recherche_mode() === 'fts') {
        $requete = recherche_requete_fts($mots);
        if ($requete !== '') {
            $pRequete = ':rfts' . $sequence;
            $pKind    = ':rkind' . $sequence;
            $params[$pRequete] = $requete;
            $params[$pKind]    = $kind;

            return [
                // CROSS JOIN : SQLite garde alors l'index FTS en premier et
                // rejoint la table ligne par ligne. Avec un JOIN ordinaire, le
                // planificateur peut faire l'inverse (parcourir toute la table
                // pour chaque fiche), ce qui rend la recherche très lente.
                'from'  => "search_fts AS s CROSS JOIN {$table} AS {$alias} ON {$alias}.id = s.content_id",
                'where' => "s.kind = {$pKind} AND s.search_fts MATCH {$pRequete}",
            ];
        }
    }

    // Recherche de secours : chaque mot doit se retrouver dans au moins une
    // colonne, en acceptant les variantes accentuées.
    $conditions = [];
    foreach ($mots as $index => $mot) {
        $pMot = ':rglob' . $sequence . '_' . $index;
        $params[$pMot] = recherche_motif_glob($mot);

        $parties = [];
        foreach ($colonnes as $colonne) {
            $parties[] = "LOWER({$colonne}) GLOB {$pMot}";
        }
        $conditions[] = '(' . implode(' OR ', $parties) . ')';
    }

    return [
        'from'  => "{$table} {$alias}",
        'where' => '(' . implode(' AND ', $conditions) . ')',
    ];
}

// ---------------------------------------------------------------------------
// Recherches par type de contenu
// ---------------------------------------------------------------------------

/**
 * Documents (photos, PDF, vidéos…) visibles, dont les étiquettes correspondent.
 *
 * @param string[] $mots
 * @return array<int, array>
 */
function recherche_documents(array $mots, int $limite = 10, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $source = recherche_source('document', 'documents', 'd', [
        'd.title',
        "coalesce(d.doc_date, '')",
        "coalesce(d.description, '')",
        "coalesce((SELECT group_concat(t.name, ' ') FROM document_tags dt JOIN tags t ON t.id = dt.tag_id WHERE dt.document_id = d.id), '')",
    ], $mots, $params);

    $where = "d.is_attachment = 0 AND {$clause} AND {$source['where']}";
    $total = (int) db_value("SELECT COUNT(*) FROM {$source['from']} WHERE {$where}", $params, 0);

    if ($limite <= 0) {
        return [];
    }

    return db_all(
        "SELECT d.* FROM {$source['from']} WHERE {$where}
         ORDER BY (d.doc_date_sort IS NULL), d.doc_date_sort DESC, d.created_at DESC
         LIMIT {$limite} OFFSET {$offset}",
        $params
    );
}

/**
 * Articles publiés et visibles.
 *
 * @param string[] $mots
 */
function recherche_articles(array $mots, int $limite = 10, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = article_visible_clause('a', $params);
    $source = recherche_source('article', 'articles', 'a', [
        'a.title',
        "coalesce(a.summary, '')",
        "coalesce(a.body, '')",
        "coalesce(a.category, '')",
    ], $mots, $params);

    $where = "{$clause} AND {$source['where']}";
    $total = (int) db_value("SELECT COUNT(*) FROM {$source['from']} WHERE {$where}", $params, 0);

    if ($limite <= 0) {
        return [];
    }

    return db_all(
        "SELECT a.* FROM {$source['from']} WHERE {$where}
         ORDER BY a.published_at DESC, a.id DESC
         LIMIT {$limite} OFFSET {$offset}",
        $params
    );
}

/**
 * Pages d'information visibles.
 *
 * @param string[] $mots
 */
function recherche_pages(array $mots, int $limite = 10, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = visibility_clause('page', 'p', $params);
    $source = recherche_source('page', 'pages', 'p', ['p.title', "coalesce(p.body, '')"], $mots, $params);

    $where = "{$clause} AND {$source['where']}";
    $total = (int) db_value("SELECT COUNT(*) FROM {$source['from']} WHERE {$where}", $params, 0);

    if ($limite <= 0) {
        return [];
    }

    return db_all(
        "SELECT p.* FROM {$source['from']} WHERE {$where}
         ORDER BY p.position, p.title
         LIMIT {$limite} OFFSET {$offset}",
        $params
    );
}

/**
 * Personnes visibles : nom, prénom, lieux de naissance et de décès, notes.
 *
 * @param string[] $mots
 */
function recherche_personnes(array $mots, int $limite = 10, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $source = recherche_source('person', 'persons', 'p', [
        "trim(coalesce(p.first_name, '') || ' ' || coalesce(p.last_name, ''))",
        "coalesce(p.last_name, '')",
        "coalesce(p.first_name, '')",
        "coalesce(p.birth_date, '')",
        "coalesce(p.death_date, '')",
        "coalesce(p.birth_place, '')",
        "coalesce(p.death_place, '')",
        "coalesce(p.notes, '')",
    ], $mots, $params);

    $where = "{$clause} AND {$source['where']}";
    $total = (int) db_value("SELECT COUNT(*) FROM {$source['from']} WHERE {$where}", $params, 0);

    if ($limite <= 0) {
        return [];
    }

    return db_all(
        "SELECT p.* FROM {$source['from']} WHERE {$where}
         ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE
         LIMIT {$limite} OFFSET {$offset}",
        $params
    );
}

/** Types de contenus cherchables : clé technique → libellé. */
function recherche_types(): array
{
    return [
        'documents'  => 'Documents',
        'articles'   => 'Articles',
        'pages'      => 'Pages d’information',
        'personnes'  => 'Personnes',
    ];
}

/**
 * Cherche dans tous les thèmes à la fois (aperçu de la page /recherche).
 *
 * @return array{resultats:array<string,array>, totaux:array<string,int>}
 */
function recherche_tout(array $mots, int $limite = 8): array
{
    $resultats = [];
    $totaux    = [];

    foreach (array_keys(recherche_types()) as $type) {
        $fonction = 'recherche_' . $type;
        $total    = 0;
        $resultats[$type] = $fonction($mots, $limite, 0, $total);
        $totaux[$type]    = $total;
    }

    return ['resultats' => $resultats, 'totaux' => $totaux];
}

/** Nombre total de résultats, toutes thèmes confondues. */
function recherche_total(array $totaux): int
{
    return array_sum($totaux);
}
