<?php
/**
 * « Éditions » de la gazette : filtre par branche et frise par décennie.
 *
 * Deux filtres très simples, sans JavaScript, ajoutés à /articles :
 *   ?branche=3        → les articles rattachés à la branche n° 3
 *   ?decennie=1960    → les articles qui parlent des années 1960
 *
 * Le millésime d'un article est celui qu'il raconte : la première année
 * écrite dans son titre ou son résumé (« Le mariage de 1965 » → 1965). À
 * défaut, on prend l'année de publication. La décennie est ce millésime
 * arrondi vers le bas (1965 → 1960).
 *
 * Les droits ne changent pas ici : les fonctions reçoivent des articles
 * déjà filtrés par visibility.php (article_visible_clause). Un visiteur ne
 * peut donc jamais obtenir un article non public, même en changeant
 * l'adresse à la main.
 */

declare(strict_types=1);

/** Année de publication d'un article (bornée à quelque chose de plausible). */
function article_annee_publication(array $article): int
{
    $horodatage = (int) ($article['published_at'] ?? 0);
    return $horodatage > 0 ? (int) date('Y', $horodatage) : (int) date('Y');
}

/**
 * Année que raconte l'article : la première année (1800-2099) du titre ou
 * du résumé, sinon l'année de publication.
 */
function article_millesime(array $article): int
{
    $texte = trim((string) ($article['title'] ?? '') . ' ' . (string) ($article['summary'] ?? ''));

    if ($texte !== '' && preg_match_all('/(?<!\d)(1[89]\d{2}|20\d{2})(?!\d)/u', $texte, $trouves) > 0) {
        $annee = (int) $trouves[1][0];
        if ($annee >= 1800 && $annee <= 2099) {
            return $annee;
        }
    }

    return article_annee_publication($article);
}

/** Décennie d'un article : 1965 → 1960. */
function article_decennie(array $article): int
{
    return intdiv(article_millesime($article), 10) * 10;
}

/** Libellé court d'une décennie : 1960 → « 1960 » ; 2020 → « 2020 ». */
function decennie_libelle(int $decennie): string
{
    return (string) $decennie;
}

/**
 * Décennies présentes dans une liste d'articles, avec leur nombre.
 *
 * @return array<int, int> décennie => nombre d'articles, de la plus récente
 *                         à la plus ancienne
 */
function editions_decennies(array $articles): array
{
    $compte = [];
    foreach ($articles as $article) {
        $decennie = article_decennie($article);
        $compte[$decennie] = ($compte[$decennie] ?? 0) + 1;
    }

    krsort($compte, SORT_NUMERIC);

    return $compte;
}

/**
 * Garde les articles d'une décennie donnée.
 *
 * @param int $decennie 0 = pas de filtre
 */
function editions_filtrer_decennie(array $articles, int $decennie): array
{
    if ($decennie <= 0) {
        return $articles;
    }

    return array_values(array_filter(
        $articles,
        static fn (array $article): bool => article_decennie($article) === $decennie
    ));
}

/**
 * Garde les articles rattachés à une branche.
 *
 * @param int $branche 0 = pas de filtre
 * @param array<int, list<int>> $branchesParArticle identifiants des branches,
 *        indexés par identifiant d'article
 */
function editions_filtrer_branche(array $articles, int $branche, array $branchesParArticle = []): array
{
    if ($branche <= 0) {
        return $articles;
    }

    return array_values(array_filter(
        $articles,
        static fn (array $article): bool => in_array(
            $branche,
            $branchesParArticle[(int) ($article['id'] ?? 0)] ?? [],
            true
        )
    ));
}

/**
 * Branches ayant au moins un article visible par la personne connectée.
 * Un visiteur ne voit donc que les branches qui ont un article public.
 *
 * @return list<array{id:int,nom:string,nb:int}>
 */
function editions_branches(): array
{
    $params = [];
    $clause = article_visible_clause('a', $params);

    $lignes = db_all(
        "SELECT b.id, b.name, COUNT(DISTINCT a.id) AS nb
         FROM branches b
         JOIN content_branches cb ON cb.branch_id = b.id AND cb.kind = 'article'
         JOIN articles a ON a.id = cb.content_id
         WHERE {$clause}
         GROUP BY b.id, b.name
         ORDER BY b.name COLLATE NOCASE",
        $params
    );

    return array_map(
        static fn (array $ligne): array => [
            'id'   => (int) $ligne['id'],
            'nom'  => (string) $ligne['name'],
            'nb'   => (int) $ligne['nb'],
        ],
        $lignes
    );
}

/**
 * Identifiants des branches de plusieurs articles, en une seule requête.
 *
 * @param list<int> $ids
 * @return array<int, list<int>>
 */
function editions_branches_par_article(array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $marques = implode(',', array_fill(0, count($ids), '?'));
    $lignes  = db_all(
        "SELECT content_id, branch_id FROM content_branches
         WHERE kind = 'article' AND content_id IN ({$marques})",
        array_map('intval', $ids)
    );

    $map = [];
    foreach ($lignes as $ligne) {
        $map[(int) $ligne['content_id']][] = (int) $ligne['branch_id'];
    }

    return $map;
}

/**
 * Ajoute à chaque article le nom de sa branche (« branche_nom »), quand il est
 * réservé à une branche précise et que cette branche a un nom. Une seule
 * requête, quel que soit le nombre d'articles.
 *
 * @param list<array> $articles
 * @return list<array>
 */
function articles_avec_branches(array $articles): array
{
    $ids = [];
    foreach ($articles as $article) {
        $ids[] = (int) ($article['id'] ?? 0);
    }

    $parArticle = editions_branches_par_article($ids);
    $noms       = [];
    if ($parArticle !== []) {
        $tousIds = [];
        foreach ($parArticle as $liste) {
            $tousIds = array_merge($tousIds, $liste);
        }
        $tousIds = array_values(array_unique($tousIds));
        if ($tousIds !== []) {
            $marques = implode(',', array_fill(0, count($tousIds), '?'));
            foreach (db_all("SELECT id, name FROM branches WHERE id IN ({$marques})", $tousIds) as $branche) {
                $noms[(int) $branche['id']] = (string) $branche['name'];
            }
        }
    }

    foreach ($articles as $index => $article) {
        $article['branche_nom'] = '';
        foreach ($parArticle[(int) ($article['id'] ?? 0)] ?? [] as $brancheId) {
            if (isset($noms[$brancheId])) {
                $article['branche_nom'] = $noms[$brancheId];
                break;
            }
        }
        $articles[$index] = $article;
    }

    return $articles;
}

/**
 * Décennies présentes parmi les articles visibles, en une seule requête
 * légère (titre, résumé, date : pas le corps des articles).
 *
 * @return array<int, int>
 */
function editions_decennies_visibles(): array
{
    $params = [];
    $clause = article_visible_clause('a', $params);

    $lignes = db_all(
        "SELECT a.title, a.summary, a.published_at FROM articles a
         WHERE {$clause}
         ORDER BY a.published_at DESC",
        $params
    );

    return editions_decennies($lignes);
}
