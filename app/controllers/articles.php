<?php
/**
 * Rubrique « Articles » : fil d'actualité de la famille.
 * Partie publique (liste, lecture) et administration.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Partie publique
// ---------------------------------------------------------------------------

/**
 * Liste des articles (/articles), avec deux filtres d'édition :
 *   ?branche=3       articles rattachés à une branche
 *   ?decennie=1960   articles qui racontent cette décennie
 *
 * Les deux filtres sont de simples liens : aucun JavaScript, et la
 * visibilité est toujours appliquée par article_visible_clause().
 */
function public_articles(): void
{
    $params = [];
    $where  = [article_visible_clause('a', $params)];

    $category = query('categorie');
    if ($category !== '') {
        $where[] = 'a.category = :categorie';
        $params[':categorie'] = $category;
    }

    $branche = query_int('branche');
    if ($branche > 0) {
        $where[] = "EXISTS (
            SELECT 1 FROM content_branches cb
            WHERE cb.kind = 'article' AND cb.content_id = a.id AND cb.branch_id = :branche
        )";
        $params[':branche'] = $branche;
    }

    // Le filtre par branche est fait en SQL ; celui par décennie dépend du
    // millésime raconté par l'article, donc du texte : on liste d'abord une
    // version légère des articles (quatre colonnes), puis on découpe.
    $whereSql = implode(' AND ', $where);

    $legers = db_all(
        "SELECT a.id, a.title, a.summary, a.published_at FROM articles a
         WHERE {$whereSql}
         ORDER BY a.published_at DESC, a.id DESC",
        $params
    );

    $decennies = editions_decennies($legers);
    $decennie  = query_int('decennie');
    if ($decennie > 0 && !array_key_exists($decennie, $decennies)) {
        $decennie = 0; // décennie inconnue : on n'invente pas de filtre
    }
    $legers = editions_filtrer_decennie($legers, $decennie);

    $total = count($legers);
    $pg    = pagination($total, 10, max(1, query_int('page', 1)));

    // Les lignes complètes ne sont chargées que pour la page affichée.
    $idsPage  = array_map(
        static fn (array $ligne): int => (int) $ligne['id'],
        array_slice($legers, (int) $pg['offset'], (int) $pg['perPage'])
    );
    $articles = articles_par_ids($idsPage);

    $catParams = [];
    $catClause = article_visible_clause('a', $catParams);
    $categories = db_all(
        "SELECT a.category AS name, COUNT(*) AS nb FROM articles a
         WHERE a.category <> '' AND {$catClause}
         GROUP BY a.category ORDER BY a.category COLLATE NOCASE",
        $catParams
    );

    render('public/articles', [
        'titre'        => 'Articles',
        'articles'     => $articles,
        'categories'   => $categories,
        'categorie'    => $category,
        'pagination'   => $pg,
        'total'        => $total,
        'branche'      => $branche,
        'decennie'     => $decennie,
        'decennies'    => $decennies,
        'branches'     => editions_branches(),
        'filtreVide'   => $total === 0 && ($branche > 0 || $decennie > 0),
    ]);
}

/**
 * Les articles d'une page, dans l'ordre demandé, avec le nom de leur auteur.
 *
 * @param list<int> $ids
 * @return list<array>
 */
function articles_par_ids(array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $marques = implode(',', array_fill(0, count($ids), '?'));
    $lignes  = db_all(
        "SELECT a.*, COALESCE(NULLIF(TRIM(u.display_name), ''), u.username) AS auteur
         FROM articles a
         LEFT JOIN accounts u ON u.id = a.author_id
         WHERE a.id IN ({$marques})",
        array_map('intval', $ids)
    );

    $parId = [];
    foreach ($lignes as $ligne) {
        $parId[(int) $ligne['id']] = $ligne;
    }

    $ordonnes = [];
    foreach ($ids as $id) {
        if (isset($parId[$id])) {
            $ordonnes[] = $parId[$id];
        }
    }

    return articles_avec_branches($ordonnes);
}

