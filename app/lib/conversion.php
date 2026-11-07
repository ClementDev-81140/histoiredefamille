<?php
/**
 * Conversion des vidéos en MP4 (H.264 + AAC), une seule à la fois.
 *
 * Pourquoi ? Un téléphone, un appareil photo et un caméscope produisent des
 * fichiers que tous les navigateurs ne savent pas lire (MOV, AVI, MKV, WebM,
 * HEVC…). Le site ne peut pas convertir au moment de l'envoi : un Raspberry Pi
 * mettrait des dizaines de minutes, la requête serait coupée, et la famille
 * attendrait devant une page blanche.
 *
 * D'où la file d'attente : l'envoi se contente d'inscrire le travail dans la
 * table `conversions`, et une commande séparée (app/cli/convertir.php, lancée
 * par un minuteur systemd) le fait tranquillement, un fichier à la fois. Ce
 * fichier ne lance JAMAIS ffmpeg : il tient la file, l'état, et les règles.
 * Le lancement du programme externe est le seul travail de la commande.
 *
 * Règles tenues ici :
 *   - un seul travail à la fois : la prise d'un travail est atomique
 *     (« UPDATE … WHERE state = 'attente' », une ligne au plus) ;
 *   - trois essais, puis l'échec est affiché avec sa raison, jamais masqué ;
 *   - l'original n'est jamais effacé sans un réglage explicite, et jamais s'il
 *     est encore référencé par une fiche ;
 *   - la fiche n'est modifiée qu'à la fin, en une seule transaction, quand le
 *     fichier converti est complet : aucune vidéo à moitié écrite n'est servie ;
 *   - rien ne dépend de la présence de ffmpeg pour fonctionner : sans lui, la
 *     file attend et la page le dit.
 */

declare(strict_types=1);

/** États d'une conversion (dans l'ordre du parcours). */
function conversion_etats(): array
{
    return ['attente', 'en_cours', 'terminee', 'echec', 'ignoree'];
}

/** Libellé français d'un état, pour l'écran. */
function conversion_libelle_etat(string $state): string
{
    return match ($state) {
        'attente'  => 'En attente',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'echec'    => 'Échec',
        'ignoree'  => 'Annulée',
        default    => 'Inconnue',
    };
}

/**
 * Réglages de la conversion, bornés et lisibles d'un coup d'œil.
 *
 * @return array{active:bool, disponible:bool, ffmpeg:?string, ffprobe:?string,
 *               qualite:string, preset:string, crf:int, audio_kbps:int,
 *               hauteur_max:int, supprimer_original:bool, essais_max:int,
 *               timeout_minutes:int, garde_secondes:int}
 */
function conversion_reglages(): array
{
    $outils = conversion_outils();

    $qualite = site_setting('conversion_qualite');
    if (!in_array($qualite, ['leger', 'equilibre', 'haute'], true)) {
        $qualite = 'equilibre';
    }

    // Trois qualités, trois jeux de valeurs. « Équilibré » est le compromis
    // retenu par défaut : lisible sur un téléphone, léger pour le SSD.
    $profils = [
        'leger'     => ['crf' => 26, 'audio' => 96,  'hauteur' => 720],
        'equilibre' => ['crf' => 23, 'audio' => 128, 'hauteur' => 1080],
        'haute'     => ['crf' => 20, 'audio' => 160, 'hauteur' => 0],
    ];
    $profil = $profils[$qualite];

    // Le preset change la vitesse de conversion, pas la qualité visée : sur un
    // Pi, « veryfast » est le seul raisonnable (environ 3 à 6 fois plus rapide
    // que « medium » pour un fichier un peu plus gros).
    return [
        'active'             => site_setting('conversion_active') !== '0',
        'disponible'         => $outils['ffmpeg'] !== null && $outils['ffprobe'] !== null,
        'ffmpeg'             => $outils['ffmpeg'],
        'ffprobe'            => $outils['ffprobe'],
        'qualite'            => $qualite,
        'preset'             => 'veryfast',
        'crf'                => $profil['crf'],
        'audio_kbps'         => $profil['audio'],
        'hauteur_max'        => $profil['hauteur'],
        'supprimer_original' => site_setting('conversion_supprimer_original') === '1',
        'essais_max'         => 3,
        'timeout_minutes'    => 180,
        'garde_secondes'     => 120,
        // Un Pi 3B+ n'a qu'un gigaoctet de mémoire pour quatre cœurs lents :
        // x264 en « threads 1 » reste le seul réglage qui ne fait pas gonfler
        // la mémoire ni échanger la carte SD. La priorité basse (nice 19)
        // laisse le site répondre pendant la conversion.
        'threads'            => 1,
        'nice'               => 19,
    ];
}

/**
 * Marge d'espace libre exigée avant de commencer une conversion.
 *
 * Une vidéo convertie pèse à peu près son original ; ffmpeg écrit le fichier
 * temporaire dans `tmp/` pendant que l'original reste en place. Sur une carte
 * SD presque pleine, lancer la conversion remplit le disque, casse la
 * conversion… et empêche la base d'écrire. On refuse donc de démarrer sous la
 * marge (1 Go par défaut, réglable dans config.local.php).
 */
function conversion_marge_libre(): int
{
    return max(0, (int) config('conversion.min_free_mb', 1024)) * 1048576;
}

/** Espace libre, en octets, sur le disque des données (null si inconnu). */
function conversion_espace_libre(): ?int
{
    $racine = (string) config('storage.root');
    if ($racine === '') {
        return null;
    }
    $libre = @disk_free_space($racine);
    return $libre === false ? null : (int) $libre;
}

/**
 * Peut-on convertir ce document sans risquer de remplir le disque ?
 *
 * La place exigée est la marge plus la taille du fichier d'origine : le
 * résultat peut être plus gros (rare) ou plus petit (fréquent), on prend
 * l'hypothèse la plus prudente.
 *
 * Les deux derniers paramètres servent aux tests : ils évitent de dépendre de
 * l'état réel du disque de la machine. `$libre` vaut -1 pour « interroger le
 * disque » (le cas normal), null pour « espace inconnu ».
 *
 * @return array{ok:bool, libre:?int, requis:int, message:string}
 */
