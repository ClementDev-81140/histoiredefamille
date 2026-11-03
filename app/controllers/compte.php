<?php
/**
 * « Mon compte » : chaque personne connectée change SON mot de passe.
 *
 * La page est la même pour tous les comptes (personne, branche,
 * administrateur). Rien d'autre n'y est modifiable : le nom, le prénom,
 * l'identifiant et la branche sont du ressort de l'administration.
 */

declare(strict_types=1);

/** Page « Mon compte » (mot de passe). */
function mon_compte(): void
{
    require_login();

    $account = current_account();
    $branche = $account !== null && $account['branch_id'] !== null
        ? find_branch((int) $account['branch_id'])
        : null;

    render('compte/mon_compte', [
        'titre'   => 'Mon compte',
        'erreurs' => form_errors(),
        'account' => $account,
        'branche' => $branche,
    ], is_admin() ? 'layout_admin' : 'layout');
}

/** Enregistrement du nouveau mot de passe. */
function mon_compte_save(): void
{
    require_login();
    csrf_check();

    $account = current_account();
    if ($account === null) {
        redirect('/connexion');
    }

    // Les mots de passe ne sont jamais « nettoyés » : un espace au début ou à
    // la fin fait partie du secret.
    $actuel       = (string) ($_POST['current_password'] ?? '');
    $nouveau      = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');

    $erreurs = mot_de_passe_changer($account, $actuel, $nouveau, $confirmation);

    if ($erreurs !== []) {
        form_fail($erreurs, [], '/mon-compte');
    }

    // Nouvel identifiant de session : un mot de passe qui change ferme les
    // sessions ouvertes ailleurs avec l'ancien (protection classique).
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    log_activity((int) $account['id'], 'mot_de_passe', 'Changement de son propre mot de passe');
    flash('succes', 'Votre mot de passe a été modifié. Utilisez-le à la prochaine connexion.');
    redirect('/mon-compte');
}
