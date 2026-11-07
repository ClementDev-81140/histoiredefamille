<?php
/**
 * Envoi de gros fichiers par morceaux, avec reprise.
 *
 * Pourquoi des morceaux ? Le site est derrière le tunnel Cloudflare (offre
 * gratuite) : une requête ne peut pas dépasser 100 Mo et dure moins de deux
 * minutes. Une vidéo de 3 Go ne peut donc pas partir en une seule fois. Le
 * navigateur découpe le fichier en morceaux de 5 Mo (20 Mo au maximum) et les
 * envoie un par un ; si la connexion tombe, il reprend là où il s'était arrêté.
 *
 * Ce fichier contient TOUT le protocole, sans rien afficher :
 *   - « init »     : annoncer un fichier, obtenir un identifiant d'envoi ;
 *   - « morceau »  : déposer un morceau numéroté (sans effet s'il est déjà là) ;
 *   - « état »     : savoir ce qui est déjà reçu, pour reprendre ;
 *   - « terminer » : assembler, vérifier le contenu, calculer l'empreinte,
 *                    créer la fiche — visibilité PRIVÉE, toujours ;
 *   - « annuler »  : supprimer les morceaux.
 *
 * Règles tenues ici :
 *   - aucun fichier n'est servi ni exécuté avant la fin (il est hors du dossier
 *     web, sous un nom aléatoire) ;
 *   - l'identifiant d'envoi est un nombre aléatoire de 128 bits, vérifié par
 *     une expression régulière stricte : aucun chemin n'est construit à partir
 *     d'une saisie libre (pas de traversée de dossier) ;
 *   - la mémoire reste constante : tout se copie par blocs de 1 Mo ;
 *   - les validations de l'envoi simple sont toutes rejouées à la fin
 *     (extension autorisée, type réel par finfo, nom nettoyé).
 */

declare(strict_types=1);

/** Taille d'un bloc de copie (octets) : la mémoire ne dépend pas du fichier. */
const ENVOI_BLOC = 1048576;

/**
 * Réglages de l'envoi par morceaux, bornés.
 *
 * @return array{morceau_mo:int, max_mo:int, morceau_octets:int, max_octets:int,
 *               simultanes:int, actif_minutes:int, marge_libre:int}
 */
function envoi_reglages(): array
{
    $morceau = (int) site_setting('envoi_morceau_mo');
    if ($morceau <= 0) {
        $morceau = (int) config('uploads.morceau_mo', 5);
    }
    // Jamais plus de 20 Mo : il faut rester sous les limites de Cloudflare,
    // d'Apache et de PHP, avec de la marge pour le reste du formulaire.
    $morceau = max(1, min(20, $morceau));

    $max = (int) site_setting('envoi_max_mo');
    if ($max <= 0) {
        $max = (int) config('uploads.max_go', 4) * 1024;
    }
    // Bornes larges mais réelles : 50 Mo (en dessous, l'envoi simple suffit)
    // à 16 Go (aucun fichier familial ne dépasse cela).
    $max = max(50, min(16384, $max));

    return [
        'morceau_mo'    => $morceau,
        'max_mo'        => $max,
        'morceau_octets' => $morceau * 1024 * 1024,
        'max_octets'    => $max * 1024 * 1024,
        'simultanes'    => 3,
        'actif_minutes' => 60,
        'marge_libre'   => 2 * 1024 * 1024 * 1024,
    ];
}

/** Taille maximale d'un fichier envoyé par morceaux (octets). */
function envoi_max_octets(): int
{
    return envoi_reglages()['max_octets'];
}

/** Taille d'un morceau (octets). */
function envoi_morceau_octets(): int
{
    return envoi_reglages()['morceau_octets'];
}

/** Un identifiant d'envoi est-il bien formé (32 caractères hexadécimaux) ? */
function envoi_token_valide(string $token): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $token) === 1;
}

/** Dossier des envois en cours (créé au besoin). */
function envoi_racine(): string
{
    return storage_path((string) config('storage.envois', 'envois'));
}

/**
 * Dossier des morceaux d'un envoi.
 *
 * Le nom du dossier vient d'un identifiant vérifié, jamais d'une saisie : il ne
 * peut pas sortir du dossier des envois (aucun « .. », aucun « / »).
 */
function envoi_dossier(string $token): string
{
    if (!envoi_token_valide($token)) {
        throw new InvalidArgumentException('Identifiant d’envoi invalide.');
    }
    $dir = envoi_racine() . '/' . $token;
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Dossier d’envoi inaccessible.');
    }
    return $dir;
}

/** Chemin du morceau numéro $numero (numéro vérifié, jamais négatif). */
function envoi_morceau_chemin(string $token, int $numero): string
{
    if ($numero < 0 || $numero > 999999) {
        throw new InvalidArgumentException('Numéro de morceau invalide.');
    }
    return envoi_dossier($token) . '/' . sprintf('%06d', $numero) . '.part';
}

/**
 * Nettoie un nom de fichier annoncé par le navigateur.
 *
 * On ne garde que le dernier morceau du chemin (aucun « / » ni « \ »), on
 * retire les caractères de contrôle et on borne la longueur. Le nom affiché
 * n'est JAMAIS utilisé pour construire un chemin sur le disque.
 */