function conversion_espace_ok(array $doc, ?int $libre = -1, ?int $marge = null): array
{
    $marge  = $marge ?? conversion_marge_libre();
    $libre  = $libre === -1 ? conversion_espace_libre() : $libre;
    $requis = $marge + max(0, (int) ($doc['size'] ?? 0));

    if ($libre === null) {
        // Disque inconnu : on ne bloque pas une conversion pour ça, le reste
        // du contrôle (écriture du fichier temporaire) tranchera.
        return ['ok' => true, 'libre' => null, 'requis' => $requis, 'message' => ''];
    }

    if ($libre >= $requis) {
        return ['ok' => true, 'libre' => $libre, 'requis' => $requis, 'message' => ''];
    }

    return [
        'ok'      => false,
        'libre'   => $libre,
        'requis'  => $requis,
        'message' => 'Conversion impossible : il faut '
            . format_size($requis) . ' libres (marge de sécurité comprise) et il n’en reste que '
            . format_size($libre) . '. Libérez de la place, puis relancez la conversion.',
    ];
}

/**
 * Emplacements de ffmpeg et ffprobe.
 *
 * Deux endroits, dans cet ordre : config.local.php (clé `conversion.ffmpeg`)
 * puis /usr/bin. Un chemin qui n'est pas un programme exécutable est ignoré :
 * mieux vaut dire « pas d'outil » que lancer n'importe quoi.
 *
 * @return array{ffmpeg:?string, ffprobe:?string}
 */
function conversion_outils(): array
{
    // Outils imposés (essais seulement) : sans cela, un test « ffmpeg absent »
    // réussirait ou échouerait selon la machine qui l'exécute (un Pi a ffmpeg,
    // le bac à sable de test ne l'a pas). Voir conversion_outils_forcer().
    $forces = $GLOBALS['hdf_conversion_outils'] ?? null;
    if (is_array($forces)) {
        return [
            'ffmpeg'  => isset($forces['ffmpeg']) ? (string) $forces['ffmpeg'] : null,
            'ffprobe' => isset($forces['ffprobe']) ? (string) $forces['ffprobe'] : null,
        ];
    }

    $trouver = static function (string $cle, string $defaut): ?string {
        $candidats = [];
        $regle = (string) config('conversion.' . $cle, '');
        if ($regle !== '') {
            $candidats[] = $regle;
        }
        $candidats[] = $defaut;
        // Repli : le programme est dans le PATH (installation Homebrew, /usr/local…).
        $chemin = getenv('PATH');
        if (is_string($chemin)) {
            foreach (explode(PATH_SEPARATOR, $chemin) as $dossier) {
                if ($dossier !== '') {
                    $candidats[] = rtrim($dossier, '/') . '/' . $cle;
                }
            }
        }

        foreach ($candidats as $candidat) {
            if ($candidat !== '' && @is_file($candidat) && @is_executable($candidat)) {
                return $candidat;
            }
        }
        return null;
    };

    return [
        'ffmpeg'  => $trouver('ffmpeg', '/usr/bin/ffmpeg'),
        'ffprobe' => $trouver('ffprobe', '/usr/bin/ffprobe'),
    ];
}

/**
 * Impose les chemins de ffmpeg et ffprobe (essais seulement).
 *
 * Passer null rétablit la recherche réelle. Le réglage ne survit pas à la
 * requête : il ne sert qu'à rendre les tests indépendants de la machine.
 *
 * @param array{ffmpeg?:?string, ffprobe?:?string}|null $outils
 */
function conversion_outils_forcer(?array $outils): void
{
    if ($outils === null) {
        unset($GLOBALS['hdf_conversion_outils']);
        return;
    }
    $GLOBALS['hdf_conversion_outils'] = $outils;
}

/**
 * Cette fiche a-t-elle besoin d'être convertie ?
 *
 * Un MP4 déjà en H.264 est laissé tel quel : le convertir à nouveau
 * n'apporterait rien et abîmerait l'image (double encodage). On se fie au
 * conteneur et au type déclaré ; le cas « MP4 en HEVC » est rattrapé par la
 * commande, qui vérifie le codec avec ffprobe avant de travailler.
 */
function conversion_necessaire(array $doc): bool
{
    if ((int) ($doc['is_attachment'] ?? 0) === 1) {
        return false;
    }

    $ext  = strtolower((string) ($doc['ext'] ?? ''));
    $mime = strtolower((string) ($doc['mime'] ?? ''));

    if ((string) ($doc['kind'] ?? '') === 'video') {
        // Un MP4 en H.264 est laissé tel quel : le reconvertir abîmerait
        // l'image (double encodage) et ne ferait gagner que des octets.
        return !(in_array($ext, ['mp4', 'm4v'], true) && $mime === 'video/mp4');
    }

    // Témoignages et recettes : un WAV ou un AMR n'est lu par aucun navigateur.
    if (str_starts_with($mime, 'audio/')) {
        if (in_array($ext, ['mp3', 'm4a', 'ogg'], true)) {
            return false;
        }
        return in_array($ext, ['wav', 'amr'], true);
    }

    return false;
}

/** Cette fiche est-elle un son (et non une vidéo) ? */
function conversion_est_audio(array $doc): bool
{
    return (string) ($doc['kind'] ?? '') !== 'video'
        && (str_starts_with(strtolower((string) ($doc['mime'] ?? '')), 'audio/')
            || in_array(strtolower((string) ($doc['ext'] ?? '')), ['wav', 'amr'], true));
}

/** La conversion est-elle active (réglage) et possible (outils présents) ? */
function conversion_active(): bool
{
    $reglages = conversion_reglages();
    return $reglages['active'] && $reglages['disponible'];
}

/** Une conversion par identifiant. */
function conversion_par_id(int $id): ?array
{
    return db_one('SELECT * FROM conversions WHERE id = ?', [$id]);
}

