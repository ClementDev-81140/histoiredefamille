<?php
/**
 * Administration → Comptes : les personnes de la famille.
 *
 * Trois écrans, dans l'ordre :
 *   1. la liste et le formulaire (une personne, ou une liste collée) ;
 *   2. l'aperçu, obligatoire avant toute création : on y voit les
 *      identifiants proposés (modifiables), les lignes invalides et les
 *      doublons éventuels ;
 *   3. le résultat : mots de passe provisoires affichés UNE SEULE FOIS, avec
 *      les boutons « Copier le message » et « Tout copier ».
 *
 * Rien n'est jamais écrit en clair : seul le hachage part en base. Les mots
 * de passe provisoires voyagent dans la session le temps d'un affichage, puis
 * sont effacés (une page rechargée ne les montre plus).
 */

declare(strict_types=1);

/** Clé de session de l'aperçu en cours. */
const COMPTES_SESSION_APERCU = '_comptes_apercu';

/** Clé de session du résultat à afficher une seule fois. */
const COMPTES_SESSION_RESULTAT = '_comptes_resultat';

// ---------------------------------------------------------------------------
// Liste
// ---------------------------------------------------------------------------

/** Liste des comptes : personnes, comptes de branche et administrateurs. */
function admin_comptes(): void
{
    require_admin();

    $q         = query('q');
    $branche   = query_int('branche');
    $type      = query('type');
    $type      = array_key_exists($type, compte_types()) ? $type : '';

    $where  = ['1 = 1'];
    $params = [];

    if ($q !== '') {
        $where[]          = '(a.username LIKE :q OR a.first_name LIKE :q OR a.last_name LIKE :q
                              OR a.display_name LIKE :q OR b.name LIKE :q)';
        $params[':q']     = '%' . $q . '%';
    }
    if ($branche > 0) {
        $where[]           = 'a.branch_id = :branche';
        $params[':branche'] = $branche;
    }
    if ($type !== '') {
        $where[]        = 'a.account_type = :type';
        $params[':type'] = $type;
    }

    $whereSql = implode(' AND ', $where);

    $comptes = db_all(
        "SELECT a.*, b.name AS branche_nom, b.code AS branche_code, b.is_active AS branche_active
         FROM accounts a
         LEFT JOIN branches b ON b.id = a.branch_id
         WHERE {$whereSql}
         ORDER BY CASE a.account_type WHEN 'personne' THEN 0 WHEN 'branche' THEN 1 ELSE 2 END,
                  a.last_name COLLATE NOCASE, a.first_name COLLATE NOCASE, a.username COLLATE NOCASE",
        $params
    );

    // Comptes par type, pour le résumé en haut de page.
    $parType = ['personne' => 0, 'branche' => 0, 'admin' => 0];
    foreach (db_all('SELECT account_type, COUNT(*) AS nb FROM accounts GROUP BY account_type') as $ligne) {
        $cle = (string) $ligne['account_type'];
        $parType[$cle] = (int) $ligne['nb'];
    }

    render('admin/comptes', [
        'titre'   => 'Comptes de la famille',
        'comptes' => $comptes,
        'parType' => $parType,
        'q'       => $q,
        'branche' => $branche,
        'type'    => $type,
        'branches' => db_all('SELECT * FROM branches ORDER BY name COLLATE NOCASE'),
    ], 'layout_admin');
}

// ---------------------------------------------------------------------------
// Formulaire de création (une personne / plusieurs personnes)
// ---------------------------------------------------------------------------

/** Formulaire : « Une personne » et « Plusieurs personnes ». */
function admin_comptes_form(): void
{
    require_admin();

    render('admin/comptes_form', [
        'titre'    => 'Ajouter des personnes',
        'erreurs'  => form_errors(),
        'old'      => form_old(),
        'branches' => comptes_branches_proposees(),
        'maximum'  => COMPTES_MAX_LIGNES,
    ], 'layout_admin');
}

// ---------------------------------------------------------------------------
// Aperçu
// ---------------------------------------------------------------------------

/**
 * Prépare l'aperçu à partir du formulaire (une personne ou une liste).
 * Aucune écriture en base à ce stade.
 */