function envoi_nom_propre(string $nom): string
{
    $nom = str_replace('\\', '/', $nom);
    $nom = basename($nom);
    $nom = preg_replace('/[\x00-\x1F\x7F]/u', '', $nom) ?? '';
    $nom = trim($nom);
    if ($nom === '' || $nom === '.' || $nom === '..') {
        return 'fichier';
    }
    if (mb_strlen($nom, 'UTF-8') > 200) {
        $extension = pathinfo($nom, PATHINFO_EXTENSION);
        $base      = mb_substr($nom, 0, 180, 'UTF-8');
        $nom       = $extension !== '' ? $base . '.' . $extension : $base;
    }
    return $nom;
}

/** Extension (minuscules) d'un nom de fichier. */
function envoi_extension(string $nom): string
{
    return strtolower(pathinfo(envoi_nom_propre($nom), PATHINFO_EXTENSION));
}

/**
 * Formats acceptés quand la destination est un album : des photos, rien
 * d'autre. C'est la même liste que celle du type « photo » (kind_for), pour
 * qu'une extension acceptée ici donne toujours une photo à l'arrivée.
 *
 * @return string[]
 */
function envoi_extensions_photo(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'];
}

/**
 * Y a-t-il assez de place pour ce fichier (taille + marge de 2 Go) ?
 *
 * @param int|null $libre espace libre à considérer (null = disque réel) ;
 *                         les tests s'en servent pour simuler un disque plein.
 */
function envoi_espace_suffisant(int $taille, ?int $libre = null): bool
{
    $libre = $libre ?? storage_free_space();
    if ($libre === null) {
        return true; // disque inconnu : on ne bloque pas, la copie le dira
    }
    return $libre > $taille + envoi_reglages()['marge_libre'];
}

/** Nombre d'envois encore actifs pour un compte (limite de 3). */
function envoi_actifs(int $compteId): int
{
    $depuis = time() - envoi_reglages()['actif_minutes'] * 60;

    return (int) db_value(
        "SELECT COUNT(*) FROM uploads
         WHERE account_id = ? AND state IN ('en_cours', 'doublon') AND updated_at >= ?",
        [$compteId, $depuis],
        0
    );
}

/**
 * Annonce un nouvel envoi.
 *
 * Refuse AVANT le premier octet : fichier trop gros, extension interdite,
 * place disque insuffisante, ou déjà trois envois en cours.
 *
 * @return array{ok:bool, error:string, envoi:array<string,mixed>|null}
 */
function envoi_creer(
    int $compteId,
    string $nom,
    int $taille,
    string $mime = '',
    bool $creerFiche = true,
    ?int $libre = null,
    ?int $albumId = null
): array {
    $reglages = envoi_reglages();
    $nom      = envoi_nom_propre($nom);
    $ext      = envoi_extension($nom);

    // Destination « album » : la photo est créée et rangée tout de suite, il
    // n'y a donc pas de fiche à laisser au formulaire, et seules les photos
    // sont acceptées.
    $album = null;
    if ($albumId !== null && $albumId > 0) {
        $album = find_album($albumId);
        if ($album === null) {
            return ['ok' => false, 'error' => 'Cet album n’existe plus.', 'envoi' => null];
        }
        if (!in_array($ext, envoi_extensions_photo(), true)) {
            return [
                'ok'    => false,
                'error' => 'Un album n’accepte que des photos ('
                    . implode(', ', envoi_extensions_photo()) . ').',
                'envoi' => null,
            ];
        }
        $creerFiche = true;
    }

    if ($compteId <= 0) {
        return ['ok' => false, 'error' => 'Compte inconnu.', 'envoi' => null];
    }
    if ($taille <= 0) {
        return ['ok' => false, 'error' => 'Le fichier est vide.', 'envoi' => null];
    }
    if ($taille > $reglages['max_octets']) {
        return [
            'ok'    => false,
            'error' => 'Fichier trop volumineux : ' . format_size($taille)
                . ' pour un maximum de ' . format_size($reglages['max_octets']) . '.',
            'envoi' => null,
        ];
    }
    if ($ext === '' || !in_array($ext, allowed_extensions(), true)) {
        return [
            'ok'    => false,
            'error' => 'Extension non autorisée (' . ($ext !== '' ? $ext : 'aucune')
                . '). Extensions acceptées : ' . implode(', ', allowed_extensions()) . '.',
            'envoi' => null,
        ];
    }
    if (!envoi_espace_suffisant($taille, $libre)) {
        return [
            'ok'    => false,
            'error' => 'Il n’y a pas assez de place sur le disque du site pour ce fichier ('
                . format_size($taille) . ' à enregistrer, plus une marge de sécurité de 2 Go). '
                . 'Libérez de la place, puis réessayez.',
            'envoi' => null,
        ];
    }
    if (envoi_actifs($compteId) >= $reglages['simultanes']) {
        return [
            'ok'    => false,
            'error' => 'Vous avez déjà ' . $reglages['simultanes']
                . ' envois en cours. Terminez-les ou annulez-en un avant d’en commencer un autre.',
            'envoi' => null,
        ];
    }

    $token = bin2hex(random_bytes(16)); // 128 bits
    $maintenant = time();

    $id = db_insert('uploads', [
        'token'         => $token,
        'account_id'    => $compteId,
        'original_name' => $nom,
        'size'          => $taille,
        'mime_client'   => mb_substr($mime, 0, 100, 'UTF-8'),
        'chunk_size'    => $reglages['morceau_octets'],
        'chunks_total'  => (int) ceil($taille / $reglages['morceau_octets']),
        'state'         => 'en_cours',
        'creer_fiche'   => $creerFiche ? 1 : 0,
        'album_id'      => $album !== null ? (int) $album['id'] : null,
        'created_at'    => $maintenant,
        'updated_at'    => $maintenant,
    ]);

    envoi_dossier($token);

    $envoi = db_one('SELECT * FROM uploads WHERE id = ?', [$id]);

    return ['ok' => true, 'error' => '', 'envoi' => $envoi ?? null];
}

