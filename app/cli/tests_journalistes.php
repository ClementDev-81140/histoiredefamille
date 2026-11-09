<?php
/**
 * Essais — 1.37.0 : droits réglables des journalistes.
 *
 *   1. réglages : défauts historiques, modification par un administrateur
 *      seul, effet immédiat, avertissement « publie directement » ;
 *   2. périmètre : la règle de branche (rattachement explicite, puis
 *      branche de l'auteur, personnes sans auteur non rattachées) ;
 *   3. publication directe « sans validation » (visible tout de suite,
 *      journalisée, dépubliable par un administrateur, jamais supprimée) ;
 *   4. « avec validation » : rien ne change (attente, invisible) ;
 *   5. interdits : pages d'administration 403, aucune suppression,
 *      hors périmètre 404 y compris par adresse directe.
 *
 * Tout se passe dans une transaction annulée : la base est rendue intacte.
 */

declare(strict_types=1);

/** Branche d'essai 1.37. */
function tests37_branche(string $nom): int
{
    return db_insert('branches', [
        'name'       => $nom,
        'code'       => 'T37' . bin2hex(random_bytes(3)),
        'is_active'  => 1,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
}

/** Compte d'essai 1.37 (admin, lecteur de branche, journaliste personne). */
function tests37_compte(string $type, ?int $branche = null): int
{
    return db_insert('accounts', [
        'username'      => 'essai37-' . $type . '-' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai37 ' . $type,
        'password_hash' => password_make('MotDePasseEssai123!'),
        'role'          => $type === 'admin' ? 'admin' : 'famille',
        'account_type'  => $type === 'admin' ? 'admin' : ($type === 'lecteur' ? 'branche' : 'personne'),
        'first_name'    => $type === 'journaliste' ? 'Jules' : '',
        'last_name'     => $type === 'journaliste' ? 'Plume' : '',
        'branch_id'     => $branche,
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
}

/** Règle un journaliste directement en base (comme le ferait la fiche admin). */
function tests37_regler(int $compte, bool $validation, bool $toutes, array $branches = []): void
{
    db_update('accounts', $compte, [
        'journaliste_validation'      => $validation ? 1 : 0,
        'journaliste_toutes_branches' => $toutes ? 1 : 0,
    ]);
    db_exec('DELETE FROM journaliste_branches WHERE account_id = ?', [$compte]);
    foreach ($branches as $b) {
        db_insert('journaliste_branches', ['account_id' => $compte, 'branch_id' => $b]);
    }
}

/** Requête avec session et jeton CSRF valides. */
function tests37_requete(string $methode, string $url, ?int $compte, array $post = []): array
{
    if ($compte !== null) {
        $_SESSION = ['account_id' => $compte, '_csrf' => csrf_token()];
        if ($methode === 'POST') {
            $post = array_merge(['_csrf' => csrf_token()], $post);
        }
    }
    return tests_envoi_requete($methode, $url, $compte, $post);
}

/** Article d'essai publié. */
function tests37_article(string $titre, int $auteur, string $visibilite, array $branches = []): int
{
    $id = db_insert('articles', [
        'title' => $titre, 'slug' => 'essai37-' . bin2hex(random_bytes(4)),
        'summary' => '', 'body' => 'Texte.', 'author_id' => $auteur,
        'published_at' => time(),
        'visibility' => $visibilite, 'moderation_state' => 'publie',
        'created_at' => time(), 'updated_at' => time(),
    ]);
    if ($branches !== []) {
        set_content_branches('article', $id, $branches);
    }
    return $id;
}

/** Personne d'essai. */
function tests37_personne(string $prenom, array $branches = []): int
{
    $id = db_insert('persons', [
        'first_name' => $prenom, 'last_name' => 'Essai37', 'sex' => 'F',
        'visibility' => 'famille', 'created_at' => time(), 'updated_at' => time(),
    ]);
    if ($branches !== []) {
        set_content_branches('person', $id, $branches);
    }
    return $id;
}

function tests_journalistes(): void
{
    echo "1.37.0 — droits réglables des journalistes\n";

    try {
        db_transaction(static function (): void {
            tests37_reglages();
            tests37_perimetre();
            tests37_publication();
            tests37_personnes_albums();
            tests37_journal_admin();
            tests371_validation_stricte();
            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }
}

function tests37_reglages(): void
{
    $admin   = tests37_compte('admin');
    $branche = tests37_branche('Branche réglages');
    $j       = tests37_compte('journaliste');

    $defaut = journaliste_reglages((array) db_one('SELECT * FROM accounts WHERE id = ?', [$j]));
    verifier(
        'réglages : un journaliste existant garde le comportement actuel (avec validation, toutes les branches)',
        $defaut['validation'] === true && $defaut['toutes'] === true && $defaut['branches'] === []
    );

    // L'administrateur change les deux réglages dans la fiche du compte.
    $avant = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$j]);
    $reponse = tests37_requete('POST', '/admin/comptes/' . $j, $admin, [
        'profil' => 'journaliste', 'nom' => 'Plume', 'prenom' => 'Jules',
        'identifiant' => (string) $avant['username'], 'branche' => (string) $branche, 'email' => '',
        'journaliste_validation' => '0', 'journaliste_toutes_branches' => '0',
        'journaliste_branches' => [(string) $branche],
    ]);
    $apres = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$j]);
    $regles = journaliste_reglages($apres);
    verifier(
        'réglages : l’administrateur passe le journaliste « sans validation », limité à une branche',
        $reponse['code'] === 303 && $regles['validation'] === false && $regles['toutes'] === false
            && $regles['branches'] === [$branche],
        $reponse['code'] . ' ' . $reponse['erreur']
    );

    // Effet immédiat : la fiche relue dans la même session voit le réglage.
    $page = tests37_requete('GET', '/admin/comptes/' . $j, $admin);
    verifier(
        'réglages : la fiche du compte affiche l’avertissement « publie directement »',
        $page['code'] === 200 && str_contains($page['corps'], 'publie directement'),
        (string) $page['code']
    );

    // Un journaliste ne peut ni voir ni changer les réglages (page admin).
    $interdit = tests37_requete('GET', '/admin/comptes/' . $j, $j);
    verifier(
        'réglages : un journaliste n’ouvre pas la fiche des comptes (403)',
        $interdit['code'] === 403,
        (string) $interdit['code']
    );
    $tentative = tests37_requete('POST', '/admin/comptes/' . $j, $j, [
        'profil' => 'journaliste', 'nom' => 'Plume', 'prenom' => 'Jules',
        'identifiant' => (string) $avant['username'],
        'journaliste_validation' => '1', 'journaliste_toutes_branches' => '1',
    ]);
    $inchange = journaliste_reglages((array) db_one('SELECT * FROM accounts WHERE id = ?', [$j]));
    verifier(
        'réglages : un journaliste ne peut pas changer son propre réglage',
        $tentative['code'] === 403 && $inchange['validation'] === false,
        (string) $tentative['code']
    );

    // Périmètre restreint sans branche cochée : refusé, rien ne change.
    $refus = tests37_requete('POST', '/admin/comptes/' . $j, $admin, [
        'profil' => 'journaliste', 'nom' => 'Plume', 'prenom' => 'Jules',
        'identifiant' => (string) $avant['username'], 'branche' => (string) $branche, 'email' => '',
        'journaliste_validation' => '0', 'journaliste_toutes_branches' => '0',
        'journaliste_branches' => [],
    ]);
    $toujours = journaliste_reglages((array) db_one('SELECT * FROM accounts WHERE id = ?', [$j]));
    verifier(
        'réglages : un périmètre restreint sans branche est refusé',
        $refus['code'] === 303 && $toujours['branches'] === [$branche],
        (string) $refus['code']
    );
}

function tests37_perimetre(): void
{
    $brancheA = tests37_branche('Branche A37');
    $brancheB = tests37_branche('Branche B37');
    $lecteurA = tests37_compte('lecteur', $brancheA);
    $lecteurB = tests37_compte('lecteur', $brancheB);
    $jA       = tests37_compte('journaliste');
    $jToutes  = tests37_compte('journaliste');
    tests37_regler($jA, false, false, [$brancheA]);
    tests37_regler($jToutes, false, true);
    $compteA = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$jA]);
    $compteT = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$jToutes]);

    // Visibilité « famille » : la lecture est ouverte aux comptes connectés,
    // seul le rattachement explicite décide du droit de corriger.
    $artA = tests37_article('Article de A', $lecteurA, 'famille', [$brancheA]);
    $artB = tests37_article('Article de B', $lecteurB, 'famille', [$brancheB]);
    $ligneA = (array) db_one('SELECT * FROM articles WHERE id = ?', [$artA]);
    $ligneB = (array) db_one('SELECT * FROM articles WHERE id = ?', [$artB]);

    verifier(
        'périmètre : rattachement explicite — A oui, B non, pour le journaliste limité à A',
        journaliste_peut_modifier('article', $ligneA, $compteA) === true
            && journaliste_peut_modifier('article', $ligneB, $compteA) === false
    );
    verifier(
        'périmètre : « toutes les branches » peut corriger les deux',
        journaliste_peut_modifier('article', $ligneA, $compteT) === true
            && journaliste_peut_modifier('article', $ligneB, $compteT) === true
    );

    // Un contenu privé, invisible pour le compte, ne fuit pas en édition :
    // même « toutes les branches » ne peut pas le corriger par adresse directe.
    $artPrive = tests37_article('Article privé de B', $lecteurB, 'prive', [$brancheB]);
    $lignePrive = (array) db_one('SELECT * FROM articles WHERE id = ?', [$artPrive]);
    verifier(
        'périmètre : un contenu invisible pour le compte ne peut pas être corrigé (aucune fuite)',
        journaliste_peut_modifier('article', $lignePrive, $compteT) === false
    );

    // Sans rattachement explicite : la branche de l'auteur décide.
    $artAuteur = tests37_article('Article par auteur de A', $lecteurA, 'famille');
    $ligneAuteur = (array) db_one('SELECT * FROM articles WHERE id = ?', [$artAuteur]);
    verifier(
        'périmètre : sans rattachement, la branche de l’auteur décide',
        contenu_releve_branches('article', $ligneAuteur) === [$brancheA]
            && journaliste_peut_modifier('article', $ligneAuteur, $compteA) === true
    );

    // Personnes : seul le rattachement compte ; sans rattachement, personne
    // ne relève d'une branche en particulier.
    $persA     = tests37_personne('Anna', [$brancheA]);
    $persLibre = tests37_personne('Libre');
    $lignePA   = (array) db_one('SELECT * FROM persons WHERE id = ?', [$persA]);
    $lignePL   = (array) db_one('SELECT * FROM persons WHERE id = ?', [$persLibre]);
    verifier(
        'périmètre : personne rattachée à A corrigeable par A ; personne sans rattachement réservée à « toutes les branches »',
        journaliste_peut_modifier('person', $lignePA, $compteA) === true
            && journaliste_peut_modifier('person', $lignePL, $compteA) === false
            && journaliste_peut_modifier('person', $lignePL, $compteT) === true
    );

    // Adresse directe : le formulaire d'un contenu hors périmètre répond 404.
    $direct = tests37_requete('GET', '/propositions/article/' . $artB, $jA);
    verifier(
        'périmètre : le formulaire d’un article de B répond 404 au journaliste de A, par adresse directe',
        $direct['code'] === 404,
        (string) $direct['code'] . ' ' . $direct['erreur']
    );
    $directOk = tests37_requete('GET', '/propositions/article/' . $artA, $jA);
    verifier(
        'périmètre : le formulaire d’un article de A s’ouvre pour le journaliste de A',
        $directOk['code'] === 200,
        (string) $directOk['code'] . ' ' . $directOk['erreur']
    );
}

