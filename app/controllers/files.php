<?php
/**
 * Diffusion des fichiers.
 *
 * Aucun fichier du stockage n'est accessible directement : chaque requête
 * passe ici et vérifie les droits avant d'envoyer le contenu.
 */

declare(strict_types=1);

/** Envoie une réponse « non trouvé » sans révéler l'existence du fichier. */
function file_not_found(string $message = 'Fichier introuvable.'): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo $message;
}

/** Consultation d'un document (PDF et images dans le navigateur). */
function file_inline(array $params): void
{
    $doc = find_viewable_document((int) ($params['id'] ?? 0));
    if (!$doc) {
        file_not_found();
        return;
    }
    send_file($doc, false);
}

/** Téléchargement d'un document (Content-Disposition: attachment). */
function file_download(array $params): void
{
    $doc = find_viewable_document((int) ($params['id'] ?? 0));
    if (!$doc) {
        file_not_found();
        return;
    }

    // Compteur de téléchargements (une erreur d'écriture ne bloque pas l'envoi).
    try {
        db_exec('UPDATE documents SET downloaded_count = downloaded_count + 1 WHERE id = ?', [(int) $doc['id']]);
    } catch (Throwable) {
    }

    send_file($doc, true);
}

/** Miniature d'une photo. */
function file_thumbnail(array $params): void
{
    $doc = find_viewable_document((int) ($params['id'] ?? 0));
    if (!$doc || empty($doc['thumb_path'])) {
        file_not_found('Miniature indisponible.');
        return;
    }

    $path = storage_absolute((string) $doc['thumb_path']);
    if (!is_file($path)) {
        file_not_found('Miniature indisponible.');
        return;
    }

    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    readfile($path);
}

/** Lecture d'une vidéo, avec gestion des requêtes Range (avance rapide). */
function file_video(array $params): void
{
    $doc = find_viewable_document((int) ($params['id'] ?? 0));
    if (!$doc || ($doc['kind'] ?? '') !== 'video') {
        file_not_found('Vidéo introuvable.');
        return;
    }
    send_file($doc, false);
}