/** Lecture d'un article (/articles/{slug}). */
function public_article_detail(array $params): void
{
    $article = find_viewable_article((string) ($params['slug'] ?? ''));
    if (!$article) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Article introuvable',
            'message' => 'Cet article n’existe pas, ou il n’est pas accessible avec votre compte.',
        ]);
        return;
    }

    $otherParams = [];
    $otherClause = article_visible_clause('a', $otherParams);
    $others = db_all(
        "SELECT a.*, COALESCE(NULLIF(TRIM(u.display_name), ''), u.username) AS auteur
         FROM articles a LEFT JOIN accounts u ON u.id = a.author_id
         WHERE a.id <> :id AND {$otherClause}
         ORDER BY a.published_at DESC, a.id DESC LIMIT 3",
        $otherParams + [':id' => (int) $article['id']]
    );

    $articleIds = content_branch_ids('article', (int) $article['id']);
    $noms       = [];
    foreach ($articleIds as $branchId) {
        $branch = find_branch($branchId);
        if ($branch) {
            $noms[] = (string) $branch['name'];
        }
    }

    $auteur = db_value(
        "SELECT COALESCE(NULLIF(TRIM(display_name), ''), username) FROM accounts WHERE id = ?",
        [(int) ($article['author_id'] ?? 0)],
        ''
    );

    render('public/article', [
        'titre'         => $article['title'],
        'article'       => $article,
        'image'         => article_image($article),
        'branches'      => $articleIds,
        'branchesNoms'  => $noms,
        'auteur'        => (string) $auteur,
        'millesime'     => article_millesime($article),
        'decennie'      => article_decennie($article),
        'others'        => $others,
    ]);
}

// ---------------------------------------------------------------------------
// Administration
// ---------------------------------------------------------------------------

/** Liste de tous les articles (brouillons et programmés compris). */
function admin_articles(): void
{
    require_admin();

    $articles = db_all(
        'SELECT a.*, u.username AS auteur FROM articles a
         LEFT JOIN accounts u ON u.id = a.author_id
         ORDER BY a.published_at DESC, a.id DESC'
    );

    render('admin/articles', [
        'titre'    => 'Articles',
        'articles' => $articles,
    ], 'layout_admin');
}

/** Formulaire de création / modification. */
function admin_article_form(array $params = []): void
{
    require_admin();

    $id      = (int) ($params['id'] ?? 0);
    $article = $id > 0 ? db_one('SELECT * FROM articles WHERE id = ?', [$id]) : null;
    if ($id > 0 && !$article) {
        http_response_code(404);
        render('erreur', ['titre' => 'Article introuvable', 'message' => 'Cet article n’existe pas.']);
        return;
    }

    render('admin/article_form', [
        'titre'           => $article ? 'Modifier « ' . $article['title'] . ' »' : 'Nouvel article',
        'article'         => $article,
        'branches'        => branches_list(true),
        'articleBranches' => $article ? content_branch_ids('article', $id) : [],
        'photos'          => db_all("SELECT id, title FROM documents WHERE kind = 'photo' AND is_attachment = 0 ORDER BY created_at DESC LIMIT 200"),
        'categories'      => db_all("SELECT DISTINCT category FROM articles WHERE category <> '' ORDER BY category COLLATE NOCASE"),
        'erreurs'         => form_errors(),
        'old'             => form_old(),
    ], 'layout_admin');
}