function tests37_publication(): void
{
    $brancheB = tests37_branche('Branche B37pub');
    $jDirect  = tests37_compte('journaliste');
    tests37_regler($jDirect, false, true);
    $jValide  = tests37_compte('journaliste');
    tests37_regler($jValide, true, true);
    $jA       = tests37_compte('journaliste');
    $brancheA = tests37_branche('Branche A37pub');
    tests37_regler($jA, false, false, [$brancheA]);

    // Sans validation : publié tout de suite, avec la visibilité choisie.
    $avant = (int) (db_one('SELECT COUNT(*) AS n FROM articles')['n'] ?? 0);
    $rep = tests37_requete('POST', '/propositions/nouvelle/article', $jDirect, [
        'title' => 'Article direct 37', 'summary' => 'Résumé', 'body' => 'Corps.',
        'suggested_visibility' => 'public', 'action' => 'proposer',
    ]);
    $ligne = db_one('SELECT * FROM articles WHERE title = ?', ['Article direct 37']);
    verifier(
        'sans validation : l’article est publié directement, en public',
        $rep['code'] === 303 && $ligne !== null
            && (string) $ligne['moderation_state'] === 'publie'
            && (string) $ligne['visibility'] === 'public',
        $rep['code'] . ' ' . $rep['erreur']
    );
    $vu = tests37_requete('GET', '/articles/' . $ligne['slug'], null);
    verifier(
        'sans validation : l’article public est lisible par un visiteur tout de suite',
        $vu['code'] === 200 && str_contains($vu['corps'], 'Article direct 37'),
        (string) $vu['code']
    );
    $journal = db_one(
        "SELECT * FROM activity_log WHERE account_id = ? AND action = 'journaliste_publication'",
        [$jDirect]
    );
    verifier(
        'sans validation : la publication est notée dans le journal d’activité',
        $journal !== null && str_contains((string) $journal['details'], 'article:' . (int) $ligne['id'])
    );

    // Avec validation : comportement historique inchangé.
    tests37_requete('POST', '/propositions/nouvelle/article', $jValide, [
        'title' => 'Article à valider 37', 'summary' => '', 'body' => 'Corps.',
        'suggested_visibility' => 'public', 'action' => 'proposer',
    ]);
    $attente = db_one('SELECT * FROM articles WHERE title = ?', ['Article à valider 37']);
    verifier(
        'avec validation : l’article reste en attente et privé, comme avant',
        $attente !== null && (string) $attente['moderation_state'] === 'attente'
            && (string) $attente['visibility'] === 'prive',
        $attente === null ? 'absent' : (string) $attente['moderation_state']
    );
    $cache = tests37_requete('GET', '/articles/' . $attente['slug'], null);
    verifier(
        'avec validation : l’article en attente est invisible pour un visiteur',
        $cache['code'] === 404,
        (string) $cache['code']
    );

    // Hors périmètre à la publication : branches de B refusées pour A.
    tests37_requete('POST', '/propositions/nouvelle/article', $jA, [
        'title' => 'Article hors périmètre 37', 'summary' => '', 'body' => 'Corps.',
        'suggested_visibility' => 'branches', 'branches' => [(string) $brancheB],
        'action' => 'proposer',
    ]);
    verifier(
        'périmètre : publier pour une branche hors périmètre est refusé (aucun article créé)',
        db_one('SELECT id FROM articles WHERE title = ?', ['Article hors périmètre 37']) === null
    );

    // Correction directe d'un article d'un autre : historique + journal.
    $cible = tests37_article('Article à corriger 37', $jValide, 'famille');
    tests37_requete('POST', '/propositions/article/' . $cible, $jDirect, [
        'title' => 'Article corrigé 37', 'summary' => '', 'body' => 'Nouveau corps.',
        'suggested_visibility' => 'famille', 'action' => 'proposer',
    ]);
    $apres = (array) db_one('SELECT * FROM articles WHERE id = ?', [$cible]);
    $histo = db_one(
        "SELECT * FROM contenu_historique WHERE kind = 'article' AND content_id = ?",
        [$cible]
    );
    verifier(
        'sans validation : corriger l’article d’un autre garde l’ancienne version dans l’historique',
        (string) $apres['title'] === 'Article corrigé 37' && $histo !== null
            && str_contains((string) $histo['avant'], 'Article à corriger 37'),
        (string) ($apres['title'] ?? '') . ' / histo ' . ($histo === null ? 'absent' : 'présent')
    );
    verifier(
        'sans validation : l’auteur d’origine reste le déposant du contenu corrigé',
        (int) $apres['submitted_by'] === $jValide || (int) ($apres['submitted_by'] ?? 0) === 0
    );

    // Événement : publication directe aussi.
    tests37_requete('POST', '/calendrier/ajouter', $jDirect, [
        'type' => '', 'title' => 'Fête directe 37', 'start_date' => '2027-05-01',
        'start_time' => '', 'end_date' => '', 'end_time' => '', 'place' => 'Village',
        'description' => '', 'visibility' => 'famille', 'annual' => '0',
    ]);
    $evt = db_one('SELECT * FROM events WHERE title = ?', ['Fête directe 37']);
    verifier(
        'sans validation : l’événement proposé est publié directement',
        $evt !== null && (string) $evt['status'] === 'publie',
        $evt === null ? 'absent' : (string) $evt['status']
    );
    tests37_requete('POST', '/calendrier/ajouter', $jValide, [
        'type' => '', 'title' => 'Fête à valider 37', 'start_date' => '2027-06-01',
        'start_time' => '', 'end_date' => '', 'end_time' => '', 'place' => '',
        'description' => '', 'visibility' => 'famille', 'annual' => '0',
    ]);
    $evt2 = db_one('SELECT * FROM events WHERE title = ?', ['Fête à valider 37']);
    verifier(
        'avec validation : l’événement proposé reste en attente, comme avant',
        $evt2 !== null && (string) $evt2['status'] === 'en_attente',
        $evt2 === null ? 'absent' : (string) $evt2['status']
    );

    // Correction d'événement : directe si « sans validation » (historique
    // gardé), repassée en attente si « avec validation ».
    $evtCible = (array) db_one('SELECT * FROM events WHERE title = ?', ['Fête directe 37']);
    $repModif = tests37_requete('POST', '/calendrier/' . (int) $evtCible['id'] . '/modifier', $jDirect, [
        'type' => '', 'title' => 'Fête corrigée 37', 'start_date' => '2027-05-02',
        'place' => 'Village', 'description' => '', 'visibility' => 'famille',
    ]);
    $evtApres = (array) db_one('SELECT * FROM events WHERE id = ?', [(int) $evtCible['id']]);
    $evtHisto = db_one("SELECT * FROM contenu_historique WHERE kind = 'event' AND content_id = ?", [(int) $evtCible['id']]);
    verifier(
        'événements : correction directe par un journaliste « sans validation », ancien état gardé',
        $repModif['code'] === 303 && (string) $evtApres['title'] === 'Fête corrigée 37'
            && (string) $evtApres['status'] === 'publie' && $evtHisto !== null,
        $repModif['code'] . ' ' . $repModif['erreur']
    );

    // 1.37.1 : le journaliste « avec validation » ne corrige plus rien
    // directement — refus (403), l'événement publié ne bouge pas.
    $refusEvtVal = tests37_requete('POST', '/calendrier/' . (int) $evtCible['id'] . '/modifier', $jValide, [
        'type' => '', 'title' => 'Fête recorrigée 37', 'start_date' => '2027-05-03',
        'place' => '', 'description' => '', 'visibility' => 'famille',
    ]);
    $evtValApres = (array) db_one('SELECT * FROM events WHERE id = ?', [(int) $evtCible['id']]);
    verifier(
        'événements : correction par un journaliste « avec validation » — refusée (403), rien ne change',
        $refusEvtVal['code'] === 403 && (string) $evtValApres['status'] === 'publie'
            && (string) $evtValApres['title'] === 'Fête corrigée 37',
        $refusEvtVal['code'] . ' / ' . (string) $evtValApres['status']
    );

    // Hors périmètre : 404 par adresse directe.
    $evtB = db_insert('events', [
        'title' => 'Fête de B 37', 'start_date' => '2027-07-01', 'place' => '',
        'description' => '', 'visibility' => 'famille', 'annual' => 0,
        'created_at' => time(), 'updated_at' => time(),
    ]);
    set_content_branches('event', $evtB, [$brancheB]);
    $interditEvt = tests37_requete('GET', '/calendrier/' . $evtB . '/modifier', $jA);
    verifier(
        'événements : le formulaire d’un événement hors périmètre répond 404',
        $interditEvt['code'] === 404,
        (string) $interditEvt['code']
    );
}

