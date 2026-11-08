<?php
/**
 * Requêtes et petits utilitaires partagés autour des contenus
 * (documents, pages, personnes, branches, catégories).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Personnes (arbre généalogique)
// ---------------------------------------------------------------------------

/** Nom complet affichable d'une personne. */
function person_full_name(array $person): string
{
    $first = trim((string) ($person['first_name'] ?? ''));
    $last  = trim((string) ($person['last_name'] ?? ''));
    $name  = trim($first . ' ' . mb_strtoupper($last, 'UTF-8'));
    return $name !== '' ? $name : 'Personne sans nom';
}

/** Dates de vie : « 1901 – 1975 », « 1901 », « 1975 » (décès seul), ''. */
function person_dates(array $person): string
{
    $birth = trim((string) ($person['birth_date'] ?? ''));
    $death = trim((string) ($person['death_date'] ?? ''));
    if ($birth === '' && $death === '') {
        return '';
    }
    if ($death === '') {
        // 1.34.0 : pas de tiret en attente d'un décès inconnu — la naissance
        // seule, sans ligne ni signe « vide ».
        return $birth;
    }
    if ($birth === '') {
        return $death;
    }
    return $birth . ' – ' . $death;
}

/** Extrait une année (4 chiffres) d'une date libre. */
function extract_year(?string $date): ?int
{
    if ($date === null) {
        return null;
    }
    if (preg_match('/(\d{4})/', $date, $m)) {
        $year = (int) $m[1];
        return ($year > 1000 && $year < 2200) ? $year : null;
    }
    return null;
}

/** Nombre d'années entre deux années (âge au décès). */
function person_age(array $person): ?int
{
    $birth = isset($person['birth_year']) ? (int) $person['birth_year'] : extract_year($person['birth_date'] ?? null);
    $death = isset($person['death_year']) ? (int) $person['death_year'] : extract_year($person['death_date'] ?? null);
    if (!$birth || !$death || $death < $birth) {
        return null;
    }
    return $death - $birth;
}

// ---------------------------------------------------------------------------
// Documents
// ---------------------------------------------------------------------------

/** Libellé court d'un type de fichier. */
function document_kind_label(string $kind, string $ext = ''): string
{
    return match ($kind) {
        'photo' => 'Photo',
        'video' => 'Vidéo',
        default => strtoupper($ext !== '' ? $ext : 'fichier'),
    };
}

/** URL de consultation d'un document. */
function document_url(array|int $doc): string
{
    $id = is_array($doc) ? (int) $doc['id'] : $doc;
    return '/documents/' . $id;
}

/** URL de la miniature (ou null). */
function thumbnail_url(array $doc): ?string
{
    return !empty($doc['thumb_path']) ? '/media/miniature/' . (int) $doc['id'] : null;
}

/** URL du fichier (dans le navigateur, si possible). */
function file_url(array $doc): string
{
    return '/f/' . (int) $doc['id'];
}

/** URL de téléchargement forcé. */
function download_url(array $doc): string
{
    return '/f/' . (int) $doc['id'] . '/telecharger';
}

/** URL de lecture vidéo (avec support du Range). */
function video_url(array $doc): string
{
    return '/video/' . (int) $doc['id'];
}

/** L'administrateur autorise-t-il le téléchargement de ce document ? */
function document_download_allowed(array $doc): bool
{
    // Les vidéos peuvent être mises en lecture seule par l'administrateur.
    if (($doc['kind'] ?? '') === 'video') {
        return (int) ($doc['allow_download'] ?? 1) === 1;
    }
    return true;
}

/** Adresse de la page publique d'une vidéo. */
function video_page_url(array $doc): string
{
    return '/videos/' . (int) $doc['id'];
}

/**
 * Durée lisible : « 12 min 30 s », « 1 h 04 min », « 42 s ».
 * Retourne '' si la durée n'est pas renseignée.
 */
function video_duration_label(?int $seconds): string
{
    if ($seconds === null || $seconds <= 0) {
        return '';
    }

    $heures = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $reste = $seconds % 60;

    if ($heures > 0) {
        return sprintf('%d h %02d', $heures, $minutes);
    }
    if ($minutes > 0) {
        return $reste > 0 ? sprintf('%d min %02d s', $minutes, $reste) : sprintf('%d min', $minutes);
    }
    return sprintf('%d s', $reste);
}

