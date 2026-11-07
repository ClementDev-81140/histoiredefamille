<?php
/**
 * Tests de la 1.32.0 — image d'aperçu des vidéos.
 *
 * Ce qui est vérifié ici :
 *   - l'instant choisi (1 s, ou 10 % de la durée pour un film très court) ;
 *   - la commande ffmpeg, construite en tableau (aucun passage par le shell) ;
 *   - l'absence de ffmpeg : pas d'image, un message clair, rien de cassé ;
 *   - les droits : la miniature suit EXACTEMENT la visibilité de la vidéo ;
 *   - l'affichage de repli : une icône de lecture, jamais un carré gris.
 *
 * L'extraction réelle d'une image ne peut pas être jouée dans la suite : elle
 * demande ffmpeg. Les essais ci-dessous n'en ont pas besoin.
 */

declare(strict_types=1);

/** Carte d'une vidéo rendue par le vrai gabarit. */
function tests_miniature_carte(array $video): string
{
    ob_start();
    require dirname(__DIR__) . '/views/partials/carte_video.php';
    return (string) ob_get_clean();
}

if ($filtre === '' || $filtre === 'miniatures_videos') {
    echo "Miniatures des vidéos\n";

    // 1. L'instant de l'image.
    verifier(
        'miniature vidéo : l’image est prise à 1 seconde',
        video_miniature_position(null) === 1.0 && video_miniature_position(120) === 1.0
    );
    verifier(
        'miniature vidéo : pour une vidéo de moins de 10 s, l’image est prise à 10 % de la durée',
        video_miniature_position(6) === 0.6 && video_miniature_position(10) === 1.0
            && video_miniature_position(2) === 0.2
    );

    // 2. La commande : un tableau, aucun shell, la bonne position.
    $commande = video_miniature_commande('/usr/bin/ffmpeg', '/media/film.mp4', '/tmp/x.jpg', 1.0);
    $texte    = implode(' ', $commande);
    verifier(
        'miniature vidéo : la commande ffmpeg est un tableau (aucun passage par le shell)',
        is_array($commande) && $commande[0] === '/usr/bin/ffmpeg'
            && in_array('-frames:v', $commande, true) && in_array('1', $commande, true)
    );
    verifier(
        'miniature vidéo : ffmpeg reçoit la position, l’échelle des miniatures et le fichier de sortie',
        str_contains($texte, '-ss 1.00') && str_contains($texte, 'scale=' . VIDEO_MINIATURE_LARGEUR . ':-2')
            && str_contains($texte, '/tmp/x.jpg')
    );

    $pdo = db();
    $transaction = !$pdo->inTransaction();
    if ($transaction) {
        $pdo->beginTransaction();
    }
    $sessionAvant = $_SESSION;

    try {
        $maintenant = time();
        $videoId = db_insert('documents', [
            'title' => 'Vidéo sans image (essai)', 'kind' => 'video', 'visibility' => 'prive',
            'rel_path' => 'essai/video-essai.mp4', 'original_name' => 'video-essai.mp4',
            'mime' => 'video/mp4', 'ext' => 'mp4', 'size' => 1024,
            'duration_seconds' => 6, 'is_attachment' => 0,
            'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);

        // 3. Sans ffmpeg : rien n'est créé, et le message dit quoi faire.
        conversion_outils_forcer(['ffmpeg' => null, 'ffprobe' => null]);
        $sansFfmpeg = video_miniature_document($videoId);
        verifier(
            'miniature vidéo : sans ffmpeg, aucune image n’est créée et le message est clair',
            $sansFfmpeg['ok'] === false && $sansFfmpeg['absent'] === true
                && str_contains($sansFfmpeg['error'], 'ffmpeg n’est pas installé')
                && trim((string) db_value('SELECT thumb_path FROM documents WHERE id = ?', [$videoId], '')) === ''
        );
        conversion_outils_forcer(null);

        // 4. Les droits : la miniature suit exactement la visibilité de la vidéo.
        $fichier = storage_absolute(storage_target_thumb_path());
        @mkdir(dirname($fichier), 0770, true);
        file_put_contents($fichier, "\xFF\xD8\xFF\xDBminiature d'essai");
        db_exec('UPDATE documents SET thumb_path = ? WHERE id = ?', [
            substr($fichier, strlen(rtrim((string) config('storage.root'), '/')) + 1), $videoId,
        ]);
        $relThumb = (string) db_value('SELECT thumb_path FROM documents WHERE id = ?', [$videoId], '');
        $adminId  = (int) db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], 0);

        $visiteur = matrice_requete('GET', '/media/miniature/' . $videoId, null);
        $_SESSION['account_id'] = $adminId;
        $admin = matrice_requete('GET', '/media/miniature/' . $videoId, $adminId);
        verifier(
            'miniature vidéo : une vidéo privée ne livre sa miniature à aucun visiteur (404, comme « introuvable »)',
            $visiteur['code'] === 404
        );
        verifier(
            'miniature vidéo : l’administrateur, lui, reçoit bien la miniature',
            $admin['code'] === 200
        );

        // Une vidéo publique : la miniature suit, sans rien ouvrir d'autre.
        db_exec("UPDATE documents SET visibility = 'public' WHERE id = ?", [$videoId]);
        $visiteurPublic = matrice_requete('GET', '/media/miniature/' . $videoId, null);
        verifier(
            'miniature vidéo : dès que la vidéo est publique, sa miniature l’est aussi',
            $visiteurPublic['code'] === 200
        );

        // 5. L'affichage de repli : une icône de lecture sur le papier.
        $video = db_one('SELECT * FROM documents WHERE id = ?', [$videoId]);
        $video['thumb_path'] = null;
        $carte = tests_miniature_carte($video);
        verifier(
            'miniature vidéo : sans image, la carte affiche une icône de lecture et aucun carré « Vidéo »',
            str_contains($carte, 'carte__apercu--sans-image')
                && str_contains($carte, 'carte__lecture')
                && !str_contains($carte, 'carte__type')
        );
        $carteAvec = tests_miniature_carte(db_one('SELECT * FROM documents WHERE id = ?', [$videoId]));
        verifier(
            'miniature vidéo : avec une image, la carte affiche la photo et pas l’icône de repli',
            str_contains($carteAvec, '<img') && !str_contains($carteAvec, 'carte__apercu--sans-image')
        );

        // 6. La commande de rattrapage existe et liste une seule fois chaque vidéo.
        $commande = (string) file_get_contents(dirname(__DIR__) . '/cli/miniatures-videos.php');
        verifier(
            'miniature vidéo : la commande de rattrapage existe et ne traite qu’une vidéo à la fois',
            is_file(dirname(__DIR__) . '/cli/miniatures-videos.php')
                && str_contains($commande, '--executer')
                && str_contains($commande, '$lot = 1;')
                && str_contains($commande, 'video_miniature_a_faire')
                && str_contains($commande, 'relancez la commande')
        );
        db_exec('UPDATE documents SET thumb_path = NULL WHERE id = ?', [$videoId]);
        $aFaire = array_column(video_miniature_a_faire(500), 'id');
        verifier(
            'miniature vidéo : la liste de rattrapage contient la vidéo sans image, une seule fois',
            in_array($videoId, array_map('intval', $aFaire), true)
                && count(array_keys($aFaire, $videoId)) === 1
        );

        // 7. La page Conversions parle des images manquantes quand ffmpeg manque.
        $outils = conversion_outils();
        $page   = matrice_requete('GET', '/admin/conversions', $adminId);
        verifier(
            'miniature vidéo : la page Conversions explique le manque d’images quand ffmpeg est absent',
            $page['code'] === 200
                && ($outils['ffmpeg'] !== null || str_contains($page['corps'], 'n’ont pas d’image d’aperçu'))
        );
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (isset($fichier) && is_file($fichier)) {
            @unlink($fichier);
        }
        conversion_outils_forcer(null);
        $_SESSION = $sessionAvant;
    }
}
