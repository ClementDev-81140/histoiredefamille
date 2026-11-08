<?php
/**
 * Essais — 1.34.0 : étiquette de décennie, calendrier, lignes vides.
 *
 *   1. un article d'actualité (aucune année racontée) n'affiche aucune
 *      étiquette de décennie, ni sur sa page ni dans les listes ; un article
 *      de souvenir affiche « Souvenir des années … » ;
 *   2. le calendrier parle d'anniversaires (et d'anniversaires de mariage),
 *      jamais de naissances ; les décès sont absents tant que le réglage
 *      administrateur n'est pas activé, puis « In memoriam » ; le bouton
 *      « Ajouter un événement » est visible par un administrateur, pas par
 *      un lecteur ; un événement proposé par un journaliste reste invisible
 *      partout jusqu'à validation ;
 *   3. aucune ligne « Décès » sur la fiche d'une personne sans date de décès.
 *
 * Tout se passe dans une transaction annulée : la base est rendue intacte.
 */

declare(strict_types=1);

/** Compte d'essai (admin, famille/branche = lecteur, personne = journaliste). */
function tests34_compte(string $type): int
{
    return db_insert('accounts', [
        'username'      => 'essai34-' . $type . '-' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai 1.34 ' . $type,
        'password_hash' => password_make('MotDePasseEssai123!'),
        'role'          => $type === 'admin' ? 'admin' : 'famille',
        'account_type'  => $type === 'admin' ? 'admin' : $type,
        'first_name'    => $type === 'personne' ? 'Essai' : '',
        'last_name'     => $type === 'personne' ? 'Journaliste' : '',
        'branch_id'     => null,
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
}

/** Personne d'essai pour le calendrier et les fiches. */
function tests34_personne(string $prenom, string $nom, string $naissance, string $deces, string $visibilite = 'famille'): int
{
    return db_insert('persons', [
        'first_name' => $prenom,
        'last_name'  => $nom,
        'sex'        => 'M',
        'birth_date' => $naissance,
        'death_date' => $deces,
        'visibility' => $visibilite,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
}

/** Le surtitre de la page d'un article (entre les balises du bloc meta). */
function tests34_surtitre(string $html): string
{
    return preg_match('/article__surtitre">(.*?)<\/p>/s', $html, $m) === 1 ? (string) $m[1] : '';
}

function tests_calendrier34(): void
{
    echo "1.34.0 — étiquette de décennie, calendrier, lignes vides\n";

    try {
        db_transaction(static function (): void {
            tests34_etiquette();
            tests34_calendrier();
            tests34_fiche();
            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    // Les caches statiques des réglages sont relus après l'annulation.
    calendrier_afficher_deces(true);
    site_settings_oublier();

    verifier('1.34 : la base est rendue intacte', true);
}

/** 1. Étiquette de décennie : seulement pour un souvenir, et nommée ainsi. */
function tests34_etiquette(): void
{
    $actu = ['title' => 'Une soupe pour l’hiver', 'summary' => 'Rien à dater.', 'published_at' => time()];
    verifier(
        'étiquette : un article d’actualité n’a aucune année de souvenir',
        article_millesime_souvenir($actu) === null && article_decennie_souvenir($actu) === 0
    );

    $souvenir = ['title' => 'Le mariage de 1958', 'summary' => '', 'published_at' => time()];
    verifier(
        'étiquette : l’année racontée donne la décennie de souvenir',
        article_millesime_souvenir($souvenir) === 1958 && article_decennie_souvenir($souvenir) === 1950
    );
    verifier(
        'étiquette : le libellé dit « Souvenir des années … »',
        decennie_souvenir_libelle(1950) === 'Souvenir des années 1950'
    );

    $decennies = editions_decennies([$actu, $souvenir]);
    verifier(
        'étiquette : la frise ne compte que les articles de souvenir',
        $decennies === [1950 => 1],
        'obtenu : ' . json_encode($decennies)
    );

    // Les vraies pages : un article public sans année, un article de souvenir.
    $slugActu = 'essai34-actu-' . bin2hex(random_bytes(3));
    $slugSouv = 'essai34-souvenir-' . bin2hex(random_bytes(3));
    foreach ([[$slugActu, 'Nouvelle du jour (essai 1.34)'], [$slugSouv, 'Le mariage de 1958 (essai 1.34)']] as [$slug, $titre]) {
        db_insert('articles', [
            'slug' => $slug, 'title' => $titre, 'summary' => '', 'body' => 'Texte.',
            'category' => '', 'visibility' => 'public', 'status' => 'publie',
            'published_at' => time() - 60, 'created_at' => time(), 'updated_at' => time(),
        ]);
    }

    $pageActu = tests_envoi_requete('GET', '/articles/' . $slugActu, null);
    $surtitreActu = tests34_surtitre((string) $pageActu['corps']);
    verifier(
        'étiquette : la page d’un article d’actualité n’affiche aucune décennie',
        $pageActu['code'] === 200 && stripos($surtitreActu, 'années') === false,
        'surtitre : ' . trim(strip_tags($surtitreActu))
    );

    $pageSouv = tests_envoi_requete('GET', '/articles/' . $slugSouv, null);
    $surtitreSouv = tests34_surtitre((string) $pageSouv['corps']);
    verifier(
        'étiquette : la page d’un souvenir affiche « Souvenir des années 1950 »',
        $pageSouv['code'] === 200 && str_contains($surtitreSouv, 'Souvenir des années 1950'),
        'surtitre : ' . trim(strip_tags($surtitreSouv))
    );

    $liste = tests_envoi_requete('GET', '/articles', null);
    verifier(
        'étiquette : la liste des articles nomme les souvenirs, sans « Années » seul',
        str_contains((string) $liste['corps'], 'Souvenir des années 1950')
            && preg_match('/(?<!des )Années \d{4}/', (string) $liste['corps']) !== 1
    );
}

/** 2. Calendrier : anniversaires, décès réglés, ajout et propositions. */
function tests34_calendrier(): void
{
    $admin       = tests34_compte('admin');
    $lecteur     = tests34_compte('branche');
    $journaliste = tests34_compte('personne');

    $annee = (int) date('Y');
    $ne      = tests34_personne('Né', 'ANNIV', '17 novembre 1963', '');
    $mort    = tests34_personne('Mort', 'MEMOIRE', '2 août 1905', '18 novembre 1982');
    $prive   = tests34_personne('Cache', 'SECRET34', '17 novembre 1970', '', 'prive');
    $epoux   = tests34_personne('Epoux', 'NOCES', '9 janvier 1930', '');
    $epouse  = tests34_personne('Epouse', 'NOCES', '30 mai 1932', '');
    $famille = db_insert('families', [
        'husband_id' => $epoux, 'wife_id' => $epouse,
        'marriage_date' => '12 novembre 1986', 'created_at' => time(), 'updated_at' => time(),
    ]);
    foreach ([$ne] as $enfant) {
        db_insert('family_children', ['family_id' => $famille, 'person_id' => $enfant]);
    }

    // --- Libellés et décès cachés par défaut -------------------------------
    $page = tests_envoi_requete('GET', '/calendrier?mois=' . $annee . '-11', $lecteur);
    $corps = (string) $page['corps'];
    verifier('calendrier : la page s’ouvre pour un lecteur', $page['code'] === 200, 'code ' . $page['code']);
    verifier_contient('calendrier : « Anniversaire de » avec l’âge', 'Anniversaire de', $corps);
    verifier(
        'calendrier : l’âge est celui de l’année affichée',
        str_contains($corps, '(' . ($annee - 1963) . ' ans)'),
        'âge attendu : ' . ($annee - 1963)
    );
    verifier_contient('calendrier : « Anniversaire de mariage de »', 'Anniversaire de mariage de', $corps);
    verifier(
        'calendrier : jamais « Naissance »',
        !str_contains($corps, 'Naissance'),
        'le mot « Naissance » apparaît'
    );
    verifier(
        'calendrier : aucun décès par défaut (ni libellé, ni nom)',
        !str_contains($corps, 'Décès') && !str_contains($corps, 'In memoriam') && !str_contains($corps, 'MEMOIRE'),
        'un décès apparaît sans le réglage'
    );
    verifier(
        'calendrier : une personne privée n’apparaît pas pour un lecteur',
        !str_contains($corps, 'SECRET34'),
        'la personne privée apparaît'
    );

    // --- Décès avec le réglage activé ---------------------------------------
    save_site_setting('calendrier_deces', '1');
    site_settings_oublier();
    calendrier_afficher_deces(true);
    $page = tests_envoi_requete('GET', '/calendrier?mois=' . $annee . '-11', $lecteur);
    verifier(
        'calendrier : avec le réglage, « In memoriam : Prénom NOM »',
        str_contains((string) $page['corps'], 'In memoriam') && str_contains((string) $page['corps'], 'Mort MEMOIRE'),
        'le décès réglé n’apparaît pas'
    );
    save_site_setting('calendrier_deces', '0');
    site_settings_oublier();
    calendrier_afficher_deces(true);

    // --- Le bouton, selon le compte ------------------------------------------
    $pageAdmin = tests_envoi_requete('GET', '/calendrier', $admin);
    verifier_contient('calendrier : un administrateur voit « Ajouter un événement »', 'Ajouter un événement', (string) $pageAdmin['corps']);
    verifier(
        'calendrier : un lecteur ne voit pas le bouton d’ajout',
        !str_contains($corps, 'Ajouter un événement') && !str_contains($corps, 'Proposer un événement'),
        'le bouton est visible pour un lecteur'
    );

    $formAdmin = tests_envoi_requete('GET', '/calendrier/ajouter', $admin);
    verifier(
        'calendrier : le formulaire d’ajout s’ouvre pour un administrateur',
        $formAdmin['code'] === 200 && str_contains((string) $formAdmin['corps'], 'name="start_date"'),
        'code ' . $formAdmin['code']
    );
    $formLecteur = tests_envoi_requete('GET', '/calendrier/ajouter', $lecteur);
    verifier('calendrier : le formulaire est refusé à un lecteur (403)', $formLecteur['code'] === 403, 'code ' . $formLecteur['code']);
    $formVisiteur = tests_envoi_requete('GET', '/calendrier/ajouter', null);
    verifier('calendrier : un visiteur est renvoyé vers la connexion', $formVisiteur['code'] === 303, 'code ' . $formVisiteur['code']);

    // --- Proposition d’un journaliste : invisible jusqu’à validation ---------
    tests_envoi_requete('GET', '/calendrier/ajouter', $journaliste);
    $jeton = (string) ($_SESSION['_csrf'] ?? '');
    $titrePropose = 'Fête proposée (essai 1.34)';
    $moisEvenement = sprintf('%04d-11-20', $annee);
    $envoi = tests_envoi_requete('POST', '/calendrier/ajouter', $journaliste, [
        '_csrf' => $jeton, 'title' => $titrePropose, 'start_date' => $moisEvenement,
        'type' => 'fete', 'visibility' => 'famille', 'description' => 'À valider.',
    ]);
    $propose = db_one('SELECT * FROM events WHERE title = ?', [$titrePropose]);
    verifier(
        'calendrier : la proposition du journaliste naît en attente',
        $envoi['code'] === 303 && $propose !== null && (string) $propose['status'] === 'en_attente',
        'code ' . $envoi['code']
    );

    foreach ([['lecteur', $lecteur], ['journaliste', $journaliste], ['administrateur (vue famille)', $admin]] as [$profil, $compte]) {
        $vue = tests_envoi_requete('GET', '/calendrier?mois=' . $annee . '-11', $compte);
        verifier(
            'calendrier : l’événement en attente est invisible (' . $profil . ')',
            !str_contains((string) $vue['corps'], $titrePropose),
            'l’événement en attente apparaît'
        );
    }
    verifier(
        'calendrier : l’événement en attente est absent des événements visibles',
        !in_array($titrePropose, array_column(evenements_visibles(500), 'title'), true)
    );

    $listeAdmin = tests_envoi_requete('GET', '/admin/calendrier', $admin);
    verifier(
        'calendrier : l’administrateur voit la proposition en attente',
        str_contains((string) $listeAdmin['corps'], $titrePropose)
            && str_contains((string) $listeAdmin['corps'], 'En attente'),
        'la proposition n’est pas dans l’administration'
    );

    if ($propose !== null) {
        tests_envoi_requete('GET', '/admin/calendrier', $admin);
        $jetonAdmin = (string) ($_SESSION['_csrf'] ?? '');
        $validation = tests_envoi_requete('POST', '/admin/calendrier/' . (int) $propose['id'] . '/valider', $admin, ['_csrf' => $jetonAdmin]);
        $vue = tests_envoi_requete('GET', '/calendrier?mois=' . $annee . '-11', $lecteur);
        verifier(
            'calendrier : après validation, l’événement apparaît',
            $validation['code'] === 303 && str_contains((string) $vue['corps'], $titrePropose),
            'code validation ' . $validation['code']
        );
    }

    // --- Ajout direct par un administrateur ----------------------------------
    tests_envoi_requete('GET', '/calendrier/ajouter', $admin);
    $jeton = (string) ($_SESSION['_csrf'] ?? '');
    $titreAdmin = 'Réunion ajoutée (essai 1.34)';
    $envoiAdmin = tests_envoi_requete('POST', '/calendrier/ajouter', $admin, [
        '_csrf' => $jeton, 'title' => $titreAdmin, 'start_date' => $moisEvenement,
        'type' => 'reunion', 'visibility' => 'famille',
    ]);
    $vue = tests_envoi_requete('GET', '/calendrier?mois=' . $annee . '-11', $lecteur);
    verifier(
        'calendrier : un événement ajouté par un administrateur est visible',
        $envoiAdmin['code'] === 303 && str_contains((string) $vue['corps'], $titreAdmin),
        'code ' . $envoiAdmin['code']
    );
}

/** 3. Aucune ligne « Décès » sans date de décès. */
function tests34_fiche(): void
{
    $lecteur = tests34_compte('branche');
    $vivant  = tests34_personne('Vivant', 'SANSDECES', '5 avril 1960', '');
    $defunt  = tests34_personne('Defunt', 'AVECDECES', '3 mars 1901', '2 février 1975');

    $page = tests_envoi_requete('GET', '/arbre/' . $vivant, $lecteur);
    verifier(
        'fiche : aucune ligne « Décès » sans date de décès',
        $page['code'] === 200 && !str_contains((string) $page['corps'], '<dt>Décès</dt>')
            && !str_contains((string) $page['corps'], 'Décès :'),
        'une ligne « Décès » vide apparaît'
    );

    $pageDefunt = tests_envoi_requete('GET', '/arbre/' . $defunt, $lecteur);
    verifier(
        'fiche : une date de décès connue reste affichée',
        str_contains((string) $pageDefunt['corps'], '<dt>Décès</dt>')
            && str_contains((string) $pageDefunt['corps'], '2 février 1975'),
        'le décès connu a disparu'
    );

    verifier(
        'fiche : les dates de vie ne gardent pas de tiret en attente',
        person_dates(['birth_date' => '1901', 'death_date' => '']) === '1901'
            && person_dates(['birth_date' => '1901', 'death_date' => '1975']) === '1901 – 1975'
            && person_dates(['birth_date' => '', 'death_date' => '']) === ''
    );
}