/** La conversion en vigueur pour une fiche (une seule par fiche). */
function conversion_pour_document(int $documentId): ?array
{
    return db_one('SELECT * FROM conversions WHERE document_id = ?', [$documentId]);
}

/**
 * État lisible d'une fiche vidéo : 'aucune', 'attente', 'en_cours',
 * 'terminee', 'echec' ou 'ignoree'.
 *
 * Utilisé par les écrans pour dire honnêtement « en préparation » plutôt que
 * de laisser croire qu'une vidéo est prête.
 *
 * @return array{state:string, progress:int, message:string}
 */
function conversion_etat_document(int $documentId): array
{
    $ligne = conversion_pour_document($documentId);
    if ($ligne === null) {
        return ['state' => 'aucune', 'progress' => 0, 'message' => ''];
    }
    return [
        'state'    => (string) $ligne['state'],
        'progress' => (int) $ligne['progress'],
        'message'  => (string) $ligne['message'],
    ];
}

/**
 * Inscrit une vidéo dans la file d'attente (ou remet à zéro son travail).
 *
 * Ne lève jamais d'exception pour un cas normal : une fiche qui n'a pas
 * besoin d'être convertie renvoie ['ok'=>true, 'ignore'=>true]. Les appelants
 * (formulaires) ne doivent donc pas échouer parce que la conversion est
 * impossible : le fichier reste utilisable tel quel.
 *
 * @return array{ok:bool, error:string, ignore:bool, id:int, state:string}
 */
function conversion_planifier(int $documentId, ?int $compteId = null, bool $forcer = false): array
{
    $resultat = ['ok' => true, 'error' => '', 'ignore' => false, 'id' => 0, 'state' => ''];

    $doc = db_one('SELECT * FROM documents WHERE id = ?', [$documentId]);
    if ($doc === null) {
        return ['ok' => false, 'error' => 'Fiche introuvable.', 'ignore' => true, 'id' => 0, 'state' => ''];
    }

    if (!$forcer && !conversion_necessaire($doc)) {
        $resultat['ignore'] = true;
        return $resultat;
    }
    if (!$forcer && !conversion_reglages()['active']) {
        $resultat['ignore'] = true;
        return $resultat;
    }

    // Pas de conversion qui remplirait la carte : le refus est explicite et
    // le fichier d'origine reste en place, utilisable tel quel.
    $espace = conversion_espace_ok($doc);
    if (!$espace['ok']) {
        return ['ok' => false, 'error' => $espace['message'], 'ignore' => false, 'id' => 0, 'state' => ''];
    }

    $rel = (string) $doc['rel_path'];
    if ($rel === '' || !is_file(storage_absolute($rel))) {
        return ['ok' => false, 'error' => 'Le fichier de cette vidéo est introuvable.', 'ignore' => true, 'id' => 0, 'state' => ''];
    }

    $actuel = conversion_pour_document($documentId);
    $donnees = [
        'document_id'     => $documentId,
        'state'           => 'attente',
        'attempts'        => 0,
        'progress'        => 0,
        'message'         => '',
        'source_path'     => $rel,
        'source_size'     => (int) ($doc['size'] ?? 0),
        'target_path'     => null,
        'original_name'   => (string) ($doc['original_name'] ?? ''),
        'original_ext'    => strtolower((string) ($doc['ext'] ?? '')),
        'duration_seconds' => isset($doc['duration_seconds']) ? (int) $doc['duration_seconds'] : null,
        'requested_by'    => $compteId,
        'started_at'      => null,
        'finished_at'     => null,
        'updated_at'      => time(),
    ];

    if ($actuel === null) {
        $donnees['created_at'] = time();
        $id = db_insert('conversions', $donnees);
    } else {
        $id = (int) $actuel['id'];
        db_update('conversions', $id, $donnees);
    }

    $resultat['id']    = $id;
    $resultat['state'] = 'attente';
    return $resultat;
}

/**
 * Inscrit une fiche dans la file, sans jamais gêner l'enregistrement du
 * formulaire ni l'envoi d'un fichier : une erreur est journalisée, point.
 */
function conversion_planifier_document(int $documentId): void
{
    try {
        $compte = current_account();
        conversion_planifier($documentId, $compte !== null ? (int) $compte['id'] : null);
    } catch (Throwable $e) {
        error_log('Conversion non planifiée pour la fiche ' . $documentId . ' : ' . $e->getMessage());
    }
}

/**
 * Prend le travail le plus ancien, une seule fois.
 *
 * L'atomicité tient dans le UPDATE conditionnel : si deux commandes tournent
 * en même temps (minuteur + lancement à la main), une seule obtient la ligne.
 */
function conversion_prendre(): ?array
{
    $candidat = db_one(
        "SELECT * FROM conversions WHERE state = 'attente' ORDER BY created_at, id LIMIT 1"
    );
    if ($candidat === null) {
        return null;
    }

    $pris = db_exec(
        "UPDATE conversions SET state = 'en_cours', attempts = attempts + 1, progress = 0,
                message = '', started_at = ?, updated_at = ?
         WHERE id = ? AND state = 'attente'",
        [time(), time(), (int) $candidat['id']]
    );

    if ($pris !== 1) {
        return null;
    }

    return conversion_par_id((int) $candidat['id']);
}

/** Avancement (0 à 99 : 100 est réservé à la réussite). */
function conversion_progresser(int $id, int $pourcent, string $message = ''): void
{
    $pourcent = max(0, min(99, $pourcent));
    if ($message !== '') {
        db_exec(
            "UPDATE conversions SET progress = ?, message = ?, updated_at = ? WHERE id = ? AND state = 'en_cours'",
            [$pourcent, mb_substr($message, 0, 300, 'UTF-8'), time(), $id]
        );
        return;
    }
    db_exec(
        "UPDATE conversions SET progress = ?, updated_at = ? WHERE id = ? AND state = 'en_cours'",
        [$pourcent, time(), $id]
    );
}

