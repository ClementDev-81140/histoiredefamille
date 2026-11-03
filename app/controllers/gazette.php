<?php
/**
 * La gazette en ligne : /gazette/AAAA-MM.
 *
 * Réservée aux personnes connectées : chaque compte ne voit que le numéro de
 * SON groupe de droits, celui-là même qu'il a reçu (ou recevra) par courriel.
 * Un numéro qui n'existe pas — ou qui ne concerne pas ce compte — répond 404,
 * comme partout ailleurs sur le site.
 */

declare(strict_types=1);

/** Page publique d'un numéro de la gazette. */
function gazette_mois_page(array $params): void
{
    require_login();

    $compte  = current_account();
    // La période (« 2026-10 ») arrive comme un « slug » : le routeur n'accepte
    // que {id} (chiffres) et {slug} (lettres, chiffres et tirets).
    $periode = (string) ($params['slug'] ?? ($params['periode'] ?? ''));

    if ($compte === null || preg_match('/^\d{4}-\d{2}$/', $periode) !== 1) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Gazette introuvable',
            'message' => 'Ce numéro de la gazette n’existe pas.',
        ]);
        return;
    }

    $gazette = gazette_pour_compte($compte, $periode);
    $admin   = compte_type($compte) === 'admin';

    // Un numéro encore à valider ne se lit que dans l'administration ; un
    // numéro « passé » ne se lit plus du tout (sauf administrateur).
    if ($gazette === null
        || ($gazette['status'] === 'a_valider' && !$admin)
        || ($gazette['status'] === 'passe' && !$admin)) {
        http_response_code(404);
        render('erreur', [
            'titre'   => 'Gazette introuvable',
            'message' => 'Ce numéro de la gazette n’existe pas, ou il n’est pas encore envoyé.',
        ]);
        return;
    }

    render('public/gazette', [
        'titre'   => 'La gazette de ' . gazette_mois_libelle($periode),
        'gazette' => $gazette,
        'periode' => $periode,
    ]);
}
