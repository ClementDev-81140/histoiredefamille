<?php
/**
 * Administration : envoi de gros fichiers par morceaux.
 *
 * Neuf routes, toutes réservées aux administrateurs (les fichiers envoyés
 * peuvent être privés, et un envoi peut occuper le disque) :
 *
 *   GET  /admin/envoyer                 page « Envoyer des fichiers » ;
 *   POST /admin/envoyer/init            annonce un fichier (réponse JSON) ;
 *   POST /admin/envoyer/morceau         reçoit un morceau (réponse JSON) ;
 *   GET  /admin/envoyer/etat            dit ce qui est déjà reçu (JSON) ;
 *   POST /admin/envoyer/terminer        assemble et crée la fiche (JSON) ;
 *   POST /admin/envoyer/decision        garder / ignorer un doublon (JSON) ;
 *   POST /admin/envoyer/annuler         supprime les morceaux ;
 *   GET  /admin/envoyer/lot             complète tout un lot d'un coup ;
 *   POST /admin/envoyer/lot             enregistre le lot ;
 *   GET  /admin/envois                  « Mes envois » (et supervision).
 *
 * Rien n'est jamais servi ni exécuté avant la fin : les morceaux et le fichier
 * assemblé vivent dans le stockage, hors du dossier web.
 */

declare(strict_types=1);

/**
 * Réservé aux administrateurs — avec deux comportements.
 *
 * Le script d'envoi envoie l'en-tête « X-Requested-With » : dans ce cas on
 * répond en JSON (le navigateur affiche un message clair, sans quitter la
 * page). Pour une requête ordinaire (lien, formulaire, test d'accès), on se
 * comporte comme toutes les pages d'administration : renvoi vers la connexion
 * pour un visiteur, « Accès refusé » pour un compte de famille.
 */
function envoi_exiger_admin_json(): bool
{
    $estScript = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    // Les adresses /propositions/fichier/* réutilisent ce protocole pour un
    // journaliste : même code, mêmes contrôles, mais la fiche n'est jamais
    // créée (le fichier reste « prêt », à rattacher à une proposition).
    //
    // Le mode est lu puis remis à zéro : il ne vaut que pour l'appel en cours,
    // jamais pour la requête suivante (les tests enchaînent les requêtes dans
    // le même processus).
    $journaliste = !empty($GLOBALS['hdf_envoi_journaliste']);
    $GLOBALS['hdf_envoi_journaliste'] = false;

    if (!$estScript) {
        if ($journaliste) {
            require_proposer();
        } else {
            require_admin();
        }
        return $journaliste;
    }

    if (!is_logged_in()) {
        json_response([
            'ok'     => false,
            'erreur' => 'Votre session a expiré. Reconnectez-vous, puis relancez l’envoi.',
            'code'   => 'session',
        ], 401);
    }
    if ($journaliste ? !peut_proposer() : !is_admin()) {
        json_response([
            'ok'     => false,
            'erreur' => $journaliste
                ? 'Votre compte ne permet pas de proposer des fichiers.'
                : 'Cette action est réservée aux administrateurs.',
            'code'   => 'refus',
        ], 403);
    }

    return $journaliste;
}

/** Refus JSON quand le jeton CSRF manque ou ne correspond pas. */
function envoi_csrf_json(): void
{
    $envoye = $_POST['_csrf'] ?? '';
    if (!csrf_valide($envoye)) {
        json_response([
            'ok'     => false,
            'erreur' => 'Le formulaire a expiré. Rechargez la page, puis relancez l’envoi.',
            'code'   => 'csrf',
        ], 403);
    }
}

/**
 * Version « JSON » d'un envoi : jamais de chemin, jamais de nom de dossier.
 *
 * @return array<string, mixed>
 */
