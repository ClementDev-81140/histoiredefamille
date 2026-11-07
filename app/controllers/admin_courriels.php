<?php
/**
 * Administration → Courriels.
 *
 * Trois choses seulement :
 *   - savoir si l'envoi est configuré (sans jamais montrer le mot de passe) ;
 *   - régler le plafond quotidien et l'adresse de réponse ;
 *   - envoyer un essai à soi-même et regarder les derniers envois.
 *
 * Aucun courriel ne part d'ici, sauf l'essai demandé exprès : le reste attend
 * la commande app/cli/envoyer-courriels.php.
 */

declare(strict_types=1);

/** Page « Courriels ». */
function admin_courriels(): void
{
    require_admin();
    $compte = current_account();

    render('admin/courriels', [
        'titre'     => 'Courriels',
        // 1.28.0 : page masquée du menu, mais on peut encore y arriver par une
        // adresse directe : elle dit alors franchement que tout est coupé.
        'desactive' => !site_courriels_actifs(),
        'configure' => courriel_configure(),
        'raison'    => courriel_raison_non_configuree(),
        'plafond'   => courriel_plafond_jour(),
        'reponse'   => courriel_adresse_reponse(),
        'compteurs' => courriel_compteurs(),
        'derniers'  => courriel_derniers(50),
        'monAdresse' => (string) ($compte['email'] ?? ''),
        'expediteur' => courriel_config()['from'],
        'serveur'    => courriel_config()['host'] !== ''
            ? courriel_config()['host'] . ':' . courriel_config()['port']
            : '',
    ], 'layout_admin');
}

/** Enregistre le plafond et l'adresse de réponse. */
function admin_courriels_reglages(): void
{
    require_admin();
    csrf_check();

    // 1.28.0 : rien ne se règle et rien ne part quand l'envoi est coupé.
    if (!site_courriels_actifs()) {
        flash('erreur', 'Les courriels sont désactivés : réactivez-les dans « Réglages » avant de régler ou d’envoyer quoi que ce soit.');
        redirect('/admin/reglages');
    }

    $plafond = (int) post('plafond');
    if ($plafond < 1 || $plafond > 500) {
        form_fail(['Le plafond doit être un nombre entre 1 et 500 courriels par jour.'], [], '/admin/courriels');
    }

    $reponse = courriel_adresse_normaliser(post('reponse'));
    if ($reponse !== '' && !courriel_adresse_valide($reponse)) {
        form_fail(['L’adresse de réponse n’est pas valide (laissez vide pour ne pas en mettre).'], [], '/admin/courriels');
    }

    courriel_regler_plafond($plafond);
    save_site_setting('courriel_reponse', $reponse);

    log_activity((int) current_account()['id'], 'courriels_reglages',
        'Plafond ' . $plafond . '/jour, réponse ' . ($reponse !== '' ? courriel_masquer($reponse) : 'aucune'));

    flash('succes', 'Les réglages des courriels sont enregistrés.');
    redirect('/admin/courriels');
}

/** Envoie un courriel d'essai à l'administrateur connecté (et à lui seul). */
function admin_courriels_essai(): void
{
    require_admin();
    csrf_check();

    // 1.28.0 : rien ne se règle et rien ne part quand l'envoi est coupé.
    if (!site_courriels_actifs()) {
        flash('erreur', 'Les courriels sont désactivés : réactivez-les dans « Réglages » avant de régler ou d’envoyer quoi que ce soit.');
        redirect('/admin/reglages');
    }

    $compte  = current_account();
    $adresse = courriel_adresse_normaliser((string) ($compte['email'] ?? ''));

    if ($adresse === '') {
        form_fail(
            ['Renseignez d’abord votre propre adresse dans « Mon compte », puis revenez ici.'],
            [],
            '/admin/courriels'
        );
    }
    if (!courriel_configure()) {
        form_fail([courriel_raison_non_configuree()], [], '/admin/courriels');
    }

    $rendu = courriel_habiller('Courriel d’essai', [
        'Ce message vient d’être envoyé depuis l’administration du site.',
        'S’il est arrivé dans votre boîte, l’envoi fonctionne : la famille pourra recevoir '
            . 'les nouveaux articles et la gazette du mois.',
        'Si vous ne le voyez pas, regardez dans les indésirables, puis vérifiez les réglages '
            . 'du serveur d’envoi dans config.local.php.',
    ], [
        'lien'           => courriel_lien_preferences(),
        'libelle_lien'   => 'Gérer mes préférences',
        'desinscription' => courriel_lien_desinscription((int) $compte['id'], 'articles'),
        'sur_titre'      => 'Essai d’envoi',
    ]);

    $resultat = courriel_envoyer([
        'recipient'      => $adresse,
        'subject'        => 'Essai d’envoi — ' . (string) (site_setting('name') !== '' ? site_setting('name') : 'Histoire de famille'),
        'text'           => $rendu['text'],
        'html'           => $rendu['html'],
        'desinscription' => courriel_lien_desinscription((int) $compte['id'], 'articles'),
    ]);

    log_activity((int) $compte['id'], 'courriel_essai',
        'Essai vers ' . courriel_masquer($adresse) . ' : ' . ($resultat['ok'] ? 'envoyé' : 'échec'));

    if ($resultat['ok']) {
        flash('succes', 'Le courriel d’essai est parti vers ' . courriel_masquer($adresse)
            . '. Il peut mettre une minute à arriver.');
    } else {
        flash('erreur', 'L’essai a échoué : ' . $resultat['message']);
    }

    redirect('/admin/courriels');
}
