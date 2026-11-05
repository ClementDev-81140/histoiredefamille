<?php
/**
 * Tests des pages d'accueil (lot 1.24.0).
 *
 * Deux pages, deux rôles :
 *   - la page publique (/) présente le journal et **deux portes** : « Consulter »
 *     et « Se connecter » (la seconde mise en avant). L'arbre généalogique n'y
 *     figure plus, et ses adresses sont fermées au visiteur. Une fois connecté,
 *     les deux portes disparaissent et « Dans la famille » les remplace :
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
            tests_accueil_dans_la_famille();

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

/** 3. Le bloc « Dans la famille » : strictement les droits du compte. */
function tests_accueil_dans_la_famille(): void
{
    $suffixe  = bin2hex(random_bytes(3));
    $brancheA = tests_profils_branche('Branche famille A');
    $brancheB = tests_profils_branche('Branche famille B');
    $lecteur  = tests_profils_compte('branche', $brancheA);
    $journaliste = tests_profils_compte('personne', $brancheA);
    $admin    = tests_profils_compte('admin');

    $maintenant = time();

    // Un anniversaire dans deux jours : naissance en 1950, même jour et même
    // mois que la date visée (l'âge affiché est celui de cette année-là).
    $proche      = (new DateTimeImmutable('today'))->modify('+2 days');
    $dateNaissance = static fn (): string => sprintf(
        '%04d-%02d-%02d',
        1950,
        (int) $proche->format('n'),
        (int) $proche->format('j')
    );

    // 1. Une personne visible par tous les comptes de famille.
    $visibleId = db_insert('persons', [
        'first_name' => 'Visible', 'last_name' => strtoupper($suffixe),
        'birth_date' => $dateNaissance(), 'visibility' => 'famille',
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    // 2. Une personne réservée à l'autre branche : un lecteur de A n'y a
    //    pas droit, ni pour son anniversaire ni pour son ajout récent.
    $autreId = db_insert('persons', [
        'first_name' => 'Cachee', 'last_name' => strtoupper($suffixe),
        'birth_date' => $dateNaissance(), 'visibility' => 'branches',
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    set_content_branches('person', $autreId, [$brancheB]);

    // 3. Une personne privée : administrateurs seulement.
    $priveId = db_insert('persons', [
        'first_name' => 'Privee', 'last_name' => strtoupper($suffixe),
        'birth_date' => $dateNaissance(), 'visibility' => 'prive',
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    // 4. Un document tout neuf, visible par les familles : il doit apparaître
    //    dans « depuis votre dernière visite ».
    $titreDocument = 'Nouveau document ' . $suffixe;
    db_insert('documents', [
        'title' => $titreDocument, 'kind' => 'document', 'visibility' => 'famille',
        'rel_path' => 'essai/' . $suffixe . '.txt', 'original_name' => $suffixe . '.txt',
        'mime' => 'text/plain', 'ext' => 'txt', 'size' => 1,
        'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    $nomVisible = person_full_name(db_one('SELECT * FROM persons WHERE id = ?', [$visibleId]));
    $nomAutre   = person_full_name(db_one('SELECT * FROM persons WHERE id = ?', [$autreId]));
    $nomPrive   = person_full_name(db_one('SELECT * FROM persons WHERE id = ?', [$priveId]));

    // Visiteur : jamais ce bloc, même quand les données existent.
    $visiteur = tests_envoi_requete('GET', '/', null);
    verifier(
        'Dans la famille : la page publique d’un visiteur ne le montre pas',
        (int) $visiteur['code'] === 200
            && !str_contains((string) $visiteur['corps'], 'dans-la-famille')
            && !str_contains((string) $visiteur['corps'], $nomVisible),
        'le bloc apparaît pour un visiteur'
    );

    // Lecteur de la branche A : le bloc est là, avec ses anniversaires, mais
    // seulement ce qu'il a le droit de voir.
    $pageLecteur = tests_envoi_requete('GET', '/', $lecteur);
    $corpsLecteur = (string) $pageLecteur['corps'];
    verifier(
        'Dans la famille : un lecteur voit le bloc et son anniversaire',
        (int) $pageLecteur['code'] === 200
            && str_contains($corpsLecteur, 'dans-la-famille')
            && str_contains($corpsLecteur, 'Anniversaires')
            && str_contains($corpsLecteur, $nomVisible),
        'code ' . $pageLecteur['code']
    );
    verifier(
        'Dans la famille : un lecteur ne voit ni l’anniversaire ni la personne d’une autre branche',
        !str_contains($corpsLecteur, $nomAutre),
        'la personne d’une autre branche apparaît'
    );
    verifier(
        'Dans la famille : un lecteur ne voit pas un anniversaire privé',
        !str_contains($corpsLecteur, $nomPrive),
        'la personne privée apparaît pour un lecteur'
    );
    verifier(
        'Dans la famille : un lecteur voit le document paru depuis sa dernière visite',
        str_contains($corpsLecteur, $titreDocument)
            && str_contains($corpsLecteur, 'Depuis votre dernière visite'),
        'le document récent manque'
    );

    // Journaliste et administrateur ont le même bloc (droits différents).
    $pageJournaliste = tests_envoi_requete('GET', '/', $journaliste);
    verifier(
        'Dans la famille : un journaliste voit aussi le bloc',
        str_contains((string) $pageJournaliste['corps'], 'dans-la-famille')
            && str_contains((string) $pageJournaliste['corps'], $nomVisible),
        'code ' . $pageJournaliste['code']
    );

    $pageAdmin = tests_envoi_requete('GET', '/', $admin);
    verifier(
        'Dans la famille : un administrateur voit jusqu’aux anniversaires privés',
        str_contains((string) $pageAdmin['corps'], 'dans-la-famille')
            && str_contains((string) $pageAdmin['corps'], $nomVisible)
            && str_contains((string) $pageAdmin['corps'], $nomPrive),
        'code ' . $pageAdmin['code']
    );

    // Les deux portes du visiteur ont disparu pour un compte connecté.
    verifier(
        'accueil connecté : plus de porte « Consulter » sur la page publique',
        !str_contains($corpsLecteur, 'actions--portes')
            && !str_contains($corpsLecteur, '>Consulter<'),
        'la porte du visiteur est encore là'
    );

    // Un bloc vide ne s'affiche pas ; la fenêtre « depuis » suit la visite.
    verifier(
        'Dans la famille : un bloc vide n’est pas affiché',
        dans_la_famille_vide(['anniversaires' => [], 'evenements' => [], 'personnes' => [], 'nouveautes' => []])
            && !dans_la_famille_vide(['anniversaires' => [['nom' => 'X']], 'evenements' => [], 'personnes' => [], 'nouveautes' => []])
    );

    $session = $_SESSION;
    $_SESSION = [];
    verifier(
        'Dans la famille : un visiteur n’obtient rien de la fonction',
        dans_la_famille() === ['anniversaires' => [], 'evenements' => [], 'personnes' => [], 'nouveautes' => []]
    );
    $_SESSION = $session;

    $_SESSION['visite_precedente'] = 1000000000;
    $depuisVisite = famille_depuis();
    unset($_SESSION['visite_precedente']);
    $depuisDefaut = famille_depuis();
    verifier(
        'Dans la famille : les nouveautés partent de la visite précédente (sinon sept jours)',
        $depuisVisite === 1000000000 && $depuisDefaut > time() - 8 * 86400 && $depuisDefaut <= time(),
        $depuisVisite . ' / ' . $depuisDefaut
    );
}

/** Le compte, tel que le voient les fonctions de droits. */
function current_account_compte(int $id): ?array
{
    return db_one('SELECT * FROM accounts WHERE id = ?', [$id]);
}
