<?php
/**
 * Tchat de la famille.
 *
 * Un seul salon, réservé aux personnes connectées. Trois points d'entrée :
 *  - la page /tchat (affichage + saisie du prénom) ;
 *  - /tchat/messages.json : interrogation toutes les 5 s par le navigateur,
 *    qui ne demande QUE les messages plus récents (ou plus anciens quand on
 *    remonte l'historique) ;
 *  - l'envoi (/tchat/envoyer) et la suppression par un administrateur.
 *
 * Rien n'est chargé en mémoire en entier : la page affiche 50 messages, la
 * suite arrive par petits paquets de 50.
 */

declare(strict_types=1);

/** Le navigateur attend-il du JSON (fetch) plutôt qu'une page ? */
function tchat_est_json(): bool
{
    $demande = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accepte = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $demande === 'fetch' || str_contains($accepte, 'application/json');
}

/** Vérifie le jeton CSRF sans afficher de page (pour les réponses JSON). */
function tchat_csrf_ok(): bool
{
    $envoye = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($envoye) && $envoye !== '' && hash_equals(csrf_token(), $envoye);
}

/** Réponse d'erreur adaptée : page normale ou JSON. */
function tchat_refus(string $message, int $statut = 400): never
{
    if (tchat_est_json()) {
        json_response(['ok' => false, 'erreur' => $message], $statut);
    }
    http_response_code($statut);
    render('erreur', ['titre' => 'Tchat', 'message' => $message]);
    exit;
}

/** Page du tchat. */
function tchat_page(): void
{
    require_login();

    if (!tchat_actif()) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Tchat fermé',
            'message' => 'Le tchat est momentanément fermé par les administrateurs.',
        ]);
        return;
    }

    $account = current_account();

    // Ouverture de la page = messages considérés comme lus (pastille à zéro).
    tchat_marquer_vu();

    $messages = [];
    $bloc     = ['html' => '', 'dernier' => 0, 'jour' => '', 'nombre' => 0];

    if (tchat_signature($account) !== '') {
        $messages = tchat_messages(50);
        $bloc     = tchat_bloc_html($messages);
    }

    $premier = $messages === [] ? 0 : (int) $messages[0]['id'];

    render('famille/tchat', [
        'titre'        => 'Tchat',
        'account'      => $account,
        'signature'    => tchat_signature($account),
        'prenom'       => tchat_prenom(),
        'messages'     => $messages,
        'bloc'         => $bloc,
        'premier'      => $premier,
        'resteAvant'   => $premier > 0 && (int) db_value(
            'SELECT COUNT(*) FROM chat_messages WHERE id < ?',
            [$premier],
            0
        ) > 0,
    ]);
}

/** Enregistrement du prénom (premier passage, puis modification). */
function tchat_prenom_save(): void
{
    require_login();
    if (!tchat_csrf_ok()) {
        tchat_refus('La page a expiré, merci de réessayer.', 403);
    }

    $prenom = tchat_nettoyer_prenom(post('prenom'));
    if ($prenom === '') {
        $erreur = 'Indiquez un prénom de 1 à 30 caractères (lettres, espace et tiret).';
        if (tchat_est_json()) {
            json_response(['ok' => false, 'erreur' => $erreur], 422);
        }
        flash('erreur', $erreur);
        redirect('/tchat');
    }

    $_SESSION['tchat_prenom'] = $prenom;

    if (tchat_est_json()) {
        json_response(['ok' => true, 'prenom' => $prenom, 'signature' => tchat_signature(current_account())]);
    }

    flash('succes', 'Vous signerez vos messages « ' . tchat_signature(current_account()) . ' ».');
    redirect('/tchat');
}

/**
 * Interrogation périodique.
 *
 *  ?apres=ID  → messages plus récents que ID (arrivée normale)
 *  ?avant=ID  → 50 messages plus anciens que ID (« voir les messages plus
 *               anciens »)
 */
