<?php
/**
 * Tests des pages d'accueil (lot 1.24.0).
 *
 * Deux pages, deux rôles :
 *   - la page publique (/) présente le journal et **deux portes** : « Consulter »
 *     et « Se connecter » (la seconde mise en avant). L'arbre généalogique n'y
 *     figure plus, et ses adresses sont fermées au visiteur ;
 *   - l'accueil connecté (/famille) est la « une » du journal, strictement dans
 *     les droits du compte : articles, documents, personnes, anniversaires,
 *     nouveautés. Un journaliste y trouve « Proposer un article », un
 *     administrateur le compteur « À valider ».
 *
 * Les essais se font dans une transaction annulée : la base du site est rendue
 * intacte.
 */

declare(strict_types=1);

/** Toute la famille « accueil ». */
function tests_accueil(): void
{
    echo "Pages d’accueil — portes du site et « une » de la famille\n";

    try {
        db_transaction(static function (): void {
            tests_accueil_public();
            tests_accueil_connecte();

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    verifier('accueil : les comptes d’essai sont annulés', true);
}

/** 1. La page publique et ses deux portes. */
function tests_accueil_public(): void
{
    $accueil = tests_envoi_requete('GET', '/', null);
    verifier(
        'accueil public : la page s’ouvre à un visiteur',
        (int) $accueil['code'] === 200,
        'code ' . $accueil['code']
    );

    verifier_contient(
        'accueil public : la porte « Consulter » est là',
        'Consulter',
        (string) $accueil['corps']
    );
    verifier_contient(
        'accueil public : la porte « Se connecter » est là',
        'Se connecter',
        (string) $accueil['corps']
    );

    // « Se connecter » est la porte mise en avant : elle porte la classe
    // d'accent, dans l'en-tête comme dans le corps de la page.
    verifier(
        'accueil public : « Se connecter » est mise en avant (classe d’accent)',
        str_contains((string) $accueil['corps'], 'nav__accent--connexion')
            && str_contains((string) $accueil['corps'], 'bouton--accent')
    );

    // La porte de connexion est aussi la plus grande des deux (feuille de
    // style) : contenu plus large, police plus forte que « Consulter ».
    $css = (string) @file_get_contents(HDF_ROOT . '/public/assets/style.css');
    verifier(
        'accueil public : la porte « Se connecter » est plus grande que « Consulter »',
        str_contains((string) $accueil['corps'], 'bouton--porte')
            && preg_match('/\.bouton--porte\s*\{[^}]*font-size:\s*1\.15rem/', $css) === 1
            // La règle doit venir APRÈS le bouton de base, sinon celui-ci
            // (0,74 rem) l'emporte : le test le vérifie sur la feuille.
            && strpos($css, '.bouton--porte') > strpos($css, "\n.bouton {")
    );

    // L'arbre n'est plus une porte publique : aucun lien vers ses pages.
    // (Le texte du site, lui, est réglable par l'administrateur : on ne juge
    // pas son contenu, seulement les liens que la page propose.)
    verifier(
        'accueil public : l’arbre généalogique n’est plus proposé au visiteur',
        !str_contains((string) $accueil['corps'], 'href="/arbre')
            && !str_contains((string) $accueil['corps'], 'href="/api/arbre'),
        'un lien vers l’arbre subsiste'
    );

    $menu = menu_rubriques(null, []);
    $libelles = array_column($menu, 'libelle');
    verifier(
        'accueil public : le menu du visiteur ne contient plus « Arbre »',
        !in_array('Arbre', $libelles, true) && in_array('Consulter', $libelles, true),
        implode(', ', $libelles)
    );

    // Les adresses de l'arbre sont fermées au visiteur : renvoi vers la
    // connexion, jamais la page elle-même.
    $arbre = tests_envoi_requete('GET', '/arbre', null);
    verifier(
        'accueil public : /arbre renvoie un visiteur vers la connexion',
        (int) $arbre['code'] === 303 && str_contains((string) $arbre['location'], '/connexion'),
        'code ' . $arbre['code'] . ' → ' . (string) $arbre['location']
    );

    // Le menu d'un compte connecté, lui, garde l'arbre.
    $branche = tests_profils_branche('Branche accueil');
    $lecteur = tests_profils_compte('branche', $branche);
    $menuConnecte = array_column(menu_rubriques((array) current_account_compte($lecteur), []), 'libelle');
    verifier(
        'accueil connecté : le menu garde « Arbre » pour un compte connecté',
        in_array('Arbre', $menuConnecte, true),
        implode(', ', $menuConnecte)
    );
}

/** 2. L'accueil connecté, strictement dans les droits du compte. */
function tests_accueil_connecte(): void
{
    $branche    = tests_profils_branche('Branche accueil famille');
    $lecteur    = tests_profils_compte('branche', $branche);
    $journaliste = tests_profils_compte('personne', $branche);
    $admin      = tests_profils_compte('admin');

    $accueil = tests_envoi_requete('GET', '/famille', $lecteur);
    verifier(
        'accueil connecté : la « une » s’ouvre à un lecteur',
        (int) $accueil['code'] === 200,
        'code ' . $accueil['code']
    );
    verifier_contient(
        'accueil connecté : les nouveautés de la famille sont là',
        'Nouveautés de votre famille',
        (string) $accueil['corps']
    );
    verifier(
        'accueil connecté : un lecteur ne voit ni « Proposer un article » ni « À valider »',
        !str_contains((string) $accueil['corps'], 'Proposer un article')
            && !str_contains((string) $accueil['corps'], 'Examiner les propositions'),
        'un lecteur voit des boutons d’écriture'
    );

    $accueilJournaliste = tests_envoi_requete('GET', '/famille', $journaliste);
    verifier(
        'accueil connecté : un journaliste voit « Proposer un article »',
        str_contains((string) $accueilJournaliste['corps'], 'Proposer un article')
            && str_contains((string) $accueilJournaliste['corps'], '/propositions'),
        'code ' . $accueilJournaliste['code']
    );

    $accueilAdmin = tests_envoi_requete('GET', '/famille', $admin);
    verifier(
        'accueil connecté : un administrateur voit le compteur « À valider »',
        str_contains((string) $accueilAdmin['corps'], 'Examiner les propositions')
            || str_contains((string) $accueilAdmin['corps'], 'À valider'),
        'code ' . $accueilAdmin['code']
    );

    // L'arbre est ouvert une fois connecté.
    $arbre = tests_envoi_requete('GET', '/arbre', $lecteur);
    verifier(
        'accueil connecté : l’arbre s’ouvre à un compte connecté',
        (int) $arbre['code'] === 200,
        'code ' . $arbre['code']
    );
}

/** Le compte, tel que le voient les fonctions de droits. */
function current_account_compte(int $id): ?array
{
    return db_one('SELECT * FROM accounts WHERE id = ?', [$id]);
}
