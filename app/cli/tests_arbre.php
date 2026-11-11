<?php
/**
 * Essais — l'arbre « chêne gravé » de la page /arbre (1.33.0).
 *
 * Ce que l'on vérifie, dans l'ordre du besoin exprimé :
 *
 *   1. le rendu avec un jeu de données : la page s'ouvre, la gravure est là
 *      (WebP **et** repli JPEG), les trois parties portent leurs titres, la
 *      personne centrale est sur le tronc, ses ancêtres et ses descendants
 *      autour d'elle ;
 *   2. l'accès : un visiteur non connecté est renvoyé vers la connexion, avec
 *      ou sans `?racine=` — l'arbre n'est jamais public (décision 1.31.0) ;
 *   3. les droits : un lecteur ne voit **ni le nom, ni le lien, ni le nombre**
 *      d'une personne privée ; la personne privée demandée en `?racine=` ne
 *      remplace pas la personne par défaut, et l'administrateur, lui, la voit
 *      (la contre-épreuve prouve que l'essai regarde bien quelque chose) ;
 *   4. la mise en page : huit enfants tiennent sur une rangée, dix donnent
 *      « voir plus » (jamais de cartes tassées), et « voir plus » montre bien
 *      les deux personnes qui manquaient ;
 *   5. l'accessibilité et la sécurité : aucun attribut `style`, aucun script en
 *      ligne, aucune ressource extérieure, chaque carte est un lien vers une
 *      fiche et les noms sont dans le texte des liens.
 *
 * Tout se passe dans une transaction annulée : la base du site est intacte.
 */

declare(strict_types=1);