/** Un envoi par son identifiant (aucun contrôle de compte ici). */
function envoi_par_token(string $token): ?array
{
    if (!envoi_token_valide($token)) {
        return null;
    }
    return db_one('SELECT * FROM uploads WHERE token = ?', [$token]);
}

/** Un envoi par son numéro. */
function envoi_par_id(int $id): ?array
{
    return $id > 0 ? db_one('SELECT * FROM uploads WHERE id = ?', [$id]) : null;
}

/**
 * Enregistre un morceau.
 *
 * Sans effet (mais sans erreur) si le morceau est déjà reçu : le navigateur
 * peut donc réessayer après une coupure sans rien casser.
 *
 * @return array{ok:bool, error:string, recu:bool, deja:bool, numero:int}
 */
function envoi_recevoir_morceau(array $envoi, int $numero, string $source, int $taille): array
{
    $refus = ['ok' => false, 'error' => '', 'recu' => false, 'deja' => false, 'numero' => $numero];

    if (($envoi['state'] ?? '') !== 'en_cours') {
        $refus['error'] = 'Cet envoi n’accepte plus de morceaux.';
        return $refus;
    }

    $morceau = (int) $envoi['chunk_size'];
    $total   = (int) $envoi['chunks_total'];
    $octets  = (int) $envoi['size'];

    if ($numero < 0 || $numero >= $total) {
        $refus['error'] = 'Numéro de morceau hors limites.';
        return $refus;
    }

    $attendu = min($morceau, $octets - $numero * $morceau);
    if ($taille !== $attendu) {
        $refus['error'] = 'Morceau de taille incohérente (' . $taille . ' octets reçus, '
            . $attendu . ' attendus).';
        return $refus;
    }

    $destination = envoi_morceau_chemin((string) $envoi['token'], $numero);

    // Déjà reçu et de la bonne taille : on dit « rien à faire ».
    if (is_file($destination) && (int) filesize($destination) === $attendu) {
        $refus['ok']   = true;
        $refus['recu'] = true;
        $refus['deja'] = true;
        envoi_toucher((int) $envoi['id']);
        return $refus;
    }

    $entree = @fopen($source, 'rb');
    if ($entree === false) {
        $refus['error'] = 'Morceau illisible sur le serveur.';
        return $refus;
    }

    $temporaire = $destination . '.tmp';
    $sortie     = @fopen($temporaire, 'wb');
    if ($sortie === false) {
        fclose($entree);
        $refus['error'] = 'Impossible d’écrire le morceau (place ou droits du disque ?).';
        return $refus;
    }

    $ecrits = 0;
    while (!feof($entree)) {
        $bloc = fread($entree, ENVOI_BLOC);
        if ($bloc === false || $bloc === '') {
            break;
        }
        $ecrits += (int) fwrite($sortie, $bloc);
    }
    fclose($entree);
    fclose($sortie);

    if ($ecrits !== $attendu || !@rename($temporaire, $destination)) {
        @unlink($temporaire);
        $refus['error'] = 'Le morceau n’a pas pu être enregistré complètement.';
        return $refus;
    }
    @chmod($destination, 0640);

    envoi_toucher((int) $envoi['id']);

    return ['ok' => true, 'error' => '', 'recu' => true, 'deja' => false, 'numero' => $numero];
}

/** Met à jour la date de dernière activité d'un envoi. */
function envoi_toucher(int $id): void
{
    db_exec('UPDATE uploads SET updated_at = ? WHERE id = ?', [time(), $id]);
}

/**
 * Morceaux déjà reçus.
 *
 * On lit le dossier (et non la base) : c'est la vérité du disque, y compris
 * après un redémarrage ou une coupure au mauvais moment.
 *
 * @return array{recus:int[], manquants:int[], complet:bool, octets:int, nombre:int}
 */
function envoi_morceaux(array $envoi): array
{
    $total = (int) $envoi['chunks_total'];
    $morceau = (int) $envoi['chunk_size'];
    $octets  = (int) $envoi['size'];

    $recus = [];
    $somme = 0;
    for ($i = 0; $i < $total; $i++) {
        $chemin  = envoi_morceau_chemin((string) $envoi['token'], $i);
        $attendu = min($morceau, $octets - $i * $morceau);
        if (is_file($chemin) && (int) filesize($chemin) === $attendu) {
            $recus[] = $i;
            $somme  += $attendu;
        }
    }

    $manquants = array_values(array_diff(range(0, max(0, $total - 1)), $recus));

    return [
        'recus'     => $recus,
        'manquants' => $total === 0 ? [] : $manquants,
        'complet'   => count($recus) === $total && $total > 0,
        'octets'    => $somme,
        'nombre'    => count($recus),
    ];
}

/**
 * Assemble les morceaux (par blocs de 1 Mo), vérifie le contenu, calcule
 * l'empreinte SHA-256 et crée la fiche.
 *
 * @param bool $forcer garder le fichier même si un doublon existe
 * @return array{ok:bool, error:string, etat:string, doublon:array|null, envoi:array|null}
 */
