<?php
/**
 * Administration → Lieux à placer.
 *
 * Le site ne devine aucun lieu : un texte de lieu (« Toulouse », « Saint-
 * Étienne ») reste tel quel dans les fiches. Cette page sert seulement à lui
 * donner des coordonnées, une fois, en choisissant une commune dans la liste
 * ou en saisissant la latitude et la longitude à la main.
 */

declare(strict_types=1);

/** Liste des textes de lieu qui n'ont pas encore de coordonnées. */
function admin_lieux(): void
{
    require_admin();

    // Carte fermée : cette page n'a plus lieu d'être. On renvoie vers le
    // réglage qui la rouvre, avec la phrase exacte (1.32.0).
    if (!carte_active()) {
        flash('info', 'La carte est désactivée. Cochez « Carte des lieux » pour la rouvrir.');
        redirect('/admin/reglages');
        return;
    }

    $aPlacer = carte_lieux_a_placer();
    $places  = db_all('SELECT * FROM places ORDER BY nom COLLATE NOCASE');

    $recherche = trim((string) ($_GET['q'] ?? ''));
    $suggestions = $recherche !== '' ? carte_suggestions($recherche, 10) : [];

    render('admin/lieux', [
        'titre'       => 'Lieux à placer',
        'aPlacer'     => $aPlacer,
        'places'      => $places,
        'recherche'   => mb_substr($recherche, 0, 80),
        'suggestions' => $suggestions,
        'source'      => carte_fond()['source'] ?? 'Natural Earth',
    ], 'layout_admin');
}

/** Enregistre un lieu (coordonnées choisies ou saisies à la main). */
function admin_lieux_placer(): void
{
    require_admin();
    csrf_check();

    $nom  = (string) post('nom');
    $pays = (string) post('pays');
    $lat  = post('lat');
    $lon  = post('lon');
    $code = (string) post('code');

    if (!carte_coordonnees_valides($lat, $lon)) {
        form_fail(
            ['La latitude doit être comprise entre -90 et 90, et la longitude entre -180 et 180. '
                . 'Par exemple : 43.6047 et 1.4442 pour Toulouse.'],
            ['nom' => $nom, 'pays' => $pays, 'lat' => (string) $lat, 'lon' => (string) $lon],
            '/admin/lieux'
        );
        return;
    }

    $id = carte_place_enregistrer($nom, $pays, $lat, $lon, $code);
    if ($id === 0) {
        form_fail(['Le nom du lieu est obligatoire (120 caractères au plus).'], ['nom' => $nom], '/admin/lieux');
        return;
    }

    log_activity((int) current_account()['id'], 'lieu_place', 'Lieu placé sur la carte : ' . $nom);
    flash('succes', '« ' . $nom . ' » est maintenant sur la carte.');
    redirect('/admin/lieux');
}

/** Retire un lieu de la carte (les textes des fiches ne bougent pas). */
function admin_lieux_supprimer(): void
{
    require_admin();
    csrf_check();

    $id    = (int) post('id');
    $place = carte_place($id);
    if ($place === null) {
        flash('erreur', 'Ce lieu n’existe pas.');
        redirect('/admin/lieux');
    }

    carte_place_supprimer($id);
    log_activity((int) current_account()['id'], 'lieu_retire', 'Lieu retiré de la carte : ' . $place['nom']);

    flash('succes', '« ' . $place['nom'] . ' » n’apparaît plus sur la carte. Les textes des fiches sont inchangés.');
    redirect('/admin/lieux');
}
