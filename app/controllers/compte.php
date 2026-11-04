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
        'titre'      => 'Mon compte',
        'erreurs'    => form_errors(),
        'account'    => $account,
        'branche'    => $branche,
        'courriel'   => courriel_resume_compte($account),
        'courrielOk' => courriel_configure(),
    ], is_admin() ? 'layout_admin' : 'layout');
}

/**
 * Réglages courriel d'un compte, prêts pour l'affichage.
 *
 * @return array{email:string, articles:bool, gazette:bool, possible:bool}
 */
function courriel_resume_compte(array $account): array
{
    return [
        'email'    => (string) ($account['email'] ?? ''),
        'articles' => (int) ($account['notify_articles'] ?? 0) === 1,
        'gazette'  => (int) ($account['notify_gazette'] ?? 0) === 1,
        'possible' => compte_type($account) !== 'branche',
    ];
}

/**
 * Enregistre l'adresse et les accords de la personne connectée.
 *
 * Changer d'adresse (ou la retirer) demande le mot de passe actuel : sans lui,
 * quelqu'un qui emprunterait une session ouverte pourrait détourner les
 * courriels de la famille vers une autre boîte.
 */
function mon_compte_courriels(): void
{
    require_login();
    csrf_check();

    $account = current_account();
    if ($account === null) {
        redirect('/connexion');
    }

    $retour = '/mon-compte';

    if (compte_type($account) === 'branche') {
        form_fail(
            ['Un compte de branche est partagé : l’adresse électronique est réservée aux comptes personnels.'],
            [],
            $retour
        );
    }

    $emailAvant = courriel_adresse_normaliser((string) ($account['email'] ?? ''));
    $email      = courriel_adresse_normaliser(post('email'));
    $articles   = post('notify_articles') === '1';
    $gazette    = post('notify_gazette') === '1';

    $erreurs = [];
    if ($email !== '' && !courriel_adresse_valide($email)) {
        $erreurs[] = 'Cette adresse électronique n’est pas valide (par exemple : prenom.nom@exemple.fr).';
    }
    if ($email !== '' && $email !== $emailAvant && !courriel_adresse_libre($email, (int) $account['id'])) {
        $erreurs[] = 'Cette adresse est déjà utilisée par un autre compte.';
    }

    // Changement d'adresse : mot de passe actuel exigé.
    if ($email !== $emailAvant) {
        $motDePasse = (string) ($_POST['current_password'] ?? '');
        if ($motDePasse === '' || !password_verify($motDePasse, (string) $account['password_hash'])) {
            $erreurs[] = 'Pour changer ou retirer votre adresse, tapez votre mot de passe actuel.';
        }
    }

    if ($erreurs !== []) {
        form_fail($erreurs, [], $retour);
    }

    // Sans adresse, il ne peut y avoir d'accord : les deux cases retombent à zéro.
    $articles = $email !== '' && $articles;
    $gazette  = $email !== '' && $gazette;

    // La date d'accord sur les articles ne se pose qu'au moment où la case est
    // cochée (voir courriel_accord_articles) : on ne reçoit jamais le retard.
    $accord = courriel_accord_articles($account, $articles);

    db_update('accounts', (int) $account['id'], [
        'email'                   => $email,
        'notify_articles'         => $accord['notify_articles'],
        'notify_articles_depuis'  => $accord['notify_articles_depuis'],
        'notify_gazette'          => $gazette ? 1 : 0,
        'updated_at'              => time(),
    ]);

    log_activity(
        (int) $account['id'],
        'courriel_preferences',
        'Adresse ' . ($email !== '' ? courriel_masquer($email) : 'retirée')
            . ' — articles : ' . ($articles ? 'oui' : 'non')
            . ' — gazette : ' . ($gazette ? 'oui' : 'non')
    );

    flash('succes', $email !== ''
        ? 'Votre adresse et vos choix sont enregistrés.'
        : 'Votre adresse a été retirée : vous ne recevrez plus de courriels.');
    redirect($retour);
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