function envoi_terminer(array $envoi, bool $forcer = false): array
{
    $resultat = ['ok' => false, 'error' => '', 'etat' => (string) $envoi['state'], 'doublon' => null, 'envoi' => $envoi];

    if (!in_array((string) $envoi['state'], ['en_cours', 'doublon'], true)) {
        $resultat['error'] = 'Cet envoi est déjà terminé ou annulé.';
        return $resultat;
    }

    // Le fichier est déjà assemblé (état « doublon ») : on ne recommence pas.
    if ((string) $envoi['state'] === 'doublon') {
        return envoi_decider($envoi, $forcer ? 'garder' : 'ignorer');
    }

    $pieces = envoi_morceaux($envoi);
    if (!$pieces['complet']) {
        $resultat['error'] = 'Il manque ' . count($pieces['manquants'])
            . ' morceau(x) : l’envoi n’est pas complet.';
        return $resultat;
    }

    $ext = (string) $envoi['ext'] !== '' ? (string) $envoi['ext'] : envoi_extension((string) $envoi['original_name']);
    if ($ext === '' || !in_array($ext, allowed_extensions(), true)) {
        return envoi_echec($envoi, 'Extension non autorisée.', $resultat);
    }

    $rel      = storage_target_path($ext);
    $absolu   = storage_absolute($rel);
    $partiel  = $absolu . '.partiel';

    $sortie = @fopen($partiel, 'wb');
    if ($sortie === false) {
        return envoi_echec($envoi, 'Impossible d’écrire le fichier assemblé (place ou droits du disque ?).', $resultat);
    }

    $empreinte = hash_init('sha256');
    $total     = 0;
    $echec     = '';

    for ($i = 0; $i < (int) $envoi['chunks_total']; $i++) {
        $entree = @fopen(envoi_morceau_chemin((string) $envoi['token'], $i), 'rb');
        if ($entree === false) {
            $echec = 'Un morceau est illisible : recommencez l’envoi.';
            break;
        }
        while (!feof($entree)) {
            $bloc = fread($entree, ENVOI_BLOC);
            if ($bloc === false || $bloc === '') {
                break;
            }
            $total += (int) fwrite($sortie, $bloc);
            hash_update($empreinte, $bloc);
        }
        fclose($entree);
    }
    fclose($sortie);

    if ($echec !== '') {
        @unlink($partiel);
        return envoi_echec($envoi, $echec, $resultat);
    }

    if ($total !== (int) $envoi['size']) {
        @unlink($partiel);
        return envoi_echec(
            $envoi,
            'La taille assemblée ne correspond pas (' . $total . ' octets au lieu de ' . (int) $envoi['size'] . ').',
            $resultat
        );
    }

    if (!@rename($partiel, $absolu)) {
        @unlink($partiel);
        return envoi_echec($envoi, 'Impossible de terminer l’enregistrement du fichier.', $resultat);
    }
    @chmod($absolu, 0640);

    // Type réel : on ne croit jamais l'extension ni le type annoncé.
    $controle = envoi_verifier_contenu($absolu, $ext);
    if (!$controle['ok']) {
        storage_delete($rel);
        return envoi_echec($envoi, (string) $controle['error'], $resultat);
    }

    $sha = hash_final($empreinte);

    $donnees = [
        'sha256'    => $sha,
        'rel_path'  => $rel,
        'ext'       => $ext,
        'mime'      => (string) $controle['mime'],
        'kind'      => kind_for((string) $controle['mime'], $ext),
        'date_meta' => envoi_date_metadonnees($absolu, $ext),
    ];

    // Album : la photo doit en être une, même si l'extension annonçait « jpg ».
    // Le type réel (finfo) a déjà parlé ; on refuse ici plutôt que de créer un
    // document qui ne serait pas une photo.
    if ((int) ($envoi['album_id'] ?? 0) > 0 && (string) $donnees['kind'] !== 'photo') {
        storage_delete($rel);
        return envoi_echec($envoi, 'Un album n’accepte que des photos : ce fichier n’en est pas une.', $resultat);
    }

    $doublon = envoi_doublon($sha, (int) ($envoi['document_id'] ?? 0));

    if ($doublon !== null && !$forcer) {
        db_exec(
            'UPDATE uploads SET state = ?, sha256 = ?, rel_path = ?, ext = ?, mime = ?, kind = ?,
                    date_meta = ?, updated_at = ? WHERE id = ?',
            ['doublon', $sha, $donnees['rel_path'], $ext, $donnees['mime'], $donnees['kind'],
             $donnees['date_meta'], time(), (int) $envoi['id']]
        );

        return [
            'ok'      => true,
            'error'   => '',
            'etat'    => 'doublon',
            'doublon' => $doublon,
            'envoi'   => envoi_par_id((int) $envoi['id']),
        ];
    }

    return envoi_conclure($envoi, $donnees);
}

/** Marque un envoi en échec (message en français, morceaux supprimés). */
function envoi_echec(array $envoi, string $message, array $resultat): array
{
    db_exec(
        'UPDATE uploads SET state = ?, message = ?, updated_at = ?, finished_at = ? WHERE id = ?',
        ['echec', mb_substr($message, 0, 500, 'UTF-8'), time(), time(), (int) $envoi['id']]
    );
    envoi_supprimer_morceaux($envoi);

    $resultat['ok']    = false;
    $resultat['error'] = $message;
    $resultat['etat']  = 'echec';
    $resultat['envoi'] = envoi_par_id((int) $envoi['id']);
    return $resultat;
}

