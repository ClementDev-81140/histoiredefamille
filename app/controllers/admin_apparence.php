<?php
/**
 * Administration → « Apparence du journal » : les petites lignes de la une,
 * les cases à cocher, le numéro d'édition et les couleurs.
 *
 * Tout est réservé aux administrateurs (require_admin()), en POST avec un
 * jeton CSRF, et chaque enregistrement est noté dans le journal d'activité —
 * sans recopier les textes saisis, qui n'ont rien à faire dans un journal.
 */

declare(strict_types=1);

/** Page « Apparence du journal ». */
function admin_apparence(): void
{
    require_admin();

    $valeurs = apparence_brut();
    $valeurs['nom']    = site_setting('name');
    $valeurs['devise'] = site_setting('devise');
    $valeurs['fondee'] = site_setting('fondee');

    render('admin/apparence', [
        'titre'          => 'Apparence du journal',
        'erreurs'        => form_errors(),
        'old'            => form_old(),
        'valeurs'        => $valeurs,
        'couleurs'       => apparence_couleurs(),
        'variables'      => apparence_variables(),
        'rapports'       => apparence_rapports(apparence_variables()),
        'numeroAuto'     => gazette_numero(),
        'policePresente' => gazette_police_titre(),
        'dateExemple'    => french_long_date(),
    ], 'layout_admin');
}

/** Enregistrement des réglages d'apparence (ou correction, ou retour à l'origine). */
function admin_apparence_save(): void
{
    require_admin();
    csrf_check();

    $action = post('action', 'enregistrer');

    // 1. Tout remettre comme avant.
    if ($action === 'retablir') {
        apparence_retablir();
        log_activity(
            (int) current_account()['id'],
            'apparence',
            'Apparence du journal remise à l’origine (textes, numéro et couleurs)'
        );
        flash('succes', 'Les textes et les couleurs d’origine sont rétablis.');
        redirect('/admin/apparence');
    }

    // 2. « Corriger pour moi » : on enregistre l'ajustement le plus proche.
    if ($action === 'corriger') {
        $couleurs = [
            'fond'    => (string) post('couleur_fond'),
            'texte'   => (string) post('couleur_texte'),
            'accent'  => (string) post('couleur_accent'),
            'bordure' => (string) post('couleur_bordure'),
        ];
        $corrigees = apparence_corriger($couleurs);

        save_site_setting('apparence_couleurs_mode', 'perso');
        foreach ($corrigees as $cle => $valeur) {
            save_site_setting('apparence_couleur_' . $cle, $valeur);
        }
        apparence_version_bump();
        apparence_brut(true);

        log_activity(
            (int) current_account()['id'],
            'apparence',
            'Couleurs du journal ajustées automatiquement pour respecter les contrastes'
        );
        flash(
            'succes',
            'Les couleurs ont été ajustées au plus près : le texte et l’accent sont maintenant '
            . 'assez contrastés sur le papier choisi.'
        );
        redirect('/admin/apparence');
    }

    // 3. Enregistrement normal.
    [$valeurs, $erreurs, $corrigees] = apparence_lire_saisie(
        $_POST,
        (string) post('nom'),
        (string) post('devise'),
        (string) post('fondee')
    );

    if ($erreurs !== []) {
        $anciennes = $valeurs + [
            'nom'    => (string) post('nom'),
            'devise' => (string) post('devise'),
            'fondee' => (string) post('fondee'),
        ];
        foreach ($corrigees as $cle => $valeur) {
            $anciennes['correction_' . $cle] = $valeur;
        }
        form_fail($erreurs, $anciennes, '/admin/apparence');
    }

    // Titre, devise et année de fondation : les réglages existants, réutilisés
    // tels quels (aucun doublon, « Réglages du site » les voit aussi).
    save_site_setting('name', $valeurs['nom']);
    save_site_setting('devise', $valeurs['devise']);
    save_site_setting('fondee', $valeurs['fondee']);
    unset($valeurs['nom'], $valeurs['devise'], $valeurs['fondee']);

    apparence_enregistrer($valeurs);

    log_activity(
        (int) current_account()['id'],
        'apparence',
        'Apparence du journal modifiée (textes, numéro d’édition, cases ou couleurs)'
    );
    flash('succes', 'L’apparence du journal a été enregistrée.');
    redirect('/admin/apparence');
}

/**
 * Feuille de style du thème : /theme.css.
 *
 * Une seule règle par palette, calculée à partir de valeurs validées. Le
 * numéro de version enregistré dans les réglages sert d'ETag : le navigateur
 * garde le fichier cinq minutes, puis demande simplement « a-t-il changé ? »
 * et reçoit un 304 (pas de corps, pas de travail pour le Pi).
 */
function theme_css(): void
{
    $version = apparence_version();
    $etag    = '"hdf-theme-' . $version . '"';

    if (!headers_sent()) {
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: public, max-age=300, must-revalidate');
        header('ETag: ' . $etag);
        header('Vary: Accept-Encoding');
    }

    $demande = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if (is_string($demande) && str_contains($demande, $etag)) {
        http_response_code(304);
        return;
    }

    echo apparence_css();
}
