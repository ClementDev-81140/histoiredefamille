<?php
/**
 * Administration → Calendrier (1.32.0).
 *
 * Liste des événements, création, modification et suppression. Tout est
 * réservé aux administrateurs, en POST avec jeton CSRF, et la suppression
 * demande une confirmation dans le navigateur.
 *
 * Un nouvel événement est PRIVÉ : personne ne le voit tant que
 * l'administrateur n'a pas choisi explicitement qui peut le voir.
 */

declare(strict_types=1);

/** Liste des événements, du plus proche au plus lointain. */
function admin_evenements(): void
{
    require_admin();

    $evenements = db_all(
        'SELECT * FROM events ORDER BY start_date DESC, start_time, title COLLATE NOCASE'
    );

    render('admin/evenements', [
        'titre'      => 'Calendrier',
        'evenements' => $evenements,
        'erreurs'    => form_errors(),
        'old'        => form_old(),
    ], 'layout_admin');
}

/** Formulaire de création ou de modification. */
function admin_evenement_form(array $params = []): void
{
    require_admin();

    $id        = (int) ($params['id'] ?? 0);
    $evenement = $id > 0 ? evenement_par_id($id) : null;
    if ($id > 0 && $evenement === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Événement introuvable', 'message' => 'Cet événement n’existe pas.']);
        return;
    }

    render('admin/evenement_form', [
        'titre'      => $evenement !== null ? 'Modifier « ' . $evenement['title'] . ' »' : 'Nouvel événement',
        'evenement'  => $evenement,
        'branches'   => branches_list(true),
        'evBranches' => $id > 0 ? content_branch_ids('event', $id) : [],
        'erreurs'    => form_errors(),
        'old'        => form_old(),
    ], 'layout_admin');
}

/** Enregistrement (création ou modification). */
function admin_evenement_save(array $params = []): void
{
    require_admin();
    csrf_check();

    $id        = (int) ($params['id'] ?? 0);
    $evenement = $id > 0 ? evenement_par_id($id) : null;
    $retour    = $id > 0 ? '/admin/calendrier/' . $id . '/modifier' : '/admin/calendrier/nouveau';

    if ($id > 0 && $evenement === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Événement introuvable', 'message' => 'Cet événement n’existe pas.']);
        return;
    }

    [$valeurs, $erreurs] = evenement_valider($_POST);
    if ($erreurs !== []) {
        form_fail($erreurs, $valeurs, $retour);
    }

    if ($id > 0) {
        evenement_modifier($id, $valeurs);
        $evenementId = $id;
    } else {
        $evenementId = evenement_creer($valeurs, (int) current_account()['id']);
    }

    log_activity(
        (int) current_account()['id'],
        $id > 0 ? 'evenement_modifie' : 'evenement_ajoute',
        (string) $valeurs['title'] . ' (' . (string) $valeurs['start_date'] . ')'
    );

    flash('succes', $id > 0 ? 'L’événement a été modifié.' : 'L’événement a été créé.');
    redirect('/admin/calendrier');
}

/** Validation d'un événement proposé par un journaliste (1.34.0). */
function admin_evenement_valider(array $params = []): void
{
    require_admin();
    csrf_check();

    $id        = (int) ($params['id'] ?? 0);
    $evenement = evenement_par_id($id);
    if ($evenement === null) {
        flash('erreur', 'Événement introuvable.');
        redirect('/admin/calendrier');
    }

    evenement_valider_publication($id);
    log_activity(
        (int) current_account()['id'],
        'evenement_valide',
        (string) $evenement['title'] . ' (' . (string) $evenement['start_date'] . ')'
    );

    flash('succes', 'L’événement a été validé : il apparaît dans le calendrier.');
    redirect('/admin/calendrier');
}

/** Suppression, après confirmation dans le navigateur. */
function admin_evenement_supprimer(array $params = []): void
{
    require_admin();
    csrf_check();

    $id        = (int) ($params['id'] ?? 0);
    $evenement = evenement_par_id($id);
    if ($evenement === null) {
        flash('erreur', 'Événement introuvable.');
        redirect('/admin/calendrier');
    }

    evenement_supprimer($id);
    log_activity(
        (int) current_account()['id'],
        'evenement_supprime',
        (string) $evenement['title'] . ' (' . (string) $evenement['start_date'] . ')'
    );

    flash('succes', 'L’événement a été supprimé.');
    redirect('/admin/calendrier');
}
