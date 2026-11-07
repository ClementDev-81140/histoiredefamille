<?php
/**
 * Administration → « Composition des pages ».
 *
 * Deux pages se règlent ici : l'accueil public (`/`) et l'espace famille
 * (`/famille`). Pour chacune, les administrateurs affichent ou masquent des
 * blocs et les font monter ou descendre ; un bloc ne quitte jamais sa
 * colonne. Trois réglages de « la une » et le modèle d'article par défaut
 * sont sur la même page.
 *
 * Tout passe par des formulaires POST avec un jeton CSRF, sans JavaScript :
 * les boutons fonctionnent même si le script ne charge pas.
 */

declare(strict_types=1);

/** Page « Composition des pages ». */
function admin_composition(): void
{
    require_admin();

    $pages = [];
    foreach (array_keys(composition_pages()) as $cle) {
        $pages[$cle] = [
            'nom'    => composition_pages()[$cle],
            'blocs'  => composition_blocs($cle, true),
            'colonnes' => composition_groupes($cle),
        ];
    }

    render('admin/composition', [
        'titre'         => 'Composition des pages',
        'pages'         => $pages,
        'catalogue'     => [
            'public'  => composition_catalogue('public'),
            'famille' => composition_catalogue('famille'),
        ],
        'colonnes'      => composition_colonnes_libelles(),
        'erreurs'       => form_errors(),
        'old'           => form_old(),
        'colonnesUne'   => composition_nb_colonnes(),
        'resume'        => composition_resume_choisi(),
        'unePhoto'      => composition_une_photo(),
        'modeleDefaut'  => site_setting('apparence_modele_defaut'),
        'modeles'       => article_modeles(),
        // La une du site public (1.31.0) : choix parmi les contenus publics.
        'unePublique'   => site_setting('une_publique'),
        'uneCandidats'  => une_publique_candidats(40),
    ], 'layout_admin');
}

/** Monter, descendre, afficher ou masquer un bloc. */
function admin_composition_action(): void
{
    require_admin();
    csrf_check();

    $page   = post('page');
    $bloc   = post('bloc');
    $action = post('action');

    if (!array_key_exists($page, composition_pages())) {
        flash('erreur', 'Page inconnue.');
        redirect('/admin/composition');
    }

    $message = '';
    if ($action === 'monter' || $action === 'descendre') {
        $sens   = $action === 'monter' ? -1 : 1;
        $bouge  = composition_deplacer($page, $bloc, $sens);
        $message = $bouge
            ? 'Le bloc « ' . composition_nom($page, $bloc) . ' » a été déplacé.'
            : 'Ce bloc est déjà tout en haut ou tout en bas de sa colonne.';
    } elseif ($action === 'basculer') {
        $affiche = composition_basculer($page, $bloc);
        $message = $affiche
            ? 'Le bloc « ' . composition_nom($page, $bloc) . ' » est de nouveau affiché.'
            : 'Le bloc « ' . composition_nom($page, $bloc) . ' » est masqué.';
    } else {
        flash('erreur', 'Action inconnue.');
        redirect('/admin/composition');
    }

    log_activity(
        (int) current_account()['id'],
        'composition',
        'Composition de « ' . composition_pages()[$page] . ' » : '
        . composition_nom($page, $bloc) . ' (' . $action . ')'
    );
    flash('succes', $message);
    redirect('/admin/composition');
}

/** Réglages de la une et modèle d'article par défaut. */
function admin_composition_reglages(): void
{
    require_admin();
    csrf_check();

    [$valeurs, $erreurs] = composition_lire_reglages($_POST);
    if ($erreurs !== []) {
        form_fail($erreurs, $_POST, '/admin/composition');
    }

    composition_enregistrer_reglages($valeurs);
    composition_blocs('public', true);

    log_activity(
        (int) current_account()['id'],
        'composition',
        'Réglages de la une : ' . $valeurs['apparence_une_colonnes'] . ' article(s) en colonnes, '
        . 'résumé ' . $valeurs['apparence_une_resume'] . ', photo '
        . ($valeurs['apparence_une_photo'] === '1' ? 'affichée' : 'masquée')
        . ', à la une du site public : '
        . ($valeurs['une_publique'] === '' ? 'automatique' : $valeurs['une_publique'])
    );
    flash('succes', 'Les réglages de la une ont été enregistrés.');
    redirect('/admin/composition');
}

/** « Rétablir la composition d'origine ». */
function admin_composition_retablir(): void
{
    require_admin();
    csrf_check();

    composition_retablir();
    composition_retablir_reglages();

    log_activity(
        (int) current_account()['id'],
        'composition',
        'Composition des pages remise à l’origine (blocs, réglages de la une et modèle par défaut)'
    );
    flash('succes', 'La composition d’origine est rétablie : tous les blocs sont affichés, dans leur ordre d’origine.');
    redirect('/admin/composition');
}
