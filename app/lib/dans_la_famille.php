<?php
/**
 * Bloc « Dans la famille » de la page d'accueil, pour un compte connecté.
 *
 * Quatre listes, toutes tirées de ce que cette personne a le droit de voir :
 *   - les anniversaires des trente prochains jours ;
 *   - les autres événements du calendrier (mariages, souvenirs) à venir ;
 *   - les personnes ajoutées récemment ;
 *   - les documents et articles parus depuis sa dernière visite.
 *
 * Aucune règle d'accès n'est écrite ici : on réutilise le calendrier
 * (calendrier.php) et les conditions de visibilité (visibility.php), qui
 * filtrent déjà selon le compte connecté. Un visiteur n'obtient jamais ce
 * bloc : le contrôleur ne l'appelle que si quelqu'un est connecté.
 */

declare(strict_types=1);

/** Horodatage de la connexion précédente (0 = première visite connue). */
function famille_visite_precedente(): int
{
    return (int) ($_SESSION['visite_precedente'] ?? 0);
}

/**
 * Depuis quand montrer les nouveautés : la visite précédente, ou à défaut les
 * sept derniers jours (une première visite ne doit pas déverser tout le site).
 */
function famille_depuis(int $joursDefaut = 7): int
{
    $precedente = famille_visite_precedente();
    return $precedente > 0 ? $precedente : time() - max(1, $joursDefaut) * 86400;
}

/**
 * Contenu du bloc « Dans la famille ».
 *
 * @return array{anniversaires:list<array>, evenements:list<array>, personnes:list<array>, nouveautes:list<array>}
 */
function dans_la_famille(int $jours = 30, int $limite = 4): array
{
    $bloc = ['anniversaires' => [], 'evenements' => [], 'personnes' => [], 'nouveautes' => []];

    if (!is_logged_in()) {
        return $bloc;
    }

    $limite = max(1, $limite);

    // 1 et 2. Anniversaires et autres événements à venir (droits du calendrier).
    foreach (calendrier_prochains($jours, 60) as $evenement) {
        $cle = $evenement['genre'] === 'naissance' ? 'anniversaires' : 'evenements';
        if (count($bloc[$cle]) < $limite) {
            $bloc[$cle][] = $evenement;
        }
    }

    // 3. Personnes ajoutées depuis la dernière visite.
    $depuis = famille_depuis();

    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    $params[':depuis'] = $depuis;
    $bloc['personnes'] = db_all(
        "SELECT p.id, p.first_name, p.last_name, p.birth_date, p.death_date, p.created_at
         FROM persons p
         WHERE {$clause} AND p.created_at > :depuis
         ORDER BY p.created_at DESC
         LIMIT {$limite}",
        $params
    );

    // 4. Documents et articles parus depuis la dernière visite.
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $params[':depuis'] = $depuis;
    $documents = db_all(
        "SELECT d.id, d.title, d.created_at
         FROM documents d
         WHERE d.is_attachment = 0 AND {$clause} AND d.created_at > :depuis
         ORDER BY d.created_at DESC
         LIMIT 6",
        $params
    );

    $params = [];
    $clause = article_visible_clause('a', $params);
    $params[':depuis'] = $depuis;
    $articles = db_all(
        "SELECT a.slug, a.title, a.published_at
         FROM articles a
         WHERE {$clause} AND a.published_at > :depuis
         ORDER BY a.published_at DESC
         LIMIT 6",
        $params
    );

    $nouveautes = [];
    foreach ($documents as $document) {
        $nouveautes[] = [
            'type'  => 'Document',
            'titre' => (string) $document['title'],
            'date'  => (int) $document['created_at'],
            'lien'  => '/documents/' . (int) $document['id'],
        ];
    }
    foreach ($articles as $article) {
        $nouveautes[] = [
            'type'  => 'Article',
            'titre' => (string) $article['title'],
            'date'  => (int) $article['published_at'],
            'lien'  => '/articles/' . (string) $article['slug'],
        ];
    }
    usort($nouveautes, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
    $bloc['nouveautes'] = array_slice($nouveautes, 0, 6);

    return $bloc;
}

/** Le bloc n'a rien à montrer ? (dans ce cas, il n'est pas affiché du tout) */
function dans_la_famille_vide(array $bloc): bool
{
    foreach ($bloc as $liste) {
        if (is_array($liste) && $liste !== []) {
            return false;
        }
    }
    return true;
}