/**
 * Durée compacte pour un badge de carte : « 12:30 », « 1:02:03 ».
 */
function video_duration_short(?int $seconds): string
{
    if ($seconds === null || $seconds <= 0) {
        return '';
    }
    $heures = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $reste = $seconds % 60;

    return $heures > 0
        ? sprintf('%d:%02d:%02d', $heures, $minutes, $reste)
        : sprintf('%d:%02d', $minutes, $reste);
}

/**
 * Comprend une durée saisie à la main : « 750 » (secondes), « 12:30 »,
 * « 1:02:03 », « 12 min 30 ». Retourne null si ce n'est pas compréhensible.
 */
function parse_duration(?string $valeur): ?int
{
    $valeur = trim((string) $valeur);
    if ($valeur === '') {
        return null;
    }

    // « 1:02:03 » ou « 12:30 »
    if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $valeur, $m)) {
        if (isset($m[3]) && $m[3] !== '') {
            return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
        }
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    // « 12 min 30 s », « 12 minutes », « 42 s »
    if (preg_match('/^(?:(\d+)\s*(?:h|heure[s]?))?\s*(?:(\d+)\s*(?:min|mn|minute[s]?))?\s*(?:(\d+)\s*(?:s|sec|seconde[s]?))?$/iu', $valeur, $m)
        && ($m[1] ?? '') . ($m[2] ?? '') . ($m[3] ?? '') !== '') {
        return ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);
    }

    // Nombre seul : des secondes.
    if (preg_match('/^\d+$/', $valeur)) {
        return (int) $valeur;
    }

    return null;
}

/**
 * Vidéos visibles par le compte courant.
 *
 * Tri : mises en avant d'abord, puis ordre d'affichage croissant, puis les
 * plus récentes (date du souvenir si elle existe, sinon date d'ajout).
 *
 * @param int      $limit  nombre maximum de résultats (0 = pas de limite)
 * @param int      $offset décalage pour la pagination
 * @param int|null $total  reçoit le nombre total de vidéos visibles
 */
function videos_visible(int $limit = 0, int $offset = 0, ?int &$total = null, int $categorieId = 0): array
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);

    $where = "d.kind = 'video' AND d.is_attachment = 0 AND {$clause}";
    $catClause = category_where('d', $categorieId > 0 ? $categorieId : null, $params, 'video');
    if ($catClause !== '') {
        $where .= ' AND ' . $catClause;
    }

    $total = (int) db_value("SELECT COUNT(*) FROM documents d WHERE {$where}", $params, 0);

    $sql = "SELECT d.* FROM documents d
            WHERE {$where}
            ORDER BY d.is_featured DESC,
                     (d.display_order = 0),
                     d.display_order ASC,
                     (d.doc_date_sort IS NULL),
                     d.doc_date_sort DESC,
                     d.created_at DESC";

    if ($limit > 0) {
        $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    }

    return db_all($sql, $params);
}

/** Une vidéo visible (ou null : 404, même réponse qu'un document interdit). */
function find_viewable_video(int $id): ?array
{
    $doc = find_viewable_document($id);
    if (!$doc || ($doc['kind'] ?? '') !== 'video') {
        return null;
    }
    return $doc;
}

/** Y a-t-il déjà des vidéos en base ? (message d'accueil de l'onglet) */
function videos_exist(): bool
{
    return (int) db_value("SELECT COUNT(*) FROM documents WHERE kind = 'video'", [], 0) > 0;
}

/** Étiquettes d'un document. */
function document_tags(int|array $doc): array
{
    $id = is_array($doc) ? (int) $doc['id'] : $doc;
    $rows = db_all(
        'SELECT t.name FROM tags t
         JOIN document_tags dt ON dt.tag_id = t.id
         WHERE dt.document_id = ?
         ORDER BY t.name',
        [$id]
    );
    return array_map(static fn ($r) => $r['name'], $rows);
}