function admin_comptes_apercu(): void
{
    require_admin();
    csrf_check();

    $mode      = post('mode') === 'liste' ? 'liste' : 'une';
    $brancheId = (int) post('branche');
    $seeAll    = post('see_all') === '1';

    $branche = $brancheId > 0 ? find_branch($brancheId) : null;
    $erreurs = [];

    if ($branche === null || (int) $branche['is_active'] !== 1) {
        $erreurs[] = 'Choisissez une branche active : c’est elle qui donne les droits de lecture.';
        $brancheId = 0;
    }

    // 1. Les lignes à examiner.
    $lignes = [];
    if ($mode === 'une') {
        $nom    = compte_nettoyer_nom(post('nom'));
        $prenom = compte_nettoyer_nom(post('prenom'));
        $lignes[] = $nom !== '' && $prenom !== ''
            ? ['numero' => 1, 'nom' => $nom, 'prenom' => $prenom, 'remarque' => '', 'erreur' => '']
            : [
                'numero'   => 1,
                'nom'      => '',
                'prenom'   => '',
                'remarque' => '',
                'erreur'   => 'Indiquez le nom et le prénom (lettres, trait d’union et apostrophe).',
            ];
    } else {
        $analyse = compte_analyser_liste((string) ($_POST['liste'] ?? ''));
        $lignes  = $analyse['lignes'];
        foreach ($analyse['erreurs'] as $erreur) {
            $erreurs[] = $erreur;
        }
        if ($lignes === [] && $analyse['erreurs'] === []) {
            $erreurs[] = 'La liste est vide : collez une personne par ligne.';
        }
    }

    // 2. Identifiant proposé, doublons, avertissements.
    $reserves = [];
    $draft    = [];
    foreach ($lignes as $ligne) {
        $valide = $ligne['erreur'] === '' && $ligne['nom'] !== '' && $ligne['prenom'] !== '';

        $identifiant = $valide
            ? compte_identifiant_libre(compte_identifiant_base($ligne['prenom'], $ligne['nom']), 0, $reserves)
            : '';

        $doublon = $valide && $brancheId > 0
            ? compte_doublon($ligne['nom'], $ligne['prenom'], $brancheId)
            : null;

        if ($identifiant !== '') {
            $reserves[] = $identifiant;
        }

        $remarque = $ligne['remarque'];
        if ($doublon !== null) {
            $remarque = trim($remarque . ' Déjà présent dans cette branche (compte ' . $doublon['username'] . ').');
        }

        $draft[] = [
            'numero'      => (int) $ligne['numero'],
            'nom'         => $ligne['nom'],
            'prenom'      => $ligne['prenom'],
            'identifiant' => $identifiant,
            'erreur'      => $ligne['erreur'],
            'remarque'    => $remarque,
            'doublon'     => $doublon !== null,
            // Une ligne valide est cochée, sauf si elle existe déjà : c'est à
            // l'administrateur de décider (« créer quand même »).
            'creer'       => $valide && $doublon === null,
        ];
    }

    $bloc = [
        'token'   => bin2hex(random_bytes(16)),
        'mode'    => $mode,
        'branche' => $brancheId,
        'see_all' => $seeAll,
        'lignes'  => $draft,
        'erreurs' => $erreurs,
    ];
    $_SESSION[COMPTES_SESSION_APERCU] = $bloc;

    comptes_apercu_afficher($bloc, []);
}

/** Affiche l'écran d'aperçu (aussi utilisé après une erreur d'identifiant). */
function comptes_apercu_afficher(array $bloc, array $erreurs): void
{
    $branche = $bloc['branche'] > 0 ? find_branch((int) $bloc['branche']) : null;

    render('admin/comptes_apercu', [
        'titre'    => 'Aperçu avant création',
        'bloc'     => $bloc,
        'branche'  => $branche,
        'erreurs'  => $erreurs,
        'nbValides' => count(array_filter($bloc['lignes'], static fn (array $l): bool => $l['erreur'] === '')),
        'nbCochés'  => count(array_filter($bloc['lignes'], static fn (array $l): bool => !empty($l['creer']))),
    ], 'layout_admin');
}

// ---------------------------------------------------------------------------
// Création
// ---------------------------------------------------------------------------