/** Personne d'essai (visibilité « famille » par défaut). */
function tests_arbre_personne(string $prenom, string $nom, string $visibilite = 'famille', string $sexe = 'M'): int
{
    return db_insert('persons', [
        'first_name' => $prenom,
        'last_name'  => $nom,
        'sex'        => $sexe,
        'birth_date' => '1900',
        'visibility' => $visibilite,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
}

/** Famille d'essai : un couple (ou un parent seul) et ses enfants. */
function tests_arbre_famille(?int $mari, ?int $femme, array $enfants): int
{
    $id = db_insert('families', [
        'husband_id' => $mari,
        'wife_id'    => $femme,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
    foreach ($enfants as $enfant) {
        db_insert('family_children', ['family_id' => $id, 'person_id' => $enfant]);
    }

    return $id;
}

/** Le morceau de HTML entre deux repères (chaîne vide si l'un manque). */
function tests_arbre_morceau(string $html, string $debut, string $fin = ''): string
{
    $a = strpos($html, $debut);
    if ($a === false) {
        return '';
    }
    if ($fin === '') {
        return substr($html, $a);
    }
    $b = strpos($html, $fin, $a);
    return $b === false ? substr($html, $a) : substr($html, $a, $b - $a);
}

/** Toute la famille « arbre ». */
/** Document d'essai (photo), avec ou sans miniature. */
function tests_arbre_document(string $titre, string $visibilite = 'famille', bool $miniature = true): int
{
    $maintenant = time();
    return db_insert('documents', [
        'title' => $titre, 'kind' => 'photo', 'visibility' => $visibilite,
        'rel_path' => 'essai/' . $titre . '.jpg',
        'thumb_path' => $miniature ? 'essai/' . $titre . '-mini.jpg' : null,
        'original_name' => $titre . '.jpg', 'mime' => 'image/jpeg', 'ext' => 'jpg',
        'size' => 10, 'created_at' => $maintenant, 'updated_at' => $maintenant,
    ]);
}

/** Donne un portrait (photo de la fiche) à une personne d'essai. */
function tests_arbre_portrait(int $personId, int $documentId): void
{
    db_exec('UPDATE persons SET photo_document_id = ? WHERE id = ?', [$documentId, $personId]);
}

function tests_arbre(): void
{
    echo "Arbre « chêne gravé » — rendu, droits, rangées, accessibilité\n";

    try {
        db_transaction(static function (): void {
            tests_arbre_rendu();
            tests_arbre_acces();
            tests_arbre_droits();
            tests_arbre_rangees();
            tests_arbre_exploration_parente();
            tests_arbre_schema();

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    verifier('arbre : la base est rendue intacte', true);
}

/** 1. Le rendu, avec un jeu de données complet. */
function tests_arbre_rendu(): void
{
    $admin = tests_envoi_compte('admin');

    // Trois générations au-dessus, deux en dessous.
    $agp = tests_arbre_personne('Théodore', 'CHENE', 'famille');
    $agm = tests_arbre_personne('Adèle', 'CHENE', 'famille', 'F');
    $agp2 = tests_arbre_personne('Sylvain', 'CHENE', 'famille');
    $agm2 = tests_arbre_personne('Reine', 'CHENE', 'famille', 'F');
    $gp  = tests_arbre_personne('Pierre', 'CHENE', 'famille');
    $gm  = tests_arbre_personne('Louise', 'CHENE', 'famille', 'F');
    $moi = tests_arbre_personne('Casimir', 'CHENE', 'famille');
    $elle = tests_arbre_personne('Odile', 'CHENE', 'famille', 'F');
    $fils = tests_arbre_personne('Serge', 'CHENE', 'famille');
    $petit = tests_arbre_personne('Léon', 'CHENE', 'famille');

    // Arrière-arrière-grands-parents (niveau 3), puis grands-parents, puis parents.
    tests_arbre_famille($agp2, $agm2, [$agp]);
    tests_arbre_famille($agp, $agm, [$gp]);
    tests_arbre_famille($gp, $gm, [$moi]);
    tests_arbre_famille($moi, $elle, [$fils]);
    tests_arbre_famille($fils, null, [$petit]);

    $page = tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin);
    $html = (string) $page['corps'];

    verifier('arbre : la page s’ouvre pour un compte connecté', $page['code'] === 200, 'code ' . $page['code']);
    verifier_contient('arbre : le titre est là', 'Arbre généalogique', $html);
    foreach (['Les ancêtres', 'Le tronc', 'Les descendants'] as $titre) {
        verifier_contient('arbre : la partie « ' . $titre . ' » est présente', $titre, $html);
    }

    // La gravure : WebP d'abord, JPEG en repli, jamais une image cassée.
    verifier_contient('arbre : la gravure est proposée en WebP', '/assets/arbre-chene.webp', $html);
    verifier_contient('arbre : la gravure a un repli JPEG', '/assets/arbre-chene.jpg', $html);
    verifier('arbre : les deux fichiers de la gravure existent et pèsent moins de 300 Ko',
        filesize(HDF_ROOT . '/public/assets/arbre-chene.webp') <= 300 * 1024
        && filesize(HDF_ROOT . '/public/assets/arbre-chene.jpg') > 0,
        'webp : ' . (int) @filesize(HDF_ROOT . '/public/assets/arbre-chene.webp') . ' octets');
    verifier_contient('arbre : la gravure est décrite par sa légende', 'les ancêtres dans les branches', $html);
    verifier('arbre : la gravure porte un texte de remplacement vide (décor) et ses dimensions',
        preg_match('/<img[^>]*alt=""[^>]*width="860"[^>]*height="1290"/', $html) === 1);

    // La personne centrale sur le tronc, son conjoint avec elle.
    $tronc = tests_arbre_morceau($html, 'arbre-chene__partie--tronc', 'arbre-chene__partie--racines');
    verifier_contient('arbre : la personne demandée est sur le tronc', 'Casimir CHENE', $tronc);
    verifier_contient('arbre : son conjoint partage le tronc', 'Odile CHENE', $tronc);
    verifier_contient('arbre : la barre mène à la fiche choisie', 'href="/arbre/' . $moi . '">Voir la fiche</a>', $html);

    // Les générations sont nommées, et ce sont les bonnes personnes.
    foreach ([
        'Arrière-grands-parents' => 'Théodore CHENE',
        'Grands-parents'         => 'Pierre CHENE',
        'Parents'                => 'Pierre CHENE',
    ] as $libelle => $nom) {
        verifier_contient('arbre : « ' . $libelle . ' » est écrit', $libelle, $html);
    }
    verifier_contient('arbre : l’arrière-grand-père est dans les branches', 'Théodore CHENE', $html);
    verifier_contient('arbre : les petits-enfants sont dans les racines', 'Léon CHENE', $html);
    verifier_contient('arbre : « Petits-enfants » est écrit', 'Petits-enfants', $html);
}

/** 2. L'accès : jamais d'arbre pour un visiteur. */
function tests_arbre_acces(): void
{
    foreach (['/arbre', '/arbre?racine=1', '/arbre?racine=1&plus=1'] as $url) {
        $page = tests_envoi_requete('GET', $url, null);
        verifier(
            'arbre : un visiteur est renvoyé vers la connexion (' . $url . ')',
            $page['code'] === 303 && str_contains($page['location'], '/connexion'),
            'code ' . $page['code'] . ' vers ' . $page['location']
        );
        verifier(
            'arbre : la page du visiteur ne contient aucun nom de l’arbre (' . $url . ')',
            !str_contains((string) $page['corps'], 'arbre-chene__nom'),
            'le corps contient un nœud de l’arbre'
        );
    }
}

/** 3. Les droits : la personne privée n'existe pas pour un lecteur. */
function tests_arbre_droits(): void
{
    $admin = tests_envoi_compte('admin');
    $lecteur = tests_envoi_compte('famille');

    $pere = tests_arbre_personne('Anselme', 'SECRET', 'famille');
    $moi  = tests_arbre_personne('Berthe', 'SECRET', 'famille', 'F');
    $cache = tests_arbre_personne('Inconnu', 'SECRET', 'prive');
    tests_arbre_famille($pere, null, [$moi]);
    tests_arbre_famille($moi, null, [$cache]);

    // Le lecteur : ni le nom, ni le lien, ni le nombre.
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $moi, $lecteur);
    $html = (string) $page['corps'];
    verifier('arbre : la page s’ouvre pour un lecteur', $page['code'] === 200, 'code ' . $page['code']);
    verifier_contient('arbre : le lecteur voit la personne de sa famille', 'Berthe SECRET', $html);
    verifier('arbre : le lecteur voit la fiche mais pas l’action de modification',
        str_contains($html, '>Voir la fiche</a>') && !str_contains($html, '>Modifier la fiche</a>'));
    $journaliste = tests_envoi_compte('famille');
    db_update('accounts', $journaliste, ['account_type' => 'personne', 'journaliste_validation' => 1]);
    $validation = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $journaliste)['corps'];
    verifier('arbre : un journaliste avec validation ne voit pas « Modifier la fiche »',
        !str_contains($validation, '>Modifier la fiche</a>'));
    $direct = tests_envoi_compte('famille');
    db_update('accounts', $direct, ['account_type' => 'personne', 'journaliste_validation' => 0, 'journaliste_toutes_branches' => 1]);
    $htmlDirect = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $direct)['corps'];
    verifier('arbre : un journaliste autorisé voit « Modifier la fiche »',
        str_contains($htmlDirect, 'href="/arbre/' . $moi . '/modifier">Modifier la fiche</a>'));
    verifier('arbre : le lecteur ne voit pas le nom d’une personne privée',
        !str_contains($html, 'Inconnu'), 'le nom « Inconnu » apparaît dans la page');
    verifier('arbre : le lecteur ne reçoit aucun lien vers une fiche privée',
        !str_contains($html, '/arbre/' . $cache), 'le lien /arbre/' . $cache . ' est dans la page');
    // Aucune génération de descendants n'est écrite : l'enfant privé ne laisse
    // ni carte, ni légende, ni compteur derrière lui.
    verifier('arbre : le compte des descendants ne trahit pas la personne privée',
        !str_contains($html, 'chene-descendants-1')
        && str_contains($html, 'Aucun enfant connu'),
        'une légende de descendants est présente alors qu’aucun ne doit se voir');
    verifier('arbre : la personne privée n’est pas proposée dans le sélecteur',
        !str_contains($html, 'value="' . $cache . '"'), 'une option porte l’identifiant ' . $cache);

    // Le lecteur demande la personne privée en personne centrale : on ne la lui
    // donne pas, et l'on ne lui dit pas qu'elle existe.
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $cache, $lecteur);
    $html = (string) $page['corps'];
    verifier('arbre : une personne privée demandée en racine ne s’affiche pas',
        $page['code'] === 200 && !str_contains($html, 'Inconnu') && !str_contains($html, '/arbre/' . $cache),
        'code ' . $page['code']);
    verifier('arbre : la page retombe alors sur une personne visible, sans le dire',
        preg_match('/arbre-chene__noeud--tronc.*?href="\/arbre\/(\d+)"/s', $html, $m) === 1
        && (int) $m[1] !== $cache,
        'racine affichée : ' . ($m[1] ?? 'aucune'));

    // Contre-épreuve : l'administrateur, lui, la voit — et la compte.
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin);
    verifier_contient('arbre : l’administrateur voit la personne privée', 'Inconnu SECRET', (string) $page['corps']);
    $page = tests_envoi_requete('GET', '/arbre?racine=' . $cache, $admin);
    verifier('arbre : l’administrateur peut la mettre au centre',
        str_contains((string) $page['corps'], 'Inconnu SECRET')
        && str_contains((string) $page['corps'], 'arbre-chene__noeud--tronc'),
        'code ' . $page['code']);
}

/** 4. Les rangées : huit cartes, puis « voir plus ». */
function tests_arbre_rangees(): void
{
    $admin = tests_envoi_compte('admin');

    $moi = tests_arbre_personne('Honoré', 'RANGEE', 'famille');
    $huit = [];
    for ($i = 1; $i <= 8; $i++) {
        $huit[] = tests_arbre_personne('Huit' . $i, 'RANGEE', 'famille');
    }
    tests_arbre_famille($moi, null, $huit);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin)['corps'];
    $descendants = tests_arbre_morceau($html, 'arbre-chene__partie--racines', 'arbre-chene__plus');
    verifier('arbre : huit enfants tiennent sur une seule rangée',
        substr_count($descendants, '<li class="arbre-chene__noeud"') === 8
        && str_contains($descendants, 'arbre-chene__rangee--8'),
        'cartes : ' . substr_count($descendants, '<li class="arbre-chene__noeud"'));
    verifier('arbre : huit enfants ne demandent pas « voir plus »',
        !str_contains($html, 'autres personnes de la famille'),
        'un lien « voir plus » est présent');

    // Dix enfants : huit cartes, puis un lien qui dit combien il en reste.
    $dix = [];
    for ($i = 9; $i <= 10; $i++) {
        $dix[] = tests_arbre_personne('Dix' . $i, 'RANGEE', 'famille');
    }
    tests_arbre_famille($moi, null, $dix);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin)['corps'];
    $descendants = tests_arbre_morceau($html, 'arbre-chene__partie--racines', 'arbre-chene__plus');
    verifier('arbre : au-delà de huit, la rangée reste à huit cartes',
        substr_count($descendants, '<li class="arbre-chene__noeud"') === 8,
        'cartes : ' . substr_count($descendants, '<li class="arbre-chene__noeud"'));
    verifier_contient('arbre : « voir plus » dit combien de personnes manquent',
        'Voir les 2 autres personnes de la famille', $html);
    verifier_contient('arbre : « voir plus » garde la personne centrale',
        '/arbre?racine=' . $moi . '&amp;plus=1', $html);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi . '&plus=1', $admin)['corps'];
    $descendants = tests_arbre_morceau($html, 'arbre-chene__partie--racines', 'arbre-chene__plus');
    verifier('arbre : « voir plus » montre les dix enfants dans une rangée logique',
        substr_count($descendants, '<li class="arbre-chene__noeud"') === 10
        && str_contains($descendants, 'arbre-chene__rangee--10')
        && str_contains($html, 'arbre-chene__dessin--elargi'),
        'cartes : ' . substr_count($descendants, '<li class="arbre-chene__noeud"'));
    verifier_contient('arbre : « voir moins » est proposé ensuite', 'Voir moins', $html);

    // Aucune position en style, aucun script en ligne, rien d'extérieur.
    verifier('arbre : aucun attribut style= dans la page',
        preg_match('/\sstyle\s*=/i', $html) !== 1, 'un attribut style= est présent');
    verifier('arbre : aucun script en ligne',
        preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $html) !== 1, 'un <script> sans src est présent');
    verifier('arbre : aucune ressource extérieure',
        preg_match('#(?:src|href)="(?:https?:)?//#i', $html) !== 1, 'une adresse extérieure est présente');
    // 1.40.0 : la carte entière centre l'arbre sur la personne (lien
    // serveur, sans JavaScript), la barre extérieure ouvre sa fiche.
    verifier('arbre : chaque carte centre l’arbre sur sa personne',
        preg_match_all('/<li class="arbre-chene__noeud[^>]*>\s*<a class="arbre-chene__lien"[^>]*href="\/arbre\?racine=\d+#chene"/', $html) ===
        substr_count($html, '<li class="arbre-chene__noeud'),
        'cartes liées : ' . preg_match_all('/<li class="arbre-chene__noeud[^>]*>\s*<a class="arbre-chene__lien"[^>]*href="\/arbre\?racine=\d+#chene"/', $html)
        . ' sur ' . substr_count($html, '<li class="arbre-chene__noeud'));
    verifier('arbre : aucun lien Fiche dans les cartes',
        !str_contains($html, 'arbre-chene__fiche'), 'un lien Fiche est dans une carte');
    verifier_contient('arbre : la barre propose « Voir la fiche »', '>Voir la fiche</a>', $html);
    verifier_contient('arbre : la barre propose « Modifier la fiche » à l’administrateur', '>Modifier la fiche</a>', $html);
}

