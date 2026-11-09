<?php
/**
 * Tests des pages d'accueil (lot 1.24.0).
 *
 * Deux pages, deux rôles :
 *   - la page publique (/) est la **Une du journal** (1.31.0) : tout le contenu
 *     public, du plus récent au plus ancien, et une seule porte, « Se
 *     connecter », mise en avant dans le premier écran. L'arbre généalogique
 *     n'y figure plus, et ses adresses sont fermées au visiteur. Une fois
 *     connecté, la vitrine disparaît et « Dans la famille » la remplace :
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
            tests_accueil_une();
            tests_accueil_connexion();

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    verifier('accueil : les comptes d’essai sont annulés', true);
}

/** 1. La page publique : la Une du journal (1.31.0). */
function tests_accueil_public(): void
{
    $accueil = tests_envoi_requete('GET', '/', null);
    verifier(
        'accueil public : la page s’ouvre à un visiteur',
        (int) $accueil['code'] === 200,
        'code ' . $accueil['code']
    );

    // Depuis la 1.31.0, l'accueil d'un visiteur est la Une du journal : tout le
    // contenu public, du plus récent au plus ancien. La porte « Consulter » a
    // disparu — la page elle-même est la consultation.
    verifier(
        'accueil public : la Une du journal remplace la porte « Consulter »',
        (str_contains((string) $accueil['corps'], 'À la une')
            || str_contains((string) $accueil['corps'], 'Le journal paraîtra bientôt'))
            && !str_contains((string) $accueil['corps'], '>Consulter<'),
        'la page ne présente pas la une, ou propose encore « Consulter »'
    );
    verifier_contient(
        'accueil public : la porte « Se connecter » est là',
        'Se connecter',
        (string) $accueil['corps']
    );

    // « Se connecter » est la porte mise en avant : elle porte la classe
    // d'accent, dans l'en-tête (1.33.1 : plus de gros bouton dans le corps).
    verifier(
        'accueil public : « Se connecter » est mise en avant (classe d’accent)',
        str_contains((string) $accueil['corps'], 'nav__accent--connexion')
    );

    // La porte de connexion est aussi la plus grande des deux (feuille de
    // style) : contenu plus large, police plus forte que les autres boutons.
    $css = (string) @file_get_contents(HDF_ROOT . '/public/assets/style.css');
    verifier(
        'accueil public : la porte « Se connecter » est le plus grand bouton de la page',
        preg_match('/\.bouton--porte\s*\{[^}]*font-size:\s*1\.15rem/', $css) === 1
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

    // 1.31.0 : le menu d'un visiteur n'a plus qu'une entrée — « Accueil ».
    // « Se connecter » est la porte mise en avant, ajoutée par la mise en page.
    $menu = menu_rubriques(null, []);
    $libelles = array_column($menu, 'libelle');
    verifier(
        'accueil public : le menu du visiteur se réduit à « Accueil »',
        $libelles === ['Accueil'],
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
        in_array('Arbre généalogique', $menuConnecte, true),
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

    // La vitrine du visiteur a disparu pour un compte connecté.
    verifier(
        'accueil connecté : plus de vitrine de visiteur sur la page d’accueil',
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

/**
 * 4. La une d'abord, pour tout compte connecté (1.29.0).
 *
 * L'accueil d'un compte connecté commence par l'article du jour : plus de
 * phrase d'accueil longue, plus de ligne de chiffres, plus de « Nous écrire »
 * avant lui, et « Dans la famille » vient après — dans une colonne à droite sur
 * grand écran (feuille de style), juste dessous sur téléphone. La page d'un
 * visiteur, elle, ne change pas : c'est vérifié ici aussi.
 *
 * La position en pixels du titre (moins de 800 px) se mesure dans un vrai
 * navigateur : `tools/navigateur/essai-une.mjs` (voir tools/navigateur/README.md).
 * Aucun essai PHP ne peut la connaître ; on vérifie ici l'ordre du document, le
 * nombre de lignes du bloc et les règles de la feuille de style.
 */
function tests_accueil_une(): void
{
    $suffixe    = bin2hex(random_bytes(3));
    $branche    = tests_profils_branche('Branche une');
    $lecteur    = tests_profils_compte('branche', $branche);
    $maintenant = time();

    // Cinq personnes ajoutées à l'instant : le bloc « Dans la famille » aura
    // de quoi remplir ses cinq lignes.
    for ($i = 1; $i <= 5; $i++) {
        db_insert('persons', [
            'first_name' => 'Une' . $i, 'last_name' => strtoupper($suffixe),
            'birth_date' => '1960-03-0' . $i, 'visibility' => 'famille',
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);
    }

    // Deux articles publiés : le plus récent doit être la une.
    $titreUne = 'Article de la une ' . $suffixe;
    db_insert('articles', [
        'slug' => 'essai-une-' . $suffixe, 'title' => $titreUne,
        'summary' => 'Résumé de la une.', 'body' => 'Texte de la une.',
        'category' => 'Souvenirs', 'visibility' => 'public', 'status' => 'publie',
        'published_at' => $maintenant - 10, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
    $titreAvant = 'Article précédent ' . $suffixe;
    db_insert('articles', [
        'slug' => 'essai-avant-' . $suffixe, 'title' => $titreAvant,
        'summary' => 'Résumé précédent.', 'body' => 'Texte précédent.',
        'category' => 'Souvenirs', 'visibility' => 'public', 'status' => 'publie',
        'published_at' => $maintenant - 900, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);

    $page  = tests_envoi_requete('GET', '/', $lecteur);
    $corps = (string) $page['corps'];
    verifier('accueil connecté : la page s’ouvre', (int) $page['code'] === 200, 'code ' . $page['code']);

    // 1. L'ordre : la une, puis « Dans la famille ».
    $posUne     = strpos($corps, 'accueil-journal__une');
    $posFamille = strpos($corps, 'dans-la-famille');
    verifier(
        'accueil connecté : la une ouvre la page, avant « Dans la famille »',
        $posUne !== false && $posFamille !== false && $posUne < $posFamille,
        'une à ' . var_export($posUne, true) . ', famille à ' . var_export($posFamille, true)
    );
    verifier(
        'accueil connecté : le dernier article publié est celui de la une',
        str_contains($corps, $titreUne)
            && strpos($corps, $titreUne) < strpos($corps, $titreAvant)
    );
    verifier(
        'accueil connecté : la une garde la carte d’article et son grand format',
        str_contains($corps, 'article-carte article-carte--une')
            && str_contains($corps, 'article-carte__titre')
    );

    // 2. La vitrine du visiteur a quitté l'accueil connecté.
    verifier(
        'accueil connecté : ni phrase d’accueil, ni chiffres, ni « Nous écrire » avant la une',
        !str_contains($corps, 'hero__texte')
            && !str_contains($corps, 'hero__chiffres')
            && !str_contains($corps, 'hero__ecrire')
            && !str_contains($corps, '<section class="hero"'),
        'un élément de la vitrine est encore là'
    );

    // 3. Une seule ligne de bienvenue, sous la date.
    verifier(
        'accueil connecté : une seule ligne de bienvenue, sous la date',
        substr_count($corps, 'entete__salutation') === 1
            && preg_match('/entete__salutation[^>]*>Bonjour /', $corps) === 1
            && strpos($corps, 'entete__salutation') < strpos($corps, 'entete__titre'),
        'salutation absente ou en double'
    );

    // 4. Le bloc « Dans la famille » : cinq lignes au plus, lien « Tout voir »,
    //    replié sur téléphone au-delà de trois lignes.
    $debut = strpos($corps, 'class="dans-la-famille');
    $fin   = $debut === false ? false : strpos($corps, '</aside>', $debut);
    $bloc  = ($debut === false || $fin === false) ? '' : substr($corps, $debut, $fin - $debut);
    // 1.38.0 : un seul rendu sert le téléphone et le grand écran — cinq
    // lignes au plus, écrites une seule fois dans la page.
    $lignes = substr_count($bloc, '<li>');
    verifier(
        'accueil connecté : « Dans la famille » montre cinq lignes au plus, écrites une seule fois',
        $lignes > 0 && $lignes <= 5,
        $lignes . ' ligne(s) dans le bloc'
    );
    verifier(
        'accueil connecté : « Dans la famille » mène à « Tout voir »',
        str_contains($bloc, 'Tout voir') && str_contains($bloc, 'href="/famille"')
    );
    verifier(
        'accueil connecté : au-delà de trois lignes, le bloc se replie sans JavaScript',
        str_contains($bloc, '<details class="accueil-famille__repli"')
            && str_contains($bloc, '<summary>'),
        'repli absent'
    );
    verifier(
        'accueil connecté : aucun style en ligne dans la une ni le bloc famille',
        !str_contains($bloc, ' style="') && !str_contains(substr($corps, 0, (int) $posUne + 4000), ' style="')
    );

    // 5. La feuille de style : colonne à droite à partir de 64 rem, repli en
    //    dessous (jamais l'inverse), et aucune classe renommée.
    $css = (string) @file_get_contents(HDF_ROOT . '/public/assets/style.css');
    verifier(
        'accueil connecté : « Dans la famille » passe en colonne à droite à 64 rem',
        preg_match('/@media\s*\(min-width:\s*64rem\)\s*\{[^@]*\.accueil-journal--deux[^@]*\.accueil-journal__famille/s', $css) === 1,
        'règle absente'
    );
    verifier(
        'accueil connecté : un seul <details> sert les deux largeurs (1.38.0)',
        preg_match('/@media\\s*\\(min-width:\\s*64rem\\)\\s*\\{[^@]*\\.accueil-famille--repliable \\.accueil-famille__repli:not\\(\\[open\\]\\) > :not\\(summary\\)\\s*\\{\\s*display:\\s*block/s', $css) === 1
            && preg_match('/@media\\s*\\(max-width:\\s*63\\.99rem\\)\\s*\\{[^@]*\\.accueil-famille--repliable \\.accueil-famille__entete\\s*\\{\\s*display:\\s*none/s', $css) === 1,
        'règle absente'
    );
    verifier(
        'accueil connecté : les classes de la une et du bloc famille sont inchangées',
        str_contains($css, '.article-carte--une .article-carte__titre')
            && str_contains($css, '.dans-la-famille .panneau')
            && str_contains($css, '.entete__date')
    );

    // 6. La page d'un visiteur est la Une du journal (1.31.0) : la phrase
    // d'accueil et la porte « Se connecter », rien de la page connectée.
    $visiteur = tests_envoi_requete('GET', '/', null);
    verifier(
        'accueil public : le visiteur ne voit ni phrase ni gros bouton en tête (menu seul)',
        !str_contains((string) $visiteur['corps'], 'une-intro')
            && !str_contains((string) $visiteur['corps'], 'bouton--porte')
            && str_contains((string) $visiteur['corps'], 'href="/connexion"')
            && !str_contains((string) $visiteur['corps'], 'hero__chiffres'),
        'la vitrine du visiteur a changé'
    );
    verifier(
        'accueil public : le visiteur n’a ni salutation ni bloc famille',
        !str_contains((string) $visiteur['corps'], 'entete__salutation')
            && !str_contains((string) $visiteur['corps'], 'dans-la-famille')
    );

    // 7. Repli : bloc « une » masqué dans la composition, l'article reste en tête.
    $blocs = composition_defauts('public');
    foreach ($blocs as $i => $blocCompose) {
        if ($blocCompose['cle'] === 'une') {
            $blocs[$i]['actif'] = false;
        }
    }
    composition_enregistrer('public', $blocs);
    $sansBloc = (string) tests_envoi_requete('GET', '/', $lecteur)['corps'];
    verifier(
        'accueil connecté : bloc « une » masqué, le dernier article reste en tête',
        str_contains($sansBloc, 'accueil-journal__une')
            && str_contains($sansBloc, $titreUne)
            && strpos($sansBloc, 'accueil-journal__une') < strpos($sansBloc, 'dans-la-famille'),
        'la une a disparu avec le bloc'
    );
    composition_retablir('public');

    // 8. Aucun article du tout : « Dans la famille » prend la première place.
    db_exec('DELETE FROM articles');
    $sansArticle = (string) tests_envoi_requete('GET', '/', $lecteur)['corps'];
    verifier(
        'accueil connecté : sans aucun article, « Dans la famille » prend la première place',
        !str_contains($sansArticle, 'article-carte--une')
            && str_contains($sansArticle, 'dans-la-famille')
            && strpos($sansArticle, 'dans-la-famille') < strpos($sansArticle, 'Derniers documents'),
        'le bloc famille n’est pas en tête'
    );
}

/** Le compte, tel que le voient les fonctions de droits. */
function current_account_compte(int $id): ?array
{
    return db_one('SELECT * FROM accounts WHERE id = ?', [$id]);
}


/** 5. Après la connexion, tout le monde arrive sur l'accueil (1.31.1). */
function tests_accueil_connexion(): void
{
    $branche     = tests_profils_branche('Branche connexion accueil');
    $lecteur     = tests_profils_compte('branche', $branche);
    $journaliste = tests_profils_compte('personne', $branche);
    $admin       = tests_profils_compte('admin');

    foreach (['lecteur' => $lecteur, 'journaliste' => $journaliste, 'administrateur' => $admin] as $nom => $id) {
        $_SESSION = ['account_id' => $id];
        verifier(
            'connexion : un ' . $nom . ' arrive sur l’accueil du journal',
            home_for_account() === '/',
            'adresse : ' . home_for_account()
        );
    }
    $_SESSION = [];

    verifier(
        'connexion : une adresse « next » externe reste refusée',
        safe_next_path('https://exemple.fr') === ''
            && safe_next_path('//exemple.fr') === ''
            && safe_next_path('/\\exemple.fr') === '',
        'redirection ouverte possible'
    );
    verifier(
        'connexion : une adresse « next » locale est conservée',
        safe_next_path('/admin/albums/2/photos') === '/admin/albums/2/photos',
        'adresse locale refusée'
    );
}
