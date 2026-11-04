<?php
/**
 * Tests de la conversion des vidéos en MP4 (lot B).
 *
 * Quatre familles :
 *   1. la file d'attente (qui entre, qui n'entre pas, une seule ligne par fiche) ;
 *   2. la prise d'un travail et les essais (atomique, trois essais, relance) ;
 *   3. la réussite (fichier remplacé, original conservé, son en M4A) ;
 *   4. les droits et l'entretien (page fermée, purge, originaux libérés).
 *
 * Les essais ne supposent rien de la machine : ffmpeg est imposé absent (ou
 * présent) par conversion_outils_forcer(), si bien qu'un serveur qui possède
 * ffmpeg obtient exactement les mêmes résultats que celui qui ne l'a pas. La
 * file doit attendre sagement au lieu de casser le site, et le lancement de la
 * commande est contrôlé par lecture du code (aucun passage par le shell).
 *
 * Tout se passe dans une transaction annulée, et les fichiers créés sont
 * effacés : la base et le stockage du site sont rendus intacts.
 */

declare(strict_types=1);

/** Fichier d'essai (contenu neutre), dans le dossier tmp du stockage. */
function tests_conversion_fichier(int $octets, string $extension): string
{
    $dossier = storage_path('tmp');
    $chemin  = $dossier . '/essai-conversion-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $handle  = fopen($chemin, 'wb');
    if ($handle !== false) {
        fwrite($handle, str_repeat('Donnée d’essai pour la conversion. ', max(1, intdiv($octets, 34) + 1)));
        fclose($handle);
    }
    return $chemin;
}

/** Faux exécutable d'essai (droit d'exécution posé), effacé par le test. */
function tests_conversion_outil(string $nom): string
{
    $chemin = storage_path('tmp') . '/essai-outil-' . $nom . '-' . bin2hex(random_bytes(4));
    file_put_contents($chemin, "#!/bin/sh\nexit 0\n");
    @chmod($chemin, 0755);
    return $chemin;
}

/**
 * Fichier .mov d'essai : véritable en-tête QuickTime (`ftyp`, marque `qt`),
 * puis du remplissage. C'est le conteneur des vidéos de téléphone.
 */
function tests_conversion_film_mov(int $octets = 65536): string
{
    $chemin = storage_path('tmp') . '/essai-envoi-mov-' . bin2hex(random_bytes(6)) . '.mov';
    $entete = pack('N', 20) . 'ftypqt  ' . pack('N', 0) . 'qt  ';
    file_put_contents($chemin, $entete . str_repeat("\0", max(0, $octets - strlen($entete))));
    return $chemin;
}

/**
 * Fiche vidéo (ou son) d'essai, avec son fichier réel dans le stockage.
 *
 * @return array{id:int, rel:string, abs:string}
 */