/** Crée les comptes cochés de l'aperçu, puis affiche les mots de passe une fois. */
function admin_comptes_creer(): void
{
    require_admin();
    csrf_check();

    $bloc = $_SESSION[COMPTES_SESSION_APERCU] ?? null;
    $token = post('token');

    if (!is_array($bloc) || $token === '' || !hash_equals((string) ($bloc['token'] ?? ''), $token)) {
        flash('erreur', 'L’aperçu a expiré. Merci de recommencer.');
        redirect('/admin/comptes/nouvelle');
    }

    $identifiants = $_POST['identifiant'] ?? [];
    $creer        = $_POST['creer'] ?? [];
    $identifiants = is_array($identifiants) ? $identifiants : [];
    $creer        = is_array($creer) ? $creer : [];

    $entrees = [];
    $erreurs = [];
    $reserves = [];
    $doublonsForces = [];

    foreach ($bloc['lignes'] as $index => $ligne) {
        if ($ligne['erreur'] !== '' || empty($creer[$index])) {
            continue;
        }

        $identifiant = mb_strtolower(trim((string) ($identifiants[$index] ?? $ligne['identifiant'])), 'UTF-8');

        if (!compte_identifiant_valide($identifiant)) {
            $erreurs[] = sprintf(
                'Ligne %d : l’identifiant « %s » n’est pas valide (3 à 30 caractères : lettres minuscules, '
                . 'chiffres, point, tiret ou souligné, en commençant par une lettre).',
                (int) $ligne['numero'],
                $identifiant
            );
            continue;
        }
        if (in_array($identifiant, $reserves, true)) {
            $erreurs[] = sprintf('Ligne %d : l’identifiant « %s » est proposé deux fois.', (int) $ligne['numero'], $identifiant);
            continue;
        }
        if (compte_identifiant_pris($identifiant)) {
            $erreurs[] = sprintf('Ligne %d : l’identifiant « %s » est déjà pris.', (int) $ligne['numero'], $identifiant);
            continue;
        }

        $reserves[] = $identifiant;
        if ($ligne['doublon']) {
            $doublonsForces[] = compte_nom_complet(['first_name' => $ligne['prenom'], 'last_name' => $ligne['nom']]);
        }

        $entrees[] = [
            'nom'         => $ligne['nom'],
            'prenom'      => $ligne['prenom'],
            'identifiant' => $identifiant,
            'branche'     => (int) $bloc['branche'],
            'see_all'     => !empty($bloc['see_all']),
        ];
    }

    if ($erreurs !== []) {
        // On réaffiche l'aperçu avec les identifiants saisis, sans rien créer.
        foreach ($bloc['lignes'] as $index => $ligne) {
            if (isset($identifiants[$index]) && is_string($identifiants[$index])) {
                $bloc['lignes'][$index]['identifiant'] = mb_strtolower(trim($identifiants[$index]), 'UTF-8');
            }
            $bloc['lignes'][$index]['creer'] = !empty($creer[$index]);
        }
        $_SESSION[COMPTES_SESSION_APERCU] = $bloc;
        comptes_apercu_afficher($bloc, $erreurs);
        return;
    }

    if ($entrees === []) {
        flash('erreur', 'Aucune personne n’était cochée : rien n’a été créé.');
        redirect('/admin/comptes/nouvelle');
    }

    $resultat = comptes_creer($entrees, (int) current_account()['id']);

    if ($resultat['erreurs'] !== []) {
        comptes_apercu_afficher($bloc, $resultat['erreurs']);
        return;
    }

    // Effacé tout de suite : l'aperçu ne peut pas resservir.
    unset($_SESSION[COMPTES_SESSION_APERCU]);

    $identifiantsCrees = array_column($resultat['crees'], 'identifiant');
    log_activity(
        (int) current_account()['id'],
        'comptes_crees',
        sprintf(
            '%d compte(s) personnel(s) créé(s) : %s',
            count($identifiantsCrees),
            implode(', ', $identifiantsCrees)
        )
    );

    if ($doublonsForces !== []) {
        log_activity(
            (int) current_account()['id'],
            'comptes_doublons',
            'Créés malgré un doublon : ' . implode(', ', $doublonsForces)
        );
    }

    comptes_resultat_preparer($resultat['crees'], true);
}

/**
 * Met le résultat en session (mots de passe en clair, le temps d'un
 * affichage) puis redirige vers l'écran de résultat.
 *
 * @param list<array> $comptes
 */
