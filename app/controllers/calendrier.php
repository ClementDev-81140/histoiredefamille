<?php
/**
 * Calendrier familial (un mois, toutes les années) et frise chronologique
 * (une période de cinquante ans).
 *
 * Les deux pages n'affichent que ce que la personne connectée a le droit de
 * voir : les mêmes règles de visibilité que l'arbre.
 */

declare(strict_types=1);

/** Calendrier d'un mois : naissances, mariages et décès. */
function public_calendrier(): void
{
    $aujourdhui = getdate();
    $annee      = (int) $aujourdhui['year'];
    $mois       = (int) $aujourdhui['mon'];

    // ?mois=1928-06 ou ?mois=2026-11
    $demande = query('mois');
    if (preg_match('/^(\d{4})-(\d{1,2})$/', $demande, $m)) {
        $anneeDemandee = (int) $m[1];
        $moisDemande   = (int) $m[2];
        if ($anneeDemandee >= 1000 && $anneeDemandee <= 2199 && $moisDemande >= 1 && $moisDemande <= 12) {
            $annee = $anneeDemandee;
            $mois  = $moisDemande;
        }
    }

    [$anneePrec, $moisPrec] = calendrier_mois_voisin($annee, $mois, -1);
    [$anneeSuiv, $moisSuiv] = calendrier_mois_voisin($annee, $mois, 1);

    render('public/calendrier', [
        'titre'      => 'Calendrier — ' . calendrier_mois_nom($mois) . ' ' . $annee,
        'annee'      => $annee,
        'mois'       => $mois,
        'grille'     => calendrier_grille($annee, $mois),
        'evenements' => calendrier_evenements($mois),
        'precedent'  => sprintf('%04d-%02d', $anneePrec, $moisPrec),
        'suivant'    => sprintf('%04d-%02d', $anneeSuiv, $moisSuiv),
        'moisCourant' => $mois === (int) $aujourdhui['mon'] && $annee === (int) $aujourdhui['year'],
    ]);
}

// ---------------------------------------------------------------------------
// Ajouter / proposer un événement depuis le calendrier (1.34.0)
// ---------------------------------------------------------------------------

/**
 * Formulaire simple d'ajout d'événement, depuis /calendrier.
 *
 * Un administrateur publie directement (avec la visibilité choisie, privée
 * par défaut) ; un journaliste PROPOSE : son événement naît « en attente »
 * et n'apparaît nulle part tant qu'un administrateur ne l'a pas validé
 * dans Administration → Calendrier. Un lecteur reçoit 403, un visiteur est
 * renvoyé vers la connexion (require_proposer, comme /propositions).
 */
function public_calendrier_ajouter(): void
{
    require_proposer();
    $compte = current_account();

    render('public/calendrier_ajouter', [
        'titre'     => is_admin() ? 'Ajouter un événement' : 'Proposer un événement',
        'estAdmin'  => is_admin(),
        'enAttente' => evenements_en_attente_de((int) $compte['id']),
        'branches'  => branches_list(true),
        'erreurs'   => form_errors(),
        'old'       => form_old(),
    ]);
}

/** Enregistrement depuis /calendrier/ajouter (administrateur ou journaliste). */
function public_calendrier_ajouter_save(): void
{
    require_proposer();
    csrf_check();

    [$valeurs, $erreurs] = evenement_valider($_POST);
    if ($erreurs !== []) {
        form_fail($erreurs, $valeurs, '/calendrier/ajouter');
    }

    $compte = current_account();
    if (is_admin()) {
        $id = evenement_creer($valeurs, (int) $compte['id'], 'publie');
        log_activity((int) $compte['id'], 'evenement_ajoute', (string) $valeurs['title'] . ' (' . (string) $valeurs['start_date'] . ')');
        flash('succes', 'L’événement a été ajouté au calendrier.');
        $mois = substr((string) $valeurs['start_date'], 0, 7);
        redirect('/calendrier?mois=' . $mois);
    }

    $id = evenement_creer($valeurs, (int) $compte['id'], 'en_attente');
    log_activity((int) $compte['id'], 'evenement_propose', (string) $valeurs['title'] . ' (' . (string) $valeurs['start_date'] . ')');
    flash('succes', 'Votre événement a été proposé : il apparaîtra après validation par un administrateur.');
    redirect('/calendrier/ajouter');
}

/** Frise chronologique d'une période de cinquante ans. */
function public_frise(): void
{
    [$borneDebut, $borneFin] = frise_bornes();

    $debut = query_int('debut', 0);
    if ($debut <= 0) {
        // Par défaut : la période qui contient les années les plus récentes.
        $debut = (int) (floor(($borneFin - 49) / 50) * 50);
    }
    $debut = max(1000, min($debut, 2150));
    $fin   = $debut + 49;

    $evenements = frise_evenements($debut, $fin, 400);

    // Regroupement par année, dans l'ordre.
    $parAnnee = [];
    foreach ($evenements as $evenement) {
        $parAnnee[$evenement['annee']][] = $evenement;
    }
    ksort($parAnnee);

    render('public/frise', [
        'titre'     => 'Frise chronologique — ' . frise_periode_label($debut, $fin),
        'debut'     => $debut,
        'fin'       => $fin,
        'parAnnee'  => $parAnnee,
        'total'     => count($evenements),
        'borneDebut' => $borneDebut,
        'borneFin'   => $borneFin,
        'precedent'  => $debut - 50 >= max(1000, $borneDebut) ? $debut - 50 : null,
        'suivant'    => $debut + 50 <= $borneFin ? $debut + 50 : null,
        'premiere'   => $debut > $borneDebut ? (int) (floor($borneDebut / 50) * 50) : null,
    ]);
}