function tests_conversion_document(int $compte, string $extension, string $mime, string $titre = 'Film d’essai'): array
{
    $rel  = storage_target_path($extension);
    $abs  = storage_absolute($rel);
    $tmp  = tests_conversion_fichier(4096, $extension);
    @rename($tmp, $abs);
    @chmod($abs, 0640);

    $id = db_insert('documents', [
        'title'         => $titre,
        'description'   => null,
        'doc_date'      => null,
        'doc_date_sort' => null,
        'category_id'   => null,
        'kind'          => str_starts_with($mime, 'audio/') ? 'document' : 'video',
        'visibility'    => 'public',
        'rel_path'      => $rel,
        'thumb_path'    => null,
        'original_name' => 'film-original.' . $extension,
        'mime'          => $mime,
        'ext'           => $extension,
        'size'          => (int) @filesize($abs),
        'width'         => null,
        'height'        => null,
        'is_attachment' => 0,
        'created_by'    => $compte,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);

    return ['id' => $id, 'rel' => $rel, 'abs' => $abs];
}

/**
 * Vide la file avant un essai : les travaux laissés en attente par l'essai
 * précédent ne doivent pas être pris à la place de celui que l'on prépare.
 */
function tests_conversion_vider_file(): void
{
    db_exec("UPDATE conversions SET state = 'ignoree' WHERE state IN ('attente', 'en_cours')");
}

/** Lance une page du site comme le faisait la famille « envoi ». */
function tests_conversion_requete(string $methode, string $url, ?int $compte, array $post = []): array
{
    return tests_envoi_requete($methode, $url, $compte, $post);
}

/**
 * Toute la famille « conversion ».
 */
function tests_conversion(): void
{
    echo "Conversion des vidéos — file, essais, réussite, droits\n";

    $compte  = tests_envoi_compte('admin');
    $famille = tests_envoi_compte('famille');

    // Réglages remis en place à la fin, quoi qu'il arrive.
    $avant = [
        'conversion_active'             => (string) site_setting('conversion_active'),
        'conversion_qualite'            => (string) site_setting('conversion_qualite'),
        'conversion_supprimer_original' => (string) site_setting('conversion_supprimer_original'),
    ];
    $avantFichiers = tests_envoi_fichiers();

    // ffmpeg est imposé absent pour toute la famille d'essais : les résultats
    // ne dépendent pas de la machine (un Pi en a un, pas le bac à sable).
    conversion_outils_forcer(['ffmpeg' => null, 'ffprobe' => null]);

    try {
        db_transaction(static function () use ($compte, $famille): void {
            tests_conversion_file($compte);
            tests_conversion_envoi($compte, $famille);
            tests_conversion_prise($compte);
            tests_conversion_reussite($compte);
            tests_conversion_droits($compte, $famille);
            tests_conversion_entretien($compte);

            // Tout est annulé : la base du site est rendue intacte.
            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    } finally {
        // Recherche réelle de ffmpeg rétablie pour la suite des essais.
        conversion_outils_forcer(null);
        foreach ($avant as $cle => $valeur) {
            if ($valeur === '') {
                db_exec('DELETE FROM settings WHERE key = ?', [$cle]);
                site_settings_oublier();
            } else {
                save_site_setting($cle, $valeur);
            }
        }
        // Fichiers créés pendant les tests seulement (la transaction ne les
        // annule pas, elle ne concerne que la base).
        foreach (array_diff(tests_envoi_fichiers(), $avantFichiers) as $fichier) {
            @unlink($fichier);
        }
        // Fichiers d'essai restés dans tmp (renommage impossible, test interrompu).
        foreach (glob(storage_path('tmp') . '/essai-conversion-*') ?: [] as $reste) {
            @unlink($reste);
        }
    }
}

/** 1. La file d'attente : qui entre, qui n'entre pas, une seule ligne par fiche. */
function tests_conversion_file(int $compte): void
{
    // Réglages : conversion active, ffmpeg imposé absent.
    save_site_setting('conversion_active', '1');
    conversion_outils_forcer(['ffmpeg' => null, 'ffprobe' => null]);

    $reglages = conversion_reglages();
    verifier(
        'conversion : sans ffmpeg, la conversion se déclare indisponible au lieu de casser',
        $reglages['disponible'] === false && conversion_active() === false,
        'outils : ' . json_encode([$reglages['ffmpeg'], $reglages['ffprobe']])
    );

    // L'autre sens : deux exécutables présents rendent la conversion possible.
    $outilFfmpeg  = tests_conversion_outil('ffmpeg');
    $outilFfprobe = tests_conversion_outil('ffprobe');
    conversion_outils_forcer(['ffmpeg' => $outilFfmpeg, 'ffprobe' => $outilFfprobe]);
    verifier(
        'conversion : avec ffmpeg et ffprobe en place, elle redevient disponible',
        conversion_reglages()['disponible'] === true && conversion_active() === true,
        json_encode([conversion_reglages()['ffmpeg'], conversion_reglages()['ffprobe']])
    );
    @unlink($outilFfmpeg);
    @unlink($outilFfprobe);

    // Retour à l'absence : le reste des essais n'a jamais besoin de ffmpeg.
    conversion_outils_forcer(['ffmpeg' => null, 'ffprobe' => null]);
    verifier(
        'conversion : sans outils, elle redevient indisponible (l’état se relit à chaque fois)',
        conversion_reglages()['disponible'] === false
    );
    verifier(
        'conversion : la qualité par défaut est « équilibrée » (1080p, CRF 23)',
        $reglages['qualite'] === 'equilibre' && $reglages['crf'] === 23
            && $reglages['hauteur_max'] === 1080 && $reglages['audio_kbps'] === 128,
        json_encode($reglages)
    );

    save_site_setting('conversion_qualite', 'qualite-inconnue');
    verifier(
        'conversion : une qualité inconnue retombe sur « équilibrée »',
        conversion_reglages()['qualite'] === 'equilibre'
    );
    save_site_setting('conversion_qualite', 'leger');
    verifier(
        'conversion : « légère » borne la hauteur à 720p',
        conversion_reglages()['hauteur_max'] === 720 && conversion_reglages()['crf'] === 26
    );
    save_site_setting('conversion_qualite', 'equilibre');

    // Quels fichiers ont besoin d'être convertis ?
    $cas = [
        ['video', 'mp4', 'video/mp4', false, 'un MP4 en H.264 reste tel quel'],
        ['video', 'mov', 'video/quicktime', true, 'un MOV de téléphone est converti'],
        ['video', 'avi', 'video/x-msvideo', true, 'un AVI est converti'],
        ['video', 'mkv', 'video/x-matroska', true, 'un MKV est converti'],
        ['video', 'webm', 'video/webm', true, 'un WebM est converti'],
        ['document', 'wav', 'audio/wav', true, 'un WAV est converti en M4A'],
        ['document', 'amr', 'audio/amr', true, 'un AMR de dictaphone est converti'],
        ['document', 'm4a', 'audio/mp4', false, 'un M4A reste tel quel'],
        ['document', 'mp3', 'audio/mpeg', false, 'un MP3 reste tel quel'],
        ['photo', 'jpg', 'image/jpeg', false, 'une photo n’est jamais convertie'],
    ];
    foreach ($cas as [$kind, $ext, $mime, $attendu, $nom]) {
        verifier('conversion : ' . $nom, conversion_necessaire([
            'kind' => $kind, 'ext' => $ext, 'mime' => $mime, 'is_attachment' => 0,
        ]) === $attendu);
    }
    verifier(
        'conversion : une photo rattachée à une personne n’est jamais convertie',
        conversion_necessaire(['kind' => 'photo', 'ext' => 'jpg', 'mime' => 'image/jpeg', 'is_attachment' => 1]) === false
    );

    // Un travail par fiche : replanifier remplace, ne duplique pas.
    $film = tests_conversion_document($compte, 'mov', 'video/quicktime');
    $premier = conversion_planifier($film['id'], $compte);
    verifier(
        'conversion : une vidéo au format non lisible entre dans la file',
        $premier['ok'] && !$premier['ignore'] && $premier['state'] === 'attente',
        json_encode($premier)
    );

    $second = conversion_planifier($film['id'], $compte);
    verifier(
        'conversion : replanifier ne crée pas de deuxième travail pour la même fiche',
        (int) db_value('SELECT COUNT(*) FROM conversions WHERE document_id = ?', [$film['id']], 0) === 1
            && $second['id'] === $premier['id']
    );

    // Un MP4 déjà bon : rien à faire, et on ne l'inscrit pas.
    $mp4 = tests_conversion_document($compte, 'mp4', 'video/mp4', 'Film déjà bon');
    $inutile = conversion_planifier($mp4['id'], $compte);
    verifier(
        'conversion : un MP4 déjà lisible n’entre pas dans la file',
        $inutile['ok'] && $inutile['ignore'] === true && conversion_pour_document($mp4['id']) === null
    );

    // Conversion désactivée : on n'inscrit rien du tout.
    save_site_setting('conversion_active', '0');
    $autre = tests_conversion_document($compte, 'avi', 'video/x-msvideo', 'Film désactivé');
    $desactive = conversion_planifier($autre['id'], $compte);
    verifier(
        'conversion : désactivée, elle n’inscrit rien dans la file',
        $desactive['ignore'] === true && conversion_pour_document($autre['id']) === null
    );
    save_site_setting('conversion_active', '1');

    // Fiche sans fichier : refus clair, pas d'exception.
    $fantome = db_insert('documents', [
        'title' => 'Fiche sans fichier', 'kind' => 'video', 'visibility' => 'prive',
        'rel_path' => 'medias/inexistant.mov', 'original_name' => 'perdu.mov',
        'mime' => 'video/quicktime', 'ext' => 'mov', 'size' => 10,
        'is_attachment' => 0, 'created_at' => time(), 'updated_at' => time(),
    ]);
    $absence = conversion_planifier($fantome, $compte);
    verifier(
        'conversion : un fichier introuvable est refusé avec un message en français',
        $absence['ok'] === false && str_contains($absence['error'], 'introuvable'),
        $absence['error']
    );

    // Le résumé sert à l'écran : il compte juste.
    $resume = conversion_resume();
    verifier(
        'conversion : le résumé compte les travaux par état',
        $resume['total'] >= 1 && $resume['attente'] >= 1 && $resume['a_faire'] === $resume['attente'] + $resume['en_cours'],
        json_encode($resume)
    );
}

/**
 * 1 bis. Le .mov d'un téléphone, de bout en bout : envoi par morceaux, fiche
 * créée, mise en file, puis invisible pour la famille jusqu'à la conversion.
 */
function tests_conversion_envoi(int $compte, int $famille): void
{
    save_site_setting('conversion_active', '1');
    tests_conversion_vider_file();

    $chemin = tests_conversion_film_mov(65536);
    $taille = (int) filesize($chemin);
    $envoi  = tests_envoi_jouer($compte, 'anniversaire-de-mamie.mov', $chemin);
    @unlink($chemin);

    verifier(
        'envoi vidéo : un .mov de téléphone est accepté de bout en bout (extension et type réel)',
        ($envoi['init']['ok'] ?? false) === true && ($envoi['terminer']['ok'] ?? false) === true,
        'init : ' . (string) ($envoi['init']['error'] ?? '') . ' / fin : ' . (string) ($envoi['terminer']['error'] ?? '')
    );

    $ligne = envoi_par_id((int) ($envoi['envoi']['id'] ?? 0));
    $doc   = $ligne !== null
        ? db_one('SELECT * FROM documents WHERE id = ?', [(int) $ligne['document_id']])
        : null;

    verifier(
        'envoi vidéo : la fiche garde le .mov d’origine, son type et sa taille',
        $doc !== null && (string) $doc['ext'] === 'mov' && (string) $doc['mime'] === 'video/quicktime'
            && (string) $doc['kind'] === 'video' && (int) $doc['size'] === $taille,
        $doc !== null ? json_encode([$doc['ext'], $doc['mime'], $doc['kind'], (int) $doc['size']]) : 'aucune fiche'
    );

    $travail = $doc !== null ? conversion_pour_document((int) $doc['id']) : null;
    verifier(
        'envoi vidéo : le .mov part directement dans la file de conversion',
        $travail !== null && (string) $travail['state'] === 'attente'
            && (string) $travail['source_path'] === (string) $doc['rel_path'],
        $travail !== null ? json_encode([$travail['state'], $travail['source_path']]) : 'hors file'
    );

    // Publiée par l'administrateur, la fiche reste invisible à la famille tant
    // que la conversion n'a pas eu lieu : même réponse qu'un contenu interdit.
    db_update('documents', (int) $doc['id'], ['visibility' => 'public']);
    $_SESSION = ['account_id' => $famille];
    $avant = find_viewable_document((int) $doc['id']);
    verifier(
        'envoi vidéo : la famille ne voit pas encore le .mov (même 404 qu’un contenu interdit)',
        $avant === null && conversion_document_pret($doc) === false,
        $avant !== null ? 'visible avant conversion' : 'refusée'
    );

    $liste = tests_conversion_requete('GET', '/videos', $famille);
    verifier(
        'envoi vidéo : la page « Vidéos » ne montre pas la vidéo en préparation',
        $liste['code'] === 200 && !str_contains($liste['corps'], 'anniversaire-de-mamie'),
        'code ' . $liste['code']
    );

    // Une fois convertie (ffmpeg simulé : le fichier MP4 est fabriqué ici),
    // la même famille la voit, sans rien avoir à faire de plus.
    $pris = conversion_prendre();
    verifier(
        'envoi vidéo : le travail repris est bien celui du .mov envoyé',
        $pris !== null && (int) $pris['document_id'] === (int) $doc['id'],
        $pris !== null ? (string) $pris['document_id'] . ' / ' . (string) $doc['id'] : 'file vide'
    );
    if ($pris !== null && (int) $pris['document_id'] === (int) $doc['id']) {
        $fini = conversion_reussir((int) $pris['id'], tests_conversion_fichier(2048, 'mp4'), ['duration' => 42]);
        $apres = db_one('SELECT * FROM documents WHERE id = ?', [(int) $doc['id']]);
        verifier(
            'envoi vidéo : converti en MP4, le film devient lisible par la famille',
            $fini['ok'] && $apres !== null && (string) $apres['ext'] === 'mp4'
                && (string) $apres['mime'] === 'video/mp4'
                && find_viewable_document((int) $doc['id']) !== null,
            $apres !== null ? json_encode([$apres['ext'], $apres['mime']]) : (string) $fini['error']
        );
    }
    $_SESSION = ['account_id' => $compte];
}

/** 2. Prise d'un travail, avancement, essais, relance, annulation. */
function tests_conversion_prise(int $compte): void
{
    tests_conversion_vider_file();

    $film = tests_conversion_document($compte, 'mkv', 'video/x-matroska', 'Film à prendre');
    conversion_planifier($film['id'], $compte);

    $pris = conversion_prendre();
    verifier(
        'conversion : la prise d’un travail le marque « en cours » et compte un essai',
        $pris !== null && (string) $pris['state'] === 'en_cours' && (int) $pris['attempts'] === 1,
        json_encode($pris['state'] ?? null)
    );

    // La prise est atomique : le même travail n'est jamais pris deux fois.
    $deuxieme = conversion_prendre();
    verifier(
        'conversion : un travail déjà pris n’est pas repris par un second passage',
        $deuxieme === null || (int) $deuxieme['id'] !== (int) $pris['id'],
        json_encode($deuxieme['id'] ?? null)
    );

    conversion_progresser((int) $pris['id'], 42);
    verifier(
        'conversion : l’avancement est enregistré et borné',
        (int) conversion_par_id((int) $pris['id'])['progress'] === 42
    );
    conversion_progresser((int) $pris['id'], 150);
    verifier(
        'conversion : l’avancement ne dépasse jamais 99 % avant la fin',
        (int) conversion_par_id((int) $pris['id'])['progress'] === 99
    );

    // Échec : on réessaie plus tard, avec le message conservé.
    conversion_echouer((int) $pris['id'], 'ffmpeg n’a pas pu lire ce fichier.');
    $apres = conversion_par_id((int) $pris['id']);
    verifier(
        'conversion : un premier échec remet le travail en attente (nouvel essai)',
        (string) $apres['state'] === 'attente' && str_contains((string) $apres['message'], 'lire ce fichier'),
        (string) $apres['state']
    );

    // Trois essais au total, puis échec définitif.
    db_exec('UPDATE conversions SET attempts = 3 WHERE id = ?', [(int) $pris['id']]);
    db_exec("UPDATE conversions SET state = 'en_cours' WHERE id = ?", [(int) $pris['id']]);
    conversion_echouer((int) $pris['id'], 'Troisième échec.');
    $definitif = conversion_par_id((int) $pris['id']);
    verifier(
        'conversion : au troisième échec, le travail s’arrête en échec (message conservé)',
        (string) $definitif['state'] === 'echec' && (int) $definitif['finished_at'] > 0,
        (string) $definitif['state']
    );

    // Relance : les essais repartent de zéro, sinon le fichier relancé à la
    // main échouerait aussitôt à cause des essais déjà consommés.
    $relance = conversion_relancer((int) $pris['id']);
    $apresRelance = conversion_par_id((int) $pris['id']);
    verifier(
        'conversion : « Relancer » remet en attente et remet les essais à zéro',
        $relance['ok'] && (string) $apresRelance['state'] === 'attente' && (int) $apresRelance['attempts'] === 0,
        json_encode($relance)
    );

    // Pendant un travail, on ne relance pas : il faudrait deux ffmpeg sur le
    // même fichier, et le Pi n'en a pas la force.
    $enCours = conversion_prendre();
    verifier(
        'conversion : relancer un travail en cours est refusé (une seule conversion à la fois)',
        $enCours !== null && (string) $enCours['state'] === 'en_cours'
            && conversion_relancer((int) $enCours['id'])['ok'] === false
    );
    verifier(
        'conversion : la commande voit qu’un travail est en cours (rien à annuler encore)',
        conversion_abandonnee((int) $enCours['id']) === false
    );

    // Annulation : le travail sort de la file, la commande s'arrête au palier
    // suivant, et le fichier d'origine de la fiche n'est pas touché.
    $annule = conversion_annuler((int) $enCours['id']);
    verifier(
        'conversion : annuler un travail le sort de la file et arrête la commande',
        $annule['ok'] && (string) conversion_par_id((int) $enCours['id'])['state'] === 'ignoree'
            && conversion_abandonnee((int) $enCours['id']) === true
            && is_file($film['abs'])
    );
    verifier(
        'conversion : un travail annulé peut être relancé (rien n’est perdu)',
        conversion_relancer((int) $enCours['id'])['ok'] === true
            && (string) conversion_par_id((int) $enCours['id'])['state'] === 'attente'
    );
}

/** 3. Réussite : fichier remplacé, original conservé, son en M4A. */
function tests_conversion_reussite(int $compte): void
{
    save_site_setting('conversion_supprimer_original', '0');

    tests_conversion_vider_file();

    $film = tests_conversion_document($compte, 'avi', 'video/x-msvideo', 'Vieux film de famille');
    $plan = conversion_planifier($film['id'], $compte);
    $travail = conversion_prendre();
    verifier(
        'conversion : le travail pris est bien celui qui vient d’être planifié',
        $travail !== null && (int) $travail['id'] === (int) $plan['id']
    );

    // ffmpeg est simulé : on fabrique le fichier converti à la main.
    $converti = tests_conversion_fichier(8192, 'mp4');

    $reussi = conversion_reussir((int) $travail['id'], $converti, ['duration' => 3720]);
    $doc = db_one('SELECT * FROM documents WHERE id = ?', [$film['id']]);
    $ligne = conversion_par_id((int) $travail['id']);

    verifier(
        'conversion : la réussite remplace le fichier de la fiche par un MP4',
        $reussi['ok'] && (string) $doc['ext'] === 'mp4' && (string) $doc['mime'] === 'video/mp4'
            && (string) $doc['rel_path'] !== $film['rel'],
        json_encode($reussi)
    );
    verifier(
        'conversion : la fiche garde son nom d’origine, avec l’extension .mp4',
        str_ends_with((string) $doc['original_name'], '.mp4')
            && str_starts_with((string) $doc['original_name'], 'film-original'),
        (string) $doc['original_name']
    );
    verifier(
        'conversion : la durée lue par ffprobe est enregistrée sur la fiche',
        (int) $doc['duration_seconds'] === 3720
    );
    verifier(
        'conversion : la taille de la fiche suit le fichier converti',
        (int) $doc['size'] === (int) @filesize(storage_absolute((string) $doc['rel_path']))
    );
    verifier(
        'conversion : le travail passe à « terminée » à 100 %, avec sa trace',
        (string) $ligne['state'] === 'terminee' && (int) $ligne['progress'] === 100
            && (string) $ligne['target_path'] === (string) $doc['rel_path']
            && str_contains((string) $ligne['message'], 'MP4'),
        json_encode([$ligne['state'], $ligne['progress']])
    );
    verifier(
        'conversion : l’original est conservé quand le réglage le demande (par défaut)',
        is_file($film['abs'])
    );
    verifier(
        'conversion : la visibilité de la fiche n’est jamais modifiée par la conversion',
        (string) $doc['visibility'] === 'public',
        (string) $doc['visibility']
    );
    verifier(
        'conversion : la conversion terminée est journalisée',
        db_one("SELECT * FROM activity_log WHERE action = 'video_convertie' ORDER BY id DESC LIMIT 1") !== null
    );

    verifier(
        'conversion : une conversion terminée ne s’annule plus (le fichier est en service)',
        conversion_annuler((int) $travail['id'])['ok'] === false
    );

    // Un fichier converti vide n'est jamais accepté (fichier réellement vide :
    // une conversion qui ne produirait rien ne doit pas écraser la vidéo).
    tests_conversion_vider_file();
    $vide = storage_path('tmp') . '/essai-conversion-vide-' . bin2hex(random_bytes(4)) . '.mp4';
    file_put_contents($vide, '');
    $film2 = tests_conversion_document($compte, 'mkv', 'video/x-matroska', 'Film suivant');
    conversion_planifier($film2['id'], $compte);
    $travail2 = conversion_prendre();
    $refus = conversion_reussir((int) $travail2['id'], $vide, []);
    verifier(
        'conversion : un fichier converti vide est refusé (l’original reste)',
        $refus['ok'] === false && (string) conversion_par_id((int) $travail2['id'])['state'] === 'en_cours',
        $refus['error']
    );
    conversion_echouer((int) $travail2['id'], 'Fichier vide.');

    // Un son : WAV → M4A, la fiche reste un document.
    tests_conversion_vider_file();
    $son = tests_conversion_document($compte, 'wav', 'audio/wav', 'Témoignage de mamie');
    conversion_planifier($son['id'], $compte);
    $travailSon = conversion_prendre();
    $sonConverti = tests_conversion_fichier(2048, 'm4a');
    $okSon = conversion_reussir((int) $travailSon['id'], $sonConverti, ['duration' => 95]);
    $docSon = db_one('SELECT * FROM documents WHERE id = ?', [$son['id']]);
    verifier(
        'conversion : un son est converti en M4A (AAC), sans devenir une vidéo',
        $okSon['ok'] && (string) $docSon['ext'] === 'm4a' && (string) $docSon['mime'] === 'audio/mp4'
            && (string) $docSon['kind'] === 'document',
        json_encode([$docSon['ext'], $docSon['mime'], $docSon['kind']])
    );
    verifier(
        'conversion : le message d’un son parle de M4A, pas de MP4',
        str_contains((string) conversion_par_id((int) $travailSon['id'])['message'], 'M4A')
    );
}

/** 4. Droits, avertissements et entretien. */
function tests_conversion_droits(int $compte, int $famille): void
{
    // --- La page est fermée aux comptes de famille et aux visiteurs ---------
    $visiteur = tests_conversion_requete('GET', '/admin/conversions', null);
    verifier(
        'conversion : un visiteur est renvoyé vers la connexion',
        $visiteur['code'] === 303 && str_contains($visiteur['location'], '/connexion'),
        $visiteur['code'] . ' ' . $visiteur['location']
    );

    $pageFamille = tests_conversion_requete('GET', '/admin/conversions', $famille);
    verifier('conversion : la page est fermée à un compte de famille (403)', $pageFamille['code'] === 403);

    $_SESSION = ['account_id' => $compte];
    $page = tests_conversion_requete('GET', '/admin/conversions', $compte);
    verifier('conversion : un administrateur ouvre la page « Conversions »', $page['code'] === 200, $page['erreur']);
    verifier_contient('conversion : la page affiche son titre', 'Conversions', $page['corps']);
    verifier_contient(
        'conversion : la page dit clairement que ffmpeg manque, sans casser',
        'ffmpeg est introuvable',
        $page['corps']
    );

    // --- Les actions exigent le jeton CSRF ----------------------------------
    db_exec("UPDATE conversions SET state = 'echec' WHERE state IN ('attente', 'en_cours')");
    $cible = db_one('SELECT * FROM conversions ORDER BY id LIMIT 1');
    if ($cible !== null) {
        $_SESSION = ['account_id' => $compte];
        $sansJeton = tests_conversion_requete('POST', '/admin/conversions/' . (int) $cible['id'] . '/relancer', $compte, []);
        verifier(
            'conversion : sans jeton CSRF, « Relancer » est refusé',
            $sansJeton['code'] === 403,
            (string) $sansJeton['code']
        );

        $_SESSION = ['account_id' => $compte];
        $avecJeton = tests_conversion_requete('POST', '/admin/conversions/' . (int) $cible['id'] . '/relancer', $compte, [
            '_csrf' => csrf_token(),
        ]);
        verifier(
            'conversion : avec le jeton, « Relancer » remet le travail en attente',
            $avecJeton['code'] === 303 && (string) conversion_par_id((int) $cible['id'])['state'] === 'attente',
            $avecJeton['code'] . ' ' . (string) conversion_par_id((int) $cible['id'])['state']
        );
    }

    // --- Rien n'est converti pendant une visite ----------------------------
    $codeWeb = (string) @file_get_contents(HDF_APP . '/controllers/admin_conversions.php');
    verifier(
        'conversion : aucune page ne lance ffmpeg (le travail est fait par la commande)',
        !str_contains($codeWeb, 'proc_open') && !str_contains($codeWeb, 'conversion_prendre')
            && !str_contains($codeWeb, 'conversion_reussir'),
        'admin_conversions.php'
    );

    $codeCli = (string) @file_get_contents(HDF_APP . '/cli/convertir.php');
    $codeLib = (string) @file_get_contents(HDF_APP . '/lib/conversion.php');
    verifier(
        'conversion : la commande lance ffmpeg sans passer par le shell',
        str_contains($codeCli, 'proc_open') && !preg_match('/\b(shell_exec|exec|system|passthru)\s*\(/', $codeCli . $codeLib),
        'cli/convertir.php'
    );
    verifier(
        'conversion : la commande produit du H.264 + AAC lisible partout',
        str_contains($codeLib, "'libx264'") && str_contains($codeLib, "'aac'")
            && str_contains($codeLib, "'+faststart'") && str_contains($codeLib, "'yuv420p'")
    );
    verifier(
        'conversion : la commande accepte d’être arrêtée (annulation) et ne bloque pas le site',
        str_contains($codeCli, 'conversion_abandonnee') && str_contains($codeCli, 'proc_terminate')
    );
    verifier(
        'conversion : la commande lit ses arguments sans supposer $argv (PHP peut le désactiver)',
        str_contains($codeCli, '$argv ?? $_SERVER') || str_contains($codeCli, '$_SERVER[' . "'argv'" . ']')
    );
    verifier(
        'conversion : la commande existe et refuse d’être appelée par le web',
        str_contains($codeCli, "if (!is_cli())")
    );

    // L'unité systemd et l'installation suivent.
    verifier(
        'conversion : un minuteur systemd lance la commande toutes les 10 minutes',
        is_file(HDF_ROOT . '/deploy/systemd/histoiredefamille-conversion.timer')
            && str_contains((string) @file_get_contents(HDF_ROOT . '/deploy/systemd/histoiredefamille-conversion.timer'), 'OnUnitActiveSec=10min')
    );
    verifier(
        'conversion : le service tourne sous www-data, en priorité basse, une fois à la fois',
        is_file(HDF_ROOT . '/deploy/systemd/histoiredefamille-conversion.service')
            && str_contains((string) @file_get_contents(HDF_ROOT . '/deploy/systemd/histoiredefamille-conversion.service'), 'Type=oneshot')
            && str_contains((string) @file_get_contents(HDF_ROOT . '/deploy/systemd/histoiredefamille-conversion.service'), 'User=www-data')
            && str_contains((string) @file_get_contents(HDF_ROOT . '/deploy/systemd/histoiredefamille-conversion.service'), 'Nice=19')
    );
    verifier(
        'conversion : l’installation pose le minuteur et propose ffmpeg',
        str_contains((string) @file_get_contents(HDF_ROOT . '/deploy/install.sh'), 'histoiredefamille-conversion.timer')
            && str_contains((string) @file_get_contents(HDF_ROOT . '/deploy/install.sh'), 'ffmpeg')
    );

    // --- La famille ne voit pas une vidéo en préparation -------------------
    tests_conversion_vider_file();
    $film = tests_conversion_document($compte, 'mov', 'video/quicktime', 'Film public en attente');
    $film['id'] = (int) $film['id'];
    db_update('documents', $film['id'], ['visibility' => 'public']);
    conversion_planifier($film['id'], $compte);

    $pret = tests_conversion_document($compte, 'mp4', 'video/mp4', 'Film public prêt');
    db_update('documents', $pret['id'], ['visibility' => 'public']);

    // Visiteur : la clause de visibilité ne doit rendre que le film prêt.
    $_SESSION = [];
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $vus = db_all("SELECT d.id, d.title FROM documents d WHERE d.visibility = 'public' AND {$clause}", $params);
    $titres = array_column($vus, 'title');
    verifier(
        'conversion : une vidéo en attente de conversion n’est pas montrée à un visiteur',
        in_array('Film public prêt', $titres, true) && !in_array('Film public en attente', $titres, true),
        implode(' | ', $titres)
    );
    verifier(
        'conversion : la clause ne retire rien aux autres contenus (photos, articles)',
        str_contains($clause, "d.kind <> 'video'")
    );

    verifier(
        'conversion : une vidéo en attente est refusée à la famille (même 404 qu’un contenu interdit)',
        conversion_document_pret(['id' => $film['id'], 'kind' => 'video']) === false
            && conversion_document_pret(['id' => $pret['id'], 'kind' => 'video']) === true
    );

    $avertissement = conversion_avertissement($film['id']);
    verifier(
        'conversion : la fiche prévient qu’une vidéo n’est pas encore prête',
        $avertissement['state'] === 'attente' && $avertissement['bloquant'] === true
            && str_contains($avertissement['phrase'], 'file de conversion'),
        $avertissement['phrase']
    );
    verifier(
        'conversion : un échec de conversion ne bloque pas la lecture du fichier d’origine',
        (static function () use ($compte): bool {
            tests_conversion_vider_file();
            $doc = tests_conversion_document($compte, 'avi', 'video/x-msvideo', 'Film en échec');
            conversion_planifier($doc['id'], $compte);
            $travail = conversion_prendre();
            db_exec('UPDATE conversions SET attempts = 3 WHERE id = ?', [(int) $travail['id']]);
            conversion_echouer((int) $travail['id'], 'Fichier abîmé.');
            return conversion_document_pret(['id' => $doc['id'], 'kind' => 'video']) === true
                && conversion_avertissement((int) $doc['id'])['bloquant'] === false;
        })()
    );

    // Le menu de l'administration mène à la page.
    verifier_contient(
        'conversion : le menu de l’administration propose « Conversions »',
        "navLink('/admin/conversions'",
        (string) @file_get_contents(HDF_APP . '/views/layout_admin.php')
    );
}

/** 5. Entretien : purge des vieilles lignes, originaux libérés. */
function tests_conversion_entretien(int $compte): void
{
    save_site_setting('conversion_supprimer_original', '0');

    tests_conversion_vider_file();
    $film = tests_conversion_document($compte, 'avi', 'video/x-msvideo', 'Film ancien');
    conversion_planifier($film['id'], $compte);
    $travail = conversion_prendre();
    $converti = tests_conversion_fichier(1024, 'mp4');
    conversion_reussir((int) $travail['id'], $converti, ['duration' => 60]);

    // Réglage décoché : l'original n'est jamais effacé, même ancien.
    db_exec('UPDATE conversions SET finished_at = ? WHERE id = ?', [time() - 30 * 86400, (int) $travail['id']]);
    $effaces = conversion_liberer_originaux(7);
    verifier(
        'entretien : sans le réglage, aucun original n’est effacé',
        $effaces === 0 && is_file($film['abs']),
        'effacés : ' . $effaces . ', présent : ' . var_export(is_file($film['abs']), true)
    );

    // Réglage coché, mais moins de sept jours : on attend encore.
    save_site_setting('conversion_supprimer_original', '1');
    db_exec('UPDATE conversions SET finished_at = ? WHERE id = ?', [time() - 2 * 86400, (int) $travail['id']]);
    $recent = conversion_liberer_originaux(7);
    verifier(
        'entretien : un original récent est conservé (délai de sept jours)',
        $recent === 0 && is_file($film['abs']),
        'effacés : ' . $recent . ', présent : ' . var_export(is_file($film['abs']), true)
    );

    // Réglage coché et plus de sept jours : l'espace est rendu.
    db_exec('UPDATE conversions SET finished_at = ? WHERE id = ?', [time() - 10 * 86400, (int) $travail['id']]);
    $vieux = conversion_liberer_originaux(7);
    verifier(
        'entretien : après sept jours et avec le réglage, l’original est effacé',
        $vieux === 1 && !is_file($film['abs']),
        'effacés : ' . $vieux . ', présent : ' . var_export(is_file($film['abs']), true)
    );

    // Un original encore utilisé par une autre fiche n'est jamais effacé.
    // On le convertit réglage décoché : c'est l'entretien qu'on éprouve ici,
    // pas la suppression immédiate.
    tests_conversion_vider_file();
    save_site_setting('conversion_supprimer_original', '0');
    $partage = tests_conversion_document($compte, 'mkv', 'video/x-matroska', 'Film partagé');
    conversion_planifier($partage['id'], $compte);
    $travailPartage = conversion_prendre();
    conversion_reussir((int) $travailPartage['id'], tests_conversion_fichier(512, 'mp4'), ['duration' => 10]);
    db_exec('UPDATE conversions SET finished_at = ? WHERE id = ?', [time() - 10 * 86400, (int) $travailPartage['id']]);

    db_insert('documents', [
        'title' => 'Copie qui utilise le même fichier', 'kind' => 'video', 'visibility' => 'prive',
        'rel_path' => $partage['rel'], 'original_name' => 'copie.mkv',
        'mime' => 'video/x-matroska', 'ext' => 'mkv', 'size' => 4096,
        'is_attachment' => 0, 'created_at' => time(), 'updated_at' => time(),
    ]);
    save_site_setting('conversion_supprimer_original', '1');
    $garde = conversion_liberer_originaux(7);
    verifier(
        'entretien : un original encore utilisé par une autre fiche n’est jamais effacé',
        $garde === 0 && is_file($partage['abs']),
        'effacés : ' . $garde . ', présent : ' . var_export(is_file($partage['abs']), true)
    );

    // Purge des lignes : les travaux terminés anciens s'en vont, les récents restent.
    db_exec('UPDATE conversions SET finished_at = ? WHERE state = ?', [time() - 400 * 86400, 'terminee']);
    $purges = conversion_purger(90);
    verifier(
        'entretien : les travaux terminés depuis plus de 90 jours sont effacés de la liste',
        $purges >= 1 && (int) db_value("SELECT COUNT(*) FROM conversions WHERE state = 'terminee'", [], 0) === 0,
        'purgés : ' . $purges
    );
    verifier(
        'entretien : la purge ne touche jamais aux fiches ni à leurs fichiers',
        db_one('SELECT * FROM documents WHERE id = ?', [$partage['id']]) !== null
            && is_file(storage_absolute((string) db_one('SELECT rel_path FROM documents WHERE id = ?', [$partage['id']])['rel_path']))
    );

    // La commande elle-même : ses arguments purs (commande ffmpeg, avancement,
    // messages d'erreur). On ne lance jamais ffmpeg dans les tests.
    $reglages = array_merge(conversion_reglages(), [
        'ffmpeg' => '/usr/bin/ffmpeg', 'preset' => 'veryfast',
        'crf' => 23, 'audio_kbps' => 128, 'hauteur_max' => 1080,
    ]);
    $commande = conversion_commande('/stockage/film.mov', '/tmp/sortie.mp4', $reglages);
    verifier(
        'commande : ffmpeg est appelé en tableau (jamais par le shell, donc aucun nom de fichier interprété)',
        is_array($commande) && $commande[0] === '/usr/bin/ffmpeg'
            && in_array('/stockage/film.mov', $commande, true)
            && in_array('/tmp/sortie.mp4', $commande, true)
    );
    verifier(
        'commande : H.264 + AAC, faststart, yuv420p et « -nostdin » (jamais d’attente au clavier)',
        in_array('libx264', $commande, true) && in_array('aac', $commande, true)
            && in_array('+faststart', $commande, true) && in_array('yuv420p', $commande, true)
            && in_array('-nostdin', $commande, true)
    );
    verifier(
        'commande : la hauteur est bornée (une vidéo 4K du téléphone devient 1080p)',
        in_array("scale=-2:'min(1080,ih)'", $commande, true),
        implode(' ', $commande)
    );

    $audio = conversion_commande_audio('/stockage/voix.wav', '/tmp/sortie.m4a', $reglages);
    verifier(
        'commande : un son est converti en AAC sans conserver d’image (« -vn »)',
        in_array('-vn', $audio, true) && in_array('aac', $audio, true)
            && !in_array('libx264', $audio, true)
    );

    verifier(
        'commande : l’avancement se lit dans la sortie de ffmpeg (et reste dans 0-99)',
        conversion_lire_progression("out_time_us=45000000\n", 90) === 50
            && conversion_lire_progression("out_time_ms=89000000\n", 90) === 98
            && conversion_lire_progression("out_time_us=190000000\n", 90) === 99
            && conversion_lire_progression("frame=12\n", 90) === null
    );
    verifier(
        'commande : sans durée connue, aucun pourcentage n’est inventé',
        conversion_lire_progression("out_time_us=45000000\n", null) === null
    );

    verifier(
        'commande : les échecs de ffmpeg sont expliqués en français simple',
        str_contains(conversion_lire_erreur('No space left on device', 1), 'disque est plein')
            && str_contains(conversion_lire_erreur('Permission denied', 1), 'Droits insuffisants')
            && str_contains(conversion_lire_erreur('moov atom not found', 1), 'abîmé')
            && str_contains(conversion_lire_erreur('Unknown encoder libx264', 1), 'H.264')
            && str_contains(conversion_lire_erreur('', 9), 'code 9')
    );

    verifier(
        'commande : la durée est écrite en français (« 1 h 02 min », « 8 min 30 s »)',
        conversion_duree_lisible(3720) === '1 h 02 min'
            && conversion_duree_lisible(510) === '8 min 30 s'
            && conversion_duree_lisible(45) === '45 s'
            && conversion_duree_lisible(null) === '0 s'
    );

    // La tâche d'entretien du dimanche s'en occupe toute seule.
    $code = (string) @file_get_contents(HDF_APP . '/cli/maintenance.php');
    verifier(
        'entretien : la tâche hebdomadaire purge la file et libère les originaux',
        str_contains($code, 'conversion_purger(') && str_contains($code, 'conversion_liberer_originaux(')
    );
}