/** 5. Exploration et parenté lisible (1.35.0). */
function tests_arbre_exploration_parente(): void
{
    $admin = tests_envoi_compte('admin');
    $lecteur = tests_envoi_compte('famille');

    // Même famille que le rendu : Casimir, sa conjointe Odile (qui a ses
    // propres parents, d'une autre famille), parents, grands-parents,
    // arrière-grands-parents, un fils, un petit-fils.
    $agp = tests_arbre_personne('Théodore', 'EXPLOR', 'famille');
    $agm = tests_arbre_personne('Adèle', 'EXPLOR', 'famille', 'F');
    $gp  = tests_arbre_personne('Pierre', 'EXPLOR', 'famille');
    $gm  = tests_arbre_personne('Louise', 'EXPLOR', 'famille', 'F');
    $moi = tests_arbre_personne('Casimir', 'EXPLOR', 'famille');
    $elle = tests_arbre_personne('Odile', 'EXPLOR', 'famille', 'F');
    $fils = tests_arbre_personne('Serge', 'EXPLOR', 'famille');
    $petit = tests_arbre_personne('Léon', 'EXPLOR', 'famille');
    $beauP = tests_arbre_personne('Henri', 'AILLEURS', 'famille');
    $beauM = tests_arbre_personne('Marcelle', 'AILLEURS', 'famille', 'F');

    $aagp = tests_arbre_personne('Sylvestre', 'EXPLOR', 'famille');
    tests_arbre_famille($aagp, null, [$agp]);
    tests_arbre_famille($agp, $agm, [$gp]);
    tests_arbre_famille($gp, $gm, [$moi]);
    tests_arbre_famille($moi, $elle, [$fils]);
    tests_arbre_famille($fils, null, [$petit]);
    tests_arbre_famille($beauP, $beauM, [$elle]);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin)['corps'];
    $dessin = tests_arbre_morceau($html, 'arbre-chene__dessin', 'arbre-chene__plus');

    // --- La barre d'outils et le script externe ---------------------------
    verifier_contient('arbre 1.35 : la barre d’outils d’exploration est là',
        'role="toolbar" aria-label="Exploration de l’arbre"', $html);
    foreach (['Zoomer', 'Dézoomer', 'Voir à gauche', 'Voir en haut', 'Voir en bas',
              'Voir à droite', 'Centrer la personne sélectionnée', 'Voir tout l’arbre'] as $libelle) {
        verifier_contient('arbre 1.35 : le bouton « ' . $libelle . ' » est là',
            'aria-label="' . $libelle . '"', $html);
        verifier_contient('arbre 1.35 : le bouton « ' . $libelle . ' » a son title',
            'title="' . $libelle . '"', $html);
    }
    verifier_contient('arbre 1.35 : le niveau de zoom en pourcentage est là',
        'data-explo-niveau', $html);
    verifier('arbre 1.35 : sans JavaScript, la barre est cachée (attribut hidden)',
        preg_match('/data-explo-barre hidden/', $html) === 1, 'la barre n’est pas cachée au départ');
    verifier_contient('arbre 1.35 : le script d’exploration est externe et propre à la page',
        '/assets/arbre-exploration.js', $html);
    verifier('arbre 1.35 : aucun style en ligne malgré l’exploration',
        preg_match('/\sstyle\s*=/i', $html) !== 1, 'un attribut style= est présent');
    verifier('arbre 1.35 : aucun script en ligne',
        preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $html) !== 1, 'un <script> sans src est présent');

    // --- Les relations, sur les cartes et dans les noms accessibles --------
    foreach ([
        'Casimir EXPLOR, personne sélectionnée' => 'la personne sélectionnée',
        'Odile EXPLOR, conjointe'               => 'la conjointe',
        'Pierre EXPLOR, parent'                 => 'un parent',
        'Adèle EXPLOR, grand-parent'            => 'une grand-mère affichée « Grand-parent »',
        'Sylvestre EXPLOR, arrière-grand-parent' => 'un arrière-grand-parent',
        'Serge EXPLOR, enfant'                  => 'un enfant',
        'Léon EXPLOR, petit-enfant'             => 'un petit-enfant',
    ] as $aria => $quoi) {
        // 1.40.0 : le libellé dit aussi ce que fait le clic — centrer.
        verifier_contient('arbre 1.35 : le nom accessible dit ' . $quoi,
            'aria-label="' . $aria . ' — centrer l’arbre sur cette personne"', $dessin);
    }
    verifier_contient('arbre 1.35 : la relation est écrite en petit sur la carte',
        'arbre-chene__relation">Conjointe<', $dessin);
    verifier('arbre 1.35 : la conjointe n’est jamais une sœur',
        !str_contains($dessin, 'Odile EXPLOR, sœur')
        && str_contains($dessin, 'Odile EXPLOR, conjointe'),
        'la conjointe est présentée comme une sœur');
    verifier('arbre 1.35 : les parents de la conjointe (autre famille) ne sont pas inventés comme ancêtres',
        !str_contains($dessin, 'Henri AILLEURS') && !str_contains($dessin, 'Marcelle AILLEURS'),
        'un beau-parent de naissance apparaît dans le dessin');

    // Le couple a son trait, et les enfants sont reliés par un SVG du serveur.
    verifier('arbre 1.35 : le trait en pointillés du couple est marqué',
        str_contains($dessin, 'arbre-chene__noeud--couple'), 'aucune carte de couple marquée');
    verifier('arbre 1.35 : les traits parents-enfants sont dessinés par le serveur',
        str_contains($dessin, '<svg class="arbre-chene__traits"') && str_contains($dessin, '<line'),
        'aucun SVG de liaison');

    // --- La fonction de parenté, directement --------------------------------
    // Une racine neuve (son contexte n'a encore jamais été calculé) : un
    // frère dans la même famille, et la nouvelle épouse du père.
    $papaF = tests_arbre_personne('Norbert', 'FRATRIE', 'famille');
    $mamanF = tests_arbre_personne('Bertille', 'FRATRIE', 'famille', 'F');
    $racineF = tests_arbre_personne('Urbain', 'FRATRIE', 'famille');
    $frere = tests_arbre_personne('Rémi', 'FRATRIE', 'famille');
    $belleMere = tests_arbre_personne('Josette', 'FRATRIE', 'famille', 'F');
    tests_arbre_famille($papaF, $mamanF, [$racineF, $frere]);
    tests_arbre_famille($papaF, $belleMere, []);
    $ligne = static fn (int $id): array => db_one('SELECT * FROM persons WHERE id = ?', [$id]);
    verifier('arbre 1.35 : un frère est un frère',
        arbre_chene_relation($racineF, $ligne($frere))['libelle'] === 'Frère',
        arbre_chene_relation($racineF, $ligne($frere))['libelle']);
    verifier('arbre 1.35 : la nouvelle épouse du père est une belle-parent',
        arbre_chene_relation($racineF, $ligne($belleMere))['libelle'] === 'Beau-parent',
        arbre_chene_relation($racineF, $ligne($belleMere))['libelle']);

    // Un conjoint qui serait aussi, dans les données, un enfant des mêmes
    // parents reste un conjoint : le couple passe avant la fratrie.
    $conjointeAussiSoeur = tests_arbre_personne('Alix', 'DOUBLE', 'famille', 'F');
    $racineDouble = tests_arbre_personne('Bruno', 'DOUBLE', 'famille');
    $papaDouble = tests_arbre_personne('Noël', 'DOUBLE', 'famille');
    tests_arbre_famille($papaDouble, null, [$racineDouble, $conjointeAussiSoeur]);
    tests_arbre_famille($racineDouble, $conjointeAussiSoeur, []);
    verifier('arbre 1.35 : un conjoint reste un conjoint, même frère ou sœur dans les données',
        arbre_chene_relation($racineDouble, $ligne($conjointeAussiSoeur))['cle'] === 'conjoint',
        arbre_chene_relation($racineDouble, $ligne($conjointeAussiSoeur))['libelle']);

    // --- Les traits ne montrent pas ce que le compte ne peut pas voir -------
    $moi2 = tests_arbre_personne('Berthe', 'TRAITS', 'famille', 'F');
    $visible = tests_arbre_personne('Visible', 'TRAITS', 'famille');
    $cache = tests_arbre_personne('Invisible', 'TRAITS', 'prive');
    tests_arbre_famille($moi2, null, [$visible, $cache]);

    $htmlLecteur = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi2, $lecteur)['corps'];
    $dessinLecteur = tests_arbre_morceau($htmlLecteur, 'arbre-chene__dessin', 'arbre-chene__plus');
    verifier('arbre 1.35 : le lecteur voit l’enfant visible et son trait',
        str_contains($dessinLecteur, 'Visible TRAITS') && str_contains($dessinLecteur, '<svg class="arbre-chene__traits"'),
        'le trait de l’enfant visible manque');
    verifier('arbre 1.35 : aucun trait vers l’enfant privé (2 segments seulement : descente et arrivée)',
        substr_count($dessinLecteur, '<line') === 2,
        'segments : ' . substr_count($dessinLecteur, '<line'));
    $htmlAdmin = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi2, $admin)['corps'];
    $dessinAdmin = tests_arbre_morceau($htmlAdmin, 'arbre-chene__dessin', 'arbre-chene__plus');
    verifier('arbre 1.35 : contre-épreuve, l’administrateur a les traits des deux enfants (4 segments)',
        substr_count($dessinAdmin, '<line') === 4,
        'segments : ' . substr_count($dessinAdmin, '<line'));
}

