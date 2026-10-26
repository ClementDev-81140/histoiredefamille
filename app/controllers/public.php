<?php
/**
 * Pages publiques : accueil, documents, pages d'informations, arbre.
 * (Le contenu affiché dépend de la session : un visiteur non connecté ne
 * voit que les contenus publics.)
 */

declare(strict_types=1);

/** Page d'accueil. */
function public_accueil(): void
{
    $documents = recent_documents(6);
    $photos    = recent_documents(4, 'photo');

    $params = [];
    $clause = visibility_clause('page', 'p', $params);
    $pages  = db_all(
        "SELECT p.slug, p.title, p.body FROM pages p
         WHERE p.is_published = 1 AND {$clause}
         ORDER BY p.show_in_nav DESC, p.position, p.title LIMIT 3",
        $params
    );

    render('public/accueil', [
        'titre'      => null,
        'documents'  => $documents,
        'photos'     => $photos,
        'pages'      => $pages,
        'personnes'  => recent_persons(4),
        'nbDocs'     => count_visible('documents', 'document'),
        'nbPages'    => count_visible('pages', 'page'),
        'nbPersonnes' => count_visible('persons', 'person'),
    ]);
}

/** Liste des documents (photos, vidéos, PDF, fichiers). */
function public_documents(): void
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);

    $where = ['d.is_attachment = 0', $clause];

    $q = query('q');
    if ($q !== '') {
        $where[] = '(d.title LIKE :q OR d.description LIKE :q OR EXISTS (
            SELECT 1 FROM document_tags dt JOIN tags t ON t.id = dt.tag_id
            WHERE dt.document_id = d.id AND t.name LIKE :q
        ))';
        $params[':q'] = '%' . $q . '%';
    }

    $categoryId = query_int('categorie');
    if ($categoryId > 0) {
        $where[] = 'd.category_id = :categorie';
        $params[':categorie'] = $categoryId;
    }

    $type = query('type');
    if (in_array($type, ['photo', 'video', 'document'], true)) {
        $where[] = 'd.kind = :type';
        $params[':type'] = $type;
    }

    $whereSql = implode(' AND ', $where);
    $total    = (int) db_value("SELECT COUNT(*) FROM documents d WHERE {$whereSql}", $params, 0);
    $pg       = pagination($total, 0, max(1, query_int('page', 1)));

    $sql = "SELECT d.* FROM documents d WHERE {$whereSql}
            ORDER BY (d.doc_date_sort IS NULL), d.doc_date_sort DESC, d.created_at DESC
            LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
    $documents = db_all($sql, $params);

    // Catégories ayant au moins un document visible (évite les listes vides).
    $catParams = [];
    $catClause = visibility_clause('document', 'd', $catParams);
    $categories = db_all(
        "SELECT c.id, c.name, COUNT(d.id) AS nb
         FROM categories c
         JOIN documents d ON d.category_id = c.id AND d.is_attachment = 0
         WHERE {$catClause}
         GROUP BY c.id, c.name
         ORDER BY c.position, c.name",
        $catParams
    );

    render('public/documents', [
        'titre'      => 'Documents',
        'documents'  => $documents,
        'categories' => $categories,
        'pagination' => $pg,
        'q'          => $q,
        'categorie'  => $categoryId,
        'type'       => $type,
        'total'      => $total,
    ]);
}

/** Fiche d'un document. */
function public_document_detail(array $params): void
{
    $doc = find_viewable_document((int) ($params['id'] ?? 0));
    if (!$doc) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Document introuvable',
            'message' => 'Ce document n’existe pas, ou il n’est pas accessible avec votre compte.',
        ]);
        return;
    }

    $category = $doc['category_id'] ? find_category((int) $doc['category_id']) : null;

    $related = [];
    if ($doc['category_id']) {
        $relParams = [];
        $relClause = visibility_clause('document', 'd', $relParams);
        $related = db_all(
            "SELECT d.* FROM documents d
             WHERE d.category_id = :cat AND d.id <> :id AND d.is_attachment = 0 AND {$relClause}
             ORDER BY d.created_at DESC LIMIT 4",
            $relParams + [':cat' => (int) $doc['category_id'], ':id' => (int) $doc['id']]
        );
    }

    render('public/document', [
        'titre'    => $doc['title'],
        'doc'      => $doc,
        'category' => $category,
        'tags'     => document_tags($doc),
        'branches' => content_branch_ids('document', (int) $doc['id']),
        'related'  => $related,
    ]);
}

/** Page d'informations (/pages/{slug}). */
function public_page(array $params): void
{
    $page = db_one('SELECT * FROM pages WHERE slug = ?', [(string) ($params['slug'] ?? '')]);
    if (!$page || !find_viewable_page((int) $page['id'])) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Page introuvable',
            'message' => 'Cette page n’existe pas ou n’est pas accessible.',
        ]);
        return;
    }

    render('public/page', [
        'titre' => $page['title'],
        'page'  => $page,
    ]);
}

