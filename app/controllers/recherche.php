<?php
/**
 * Recherche générale : documents, articles, pages d'information et personnes.
 *
 * Une seule page (/recherche) ; le filtre `type` permet d'afficher une seule
 * catégorie avec une pagination complète. Les droits sont appliqués par les
 * mêmes fonctions que les listes : un visiteur ne trouve que le public, un
 * compte de famille ce qui lui est ouvert, un administrateur tout.
 */

declare(strict_types=1);

/** Page de recherche (aperçu par catégorie, ou liste complète d'un type). */
function public_recherche(): void
{
    $q = trim(query('q'));
    // Une recherche très longue ne sert à rien et ralentirait la base.
    if (mb_strlen($q, 'UTF-8') > 100) {
        $q = mb_substr($q, 0, 100, 'UTF-8');
    }

    $types = recherche_types();
    $type  = query('type');
    if (!isset($types[$type])) {
        $type = '';
    }

    $mots      = recherche_mots($q);
    $resultats = [];
    $totaux    = [];
    $total     = 0;
    $pg        = null;

    if ($mots !== []) {
        if ($type === '') {
            // Aperçu : quelques résultats par catégorie.
            $apercu    = recherche_tout($mots, 5);
            $resultats = $apercu['resultats'];
            $totaux    = $apercu['totaux'];
            $total     = recherche_total($totaux);
        } else {
            // Une seule catégorie : liste complète, paginée.
            $fonction = 'recherche_' . $type;
            $fonction($mots, 0, 0, $total);
            $pg = pagination($total, 0, max(1, query_int('page', 1)));
            $resultats[$type] = $fonction($mots, $pg['perPage'], $pg['offset']);
            $totaux[$type]    = $total;
        }
    }

    render('public/recherche', [
        'titre'     => $q !== '' ? 'Recherche : ' . $q : 'Recherche',
        'q'         => $q,
        'mots'      => $mots,
        'type'      => $type,
        'types'     => $types,
        'resultats' => $resultats,
        'totaux'    => $totaux,
        'total'     => $total,
        'pagination' => $pg,
    ]);
}