/** Enregistrement d'un article. */
function admin_article_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $article = $id > 0 ? db_one('SELECT * FROM articles WHERE id = ?', [$id]) : null;
    $back    = $id > 0 ? '/admin/articles/' . $id : '/admin/articles/nouveau';

    if ($id > 0 && !$article) {
        http_response_code(404);
        render('erreur', ['titre' => 'Article introuvable', 'message' => 'Cet article n’existe pas.']);
        return;
    }

    $old = [
        'title'        => post('title'),
        'slug'         => strtolower(post('slug')),
        'summary'      => post('summary'),
        'body'         => post('body'),
        'category'     => post('category'),
        'image_id'     => (int) post('image_id'),
        'modele'       => post('modele'),
        'visibility'   => post('visibility'),
        'branches'     => array_map('intval', post_array('branches')),
        'status'       => post('status'),
        'published_at' => post('published_at'),
        'notify'       => post('notify'),
    ];

    $errors = [];
    if ($old['title'] === '' || mb_strlen($old['title']) > 200) {
        $errors[] = 'Le titre est obligatoire (200 caractères maximum).';
    }
    if ($old['slug'] === '') {
        $old['slug'] = slugify($old['title']);
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $old['slug'])) {
        $errors[] = 'L’adresse de l’article doit contenir uniquement des lettres minuscules, chiffres et tirets.';
    }
    if (db_one('SELECT id FROM articles WHERE slug = ? AND id <> ?', [$old['slug'], $id])) {
        $errors[] = 'Un autre article utilise déjà cette adresse.';
    }
    if (mb_strlen($old['summary']) > 500) {
        $errors[] = 'Le résumé est limité à 500 caractères.';
    }
    if (mb_strlen($old['category']) > 60) {
        $errors[] = 'La rubrique est limitée à 60 caractères.';
    }
    if (mb_strlen($old['body']) > 200000) {
        $errors[] = 'Le texte est trop long.';
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }

    $status = $old['status'] === 'publie' ? 'publie' : 'brouillon';

    // Date de publication : champ « datetime-local » (AAAA-MM-JJTHH:MM), vide = maintenant.
    $publishedAt = time();
    if ($old['published_at'] !== '') {
        $parsed = strtotime($old['published_at']);
        if ($parsed === false) {
            $errors[] = 'La date de publication n’est pas valide.';
        } else {
            $publishedAt = $parsed;
        }
    }

    // Modèle de mise en page : une valeur inconnue n'est jamais enregistrée
    // (elle retombe sur « comme le site »).
    $modele = array_key_exists($old['modele'], article_modeles()) ? $old['modele'] : '';
    if ($modele !== $old['modele']) {
        $errors[] = 'Modèle de mise en page inconnu : choisissez-en un dans la liste.';
    }

    $imageId = $old['image_id'] > 0 ? $old['image_id'] : null;
    if ($imageId !== null && !db_one("SELECT id FROM documents WHERE id = ? AND kind = 'photo'", [$imageId])) {
        $errors[] = 'L’image choisie n’existe pas.';
    }

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    // « Prévenir les inscrits » : coché par défaut à la première publication,
    // décochable, et sans objet pour un brouillon.
    $prevenir = $status === 'publie' && $old['notify'] === '1';

    $data = [
        'title'        => $old['title'],
        'slug'         => $old['slug'],
        'summary'      => $old['summary'],
        'body'         => $old['body'],
        'category'     => $old['category'],
        'image_id'     => $imageId,
        'modele'       => $modele,
        'visibility'   => $visibility,
        'status'       => $status,
        'published_at' => $publishedAt,
        'notify'       => $prevenir ? 1 : 0,
        'updated_at'   => time(),
    ];

    if ($id > 0) {
        db_update('articles', $id, $data);
        $articleId = $id;
    } else {
        $data['author_id']  = (int) current_account()['id'];
        $data['created_at'] = time();
        $articleId = db_insert('articles', $data);
    }

    set_content_branches('article', $articleId, $visibility === 'branches' ? $branches : []);

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'article_modifie' : 'article_ajoute',
        $old['title']
    );

    // Repasser un article en brouillon annule les envois encore en attente :
    // personne ne reçoit un article qui n'est plus publié.
    if ($status !== 'publie') {
        $annules = courriel_annuler_article($articleId);
        if ($annules > 0) {
            log_activity((int) current_account()['id'], 'courriels_annules',
                $annules . ' courriel(s) en attente annulé(s) pour « ' . $old['title'] . ' »');
        }
    }

    // Publication immédiate : on met les notifications en file tout de suite.
    // Une date future passera par la commande d'envoi, le jour venu.
    // On ne prévient que sur une VRAIE publication : pas de courriel pour un
    // vieil article que l'on corrige, ni à chaque enregistrement.
    $etaitPublie = $article !== null
        && (string) $article['status'] === 'publie'
        && (int) $article['published_at'] <= time();
    $transition = !$etaitPublie;

    if ($status === 'publie' && $publishedAt <= time() && $prevenir && $transition) {
        $complet = db_one('SELECT * FROM articles WHERE id = ?', [$articleId]);
        if ($complet !== null) {
            $mis = courriel_notifier_article($complet);
            if ($mis > 0) {
                log_activity((int) current_account()['id'], 'courriels_articles',
                    $mis . ' notification(s) mise(s) en file pour « ' . $old['title'] . ' »');
            }
        }
    }

    flash('succes', $id > 0 ? 'L’article a été mis à jour.' : 'L’article a été créé.');
    redirect('/admin/articles');
}

/** Suppression d'un article. */
function admin_article_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $article = db_one('SELECT * FROM articles WHERE id = ?', [$id]);
    if (!$article) {
        flash('erreur', 'Article introuvable.');
        redirect('/admin/articles');
    }

    db_transaction(static function () use ($id): void {
        db_exec("DELETE FROM content_branches WHERE kind = 'article' AND content_id = ?", [$id]);
        db_exec('DELETE FROM articles WHERE id = ?', [$id]);
    });

    log_activity((int) current_account()['id'], 'article_supprime', (string) $article['title']);
    flash('succes', 'L’article « ' . $article['title'] . ' » a été supprimé.');
    redirect('/admin/articles');
}