/** Enregistre les étiquettes d'un document à partir d'un texte « a, b, c ». */
function save_document_tags(int $documentId, string $tagsText): void
{
    db_exec('DELETE FROM document_tags WHERE document_id = ?', [$documentId]);

    $names = preg_split('/[,;]+/u', $tagsText) ?: [];
    $seen  = [];
    foreach ($names as $name) {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            continue;
        }
        $name = mb_substr($name, 0, 40, 'UTF-8');
        $key  = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $existing = db_one('SELECT id FROM tags WHERE name = ? COLLATE NOCASE', [$name]);
        $tagId = $existing ? (int) $existing['id'] : db_insert('tags', ['name' => $name]);
        db_exec('INSERT OR IGNORE INTO document_tags (document_id, tag_id) VALUES (?, ?)', [$documentId, $tagId]);
    }
}

/** Toutes les étiquettes existantes (pour l'aide à la saisie). */
function all_tags(): array
{
    return db_all('SELECT name FROM tags ORDER BY name COLLATE NOCASE');
}

// ---------------------------------------------------------------------------
// Branches et catégories
// ---------------------------------------------------------------------------

/** Liste des branches (actives par défaut). */
function branches_list(bool $includeInactive = false): array
{
    $sql = 'SELECT * FROM branches';
    if (!$includeInactive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY name COLLATE NOCASE';
    return db_all($sql);
}

/** Branche par identifiant. */
function find_branch(int $id): ?array
{
    return db_one('SELECT * FROM branches WHERE id = ?', [$id]);
}

/** Les six types de contenu auxquels une catégorie peut s'appliquer (1.32.0). */
const CATEGORY_KINDS = [
    'article'  => 'Articles',
    'document' => 'Documents',
    'photo'    => 'Photos',
    'album'    => 'Albums',
    'video'    => 'Vidéos',
    'recette'  => 'Recettes',
];

/** Un type de contenu est-il connu ? (garde-fou des formulaires et des URL) */
function category_kind_valide(string $kind): bool
{
    return array_key_exists($kind, CATEGORY_KINDS);
}

/**
 * Types d'une catégorie, à partir de la colonne `kinds`.
 *
 * Une valeur vide ou inconnue vaut « tous les types » : une catégorie ne doit
 * jamais disparaître d'un formulaire à cause d'une donnée abîmée.
 *
 * @return list<string>
 */
function category_kinds(array $categorie): array
{
    $brut = trim((string) ($categorie['kinds'] ?? ''));
    if ($brut === '') {
        return array_keys(CATEGORY_KINDS);
    }
    $types = [];
    foreach (explode(',', $brut) as $morceau) {
        $morceau = trim($morceau);
        if (category_kind_valide($morceau)) {
            $types[] = $morceau;
        }
    }
    return $types === [] ? array_keys(CATEGORY_KINDS) : array_values(array_unique($types));
}

/** Texte à enregistrer dans `categories.kinds` (ordre des six types). */
function category_kinds_texte(array $types): string
{
    $gardes = [];
    foreach (array_keys(CATEGORY_KINDS) as $type) {
        if (in_array($type, $types, true)) {
            $gardes[] = $type;
        }
    }
    return $gardes === [] ? '' : implode(',', $gardes);
}

/** Cette catégorie s'applique-t-elle à ce type de contenu ? */
function category_applicable(array $categorie, string $kind): bool
{
    return in_array($kind, category_kinds($categorie), true);
}

/**
 * Catégories ordonnées (« Ordre » puis nom).
 *
 * @param string|null $kind ne garder que celles de ce type de contenu
 */
function categories_list(?string $kind = null): array
{
    $toutes = db_all('SELECT * FROM categories ORDER BY position, name COLLATE NOCASE');
    if ($kind === null) {
        return $toutes;
    }
    return array_values(array_filter(
        $toutes,
        static fn (array $categorie): bool => category_applicable($categorie, $kind)
    ));
}

/** Catégories qui s'appliquent à au moins un des types demandés. */
function categories_pour_kinds(array $kinds): array
{
    return array_values(array_filter(
        categories_list(),
        static function (array $categorie) use ($kinds): bool {
            foreach ($kinds as $kind) {
                if (category_applicable($categorie, (string) $kind)) {
                    return true;
                }
            }
            return false;
        }
    ));
}

/** Identifiants des catégories d'un contenu, triés. */
function category_ids_pour(string $kind, int $contenuId): array
{
    if (!category_kind_valide($kind) || $contenuId <= 0) {
        return [];
    }
    $ids = db_all(
        'SELECT category_id FROM category_links WHERE kind = ? AND content_id = ? ORDER BY category_id',
        [$kind, $contenuId]
    );
    return array_map(static fn (array $l): int => (int) $l['category_id'], $ids);
}

/**
 * Enregistre les catégories d'un contenu (remplace les précédentes).
 *
 * Seules les catégories qui s'appliquent vraiment au type sont gardées : une
 * valeur fabriquée à la main ne peut pas créer de liaison incohérente.
 */
function category_enregistrer(string $kind, int $contenuId, array $ids): void
{
    if (!category_kind_valide($kind) || $contenuId <= 0) {
        return;
    }
    $valides = [];
    foreach (categories_list($kind) as $categorie) {
        if (in_array((int) $categorie['id'], array_map('intval', $ids), true)) {
            $valides[] = (int) $categorie['id'];
        }
    }

    db_exec('DELETE FROM category_links WHERE kind = ? AND content_id = ?', [$kind, $contenuId]);
    foreach ($valides as $id) {
        db_exec(
            'INSERT OR IGNORE INTO category_links (category_id, kind, content_id) VALUES (?, ?, ?)',
            [$id, $kind, $contenuId]
        );
    }
}

/** Les catégories envoyées par un formulaire (`categories[]`), nettoyées. */
function categories_saisies(): array
{
    $brut = $_POST['categories'] ?? [];
    if (!is_array($brut)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map('intval', $brut), static fn (int $id): bool => $id > 0)));
}