function comptes_resultat_preparer(array $comptes, bool $creation): never
{
    $jeton = bin2hex(random_bytes(16));

    $lignes = [];
    foreach ($comptes as $compte) {
        $compte['identifiant'] = (string) ($compte['identifiant'] ?? $compte['username'] ?? '');
        $compte['message']     = compte_message_acces($compte, (string) $compte['mot_de_passe']);
        $lignes[]              = $compte;
    }

    $_SESSION[COMPTES_SESSION_RESULTAT] = [
        'token'    => $jeton,
        'creation' => $creation,
        'comptes'  => $lignes,
        'date'     => time(),
    ];

    redirect('/admin/comptes/resultat/' . $jeton);
}

/** Écran de résultat : les mots de passe sont pris puis effacés de la session. */
function admin_comptes_resultat(array $params): void
{
    require_admin();

    $jeton = (string) ($params['slug'] ?? '');
    $bloc  = $_SESSION[COMPTES_SESSION_RESULTAT] ?? null;

    $valide = is_array($bloc)
        && $jeton !== ''
        && hash_equals((string) ($bloc['token'] ?? ''), $jeton);

    if (!$valide) {
        // Page rechargée, ou lien rouvert plus tard : plus aucun mot de passe.
        render('admin/comptes_resultat', [
            'titre'   => 'Mots de passe',
            'comptes' => [],
            'creation' => true,
            'expire'  => true,
        ], 'layout_admin');
        return;
    }

    unset($_SESSION[COMPTES_SESSION_RESULTAT]);

    render('admin/comptes_resultat', [
        'titre'    => $bloc['creation'] ? 'Comptes créés' : 'Nouveau mot de passe',
        'comptes'  => $bloc['comptes'],
        'creation' => (bool) $bloc['creation'],
        'expire'   => false,
    ], 'layout_admin');
}

// ---------------------------------------------------------------------------
// Modifier une personne
// ---------------------------------------------------------------------------

/** Formulaire de modification d'un compte personnel. */
function admin_compte_form(array $params): void
{
    require_admin();

    $id      = (int) ($params['id'] ?? 0);
    $compte  = db_one('SELECT * FROM accounts WHERE id = ?', [$id]);

    if ($compte === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Compte introuvable', 'message' => 'Ce compte n’existe pas.']);
        return;
    }

    // Les comptes de branche et les administrateurs se modifient là où ils
    // ont été créés : on ne duplique pas les écrans.
    if (compte_type($compte) !== 'personne') {
        if (compte_type($compte) === 'admin') {
            flash('info', 'Les comptes administrateurs se modifient dans « Administrateurs ».');
            redirect('/admin/admins');
        }
        flash('info', 'Les comptes de branche se modifient dans « Branches & comptes ».');
        redirect('/admin/branches');
    }

    $brancheActuelle = $compte['branch_id'] !== null ? (int) $compte['branch_id'] : null;

    render('admin/compte_form', [
        'titre'    => 'Modifier ' . compte_nom_complet($compte),
        'compte'   => $compte,
        'erreurs'  => form_errors(),
        'old'      => form_old(),
        'branches' => comptes_branches_proposees($brancheActuelle),
    ], 'layout_admin');
}

/** Enregistre les modifications d'un compte personnel. */
function admin_compte_save(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $compte = db_one('SELECT * FROM accounts WHERE id = ?', [$id]);

    if ($compte === null || compte_type($compte) !== 'personne') {
        flash('erreur', 'Compte introuvable.');
        redirect('/admin/comptes');
    }

    $nom    = compte_nettoyer_nom(post('nom'));
    $prenom = compte_nettoyer_nom(post('prenom'));
    $identifiant = mb_strtolower(post('identifiant'), 'UTF-8');
    $brancheId   = (int) post('branche');
    $seeAll      = post('see_all') === '1';

    $back   = '/admin/comptes/' . $id;
    $errors = [];

    if ($nom === '') {
        $errors[] = 'Le nom est obligatoire (lettres, trait d’union et apostrophe, 40 lettres au plus).';
    }
    if ($prenom === '') {
        $errors[] = 'Le prénom est obligatoire (lettres, trait d’union et apostrophe, 40 lettres au plus).';
    }
    if (!compte_identifiant_valide($identifiant)) {
        $errors[] = 'L’identifiant doit contenir de 3 à 30 caractères : lettres minuscules, chiffres, '
            . 'point, tiret ou souligné, en commençant par une lettre.';
    } elseif (compte_identifiant_pris($identifiant, $id)) {
        $errors[] = 'Cet identifiant est déjà utilisé par un autre compte.';
    }

    $branche = $brancheId > 0 ? find_branch($brancheId) : null;
    if ($branche === null) {
        $errors[] = 'Choisissez une branche.';
    } elseif ((int) $branche['is_active'] !== 1 && $brancheId !== (int) ($compte['branch_id'] ?? 0)) {
        $errors[] = 'Cette branche est désactivée : choisissez-en une autre.';
    }

    if ($errors !== []) {
        form_fail($errors, [
            'nom'         => $nom,
            'prenom'      => $prenom,
            'identifiant' => $identifiant,
            'branche'     => $brancheId,
            'see_all'     => $seeAll,
        ], $back);
    }

    db_update('accounts', $id, [
        'username'     => $identifiant,
        // Le nom affiché d'un compte personnel reste le prénom.
        'display_name' => $prenom,
        'first_name'   => $prenom,
        'last_name'    => $nom,
        'branch_id'    => $brancheId,
        'see_all'      => $seeAll ? 1 : 0,
        'updated_at'   => time(),
    ]);

    log_activity(
        (int) current_account()['id'],
        'compte_modifie',
        sprintf('%s %s (%s)', $prenom, $nom, $identifiant)
    );

    flash('succes', 'Le compte a été modifié.');
    redirect('/admin/comptes');
}