function tests37_personnes_albums(): void
{
    $brancheA = tests37_branche('Branche A37pa');
    $brancheB = tests37_branche('Branche B37pa');
    $jA = tests37_compte('journaliste');
    tests37_regler($jA, false, false, [$brancheA]);
    $lecteur = tests37_compte('lecteur', $brancheA);

    $persA = tests37_personne('Anna', [$brancheA]);
    $persB = tests37_personne('Béa', [$brancheB]);

    // Correction d'une fiche de son périmètre : directe, historisée.
    $rep = tests37_requete('POST', '/arbre/' . $persA . '/modifier', $jA, [
        'first_name' => 'Anna', 'last_name' => 'Corrigée', 'sex' => 'F',
        'birth_date' => '', 'birth_place' => '', 'death_date' => '', 'death_place' => '',
        'notes' => '', 'visibility' => 'famille',
    ]);
    $apres = (array) db_one('SELECT * FROM persons WHERE id = ?', [$persA]);
    $histo = db_one("SELECT * FROM contenu_historique WHERE kind = 'person' AND content_id = ?", [$persA]);
    verifier(
        'personnes : le journaliste corrige une fiche de sa branche, ancienne version gardée',
        $rep['code'] === 303 && (string) $apres['last_name'] === 'Corrigée' && $histo !== null,
        $rep['code'] . ' ' . $rep['erreur']
    );

    // Hors périmètre : 404 par adresse directe.
    $interdit = tests37_requete('POST', '/arbre/' . $persB . '/modifier', $jA, [
        'first_name' => 'Béa', 'last_name' => 'Touchée', 'sex' => 'F', 'visibility' => 'famille',
    ]);
    $intacte = (array) db_one('SELECT * FROM persons WHERE id = ?', [$persB]);
    verifier(
        'personnes : une fiche hors périmètre répond 404 et ne change pas',
        $interdit['code'] === 404 && (string) $intacte['last_name'] === 'Essai37',
        (string) $interdit['code']
    );

    // Un lecteur ne peut rien corriger.
    $lecteurRep = tests37_requete('GET', '/arbre/' . $persA . '/modifier', $lecteur);
    verifier(
        'personnes : un lecteur reçoit 403 sur le formulaire de correction',
        $lecteurRep['code'] === 403,
        (string) $lecteurRep['code']
    );

    // Nouvelle fiche rattachée hors périmètre : refusée.
    tests37_requete('POST', '/arbre/nouvelle-personne', $jA, [
        'first_name' => 'Hors', 'last_name' => 'Limite', 'sex' => 'M',
        'visibility' => 'branches', 'branches' => [(string) $brancheB],
    ]);
    verifier(
        'personnes : créer une fiche pour une branche hors périmètre est refusé',
        db_one('SELECT id FROM persons WHERE last_name = ?', ['Limite']) === null
    );

    // Album : création dans son périmètre, refusée hors périmètre.
    tests37_requete('POST', '/albums/nouveau', $jA, [
        'title' => 'Album de A 37', 'description' => '', 'visibility' => 'branches',
        'branches' => [(string) $brancheA],
    ]);
    $album = db_one('SELECT * FROM albums WHERE title = ?', ['Album de A 37']);
    verifier(
        'albums : le journaliste crée un album de sa branche',
        $album !== null && (int) $album['created_by'] === $jA,
        $album === null ? 'absent' : 'créé par ' . (int) $album['created_by']
    );
    tests37_requete('POST', '/albums/nouveau', $jA, [
        'title' => 'Album de B 37', 'description' => '', 'visibility' => 'branches',
        'branches' => [(string) $brancheB],
    ]);
    verifier(
        'albums : créer un album pour une branche hors périmètre est refusé',
        db_one('SELECT id FROM albums WHERE title = ?', ['Album de B 37']) === null
    );

    // Aucune suppression par un journaliste, même par adresse directe.
    $suppr = tests37_requete('POST', '/admin/albums/' . (int) ($album['id'] ?? 0) . '/supprimer', $jA);
    verifier(
        'interdits : un journaliste ne supprime rien (page d’administration fermée, 403)',
        $suppr['code'] === 403 && db_one('SELECT id FROM albums WHERE id = ?', [(int) ($album['id'] ?? 0)]) !== null,
        (string) $suppr['code']
    );
}