/** La conversion a-t-elle été annulée pendant son exécution ? */
function conversion_abandonnee(int $id): bool
{
    $ligne = conversion_par_id($id);
    return $ligne === null || (string) $ligne['state'] !== 'en_cours';
}

/**
 * Échec : nouvel essai si l'on n'a pas dépassé le quota, sinon échec définitif.
 *
 * Le message est toujours en français simple : c'est ce que l'administrateur
 * lira dans la page « Conversions ».
 */
function conversion_echouer(int $id, string $message): void
{
    $ligne = conversion_par_id($id);
    if ($ligne === null) {
        return;
    }
    $reglages = conversion_reglages();
    $message  = trim($message);
    if ($message === '') {
        $message = 'La conversion a échoué sans message.';
    }
    $message = mb_substr($message, 0, 500, 'UTF-8');

    $essais = (int) $ligne['attempts'];
    $etat   = $essais < (int) $reglages['essais_max'] ? 'attente' : 'echec';

    db_exec(
        'UPDATE conversions SET state = ?, message = ?, progress = 0, updated_at = ?, finished_at = ?
         WHERE id = ?',
        [$etat, $message, time(), $etat === 'echec' ? time() : null, $id]
    );
}

/**
 * Réussite : le fichier converti prend la place de l'ancien, en une transaction.
 *
 * @param string $cheminFichier fichier produit par ffmpeg (chemin absolu)
 * @param array  $infos         durée en secondes éventuelle : ['duration'=>int]
 * @return array{ok:bool, error:string}
 */
function conversion_reussir(int $id, string $cheminFichier, array $infos = []): array
{
    $ligne = conversion_par_id($id);
    if ($ligne === null) {
        return ['ok' => false, 'error' => 'Travail introuvable.'];
    }

    $doc = db_one('SELECT * FROM documents WHERE id = ?', [(int) $ligne['document_id']]);
    if ($doc === null) {
        return ['ok' => false, 'error' => 'La fiche a été supprimée pendant la conversion.'];
    }

    if (!is_file($cheminFichier) || (int) @filesize($cheminFichier) <= 0) {
        return ['ok' => false, 'error' => 'Le fichier converti est vide ou introuvable.'];
    }

    // Le fichier définitif est rangé comme n'importe quel envoi : nom aléatoire,
    // sous-dossier année/mois, hors de la racine web.
    $audio = conversion_est_audio($doc);
    $rel   = storage_target_path($audio ? 'm4a' : 'mp4');
    $dest = storage_absolute($rel);
    $ok   = @rename($cheminFichier, $dest);
    if (!$ok) {
        $ok = @copy($cheminFichier, $dest);
        if ($ok) {
            @unlink($cheminFichier);
        }
    }
    if (!$ok) {
        return ['ok' => false, 'error' => 'Impossible de ranger le fichier converti (droits du dossier ?).'];
    }
    @chmod($dest, 0640);

    $taille     = (int) @filesize($dest);
    $ancienRel  = (string) $doc['rel_path'];
    $ancienNom  = (string) $doc['original_name'];
    $base       = pathinfo($ancienNom !== '' ? $ancienNom : (string) $doc['title'], PATHINFO_FILENAME);
    $extension  = $audio ? 'm4a' : 'mp4';
    $nouveauNom = mb_substr(($base !== '' ? $base : ($audio ? 'son' : 'video')) . '.' . $extension, 0, 200, 'UTF-8');
    $duree      = isset($infos['duration']) ? max(0, (int) $infos['duration']) : null;

    db_transaction(static function () use ($doc, $rel, $taille, $nouveauNom, $duree, $id, $audio, $extension): void {
        $maj = [
            'rel_path'      => $rel,
            'ext'           => $extension,
            'mime'          => $audio ? 'audio/mp4' : 'video/mp4',
            'size'          => $taille,
            'original_name' => $nouveauNom,
            'updated_at'    => time(),
        ];
        if ($duree !== null) {
            $maj['duration_seconds'] = $duree;
        }
        db_update('documents', (int) $doc['id'], $maj);

        db_update('conversions', $id, [
            'state'       => 'terminee',
            'progress'    => 100,
            'message'     => $audio ? 'Converti en M4A (AAC).' : 'Converti en MP4 (H.264 + AAC).',
            'target_path' => $rel,
            'finished_at' => time(),
            'updated_at'  => time(),
        ]);
    });

    // Une vidéo convertie reçoit son image d'aperçu une fois le fichier en
    // place (1.32.0). Un son n'en a pas besoin ; un échec de ffmpeg ne remet
    // pas la conversion en cause (la miniature est un confort, et la commande
    // « miniatures-videos.php » sait la rattraper).
    if (!$audio) {
        $miniature = video_miniature_document((int) $doc['id']);
        if (!$miniature['ok'] && $miniature['error'] !== '') {
            error_log('Miniature de vidéo impossible après conversion (document ' . (int) $doc['id'] . ') : ' . $miniature['error']);
        }
    }

    // L'original n'est effacé que si on l'a demandé, et jamais s'il est encore
    // le fichier d'une autre fiche (copie, remplacement partiel…).
    if (conversion_reglages()['supprimer_original'] && $ancienRel !== '' && $ancienRel !== $rel) {
        $encoreUtilise = (int) db_value(
            'SELECT COUNT(*) FROM documents WHERE rel_path = ? AND id <> ?',
            [$ancienRel, (int) $doc['id']],
            0
        );
        if ($encoreUtilise === 0) {
            storage_delete($ancienRel);
        }
    }

    log_activity(null, 'video_convertie', $ancienNom . ' → ' . $nouveauNom);
    return ['ok' => true, 'error' => ''];
}

/**
 * Remet un travail en attente (après un échec, ou pour forcer un nouvel essai).
 *
 * Les essais repartent de zéro : sinon, un fichier relancé à la main
 * échouerait aussitôt. La source est relue sur la fiche, au cas où le fichier
 * aurait changé entre-temps.
 */
