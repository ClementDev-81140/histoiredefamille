<?php
/**
 * Rubriques publiques du site.
 *
 * La page d'accueil et le menu public ne montrent que ce qui a du contenu
 * public : trois entrées de base (documents, arbre, espace famille) et,
 * en plus, les rubriques où quelque chose a réellement été publié.
 * Un visiteur ne doit jamais tomber sur une rubrique vide.
 *
 * Une seule requête légère par rubrique (des EXISTS, servis par les index),
 * et le résultat est gardé en mémoire pour la durée de la requête : le menu
 * et la page d'accueil partagent la même réponse.
 *
 * Les droits ne changent pas ici : les pages elles-mêmes continuent de
 * filtrer avec visibility.php. Tout compte connecté (famille ou
 * administrateur) garde le menu complet, comme avant.
 */

declare(strict_types=1);

/**
 * Rubriques publiques à afficher, sous la forme clé => vrai.
 *
 * Toujours présentes : documents, arbre, famille (l'entrée de connexion).
 * Les autres ne reviennent que si elles ont du contenu public.
 *
 * @param bool $rafraichir recalcule le résultat (tests automatiques)
 * @return array<string, bool>
 */
function rubriques_publiques(bool $rafraichir = false): array
{
    static $cache = null;
    if ($cache !== null && !$rafraichir) {
        return $cache;
    }

    // Les trois entrées de base : documents et arbre pour tout le monde,
    // et la connexion pour les familles.
    $cache = [
        'documents' => true,
        'arbre'     => true,
        'famille'   => true,
    ];

    // Une publication datée dans le futur n'est pas encore publique.
    $maintenant = time();
    $oui        = static function (string $sql, array $params = []) : bool {
        return (int) db_value($sql, $params, 0) === 1;
    };

    try {
        $cache['articles'] = $oui(
            "SELECT EXISTS (
                SELECT 1 FROM articles
                WHERE status = 'publie' AND published_at <= ? AND visibility = 'public'
            )",
            [$maintenant]
        );

        $cache['videos'] = $oui(
            "SELECT EXISTS (
                SELECT 1 FROM documents
                WHERE kind = 'video' AND is_attachment = 0 AND visibility = 'public'
            )"
        );

        $cache['albums'] = $oui(
            "SELECT EXISTS (SELECT 1 FROM albums WHERE visibility = 'public')"
        );

        $cache['calendrier'] = $oui(
            "SELECT EXISTS (
                SELECT 1 FROM persons
                WHERE visibility = 'public'
                  AND ((birth_date IS NOT NULL AND TRIM(birth_date) <> '')
                    OR (death_date IS NOT NULL AND TRIM(death_date) <> ''))
            )"
        );

        // La recherche n'a de sens pour un visiteur que s'il y a quelque chose
        // à trouver : un document, un article, une page ou une personne publique.
        $cache['recherche'] = $oui(
            "SELECT EXISTS (SELECT 1 FROM documents WHERE visibility = 'public')
                 OR EXISTS (SELECT 1 FROM articles
                            WHERE status = 'publie' AND published_at <= ?
                              AND visibility = 'public')
                 OR EXISTS (SELECT 1 FROM pages WHERE is_published = 1 AND visibility = 'public')
                 OR EXISTS (SELECT 1 FROM persons WHERE visibility = 'public')",
            [$maintenant]
        );
    } catch (Throwable) {
        // Base pas encore installée (ou table manquante) : on s'en tient aux
        // trois entrées de base. Aucune page ne doit tomber en erreur pour ça.
        return $cache;
    }

    // Le tchat est un salon privé : jamais dans le menu public.
    $cache['tchat'] = false;

    return $cache;
}

/** Une rubrique donnée est-elle publique ? (raccourci lisible) */
function rubrique_publique(string $cle): bool
{
    return rubriques_publiques()[$cle] ?? false;
}

/**
 * Le visiteur non connecté voit-il le menu réduit ?
 * (les comptes connectés gardent le menu complet)
 */
function menu_public_reduit(): bool
{
    return !is_logged_in();
}

/** Recalcule rubriques_publiques() au prochain appel (tests automatiques). */
function rubriques_publiques_oubli(): void
{
    rubriques_publiques(true);
}

/**
 * Entrées du menu principal, selon le profil.
 *
 * Visiteur non connecté : menu court, et seulement les rubriques qui ont du
 * contenu public. Compte de famille ou administrateur : menu complet, chaque
 * page filtrant ensuite selon les droits du compte.
 *
 * @param array|null $compte  compte courant (null = visiteur non connecté)
 * @param array{recettes?:bool,temoignages?:bool,tchat?:bool} $extras
 *        rubriques facultatives, déjà vérifiées par l'appelant
 * @return list<array{cle:string,href:string,libelle:string}>
 */
function menu_rubriques(?array $compte, array $extras = []): array
{
    $entrees = [['cle' => 'accueil', 'href' => '/', 'libelle' => 'Accueil']];

    if ($compte === null) {
        // --- Menu public réduit -------------------------------------------
        $entrees[] = ['cle' => 'documents', 'href' => '/documents', 'libelle' => 'Documents'];
        $entrees[] = ['cle' => 'arbre', 'href' => '/arbre', 'libelle' => 'Arbre'];

        foreach ([
            ['articles', '/articles', 'Articles'],
            ['videos', '/videos', 'Vidéos'],
            ['albums', '/albums', 'Albums'],
            ['calendrier', '/calendrier', 'Calendrier'],
        ] as [$cle, $href, $libelle]) {
            if (rubrique_publique($cle)) {
                $entrees[] = ['cle' => $cle, 'href' => $href, 'libelle' => $libelle];
            }
        }

        if (!empty($extras['recettes'])) {
            $entrees[] = ['cle' => 'recettes', 'href' => '/recettes', 'libelle' => 'Recettes'];
        }
        if (!empty($extras['temoignages'])) {
            $entrees[] = ['cle' => 'temoignages', 'href' => '/temoignages', 'libelle' => 'Témoignages'];
        }
        if (rubrique_publique('recherche')) {
            $entrees[] = ['cle' => 'recherche', 'href' => '/recherche', 'libelle' => 'Recherche'];
        }

        // Le tchat est privé : jamais dans le menu public.
        return $entrees;
    }

    // --- Menu complet (famille ou administration) --------------------------
    foreach ([
        ['articles', '/articles', 'Articles'],
        ['documents', '/documents', 'Documents'],
        ['videos', '/videos', 'Vidéos'],
        ['albums', '/albums', 'Albums'],
        ['arbre', '/arbre', 'Arbre'],
        ['calendrier', '/calendrier', 'Calendrier'],
    ] as [$cle, $href, $libelle]) {
        $entrees[] = ['cle' => $cle, 'href' => $href, 'libelle' => $libelle];
    }

    if (!empty($extras['recettes'])) {
        $entrees[] = ['cle' => 'recettes', 'href' => '/recettes', 'libelle' => 'Recettes'];
    }
    if (!empty($extras['temoignages'])) {
        $entrees[] = ['cle' => 'temoignages', 'href' => '/temoignages', 'libelle' => 'Témoignages'];
    }

    $entrees[] = ['cle' => 'recherche', 'href' => '/recherche', 'libelle' => 'Recherche'];

    if (!empty($extras['tchat'])) {
        $entrees[] = ['cle' => 'tchat', 'href' => '/tchat', 'libelle' => 'Tchat'];
    }

    return $entrees;
}