/**
 * 1.37.1 : un journaliste « avec validation » ne publie ni ne modifie
 * jamais directement — albums en attente, modifications refusées (403),
 * fiches personnes fermées, défaut « privé ».
 */
function tests371_validation_stricte(): void
{
    $admin = tests37_compte('admin');
    $brancheA = tests37_branche('Branche A371');
    $brancheB = tests37_branche('Branche B371');
    $lecteur = tests37_compte('lecteur', $brancheA);
    $jVal = tests37_compte('journaliste');
    tests37_regler($jVal, true, false, [$brancheA]);
    $jDirect = tests37_compte('journaliste');
    tests37_regler($jDirect, false, false, [$brancheA]);
    $jAutre = tests37_compte('journaliste');
    tests37_regler($jAutre, false, true);

    // --- Album créé « avec validation » : en attente, invisible. ---
    tests37_requete('POST', '/albums/nouveau', $jVal, [
        'title' => 'Album proposé 371', 'description' => '', 'visibility' => 'famille',
    ]);
    $album = db_one('SELECT * FROM albums WHERE title = ?', ['Album proposé 371']);
    verifier(
        '1.37.1 albums : l’album d’un journaliste « avec validation » naît privé et en attente',
        $album !== null && (string) $album['moderation_state'] === 'attente'
            && (string) $album['visibility'] === 'prive'
            && (string) $album['suggested_visibility'] === 'famille',
        $album === null ? 'absent' : (string) $album['moderation_state'] . '/' . (string) $album['visibility']
    );
    $vuLecteur = tests37_requete('GET', '/albums/' . $album['slug'], $lecteur);
    $vuAutre = tests37_requete('GET', '/albums/' . $album['slug'], $jAutre);
    $liste = tests37_requete('GET', '/albums', $lecteur);
    verifier(
        '1.37.1 albums : invisible d’un lecteur et d’un autre journaliste, absent des listes',
        $vuLecteur['code'] === 404 && $vuAutre['code'] === 404
            && !str_contains($liste['corps'], 'Album proposé 371'),
        $vuLecteur['code'] . '/' . $vuAutre['code']
    );
    $vuAuteur = tests37_requete('GET', '/albums/' . $album['slug'], $jVal);
    verifier(
        '1.37.1 albums : son auteur peut le relire par son adresse directe',
        $vuAuteur['code'] === 200 && str_contains($vuAuteur['corps'], 'en attente de validation'),
        (string) $vuAuteur['code']
    );

    // Validation par l'administrateur : la visibilité suggérée s'applique.
    tests37_requete('POST', '/admin/albums/' . (int) $album['id'] . '/valider', $admin);
    $valide = (array) db_one('SELECT * FROM albums WHERE id = ?', [(int) $album['id']]);
    $vuApres = tests37_requete('GET', '/albums/' . $album['slug'], $lecteur);
    verifier(
        '1.37.1 albums : après validation, visible avec la visibilité demandée',
        (string) $valide['moderation_state'] === 'publie' && (string) $valide['visibility'] === 'famille'
            && $vuApres['code'] === 200,
        (string) $valide['moderation_state'] . '/' . (string) $valide['visibility'] . '/' . $vuApres['code']
    );

    // --- Album : modification refusée « avec validation », même le sien. ---
    $avant = (array) db_one('SELECT * FROM albums WHERE id = ?', [(int) $album['id']]);
    $refus = tests37_requete('POST', '/albums/' . (int) $album['id'] . '/modifier', $jVal, [
        'title' => 'Album retouché 371', 'description' => '', 'visibility' => 'public',
    ]);
    $apres = (array) db_one('SELECT * FROM albums WHERE id = ?', [(int) $album['id']]);
    verifier(
        '1.37.1 albums : la modification directe est refusée (403) « avec validation », rien ne change',
        $refus['code'] === 403 && (string) $apres['title'] === (string) $avant['title'],
        (string) $refus['code']
    );
    $formRefus = tests37_requete('GET', '/albums/' . (int) $album['id'] . '/modifier', $jVal);
    verifier(
        '1.37.1 albums : le formulaire de modification répond aussi 403',
        $formRefus['code'] === 403,
        (string) $formRefus['code']
    );

    // « Sans validation » inchangé : création directe.
    tests37_requete('POST', '/albums/nouveau', $jDirect, [
        'title' => 'Album direct 371', 'description' => '', 'visibility' => 'famille',
    ]);
    $direct = db_one('SELECT * FROM albums WHERE title = ?', ['Album direct 371']);
    verifier(
        '1.37.1 albums : « sans validation » publie toujours directement',
        $direct !== null && (string) $direct['moderation_state'] === 'publie'
            && (string) $direct['visibility'] === 'famille'
    );

    // --- Personnes : création et correction refusées « avec validation ». ---
    $crea = tests37_requete('POST', '/arbre/nouvelle-personne', $jVal, [
        'first_name' => 'Refusée', 'last_name' => 'Personne371', 'sex' => 'F', 'visibility' => 'prive',
    ]);
    verifier(
        '1.37.1 personnes : la création est refusée (403) « avec validation », aucune fiche créée',
        $crea['code'] === 403 && db_one('SELECT id FROM persons WHERE last_name = ?', ['Personne371']) === null,
        (string) $crea['code']
    );
    $pers = tests37_personne('Vera', [$brancheA]);
    $corr = tests37_requete('POST', '/arbre/' . $pers . '/modifier', $jVal, [
        'first_name' => 'Vera', 'last_name' => 'Touchée371', 'sex' => 'F', 'visibility' => 'famille',
    ]);
    $intacte = (array) db_one('SELECT * FROM persons WHERE id = ?', [$pers]);
    verifier(
        '1.37.1 personnes : la correction est refusée (403), la fiche ne change pas',
        $corr['code'] === 403 && (string) $intacte['last_name'] === 'Essai37',
        (string) $corr['code']
    );
    // « Sans validation » inchangé.
    $okCorr = tests37_requete('POST', '/arbre/' . $pers . '/modifier', $jDirect, [
        'first_name' => 'Vera', 'last_name' => 'Corrigée371', 'sex' => 'F', 'visibility' => 'famille',
    ]);
    $corrigee = (array) db_one('SELECT * FROM persons WHERE id = ?', [$pers]);
    verifier(
        '1.37.1 personnes : « sans validation » corrige toujours directement',
        $okCorr['code'] === 303 && (string) $corrigee['last_name'] === 'Corrigée371',
        (string) $okCorr['code']
    );

    // --- Événements : modification refusée « avec validation ». ---
    $evt = db_insert('events', [
        'title' => 'Fête figée 371', 'start_date' => '2027-08-01', 'place' => '',
        'description' => '', 'visibility' => 'famille', 'annual' => 0,
        'created_by' => $jVal, 'created_at' => time(), 'updated_at' => time(),
    ]);
    $refusEvt = tests37_requete('POST', '/calendrier/' . $evt . '/modifier', $jVal, [
        'type' => '', 'title' => 'Fête retouchée 371', 'start_date' => '2027-08-02',
        'place' => '', 'description' => '', 'visibility' => 'famille',
    ]);
    $evtApres = (array) db_one('SELECT * FROM events WHERE id = ?', [$evt]);
    verifier(
        '1.37.1 événements : la modification directe est refusée (403) « avec validation », rien ne change',
        $refusEvt['code'] === 403 && (string) $evtApres['title'] === 'Fête figée 371'
            && (string) $evtApres['status'] === 'publie',
        (string) $refusEvt['code']
    );
    // La création reste une proposition en attente (déjà le cas depuis 1.34).
    tests37_requete('POST', '/calendrier/ajouter', $jVal, [
        'type' => '', 'title' => 'Fête proposée 371', 'start_date' => '2027-09-01',
        'start_time' => '', 'end_date' => '', 'end_time' => '', 'place' => '',
        'description' => '', 'visibility' => 'famille', 'annual' => '0',
    ]);
    $evtProp = db_one('SELECT * FROM events WHERE title = ?', ['Fête proposée 371']);
    verifier(
        '1.37.1 événements : la création reste en attente « avec validation »',
        $evtProp !== null && (string) $evtProp['status'] === 'en_attente'
    );

    // --- Défaut « privé » présélectionné dans les formulaires de création. ---
    $formAlbum = tests37_requete('GET', '/albums/nouveau', $jDirect);
    $formPers = tests37_requete('GET', '/arbre/nouvelle-personne', $jDirect);
    $formEvt = tests37_requete('GET', '/calendrier/ajouter', $jDirect);
    $priveCoche = static fn (string $corps): bool => preg_match('/name="visibility" value="prive"[^>]*checked/', $corps) === 1;
    verifier(
        '1.37.1 défauts : « Privé » est présélectionné (album, personne, événement)',
        $priveCoche($formAlbum['corps']) && $priveCoche($formPers['corps']) && $priveCoche($formEvt['corps']),
        'album ' . $formAlbum['code'] . ', personne ' . $formPers['code'] . ', événement ' . $formEvt['code']
    );

    // --- Rattrapage 1.37.0 : un album publié par un journaliste « avec
    // validation » repasse en attente et en privé (visibilité gardée). ---
    $ancien = db_insert('albums', [
        'title' => 'Album 1.37.0 à rattraper', 'slug' => 'album-rattrapage-371-' . bin2hex(random_bytes(3)),
        'description' => '', 'visibility' => 'famille', 'created_by' => $jVal,
        'moderation_state' => 'publie', 'created_at' => time(), 'updated_at' => time(),
    ]);
    db_exec(
        "UPDATE albums SET suggested_visibility = visibility, visibility = 'prive', moderation_state = 'attente'
         WHERE moderation_state = 'publie'
           AND created_by IN (SELECT id FROM accounts WHERE role = 'famille'
             AND COALESCE(account_type, 'branche') = 'personne' AND journaliste_validation = 1)"
    );
    $rattrape = (array) db_one('SELECT * FROM albums WHERE id = ?', [$ancien]);
    $directIntact = (array) db_one('SELECT * FROM albums WHERE title = ?', ['Album direct 371']);
    verifier(
        '1.37.1 rattrapage : l’album 1.37.0 d’un journaliste « avec validation » repasse en attente/privé, celui d’un « sans validation » ne bouge pas',
        (string) $rattrape['moderation_state'] === 'attente' && (string) $rattrape['visibility'] === 'prive'
            && (string) $rattrape['suggested_visibility'] === 'famille'
            && (string) $directIntact['moderation_state'] === 'publie',
        (string) $rattrape['moderation_state'] . '/' . (string) $rattrape['visibility']
    );
}