function conversion_relancer(int $id): array
{
    $ligne = conversion_par_id($id);
    if ($ligne === null) {
        return ['ok' => false, 'error' => 'Travail introuvable.'];
    }
    if ((string) $ligne['state'] === 'en_cours') {
        return ['ok' => false, 'error' => 'Cette conversion est en cours : attendez qu’elle se termine.'];
    }

    $doc = db_one('SELECT * FROM documents WHERE id = ?', [(int) $ligne['document_id']]);
    if ($doc === null) {
        return ['ok' => false, 'error' => 'La fiche a été supprimée.'];
    }
    if ((string) $doc['kind'] !== 'video') {
        return ['ok' => false, 'error' => 'Cette fiche n’est pas une vidéo.'];
    }
    if (!is_file(storage_absolute((string) $doc['rel_path']))) {
        return ['ok' => false, 'error' => 'Le fichier de cette vidéo est introuvable.'];
    }

    db_update('conversions', $id, [
        'state'        => 'attente',
        'attempts'     => 0,
        'progress'     => 0,
        'message'      => '',
        'source_path'  => (string) $doc['rel_path'],
        'source_size'  => (int) $doc['size'],
        'target_path'  => null,
        'started_at'   => null,
        'finished_at'  => null,
        'updated_at'   => time(),
    ]);

    return ['ok' => true, 'error' => ''];
}

/**
 * Annule un travail. Pendant une conversion, la commande s'arrête au prochain
 * point d'avancement (elle vérifie l'état à chaque palier) et efface son
 * fichier temporaire : le document, lui, garde son fichier actuel.
 */
function conversion_annuler(int $id): array
{
    $ligne = conversion_par_id($id);
    if ($ligne === null) {
        return ['ok' => false, 'error' => 'Travail introuvable.'];
    }
    if ((string) $ligne['state'] === 'terminee') {
        return ['ok' => false, 'error' => 'Cette conversion est déjà terminée.'];
    }

    db_update('conversions', $id, [
        'state'       => 'ignoree',
        'message'     => 'Conversion annulée.',
        'progress'    => 0,
        'finished_at' => time(),
        'updated_at'  => time(),
    ]);

    return ['ok' => true, 'error' => ''];
}

/**
 * File d'attente, dans l'ordre de passage, avec le titre de la fiche.
 *
 * @return array<int, array<string, mixed>>
 */
function conversion_file(int $limite = 50): array
{
    $limite = max(1, min(200, $limite));
    return db_all(
        "SELECT c.*, d.title, d.visibility, d.kind, d.duration_seconds AS doc_duration
         FROM conversions c
         JOIN documents d ON d.id = c.document_id
         ORDER BY CASE c.state WHEN 'en_cours' THEN 0 WHEN 'attente' THEN 1 ELSE 2 END,
                  c.created_at, c.id
         LIMIT {$limite}"
    );
}

/**
 * Compteurs pour la page « Conversions » et le tableau de bord.
 *
 * @return array{attente:int, en_cours:int, terminee:int, echec:int, ignoree:int, total:int, a_faire:int}
 */
function conversion_resume(): array
{
    $resume = ['attente' => 0, 'en_cours' => 0, 'terminee' => 0, 'echec' => 0, 'ignoree' => 0, 'total' => 0, 'a_faire' => 0];
    foreach (db_all('SELECT state, COUNT(*) AS n FROM conversions GROUP BY state') as $ligne) {
        $etat = (string) $ligne['state'];
        if (array_key_exists($etat, $resume)) {
            $resume[$etat] = (int) $ligne['n'];
        }
        $resume['total'] += (int) $ligne['n'];
    }
    $resume['a_faire'] = $resume['attente'] + $resume['en_cours'];
    return $resume;
}

/**
 * Supprime les travaux terminés (ou annulés) depuis plus de N jours.
 *
 * Les échecs récents restent affichés : c'est une information utile. Seuls
 * les travaux les plus anciens sont effacés, et uniquement la ligne : les
 * fichiers, eux, appartiennent aux fiches.
 */
function conversion_purger(int $jours = 90): int
{
    $jours  = max(7, min(3650, $jours));
    // Le nombre est inséré dans la requête (il est calculé ici, jamais saisi) :
    // passé en paramètre, PDO le lierait en texte, et SQLite comparerait alors
    // des TEXT à des INTEGER — un INTEGER est toujours « plus petit » qu'un
    // TEXT, donc la comparaison serait fausse et tout serait effacé.
    $limite = (int) (time() - $jours * 86400);
    return db_exec(
        "DELETE FROM conversions
         WHERE (state IN ('terminee', 'ignoree') AND COALESCE(finished_at, updated_at) < {$limite})
            OR (state = 'echec' AND COALESCE(finished_at, updated_at) < {$limite})"
    );
}

/**
 * Un envoi par morceaux est-il en cours en ce moment ?
 *
 * Le site et la conversion tournent sur le même Pi, avec 1 Go de mémoire :
 * assembler un fichier de 3 Go et encoder une vidéo en même temps met la
 * machine à genoux (et la carte SD à genoux avec elle). La conversion attend
 * donc que la place soit libre. Un envoi abandonné (navigateur fermé) cesse
 * d'être « en cours » au bout du délai habituel : la conversion reprend alors
 * au passage suivant du minuteur, sans intervention.
 *
 * @return array{id:int, nom:string, depuis:int}|null
 */
function conversion_envoi_en_cours(): ?array
{
    // La table des envois peut manquer sur une base très ancienne : dans ce
    // cas, aucun envoi ne peut être en cours.
    try {
        $ligne = db_one(
            "SELECT id, original_name, updated_at FROM uploads
             WHERE state = 'en_cours' AND updated_at >= ?
             ORDER BY updated_at DESC LIMIT 1",
            [time() - max(5, (int) conversion_envoi_delai()) * 60]
        );
    } catch (Throwable) {
        return null;
    }

    if ($ligne === null) {
        return null;
    }

    return [
        'id'     => (int) $ligne['id'],
        'nom'    => (string) ($ligne['original_name'] ?? ''),
        'depuis' => (int) $ligne['updated_at'],
    ];
}