/**
 * Fin normale : la fiche est créée (état « termine ») ou le fichier attend un
 * formulaire (état « pret »).
 *
 * Dans les deux cas la miniature et les dimensions sont calculées ici, une
 * seule fois : une photo n'est jamais décodée deux fois sur le Pi.
 */
function envoi_conclure(array $envoi, array $donnees): array
{
    $resultat = ['ok' => true, 'error' => '', 'etat' => 'pret', 'doublon' => null, 'envoi' => null];

    // Réservation conditionnelle AVANT la création de la fiche : un double
    // clic, deux onglets ou deux appareils ne peuvent pas tirer deux documents
    // du même fichier. Le second passage reçoit un refus clair. Les deux états
    // d'où l'on peut conclure sont « en_cours » (assemblage) et « doublon »
    // (l'administrateur a répondu « Garder quand même »).
    $pris = db_exec(
        "UPDATE uploads SET state = 'termine', updated_at = ? WHERE id = ? AND state IN ('en_cours', 'doublon')",
        [time(), (int) $envoi['id']]
    );
    if ($pris < 1) {
        return [
            'ok'      => false,
            'error'   => 'Ce fichier a déjà été ajouté : un fichier reçu ne devient un document qu’une seule fois.',
            'etat'    => (string) ($envoi['state'] ?? ''),
            'doublon' => null,
            'envoi'   => envoi_par_id((int) $envoi['id']),
        ];
    }

    // Une miniature déjà calculée (décision après doublon) n'est pas refaite.
    $dejaMiniature = isset($donnees['thumb_path']) && $donnees['thumb_path'] !== null;

    if (!$dejaMiniature && (string) $donnees['kind'] === 'photo') {
        $stored = finish_stored_file((string) $donnees['rel_path'], [
            'ok'   => true,
            'kind' => (string) $donnees['kind'],
        ]);
        $donnees['thumb_path'] = $stored['thumb_path'];
        $donnees['width']      = $stored['width'];
        $donnees['height']     = $stored['height'];
    } elseif (!$dejaMiniature) {
        $donnees['thumb_path'] = null;
        $donnees['width']      = null;
        $donnees['height']     = null;
    }

    if ((int) ($envoi['creer_fiche'] ?? 1) === 1) {
        $documentId = envoi_creer_document($envoi, $donnees);
        if ($documentId <= 0) {
            return envoi_echec($envoi, 'La fiche n’a pas pu être créée.', $resultat);
        }
        db_exec(
            'UPDATE uploads SET state = ?, sha256 = ?, rel_path = ?, thumb_path = ?, ext = ?, mime = ?,
                    kind = ?, date_meta = ?, width = ?, height = ?, document_id = ?,
                    updated_at = ?, finished_at = ? WHERE id = ?',
            ['termine', $donnees['sha256'], $donnees['rel_path'], (string) $donnees['thumb_path'],
             $donnees['ext'], $donnees['mime'], $donnees['kind'], $donnees['date_meta'],
             $donnees['width'], $donnees['height'], $documentId, time(), time(), (int) $envoi['id']]
        );
        // Une vidéo (ou un son) dans un format que les navigateurs ne lisent
        // pas entre dans la file de conversion, une fois la fiche créée.
        conversion_planifier_document($documentId);
        $resultat['etat'] = 'termine';
    } else {
        // Le fichier est prêt : il attend que l'administrateur valide le
        // formulaire (documents, vidéos…). Toujours hors du public.
        db_exec(
            'UPDATE uploads SET state = ?, sha256 = ?, rel_path = ?, thumb_path = ?, ext = ?, mime = ?,
                    kind = ?, date_meta = ?, width = ?, height = ?,
                    updated_at = ?, finished_at = ? WHERE id = ?',
            ['pret', $donnees['sha256'], $donnees['rel_path'], (string) $donnees['thumb_path'],
             $donnees['ext'], $donnees['mime'], $donnees['kind'], $donnees['date_meta'],
             $donnees['width'], $donnees['height'], time(), time(), (int) $envoi['id']]
        );
        $resultat['etat'] = 'pret';
    }

    envoi_supprimer_morceaux($envoi);
    $resultat['envoi'] = envoi_par_id((int) $envoi['id']);

    return $resultat;
}

/**
 * Crée la fiche du fichier reçu, exactement comme l'envoi simple : miniature,
 * dimensions, nom d'origine, et TOUJOURS la visibilité privée.
 */