/** Nombre de contenus de ce type visibles par la personne, dans une catégorie. */
function category_compteur(string $kind, int $categorieId): int
{
    $tables = [
        'article'  => ['articles', 'a', 'article'],
        'document' => ['documents', 'd', 'document'],
        'photo'    => ['documents', 'd', 'document'],
        'video'    => ['documents', 'd', 'document'],
        'album'    => ['albums', 'al', 'album'],
        'recette'  => ['recipes', 'r', 'recipe'],
    ];
    if (!isset($tables[$kind])) {
        return 0;
    }
    [$table, $alias, $kindSql] = $tables[$kind];

    $params = [':cat' => $categorieId];
    $clause = visibility_clause($kindSql, $alias, $params);
    $extra  = '';
    if ($table === 'documents') {
        $extra = " AND {$alias}.is_attachment = 0 AND {$alias}.kind = :kinddoc";
        $params[':kinddoc'] = $kind === 'document' ? 'document' : $kind;
        if ($kind === 'document') {
            // Un « document » au sens des catégories, c'est tout ce qui n'est
            // ni une photo ni une vidéo.
            $extra = " AND {$alias}.is_attachment = 0 AND {$alias}.kind NOT IN ('photo', 'video')";
            unset($params[':kinddoc']);
        }
    }
    if ($table === 'articles') {
        $extra = " AND {$alias}.status = 'publie' AND {$alias}.published_at <= :maintenant";
        $params[':maintenant'] = time();
    }

    $sql = "SELECT COUNT(DISTINCT {$alias}.id)
              FROM {$table} {$alias}
              JOIN category_links cl ON cl.kind = :k AND cl.content_id = {$alias}.id
             WHERE cl.category_id = :cat AND {$clause} {$extra}";
    $params[':k'] = $kind;

    return (int) db_value($sql, $params, 0);
}

/**
 * Catégories d'un type, avec le nombre de contenus visibles par la personne.
 * Une catégorie vide pour elle n'apparaît pas : ni le compteur ni la liste ne
 * révèlent un contenu qu'elle n'a pas le droit de voir.
 */
function categories_avec_compteur(string $kind): array
{
    $lignes = [];
    foreach (categories_list($kind) as $categorie) {
        $nombre = category_compteur($kind, (int) $categorie['id']);
        if ($nombre > 0) {
            $lignes[] = $categorie + ['nb' => $nombre];
        }
    }
    return $lignes;
}

/** Catégorie par identifiant. */
function find_category(int $id): ?array
{
    return db_one('SELECT * FROM categories WHERE id = ?', [$id]);
}

