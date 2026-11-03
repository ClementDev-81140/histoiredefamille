<?php
/**
 * Administration → Gazette du mois.
 *
 * La préparation est automatique (le 1er de chaque mois, par la commande
 * d'envoi des courriels). Cette page sert à relire, retirer un élément,
 * écrire un mot, puis envoyer ou passer le mois.
 */

declare(strict_types=1);

/** Liste des numéros, du plus récent au plus ancien. */
function admin_gazette(): void
{
    require_admin();

    $gazettes = gazette_liste();
    $aValider = array_values(array_filter(
        $gazettes,
        static fn (array $g): bool => $g['status'] === 'a_valider'
    ));

    render('admin/gazette', [
        'titre'     => 'Gazette du mois',
        'gazettes'  => $gazettes,
        'aValider'  => $aValider,
        'auto'      => gazette_auto_active(),
        'prochain'  => gazette_mois_ecoule(),
        'grace'     => GAZETTE_GRACE_JOURS,
        'motMax'    => GAZETTE_MOT_MAX,
        'configure' => courriel_configure(),
    ], 'layout_admin');
}

/** Aperçu fidèle d'un numéro, avec les gestes de l'éditeur. */
function admin_gazette_apercu(array $params): void
{
    require_admin();

    $id      = (int) ($params['id'] ?? 0);
    $gazette = gazette_trouver($id);

    if ($gazette === null) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Numéro introuvable',
            'message' => 'Ce numéro de la gazette n’existe pas.',
        ], 'layout_admin');
        return;
    }

    // Les autres numéros du même mois : le sélecteur de groupe de droits.
    $memes = array_values(array_filter(
        gazette_liste((string) $gazette['periode']),
        static fn (array $g): bool => (int) $g['id'] !== $id
    ));

    $retenus = [];
    foreach ($gazette['retires'] as $cle) {
        $retenus[$cle] = true;
    }

    render('admin/gazette_apercu', [
        'titre'    => 'Gazette de ' . gazette_mois_libelle((string) $gazette['periode']),
        'gazette'  => $gazette,
        'memes'    => $memes,
        'retenus'  => $retenus,
        'motMax'   => GAZETTE_MOT_MAX,
    ], 'layout_admin');
}

/** Retire un élément du numéro (ou le remet). */
function admin_gazette_retirer(array $params): void
{
    require_admin();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $gazette = gazette_trouver($id);
    if ($gazette === null) {
        flash('erreur', 'Numéro introuvable.');
        redirect('/admin/gazette');
    }

    $cle     = (string) post('cle');
    $remettre = post('geste') === 'remettre';

    if (!gazette_retirer($id, $cle, !$remettre)) {
        flash('erreur', 'Cet élément ne fait pas partie de ce numéro, ou le numéro est déjà parti.');
        redirect('/admin/gazette/' . $id);
    }

    log_activity((int) current_account()['id'], 'gazette_element',
        ($remettre ? 'Élément remis' : 'Élément retiré') . ' dans la gazette de '
        . gazette_mois_libelle((string) $gazette['periode']) . ' (' . $cle . ')');

    flash('succes', $remettre ? 'L’élément est de nouveau dans la gazette.' : 'L’élément a été retiré.');
    redirect('/admin/gazette/' . $id);
}

/** Mot de l'éditeur. */
function admin_gazette_mot(array $params): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $gazette = gazette_trouver($id);

    if ($gazette === null) {
        flash('erreur', 'Numéro introuvable.');
        redirect('/admin/gazette');
    }

    $texte = (string) post('mot');
    if (!gazette_mot($id, $texte)) {
        form_fail(
            ['Le mot de l’éditeur est limité à ' . GAZETTE_MOT_MAX . ' caractères, et ne se modifie plus une fois le numéro envoyé.'],
            ['mot' => $texte],
            '/admin/gazette/' . $id
        );
        return;
    }

    flash('succes', 'Le mot de l’éditeur est enregistré.');
    redirect('/admin/gazette/' . $id);
}

/** « Envoyer » : met les messages en file (jamais pendant la visite). */
function admin_gazette_envoyer(array $params): void
{
    require_admin();
    csrf_check();

    $id      = (int) ($params['id'] ?? 0);
    $gazette = gazette_trouver($id);

    if ($gazette === null) {
        flash('erreur', 'Numéro introuvable.');
        redirect('/admin/gazette');
    }

    $mis = gazette_envoyer($id);
    if ($mis === 0) {
        flash('erreur', 'Rien n’a été mis en file : ce numéro est vide, déjà envoyé, ou plus personne ne reçoit la gazette.');
        redirect('/admin/gazette/' . $id);
    }

    flash('succes', $mis . ' message(s) mis en file pour la gazette de '
        . gazette_mois_libelle((string) $gazette['periode'])
        . '. Ils partiront dans les cinq minutes (tâche automatique).');
    redirect('/admin/gazette');
}

/** « Passer ce mois-ci ». */
function admin_gazette_passer(array $params): void
{
    require_admin();
    csrf_check();

    $id = (int) ($params['id'] ?? 0);
    if (!gazette_passer($id)) {
        flash('erreur', 'Ce numéro est déjà envoyé ou passé.');
        redirect('/admin/gazette');
    }

    flash('succes', 'Ce mois-ci est passé : aucun courriel ne sera envoyé.');
    redirect('/admin/gazette');
}

/** Réglage : envoi automatique sans validation (désactivé par défaut). */
function admin_gazette_auto(): void
{
    require_admin();
    csrf_check();

    $actif = post('auto') === '1';
    gazette_auto_regler($actif);

    log_activity((int) current_account()['id'], 'gazette_reglage',
        'Envoi automatique de la gazette : ' . ($actif ? 'activé' : 'désactivé'));

    flash('succes', $actif
        ? 'L’envoi automatique est activé : au-delà de ' . GAZETTE_GRACE_JOURS . ' jours, un numéro non validé partira tout seul.'
        : 'L’envoi automatique est désactivé : chaque numéro attend votre décision.');
    redirect('/admin/gazette');
}