/**
 * Délai (minutes) au bout duquel un envoi silencieux n'est plus « en cours ».
 *
 * Même valeur que pour l'écran « Mes envois » : un envoi sans nouvelle depuis
 * ce délai est considéré comme abandonné.
 */
function conversion_envoi_delai(): int
{
    return max(5, min(240, (int) envoi_reglages()['actif_minutes']));
}

/**
 * La conversion peut-elle démarrer maintenant ?
 *
 * Deux raisons de patienter, jamais d'échouer :
 *   - un envoi par morceaux est en cours (mémoire et disque partagés) ;
 *   - l'espace libre est sous la marge prévue (le disque se remplirait).
 *
 * Le travail reste alors « en attente » et repart au passage suivant du
 * minuteur : aucun essai n'est consommé, aucun message d'échec n'est affiché.
 *
 * @return array{ok:bool, raison:string, message:string, libre:?int, requis:int}
 */
function conversion_peut_demarrer(array $doc, ?int $libre = -1, ?int $marge = null): array
{
    $espace = conversion_espace_ok($doc, $libre, $marge);
    if (!$espace['ok']) {
        return [
            'ok'      => false,
            'raison'  => 'espace',
            'message' => $espace['message'],
            'libre'   => $espace['libre'],
            'requis'  => $espace['requis'],
        ];
    }

    $envoi = conversion_envoi_en_cours();
    if ($envoi !== null) {
        $nom = $envoi['nom'] !== '' ? ' (« ' . $envoi['nom'] . ' »)' : '';
        return [
            'ok'      => false,
            'raison'  => 'envoi',
            'message' => 'Conversion reportée : un envoi de fichier est en cours' . $nom
                . '. Elle reprendra au prochain passage du minuteur, une fois l’envoi terminé.',
            'libre'   => $espace['libre'],
            'requis'  => $espace['requis'],
        ];
    }

    return [
        'ok'      => true,
        'raison'  => '',
        'message' => '',
        'libre'   => $espace['libre'],
        'requis'  => $espace['requis'],
    ];
}

/**
 * Reporte un travail sans le compter comme un échec.
 *
 * C'est le geste à faire quand la conversion ne doit pas démarrer tout de
 * suite (envoi en cours, disque trop plein). L'essai pris au démarrage est
 * rendu : sinon trois reports sur une journée condamneraient la vidéo.
 */
function conversion_reporter(int $id, string $raison): void
{
    db_exec(
        "UPDATE conversions SET state = 'attente', attempts = MAX(0, attempts - 1),
                progress = 0, message = ?, started_at = NULL, updated_at = ?
         WHERE id = ? AND state = 'en_cours'",
        [mb_substr(trim($raison), 0, 300, 'UTF-8'), time(), $id]
    );
}

/**
 * Remet d'aplomb une file bloquée par une coupure de courant.
 *
 * Une conversion interrompue laisse un travail « en cours » et un fichier
 * temporaire à moitié écrit. Au redémarrage : le travail repart en attente
 * (sans consommer d'essai), et les fichiers temporaires abandonnés sont
 * effacés. Rien de tout cela n'est montré à la famille : les fichiers
 * temporaires vivent dans `tmp/`, hors de portée du serveur web, et une vidéo
 * non terminée n'est jamais servie.
 *
 * @return array{repris:int, abandonnes:int, temporaires:int}
 */
function conversion_recuperer(?int $inactiviteMinutes = null, ?int $maintenant = null): array
{
    $maintenant = $maintenant ?? time();
    $minutes    = $inactiviteMinutes ?? 30;
    $limite     = $maintenant - max(5, $minutes) * 60;
    $reglages   = conversion_reglages();

    $resultat = ['repris' => 0, 'abandonnes' => 0, 'temporaires' => 0];

    $bloques = db_all(
        "SELECT * FROM conversions WHERE state = 'en_cours' AND updated_at <= ?",
        [$limite]
    );
    foreach ($bloques as $ligne) {
        $id     = (int) $ligne['id'];
        $essais = (int) $ligne['attempts'];
        if ($essais >= (int) $reglages['essais_max']) {
            db_exec(
                "UPDATE conversions SET state = 'echec', progress = 0,
                        message = ?, finished_at = ?, updated_at = ?
                 WHERE id = ? AND state = 'en_cours'",
                [
                    'Conversion interrompue (coupure de courant ou arrêt du Raspberry Pi) : '
                        . $essais . ' essai(s) sans succès. Le fichier d’origine est conservé ; '
                        . 'relancez la conversion depuis cette page.',
                    $maintenant,
                    $maintenant,
                    $id,
                ]
            );
            $resultat['abandonnes']++;
            continue;
        }

        // L'essai interrompu est rendu : ce n'est pas la faute du fichier.
        db_exec(
            "UPDATE conversions SET state = 'attente', attempts = MAX(0, attempts - 1),
                    progress = 0, started_at = NULL, updated_at = ?, message = ?
             WHERE id = ? AND state = 'en_cours'",
            [
                $maintenant,
                'Conversion interrompue (coupure de courant) : elle repart automatiquement.',
                $id,
            ]
        );
        $resultat['repris']++;
    }

    $resultat['temporaires'] = conversion_nettoyer_temporaires($minutes, $maintenant);

    return $resultat;
}

/**
 * Efface les fichiers temporaires laissés par une conversion interrompue.
 *
 * Uniquement les fichiers de travail (préfixe `conversion-`) et uniquement
 * ceux qui n'ont pas bougé depuis N minutes : une conversion réellement en
 * cours n'est jamais touchée.
 */
function conversion_nettoyer_temporaires(int $minutes = 30, ?int $maintenant = null): int
{
    $maintenant = $maintenant ?? time();
    $limite     = $maintenant - max(5, $minutes) * 60;
    $dossier    = storage_path((string) config('storage.tmp', 'tmp'));
    if (!is_dir($dossier)) {
        return 0;
    }

    $effaces = 0;
    foreach (glob($dossier . '/conversion-*') ?: [] as $fichier) {
        if (!is_file($fichier)) {
            continue;
        }
        $age = @filemtime($fichier);
        if ($age !== false && $age <= $limite) {
            if (@unlink($fichier)) {
                $effaces++;
            }
        }
    }

    return $effaces;
}

