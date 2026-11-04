<?php
/**
 * Administration : conversion des vidéos en MP4.
 *
 * Trois routes, réservées aux administrateurs :
 *
 *   GET  /admin/conversions              la file d'attente et son état ;
 *   POST /admin/conversions/relancer     remet un travail en attente ;
 *   POST /admin/conversions/annuler      arrête un travail (l'original reste) ;
 *   POST /admin/conversions/planifier    inscrit les vidéos pas encore prévues.
 *
 * Aucune de ces pages ne convertit quoi que ce soit pendant la visite : un
 * Raspberry Pi mettrait des dizaines de minutes, et la requête serait coupée.
 * Le travail est fait par la commande app/cli/convertir.php, lancée par un
 * minuteur systemd. Ces pages ne font que remplir et surveiller la file.
 */

declare(strict_types=1);

/** Page « Conversions » : file d'attente, état, outils, réglages. */
function admin_conversions(): void
{
    require_admin();

    $filtre = query('etat');
    $where  = '';
    $params = [];
    if (in_array($filtre, conversion_etats(), true)) {
        $where = 'WHERE c.state = :etat';
        $params[':etat'] = $filtre;
    }

    $total = (int) db_value('SELECT COUNT(*) FROM conversions c ' . $where, $params, 0);
    $pg    = pagination($total, 0, max(1, query_int('page', 1)));

    $lignes = db_all(
        "SELECT c.*, d.title, d.kind, d.visibility, d.duration_seconds AS doc_duration
         FROM conversions c
         JOIN documents d ON d.id = c.document_id
         {$where}
         ORDER BY CASE c.state WHEN 'en_cours' THEN 0 WHEN 'attente' THEN 1 ELSE 2 END,
                  c.updated_at DESC
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    // Vidéos qui pourraient entrer dans la file (utile après l'installation de
    // ffmpeg, ou après avoir activé la conversion).
    $aPlanifier = 0;
    foreach (db_all("SELECT * FROM documents WHERE kind = 'video' AND is_attachment = 0") as $doc) {
        if (conversion_necessaire($doc) && conversion_pour_document((int) $doc['id']) === null) {
            $aPlanifier++;
        }
    }

    render('admin/conversions', [
        'titre'      => 'Conversions',
        'lignes'     => $lignes,
        'pagination' => $pg,
        'total'      => $total,
        'filtre'     => $filtre,
        'resume'     => conversion_resume(),
        'reglages'   => conversion_reglages(),
        'aPlanifier' => $aPlanifier,
        'derniere'   => db_one("SELECT MAX(finished_at) AS t FROM conversions WHERE state = 'terminee'"),
    ], 'layout_admin');
}

/** Remet un travail en attente (bouton « Relancer »). */
function admin_conversion_relancer(array $params): void
{
    require_admin();
    csrf_check();

    $resultat = conversion_relancer((int) ($params['id'] ?? 0));
    log_activity(
        (int) current_account()['id'],
        'conversion_relancee',
        'Travail ' . (int) ($params['id'] ?? 0)
    );

    flash($resultat['ok'] ? 'succes' : 'erreur', $resultat['ok']
        ? 'La conversion a été remise dans la file : elle partira au prochain passage du minuteur.'
        : $resultat['error']);

    redirect('/admin/conversions');
}

/** Annule un travail (le fichier actuel de la fiche reste en place). */
function admin_conversion_annuler(array $params): void
{
    require_admin();
    csrf_check();

    $resultat = conversion_annuler((int) ($params['id'] ?? 0));
    log_activity(
        (int) current_account()['id'],
        'conversion_annulee',
        'Travail ' . (int) ($params['id'] ?? 0)
    );

    flash($resultat['ok'] ? 'succes' : 'erreur', $resultat['ok']
        ? 'La conversion est annulée : la vidéo reste telle quelle.'
        : $resultat['error']);

    redirect('/admin/conversions');
}

/** Inscrit dans la file toutes les vidéos qui ne l'étaient pas encore. */
function admin_conversion_planifier(): void
{
    require_admin();
    csrf_check();

    $compte = (int) current_account()['id'];
    $ajouts = 0;
    $erreurs = 0;

    foreach (db_all("SELECT * FROM documents WHERE kind = 'video' AND is_attachment = 0") as $doc) {
        $id = (int) $doc['id'];
        if (!conversion_necessaire($doc) || conversion_pour_document($id) !== null) {
            continue;
        }
        $resultat = conversion_planifier($id, $compte);
        if ($resultat['ok'] && !$resultat['ignore']) {
            $ajouts++;
        } elseif (!$resultat['ok']) {
            $erreurs++;
        }
    }

    log_activity($compte, 'conversions_planifiees', $ajouts . ' vidéo(s)');

    if ($ajouts === 0 && $erreurs === 0) {
        flash('info', 'Aucune vidéo à ajouter : toutes celles qui ont besoin de l’être sont déjà dans la file.');
    } else {
        flash('succes', $ajouts . ' vidéo(s) ajoutée(s) à la file'
            . ($erreurs > 0 ? ', ' . $erreurs . ' en erreur (fichier introuvable ?)' : '')
            . '. Le minuteur s’en occupe un fichier à la fois.');
    }

    redirect('/admin/conversions');
}
