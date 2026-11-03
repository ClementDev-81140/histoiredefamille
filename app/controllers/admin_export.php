<?php
/**
 * Export de l'arbre généalogique au format GEDCOM 5.5.1.
 *
 * Fichier lisible par la plupart des logiciels de généalogie (Gramps, Ancestry,
 * MyHeritage, Geneanet…). Réservé aux administrateurs : il contient toutes les
 * fiches, y compris celles qui ne sont pas publiques.
 */

declare(strict_types=1);

/** Téléchargement du fichier .ged (écrit au fur et à mesure, sans tout garder en mémoire). */
function admin_export_gedcom(): void
{
    require_admin();

    $fichier = 'histoire-famille-' . date('Y-m-d') . '.ged';

    // Les tests d'accès appellent la page dans le même processus : ils
    // gardent la sortie en tampon au lieu de l'envoyer au navigateur.
    if (!class_exists('HdfTestArret', false)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fichier . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    gedcom_export_stream();

    log_activity(
        (int) current_account()['id'],
        'gedcom_exporte',
        $fichier . ' — ' . (int) db_value('SELECT COUNT(*) FROM persons', [], 0) . ' personnes'
    );

    hdf_terminer();
}
