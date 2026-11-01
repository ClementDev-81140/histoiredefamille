<?php
/**
 * Administration : journal des connexions et alertes de sécurité.
 */

declare(strict_types=1);

/** Page du journal (filtres : statut, période, recherche). */
function admin_connexions(): void
{
    require_admin();

    $statut  = query('statut');
    $periode = query('periode');
    $q       = mb_substr(query('q'), 0, 60, 'UTF-8');

    $where  = ['1 = 1'];
    $params = [];

    switch ($statut) {
        case 'echecs':
            $where[] = 'l.success = 0';
            break;
        case 'succes':
            $where[] = 'l.success = 1';
            break;
        case 'nouvelle_ip':
            $where[] = 'l.new_ip = 1';
            break;
        default:
            $statut = '';
    }

    $durees = ['24h' => 86400, '7j' => 7 * 86400, '30j' => 30 * 86400];
    if (isset($durees[$periode])) {
        $where[]               = 'l.created_at > :depuis';
        $params[':depuis']     = time() - $durees[$periode];
    } else {
        $periode = '';
    }

    if ($q !== '') {
        $where[]       = '(l.identifier LIKE :q OR l.ip LIKE :q)';
        $params[':q']  = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);
    $total    = (int) db_value("SELECT COUNT(*) FROM login_log l WHERE {$whereSql}", $params, 0);
    $pg       = pagination($total, 50, max(1, query_int('page', 1)));

    $lignes = db_all(
        "SELECT l.*, a.username, a.role FROM login_log l
         LEFT JOIN accounts a ON a.id = l.account_id
         WHERE {$whereSql}
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    $jour = time() - 86400;
    render('admin/connexions', [
        'titre'      => 'Journal des connexions',
        'lignes'     => $lignes,
        'pagination' => $pg,
        'total'      => $total,
        'statut'     => $statut,
        'periode'    => $periode,
        'q'          => $q,
        'resume'     => [
            'succes' => (int) db_value('SELECT COUNT(*) FROM login_log WHERE success = 1 AND created_at > ?', [$jour], 0),
            'echecs' => (int) db_value('SELECT COUNT(*) FROM login_log WHERE success = 0 AND created_at > ?', [$jour], 0),
        ],
        'alertes'    => journal_alertes(),
        'conservation' => journal_conservation_jours(),
    ], 'layout_admin');
}

/** « J'ai pris connaissance » : masque les alertes en cours. */
function admin_alertes_acquitter(): void
{
    require_admin();
    csrf_check();

    journal_acquitter();
    log_activity((int) current_account()['id'], 'alertes_acquittees', 'Alertes de connexion marquées comme lues');
    flash('succes', 'Les alertes ont été marquées comme lues. Elles réapparaîtront si un nouvel événement se produit.');

    $retour = post('retour');
    redirect($retour === 'journal' ? '/admin/connexions' : '/admin');
}

/** Durée de conservation du journal. */
function admin_connexions_reglage(): void
{
    require_admin();
    csrf_check();

    $jours = (int) post('conservation');
    if (!array_key_exists($jours, journal_conservations())) {
        flash('erreur', 'Durée de conservation non valide.');
        redirect('/admin/connexions');
    }

    save_site_setting('journal_conservation_jours', (string) $jours);
    log_activity((int) current_account()['id'], 'journal_conservation', 'Conservation du journal : ' . $jours . ' jours');
    flash('succes', 'Durée de conservation enregistrée. Le nettoyage a lieu chaque dimanche.');
    redirect('/admin/connexions');
}