function tests37_journal_admin(): void
{
    $admin    = tests37_compte('admin');
    $jDirect  = tests37_compte('journaliste');
    tests37_regler($jDirect, false, true);
    $lecteur  = tests37_compte('lecteur', tests37_branche('Branche journal'));

    tests37_requete('POST', '/propositions/nouvelle/article', $jDirect, [
        'title' => 'Article journalisé 37', 'summary' => '', 'body' => 'Corps.',
        'suggested_visibility' => 'public', 'action' => 'proposer',
    ]);
    $ligne = (array) db_one('SELECT * FROM articles WHERE title = ?', ['Article journalisé 37']);

    $page = tests37_requete('GET', '/admin/journalistes', $admin);
    verifier(
        'journal : la page du journal est visible par un administrateur et cite l’article',
        $page['code'] === 200 && str_contains($page['corps'], 'Article journalisé 37'),
        (string) $page['code'] . ' ' . $page['erreur']
    );
    $ferme = tests37_requete('GET', '/admin/journalistes', $jDirect);
    verifier(
        'journal : la page du journal est fermée à un journaliste (403)',
        $ferme['code'] === 403,
        (string) $ferme['code']
    );

    // Dépublier : privé, pas supprimé.
    tests37_requete('POST', '/admin/journalistes/depublier/article/' . (int) $ligne['id'], $admin);
    $apres = (array) db_one('SELECT * FROM articles WHERE id = ?', [(int) $ligne['id']]);
    verifier(
        'journal : l’administrateur dépublie en un clic — privé, jamais supprimé',
        (string) $apres['visibility'] === 'prive' && (string) $apres['title'] === 'Article journalisé 37'
    );
    $cache = tests37_requete('GET', '/articles/' . $apres['slug'], null);
    verifier(
        'journal : après dépublication, un visiteur ne voit plus l’article',
        $cache['code'] === 404,
        (string) $cache['code']
    );
}