function tchat_messages_json(): void
{
    require_login();
    if (!tchat_actif()) {
        json_response(['ok' => false, 'ferme' => true, 'erreur' => 'Le tchat est fermé.'], 403);
    }

    $account = current_account();
    if (tchat_signature($account) === '') {
        json_response(['ok' => false, 'prenom_requis' => true], 200);
    }

    $apres = query_int('apres');
    $avant = query_int('avant');

    if ($avant > 0) {
        $messages = tchat_messages(50, $avant);
        // `jour` (facultatif) = jour du message le plus ancien déjà affiché :
        // évite d'écrire deux fois le même séparateur de date.
        $bloc      = tchat_bloc_html($messages, query('jour'));
        $plusVieux = $messages === [] ? 0 : (int) $messages[0]['id'];

        json_response([
            'ok'         => true,
            'html'       => $bloc['html'],
            'nombre'     => $bloc['nombre'],
            'premier'    => $plusVieux,
            'jour'       => $messages === []
                ? ''
                : date('Y-m-d', (int) $messages[0]['created_at']),
            'resteAvant' => $plusVieux > 0 && (int) db_value(
                'SELECT COUNT(*) FROM chat_messages WHERE id < ?',
                [$plusVieux],
                0
            ) > 0,
        ]);
    }

    // Cas normal : uniquement ce qui est arrivé depuis le dernier affichage.
    // Sans repère (salon encore vide au chargement de la page), on renvoie les
    // derniers messages, du plus ancien au plus récent.
    if ($apres <= 0) {
        $messages = tchat_messages(100);
        $bloc     = tchat_bloc_html($messages);
    } else {
        $messages = tchat_messages(100, null, $apres);
        $bloc     = tchat_bloc_html($messages, tchat_jour($apres));
    }

    json_response([
        'ok'      => true,
        'html'    => $bloc['html'],
        'nombre'  => $bloc['nombre'],
        'dernier' => $bloc['dernier'] > 0 ? $bloc['dernier'] : $apres,
        'jour'    => $bloc['jour'],
    ]);
}

/** Envoi d'un message. */
function tchat_envoyer(): void
{
    require_login();

    if (!tchat_actif()) {
        tchat_refus('Le tchat est fermé pour le moment.', 403);
    }
    if (!tchat_csrf_ok()) {
        tchat_refus('La page a expiré, rechargez-la pour envoyer votre message.', 403);
    }

    $account = current_account();
    $label   = tchat_signature($account);
    if ($label === '') {
        tchat_refus('Choisissez d’abord le prénom qui signera vos messages.', 422);
    }

    $corps = trim((string) ($_POST['message'] ?? ''));
    if ($corps === '') {
        tchat_refus('Votre message est vide.', 422);
    }
    if (mb_strlen($corps, 'UTF-8') > 1000) {
        tchat_refus('Votre message dépasse 1000 caractères.', 422);
    }

    $autorise = tchat_peut_ecrire($account);
    if (!$autorise['ok']) {
        tchat_refus($autorise['message'], 429);
    }

    $id = db_insert('chat_messages', [
        'account_id'    => (int) $account['id'],
        'author_label'  => $label,
        'author_branch' => $account['role'] === 'admin'
            ? 'ADMIN'
            : (string) ($account['branch_code'] ?? ''),
        'body'        => $corps,
        'created_at'  => time(),
    ]);

    $bloc = tchat_bloc_html(tchat_messages(1, null, $id - 1), tchat_jour($id - 1));

    // Le tchat affiché = messages lus.
    tchat_marquer_vu();

    if (tchat_est_json()) {
        json_response([
            'ok'      => true,
            'html'    => $bloc['html'],
            'dernier' => $id,
            'jour'    => $bloc['jour'],
        ]);
    }

    redirect('/tchat');
}

/** Suppression d'un message par un administrateur (avec trace au journal). */
function tchat_supprimer(array $params): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $message = db_one('SELECT * FROM chat_messages WHERE id = ?', [$id]);

    if ($message === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Message introuvable', 'message' => 'Ce message n’existe plus.']);
        return;
    }

    db_exec('DELETE FROM chat_messages WHERE id = ?', [$id]);

    log_activity(
        (int) current_account()['id'],
        'tchat_suppression',
        'Message supprimé — auteur « ' . $message['author_label'] . ' », envoyé le '
            . format_date((int) $message['created_at'], true)
            . ', texte : « ' . mb_substr((string) $message['body'], 0, 120, 'UTF-8') . ' »'
    );

    flash('succes', 'Le message a été supprimé. La suppression est notée dans le journal d’activité.');
    redirect('/tchat');
}