function envoi_creer_document(array $envoi, array $donnees): int
{
    $nom   = envoi_nom_propre((string) $envoi['original_name']);
    $titre = trim(pathinfo($nom, PATHINFO_FILENAME));
    if ($titre === '') {
        $titre = $nom;
    }

    $date = (string) $donnees['date_meta'];

    // Envoi direct dans un album : la photo prend la visibilité de l'album et
    // y est rangée à la fin. Une photo privée dans un album public reste donc
    // invisible d'un visiteur, comme avant.
    $albumId = (int) ($envoi['album_id'] ?? 0);
    $album   = $albumId > 0 ? find_album($albumId) : null;
    $visibilite = $album !== null ? (string) $album['visibility'] : 'prive';

    $documentId = db_insert('documents', [
        'title'         => mb_substr($titre, 0, 200, 'UTF-8'),
        'description'   => null,
        'doc_date'      => $date !== '' ? mb_substr($date, 0, 100, 'UTF-8') : null,
        'doc_date_sort' => extract_year($date),
        'category_id'   => null,
        // Sans album, la visibilité reste privée : rien n'est publié tant que
        // l'administrateur ne l'a pas validé explicitement.
        'visibility'    => $visibilite,
        'kind'          => $album !== null ? 'photo' : (string) $donnees['kind'],
        'rel_path'      => (string) $donnees['rel_path'],
        'thumb_path'    => $donnees['thumb_path'] ?? null,
        'original_name' => $nom,
        'mime'          => (string) $donnees['mime'],
        'ext'           => (string) $donnees['ext'],
        'size'          => (int) $envoi['size'],
        'width'         => $donnees['width'] ?? null,
        'height'        => $donnees['height'] ?? null,
        'sha256'        => (string) $donnees['sha256'],
        'is_attachment' => 0,
        'created_by'    => (int) $envoi['account_id'],
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);

    if ($album !== null && $documentId > 0) {
        album_ajouter_photo((int) $album['id'], $documentId);

        // Album réservé à certaines branches : la photo suit les mêmes
        // branches, sinon elle serait invisible même pour elles.
        if ($visibilite === 'branches') {
            set_content_branches('document', $documentId, content_branch_ids('album', (int) $album['id']));
        }
    }

    return $documentId;
}

/**
 * Choix de l'administrateur devant un doublon.
 *
 * @param string $choix 'garder' ou 'ignorer'
 */
function envoi_decider(array $envoi, string $choix): array
{
    $resultat = ['ok' => true, 'error' => '', 'etat' => (string) $envoi['state'], 'doublon' => null, 'envoi' => $envoi];

    if ((string) $envoi['state'] !== 'doublon') {
        $resultat['error'] = 'Cet envoi n’attend aucune décision.';
        return $resultat;
    }

    if ($choix === 'ignorer') {
        envoi_annuler($envoi, 'doublon');
        $resultat['etat']  = 'annule';
        $resultat['envoi'] = envoi_par_id((int) $envoi['id']);
        return $resultat;
    }

    // La miniature existe déjà (calculée à l'assemblage) : on la réutilise
    // telle quelle pour ne pas décoder la photo une deuxième fois.
    $donnees = [
        'sha256'     => (string) $envoi['sha256'],
        'rel_path'   => (string) $envoi['rel_path'],
        'ext'        => (string) $envoi['ext'],
        'mime'       => (string) $envoi['mime'],
        'kind'       => (string) $envoi['kind'],
        'date_meta'  => (string) $envoi['date_meta'],
        'thumb_path' => (string) ($envoi['thumb_path'] ?? '') !== '' ? (string) $envoi['thumb_path'] : null,
        'width'      => isset($envoi['width']) ? (int) $envoi['width'] : null,
        'height'     => isset($envoi['height']) ? (int) $envoi['height'] : null,
    ];

    return envoi_conclure($envoi, $donnees);
}

/**
 * Photos qu'un compte a ajoutées à un album par l'envoi direct.
 *
 * Sert à la page « Ajouter des photos » : après l'envoi, la liste des photos
 * arrive avec, pour chacune, le titre et la date à corriger, et le bouton
 * « Retirer de l'album ». Aucun chemin de fichier n'est rendu ici.
 *
 * @return array<int, array<string, mixed>>
 */
function envoi_photos_album(int $albumId, int $compteId, int $limite = 60): array
{
    if ($albumId <= 0 || $compteId <= 0) {
        return [];
    }

    return db_all(
        "SELECT u.id AS upload_id, u.updated_at AS envoye_at, d.*
         FROM uploads u
         JOIN documents d ON d.id = u.document_id
         JOIN album_documents ad ON ad.album_id = u.album_id AND ad.document_id = u.document_id
         WHERE u.album_id = ? AND u.account_id = ? AND u.state = 'termine'
           AND u.document_id IS NOT NULL
         ORDER BY u.updated_at DESC, u.id DESC
         LIMIT " . max(1, min(200, $limite)),
        [$albumId, $compteId]
    );
}

/**
 * Un fichier identique existe-t-il déjà ?
 *
 * @return array{id:int, title:string, doc_date:string, visibility:string}|null
 */
function envoi_doublon(string $sha, int $saufDocument = 0): ?array
{
    if ($sha === '') {
        return null;
    }

    $ligne = db_one(
        'SELECT id, title, doc_date, visibility FROM documents
         WHERE sha256 = ? AND sha256 <> \'\' AND id <> ? LIMIT 1',
        [$sha, $saufDocument]
    );

    if (!$ligne) {
        return null;
    }

    return [
        'id'         => (int) $ligne['id'],
        'title'      => (string) $ligne['title'],
        'doc_date'   => (string) $ligne['doc_date'],
        'visibility' => (string) $ligne['visibility'],
    ];
}

/** Supprime les morceaux reçus (l'envoi lui-même reste en base). */
function envoi_supprimer_morceaux(array $envoi): void
{
    $token = (string) ($envoi['token'] ?? '');
    if (!envoi_token_valide($token)) {
        return;
    }

    $dossier = envoi_racine() . '/' . $token;
    if (!is_dir($dossier)) {
        return;
    }
    foreach (glob($dossier . '/*') ?: [] as $fichier) {
        if (is_file($fichier)) {
            @unlink($fichier);
        }
    }
    @rmdir($dossier);
}

/**
 * Annule un envoi : morceaux et fichier assemblé disparaissent.
 *
 * @param string $raison 'annule' (par la personne) ou 'doublon' (ignoré)
 */
function envoi_annuler(array $envoi, string $raison = 'annule'): void
{
    envoi_supprimer_morceaux($envoi);

    // Le fichier assemblé n'existe que pour deux états : « doublon » (gardé le
    // temps de la décision) et « pret » (en attente de formulaire). Ailleurs il
    // n'y a rien à effacer, et surtout pas pour « termine » : le fichier
    // appartient alors à la fiche, qui le supprimera elle-même.
    $fichier = (string) ($envoi['rel_path'] ?? '');
    if ($fichier !== '' && in_array((string) $envoi['state'], ['doublon', 'pret'], true)) {
        storage_delete($fichier);
        storage_delete((string) ($envoi['thumb_path'] ?? ''));
    }

    db_exec(
        'UPDATE uploads SET state = ?, message = ?, rel_path = \'\', updated_at = ?, finished_at = ? WHERE id = ?',
        ['annule', mb_substr($raison, 0, 200, 'UTF-8'), time(), time(), (int) $envoi['id']]
    );
}

/**
 * Vérifie le contenu réel d'un fichier assemblé (même règle que l'envoi simple).
 *
 * @return array{ok:bool, error:string, mime:string}
 */
function envoi_verifier_contenu(string $chemin, string $ext): array
{
    $resultat = ['ok' => false, 'error' => '', 'mime' => ''];

    if (!is_file($chemin) || !is_readable($chemin)) {
        $resultat['error'] = 'Fichier illisible après assemblage.';
        return $resultat;
    }

    if (!class_exists('finfo')) {
        $resultat['error'] = 'L’extension PHP « fileinfo » est requise sur le serveur.';
        return $resultat;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($chemin);
    $map   = mime_map();
    $connus = $map[$ext] ?? [];

    if ($connus !== [] && !in_array($mime, $connus, true)) {
        $resultat['error'] = 'Le contenu du fichier ne correspond pas à son extension (type détecté : '
            . ($mime !== '' ? $mime : 'inconnu') . ').';
        return $resultat;
    }

    $resultat['ok']   = true;
    $resultat['mime'] = $mime !== '' ? $mime : 'application/octet-stream';
    return $resultat;
}

/**
 * Date de prise de vue lue dans le fichier, si elle existe (facultatif).
 * Aucune donnée n'est inventée : sans métadonnée, on renvoie une chaîne vide.
 */
function envoi_date_metadonnees(string $chemin, string $ext): string
{
    if (!in_array($ext, ['jpg', 'jpeg'], true) || !function_exists('exif_read_data')) {
        return '';
    }

    $exif = @exif_read_data($chemin, 'EXIF', true);
    if (!is_array($exif)) {
        return '';
    }

    $brute = (string) ($exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? '');
    if (preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $brute, $m) !== 1) {
        return '';
    }

    $annee = (int) $m[1];
    if ($annee < 1826 || $annee > (int) date('Y') + 1) {
        return '';
    }

    return sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], $annee);
}