/** Répertoire de l'arbre généalogique. */
function public_arbre(): void
{
    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $where  = [$clause];

    $q = query('q');
    if ($q !== '') {
        $where[] = "(p.first_name LIKE :q OR p.last_name LIKE :q OR (p.first_name || ' ' || p.last_name) LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    $letter = strtoupper(query('lettre'));
    if ($letter !== '' && preg_match('/^[A-Z]$/', $letter)) {
        $where[] = "UPPER(SUBSTR(TRIM(p.last_name), 1, 1)) = :lettre";
        $params[':lettre'] = $letter;
    }

    $whereSql = implode(' AND ', $where);
    $total    = (int) db_value("SELECT COUNT(*) FROM persons p WHERE {$whereSql}", $params, 0);
    $pg       = pagination($total, 0, max(1, query_int('page', 1)));

    $persons = db_all(
        "SELECT p.* FROM persons p WHERE {$whereSql}
         ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    // Personne sélectionnée pour l'explorateur d'arbre.
    $rootId  = query_int('racine');
    $root    = $rootId > 0 ? find_viewable_person($rootId) : null;

    // Lettres disponibles.
    $letterParams = [];
    $letterClause = visibility_clause('person', 'p', $letterParams);
    $letters = db_all(
        "SELECT DISTINCT UPPER(SUBSTR(TRIM(p.last_name), 1, 1)) AS l
         FROM persons p WHERE {$letterClause} AND TRIM(p.last_name) <> ''
         ORDER BY l",
        $letterParams
    );

    render('public/arbre', [
        'titre'      => 'Arbre généalogique',
        'persons'    => $persons,
        'pagination' => $pg,
        'q'          => $q,
        'lettre'     => $letter,
        'letters'    => array_map(static fn ($r) => $r['l'], $letters),
        'root'       => $root,
        'total'      => $total,
    ]);
}

/** Fiche d'une personne. */
function public_personne(array $params): void
{
    $person = find_viewable_person((int) ($params['id'] ?? 0));
    if (!$person) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Personne introuvable',
            'message' => 'Cette fiche n’existe pas ou n’est pas accessible avec votre compte.',
        ]);
        return;
    }

    $parents  = filter_visible_persons(person_parents((int) $person['id']));
    $siblings = filter_visible_persons(person_siblings((int) $person['id']));

    // Conjoints et enfants, filtrés selon leur propre visibilité.
    $unions = [];
    foreach (person_unions((int) $person['id']) as $union) {
        $spouse   = $union['spouse'] ? filter_visible_persons([$union['spouse']]) : [];
        $children = filter_visible_persons($union['children']);
        $unions[] = [
            'family'       => $union['family'],
            'spouse'       => $spouse[0] ?? null,
            'children'     => $children,
        ];
    }

    $photo = null;
    if (!empty($person['photo_document_id'])) {
        $candidate = find_viewable_document((int) $person['photo_document_id']);
        if ($candidate) {
            $photo = $candidate;
        }
    }

    render('public/personne', [
        'titre'    => person_full_name($person),
        'person'   => $person,
        'parents'  => $parents,
        'siblings' => $siblings,
        'unions'   => $unions,
        'photo'    => $photo,
        'branches' => content_branch_ids('person', (int) $person['id']),
    ]);
}

/**
 * API JSON pour l'explorateur d'arbre (JS léger).
 * Renvoie les ascendants et descendants visibles d'une personne.
 */
function api_arbre(array $params): void
{
    $person = find_viewable_person((int) ($params['id'] ?? 0));
    if (!$person) {
        json_response(['erreur' => 'Personne introuvable'], 404);
    }

    $maxAsc  = min(6, max(1, (int) ($_GET['ascendants'] ?? 3)));
    $maxDesc = min(4, max(0, (int) ($_GET['descendants'] ?? 2)));

    $node = static function (array $p): array {
        return [
            'id'     => (int) $p['id'],
            'nom'    => person_full_name($p),
            'dates'  => person_dates($p),
            'sexe'   => $p['sex'] ?? '',
            'photo'  => !empty($p['photo_document_id']) ? '/f/' . (int) $p['photo_document_id'] : null,
            'lien'   => '/arbre/' . (int) $p['id'],
        ];
    };

    // --- Ascendants (par niveaux) ------------------------------------------
    $ascendants = [];
    $visited    = [(int) $person['id'] => true];
    $current    = [$person];

    for ($level = 0; $level < $maxAsc; $level++) {
        $next = [];
        foreach ($current as $child) {
            foreach (person_parents((int) $child['id']) as $parent) {
                $id = (int) $parent['id'];
                if (isset($visited[$id]) || !filter_visible_persons([$parent])) {
                    continue;
                }
                $visited[$id]        = true;
                $ascendants[$level][] = $node($parent);
                $next[]              = $parent;
            }
        }
        if ($next === []) {
            break;
        }
        $current = $next;
    }

    // --- Descendants (par niveaux) -----------------------------------------
    $descendants = [];
    $visited     = [(int) $person['id'] => true];
    $current     = [$person];

    for ($level = 0; $level < $maxDesc; $level++) {
        $next = [];
        foreach ($current as $parent) {
            foreach (person_unions((int) $parent['id']) as $union) {
                foreach ($union['children'] as $child) {
                    $id = (int) $child['id'];
                    if (isset($visited[$id]) || !filter_visible_persons([$child])) {
                        continue;
                    }
                    $visited[$id]         = true;
                    $descendants[$level][] = $node($child);
                    $next[]               = $child;
                }
            }
        }
        if ($next === []) {
            break;
        }
        $current = $next;
    }

    // Conjoints de la personne sélectionnée.
    $spouses = [];
    foreach (person_unions((int) $person['id']) as $union) {
        if ($union['spouse'] && filter_visible_persons([$union['spouse']])) {
            $spouses[] = $node($union['spouse']);
        }
    }

    json_response([
        'personne'    => $node($person),
        'conjoints'   => $spouses,
        'ascendants'  => $ascendants,
        'descendants' => $descendants,
    ]);
}