function envoi_json(?array $envoi): array
{
    if ($envoi === null) {
        return [];
    }

    $pieces = envoi_morceaux($envoi);

    return [
        'id'            => (int) $envoi['id'],
        'token'         => (string) $envoi['token'],
        'nom'           => (string) $envoi['original_name'],
        'taille'        => (int) $envoi['size'],
        'morceau'       => (int) $envoi['chunk_size'],
        'morceaux'      => (int) $envoi['chunks_total'],
        'recus'         => $pieces['recus'],
        'manquants'     => $pieces['manquants'],
        'complet'       => $pieces['complet'],
        'etat'          => (string) $envoi['state'],
        'message'       => (string) $envoi['message'],
        'document_id'   => (int) ($envoi['document_id'] ?? 0),
    ];
}

/** Un envoi du compte courant, ou une réponse JSON de refus. */
function envoi_exiger_le_sien(string $token): array
{
    $envoi = envoi_par_token($token);
    if ($envoi === null) {
        json_response(['ok' => false, 'erreur' => 'Envoi introuvable.', 'code' => 'inconnu'], 404);
    }
    if (!envoi_appartient($envoi, (int) current_account()['id'])) {
        json_response([
            'ok'     => false,
            'erreur' => 'Cet envoi appartient à un autre administrateur : vous pouvez le consulter, pas le modifier.',
            'code'   => 'refus',
        ], 403);
    }
    return $envoi;
}

// ---------------------------------------------------------------------------
// Page « Envoyer des fichiers »
// ---------------------------------------------------------------------------

/** Page unique d'envoi : glisser-déposer + les derniers fichiers reçus. */
function admin_envoi_page(): void
{
    require_admin();

    $compte = (int) current_account()['id'];
    $reglages = envoi_reglages();

    $recents = db_all(
        "SELECT u.*, d.title AS document_titre
         FROM uploads u LEFT JOIN documents d ON d.id = u.document_id
         WHERE u.account_id = ? AND u.state = 'termine' AND u.updated_at >= ?
         ORDER BY u.updated_at DESC LIMIT 20",
        [$compte, time() - 86400]
    );

    $prets = db_all(
        "SELECT * FROM uploads WHERE account_id = ? AND state = 'pret' ORDER BY updated_at DESC LIMIT 100",
        [$compte]
    );

    render('admin/envoyer', [
        'titre'      => 'Envoyer des fichiers',
        'reglages'   => $reglages,
        'recents'    => $recents,
        'prets'      => $prets,
        'enCours'    => envoi_actifs($compte),
        'maxBytes'   => upload_max_bytes(),
        'libre'      => storage_free_space(),
        'erreurs'    => form_errors(),
        'old'        => form_old(),
    ], 'layout_admin');
}

// ---------------------------------------------------------------------------
// Protocole (réponses JSON)
// ---------------------------------------------------------------------------

/** « init » : annonce un fichier, après tous les refus possibles. */
function admin_envoi_init(): void
{
    $journaliste = envoi_exiger_admin_json();
    envoi_csrf_json();

    $nom    = post('nom');
    $taille = (int) post('taille');
    $mime   = post('type');
    $creer  = post('creer', '1') !== '0';

    // Un journaliste ne crée jamais de fiche par l'envoi : son fichier reste
    // « prêt » et ne devient un document que dans sa proposition.
    if ($journaliste) {
        $creer = false;
    }

    $compte = (int) current_account()['id'];

    // « reprise » : si un envoi du même nom et de la même taille est déjà en
    // cours, on le reprend au lieu d'en créer un deuxième (rechargement de la
    // page, coupure, retour le lendemain).
    if (post('reprise') === '1') {
        $existant = envoi_a_reprendre($compte, $nom, $taille);
        if ($existant !== null) {
            envoi_toucher((int) $existant['id']);
            json_response([
                'ok'         => true,
                'reprise'    => true,
                'envoi'      => envoi_json($existant),
                'simultanes' => envoi_reglages()['simultanes'],
            ]);
        }
    }

    $resultat = envoi_creer($compte, $nom, $taille, $mime, $creer);
    if (!$resultat['ok']) {
        json_response(['ok' => false, 'erreur' => $resultat['error'], 'code' => 'refus'], 400);
    }

    json_response([
        'ok'       => true,
        'envoi'    => envoi_json($resultat['envoi']),
        'simultanes' => envoi_reglages()['simultanes'],
    ]);
}