/**
 * Commande ffmpeg, construite en tableau : aucun passage par le shell, donc
 * aucun risque d'interprétation d'un nom de fichier.
 *
 * @return array<int, string>
 */
function conversion_commande(string $source, string $destination, array $reglages): array
{
    $commande = [
        (string) $reglages['ffmpeg'],
        '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
        '-i', $source,
        '-c:v', 'libx264',
        '-preset', (string) $reglages['preset'],
        '-crf', (string) $reglages['crf'],
        '-profile:v', 'high',
        '-pix_fmt', 'yuv420p',
        '-c:a', 'aac',
        '-b:a', $reglages['audio_kbps'] . 'k',
        '-ac', '2',
        // Un seul fil de calcul : sur 1 Go de mémoire, quatre fils tueraient
        // le site (le noyau finit par tuer ffmpeg, ou pire, Apache).
        '-threads', (string) max(1, (int) ($reglages['threads'] ?? 1)),
        '-movflags', '+faststart',
        '-progress', 'pipe:1',
        '-nostats',
    ];

    // Bornage de la hauteur : une vidéo 4K du téléphone devient 1080p (ou
    // 720p en qualité « légère »), ce qui économise le SSD et la bande
    // passante. La largeur suit le rapport d'aspect (largeur paire exigée).
    if ((int) $reglages['hauteur_max'] > 0) {
        $commande[] = '-vf';
        $commande[] = "scale=-2:'min(" . (int) $reglages['hauteur_max'] . ",ih)'";
    }

    $commande[] = $destination;
    return $commande;
}

/**
 * Commande ffmpeg pour un son : AAC dans un conteneur M4A, prêt à être lu par
 * un simple lecteur audio du navigateur. Aucun flux vidéo n'est conservé.
 *
 * @return array<int, string>
 */
function conversion_commande_audio(string $source, string $destination, array $reglages): array
{
    return [
        (string) $reglages['ffmpeg'],
        '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
        '-i', $source,
        '-vn',
        '-c:a', 'aac',
        '-b:a', '96k',
        '-ac', '2',
        '-threads', '1',
        '-movflags', '+faststart',
        '-progress', 'pipe:1',
        '-nostats',
        $destination,
    ];
}

/**
 * Enveloppe la commande dans `nice` (priorité basse), si le programme existe.
 *
 * Sur un Pi 3B+, ffmpeg qui encode à pleine vitesse rend le site presque
 * inutilisable : la page met dix secondes à s'afficher, et le tunnel
 * Cloudflare finit par renvoyer une erreur. Une priorité basse change tout,
 * pour un coût nul. Sans `nice` (installation inhabituelle), la commande part
 * telle quelle : la conversion n'est jamais bloquée par ce détail.
 *
 * @param array<int, string> $commande commande ffmpeg complète
 * @return array<int, string>
 */
function conversion_commande_nicee(array $commande, array $reglages): array
{
    $nice = null;
    foreach (['/usr/bin/nice', '/bin/nice'] as $candidat) {
        if (@is_file($candidat) && @is_executable($candidat)) {
            $nice = $candidat;
            break;
        }
    }
    if ($nice === null) {
        return $commande;
    }

    $niveau = max(1, min(19, (int) ($reglages['nice'] ?? 19)));
    return array_merge([$nice, '-n', (string) $niveau], $commande);
}

/**
 * Lit une ligne de « ffmpeg -progress » et rend un pourcentage (0 à 99).
 *
 * ffmpeg écrit « out_time_us=… » (microsecondes) ; les versions plus
 * anciennes écrivaient « out_time_ms=… » avec la même unité, malgré le nom.
 */
function conversion_lire_progression(string $ligne, ?int $duree): ?int
{
    if ($duree === null || $duree <= 0) {
        return null;
    }
    if (preg_match('/^out_time_(?:us|ms)=(\d+)$/', trim($ligne), $m) !== 1) {
        return null;
    }
    $secondes = (int) $m[1] / 1000000;
    if ($secondes <= 0) {
        return 0;
    }
    return max(0, min(99, (int) floor($secondes * 100 / $duree)));
}

/** Message d'échec lisible, à partir de la sortie d'erreur de ffmpeg. */
function conversion_lire_erreur(string $sortie, int $code): string
{
    $sortie = trim($sortie);
    if ($sortie === '') {
        return 'ffmpeg s’est arrêté avec le code ' . $code . '.';
    }

    // Les dernières lignes sont les plus utiles ; on en garde deux au plus.
    $lignes = preg_split('/\R/', $sortie) ?: [];
    $lignes = array_values(array_filter(array_map('trim', $lignes), static fn (string $l): bool => $l !== ''));
    $extrait = implode(' ', array_slice($lignes, -2));

    if (str_contains($extrait, 'No space left')) {
        return 'Le disque est plein : impossible d’écrire la vidéo convertie.';
    }
    if (str_contains($extrait, 'Permission denied')) {
        return 'Droits insuffisants sur le dossier de stockage.';
    }
    if (str_contains($extrait, 'Invalid data') || str_contains($extrait, 'moov atom not found')) {
        return 'Le fichier d’origine est abîmé ou incomplet : ffmpeg ne sait pas le lire.';
    }
    if (str_contains($extrait, 'Unknown encoder')) {
        return 'Cette version de ffmpeg ne sait pas encoder en H.264 (paquet « ffmpeg » incomplet).';
    }

    return 'ffmpeg a échoué (code ' . $code . ') : ' . mb_substr($extrait, 0, 300, 'UTF-8');
}