/**
 * Supprime les envois abandonnés depuis plus de 48 h et libère leur espace.
 *
 * @return array{supprimes:int, octets:int}
 */
function envoi_purge(int $heures = 48): array
{
    $limite = time() - max(1, $heures) * 3600;

    $vieux = db_all(
        "SELECT * FROM uploads
         WHERE state IN ('en_cours', 'doublon', 'pret', 'echec') AND updated_at < ?",
        [$limite]
    );

    $octets = 0;
    foreach ($vieux as $envoi) {
        if (in_array((string) $envoi['state'], ['doublon', 'pret'], true)) {
            $rel = (string) $envoi['rel_path'];
            if ($rel !== '') {
                $chemin = storage_absolute($rel);
                if (is_file($chemin)) {
                    $octets += (int) filesize($chemin);
                }
                storage_delete($rel);
            }
        }
        envoi_supprimer_morceaux($envoi);
        db_exec('DELETE FROM uploads WHERE id = ?', [(int) $envoi['id']]);
    }

    // Dossiers orphelins (envoi supprimé de la base à la main, par exemple).
    $connus = [];
    foreach (db_all('SELECT token FROM uploads') as $ligne) {
        $connus[(string) $ligne['token']] = true;
    }
    foreach (glob(envoi_racine() . '/*') ?: [] as $dossier) {
        if (!is_dir($dossier)) {
            continue;
        }
        $token = basename($dossier);
        if (isset($connus[$token])) {
            continue;
        }
        if ((int) @filemtime($dossier) > $limite) {
            continue;
        }
        foreach (glob($dossier . '/*') ?: [] as $fichier) {
            if (is_file($fichier)) {
                @unlink($fichier);
            }
        }
        @rmdir($dossier);
    }

    return ['supprimes' => count($vieux), 'octets' => $octets];
}

/** Libellé français d'un état d'envoi. */
function envoi_etat_libelle(string $etat): string
{
    return match ($etat) {
        'en_cours' => 'En cours',
        'doublon'  => 'Doublon à décider',
        'pret'     => 'Prêt à valider',
        'termine'  => 'Terminé',
        'echec'    => 'Échec',
        default    => 'Annulé',
    };
}

/** Classe CSS d'un état d'envoi (badges existants). */
function envoi_etat_classe(string $etat): string
{
    return match ($etat) {
        'termine'  => 'badge badge-public',
        'pret'     => 'badge badge-branches',
        'en_cours' => 'badge badge-famille',
        'doublon'  => 'badge badge-branches',
        'echec'    => 'badge badge-prive',
        default    => 'badge badge-prive',
    };
}

