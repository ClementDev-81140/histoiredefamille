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

    $params = [];
    $clause = visibility_clause('page', 'p', $params);
    $pages  = db_all(
        "SELECT p.slug, p.title, p.body FROM pages p
         WHERE p.is_published = 1 AND {$clause}
         ORDER BY p.position, p.title LIMIT 6",
        $params
    );

    // Accès rapides : toutes les rubriques que ce compte peut ouvrir.
    // Les pages elles-mêmes filtrent selon les droits ; on n'affiche pas les
    // onglets qui n'existent pas (recettes, témoignages, tchat fermé).
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

    render('famille/accueil', [
        'titre'    => 'Espace famille',
        'account'  => $account,
        'documents' => recent_documents(8),
        'personnes' => recent_persons(6),
        'pages'     => $pages,
        'articles'  => recent_articles(5),
        'videos'    => videos_visible(4),
        'albums'    => albums_visible(4),
        'acces'     => $acces,
        'annonces'  => gazette_annonces(5),
        'courrier'  => gazette_courrier(3),
        'nbDocuments' => count_visible('documents', 'document'),
        'nbPersonnes' => count_visible('persons', 'person'),
        'nbPages'     => count_visible('pages', 'page'),
    ]);
}
