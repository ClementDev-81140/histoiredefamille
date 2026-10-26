<?php
/**
 * Connexion et déconnexion.
 */

declare(strict_types=1);

/** Formulaire de connexion. */
function auth_login_form(): void
{
    if (is_logged_in()) {
        redirect(home_for_account());
    }

    render('public/connexion', [
        'titre'      => 'Connexion',
        'next'       => (string) ($_GET['next'] ?? ''),
        'identifiant' => (string) ($_SESSION['_old_username'] ?? ''),
    ]);
    unset($_SESSION['_old_username']);
}

/** Traitement du formulaire de connexion. */
function auth_login_submit(): void
{
    csrf_check();

    $username = post('username');
    $password = (string) ($_POST['password'] ?? '');
    $next     = post('next');

    $result = auth_attempt($username, $password);

    if (!$result['ok']) {
        $_SESSION['_old_username'] = $username;
        flash('erreur', $result['message']);
        redirect('/connexion' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
    }

    // Redirection interne uniquement (pas d'URL externe).
    if ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
        redirect($next);
    }
    redirect(home_for_account());
}

/** Déconnexion (POST + jeton CSRF pour éviter les déconnexions forcées). */
function auth_logout_action(): void
{
    csrf_check();
    auth_logout();
    session_start_secure();
    flash('info', 'Vous êtes déconnecté.');
    redirect('/');
}