/** 6. Le schéma 1.40.0 : rangée du tronc, photos rondes, traits, droits. */
function tests_arbre_schema(): void
{
    $admin = tests_envoi_compte('admin');
    $lecteur = tests_envoi_compte('famille');

    // Une famille complète : deux parents, la personne choisie, son frère,
    // sa sœur (privée), sa conjointe — qui a ses propres parents, ailleurs.
    $papa = tests_arbre_personne('Papa', 'SCHEMA', 'famille');
    $maman = tests_arbre_personne('Maman', 'SCHEMA', 'famille', 'F');
    $moi = tests_arbre_personne('Racine', 'SCHEMA', 'famille');
    $frere = tests_arbre_personne('Frangin', 'SCHEMA', 'famille');
    $soeur = tests_arbre_personne('Cachee', 'SCHEMA', 'prive', 'F');
    $elle = tests_arbre_personne('Epouse', 'SCHEMA', 'famille', 'F');
    $beauP = tests_arbre_personne('Beaupere', 'HORSFAMILLE', 'famille');
    $beauM = tests_arbre_personne('Bellemere', 'HORSFAMILLE', 'famille', 'F');
    tests_arbre_famille($papa, $maman, [$moi, $frere, $soeur]);
    tests_arbre_famille($moi, $elle, []);
    tests_arbre_famille($beauP, $beauM, [$elle]);

    // Les portraits : visible avec miniature (moi), visible sans miniature
    // (papa, repli sur le fichier), privé (la conjointe), absent (le frère).
    $docMoi = tests_arbre_document('schema-moi');
    $docPapa = tests_arbre_document('schema-papa', 'famille', false);
    $docElle = tests_arbre_document('schema-elle', 'prive');
    tests_arbre_portrait($moi, $docMoi);
    tests_arbre_portrait($papa, $docPapa);
    tests_arbre_portrait($elle, $docElle);

    $html = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $lecteur)['corps'];
    $dessin = tests_arbre_morceau($html, 'arbre-chene__dessin', 'arbre-explo__indice');
    $tronc = tests_arbre_morceau($html, 'arbre-chene__partie--tronc', 'arbre-chene__partie--racines');
    $branches = tests_arbre_morceau($html, 'arbre-chene__partie--branches', 'arbre-chene__partie--tronc');

    // --- La rangée du tronc : personne, conjoint, frères et sœurs ---------
    $posMoi = strpos($tronc, 'Racine SCHEMA');
    $posElle = strpos($tronc, 'Epouse SCHEMA');
    $posFrere = strpos($tronc, 'Frangin SCHEMA');
    verifier('arbre 1.40 : le tronc aligne personne, conjoint puis frère, dans cet ordre',
        $posMoi !== false && $posElle !== false && $posFrere !== false
        && $posMoi < $posElle && $posElle < $posFrere,
        'positions : ' . var_export([$posMoi, $posElle, $posFrere], true));
    verifier_contient('arbre 1.40 : le conjoint est dit conjoint sur le tronc',
        'arbre-chene__relation">Conjointe<', $tronc);
    verifier_contient('arbre 1.40 : le frère est dit frère sur le tronc',
        'arbre-chene__relation">Frère<', $tronc);
    verifier('arbre 1.40 : le conjoint n’est jamais un frère ou une sœur',
        !str_contains($tronc, 'Epouse SCHEMA, sœur') && !str_contains($tronc, 'Epouse SCHEMA, frère'),
        'la conjointe est présentée comme fratrie');
    verifier('arbre 1.40 : la sœur privée n’a ni carte ni nom pour le lecteur',
        !str_contains($html, 'Cachee'), 'le nom « Cachee » apparaît');
    verifier('arbre 1.40 : les parents du conjoint ne sont pas dans le schéma',
        !str_contains($dessin, 'HORSFAMILLE'), 'un parent du conjoint apparaît');
    verifier('arbre 1.40 : la personne sélectionnée est marquée pour le centrage',
        str_contains($tronc, 'data-relation="selection" data-explo-centre="1"'),
        'le centre d’exploration manque sur le tronc');
    verifier('arbre 1.40 : la carte de la personne sélectionnée la recentre',
        str_contains($tronc, 'href="/arbre?racine=' . $moi . '#chene"'),
        'le lien de centrage manque');

    // --- Les photos rondes -------------------------------------------------
    verifier_contient('arbre 1.40 : le portrait visible est servi en miniature',
        '/media/miniature/' . $docMoi, $dessin);
    verifier_contient('arbre 1.40 : le portrait a son texte de remplacement',
        'alt="Portrait de Racine SCHEMA"', $dessin);
    verifier_contient('arbre 1.40 : sans miniature, le portrait passe par le fichier',
        '/f/' . $docPapa, $dessin);
    verifier('arbre 1.40 : le portrait privé de la conjointe ne laisse aucune adresse',
        !str_contains($html, '/media/miniature/' . $docElle) && !str_contains($html, '/f/' . $docElle),
        'une adresse du portrait privé est dans la page');
    verifier('arbre 1.40 : sans portrait visible, la silhouette du site prend le relais',
        substr_count($dessin, 'arbre-chene__photo--silhouette') >= 2
        && str_contains($dessin, 'arbre-chene__photo arbre-chene__photo--silhouette'),
        'silhouettes : ' . substr_count($dessin, 'arbre-chene__photo--silhouette'));
    verifier('arbre 1.40 : la silhouette n’a pas de texte de remplacement (décor)',
        preg_match('/arbre-chene__photo--silhouette"\s+src="[^"]*silhouette\.svg[^"]*" alt=""/', $dessin) === 1,
        'la silhouette n’est pas un décor muet');
    verifier('arbre 1.40 : plus de dates sur les cartes (elles restent sur la fiche)',
        !str_contains($dessin, 'arbre-chene__dates'), 'une date est encore sur une carte');

    // Contre-épreuve : l'administrateur voit le portrait de la conjointe
    // et la carte de la sœur — les essais ci-dessus regardent donc bien
    // une question de droits, pas une absence de données.
    $htmlAdmin = (string) tests_envoi_requete('GET', '/arbre?racine=' . $moi, $admin)['corps'];
    $dessinAdmin = tests_arbre_morceau($htmlAdmin, 'arbre-chene__dessin', 'arbre-explo__indice');
    verifier_contient('arbre 1.40 : contre-épreuve, l’administrateur voit le portrait privé',
        '/media/miniature/' . $docElle, $dessinAdmin);
    verifier_contient('arbre 1.40 : contre-épreuve, l’administrateur voit la sœur',
        'Cachee SCHEMA', $dessinAdmin);

    // --- Les traits : vers les enfants des parents affichés, jamais vers
    // le conjoint ni vers une personne invisible. Parents au centre 50 % ;
    // tronc du lecteur [Racine 16.67, Epouse 50, Frangin 83.33] : descente,
    // barre et deux arrivées = 4 segments, aucune arrivée à 50 %.
    verifier('arbre 1.40 : les parents se relient à leurs enfants du tronc (4 segments)',
        substr_count($branches, '<line') === 4,
        'segments : ' . substr_count($branches, '<line'));
    verifier('arbre 1.40 : aucun trait ne descend sur le conjoint',
        !str_contains($branches, 'x1="50" y1="12"'),
        'un trait arrive au centre du conjoint');
    $branchesAdmin = tests_arbre_morceau($htmlAdmin, 'arbre-chene__partie--branches', 'arbre-chene__partie--tronc');
    verifier('arbre 1.40 : contre-épreuve, la sœur visible ajoute son arrivée (5 segments)',
        substr_count($branchesAdmin, '<line') === 5,
        'segments : ' . substr_count($branchesAdmin, '<line'));

    // --- Cas limites --------------------------------------------------------
    // Un seul enfant : descente et arrivée alignées, pas de barre.
    $solo = tests_arbre_personne('Unique', 'ENFANT', 'famille');
    $unique = tests_arbre_personne('Petit', 'ENFANT', 'famille');
    tests_arbre_famille($solo, null, [$unique]);
    $htmlUn = (string) tests_envoi_requete('GET', '/arbre?racine=' . $solo, $admin)['corps'];
    $racinesUn = tests_arbre_morceau($htmlUn, 'arbre-chene__partie--racines', 'arbre-explo__indice');
    verifier('arbre 1.40 : un seul enfant, deux segments alignés',
        substr_count($racinesUn, '<line') === 2,
        'segments : ' . substr_count($racinesUn, '<line'));

    // Couple sans enfants : les deux cartes, le trait du couple, rien d’autre.
    $luiC = tests_arbre_personne('Lui', 'COUPLE', 'famille');
    $elleC = tests_arbre_personne('Elle', 'COUPLE', 'famille', 'F');
    tests_arbre_famille($luiC, $elleC, []);
    $htmlCouple = (string) tests_envoi_requete('GET', '/arbre?racine=' . $luiC, $admin)['corps'];
    $dessinCouple = tests_arbre_morceau($htmlCouple, 'arbre-chene__dessin', 'arbre-explo__indice');
    verifier('arbre 1.40 : couple sans enfants, deux cartes et aucun trait de génération',
        substr_count($dessinCouple, '<li class="arbre-chene__noeud') === 2
        && !str_contains($dessinCouple, '<svg class="arbre-chene__traits"')
        && str_contains($dessinCouple, 'Aucun enfant connu'),
        'cartes : ' . substr_count($dessinCouple, '<li class="arbre-chene__noeud'));

    // Personne seule : une carte, aucun trait, aucune génération inventée.
    $seul = tests_arbre_personne('Solitaire', 'SEUL', 'famille');
    $htmlSeul = (string) tests_envoi_requete('GET', '/arbre?racine=' . $seul, $admin)['corps'];
    $dessinSeul = tests_arbre_morceau($htmlSeul, 'arbre-chene__dessin', 'arbre-explo__indice');
    verifier('arbre 1.40 : personne seule, une carte et aucun trait',
        substr_count($dessinSeul, '<li class="arbre-chene__noeud') === 1
        && !str_contains($dessinSeul, '<svg class="arbre-chene__traits"')
        && str_contains($dessinSeul, 'Aucun parent connu')
        && str_contains($dessinSeul, 'Aucun enfant connu'),
        'cartes : ' . substr_count($dessinSeul, '<li class="arbre-chene__noeud'));

    // Fratrie nombreuse : le tronc suit la même règle que les générations —
    // huit cartes, le reste annoncé, puis toutes les cartes avec « plus ».
    $centre = tests_arbre_personne('Centre', 'NOMBREUX', 'famille');
    $parrain = tests_arbre_personne('Pere', 'NOMBREUX', 'famille');
    $fratrie = [$centre];
    for ($i = 1; $i <= 9; $i++) {
        $fratrie[] = tests_arbre_personne('Frere' . $i, 'NOMBREUX', 'famille');
    }
    tests_arbre_famille($parrain, null, $fratrie);
    $enfantCentre = tests_arbre_personne('Enfant', 'NOMBREUX', 'famille');
    tests_arbre_famille($centre, null, [$enfantCentre]);
    $htmlNombreux = (string) tests_envoi_requete('GET', '/arbre?racine=' . $centre, $admin)['corps'];
    $troncNombreux = tests_arbre_morceau($htmlNombreux, 'arbre-chene__partie--tronc', 'arbre-chene__partie--racines');
    verifier('arbre 1.40 : fratrie nombreuse, le tronc reste à huit cartes',
        substr_count($troncNombreux, '<li class="arbre-chene__noeud') === 8,
        'cartes : ' . substr_count($troncNombreux, '<li class="arbre-chene__noeud'));
    verifier_contient('arbre 1.40 : le reste de la fratrie est annoncé',
        'Voir les 2 autres personnes de la famille', $htmlNombreux);
    $htmlTout = (string) tests_envoi_requete('GET', '/arbre?racine=' . $centre . '&plus=1', $admin)['corps'];
    $troncTout = tests_arbre_morceau($htmlTout, 'arbre-chene__partie--tronc', 'arbre-chene__partie--racines');
    verifier('arbre 1.40 : « voir plus » montre toute la fratrie dans une rangée logique',
        substr_count($troncTout, '<li class="arbre-chene__noeud') === 10
        && str_contains($troncTout, 'arbre-chene__rangee--10')
        && str_contains($htmlTout, 'arbre-chene__dessin--elargi'),
        'cartes : ' . substr_count($troncTout, '<li class="arbre-chene__noeud'));
}
