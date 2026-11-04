<?php
/**
 * Espace famille (accessible à tout compte connecté, hors administration).
 */

declare(strict_types=1);

/** Tableau de bord de l'espace famille. */
function family_home(): void
{
    require_login();

    $account = current_account();

    // Composition réglée dans « Administration → Composition des pages » :
    // un bloc masqué n'est pas calculé du tout (aucune requête).
    $visible = static fn (string $cle): bool => composition_visible('famille', $cle);

    $pages = [];
    if ($visible('pages')) {
        $params = [];
        $clause = visibility_clause('page', 'p', $params);
        $pages  = db_all(
            "SELECT p.slug, p.title, p.body FROM pages p
             WHERE p.is_published = 1 AND {$clause}
             ORDER BY p.position, p.title LIMIT 6",
            $params
        );
    }

    // Accès rapides : toutes les rubriques que ce compte peut ouvrir.
    // Les pages elles-mêmes filtrent selon les droits ; on n'affiche pas les
    // onglets qui n'existent pas (recettes, témoignages, tchat fermé).
    $acces = [];
    if ($visible('acces')) {
        $acces = [
            ['/articles', 'Articles'],
            ['/documents', 'Documents'],
            ['/videos', 'Vidéos'],
            ['/albums', 'Albums'],
            ['/arbre', 'Arbre généalogique'],
            ['/calendrier', 'Calendrier'],
            ['/frise', 'Frise chronologique'],
            ['/recherche', 'Recherche'],
        ];
        if (recipes_exist()) {
            $acces[] = ['/recettes', 'Recettes'];
        }
        if (audio_exist()) {
            $acces[] = ['/temoignages', 'Témoignages'];
        }
        if (tchat_actif()) {
            $acces[] = ['/tchat', 'Tchat'];
        }
    }

    $articles = [];
    if ($visible('une') || $visible('colonnes')) {
        $articles = recent_articles(composition_nb_articles());
    }

    // Nouveautés : ce qui a bougé récemment, strictement dans les droits de
    // ce compte (les fonctions ci-dessus filtrent déjà avec visibility.php).
    $nouveautes = [];
    foreach (recent_documents(4) as $document) {
        $nouveautes[] = [
            'type'  => 'Document',
            'titre' => (string) $document['title'],
            'date'  => (int) $document['created_at'],
            'lien'  => '/documents/' . (int) $document['id'],
        ];
    }
    foreach (recent_articles(4) as $article) {
        $nouveautes[] = [
            'type'  => 'Article',
            'titre' => (string) $article['title'],
            'date'  => (int) $article['published_at'],
            'lien'  => '/articles/' . (string) $article['slug'],
        ];
    }
    foreach (recent_persons(4) as $personne) {
        $nouveautes[] = [
            'type'  => 'Personne',
            'titre' => person_full_name($personne),
            'date'  => (int) $personne['updated_at'],
            'lien'  => '/arbre/' . (int) $personne['id'],
        ];
    }
    usort($nouveautes, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
    $nouveautes = array_slice($nouveautes, 0, 6);

    $nbDocuments = $nbPersonnes = $nbPages = 0;
    if ($visible('chiffres')) {
        $nbDocuments = count_visible('documents', 'document');
        $nbPersonnes = count_visible('persons', 'person');
        $nbPages     = count_visible('pages', 'page');
    }

    render('famille/accueil', [
        'titre'     => 'Espace famille',
        'account'   => $account,
        'documents' => $visible('documents') ? recent_documents(8) : [],
        'personnes' => $visible('personnes') ? recent_persons(6) : [],
        'pages'     => $pages,
        'articles'  => $articles,
        'videos'    => $visible('videos') ? videos_visible(4) : [],
        'branchesEdition'  => $visible('editions') ? editions_branches() : [],
        'decenniesEdition' => $visible('editions') ? editions_decennies_visibles() : [],
        'albums'    => $visible('albums') ? albums_visible(4) : [],
        'acces'     => $acces,
        'annonces'  => $visible('annonces') ? gazette_annonces(5) : [],
        'courrier'  => $visible('courrier') ? gazette_courrier(3) : [],
        'nouveautes' => $nouveautes,
        'aValider'  => is_admin() ? propositions_a_valider() : 0,
        'nbDocuments' => $nbDocuments,
        'nbPersonnes' => $nbPersonnes,
        'nbPages'     => $nbPages,
    ]);
}