/**
 * Envois d'un compte (les siens), ou de tout le monde pour la supervision.
 *
 * @return array<int, array<string, mixed>>
 */
function envoi_liste(?int $compteId, int $jours = 30, int $limite = 200): array
{
    $depuis = time() - max(1, $jours) * 86400;
    $params = [$depuis];
    $where  = "(u.state IN ('en_cours', 'doublon', 'pret')
                OR (u.state IN ('termine', 'annule', 'echec') AND u.updated_at >= ?))";

    if ($compteId !== null) {
        $where .= ' AND u.account_id = ?';
        $params[] = $compteId;
    }

    return db_all(
        "SELECT u.*, a.username FROM uploads u
         LEFT JOIN accounts a ON a.id = u.account_id
         WHERE {$where}
         ORDER BY u.updated_at DESC LIMIT " . max(1, min(500, $limite)),
        $params
    );
}

/**
 * Un envoi appartient-il bien à ce compte ? (un administrateur ne reprend pas
 * l'envoi d'un autre : il le voit, en lecture seule, dans « Tous les envois »)
 */
function envoi_appartient(array $envoi, int $compteId): bool
{
    return (int) ($envoi['account_id'] ?? 0) === $compteId;
}

/** Un envoi peut-il encore être repris (morceaux à envoyer) ? */
function envoi_reprisable(array $envoi): bool
{
    return (string) ($envoi['state'] ?? '') === 'en_cours';
}

// ---------------------------------------------------------------------------
// Reprise et réutilisation dans les formulaires existants
// ---------------------------------------------------------------------------

/**
 * Envoi en cours à reprendre, quand on redépose le même fichier.
 *
 * On ne reprend que si le NOM et la TAILLE sont identiques : deux fichiers
 * différents ne s'additionnent jamais.
 */
function envoi_a_reprendre(int $compteId, string $nom, int $taille, ?int $albumId = null): ?array
{
    $nom = envoi_nom_propre($nom);
    if ($nom === '' || $taille <= 0) {
        return null;
    }

    // La destination fait partie de l'envoi : reprendre un fichier déposé pour
    // un album ne doit pas le transformer en fiche privée, et l'inverse non plus.
    // (CAST : le résultat de COALESCE n'a pas d'affinité, et SQLite comparerait
    // alors l'entier de la colonne au texte du paramètre — jamais égaux.)
    $ligne = db_one(
        "SELECT * FROM uploads
         WHERE account_id = ? AND original_name = ? AND size = ? AND state = 'en_cours'
           AND CAST(COALESCE(album_id, 0) AS INTEGER) = CAST(? AS INTEGER)
         ORDER BY updated_at DESC LIMIT 1",
        [$compteId, $nom, $taille, max(0, (int) $albumId)]
    );

    return $ligne ?: null;
}

/**
 * Fichier préparé par l'envoi par morceaux, prêt à être rattaché à une fiche.
 * Un administrateur ne peut utiliser que les fichiers de son propre envoi.
 */
function envoi_fichier_pret(int $compteId, string $token): ?array
{
    $envoi = envoi_par_token($token);
    if ($envoi === null || (string) $envoi['state'] !== 'pret') {
        return null;
    }
    if ((int) $envoi['account_id'] !== $compteId) {
        return null;
    }
    return $envoi;
}

/**
 * Réserve un fichier préparé pour la fiche en cours d'enregistrement.
 *
 * C'est le seul point où un envoi « prêt » quitte cet état, et il est
 * conditionnel (`WHERE state = 'pret'`) : deux enregistrements simultanés —
 * un double clic, deux onglets — ne peuvent pas rattacher le même fichier à
 * deux fiches. Le second reçoit `false` et un message clair.
 */
function envoi_reserver(array $envoi): bool
{
    return db_exec(
        "UPDATE uploads SET state = 'termine', updated_at = ? WHERE id = ? AND state = 'pret'",
        [time(), (int) $envoi['id']]
    ) > 0;
}

/**
 * Rattache le fichier réservé à sa fiche.
 *
 * @return bool false si l'envoi n'était pas réservé (déjà lié ailleurs)
 */
function envoi_marquer_utilise(array $envoi, int $documentId): bool
{
    return db_exec(
        "UPDATE uploads SET document_id = ?, updated_at = ? WHERE id = ? AND state = 'termine'",
        [$documentId, time(), (int) $envoi['id']]
    ) > 0;
}

/**
 * Champs de la fiche (« documents ») remplis par un fichier préparé.
 *
 * @return array<string, mixed>
 */
function envoi_champs_document(array $envoi): array
{
    return [
        'rel_path'      => (string) $envoi['rel_path'],
        'thumb_path'    => (string) ($envoi['thumb_path'] ?? '') !== '' ? (string) $envoi['thumb_path'] : null,
        'original_name' => (string) $envoi['original_name'],
        'mime'          => (string) $envoi['mime'],
        'ext'           => (string) $envoi['ext'],
        'size'          => (int) $envoi['size'],
        'kind'          => (string) $envoi['kind'],
        'width'         => isset($envoi['width']) ? (int) $envoi['width'] : null,
        'height'        => isset($envoi['height']) ? (int) $envoi['height'] : null,
        'sha256'        => (string) $envoi['sha256'],
    ];
}
