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
    // Un visiteur voit la Une du journal (1.31.0) : tout le contenu public, du
    // plus récent au plus ancien. L'accueil d'un compte connecté ne change pas
    // (1.29.0) : il est calculé juste après, comme avant.
    if (!is_logged_in()) {
        public_accueil_visiteur();
        return;
    }

    // Un bloc masqué dans « Composition des pages » n'est pas calculé : ni
    // requête, ni travail pour le Pi.
    $visible = static fn (string $cle): bool => composition_visible('public', $cle);

    $documents = $visible('documents') ? recent_documents(6) : [];
    $articles  = $visible('une') ? recent_articles(4) : [];

    $pages = [];
    if ($visible('lire')) {
        $params = [];
        $clause = visibility_clause('page', 'p', $params);
        $pages  = db_all(
            "SELECT p.slug, p.title, p.body FROM pages p
             WHERE p.is_published = 1 AND {$clause}
             ORDER BY p.show_in_nav DESC, p.position, p.title LIMIT 3",
            $params
        );
    }

    // Bloc « Dans la famille » : réservé aux comptes connectés, et jamais
    // calculé pour un visiteur (aucune requête, rien à filtrer).
    $dansLaFamille = is_logged_in() && $visible('famille') ? dans_la_famille() : [];

    // La une d'abord (1.29.0) : pour un compte connecté, l'article principal
    // est le plus récent qu'il peut lire. Il est cherché même si le bloc
    // « une » est masqué dans la composition : un accueil connecté ne commence
    // pas par une page sans article. Aucun article du tout : rien à montrer,
    // « Dans la famille » passe en tête.
    $uneArticle = null;
    if (is_logged_in()) {
        $uneArticle = $articles[0] ?? null;
        if ($uneArticle === null) {
            $derniers   = recent_articles(1);
            $uneArticle = $derniers[0] ?? null;
        }
    }

    // Une seule ligne de bienvenue, sous la date (layout.php) : le prénom, et
    // rien d'autre. Absente si le compte n'en a pas.
    $salutation = '';
    $compte     = current_account();
    if ($compte !== null) {
        $prenom = trim((string) ($compte['first_name'] ?? ''));
        if ($prenom === '') {
            $prenom = trim((string) ($compte['display_name'] ?? ''));
        }
        if ($prenom !== '') {
            $salutation = 'Bonjour ' . mb_substr($prenom, 0, 40);
        }
    }

    // Les chiffres de l'ancien en-tête ne sont plus affichés (1.31.0) : le bloc
    // a été retiré de la composition, `composition_visible()` répond donc faux
    // et plus aucune requête de comptage n'est faite.

    render('public/accueil', [
        'titre'      => null,
        'documents'  => $documents,
        'articles'   => $articles,
        'pages'      => $pages,
        'dansLaFamille' => $dansLaFamille,
        'uneArticle' => $uneArticle,
        'salutation' => $salutation,
    ]);
}

/**
 * Accueil d'un **visiteur** : la Une du journal (1.31.0).
 *
 * Une seule page pour tout le contenu public — articles, photos, vidéos,
 * documents, recettes et pages d'information — du plus récent au plus ancien,
 * le plus récent en grand. Aucun lien vers autre chose que du contenu public
 * et la connexion.
 *
 * Le filtrage n'est pas refait ici : `une_publique_page()` passe par
 * `visibility_clause()`, exactement comme les autres pages du site.
 */
function public_accueil_visiteur(): void
{
    $page    = max(1, query_int('page', 1));
    $parPage = une_publique_par_page();

    // La une : le contenu choisi par un administrateur (« Composition des
    // pages »), sinon le contenu public le plus récent. Le choix est revérifié
    // ici : un contenu repassé en privé ne s'affiche pas.
    $une        = une_publique_une();
    $uneChoisie = $une !== null;
    $liste      = une_publique_page($parPage, $page, $une);

    // Sans choix d'administrateur, c'est le premier de la liste qui tient la
    // une : on le retire de la grille pour ne pas le montrer deux fois.
    $items = $liste['items'];
    if ($une === null && $items !== []) {
        $une   = array_shift($items);
        // Le total ne compte plus la une : la pagination reste juste.
        $liste['total'] = max(0, $liste['total'] - 1);
        $liste['pagination'] = pagination($liste['total'], $parPage, $page);
    }

    render('public/accueil', [
        'titre'          => null,
        'salutation'     => '',
        'unePublique'    => $une,
        'uneChoisie'     => $uneChoisie,
        'itemsPubliques' => $items,
        'totalPublique'  => $liste['total'],
        'pagination'     => $liste['pagination'],
        'pagePublique'   => $page,
        'parPage'        => $parPage,
        'phraseAccueil'  => apparence_accueil_phrase(),
    ]);
}