/** Durée en français simple : « 1 h 12 min », « 8 min 30 s ». */
function conversion_duree_lisible(?int $secondes): string
{
    $secondes = max(0, (int) $secondes);
    $h = intdiv($secondes, 3600);
    $m = intdiv($secondes % 3600, 60);
    $s = $secondes % 60;
    if ($h > 0) {
        return $h . ' h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ' min';
    }
    if ($m > 0) {
        return $m . ' min ' . str_pad((string) $s, 2, '0', STR_PAD_LEFT) . ' s';
    }
    return $s . ' s';
}

/**
 * Efface les fichiers d'origine des conversions réussies depuis plus de N jours.
 *
 * Trois précautions, dans cet ordre : le réglage doit être activé ; le fichier
 * d'origine ne doit plus être celui d'aucune fiche ; et il doit exister. Un
 * original absent n'est pas une erreur. Rend le nombre de fichiers effacés.
 */
function conversion_liberer_originaux(int $jours = 7): int
{
    if (!conversion_reglages()['supprimer_original']) {
        return 0;
    }

    $jours   = max(1, min(3650, $jours));
    // Même précaution que dans conversion_purger() : le nombre est calculé ici,
    // donc inséré tel quel. Un paramètre lié serait comparé comme du texte.
    $limite  = (int) (time() - $jours * 86400);
    $effaces = 0;

    $lignes = db_all(
        "SELECT c.*, d.rel_path AS doc_rel
         FROM conversions c
         JOIN documents d ON d.id = c.document_id
         WHERE c.state = 'terminee' AND c.source_path <> ''
           AND c.source_path <> d.rel_path
           AND COALESCE(c.finished_at, c.updated_at) < {$limite}"
    );

    foreach ($lignes as $ligne) {
        $source = (string) $ligne['source_path'];
        $encoreUtilise = (int) db_value('SELECT COUNT(*) FROM documents WHERE rel_path = ?', [$source], 0);
        if ($encoreUtilise > 0) {
            continue;
        }
        if (is_file(storage_absolute($source))) {
            storage_delete($source);
            $effaces++;
        }
    }

    return $effaces;
}

/**
 * Condition SQL : « cette fiche est prête à être montrée ».
 *
 * Une vidéo qui attend sa conversion, qui est en train d'être convertie ou
 * dont la conversion a échoué n'est pas montrée à la famille : son lecteur
 * afficherait une erreur, ou pire, un fichier qu'aucun navigateur ne sait
 * lire. Les administrateurs, eux, voient tout (et la fiche leur dit où en est
 * le travail, avec la raison de l'échec) ; l'auteur de l'envoi aussi, pour sa
 * propre fiche. Une conversion **annulée** ne bloque rien : l'administrateur a
 * demandé que le fichier d'origine soit servi tel quel, il l'est.
 *
 * @param string $alias alias SQL de la table documents
 */
function conversion_pretes_sql(string $alias = 'd'): string
{
    // Avant la migration 015 (ou sur une base restaurée d'une version
    // antérieure), la table n'existe pas encore : aucune condition ne doit
    // alors empêcher le site de s'afficher. On ne cache ce résultat que pour
    // la durée d'une requête.
    static $table = null;
    if ($table === null) {
        try {
            $table = db_value(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'conversions'"
            ) === 'conversions';
        } catch (Throwable) {
            $table = false;
        }
    }
    if ($table === false) {
        return '1 = 1';
    }

    return "($alias.kind <> 'video' OR NOT EXISTS (
        SELECT 1 FROM conversions c
        WHERE c.document_id = $alias.id AND c.state IN ('attente', 'en_cours', 'echec')
    ))";
}

/**
 * Cette vidéo en cours de conversion appartient-elle à ce compte ?
 *
 * Tant qu'une vidéo est dans la file, elle n'est montrée à personne — sauf à
 * qui l'a déposée : l'auteur d'un envoi doit pouvoir relire sa fiche et lire
 * « en préparation, 42 % » plutôt que de croire son fichier perdu. C'est un
 * droit de LECTURE sur son propre dépôt, jamais sur celui d'un autre.
 */
function conversion_auteur_du_document(array $doc, ?array $compte): bool
{
    if ($compte === null) {
        return false;
    }
    $id = (int) ($compte['id'] ?? 0);
    if ($id <= 0) {
        return false;
    }

    return ((int) ($doc['created_by'] ?? 0) === $id)
        || ((int) ($doc['submitted_by'] ?? 0) === $id);
}

/** Une fiche est-elle prête à être montrée à la famille ? (même règle en PHP) */
function conversion_document_pret(array $doc): bool
{
    if ((string) ($doc['kind'] ?? '') !== 'video') {
        return true;
    }
    try {
        $etat = conversion_etat_document((int) ($doc['id'] ?? 0));
    } catch (Throwable) {
        // Base pas encore migrée : le fichier est servi comme avant.
        return true;
    }
    return !in_array($etat['state'], ['attente', 'en_cours', 'echec'], true);
}

/**
 * Réglage affiché dans l'écran des vidéos : faut-il prévenir que la vidéo
 * n'est pas encore prête ?
 *
 * @return array{state:string, phrase:string, bloquant:bool}
 */
function conversion_avertissement(int $documentId): array
{
    $etat = conversion_etat_document($documentId);
    $phrase = match ($etat['state']) {
        'attente'  => 'Cette vidéo n’est pas encore au format lu par les navigateurs : elle est dans la file de conversion.',
        'en_cours' => 'Cette vidéo est en cours de conversion'
            . ($etat['progress'] > 0 ? ' (' . $etat['progress'] . ' %)' : '') . '.',
        'echec'    => 'La conversion de cette vidéo a échoué : elle n’est pas montrée à la famille. '
            . 'Le fichier d’origine est conservé intact dans le stockage ; relancez la conversion après avoir lu la raison de l’échec.',
        'ignoree'  => 'La conversion de cette vidéo a été annulée : le fichier d’origine est servi tel quel.',
        default    => '',
    };

    return [
        'state'    => $etat['state'],
        'phrase'   => $phrase,
        'bloquant' => in_array($etat['state'], ['attente', 'en_cours', 'echec'], true),
    ];
}