/** Réinitialise le mot de passe d'une personne (affiché une seule fois). */
function admin_compte_password(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $compte = db_one('SELECT * FROM accounts WHERE id = ?', [$id]);

    if ($compte === null || compte_type($compte) !== 'personne') {
        flash('erreur', 'Compte introuvable.');
        redirect('/admin/comptes');
    }

    $motDePasse = password_generate();
    db_update('accounts', $id, [
        'password_hash' => password_make($motDePasse),
        'updated_at'    => time(),
    ]);

    $brancheNom = '';
    if ($compte['branch_id'] !== null) {
        $branche    = find_branch((int) $compte['branch_id']);
        $brancheNom = $branche !== null ? (string) $branche['name'] : '';
    }

    log_activity(
        (int) current_account()['id'],
        'mot_de_passe_personne',
        sprintf('Nouveau mot de passe pour %s (%s)', compte_nom_complet($compte), (string) $compte['username'])
    );

    comptes_resultat_preparer([[
        'nom'          => (string) $compte['last_name'],
        'prenom'       => (string) $compte['first_name'],
        'identifiant'  => (string) $compte['username'],
        'mot_de_passe' => $motDePasse,
        'branche_nom'  => $brancheNom,
    ]], false);
}

/** Désactive ou réactive un compte personnel. */
function admin_compte_toggle(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $compte = db_one('SELECT * FROM accounts WHERE id = ?', [$id]);

    if ($compte === null || compte_type($compte) !== 'personne') {
        flash('erreur', 'Compte introuvable.');
        redirect('/admin/comptes');
    }

    $nouvelEtat = (int) $compte['is_active'] === 1 ? 0 : 1;
    db_update('accounts', $id, ['is_active' => $nouvelEtat, 'updated_at' => time()]);

    log_activity(
        (int) current_account()['id'],
        $nouvelEtat === 1 ? 'compte_active' : 'compte_desactive',
        sprintf('%s (%s)', compte_nom_complet($compte), (string) $compte['username'])
    );

    flash('succes', $nouvelEtat === 1
        ? 'Le compte est de nouveau actif.'
        : 'Le compte est désactivé : la personne ne peut plus se connecter et sa session en cours est fermée.');

    redirect('/admin/comptes');
}

/** Supprime un compte personnel (ses messages du tchat restent). */
function admin_compte_delete(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $compte = db_one('SELECT * FROM accounts WHERE id = ?', [$id]);

    if ($compte === null || compte_type($compte) !== 'personne') {
        flash('erreur', 'Compte introuvable.');
        redirect('/admin/comptes');
    }

    log_activity(
        (int) current_account()['id'],
        'compte_supprime',
        sprintf('%s (%s)', compte_nom_complet($compte), (string) $compte['username'])
    );

    db_exec("DELETE FROM accounts WHERE id = ? AND account_type = 'personne'", [$id]);

    flash('succes', 'Le compte a été supprimé. Ses messages du tchat restent lisibles avec leur signature.');
    redirect('/admin/comptes');
}