/** Liste des documents (photos, vidéos, PDF, fichiers). */
function public_documents(): void
{
    rediriger_ancien_param_theme();
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

    $categoryId = query_int('theme');
    if ($categoryId > 0) {
        // Thèmes du document (1.32.0) : la liaison porte le type de la
        // fiche (document, photo ou vidéo).
        $where[] = 'EXISTS (SELECT 1 FROM category_links cl
                             WHERE cl.category_id = :categorie
                               AND cl.content_id = d.id
                               AND cl.kind = d.kind)';
        $params[':categorie'] = $categoryId;
    }

    // Filtre « Archives » (1.36.0) : archives seulement, journal seulement,
    // ou tout. La visibilité habituelle s'applique dans tous les cas.
    $archives = query('archives');
    if ($archives === '1') {
        $where[] = 'd.est_archive = 1';
    } elseif ($archives === '0') {
        $where[] = 'd.est_archive = 0';
    } else {
        $archives = '';
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

    // Thèmes ayant au moins un contenu visible par la personne (le
    // compteur ne compte que ce qu'elle peut voir : aucune fuite).
    $categories = categories_avec_compteur_kinds(['document', 'photo', 'video']);

    render('public/documents', [
        'titre'      => 'Documents',
        'documents'  => $documents,
        'categories' => $categories,
        'pagination' => $pg,
        'q'          => $q,
        'categorie'  => $categoryId,
        'theme'      => $categoryId,
        'type'       => $type,
        'archives'   => $archives,
        // Dossiers d'archives : seulement ceux qui ont quelque chose de
        // visible pour ce compte (nom et nombre ne fuient jamais).
        'dossiers'   => dossiers_visibles(),
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

    // Dossier du document, seulement s'il a quelque chose de visible pour
    // ce compte (sinon le nom du dossier ne doit pas fuir).
    $dossierDoc = null;
    if ((int) ($doc['dossier_id'] ?? 0) > 0 && dossier_visible_pour_compte((int) $doc['dossier_id'])) {
        $dossierDoc = dossier_par_id((int) $doc['dossier_id']);
    }

    render('public/document', [
        'titre'    => $doc['title'],
        'doc'      => $doc,
        'category' => $category,
        'themes'   => themes_noms_pour((string) $doc['kind'], (int) $doc['id']),
        'dossier'  => $dossierDoc,
        'tags'     => document_tags($doc),
        'branches' => content_branch_ids('document', (int) $doc['id']),
        'related'  => $related,
        'personnes' => document_persons((int) $doc['id']),
        'albums'    => albums_of_document((int) $doc['id']),
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
    // L'arbre généalogique n'est plus public : il s'ouvre une fois connecté.
    require_login();

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

    // --- Le chêne gravé (1.33.0) -------------------------------------------
    // La personne centrale vient de l'adresse (`?racine=`) ; si elle n'existe
    // pas, ou si le compte n'a pas le droit de la voir, on retombe **sans rien
    // dire** sur la personne par défaut : la page ne laisse jamais deviner
    // qu'une fiche existe.
    $rootId = query_int('racine');
    $root   = $rootId > 0 ? find_viewable_person($rootId) : null;
    if ($root === null) {
        $root = arbre_chene_racine_defaut();
    }

    $tout   = query('plus') === '1';
    $arbre  = $root !== null ? arbre_chene_donnees((int) $root['id'], $tout) : null;
    $choix  = arbre_chene_choix($root !== null ? (int) $root['id'] : 0);
    $defaut = arbre_chene_racine_defaut();

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
        'arbre'      => $arbre,
        'choix'      => $choix,
        'tout'       => $tout,
        'defautId'   => $defaut !== null ? (int) $defaut['id'] : 0,
        'total'      => $total,
    ]);
}

/** Fiche d'une personne. */
function public_personne(array $params): void
{
    require_login();

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

    // Famille élargie : chaque personne est filtrée selon sa propre visibilité.
    $grandsParents = filter_visible_persons(person_grandparents((int) $person['id']));
    $petitsEnfants = filter_visible_persons(person_grandchildren((int) $person['id']));
    $onclesTantes   = filter_visible_persons(person_uncles_aunts((int) $person['id']));
    $neveuxNieces   = filter_visible_persons(person_nephews_nieces((int) $person['id']));

    $compteFamille = current_account();
    $peutAjouterFamille = $compteFamille !== null
        && (($compteFamille['role'] ?? '') === 'admin' || (compte_est_journaliste($compteFamille) && !journaliste_avec_validation($compteFamille)))
        && journaliste_peut_modifier('person', $person, $compteFamille);
    $rechercheFamille = mb_substr(query('famille_recherche'), 0, 100);
    $suggestionsFamille = [];
    if ($peutAjouterFamille && trim($rechercheFamille) !== '') {
        $aChercher = famille_lien_normaliser($rechercheFamille);
        foreach (personne_saisie_suggestions() as $candidate) {
            if ((int) $candidate['id'] !== (int) $person['id']
                && str_contains(famille_lien_normaliser(person_full_name($candidate)), $aChercher)) {
                $suggestionsFamille[] = $candidate;
            }
        }
    }
    render('public/personne', [
        'peutAjouterFamille' => $peutAjouterFamille,
        'rechercheFamille' => $rechercheFamille,
        'suggestionsFamille' => $suggestionsFamille,
        'titre'         => person_full_name($person),
        'person'        => $person,
        'parents'       => $parents,
        'siblings'      => $siblings,
        'unions'        => $unions,
        'photo'         => $photo,
        'branches'      => content_branch_ids('person', (int) $person['id']),
        'grandsParents' => $grandsParents,
        'petitsEnfants' => $petitsEnfants,
        'onclesTantes'  => $onclesTantes,
        'neveuxNieces'  => $neveuxNieces,
        'reperes'       => person_reperes($person, $unions),
        'homonymes'     => person_homonyms($person, 6),
        'dossiers'      => dossiers_pour_personne((int) $person['id']),
        'peutCorriger'  => journaliste_peut_modifier('person', $person),
        'photos'        => person_photos((int) $person['id'], 12),
        'nbPhotos'      => person_photo_count((int) $person['id']),
    ]);
}

/**
 * API JSON pour l'explorateur d'arbre (JS léger).
 * Renvoie les ascendants et descendants visibles d'une personne.
 */
function api_arbre(array $params): void
{
    require_login();

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
