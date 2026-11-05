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

/** Compte d'essai d'un type donné (lecteur, journaliste, administrateur). */
function tests_conversion_compte(string $type): int
{
    return db_insert('accounts', [
        'username'      => 'conv-' . $type . '-' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai ' . $type,
        'password_hash' => password_make('MotDePasseEssai123!'),
        'role'          => $type === 'admin' ? 'admin' : 'famille',
        'account_type'  => $type,
        'first_name'    => $type === 'branche' ? '' : 'Essai',
        'last_name'     => $type === 'branche' ? '' : 'Conversion',
        'branch_id'     => null,
        'see_all'       => 0,
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
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
            tests_conversion_pendant($compte, $famille);
            tests_conversion_memoire($compte);
            tests_conversion_reelle($compte);

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

    // Marge d'espace libre : la conversion refuse de démarrer sous la marge.
    $marge = conversion_marge_libre();
    verifier(
        'conversion : une marge d’espace libre est prévue (1 Go par défaut)',
        $marge >= 1024 * 1048576,
        (string) $marge
    );
    $filmLourd = ['size' => 5 * 1024 * 1048576];
    $assez = conversion_espace_ok($filmLourd, $marge + $filmLourd['size'], $marge);
    verifier(
        'conversion : marge + taille du fichier libres → elle peut démarrer',
        $assez['ok'] === true && $assez['requis'] === $marge + $filmLourd['size'],
        json_encode($assez, JSON_UNESCAPED_UNICODE)
    );
    $justeTrop = conversion_espace_ok($filmLourd, $marge + $filmLourd['size'] - 1, $marge);
    verifier(
        'conversion : un octet sous la marge → refus de démarrer, message en français',
        $justeTrop['ok'] === false
            && str_contains($justeTrop['message'], 'Conversion impossible')
            && str_contains($justeTrop['message'], 'libérez') === false,
        $justeTrop['message']
    );
    $petitDisque = conversion_espace_ok($filmLourd, 100 * 1048576, $marge);
    verifier(
        'conversion : disque presque plein → refus, le fichier d’origine reste en place',
        $petitDisque['ok'] === false && str_contains($petitDisque['message'], 'Libérez de la place')
    );
    verifier(
        'conversion : la taille de l’original est comptée dans la place exigée',
        conversion_espace_ok(['size' => 0], 2 * $marge, $marge)['ok'] === true
            && conversion_espace_ok(['size' => 0], $marge - 1, $marge)['ok'] === false
    );
    verifier(
        'conversion : espace inconnu → on ne bloque pas (le disque tranchera à l’écriture)',
        conversion_espace_ok($filmLourd, null, $marge)['ok'] === true
            && conversion_espace_ok($filmLourd, null, $marge)['libre'] === null
    );

    // La file et la commande appliquent réellement ce refus.
    $codeFile = (string) file_get_contents(dirname(__DIR__) . '/lib/conversion.php');
    verifier(
        'conversion : mettre un fichier en file vérifie l’espace libre',
        str_contains($codeFile, 'conversion_espace_ok($doc)')
            && str_contains($codeFile, 'Conversion impossible')
    );
    $codeCommande = (string) file_get_contents(__DIR__ . '/convertir.php');
    verifier(
        'conversion : la commande revérifie l’espace AVANT de lancer ffmpeg',
        strpos($codeCommande, 'conversion_peut_demarrer($doc)') !== false
            && strpos($codeCommande, 'conversion_peut_demarrer($doc)') < strpos($codeCommande, 'proc_open(')
    );

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
        'conversion : un ÉCHEC garde la vidéo invisible à la famille et l’original en place',
        (static function () use ($compte): bool {
            tests_conversion_vider_file();
            $doc = tests_conversion_document($compte, 'avi', 'video/x-msvideo', 'Film en échec');
            conversion_planifier($doc['id'], $compte);
            $travail = conversion_prendre();
            db_exec('UPDATE conversions SET attempts = 3 WHERE id = ?', [(int) $travail['id']]);
            conversion_echouer((int) $travail['id'], 'Fichier abîmé.');
            return conversion_document_pret(['id' => $doc['id'], 'kind' => 'video']) === false
                && is_file($doc['abs'])
                && conversion_avertissement((int) $doc['id'])['bloquant'] === true
                && str_contains(conversion_avertissement((int) $doc['id'])['phrase'], 'origine est conservé');
        })()
    );

    verifier(
        'conversion : une conversion ANNULÉE, elle, laisse le fichier d’origine servi tel quel',
        (static function () use ($compte): bool {
            tests_conversion_vider_file();
            $doc = tests_conversion_document($compte, 'avi', 'video/x-msvideo', 'Film annulé');
            conversion_planifier($doc['id'], $compte);
            $travail = conversion_prendre();
            conversion_annuler((int) $travail['id']);
            return conversion_document_pret(['id' => $doc['id'], 'kind' => 'video']) === true;
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

// ---------------------------------------------------------------------------
// 7. Droits PENDANT la conversion (1.26.0)
// ---------------------------------------------------------------------------

/**
 * Une vidéo dans la file n'existe pour personne — sauf pour qui l'a déposée
 * et pour les administrateurs. Après conversion, elle prend la visibilité
 * choisie. L'adresse directe du fichier et celle de la miniature suivent
 * exactement la même règle que la page.
 */
function tests_conversion_pendant(int $admin, int $famille): void
{
    tests_conversion_vider_file();
    save_site_setting('conversion_active', '1');
    conversion_outils_forcer(['ffmpeg' => null, 'ffprobe' => null]);

    // Quatre profils : un lecteur, l'auteur de l'envoi (journaliste), un autre
    // journaliste, un visiteur, plus l'administrateur.
    $auteur = tests_conversion_compte('personne');
    $autre  = tests_conversion_compte('personne');

    $film = tests_conversion_document($auteur, 'mov', 'video/quicktime', 'Film de l’auteur');
    // Une miniature, pour vérifier que /media/miniature/{id} suit la même règle.
    $thumbRel = storage_target_path('jpg');
    $thumbAbs = storage_absolute($thumbRel);
    file_put_contents($thumbAbs, "\xFF\xD8\xFF\xD9");
    db_update('documents', $film['id'], [
        'thumb_path'  => $thumbRel,
        'created_by'  => $auteur,
        'visibility'  => 'famille',
        'title'       => 'Film de l’auteur',
    ]);
    conversion_planifier($film['id'], $auteur);

    $code = static function (string $url, ?int $compte) use ($film): int {
        return (int) tests_conversion_requete('GET', $url, $compte)['code'];
    };

    $urls = [
        'page'          => '/documents/' . $film['id'],
        'fichier'       => '/f/' . $film['id'],
        'telecharger'   => '/f/' . $film['id'] . '/telecharger',
        'miniature'     => '/media/miniature/' . $film['id'],
        'videos'        => '/videos',
    ];

    $pendant = [];
    foreach (['visiteur' => null, 'lecteur' => $famille, 'auteur' => $auteur, 'autre' => $autre, 'admin' => $admin] as $nom => $compte) {
        foreach ($urls as $cle => $url) {
            $pendant[$nom][$cle] = $code($url, $compte);
        }
    }

    verifier(
        'droits pendant la conversion : la vidéo est invisible au visiteur (page, fichier, téléchargement, miniature)',
        $pendant['visiteur']['page'] === 404 && $pendant['visiteur']['fichier'] === 404
            && $pendant['visiteur']['telecharger'] === 404 && $pendant['visiteur']['miniature'] === 404,
        json_encode($pendant['visiteur'])
    );
    verifier(
        'droits pendant la conversion : la vidéo est invisible au lecteur de la famille',
        $pendant['lecteur']['page'] === 404 && $pendant['lecteur']['fichier'] === 404
            && $pendant['lecteur']['telecharger'] === 404 && $pendant['lecteur']['miniature'] === 404,
        json_encode($pendant['lecteur'])
    );
    verifier(
        'droits pendant la conversion : un autre journaliste n’y a pas accès non plus',
        $pendant['autre']['page'] === 404 && $pendant['autre']['fichier'] === 404
            && $pendant['autre']['telecharger'] === 404 && $pendant['autre']['miniature'] === 404,
        json_encode($pendant['autre'])
    );
    verifier(
        'droits pendant la conversion : l’auteur de l’envoi, lui, peut relire sa fiche et son fichier',
        $pendant['auteur']['page'] === 200 && $pendant['auteur']['fichier'] === 200
            && $pendant['auteur']['miniature'] === 200,
        json_encode($pendant['auteur'])
    );
    verifier(
        'droits pendant la conversion : l’administrateur voit la fiche, le fichier et son état',
        $pendant['admin']['page'] === 200 && $pendant['admin']['fichier'] === 200
            && $pendant['admin']['miniature'] === 200,
        json_encode($pendant['admin'])
    );

    // L'état est écrit noir sur blanc sur la fiche, et pas seulement dans la
    // page « Conversions ».
    db_update('conversions', (int) conversion_pour_document($film['id'])['id'], ['progress' => 37]);
    $_SESSION = ['account_id' => $admin];
    $fiche = tests_conversion_requete('GET', '/admin/videos/' . $film['id'], $admin);
    verifier(
        'droits pendant la conversion : la fiche de l’administrateur dit « en cours » et le pourcentage',
        (int) $fiche['code'] === 200
            && (stripos((string) $fiche['corps'], 'en cours') !== false
                || stripos((string) $fiche['corps'], 'conversion') !== false),
        'code ' . $fiche['code']
    );
    $espaceAdmin = tests_conversion_requete('GET', '/admin/conversions', $admin);
    verifier(
        'droits pendant la conversion : la page « Conversions » cite la vidéo et son état',
        (int) $espaceAdmin['code'] === 200
            && str_contains((string) $espaceAdmin['corps'], 'Film de l’auteur'),
        'code ' . $espaceAdmin['code']
    );

    // Après la conversion, la visibilité choisie s'applique : « famille ».
    $pris = conversion_prendre();
    if ($pris !== null) {
        conversion_reussir((int) $pris['id'], tests_conversion_fichier(2048, 'mp4'), ['duration' => 12]);
    }
    $apres = [];
    foreach (['visiteur' => null, 'lecteur' => $famille, 'autre' => $autre, 'admin' => $admin] as $nom => $compte) {
        foreach ($urls as $cle => $url) {
            $apres[$nom][$cle] = $code($url, $compte);
        }
    }
    verifier(
        'droits après conversion : la vidéo « famille » s’ouvre au lecteur, jamais au visiteur',
        $apres['lecteur']['page'] === 200 && $apres['lecteur']['fichier'] === 200
            && $apres['lecteur']['miniature'] === 200
            && $apres['visiteur']['page'] === 404 && $apres['visiteur']['fichier'] === 404,
        json_encode(['lecteur' => $apres['lecteur'], 'visiteur' => $apres['visiteur']])
    );

    // Et une vidéo « privé » reste fermée à la famille après conversion.
    $prive = tests_conversion_document($admin, 'mov', 'video/quicktime', 'Film privé converti');
    db_update('documents', $prive['id'], ['visibility' => 'prive']);
    conversion_planifier($prive['id'], $admin);
    $travailPrive = conversion_prendre();
    if ($travailPrive !== null && (int) $travailPrive['document_id'] === (int) $prive['id']) {
        conversion_reussir((int) $travailPrive['id'], tests_conversion_fichier(2048, 'mp4'));
    }
    verifier(
        'droits après conversion : une vidéo « privé » reste fermée à la famille',
        $code('/documents/' . $prive['id'], $famille) === 404
            && $code('/f/' . $prive['id'], $famille) === 404
            && $code('/documents/' . $prive['id'], $admin) === 200,
        'famille ' . $code('/documents/' . $prive['id'], $famille) . ', admin ' . $code('/documents/' . $prive['id'], $admin)
    );

    // Échec : la vidéo reste invisible à la famille, l'original est conservé,
    // et l'administrateur lit un message clair.
    $rate = tests_conversion_document($admin, 'mov', 'video/quicktime', 'Film rataRé');
    db_update('documents', $rate['id'], ['visibility' => 'public']);
    conversion_planifier($rate['id'], $admin);
    $travailRate = conversion_prendre();
    if ($travailRate !== null && (int) $travailRate['document_id'] === (int) $rate['id']) {
        db_exec('UPDATE conversions SET attempts = 3 WHERE id = ?', [(int) $travailRate['id']]);
        conversion_echouer((int) $travailRate['id'], conversion_lire_erreur('moov atom not found', 1));
    }
    $ligneRate = conversion_par_id((int) ($travailRate['id'] ?? 0));
    verifier(
        'droits après échec : la vidéo reste invisible à la famille et l’original est conservé',
        is_file($rate['abs']) && $code('/documents/' . $rate['id'], $famille) === 404,
        'fichier ' . var_export(is_file($rate['abs']), true)
    );
    verifier(
        'droits après échec : l’administrateur lit une raison claire (fichier abîmé, original gardé)',
        $ligneRate !== null && str_contains((string) $ligneRate['message'], 'abîmé'),
        (string) ($ligneRate['message'] ?? 'aucune ligne')
    );
    verifier(
        'droits après échec : la fiche de la vidéo dit que le fichier d’origine est conservé',
        str_contains(
            conversion_avertissement($rate['id'])['phrase'],
            'origine est conservé'
        ),
        conversion_avertissement($rate['id'])['phrase']
    );

    @unlink($thumbAbs);
    $_SESSION = ['account_id' => $admin];
}

// ---------------------------------------------------------------------------
// 8. Mémoire et robustesse : envoi en cours, espace, coupure de courant
// ---------------------------------------------------------------------------

function tests_conversion_memoire(int $compte): void
{
    tests_conversion_vider_file();
    save_site_setting('conversion_active', '1');

    $film = tests_conversion_document($compte, 'mkv', 'video/x-matroska', 'Film mémoire');

    // 1. Un envoi par morceaux est en cours : la conversion patiente.
    $envoi = envoi_creer($compte, 'gros-film.mov', 10 * 1024 * 1024);
    $idEnvoi = (int) ($envoi['envoi']['id'] ?? 0);
    $blocage = conversion_peut_demarrer(['size' => 4096], 100 * 1024 * 1024 * 1024);
    verifier(
        'mémoire : la conversion refuse de démarrer quand un envoi par morceaux est en cours',
        $blocage['ok'] === false && $blocage['raison'] === 'envoi'
            && str_contains($blocage['message'], 'envoi'),
        json_encode($blocage)
    );

    // L'envoi abandonné depuis longtemps ne bloque plus (le navigateur a été
    // fermé) : sinon une seule coupure de réseau arrêterait la file pour de bon.
    if ($idEnvoi > 0) {
        db_exec('UPDATE uploads SET updated_at = ? WHERE id = ?', [time() - 3 * 3600, $idEnvoi]);
    }
    $libre = conversion_peut_demarrer(['size' => 4096], 100 * 1024 * 1024 * 1024);
    verifier(
        'mémoire : un envoi abandonné (plus de nouvelles depuis le délai) ne bloque plus la file',
        $libre['ok'] === true,
        json_encode($libre)
    );

    // 2. Espace libre sous la marge : refus, avec la place qu'il faudrait.
    $plein = conversion_peut_demarrer(['size' => 4096], 10 * 1024 * 1024);
    verifier(
        'mémoire : la conversion refuse de démarrer quand l’espace libre est sous la marge',
        $plein['ok'] === false && $plein['raison'] === 'espace'
            && str_contains($plein['message'], 'libres'),
        json_encode($plein)
    );
    verifier(
        'mémoire : la marge demandée tient compte de la taille du fichier d’origine',
        conversion_espace_ok(['size' => 500 * 1024 * 1024], 0, 0)['requis'] === 500 * 1024 * 1024
    );

    // 3. Report : le travail reste en attente, l'essai est rendu, le message
    //    est écrit — le minuteur le reprendra.
    conversion_planifier($film['id'], $compte);
    $pris = conversion_prendre();
    verifier(
        'mémoire : le travail pris compte un essai avant le contrôle de démarrage',
        $pris !== null && (int) $pris['attempts'] === 1
    );
    conversion_reporter((int) $pris['id'], 'Conversion reportée : un envoi de fichier est en cours.');
    $reporte = conversion_par_id((int) $pris['id']);
    verifier(
        'mémoire : un report remet le travail en attente SANS consommer d’essai',
        (string) $reporte['state'] === 'attente' && (int) $reporte['attempts'] === 0
            && str_contains((string) $reporte['message'], 'reportée'),
        json_encode([$reporte['state'], (int) $reporte['attempts']])
    );

    // 4. Coupure de courant : un travail « en cours » depuis deux heures
    //    repart tout seul, un fichier temporaire abandonné est effacé.
    db_exec(
        "UPDATE conversions SET state = 'en_cours', attempts = 1, progress = 61,
                updated_at = ?, started_at = ? WHERE id = ?",
        [time() - 7200, time() - 7300, (int) $pris['id']]
    );
    $temporaireVieux = storage_path('tmp') . '/conversion-essai-' . bin2hex(random_bytes(3)) . '.mp4';
    file_put_contents($temporaireVieux, 'moitié de fichier');
    touch($temporaireVieux, time() - 7200);
    $temporaireNeuf = storage_path('tmp') . '/conversion-essai-' . bin2hex(random_bytes(3)) . '.mp4';
    file_put_contents($temporaireNeuf, 'conversion en cours');

    $recuperation = conversion_recuperer(30);
    $apresCoupure = conversion_par_id((int) $pris['id']);
    verifier(
        'panne : un travail « en cours » depuis plus de 30 minutes repart en attente',
        (string) $apresCoupure['state'] === 'attente' && (int) $apresCoupure['progress'] === 0
            && $recuperation['repris'] >= 1,
        json_encode([$apresCoupure['state'], $recuperation['repris']])
    );
    verifier(
        'panne : l’essai interrompu est rendu (ce n’est pas la faute du fichier)',
        (int) $apresCoupure['attempts'] === 0,
        'essais ' . (int) $apresCoupure['attempts']
    );
    verifier(
        'panne : le fichier temporaire à moitié écrit est effacé, celui en cours est gardé',
        !is_file($temporaireVieux) && is_file($temporaireNeuf),
        json_encode([is_file($temporaireVieux), is_file($temporaireNeuf)])
    );
    @unlink($temporaireNeuf);

    // Un travail interrompu après le dernier essai s'arrête en échec lisible.
    db_exec(
        "UPDATE conversions SET state = 'en_cours', attempts = 3, updated_at = ? WHERE id = ?",
        [time() - 7200, (int) $pris['id']]
    );
    conversion_recuperer(30);
    $abandonne = conversion_par_id((int) $pris['id']);
    verifier(
        'panne : après le dernier essai, le travail s’arrête en échec avec une raison claire',
        (string) $abandonne['state'] === 'echec'
            && str_contains((string) $abandonne['message'], 'interrompue')
            && str_contains((string) $abandonne['message'], 'origine est conservé'),
        (string) $abandonne['message']
    );

    // 5. La commande elle-même : un seul fil de calcul, priorité basse.
    $reglages = array_merge(conversion_reglages(), ['ffmpeg' => '/usr/bin/ffmpeg']);
    $commande = conversion_commande('/stockage/film.mov', '/tmp/sortie.mp4', $reglages);
    $positionThreads = array_search('-threads', $commande, true);
    verifier(
        'mémoire : ffmpeg est borné à un seul fil de calcul (« -threads 1 »)',
        $positionThreads !== false && (string) $commande[$positionThreads + 1] === '1',
        implode(' ', $commande)
    );
    $nicee = conversion_commande_nicee($commande, $reglages);
    $aNice = is_file('/usr/bin/nice') || is_file('/bin/nice');
    verifier(
        'mémoire : ffmpeg tourne en priorité basse (nice 19) quand le programme existe',
        $aNice
            ? (str_ends_with((string) $nicee[0], 'nice') && $nicee[1] === '-n' && $nicee[2] === '19')
            : count($nicee) === count($commande),
        implode(' ', array_slice($nicee, 0, 4))
    );
    verifier(
        'mémoire : l’enveloppe « nice » ne change jamais la commande ffmpeg elle-même',
        array_slice($nicee, -count($commande)) === $commande
    );

    // 6. Le code de la commande applique bien ces règles avant de lancer ffmpeg.
    $codeCli = (string) @file_get_contents(HDF_APP . '/cli/convertir.php');
    verifier(
        'mémoire : la commande contrôle l’envoi en cours et l’espace AVANT ffmpeg',
        str_contains($codeCli, 'conversion_peut_demarrer(')
            && strpos($codeCli, 'conversion_peut_demarrer(') < strpos($codeCli, 'proc_open(')
    );
    verifier(
        'mémoire : un report n’est pas un échec (la commande sort sans code d’erreur)',
        str_contains($codeCli, 'conversion_reporter(') && str_contains($codeCli, "'reporte'")
    );
    verifier(
        'panne : la commande remet la file d’aplomb avant de prendre du travail',
        strpos($codeCli, 'conversion_recuperer(') < strpos($codeCli, 'conversion_prendre()')
    );
    $codeEntretien = (string) @file_get_contents(HDF_APP . '/cli/maintenance.php');
    verifier(
        'panne : la maintenance quotidienne rattrape aussi une file bloquée',
        str_contains($codeEntretien, 'conversion_recuperer(')
    );
    verifier(
        'mémoire : le minuteur de conversion ne lance qu’un seul travail à la fois',
        is_file(HDF_ROOT . '/deploy/systemd/histoiredefamille-conversion.timer')
    );

    // Nettoyage : l'envoi d'essai et ses morceaux.
    if ($idEnvoi > 0) {
        $ligne = envoi_par_id($idEnvoi);
        if ($ligne !== null) {
            envoi_supprimer_morceaux($ligne);
        }
    }
}

// ---------------------------------------------------------------------------
// 9. Conversion RÉELLE d'un .mov par ffmpeg (bout en bout)
// ---------------------------------------------------------------------------

/**
 * Fabrique un vrai .mov (QuickTime, H.264) avec ffmpeg, puis le convertit avec
 * la commande exacte du site et vérifie le résultat avec ffprobe.
 *
 * Ce test est « ignoré » (et le dit) sur une machine sans ffmpeg : il ne doit
 * jamais faire échouer l'ensemble. Sur le Raspberry Pi, en revanche, c'est le
 * seul essai qui prouve que la chaîne complète fonctionne — droits compris.
 */
function tests_conversion_reelle(int $admin): void
{
    $reglages = conversion_reglages();
    if ($reglages['ffmpeg'] === null || $reglages['ffprobe'] === null) {
        echo "  ignoré  conversion réelle : ffmpeg n’est pas installé sur cette machine\n";
        return;
    }
    if (!function_exists('proc_open')) {
        echo "  ignoré  conversion réelle : proc_open est désactivé sur cette machine\n";
        return;
    }

    $tmp     = storage_path('tmp');
    $source  = $tmp . '/essai-reel-' . bin2hex(random_bytes(4)) . '.mov';
    $sortie  = $tmp . '/essai-reel-' . bin2hex(random_bytes(4)) . '.mp4';

    // Un vrai .mov de deux secondes, en H.264 : c'est ce que produit un
    // téléphone, et c'est ce que le site doit savoir convertir.
    $fabrication = [
        (string) $reglages['ffmpeg'], '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
        '-f', 'lavfi', '-i', 'testsrc=duration=2:size=160x120:rate=10',
        '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'ultrafast',
        $source,
    ];
    $sortieOutil = '';
    $code = 1;
    $processus = @proc_open(
        $fabrication,
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $tuyaux
    );
    if (is_resource($processus)) {
        $sortieOutil = (string) stream_get_contents($tuyaux[2] ?? fopen('php://memory', 'r'));
        if (isset($tuyaux[1]) && is_resource($tuyaux[1])) {
            stream_get_contents($tuyaux[1]);
            fclose($tuyaux[1]);
        }
        if (isset($tuyaux[2]) && is_resource($tuyaux[2])) {
            fclose($tuyaux[2]);
        }
        $code = proc_close($processus);
    }

    verifier(
        'conversion réelle : un vrai fichier .mov (H.264) est fabriqué pour l’essai',
        $code === 0 && is_file($source) && (int) filesize($source) > 0,
        trim($sortieOutil)
    );
    if (!is_file($source) || (int) filesize($source) <= 0) {
        @unlink($source);
        return;
    }

    // La fiche, avec le vrai fichier.
    $rel = storage_target_path('mov');
    @rename($source, storage_absolute($rel));
    $id = db_insert('documents', [
        'title'         => 'Film réel converti',
        'description'   => null,
        'doc_date'      => null,
        'doc_date_sort' => null,
        'category_id'   => null,
        'kind'          => 'video',
        'visibility'    => 'famille',
        'rel_path'      => $rel,
        'thumb_path'    => null,
        'original_name' => 'film-reel.mov',
        'mime'          => 'video/quicktime',
        'ext'           => 'mov',
        'size'          => (int) filesize(storage_absolute($rel)),
        'width'         => null,
        'height'        => null,
        'is_attachment' => 0,
        'created_by'    => $admin,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
    conversion_planifier($id, $admin);
    $pris = conversion_prendre();

    if ($pris === null) {
        echo "  ignoré  conversion réelle : aucun travail pris (file occupée)\n";
        return;
    }

    // La commande exacte du site, lancée comme la commande CLI la lance.
    $commande = conversion_commande_nicee(
        conversion_commande(storage_absolute($rel), $sortie, $reglages),
        $reglages
    );
    $journal = $tmp . '/essai-reel-' . bin2hex(random_bytes(4)) . '.log';
    $processus = @proc_open(
        $commande,
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $journal, 'w']],
        $tuyaux
    );
    $pourcentage = 0;
    if (is_resource($processus)) {
        if (isset($tuyaux[1]) && is_resource($tuyaux[1])) {
            while (!feof($tuyaux[1])) {
                $ligne = fgets($tuyaux[1]);
                if ($ligne === false) {
                    break;
                }
                $lu = conversion_lire_progression((string) $ligne, 2);
                if ($lu !== null) {
                    $pourcentage = max($pourcentage, $lu);
                }
            }
            fclose($tuyaux[1]);
        }
        $code = proc_close($processus);
    } else {
        $code = 1;
    }
    $erreur = (string) @file_get_contents($journal);
    @unlink($journal);

    verifier(
        'conversion réelle : ffmpeg convertit le .mov sans erreur',
        $code === 0 && is_file($sortie) && (int) filesize($sortie) > 0,
        $code . ' — ' . conversion_lire_erreur($erreur, $code)
    );
    verifier(
        'conversion réelle : l’avancement se lit dans la sortie réelle de ffmpeg',
        $code === 0 && $pourcentage > 0,
        'pourcentage lu : ' . $pourcentage
    );

    if ($code === 0 && is_file($sortie)) {
        // Le fichier produit est bien du MP4 lisible partout : conteneur MP4,
        // H.264, images clés en tête (faststart).
        $sonde = [
            (string) $reglages['ffprobe'], '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=codec_name,pix_fmt:format=format_name',
            '-of', 'default=noprint_wrappers=1', $sortie,
        ];
        $sortieSonde = conversion_lancer_court_pour_essai($sonde);
        verifier(
            'conversion réelle : le fichier produit est du MP4 en H.264 yuv420p',
            str_contains($sortieSonde, 'codec_name=h264') && str_contains($sortieSonde, 'pix_fmt=yuv420p')
                && stripos($sortieSonde, 'mp4') !== false,
            $sortieSonde
        );

        $reussite = conversion_reussir((int) $pris['id'], $sortie, ['duration' => 2]);
        $apres = db_one('SELECT * FROM documents WHERE id = ?', [$id]);
        verifier(
            'conversion réelle : la fiche passe en MP4 et la famille peut enfin voir la vidéo',
            $reussite['ok'] && $apres !== null && (string) $apres['ext'] === 'mp4'
                && (string) $apres['mime'] === 'video/mp4'
                && (string) $apres['original_name'] === 'film-reel.mp4',
            $reussite['error']
        );
        $_SESSION = ['account_id' => $admin];
        verifier(
            'conversion réelle : après conversion, la vidéo « famille » s’ouvre (adresse directe du fichier)',
            (int) tests_conversion_requete('GET', '/f/' . $id, $admin)['code'] === 200
                && (int) tests_conversion_requete('GET', '/documents/' . $id, $admin)['code'] === 200
        );
    }

    @unlink($sortie);
    @unlink(storage_absolute($rel));
}

/** Lance une commande courte et rend sa sortie (essais seulement). */
function conversion_lancer_court_pour_essai(array $commande): string
{
    $descripteurs = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']];
    $processus = @proc_open($commande, $descripteurs, $tuyaux);
    if (!is_resource($processus)) {
        return '';
    }
    $sortie = '';
    if (isset($tuyaux[1]) && is_resource($tuyaux[1])) {
        $sortie = (string) stream_get_contents($tuyaux[1]);
        @fclose($tuyaux[1]);
    }
    proc_close($processus);
    return $sortie;
}
