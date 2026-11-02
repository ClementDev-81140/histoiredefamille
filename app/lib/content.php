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

/** Dates de vie : « 1901 – 1975 », « 1901 », « – 1975 », ''. */
function person_dates(array $person): string
{
    $birth = trim((string) ($person['birth_date'] ?? ''));
    $death = trim((string) ($person['death_date'] ?? ''));
    if ($birth === '' && $death === '') {
        return '';
    }
    if ($death === '') {
        return $birth . ' –';
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
function videos_visible(int $limit = 0, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);

    $where = "d.kind = 'video' AND d.is_attachment = 0 AND {$clause}";

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

/** Catégories ordonnées. */
function categories_list(): array
{
    return db_all('SELECT * FROM categories ORDER BY position, name COLLATE NOCASE');
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

    if ($cache === null) {
        $cache = [];
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
function save_site_setting(string $key, string $value): void
{
    db_exec(
        'INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value',
        [$key, $value]
    );
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
    $visible = [];
    foreach ($persons as $person) {
        $id = (int) $person['id'];
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = can_view((string) $person['visibility'], content_branch_ids('person', $id));
        }
        if ($cache[$id]) {
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
    if (!can_view((string) $article['visibility'], content_branch_ids('article', (int) $article['id']))) {
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
    $doc = db_one("SELECT id, thumb_path FROM documents WHERE id = ? AND kind = 'photo'", [(int) $article['image_id']]);
    if (!$doc) {
        return null;
    }
    return [
        'thumb' => !empty($doc['thumb_path']) ? '/media/miniature/' . (int) $doc['id'] : '/f/' . (int) $doc['id'],
        'full'  => '/f/' . (int) $doc['id'],
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