/** Transforme un titre en identifiant d'URL. */
function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($converted)) {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'page-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

// ---------------------------------------------------------------------------
// Réglages du site (table settings, modifiables depuis l'administration)
// ---------------------------------------------------------------------------

/** Valeur d'un réglage, avec repli sur la configuration. */
function site_setting(string $key): string
{
    static $cache = null;
    static $generation = null;

    // Un enregistrement (save_site_setting) change la génération : le cache
    // est alors relu, sinon la page suivante du même appel verrait encore
    // l'ancienne valeur.
    $actuelle = (int) ($GLOBALS['hdf_settings_generation'] ?? 0);
    if ($cache === null || $generation !== $actuelle) {
        $generation = $actuelle;
        $cache      = [];
        try {
            foreach (db_all('SELECT key, value FROM settings') as $row) {
                $cache[(string) $row['key']] = (string) $row['value'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }

    $value = $cache[$key] ?? '';
    if ($value !== '') {
        return $value;
    }
    return (string) config('site.' . $key, '');
}

/** Enregistre un réglage. */
/**
 * Oublie les réglages déjà lus pendant cette page.
 *
 * À appeler après un effacement direct dans la table `settings` (par exemple
 * « Rétablir les valeurs d'origine »), sinon la page continuerait d'afficher
 * les anciennes valeurs jusqu'au rechargement suivant.
 */
function site_settings_oublier(): void
{
    $GLOBALS['hdf_settings_generation'] = (int) ($GLOBALS['hdf_settings_generation'] ?? 0) + 1;
}

function save_site_setting(string $key, string $value): void
{
    db_exec(
        'INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value',
        [$key, $value]
    );

    // Le cache de lecture est relu au prochain site_setting().
    site_settings_oublier();
}

// ---------------------------------------------------------------------------
// Requêtes de contenus visibles
// ---------------------------------------------------------------------------

/** Pages visibles (pour la navigation publique). */
function nav_pages(): array
{
    $params = [];
    $clause = visibility_clause('page', 'p', $params);
    return db_all(
        "SELECT p.id, p.slug, p.title FROM pages p
         WHERE p.is_published = 1 AND p.show_in_nav = 1 AND {$clause}
         ORDER BY p.position, p.title COLLATE NOCASE",
        $params
    );
}

/** Compte les contenus visibles d'une table (alias « t »). */
function count_visible(string $table, string $kind): int
{
    $params = [];
    $clause = visibility_clause($kind, 't', $params);
    return (int) db_value("SELECT COUNT(*) FROM {$table} t WHERE {$clause}", $params, 0);
}

/** Derniers documents visibles. */
function recent_documents(int $limit = 6, ?string $kind = null): array
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $sql = "SELECT d.* FROM documents d
            WHERE d.is_attachment = 0 AND {$clause}";
    if ($kind !== null) {
        $sql .= ' AND d.kind = :kind';
        $params[':kind'] = $kind;
    }
    $sql .= ' ORDER BY d.created_at DESC LIMIT ' . max(1, $limit);
    return db_all($sql, $params);
}

/** Dernières personnes visibles. */
function recent_persons(int $limit = 6): array
{
    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $sql = "SELECT p.* FROM persons p WHERE {$clause} ORDER BY p.updated_at DESC LIMIT " . max(1, $limit);
    return db_all($sql, $params);
}

/** Couples (familles) d'une personne : en tant que conjoint ou parent. */
function person_families(int $personId): array
{
    return db_all(
        'SELECT * FROM families WHERE husband_id = ? OR wife_id = ? ORDER BY id',
        [$personId, $personId]
    );
}

/** Parents d'une personne (via les familles où elle est enfant). */
function person_parents(int $personId): array
{
    $rows = db_all(
        'SELECT f.* FROM families f
         JOIN family_children fc ON fc.family_id = f.id
         WHERE fc.person_id = ?
         ORDER BY f.id',
        [$personId]
    );

    $parents = [];
    foreach ($rows as $family) {
        foreach ([$family['husband_id'], $family['wife_id']] as $parentId) {
            if ($parentId && (int) $parentId !== $personId) {
                $parents[(int) $parentId] = (int) $parentId;
            }
        }
    }
    if ($parents === []) {
        return [];
    }

    $ids = array_values($parents);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    return db_all("SELECT * FROM persons WHERE id IN ({$in}) ORDER BY sex DESC, last_name, first_name", $ids);
}

/** Conjoints et enfants d'une personne. */
function person_unions(int $personId): array
{
    $families = person_families($personId);
    $unions   = [];

    foreach ($families as $family) {
        $spouseId = null;
        if ((int) $family['husband_id'] === $personId) {
            $spouseId = $family['wife_id'] !== null ? (int) $family['wife_id'] : null;
        } elseif ((int) $family['wife_id'] === $personId) {
            $spouseId = $family['husband_id'] !== null ? (int) $family['husband_id'] : null;
        } else {
            continue; // famille où la personne n'est pas conjointe (parent isolé)
        }

        $children = db_all(
            'SELECT p.* FROM persons p
             JOIN family_children fc ON fc.person_id = p.id
             WHERE fc.family_id = ?
             ORDER BY p.birth_year IS NULL, p.birth_year, p.first_name',
            [(int) $family['id']]
        );

        $unions[] = [
            'family'  => $family,
            'spouse'  => $spouseId ? db_one('SELECT * FROM persons WHERE id = ?', [$spouseId]) : null,
            'children' => $children,
        ];
    }

    return $unions;
}

/** Fratrie d'une personne (autres enfants de ses familles parentales). */
function person_siblings(int $personId): array
{
    $rows = db_all(
        'SELECT p.* FROM persons p
         JOIN family_children fc ON fc.person_id = p.id
         WHERE fc.family_id IN (SELECT family_id FROM family_children WHERE person_id = ?)
           AND p.id <> ?
         ORDER BY p.birth_year IS NULL, p.birth_year, p.first_name',
        [$personId, $personId]
    );

    $unique = [];
    foreach ($rows as $row) {
        $unique[(int) $row['id']] = $row;
    }
    return array_values($unique);
}

/** Filtre une liste de personnes selon leur visibilité. */
function filter_visible_persons(array $persons): array
{
    static $cache = [];

    // La mémoire est celle du compte courant : dans une même requête on ne
    // change pas de compte, mais les essais, eux, se suivent dans un seul
    // processus. Une mémoire par personne et par compte évite qu'un
    // administrateur ouvre la porte au lecteur qui passe après lui.
    $compte = current_account();
    $prefixe = $compte !== null ? 'compte:' . (int) $compte['id'] . ':' : 'visiteur:';

    $visible = [];
    foreach ($persons as $person) {
        $id = (int) $person['id'];
        $cle = $prefixe . $id;
        if (!array_key_exists($cle, $cache)) {
            $cache[$cle] = can_view((string) $person['visibility'], content_branch_ids('person', $id));
        }
        if ($cache[$cle]) {
            $visible[] = $person;
        }
    }
    return $visible;
}

// ---------------------------------------------------------------------------
// Famille élargie (fiche d'une personne)
// ---------------------------------------------------------------------------

/** Grands-parents d'une personne (les parents de ses parents). */
function person_grandparents(int $personId): array
{
    $trouves = [];
    foreach (person_parents($personId) as $parent) {
        foreach (person_parents((int) $parent['id']) as $grandParent) {
            $id = (int) $grandParent['id'];
            if ($id !== $personId) {
                $trouves[$id] = $grandParent;
            }
        }
    }
    return array_values($trouves);
}

/** Petits-enfants d'une personne (les enfants de ses enfants). */
function person_grandchildren(int $personId): array
{
    $trouves = [];
    foreach (person_unions($personId) as $union) {
        foreach ($union['children'] as $child) {
            foreach (person_unions((int) $child['id']) as $childUnion) {
                foreach ($childUnion['children'] as $grandChild) {
                    $trouves[(int) $grandChild['id']] = $grandChild;
                }
            }
        }
    }
    return array_values($trouves);
}

/** Oncles et tantes (les frères et sœurs des parents). */
function person_uncles_aunts(int $personId): array
{
    $parents = person_parents($personId);
    $exclus  = [$personId];
    foreach ($parents as $parent) {
        $exclus[] = (int) $parent['id'];
    }

    $trouves = [];
    foreach ($parents as $parent) {
        foreach (person_siblings((int) $parent['id']) as $oncle) {
            $id = (int) $oncle['id'];
            if (!in_array($id, $exclus, true)) {
                $trouves[$id] = $oncle;
            }
        }
    }
    return array_values($trouves);
}

/** Neveux et nièces (les enfants des frères et sœurs). */
function person_nephews_nieces(int $personId): array
{
    $trouves = [];
    foreach (person_siblings($personId) as $sibling) {
        foreach (person_unions((int) $sibling['id']) as $union) {
            foreach ($union['children'] as $child) {
                $trouves[(int) $child['id']] = $child;
            }
        }
    }
    return array_values($trouves);
}

/**
 * Chronologie d'une personne, du plus ancien au plus récent :
 * naissance, mariages, naissances des enfants, décès.
 *
 * Les dates sont celles saisies (texte libre : « 1932 », « vers 1930 »…).
 * Le tri utilise l'année quand elle est reconnaissable.
 *
 * @param array $person fiche de la personne
 * @param array $unions résultat de person_unions()
 */
function person_reperes(array $person, array $unions): array
{
    $reperes = [];

    $ajouter = static function (string $date, string $texte, ?string $url = null) use (&$reperes): void {
        $date = trim($date);
        if ($date === '') {
            return;
        }
        $reperes[] = [
            'date'  => $date,
            'tri'   => extract_year($date) ?? 9999,
            'texte' => $texte,
            'url'   => $url,
        ];
    };

    $naissance = 'Naissance';
    if (trim((string) ($person['birth_place'] ?? '')) !== '') {
        $naissance .= ' à ' . trim((string) $person['birth_place']);
    }
    $ajouter((string) ($person['birth_date'] ?? ''), $naissance);

    foreach ($unions as $union) {
        $conjoint = $union['spouse'] !== null ? person_full_name($union['spouse']) : '';
        $mariage  = $conjoint !== '' ? 'Mariage avec ' . $conjoint : 'Mariage';
        if (trim((string) ($union['family']['marriage_place'] ?? '')) !== '') {
            $mariage .= ' à ' . trim((string) $union['family']['marriage_place']);
        }
        $ajouter((string) ($union['family']['marriage_date'] ?? ''), $mariage);

        foreach ($union['children'] as $child) {
            $ajouter(
                (string) ($child['birth_date'] ?? ''),
                'Naissance : ' . person_full_name($child),
                '/arbre/' . (int) $child['id']
            );
        }
    }

    $deces = 'Décès';
    if (trim((string) ($person['death_place'] ?? '')) !== '') {
        $deces .= ' à ' . trim((string) $person['death_place']);
    }
    $ajouter((string) ($person['death_date'] ?? ''), $deces);

    usort($reperes, static fn (array $a, array $b): int => $a['tri'] <=> $b['tri']);

    return $reperes;
}

/**
 * Autres personnes portant le même nom de famille, visibles par le visiteur.
 * Sert à repérer les homonymes et les branches cousines.
 */
function person_homonyms(array $person, int $limit = 6): array
{
    $nom = trim((string) $person['last_name']);
    if ($nom === '') {
        return [];
    }

    $params = [':nom' => $nom, ':moi' => (int) $person['id']];
    $clause = visibility_clause('person', 'p', $params);

    return db_all(
        "SELECT p.* FROM persons p
         WHERE {$clause} AND p.id <> :moi AND TRIM(p.last_name) = :nom COLLATE NOCASE
         ORDER BY p.birth_year IS NULL, p.birth_year, p.first_name
         LIMIT " . max(1, $limit),
        $params
    );
}

/** Année de naissance d'une personne (colonne ou texte). */
function person_birth_year(array $person): ?int
{
    if (isset($person['birth_year']) && $person['birth_year'] !== null) {
        return (int) $person['birth_year'];
    }
    return extract_year($person['birth_date'] ?? null);
}

// ---------------------------------------------------------------------------
// Articles
// ---------------------------------------------------------------------------

/**
 * Condition SQL des articles visibles par le visiteur courant : publiés,
 * date de publication atteinte, et droits de visibilité respectés.
 */
function article_visible_clause(string $alias, array &$params): string
{
    $clause = visibility_clause('article', $alias, $params);
    static $n = 0;
    $n++;
    $params[':anow' . $n] = time();
    return "({$alias}.status = 'publie' AND {$alias}.published_at <= :anow{$n} AND {$clause})";
}

/** Article visible par le visiteur (les admins peuvent aussi prévisualiser un brouillon). */
function find_viewable_article(string $slug): ?array
{
    $article = db_one('SELECT * FROM articles WHERE slug = ?', [$slug]);
    if (!$article) {
        return null;
    }
    if (is_admin()) {
        return $article;
    }
    if ($article['status'] !== 'publie' || (int) $article['published_at'] > time()) {
        return null;
    }
    $compte = current_account();
    $mienne = proposition_mienne($compte, 'article', $article);
    if (!$mienne && !can_view((string) $article['visibility'], content_branch_ids('article', (int) $article['id']))) {
        return null;
    }
    if (!proposition_visible($compte, 'article', $article)) {
        return null;
    }
    return $article;
}

/** Image à la une d'un article : [url miniature, url grande] ou null. */
function article_image(array $article): ?array
{
    if (empty($article['image_id'])) {
        return null;
    }
    $doc = db_one("SELECT id, thumb_path, visibility, vintage_aspect FROM documents WHERE id = ? AND kind = 'photo'", [(int) $article['image_id']]);
    if (!$doc) {
        return null;
    }
    // Un article public peut illustrer une photo réservée aux familles : le
    // visiteur n'a pas le droit de la charger (le serveur la refuserait),
    // donc on ne lui montre pas d'image cassée, mais la plaque de repli.
    if (!can_view((string) $doc['visibility'], content_branch_ids('document', (int) $doc['id']))) {
        return null;
    }
    return [
        'thumb'  => !empty($doc['thumb_path']) ? '/media/miniature/' . (int) $doc['id'] : '/f/' . (int) $doc['id'],
        'full'   => '/f/' . (int) $doc['id'],
        // Aspect « photo ancienne » (filtre d'affichage seulement, 1.32.0).
        'aspect' => aspect_photo_classe($doc),
    ];
}

/** Derniers articles visibles (page d'accueil). */
function recent_articles(int $limit = 4): array
{
    $params = [];
    $clause = article_visible_clause('a', $params);
    $lignes = db_all(
        "SELECT a.*, COALESCE(NULLIF(TRIM(u.display_name), ''), u.username) AS auteur
         FROM articles a LEFT JOIN accounts u ON u.id = a.author_id
         WHERE {$clause}
         ORDER BY a.published_at DESC, a.id DESC LIMIT " . max(1, $limit),
        $params
    );

    // Nom de la branche pour les articles réservés (surtitre de la carte).
    return articles_avec_branches($lignes);
}

/** Catégories d'un ou plusieurs types, avec le nombre de contenus visibles. */
function categories_avec_compteur_kinds(array $kinds): array
{
    $lignes = [];
    foreach (categories_pour_kinds($kinds) as $categorie) {
        $nombre = 0;
        foreach ($kinds as $kind) {
            $nombre += category_compteur((string) $kind, (int) $categorie['id']);
        }
        if ($nombre > 0) {
            $lignes[] = $categorie + ['nb' => $nombre];
        }
    }
    return $lignes;
}

/**
 * Catégorie demandée dans l'adresse, si elle s'applique bien à ce type.
 *
 * Une valeur inconnue est ignorée (aucun filtre) : on n'invente jamais un
 * filtre à partir d'une adresse fabriquée.
 */
function category_filtre_demande(string|array $kind, string $param = 'categorie'): int
{
    $id = query_int($param);
    if ($id <= 0) {
        return 0;
    }
    $categorie = find_category($id);
    if ($categorie === null) {
        return 0;
    }
    foreach ((array) $kind as $type) {
        if (category_applicable($categorie, (string) $type)) {
            return $id;
        }
    }

    return 0;
}

/**
 * Condition SQL « ce contenu appartient à cette catégorie » (ou rien).
 *
 * @param array $params paramètres de la requête, complétés par référence
 */
function category_where(string $alias, ?int $categorieId, array &$params, string $kindSql): string
{
    if ($categorieId === null || $categorieId <= 0) {
        return '';
    }
    $params[':catf'] = $categorieId;
    $params[':catk'] = $kindSql;

    return "EXISTS (SELECT 1 FROM category_links cl
                     WHERE cl.category_id = :catf
                       AND cl.kind = :catk
                       AND cl.content_id = {$alias}.id)";
}