/** « morceau » : dépose un morceau numéroté (sans effet s'il est déjà reçu). */
function admin_envoi_morceau(): void
{
    envoi_exiger_admin_json();
    envoi_csrf_json();

    $envoi  = envoi_exiger_le_sien(post('token'));
    $numero = (int) post('numero');

    $fichier = $_FILES['morceau'] ?? null;
    if (!is_array($fichier) || (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response([
            'ok'     => false,
            'erreur' => 'Le morceau n’est pas arrivé entier. Nouvelle tentative…',
            'code'   => 'morceau',
        ], 400);
    }

    if (!is_uploaded_file((string) $fichier['tmp_name'])) {
        json_response(['ok' => false, 'erreur' => 'Fichier temporaire invalide.', 'code' => 'morceau'], 400);
    }

    $resultat = envoi_recevoir_morceau(
        $envoi,
        $numero,
        (string) $fichier['tmp_name'],
        (int) $fichier['size']
    );

    if (!$resultat['ok']) {
        json_response(['ok' => false, 'erreur' => $resultat['error'], 'code' => 'morceau'], 400);
    }

    $pieces = envoi_morceaux(envoi_par_token((string) $envoi['token']));

    json_response([
        'ok'      => true,
        'numero'  => $numero,
        'deja'    => $resultat['deja'],
        'recus'   => $pieces['recus'],
        'complet' => $pieces['complet'],
    ]);
}

/** « état » : ce qui est déjà reçu (permet de reprendre après une coupure). */
function admin_envoi_etat(): void
{
    envoi_exiger_admin_json();

    $token = query('token');
    if ($token === '') {
        // Sans identifiant, ce n'est pas une erreur : la page demande
        // simplement où en est un envoi, et il n'y en a pas.
        json_response(['ok' => true, 'envoi' => null, 'doublon' => null]);
    }

    $envoi = envoi_exiger_le_sien($token);
    $json  = envoi_json($envoi);

    $doublon = null;
    if ((string) $envoi['state'] === 'doublon' && (string) $envoi['sha256'] !== '') {
        $doublon = envoi_doublon((string) $envoi['sha256']);
    }

    json_response(['ok' => true, 'envoi' => $json, 'doublon' => $doublon]);
}

/** « terminer » : assemble, vérifie, empreinte, fiche privée. */
function admin_envoi_terminer(): void
{
    envoi_exiger_admin_json();
    envoi_csrf_json();

    $envoi = envoi_exiger_le_sien(post('token'));
    $forcer = post('forcer') === '1';

    $resultat = envoi_terminer($envoi, $forcer);
    if (!$resultat['ok']) {
        json_response([
            'ok'     => false,
            'erreur' => $resultat['error'],
            'etat'   => $resultat['etat'],
            'envoi'  => envoi_json($resultat['envoi']),
            'code'   => 'terminer',
        ], 400);
    }

    $envoi = $resultat['envoi'];

    if ($resultat['etat'] === 'termine') {
        journaliser_envoi_termine($envoi);
    }

    json_response([
        'ok'      => true,
        'etat'    => $resultat['etat'],
        'envoi'   => envoi_json($envoi),
        'doublon' => $resultat['doublon'],
        'lien'    => (int) ($envoi['document_id'] ?? 0) > 0
            ? '/documents/' . (int) $envoi['document_id']
            : '',
    ]);
}

/** Décision devant un doublon : « garder » ou « ignorer ». */
function admin_envoi_decision(): void
{
    envoi_exiger_admin_json();
    envoi_csrf_json();

    $envoi = envoi_exiger_le_sien(post('token'));
    $choix = post('choix') === 'garder' ? 'garder' : 'ignorer';

    $resultat = envoi_decider($envoi, $choix);
    if (!$resultat['ok']) {
        json_response(['ok' => false, 'erreur' => $resultat['error'], 'code' => 'decision'], 400);
    }

    if ($resultat['etat'] === 'termine') {
        journaliser_envoi_termine($resultat['envoi']);
    } elseif ($choix === 'ignorer') {
        log_activity(
            (int) current_account()['id'],
            'envoi_annule',
            envoi_nom_propre((string) $envoi['original_name']) . ' (doublon ignoré)'
        );
    }

    $envoi = $resultat['envoi'];

    json_response([
        'ok'    => true,
        'etat'  => $resultat['etat'],
        'envoi' => envoi_json($envoi),
        'lien'  => (int) ($envoi['document_id'] ?? 0) > 0
            ? '/documents/' . (int) $envoi['document_id']
            : '',
    ]);
}

/** « annuler » : supprime les morceaux reçus. */
function admin_envoi_annuler(): void
{
    $estJson = post('format') === 'json';

    if ($estJson) {
        envoi_exiger_admin_json();
        envoi_csrf_json();
    } else {
        require_admin();
        csrf_check();
    }

    $token = post('token');
    $envoi = envoi_par_token($token);

    if ($envoi !== null && !envoi_appartient($envoi, (int) current_account()['id'])) {
        if ($estJson) {
            json_response([
                'ok'     => false,
                'erreur' => 'Cet envoi appartient à un autre administrateur.',
                'code'   => 'refus',
            ], 403);
        }
        flash('erreur', 'Cet envoi appartient à un autre administrateur.');
        redirect('/admin/envois');
    }

    if ($envoi !== null) {
        envoi_annuler($envoi);
        log_activity(
            (int) current_account()['id'],
            'envoi_annule',
            envoi_nom_propre((string) $envoi['original_name']) . ' (' . format_size((int) $envoi['size']) . ')'
        );
    }

    if ($estJson) {
        json_response(['ok' => true]);
    }

    flash('succes', 'L’envoi a été annulé et ses morceaux supprimés.');
    redirect('/admin/envois');
}

/** Journalise « envoi terminé » : nom nettoyé et taille, jamais le contenu. */
function journaliser_envoi_termine(?array $envoi): void
{
    if ($envoi === null) {
        return;
    }
    log_activity(
        (int) current_account()['id'],
        'envoi_termine',
        envoi_nom_propre((string) $envoi['original_name']) . ' (' . format_size((int) $envoi['size']) . ')'
    );
}

// ---------------------------------------------------------------------------
// Formulaire de lot
// ---------------------------------------------------------------------------

/**
 * Les documents récemment créés par un envoi de ce compte, et pas encore
 * complétés (visibilité encore privée et aucune catégorie choisie).
 *
 * @return array<int, array<string, mixed>>
 */
function envoi_lot_documents(int $compteId, int $limite = 100): array
{
    return db_all(
        "SELECT d.*, u.id AS upload_id, u.original_name AS upload_nom
         FROM uploads u
         JOIN documents d ON d.id = u.document_id
         WHERE u.account_id = ? AND u.state = 'termine' AND u.updated_at >= ?
         ORDER BY u.updated_at DESC LIMIT " . max(1, min(300, $limite)),
        [$compteId, time() - 7 * 86400]
    );
}

/** Formulaire commun appliqué à tout un lot. */
function admin_envoi_lot(): void
{
    require_admin();

    $compte    = (int) current_account()['id'];
    $documents = envoi_lot_documents($compte);

    render('admin/envoi_lot', [
        'titre'      => 'Compléter un lot',
        'documents'  => $documents,
        'categories' => categories_list(),
        'albums'     => db_all('SELECT id, title FROM albums ORDER BY title COLLATE NOCASE'),
        'branches'   => branches_list(true),
        'erreurs'    => form_errors(),
        'old'        => form_old(),
    ], 'layout_admin');
}

/** Enregistre un lot : réglages communs, puis ajustements fichier par fichier. */
function admin_envoi_lot_save(): void
{
    require_admin();
    csrf_check();

    $compte = (int) current_account()['id'];
    $ids    = array_values(array_unique(array_map('intval', post_array('documents'))));

    $old = [
        'kind'       => post('kind'),
        'category_id' => post('category_id'),
        'visibility' => sanitize_visibility(post('visibility')),
        'branches'   => array_map('intval', post_array('branches')),
        'album_id'   => post('album_id'),
    ];

    if ($ids === []) {
        form_fail(['Cochez au moins un fichier à compléter.'], $old, '/admin/envoyer/lot');
    }

    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        form_fail(['Visibilité « Branches précises » : cochez au moins une branche.'], $old, '/admin/envoyer/lot');
    }

    $categoryId = (int) $old['category_id'] > 0 ? (int) $old['category_id'] : null;
    if ($categoryId !== null && !find_category($categoryId)) {
        form_fail(['La catégorie choisie n’existe pas.'], $old, '/admin/envoyer/lot');
    }

    $kind = in_array($old['kind'], ['photo', 'video', 'document'], true)
        ? $old['kind']
        : '';

    // Seuls les documents issus d'un envoi de CE compte peuvent être modifiés
    // ici : un administrateur ne touche pas aux fichiers des autres.
    $autorises = [];
    foreach (envoi_lot_documents($compte, 300) as $document) {
        $autorises[(int) $document['id']] = $document;
    }

    $modifies = 0;
    $albumId  = (int) $old['album_id'] > 0 ? (int) $old['album_id'] : 0;
    $ajoutes   = [];

    foreach ($ids as $id) {
        if (!isset($autorises[$id])) {
            continue;
        }

        $titre = trim(post('titre_' . $id));
        if ($titre === '') {
            $titre = (string) $autorises[$id]['title'];
        }
        if (mb_strlen($titre) > 200) {
            $titre = mb_substr($titre, 0, 200, 'UTF-8');
        }

        $date     = trim(post('date_' . $id));
        $visPerso = post('visibilite_' . $id);
        $visFin   = $visPerso !== '' ? sanitize_visibility($visPerso) : $visibility;

        db_update('documents', $id, [
            'title'         => $titre,
            'doc_date'      => $date !== '' ? mb_substr($date, 0, 100, 'UTF-8') : null,
            'doc_date_sort' => extract_year($date),
            'category_id'   => $categoryId,
            'visibility'    => $visFin,
            'kind'          => $kind !== '' ? $kind : (string) $autorises[$id]['kind'],
            'updated_at'    => time(),
        ]);

        set_content_branches('document', $id, $visFin === 'branches' ? $branches : []);
        $modifies++;

        if ($albumId > 0) {
            $ajoutes[] = $id;
        }
    }

    if ($albumId > 0 && $ajoutes !== [] && find_album($albumId)) {
        $photos = album_photo_ids($albumId);
        foreach ($ajoutes as $id) {
            if (!in_array($id, $photos, true)) {
                $photos[] = $id;
            }
        }
        set_album_photos($albumId, $photos);
    }

    log_activity(
        (int) current_account()['id'],
        'envoi_lot',
        $modifies . ' fichier(s) complété(s), visibilité « ' . visibility_short($visibility) . ' »'
    );

    flash('succes', $modifies . ' fichier(s) complété(s).');
    redirect('/admin/envois');
}

// ---------------------------------------------------------------------------
// « Mes envois »
// ---------------------------------------------------------------------------

/** Liste des envois : les siens, ou tous (supervision, lecture seule). */
function admin_envois(): void
{
    require_admin();

    $tous   = query('tous') === '1';
    $compte = (int) current_account()['id'];

    $envois = envoi_liste($tous ? null : $compte);

    $documents = [];
    foreach ($envois as $envoi) {
        $id = (int) ($envoi['document_id'] ?? 0);
        if ($id > 0 && !isset($documents[$id])) {
            $documents[$id] = db_one('SELECT id, title, visibility FROM documents WHERE id = ?', [$id]);
        }
    }

    render('admin/envois', [
        'titre'     => $tous ? 'Tous les envois' : 'Mes envois',
        'envois'    => $envois,
        'documents' => $documents,
        'tous'      => $tous,
        'compte'    => $compte,
        'reglages'  => envoi_reglages(),
        'erreurs'   => form_errors(),
    ], 'layout_admin');
}
