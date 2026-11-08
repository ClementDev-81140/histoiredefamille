<?php
/**
 * Tests de l'envoi de gros fichiers par morceaux (lot A).
 *
 * Trois familles :
 *   1. le protocole (init, morceau, état, terminer, annuler) — avec de vrais
 *      fichiers d'essai, petits, dans le dossier de stockage ;
 *   2. les refus (taille, extension, type réel, place disque, droits, CSRF,
 *      identifiant falsifié) ;
 *   3. le ménage (doublons, abandon après 48 h, fichiers préparés).
 *
 * Tout se passe dans une transaction annulée et les fichiers créés sont
 * effacés : la base et le stockage du site sont rendus intacts.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Outils d'essai
// ---------------------------------------------------------------------------

/** Compte fictif (administrateur ou famille), supprimé par le rollback. */
function tests_envoi_compte(string $role = 'admin'): int
{
    return db_insert('accounts', [
        'username'      => 'essai-envoi-' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai envoi',
        'password_hash' => password_make('MotDePasseEssai123!'),
        'role'          => $role,
        'branch_id'     => null,
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
}

/** Fichier texte d'essai de la taille demandée (contenu lisible, type réel texte). */
function tests_envoi_texte(int $octets, string $extension = 'txt'): string
{
    $dossier = storage_path('tmp');
    $chemin  = $dossier . '/essai-' . bin2hex(random_bytes(6)) . '.' . $extension;

    // Gros blocs : le disque d'essai (monté) est lent, on limite les appels.
    $ligne = str_repeat('Ligne d’essai pour l’envoi par morceaux. ', 1024);
    $handle = fopen($chemin, 'wb');
    $ecrits = 0;
    while ($ecrits < $octets) {
        $reste = $octets - $ecrits;
        $bloc  = strlen($ligne) <= $reste ? $ligne : substr($ligne, 0, $reste);
        fwrite($handle, $bloc);
        $ecrits += strlen($bloc);
    }
    fclose($handle);

    return $chemin;
}

/**
 * Joue le protocole comme le navigateur : init, morceaux, état, terminer.
 *
 * @param int[] $morceaux numéros à envoyer, dans cet ordre (vide = tous)
 * @return array{init:array, envoi:array|null, terminer:array|null, etats:array<int, array>}
 */
function tests_envoi_jouer(
    int $compte,
    string $nom,
    string $chemin,
    array $morceaux = [],
    bool $creerFiche = true,
    bool $terminer = true,
    ?int $albumId = null
): array {
    $taille = (int) filesize($chemin);
    $init   = envoi_creer($compte, $nom, $taille, 'text/plain', $creerFiche, null, $albumId);
    $resultat = ['init' => $init, 'envoi' => null, 'terminer' => null, 'etats' => []];

    if (!$init['ok']) {
        return $resultat;
    }

    $envoi = $init['envoi'];
    $total = (int) $envoi['chunks_total'];
    $aEnvoyer = $morceaux !== [] ? $morceaux : range(0, $total - 1);

    foreach ($aEnvoyer as $numero) {
        $morceau = (int) $envoi['chunk_size'];
        $debut   = $numero * $morceau;
        $longueur = min($morceau, $taille - $debut);

        // On découpe le fichier d'essai en morceau, comme Blob.slice.
        $partiel = storage_path('tmp') . '/lot-' . bin2hex(random_bytes(6)) . '.part';
        $entree = fopen($chemin, 'rb');
        fseek($entree, $debut);
        $sortie = fopen($partiel, 'wb');
        $reste = $longueur;
        while ($reste > 0) {
            $bloc = fread($entree, min(1048576, $reste));
            if ($bloc === false || $bloc === '') {
                break;
            }
            fwrite($sortie, $bloc);
            $reste -= strlen($bloc);
        }
        fclose($entree);
        fclose($sortie);

        $resultat['etats'][$numero] = envoi_recevoir_morceau($envoi, $numero, $partiel, $longueur);
        @unlink($partiel);

        $envoi = envoi_par_token((string) $envoi['token']);
    }

    $resultat['envoi'] = $envoi;

    if ($terminer) {
        $resultat['terminer'] = envoi_terminer($envoi);
        $resultat['envoi']    = envoi_par_token((string) $envoi['token']);
    }

    return $resultat;
}

/**
 * Envoie une requête à l'application dans le processus courant.
 *
 * @param array<string, string> $post
 * @return array{code:int, location:string, corps:string, erreur:string}
 */
function tests_envoi_requete(string $methode, string $url, ?int $compte, array $post = [], array $entetes = []): array
{
    require_once HDF_APP . '/router.php';
    if (empty($GLOBALS['hdf_routes'])) {
        require_once HDF_APP . '/routes.php';
    }

    $parties = parse_url($url);

    // Le jeton CSRF de la session d'essai est conservé : les tests l'envoient
    // dans leur POST pour vérifier le chemin complet.
    $jetonCsrf = $_SESSION['_csrf'] ?? null;
    $_SESSION  = $compte === null ? [] : ['account_id' => $compte];
    if (is_string($jetonCsrf) && $compte !== null) {
        $_SESSION['_csrf'] = $jetonCsrf;
    }
    $_GET     = [];
    if (isset($parties['query'])) {
        parse_str((string) $parties['query'], $_GET);
    }
    $_POST  = $post;
    $_FILES = [];
    $_SERVER['REQUEST_METHOD'] = $methode;
    $_SERVER['REQUEST_URI']    = $url;
    $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    foreach ($entetes as $nom => $valeur) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $nom))] = $valeur;
    }

    header_remove();
    http_response_code(200);

    $niveau = ob_get_level();
    ob_start();
    $erreur = '';
    try {
        dispatch_request();
    } catch (HdfTestArret) {
        // Fin normale d'une page (redirection, réponse JSON, refus).
    } catch (Throwable $e) {
        $erreur = get_class($e) . ' : ' . $e->getMessage();
    }
    $corps = '';
    while (ob_get_level() > $niveau) {
        $corps = (string) ob_get_clean() . $corps;
    }

    $location = '';
    foreach (headers_list() as $entete) {
        if (stripos($entete, 'Location:') === 0) {
            $location = trim(substr($entete, 9));
        }
    }

    return ['code' => (int) http_response_code(), 'location' => $location, 'corps' => $corps, 'erreur' => $erreur];
}

/** Décode une réponse JSON de test. */
function tests_envoi_json(string $corps): array
{
    $donnees = json_decode($corps, true);
    return is_array($donnees) ? $donnees : [];
}

// ---------------------------------------------------------------------------
// Les tests
// ---------------------------------------------------------------------------

function tests_envoi(): void
{
    echo "Envoi par morceaux — protocole, reprise, droits, doublons, ménage\n";

    $compte  = tests_envoi_compte('admin');
    $autre   = tests_envoi_compte('admin');
    $famille = tests_envoi_compte('famille');

    // Taille des morceaux ramenée à 1 Mo pour aller vite (réglage borné).
    $morceauAvant = (string) site_setting('envoi_morceau_mo');
    save_site_setting('envoi_morceau_mo', '1');

    // Ce qui existait avant : on ne touchera à rien d'autre (un envoi réel
    // en cours ne doit jamais être effacé par un test).
    $avantDossiers = tests_envoi_dossiers();
    $avantFichiers = tests_envoi_fichiers();

    try {
        db_transaction(static function () use ($compte, $autre, $famille): void {
            tests_envoi_protocole($compte);
            tests_envoi_refus($compte);
            tests_envoi_familles_media($compte);
            tests_envoi_doublons($compte);
            tests_envoi_droits($compte, $autre, $famille);
            tests_envoi_formulaire($compte, $autre, $famille);
            tests_envoi_fiches($compte, $autre, $famille);
            tests_envoi_menage($compte);

            // Tout est annulé : la base du site est rendue intacte.
            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    } finally {
        save_site_setting('envoi_morceau_mo', $morceauAvant);

        // Fichiers d'essai : seuls ceux créés pendant les tests sont effacés
        // (la transaction ne s'applique qu'à la base).
        foreach (array_diff(tests_envoi_dossiers(), $avantDossiers) as $dossier) {
            foreach (glob($dossier . '/*') ?: [] as $fichier) {
                if (is_file($fichier)) {
                    @unlink($fichier);
                }
            }
            @rmdir($dossier);
        }
        foreach (array_diff(tests_envoi_fichiers(), $avantFichiers) as $fichier) {
            @unlink($fichier);
        }
    }
}

/** Dossiers d'envoi présents (avant / après les tests). @return string[] */
function tests_envoi_dossiers(): array
{
    $dossiers = [];
    foreach (glob(envoi_racine() . '/*') ?: [] as $chemin) {
        if (is_dir($chemin)) {
            $dossiers[] = $chemin;
        }
    }
    return $dossiers;
}

/** Fichiers du stockage (médias et miniatures), pour effacer les nôtres. @return string[] */
function tests_envoi_fichiers(): array
{
    $fichiers = [];
    foreach (['medias', 'miniatures'] as $sous) {
        $base = storage_path((string) config('storage.' . ($sous === 'medias' ? 'medias' : 'thumbs')));
        foreach (glob($base . '/*/*/*') ?: [] as $chemin) {
            if (is_file($chemin)) {
                $fichiers[] = $chemin;
            }
        }
    }
    return $fichiers;
}

/** 1. Le protocole complet, et la reprise après une coupure. */
function tests_envoi_protocole(int $compte): void
{
    // --- Un envoi de 2,5 Mo : trois morceaux de 1 Mo ---
    $fichier = tests_envoi_texte(2_500_000);
    $somme   = hash_file('sha256', $fichier);

    // Coupure au milieu : seuls les morceaux 0 et 1 arrivent.
    $resultat = tests_envoi_jouer($compte, 'essai-coupure.txt', $fichier, [0, 1], true, false);
    verifier(
        'envoi : « init » rend un identifiant de 128 bits, lié au compte',
        $resultat['init']['ok'] && strlen((string) $resultat['init']['envoi']['token']) === 32
            && preg_match('/^[a-f0-9]{32}$/', (string) $resultat['init']['envoi']['token']) === 1
            && (int) $resultat['init']['envoi']['account_id'] === $compte,
        (string) ($resultat['init']['error'] ?? '')
    );

    verifier(
        'envoi : trois morceaux sont attendus pour 2,5 Mo (morceaux de 1 Mo)',
        (int) $resultat['init']['envoi']['chunks_total'] === 3,
        'obtenu : ' . (int) ($resultat['init']['envoi']['chunks_total'] ?? 0)
    );

    $pieces = envoi_morceaux($resultat['envoi']);
    verifier(
        'envoi : après une coupure, l’état dit exactement ce qui est reçu',
        $pieces['recus'] === [0, 1] && $pieces['complet'] === false && count($pieces['manquants']) === 1,
        json_encode($pieces)
    );
    verifier(
        'envoi : un envoi incomplet ne peut pas être terminé',
        envoi_terminer($resultat['envoi'])['ok'] === false
    );

    // Reprise : le morceau manquant, puis la fin.
    $morceau = (int) $resultat['envoi']['chunk_size'];
    $debut   = 2 * $morceau;
    $partiel = storage_path('tmp') . '/reprise.part';
    file_put_contents($partiel, substr((string) file_get_contents($fichier), $debut));
    $reprise = envoi_recevoir_morceau($resultat['envoi'], 2, $partiel, (int) filesize($partiel));
    @unlink($partiel);

    verifier('envoi : la reprise accepte le morceau manquant', $reprise['ok'] === true, $reprise['error']);

    $envoi = envoi_par_token((string) $resultat['envoi']['token']);
    $fin   = envoi_terminer($envoi);
    verifier('envoi : l’envoi repris se termine', $fin['ok'] === true && $fin['etat'] === 'termine', $fin['error']);

    $envoi  = envoi_par_id((int) $envoi['id']);
    $doc    = db_one('SELECT * FROM documents WHERE id = ?', [(int) $envoi['document_id']]);
    $chemin = $doc ? storage_absolute((string) $doc['rel_path']) : '';

    verifier(
        'envoi : le fichier assemblé est identique à l’original (empreinte SHA-256)',
        $doc !== null && (string) $doc['sha256'] === $somme
            && is_file($chemin) && hash_file('sha256', $chemin) === $somme
    );
    verifier(
        'envoi : la taille annoncée est respectée',
        $doc !== null && (int) $doc['size'] === (int) filesize($fichier)
    );
    verifier(
        'envoi : la fiche créée est PRIVÉE par défaut',
        $doc !== null && (string) $doc['visibility'] === 'prive',
        $doc !== null ? (string) $doc['visibility'] : 'aucune fiche'
    );
    verifier(
        'envoi : le nom d’origine est conservé, le nom sur le disque est aléatoire',
        $doc !== null && (string) $doc['original_name'] === 'essai-coupure.txt'
            && !str_contains((string) $doc['rel_path'], 'essai-coupure')
    );
    verifier(
        'envoi : les morceaux ont disparu une fois le fichier assemblé',
        ($resultat['envoi']['token'] ?? '') !== ''
            && !is_dir(envoi_racine() . '/' . (string) $envoi['token'])
    );
    verifier(
        'envoi : le fichier n’est jamais dans le dossier public',
        $chemin !== '' && !str_starts_with($chemin, HDF_ROOT . '/public'),
        $chemin
    );
    verifier(
        'envoi : aucun morceau ni fichier assemblé n’a d’adresse web',
        (static function (): bool {
            $routes = (string) @file_get_contents(HDF_APP . '/routes.php');
            return !preg_match("#route\\('GET', '/(envoi|morceau|uploads)#", $routes) === true;
        })()
    );

    @unlink($fichier);

    // --- Morceau en double et morceau hors ordre ---
    $fichier  = tests_envoi_texte(1_500_000);
    $resultat = tests_envoi_jouer($compte, 'essai-ordre.txt', $fichier, [1, 0], true, false);
    verifier(
        'envoi : les morceaux peuvent arriver dans le désordre',
        ($resultat['etats'][1]['ok'] ?? false) && ($resultat['etats'][0]['ok'] ?? false)
            && envoi_morceaux($resultat['envoi'])['recus'] === [0, 1]
    );

    $envoi = $resultat['envoi'];
    $morceau = (int) $envoi['chunk_size'];
    $partiel = storage_path('tmp') . '/double.part';
    file_put_contents($partiel, str_repeat('A', $morceau));
    $avant = (int) filesize(envoi_morceau_chemin((string) $envoi['token'], 0));
    $double = envoi_recevoir_morceau($envoi, 0, $partiel, $morceau);
    $apres  = (int) filesize(envoi_morceau_chemin((string) $envoi['token'], 0));
    @unlink($partiel);
    verifier(
        'envoi : renvoyer un morceau déjà reçu ne change rien (sans effet)',
        $double['ok'] === true && $double['deja'] === true && $avant === $apres
    );

    // --- Morceau de taille incohérente ---
    $partiel = storage_path('tmp') . '/court.part';
    file_put_contents($partiel, 'trop court');
    $faux = envoi_recevoir_morceau($envoi, 0, $partiel, 3);
    @unlink($partiel);
    verifier(
        'envoi : un morceau de taille incohérente est refusé',
        $faux['ok'] === false && str_contains($faux['error'], 'taille incohérente'),
        $faux['error']
    );

    $faux = envoi_recevoir_morceau($envoi, 99, $partiel, 100);
    verifier(
        'envoi : un numéro de morceau hors limites est refusé',
        $faux['ok'] === false && str_contains($faux['error'], 'hors limites')
    );

    envoi_annuler($envoi);

    verifier(
        'envoi : « annuler » supprime les morceaux et marque l’envoi annulé',
        !is_dir(envoi_racine() . '/' . (string) $envoi['token'])
            && (string) envoi_par_id((int) $envoi['id'])['state'] === 'annule'
    );

    @unlink($fichier);

    // --- Fichier préparé par un formulaire (documents, vidéos) ---
    $fichier  = tests_envoi_texte(300_000);
    $resultat = tests_envoi_jouer($compte, 'essai-formulaire.txt', $fichier, [], false);
    $envoi    = $resultat['envoi'];

    verifier(
        'envoi : un fichier préparé pour un formulaire attend (état « prêt »)',
        ($resultat['terminer']['ok'] ?? false) && $envoi !== null && (string) $envoi['state'] === 'pret'
            && (int) $envoi['document_id'] === 0,
        (string) ($resultat['terminer']['error'] ?? '')
    );

    $pret = envoi_fichier_pret($compte, (string) $envoi['token']);
    verifier('envoi : le fichier préparé est retrouvé pour son compte', $pret !== null);
    verifier(
        'envoi : un fichier préparé n’est jamais visible (visibilité privée, aucune fiche)',
        $pret !== null && (int) $pret['document_id'] === 0
            && db_value('SELECT COUNT(*) FROM documents WHERE rel_path = ?', [(string) $pret['rel_path']], 0) === 0
    );

    // Une fiche d'essai, le temps de vérifier le rattachement (transaction).
    $documentEssai = db_insert('documents', [
        'title'         => 'Fiche d’essai (envoi)',
        'kind'          => 'document',
        'visibility'    => 'prive',
        'rel_path'      => (string) $pret['rel_path'],
        'original_name' => 'essai.txt',
        'mime'          => 'text/plain',
        'ext'           => 'txt',
        'size'          => (int) $envoi['size'],
        'is_attachment' => 0,
        'created_by'    => $compte,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);

    // Nouvelle séquence (1.26.2) : on réserve le fichier, puis on le lie.
    verifier('envoi : un fichier préparé peut être réservé une fois', envoi_reserver($pret));
    verifier('envoi : un fichier déjà réservé ne peut pas l’être une seconde fois', !envoi_reserver($pret));
    envoi_marquer_utilise($pret, $documentEssai);
    verifier(
        'envoi : un fichier préparé ne peut être utilisé qu’une fois',
        envoi_fichier_pret($compte, (string) $envoi['token']) === null
            && (string) envoi_par_id((int) $envoi['id'])['state'] === 'termine'
            && (int) envoi_par_id((int) $envoi['id'])['document_id'] === $documentEssai
    );
    db_exec('DELETE FROM documents WHERE id = ?', [$documentEssai]);

    @unlink($fichier);
}

/** 2. Tous les refus, avant le premier octet quand c'est possible. */
function tests_envoi_refus(int $compte): void
{
    $reglages = envoi_reglages();

    $trop = envoi_creer($compte, 'enorme.mp4', $reglages['max_octets'] + 1);
    verifier(
        'refus : un fichier plus gros que la limite est refusé avant tout envoi',
        !$trop['ok'] && str_contains($trop['error'], 'trop volumineux'),
        $trop['error']
    );

    $extension = envoi_creer($compte, 'programme.exe', 1000);
    verifier(
        'refus : une extension interdite est refusée',
        !$extension['ok'] && str_contains($extension['error'], 'Extension non autorisée'),
        $extension['error']
    );

    $espace = envoi_creer($compte, 'gros-film.mp4', 10 * 1024 * 1024, '', true, 0);
    verifier(
        'refus : sans place disque (taille + marge de 2 Go), l’envoi ne commence pas',
        !$espace['ok'] && str_contains($espace['error'], 'pas assez de place'),
        $espace['error']
    );

    // Faux type : un exécutable renommé en .jpg ne passe pas à l'assemblage.
    $faux = storage_path('tmp') . '/faux.jpg';
    file_put_contents($faux, "#!/bin/sh\necho bonjour\n");
    $resultat = tests_envoi_jouer($compte, 'faux.jpg', $faux);
    verifier(
        'refus : un exécutable renommé .jpg est refusé à l’assemblage (type réel)',
        ($resultat['terminer']['ok'] ?? true) === false
            && str_contains((string) ($resultat['terminer']['error'] ?? ''), 'n’est pas celui d’une photo'),
        (string) ($resultat['terminer']['error'] ?? 'aucune erreur')
    );
    verifier(
        'refus : aucun document n’est créé pour un faux type',
        db_value('SELECT COUNT(*) FROM documents WHERE original_name = ?', ['faux.jpg'], 0) === 0
    );
    $envoi = $resultat['envoi'];
    verifier(
        'refus : le fichier refusé est effacé, l’envoi marqué en échec',
        $envoi !== null && (string) envoi_par_id((int) $envoi['id'])['state'] === 'echec'
    );
    @unlink($faux);

    // Un nom ne peut pas contenir de chemin.
    $nom = envoi_nom_propre('../../etc/passwd.txt');
    verifier('refus : un nom de fichier ne peut pas sortir du dossier', $nom === 'passwd.txt', $nom);

    // Un identifiant falsifié ne construit aucun chemin.
    $leve = false;
    try {
        envoi_dossier('../../etc');
    } catch (Throwable) {
        $leve = true;
    }
    verifier('refus : un identifiant d’envoi falsifié ne construit aucun chemin', $leve);
    verifier('refus : un jeton mal formé n’est pas retrouvé en base', envoi_par_token('../../etc/passwd') === null);

    $leve = false;
    try {
        envoi_morceau_chemin(str_repeat('a', 32), 1000000);
    } catch (Throwable) {
        $leve = true;
    }
    verifier('refus : un numéro de morceau démesuré ne construit aucun chemin', $leve);

    // Trois envois simultanés au maximum.
    $jetons = [];
    for ($i = 0; $i < 3; $i++) {
        $creation = envoi_creer($compte, 'simultane-' . $i . '.txt', 1000);
        if ($creation['ok']) {
            $jetons[] = (string) $creation['envoi']['token'];
        }
    }
    $quatrieme = envoi_creer($compte, 'simultane-4.txt', 1000);
    verifier(
        'refus : pas plus de trois envois en cours pour un même administrateur',
        count($jetons) === 3 && !$quatrieme['ok'] && str_contains($quatrieme['error'], 'déjà 3 envois'),
        $quatrieme['error']
    );
    foreach ($jetons as $jeton) {
        $envoi = envoi_par_token($jeton);
        if ($envoi !== null) {
            envoi_annuler($envoi);
        }
    }

    // Les réglages sont bornés, même si on force une valeur absurde.
    $avant = (string) site_setting('envoi_morceau_mo');
    save_site_setting('envoi_morceau_mo', '99');
    $haut = envoi_reglages()['morceau_mo'];
    save_site_setting('envoi_morceau_mo', '-9');
    $bas = envoi_reglages()['morceau_mo'];
    save_site_setting('envoi_morceau_mo', $avant);
    verifier(
        'refus : la taille d’un morceau reste entre 1 et 20 Mo, quoi qu’on saisisse',
        $haut === 20 && $bas >= 1 && $bas <= 20,
        $haut . ' / ' . $bas
    );

    verifier(
        'refus : la marge de sécurité de 2 Go est bien exigée sur le disque',
        envoi_espace_suffisant(1000) === true,
        'libre : ' . (string) storage_free_space()
    );
}

/** 3. Doublons : on avertit, on ne supprime jamais tout seul. */
function tests_envoi_doublons(int $compte): void
{
    $fichier = tests_envoi_texte(200_000);
    $somme   = hash_file('sha256', $fichier);

    $premier = tests_envoi_jouer($compte, 'premier.txt', $fichier);
    verifier('doublon : le premier envoi crée sa fiche', ($premier['terminer']['etat'] ?? '') === 'termine');

    $avant = (int) db_value('SELECT COUNT(*) FROM documents', [], 0);
    $second = tests_envoi_jouer($compte, 'copie.txt', $fichier);
    $apres  = (int) db_value('SELECT COUNT(*) FROM documents', [], 0);

    verifier(
        'doublon : un fichier identique (même empreinte) est signalé, pas ajouté',
        ($second['terminer']['etat'] ?? '') === 'doublon' && $apres === $avant,
        json_encode($second['terminer']['doublon'] ?? null)
    );
    verifier(
        'doublon : le message indique la fiche existante (titre)',
        (int) ($second['terminer']['doublon']['id'] ?? 0) > 0
            && (string) ($second['terminer']['doublon']['title'] ?? '') === 'premier',
        (string) ($second['terminer']['doublon']['title'] ?? '')
    );
    verifier(
        'doublon : l’empreinte est bien celle du fichier',
        ($second['envoi']['sha256'] ?? '') === $somme
    );

    // « Ignorer » : le fichier assemblé est supprimé, rien n'est créé.
    $ignore = envoi_decider($second['envoi'], 'ignorer');
    verifier(
        'doublon : « ignorer » supprime le fichier assemblé et n’ajoute rien',
        $ignore['etat'] === 'annule'
            && (int) db_value('SELECT COUNT(*) FROM documents', [], 0) === $avant
            && !is_file(storage_absolute((string) $second['envoi']['rel_path']))
    );

    // « Garder quand même » : une deuxième fiche, toujours privée.
    $troisieme = tests_envoi_jouer($compte, 'copie2.txt', $fichier);
    $garde     = envoi_decider($troisieme['envoi'], 'garder');
    $doc       = db_one('SELECT * FROM documents WHERE id = ?', [(int) ($garde['envoi']['document_id'] ?? 0)]);
    verifier(
        'doublon : « garder quand même » crée la fiche demandée, privée',
        $garde['etat'] === 'termine' && $doc !== null && (string) $doc['visibility'] === 'prive'
            && (int) db_value('SELECT COUNT(*) FROM documents', [], 0) === $avant + 1
    );

    @unlink($fichier);
}

/** Faux film QuickTime d'essai : en-tête `ftyp`/`qt`, comme un téléphone. */
function tests_envoi_film_mov(int $octets = 65536): string
{
    $chemin = storage_path('tmp') . '/essai-form-mov-' . bin2hex(random_bytes(6)) . '.mov';
    $entete = pack('N', 20) . 'ftypqt  ' . pack('N', 0) . 'qt  ';
    file_put_contents($chemin, $entete . str_repeat("\0", max(0, $octets - strlen($entete))));
    return $chemin;
}

/** Faux film MP4 d'essai : en-tête `ftyp`/`isom`, déjà au bon format. */
function tests_envoi_film_mp4(int $octets = 65536): string
{
    $chemin = storage_path('tmp') . '/essai-form-mp4-' . bin2hex(random_bytes(6)) . '.mp4';
    $entete = pack('N', 24) . 'ftypisom' . pack('N', 0) . 'isomiso2';
    file_put_contents($chemin, $entete . str_repeat("\0", max(0, $octets - strlen($entete))));
    return $chemin;
}

/**
 * Un fichier assemblé, prêt pour un formulaire (état « pret »).
 *
 * Le contenu reçoit une marque aléatoire : sans elle, deux fichiers d'essai
 * identiques déclencheraient la détection de doublon et l'envoi resterait
 * « doublon » au lieu de « prêt ».
 *
 * @return array<string, mixed>|null
 */
function tests_envoi_pret(int $compte, string $chemin, string $nom): ?array
{
    file_put_contents($chemin, hex2bin(bin2hex(random_bytes(8))), FILE_APPEND);
    $resultat = tests_envoi_jouer($compte, $nom, $chemin, [], false);
    $envoi    = $resultat['envoi'];
    if (!is_array($envoi) || !($resultat['terminer']['ok'] ?? false)) {
        return null;
    }
    return envoi_fichier_pret($compte, (string) $envoi['token']);
}

/** 5. Le formulaire : le fichier assemblé remplace le champ « Fichier ». */
function tests_envoi_formulaire(int $compte, int $autre, int $famille): void
{
    // --- a. Un document arrive par morceaux, puis le formulaire l'enregistre
    // sans que personne ne touche au champ « Fichier ».
    $source = tests_envoi_texte(200_000);
    $pret   = tests_envoi_pret($compte, $source, 'lettre-essai.txt');
    @unlink($source);
    verifier('formulaire : un fichier assemblé est prêt à être rattaché', $pret !== null);

    if ($pret === null) {
        return;
    }

    // Chaque requête repart d'une session propre avec un jeton CSRF neuf :
    // c'est ce que fait un vrai navigateur, et cela évite qu'un jeton d'une
    // requête précédente ne fasse échouer la suivante pour la mauvaise raison.
    $nouveauJeton = static function () use ($compte): string {
        $_SESSION = ['account_id' => $compte];
        return csrf_token();
    };

    $reponse = tests_envoi_requete('POST', '/admin/documents/nouveau', $compte, [
        '_csrf'      => $nouveauJeton(),
        'title'      => 'Lettre venue par morceaux',
        'visibility' => 'prive',
        'envoi'      => (string) $pret['token'],
    ]);
    $doc = db_one('SELECT * FROM documents WHERE title = ?', ['Lettre venue par morceaux']);
    verifier(
        'formulaire : sans champ « Fichier », le jeton `envoi` crée la fiche',
        $doc !== null && (string) $doc['rel_path'] === (string) $pret['rel_path'],
        $reponse['code'] . ' ' . $reponse['location'] . ' ' . $reponse['erreur']
    );
    verifier(
        'formulaire : l’envoi est alors lié à cette fiche, une seule fois',
        $doc !== null
            && (string) envoi_par_id((int) $pret['id'])['state'] === 'termine'
            && (int) envoi_par_id((int) $pret['id'])['document_id'] === (int) $doc['id']
    );

    // --- b. Le même jeton, réutilisé : refusé, et aucune seconde fiche.
    $avant = (int) db_value('SELECT COUNT(*) FROM documents', [], 0);
    $_SESSION = ['account_id' => $compte];
    $rejoue = tests_envoi_requete('POST', '/admin/documents/nouveau', $compte, [
        '_csrf'      => $nouveauJeton(),
        'title'      => 'Deuxième fiche avec le même fichier',
        'visibility' => 'prive',
        'envoi'      => (string) $pret['token'],
    ]);
    $erreurs = (array) ($_SESSION['_form_errors'] ?? []);
    verifier(
        'formulaire : un jeton déjà utilisé est refusé (aucune seconde fiche)',
        (int) db_value('SELECT COUNT(*) FROM documents', [], 0) === $avant
            && db_one('SELECT id FROM documents WHERE title = ?', ['Deuxième fiche avec le même fichier']) === null
            && $erreurs !== [],
        $rejoue['code'] . ' ' . implode(' | ', $erreurs)
    );
    unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

    // --- c. Le fichier d'un autre administrateur : refusé.
    $sourceAutre = tests_envoi_texte(120_000);
    $pretAutre   = tests_envoi_pret($autre, $sourceAutre, 'lettre-autre.txt');
    @unlink($sourceAutre);
    verifier('formulaire : le second administrateur a bien son propre fichier prêt', $pretAutre !== null);

    if ($pretAutre !== null) {
        $avantAutre = (int) db_value('SELECT COUNT(*) FROM documents', [], 0);
        $_SESSION = ['account_id' => $compte];
        tests_envoi_requete('POST', '/admin/documents/nouveau', $compte, [
            '_csrf'      => $nouveauJeton(),
            'title'      => 'Fiche volée',
            'visibility' => 'prive',
            'envoi'      => (string) $pretAutre['token'],
        ]);
        $erreurs = (array) ($_SESSION['_form_errors'] ?? []);
        verifier(
            'formulaire : le jeton d’un autre compte est refusé',
            (int) db_value('SELECT COUNT(*) FROM documents', [], 0) === $avantAutre
                && db_one('SELECT id FROM documents WHERE title = ?', ['Fiche volée']) === null
                && $erreurs !== []
                && (string) envoi_par_id((int) $pretAutre['id'])['state'] === 'pret',
            implode(' | ', $erreurs)
        );
        unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
    }

    // --- d. Un jeton inventé (bien formé, mais inconnu) : refusé aussi.
    $avantFaux = (int) db_value('SELECT COUNT(*) FROM documents', [], 0);
    $_SESSION = ['account_id' => $compte];
    tests_envoi_requete('POST', '/admin/documents/nouveau', $compte, [
        '_csrf'      => $nouveauJeton(),
        'title'      => 'Fiche fantôme',
        'visibility' => 'prive',
        'envoi'      => str_repeat('b', 32),
    ]);
    $erreurs = (array) ($_SESSION['_form_errors'] ?? []);
    verifier(
        'formulaire : un jeton inconnu est refusé (aucune fiche créée)',
        (int) db_value('SELECT COUNT(*) FROM documents', [], 0) === $avantFaux && $erreurs !== [],
        implode(' | ', $erreurs)
    );
    unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

    // --- e. Sans fichier ET sans jeton : le message le dit.
    $_SESSION = ['account_id' => $compte];
    tests_envoi_requete('POST', '/admin/documents/nouveau', $compte, [
        '_csrf'      => $nouveauJeton(),
        'title'      => 'Rien du tout',
        'visibility' => 'prive',
    ]);
    $erreurs = implode(' | ', (array) ($_SESSION['_form_errors'] ?? []));
    verifier(
        'formulaire : sans fichier ni jeton, l’erreur demande un fichier',
        str_contains($erreurs, 'Choisissez un fichier'),
        $erreurs
    );
    unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

    // --- f. Formulaire vidéo, fichier MP4 déjà bon : la vidéo est créée et
    // visible tout de suite (aucune conversion à attendre).
    $mp4 = tests_envoi_film_mp4();
    $pretMp4 = tests_envoi_pret($compte, $mp4, 'film-essai.mp4');
    @unlink($mp4);
    verifier('formulaire : un MP4 assemblé est prêt pour le formulaire vidéo', $pretMp4 !== null);

    if ($pretMp4 !== null) {
        $_SESSION = ['account_id' => $compte];
        tests_envoi_requete('POST', '/admin/videos/nouvelle', $compte, [
            '_csrf'      => $nouveauJeton(),
            'title'      => 'Film venu par morceaux',
            'visibility' => 'famille',
            'envoi'      => (string) $pretMp4['token'],
        ]);
        $film = db_one("SELECT * FROM documents WHERE title = ? AND kind = 'video'", ['Film venu par morceaux']);
        verifier(
            'formulaire vidéo : le film assemblé crée la fiche, sans champ « Fichier »',
            $film !== null && (string) $film['rel_path'] === (string) $pretMp4['rel_path'],
            implode(' | ', (array) ($_SESSION['_form_errors'] ?? []))
        );
        verifier(
            'formulaire vidéo : un MP4 déjà bon n’entre pas dans la file de conversion',
            $film !== null && conversion_pour_document((int) $film['id']) === null
        );

        // --- g. Formulaire vidéo, fichier .mov : la vidéo existe, mais reste
        // invisible pour la famille tant que la conversion n'est pas finie.
        $mov = tests_envoi_film_mov();
        $pretMov = tests_envoi_pret($compte, $mov, 'film-telephone.mov');
        @unlink($mov);
        verifier('formulaire : un MOV assemblé est prêt pour le formulaire vidéo', $pretMov !== null);

        if ($pretMov !== null) {
            unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
            $_SESSION = ['account_id' => $compte];
            tests_envoi_requete('POST', '/admin/videos/nouvelle', $compte, [
                '_csrf'      => $nouveauJeton(),
                'title'      => 'Film de téléphone',
                'visibility' => 'famille',
                'envoi'      => (string) $pretMov['token'],
            ]);
            $telephone = db_one("SELECT * FROM documents WHERE title = ? AND kind = 'video'", ['Film de téléphone']);
            verifier(
                'formulaire vidéo : un MOV assemblé crée la fiche et part en conversion',
                $telephone !== null && conversion_pour_document((int) $telephone['id']) !== null,
                implode(' | ', (array) ($_SESSION['_form_errors'] ?? []))
            );

            if ($telephone !== null) {
                $_SESSION = ['account_id' => $famille];
                verifier(
                    'formulaire vidéo : tant que la conversion n’est pas finie, la famille ne la voit pas',
                    find_viewable_document((int) $telephone['id']) === null
                );

                $_SESSION = ['account_id' => $compte];
                verifier(
                    'formulaire vidéo : l’administrateur, lui, voit la fiche pendant la conversion',
                    find_viewable_document((int) $telephone['id']) !== null
                );
            }
        }
    }

    // --- j. Une erreur de formulaire ne fait pas perdre le fichier reçu.
    // Le jeton est conservé avec les valeurs saisies ; la page le réaffiche
    // dans le champ caché (sinon il faudrait renvoyer plusieurs gigaoctets
    // pour une faute de frappe dans le titre).
    $sourceErr = tests_envoi_texte(70_000);
    $pretErr   = tests_envoi_pret($compte, $sourceErr, 'lettre-erreur.txt');
    @unlink($sourceErr);
    if ($pretErr !== null) {
        $_SESSION = ['account_id' => $compte];
        tests_envoi_requete('POST', '/admin/documents/nouveau', $compte, [
            '_csrf'      => $nouveauJeton(),
            'title'      => '',
            'visibility' => 'prive',
            'envoi'      => (string) $pretErr['token'],
        ]);
        $garde = (string) ($_SESSION['_form_old']['envoi'] ?? '');
        verifier(
            'formulaire : une erreur de saisie conserve le fichier déjà reçu',
            $garde === (string) $pretErr['token']
                && (string) envoi_par_id((int) $pretErr['id'])['state'] === 'pret',
            'jeton conservé : ' . ($garde !== '' ? 'oui' : 'NON')
        );
        unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

        // Les trois formulaires réaffichent ce jeton dans un champ caché.
        $vues = ['/app/views/admin/document_form.php', '/app/views/admin/video_form.php', '/app/views/propositions/form.php'];
        $sansChamp = [];
        foreach ($vues as $vue) {
            $texte = (string) @file_get_contents(HDF_ROOT . $vue);
            if (preg_match('#<input type="hidden" name="envoi" value="<\?= e\(\(string\) old_value\(\$old, \'envoi\', \'\'\)\) \?>"#', $texte) !== 1) {
                $sansChamp[] = $vue;
            }
        }
        verifier(
            'formulaire : les trois vues réaffichent le jeton reçu (champ caché)',
            $sansChamp === [],
            implode(', ', $sansChamp)
        );
    }

    // --- i. La page elle-même : ce que voit la personne.
    // Sans cela, on pourrait casser le formulaire sans qu'aucun essai serveur
    // ne s'en aperçoive : champ caché absent, ancre sans cible, message qui
    // ne nomme pas le vrai bouton.
    $pageVideo = tests_envoi_requete('GET', '/admin/videos/nouvelle', $compte);
    verifier(
        'page vidéo : le bouton d’enregistrement porte l’ancre « enregistrer »',
        str_contains($pageVideo['corps'], 'id="enregistrer"')
            && str_contains($pageVideo['corps'], 'Envoyer la vidéo'),
        'code ' . $pageVideo['code']
    );
    verifier(
        'page vidéo : le champ caché « envoi » est présent, vide au départ',
        (bool) preg_match('#<input type="hidden" name="envoi" value=""#', $pageVideo['corps'])
    );

    $pageDoc = tests_envoi_requete('GET', '/admin/documents/nouveau', $compte);
    verifier(
        'page document : même ancre et même champ caché',
        str_contains($pageDoc['corps'], 'id="enregistrer"')
            && str_contains($pageDoc['corps'], 'Envoyer le document')
            && (bool) preg_match('#<input type="hidden" name="envoi" value=""#', $pageDoc['corps']),
        'code ' . $pageDoc['code']
    );

    $script = (string) @file_get_contents(HDF_ROOT . '/public/assets/envoi.js');
    verifier(
        'script : le message nomme le vrai bouton et propose l’ancre',
        str_contains($script, 'Le fichier est prêt : cliquez sur')
            && str_contains($script, 'Aller au bouton d’enregistrement')
            && str_contains($script, "ancre.href = '#' + cible")
    );
    verifier(
        'script : la case « Fichier » visée est celle du formulaire, pas celle du composant',
        str_contains($script, 'input[type="file"][name="fichier"]')
            && str_contains($script, "hasAttribute('data-envoi-champ')"),
        'la sélection doit exclure le champ interne du bloc d’envoi'
    );
    verifier(
        'script : le jeton est écrit dans le champ « envoi » du formulaire',
        str_contains($script, "champ.name = 'envoi'") && str_contains($script, 'champ.value = tache.token')
    );

    // --- h. Formulaire vidéo, fichier qui n'est pas une vidéo : refusé.
    $sourceTxt = tests_envoi_texte(80_000);
    $pretTxt   = tests_envoi_pret($compte, $sourceTxt, 'pas-une-video.txt');
    @unlink($sourceTxt);
    if ($pretTxt !== null) {
        unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
        $_SESSION = ['account_id' => $compte];
        tests_envoi_requete('POST', '/admin/videos/nouvelle', $compte, [
            '_csrf'      => $nouveauJeton(),
            'title'      => 'Pas une vidéo',
            'visibility' => 'prive',
            'envoi'      => (string) $pretTxt['token'],
        ]);
        $erreurs = implode(' | ', (array) ($_SESSION['_form_errors'] ?? []));
        verifier(
            'formulaire vidéo : un fichier qui n’est pas une vidéo est refusé',
            str_contains($erreurs, 'pas une vidéo'),
            $erreurs
        );
        unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
    }
}

/** 4. Droits : visiteur, compte de famille, autre administrateur, CSRF. */
function tests_envoi_droits(int $compte, int $autre, int $famille): void
{
    $visiteur = tests_envoi_requete('POST', '/admin/envoyer/init', null, ['nom' => 'a.txt', 'taille' => '10']);
    verifier(
        'droits : un visiteur ne peut pas lancer un envoi (renvoi vers la connexion)',
        $visiteur['code'] === 303 && str_contains($visiteur['location'], '/connexion'),
        $visiteur['code'] . ' ' . $visiteur['location']
    );

    $compteFamille = tests_envoi_requete('POST', '/admin/envoyer/init', $famille, ['nom' => 'a.txt', 'taille' => '10']);
    verifier(
        'droits : un compte de famille ne peut pas envoyer de fichier (403)',
        $compteFamille['code'] === 403,
        (string) $compteFamille['code']
    );

    $page = tests_envoi_requete('GET', '/admin/envoyer', $famille);
    verifier('droits : la page d’envoi est fermée à un compte de famille', $page['code'] === 403);

    // Sans jeton CSRF, l'action est refusée même pour un administrateur.
    $_SESSION = ['account_id' => $compte];
    $sansJeton = tests_envoi_requete('POST', '/admin/envoyer/init', $compte, ['nom' => 'a.txt', 'taille' => '10']);
    verifier('droits : sans jeton CSRF, aucun envoi ne commence (403)', $sansJeton['code'] === 403);

    // Avec le jeton, l'init répond en JSON.
    $creation = envoi_creer($compte, 'droits.txt', 1000);
    $jeton    = (string) $creation['envoi']['token'];

    $_SESSION = ['account_id' => $compte];
    $session = csrf_token();
    $avecJeton = tests_envoi_requete('POST', '/admin/envoyer/init', $compte, [
        '_csrf' => $session, 'nom' => 'droits2.txt', 'taille' => '1000',
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $json = tests_envoi_json($avecJeton['corps']);
    verifier(
        'droits : avec le jeton CSRF, « init » répond en JSON et crée l’envoi',
        $avecJeton['code'] === 200 && ($json['ok'] ?? false) === true
            && strlen((string) ($json['envoi']['token'] ?? '')) === 32,
        $avecJeton['corps']
    );

    // Un autre administrateur ne peut ni voir l'état d'un envoi qui n'est pas
    // le sien, ni le terminer, ni l'annuler.
    $_SESSION = ['account_id' => $autre];
    $etat = tests_envoi_requete('GET', '/admin/envoyer/etat?token=' . $jeton, $autre);
    verifier(
        'droits : l’état de l’envoi d’un autre administrateur est refusé (403)',
        $etat['code'] === 403,
        (string) $etat['code']
    );

    $_SESSION = ['account_id' => $autre];
    $terminer = tests_envoi_requete('POST', '/admin/envoyer/terminer', $autre, [
        '_csrf' => csrf_token(), 'token' => $jeton,
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    verifier(
        'droits : terminer l’envoi d’un autre administrateur est refusé (403)',
        $terminer['code'] === 403,
        (string) $terminer['code']
    );

    // Identifiant inconnu (bien formé) : 404, sans révéler quoi que ce soit.
    $_SESSION = ['account_id' => $compte];
    $inconnu = tests_envoi_requete('GET', '/admin/envoyer/etat?token=' . str_repeat('a', 32), $compte);
    verifier('droits : un identifiant d’envoi inconnu répond 404', $inconnu['code'] === 404);

    $falsifie = tests_envoi_requete('GET', '/admin/envoyer/etat?token=' . rawurlencode('../../etc/passwd'), $compte);
    verifier(
        'droits : une tentative de traversée de dossier dans le jeton répond 404',
        $falsifie['code'] === 404 && !str_contains($falsifie['corps'], 'root:'),
        (string) $falsifie['code']
    );

    envoi_annuler(envoi_par_token($jeton) ?? $creation['envoi']);

    // La page « Mes envois » fonctionne pour un administrateur.
    $_SESSION = ['account_id' => $compte];
    $mesEnvois = tests_envoi_requete('GET', '/admin/envois', $compte);
    verifier('droits : un administrateur ouvre « Mes envois »', $mesEnvois['code'] === 200, $mesEnvois['erreur']);
    verifier_contient('droits : « Mes envois » affiche le titre de la page', 'Mes envois', $mesEnvois['corps']);

    $tous = tests_envoi_requete('GET', '/admin/envois?tous=1', $compte);
    verifier('droits : la supervision « Tous les envois » est en lecture seule', $tous['code'] === 200
        && str_contains($tous['corps'], 'lecture seule'));

    $pageEnvoi = tests_envoi_requete('GET', '/admin/envoyer', $compte);
    verifier('droits : la page d’envoi s’ouvre à un administrateur', $pageEnvoi['code'] === 200, $pageEnvoi['erreur']);
    verifier_contient('droits : la page d’envoi contient la zone de dépôt', 'data-envoi-zone', $pageEnvoi['corps']);
    verifier_contient(
        'droits : la page d’envoi prévient que JavaScript est nécessaire pour les gros fichiers',
        'JavaScript est désactivé',
        $pageEnvoi['corps']
    );
    verifier(
        'droits : la page d’envoi n’a aucun style en ligne ni script en ligne',
        !str_contains($pageEnvoi['corps'], ' style=') && !str_contains($pageEnvoi['corps'], '<script>')
    );
}

/**
 * 6. « Créer le document » et « Supprimer » : les deux actions de la liste
 * « Fichiers reçus, en attente de formulaire ».
 *
 * Un fichier reçu ne devient un document qu'une seule fois, seul le compte qui
 * l'a envoyé peut agir (les autres administrateurs le lisent, sans bouton), et
 * la suppression efface le fichier assemblé comme les morceaux.
 */
function tests_envoi_fiches(int $compte, int $autre, int $famille): void
{
    $nouveauJeton = static function (int $pour): string {
        $_SESSION = ['account_id' => $pour];
        return csrf_token();
    };

    /** Messages flash posés par la requête qui vient de tourner. */
    $messages = static function (): string {
        return implode(' | ', array_column((array) ($_SESSION['_flash'] ?? []), 'message'));
    };

    // --- a. « Créer le document » : une fiche privée, sans formulaire ------
    $source = tests_envoi_texte(150_000);
    $pret   = tests_envoi_pret($compte, $source, 'acte_naissance_1912.txt');
    @unlink($source);
    verifier('actions : un fichier assemblé attend son formulaire', $pret !== null);
    if ($pret === null) {
        return;
    }

    $fichier = storage_absolute((string) $pret['rel_path']);

    // La page : deux boutons, une colonne d'actions, une aide honnête.
    $_SESSION = ['account_id' => $compte];
    $page = tests_envoi_requete('GET', '/admin/envoyer', $compte);
    verifier_contient('actions : la liste propose « Créer le document »', 'Créer le document', $page['corps']);
    verifier_contient(
        'actions : la liste propose « Supprimer »',
        'lien-bouton--danger">Supprimer<',
        $page['corps']
    );
    verifier(
        'actions : la suppression demande une confirmation',
        str_contains($page['corps'], 'data-confirmation="Supprimer définitivement')
            && str_contains($page['corps'], 'irréversible'),
        ''
    );
    verifier(
        'actions : le tableau des fichiers reçus a une colonne « Actions »',
        str_contains($page['corps'], '<th scope="col">Actions</th>'),
        ''
    );
    verifier(
        'actions : l’aide ne renvoie plus à une liste qui n’existe pas',
        !str_contains($page['corps'], 'liste ci-dessous')
            && str_contains($page['corps'], 'devenir un document'),
        ''
    );
    verifier(
        'actions : la page n’a ni style ni script en ligne',
        !str_contains($page['corps'], ' style=') && !str_contains($page['corps'], '<script>'),
        ''
    );

    $creation = tests_envoi_requete('POST', '/admin/envoyer/creer-document', $compte, [
        '_csrf' => $nouveauJeton($compte),
        'token' => (string) $pret['token'],
    ]);
    $doc    = db_one('SELECT * FROM documents WHERE rel_path = ?', [(string) $pret['rel_path']]);
    $envoi1 = envoi_par_token((string) $pret['token']);

    verifier(
        'actions : « Créer le document » crée une fiche privée, sans formulaire',
        $doc !== null
            && (string) $doc['visibility'] === 'prive'
            && (int) $doc['created_by'] === $compte
            && (string) $doc['rel_path'] === (string) $pret['rel_path']
            && (string) $doc['sha256'] === (string) $pret['sha256']
            && is_file($fichier),
        $creation['code'] . ' ' . $creation['location'] . ' ' . $creation['erreur']
    );
    verifier(
        'actions : le titre de la fiche vient du nom du fichier',
        $doc !== null && (string) $doc['title'] === 'acte_naissance_1912',
        (string) ($doc['title'] ?? '—')
    );
    verifier(
        'actions : après création, on arrive sur la fiche à compléter',
        $creation['code'] === 303 && $doc !== null
            && $creation['location'] === '/admin/documents/' . (int) $doc['id'] . '/modifier',
        $creation['code'] . ' ' . $creation['location']
    );
    verifier(
        'actions : l’envoi est rattaché à la fiche, une seule fois',
        $envoi1 !== null
            && (string) $envoi1['state'] === 'termine'
            && (int) $envoi1['document_id'] === (int) ($doc['id'] ?? 0),
        (string) ($envoi1['state'] ?? '—')
    );
    verifier(
        'actions : la confirmation annonce la fiche privée',
        str_contains($messages(), 'créé en privé'),
        $messages()
    );

    // Double clic, second onglet : la même fiche ne peut pas naître deux fois.
    $encore = tests_envoi_requete('POST', '/admin/envoyer/creer-document', $compte, [
        '_csrf' => $nouveauJeton($compte),
        'token' => (string) $pret['token'],
    ]);
    $nombre = (int) db_value('SELECT COUNT(*) FROM documents WHERE rel_path = ?', [(string) $pret['rel_path']], -1);
    verifier(
        'actions : un fichier reçu ne devient un document qu’une seule fois',
        $nombre === 1
            && $encore['code'] === 303
            && $encore['location'] === '/admin/envoyer'
            && str_contains($messages(), 'déjà rattaché à une fiche')
            && is_file($fichier),
        $nombre . ' fiche(s) — ' . $encore['code'] . ' ' . $encore['location'] . ' — ' . $messages()
    );

    // La fiche créée reste invisible à la famille tant qu'elle est privée.
    $_SESSION = ['account_id' => $famille];
    $ficheFamille = tests_envoi_requete('GET', '/documents/' . (int) ($doc['id'] ?? 0), $famille);
    verifier(
        'actions : la fiche créée reste privée (404 pour la famille)',
        $doc !== null && $ficheFamille['code'] === 404,
        (string) $ficheFamille['code']
    );

    // --- b. Un envoi qui n'est pas « pret » ne crée rien -------------------
    $source3 = tests_envoi_texte(60_000);
    $enCours = tests_envoi_jouer($compte, 'encours.txt', $source3, [], false, false);
    @unlink($source3);
    $tokenEnCours = (string) ($enCours['envoi']['token'] ?? '');

    $creationEnCours = $tokenEnCours === '' ? ['code' => 0, 'location' => '', 'corps' => '', 'erreur' => '']
        : tests_envoi_requete('POST', '/admin/envoyer/creer-document', $compte, [
            '_csrf' => $nouveauJeton($compte),
            'token' => $tokenEnCours,
        ]);
    $apresEnCours = envoi_par_token($tokenEnCours);
    verifier(
        'actions : un envoi encore en cours ne crée pas de fiche',
        $tokenEnCours !== ''
            && $creationEnCours['code'] === 303
            && $creationEnCours['location'] === '/admin/envoyer'
            && $apresEnCours !== null
            && (string) $apresEnCours['state'] === 'en_cours'
            && (int) db_value('SELECT COUNT(*) FROM documents WHERE rel_path = ?', [(string) $apresEnCours['rel_path']], -1) === 0,
        $creationEnCours['code'] . ' ' . $creationEnCours['location'] . ' — ' . $messages()
    );

    // --- c. Droits : seul le compte qui a envoyé agit ---------------------
    $source2 = tests_envoi_texte(120_000);
    $pret2   = tests_envoi_pret($compte, $source2, 'lettre-du-front.txt');
    @unlink($source2);
    verifier('droits : un deuxième fichier attend son formulaire', $pret2 !== null);
    if ($pret2 === null) {
        return;
    }

    $fichier2 = storage_absolute((string) $pret2['rel_path']);
    $jeton2   = (string) $pret2['token'];

    $_SESSION = ['account_id' => $autre];
    $pageAutre = tests_envoi_requete('GET', '/admin/envoyer', $autre);
    verifier(
        'droits : le fichier d’un autre administrateur n’est pas sur sa page d’envoi',
        $pageAutre['code'] === 200 && !str_contains($pageAutre['corps'], 'lettre-du-front.txt'),
        (string) $pageAutre['code']
    );

    $supervision = tests_envoi_requete('GET', '/admin/envois?tous=1', $autre);
    verifier(
        'droits : la supervision montre le fichier d’un autre, en lecture seule',
        $supervision['code'] === 200
            && str_contains($supervision['corps'], 'lettre-du-front.txt')
            && str_contains($supervision['corps'], 'lecture seule')
            && !str_contains($supervision['corps'], '/admin/envoyer/creer-document')
            && !str_contains($supervision['corps'], '/admin/envoyer/supprimer'),
        (string) $supervision['code']
    );

    $creationAutre = tests_envoi_requete('POST', '/admin/envoyer/creer-document', $autre, [
        '_csrf' => $nouveauJeton($autre),
        'token' => $jeton2,
    ]);
    $apresTentative = envoi_par_token($jeton2);
    verifier(
        'droits : un autre administrateur ne peut pas créer la fiche',
        $creationAutre['code'] === 303
            && $creationAutre['location'] === '/admin/envoyer'
            && $apresTentative !== null
            && (string) $apresTentative['state'] === 'pret'
            && is_file($fichier2)
            && (int) db_value('SELECT COUNT(*) FROM documents WHERE rel_path = ?', [(string) $pret2['rel_path']], -1) === 0,
        $creationAutre['code'] . ' ' . $creationAutre['location'] . ' — ' . $messages()
    );

    $suppressionAutre = tests_envoi_requete('POST', '/admin/envoyer/supprimer', $autre, [
        '_csrf' => $nouveauJeton($autre),
        'token' => $jeton2,
    ]);
    $apresSuppressionAutre = envoi_par_token($jeton2);
    verifier(
        'droits : un autre administrateur ne peut pas supprimer le fichier',
        $suppressionAutre['code'] === 303
            && $apresSuppressionAutre !== null
            && (string) $apresSuppressionAutre['state'] === 'pret'
            && is_file($fichier2),
        (string) $suppressionAutre['code']
    );

    $familleCreation    = tests_envoi_requete('POST', '/admin/envoyer/creer-document', $famille, [
        '_csrf' => $nouveauJeton($famille),
        'token' => $jeton2,
    ]);
    $familleSuppression = tests_envoi_requete('POST', '/admin/envoyer/supprimer', $famille, [
        '_csrf' => $nouveauJeton($famille),
        'token' => $jeton2,
    ]);
    $apresFamille = envoi_par_token($jeton2);
    verifier(
        'droits : un compte de famille ne peut ni créer ni supprimer (403)',
        $familleCreation['code'] === 403
            && $familleSuppression['code'] === 403
            && $apresFamille !== null
            && (string) $apresFamille['state'] === 'pret'
            && is_file($fichier2),
        $familleCreation['code'] . ' / ' . $familleSuppression['code']
    );

    $sansJeton       = tests_envoi_requete('POST', '/admin/envoyer/creer-document', $compte, ['token' => $jeton2]);
    $sansJetonSuppr  = tests_envoi_requete('POST', '/admin/envoyer/supprimer', $compte, ['token' => $jeton2]);
    verifier(
        'droits : sans jeton CSRF, aucune des deux actions ne passe (403)',
        $sansJeton['code'] === 403
            && $sansJetonSuppr['code'] === 403
            && is_file($fichier2)
            && (int) db_value('SELECT COUNT(*) FROM documents WHERE rel_path = ?', [(string) $pret2['rel_path']], -1) === 0,
        $sansJeton['code'] . ' / ' . $sansJetonSuppr['code']
    );

    // --- d. « Supprimer » : le fichier assemblé et les morceaux partent ---
    verifier('actions : avant suppression, le fichier est sur le disque', is_file($fichier2));

    $suppression = tests_envoi_requete('POST', '/admin/envoyer/supprimer', $compte, [
        '_csrf' => $nouveauJeton($compte),
        'token' => $jeton2,
    ]);
    $apres = envoi_par_token($jeton2);
    verifier(
        'actions : « Supprimer » efface le fichier assemblé',
        !is_file($fichier2)
            && $suppression['code'] === 303
            && $suppression['location'] === '/admin/envoyer'
            && str_contains($messages(), 'supprimés'),
        $suppression['code'] . ' ' . $suppression['location'] . ' — ' . $messages()
    );
    verifier(
        'actions : « Supprimer » efface les morceaux et clôt l’envoi',
        !is_dir(envoi_racine() . '/' . $jeton2)
            && $apres !== null
            && (string) $apres['state'] === 'annule',
        (string) ($apres['state'] ?? '—')
    );

    $_SESSION = ['account_id' => $compte];
    $pageApres = tests_envoi_requete('GET', '/admin/envoyer', $compte);
    verifier(
        'actions : le fichier supprimé ne figure plus dans la liste',
        !str_contains($pageApres['corps'], 'lettre-du-front.txt'),
        ''
    );
    verifier(
        'actions : la fiche créée figure dans « Reçus depuis hier »',
        str_contains($pageApres['corps'], 'acte_naissance_1912'),
        ''
    );

    // Un fichier déjà devenu document ne se supprime pas depuis cette liste :
    // c'est la fiche qui possède le fichier.
    $suppressionDoc = tests_envoi_requete('POST', '/admin/envoyer/supprimer', $compte, [
        '_csrf' => $nouveauJeton($compte),
        'token' => (string) $pret['token'],
    ]);
    $envoiDoc = envoi_par_token((string) $pret['token']);
    verifier(
        'actions : un fichier déjà rattaché à une fiche n’est pas supprimé ici',
        $suppressionDoc['code'] === 303
            && $envoiDoc !== null
            && (string) $envoiDoc['state'] === 'termine'
            && is_file($fichier),
        (string) $suppressionDoc['code'] . ' — ' . $messages()
    );

    // --- e. Une vidéo : la fiche s'ouvre dans le formulaire vidéo ---------
    $film      = tests_envoi_film_mp4(120_000);
    $pretVideo = tests_envoi_pret($compte, $film, 'film-du-mariage.mp4');
    @unlink($film);
    verifier('actions : un MP4 assemblé attend son formulaire', $pretVideo !== null);
    if ($pretVideo === null) {
        return;
    }

    $creationVideo = tests_envoi_requete('POST', '/admin/envoyer/creer-document', $compte, [
        '_csrf' => $nouveauJeton($compte),
        'token' => (string) $pretVideo['token'],
    ]);
    $docVideo = db_one('SELECT * FROM documents WHERE rel_path = ?', [(string) $pretVideo['rel_path']]);
    verifier(
        'actions : une vidéo reçue devient une fiche vidéo, ouverte dans son formulaire',
        $docVideo !== null
            && (string) $docVideo['kind'] === 'video'
            && (string) $docVideo['visibility'] === 'prive'
            && $creationVideo['location'] === '/admin/videos/' . (int) $docVideo['id'],
        $creationVideo['code'] . ' ' . $creationVideo['location']
    );
}

/** 5. Ménage : envois abandonnés, journal. */
function tests_envoi_menage(int $compte): void
{
    // Un envoi abandonné depuis 3 jours (les morceaux sont encore là).
    $fichier = tests_envoi_texte(150_000);
    $resultat = tests_envoi_jouer($compte, 'abandonne.txt', $fichier, [0], true, false);
    @unlink($fichier);

    $envoi = $resultat['envoi'];
    $jeton = (string) $envoi['token'];
    verifier(
        'ménage : un envoi interrompu garde ses morceaux pour la reprise',
        is_dir(envoi_racine() . '/' . $jeton) && count(envoi_morceaux($envoi)['recus']) === 1
    );

    db_exec('UPDATE uploads SET updated_at = ? WHERE id = ?', [time() - 3 * 86400, (int) $envoi['id']]);

    $purge = envoi_purge(48);
    verifier(
        'ménage : les envois abandonnés depuis plus de 48 h sont supprimés',
        $purge['supprimes'] >= 1 && envoi_par_id((int) $envoi['id']) === null
            && !is_dir(envoi_racine() . '/' . $jeton),
        json_encode($purge)
    );

    $recent = envoi_creer($compte, 'recent.txt', 1000);
    envoi_purge(48);
    verifier(
        'ménage : un envoi récent n’est jamais supprimé',
        envoi_par_id((int) $recent['envoi']['id']) !== null
            && is_dir(envoi_racine() . '/' . (string) $recent['envoi']['token'])
    );
    envoi_annuler(envoi_par_id((int) $recent['envoi']['id']));

    // Journal : nom nettoyé et taille, jamais le contenu.
    $fichier = tests_envoi_texte(120_000);
    $resultat = tests_envoi_jouer($compte, 'journal.txt', $fichier);
    @unlink($fichier);

    $_SESSION = ['account_id' => $compte];
    journaliser_envoi_termine($resultat['envoi']);

    $ligne = db_one(
        "SELECT * FROM activity_log WHERE account_id = ? AND action = 'envoi_termine' ORDER BY id DESC LIMIT 1",
        [$compte]
    );
    verifier(
        'ménage : l’envoi terminé est journalisé avec le nom et la taille',
        $ligne !== null && str_contains((string) $ligne['details'], 'journal.txt')
            && str_contains((string) $ligne['details'], 'Ko'),
        (string) ($ligne['details'] ?? 'aucune ligne')
    );
    verifier(
        'ménage : le journal ne contient jamais le contenu du fichier',
        $ligne !== null && !str_contains((string) $ligne['details'], 'Ligne d’essai'),
        (string) ($ligne['details'] ?? '')
    );

    // Mise en page : le champ de fichier masqué (class="sr-only") ne doit pas
    // faire la largeur de la fenêtre. Le sélecteur « input[type="file"] » étant
    // plus précis que « .sr-only », la règle « width: 100% » l'emportait : la
    // page /admin/envoyer glissait horizontalement (611 px de large pour 390
    // sur un téléphone).
    $css = (string) @file_get_contents(HDF_ROOT . '/public/assets/style.css');
    verifier(
        'mise en page : le champ de fichier masqué ne déborde pas de la page (pas de barre horizontale)',
        preg_match('/\.sr-only,\s*\ninput\[type="file"\]\.sr-only\s*\{[^}]*width:\s*1px;/', $css) === 1
            && preg_match('/\.sr-only,\s*\ninput\[type="file"\]\.sr-only\s*\{[^}]*left:\s*0;/', $css) === 1,
        'règle .sr-only de style.css'
    );
    verifier(
        'mise en page : le composant d’envoi masque bien son champ de fichier',
        str_contains(
            (string) @file_get_contents(HDF_APP . '/views/admin/partials/envoi.php'),
            'class="sr-only"'
        )
    );

    // Un chemin du Pi est un mot unique de quarante caractères : sans césure,
    // « /var/www/histoiredefamille/config.local.php » poussait la page
    // « Courriels » à 443 px de large sur un téléphone de 390.
    verifier(
        'mise en page : un long chemin dans <code> ne déborde pas de la page',
        preg_match('/\ncode \{[^}]*overflow-wrap:\s*anywhere;/', $css) === 1,
        'règle code de style.css'
    );
    verifier(
        'mise en page : les chemins du Pi sont écrits dans <code> (donc coupés au besoin)',
        str_contains(
            (string) @file_get_contents(HDF_APP . '/views/admin/courriels.php'),
            '<code>/var/www/histoiredefamille/config.local.php</code>'
        )
    );
}

/** Fichier ISO synthétique : marque de conteneur + piste vidéo ou son (1.35.1). */
function tests_envoi_fichier_iso(string $marque, bool $avecVideo): string
{
    $boite = static fn (string $type, string $contenu): string => pack('N', 8 + strlen($contenu)) . $type . $contenu;
    $data = $boite('ftyp', $marque . pack('N', 0) . $marque . 'isom');
    $handler = $avecVideo ? 'vide' : 'soun';
    $nom = $avecVideo ? "VideoHandler\0" : "SoundHandler\0";
    $hdlr = $boite('hdlr', "\0\0\0\0\0\0\0\0" . $handler . pack('NNN', 0, 0, 0) . $nom);
    $minf = $boite('minf', $boite($avecVideo ? 'vmhd' : 'smhd', pack('Nn', 0, 0)));
    $data .= $boite('moov', $boite('trak', $boite('mdia', $hdlr . $minf)));
    $data .= $boite('mdat', str_repeat("\x00\x01\x02\x03", 512));

    $chemin = storage_path('tmp') . '/iso-' . bin2hex(random_bytes(6)) . '.mp4';
    file_put_contents($chemin, $data);
    return $chemin;
}

/** 1.35.1 : familles de conteneurs, sonde ffprobe, messages simples. */
function tests_envoi_familles_media(int $compte): void
{
    // Le cas du rapport : vraie vidéo, marque M4A, extension .mp4.
    $videoM4a = tests_envoi_fichier_iso('M4A ', true);
    $resultat = tests_envoi_jouer($compte, 'Bienvenue sur la lune.MP4', $videoM4a);
    verifier(
        'media : une vidéo à marque M4A et extension .mp4 est acceptée (cas du rapport)',
        ($resultat['terminer']['ok'] ?? false) === true,
        (string) ($resultat['terminer']['error'] ?? 'aucune erreur')
    );
    verifier_contient(
        'media : le type retenu pour la marque M4A est bien celui du conteneur audio/mp4',
        'audio/',
        (string) ($resultat['envoi']['mime'] ?? '')
    );
    @unlink($videoM4a);

    // Chaque marque de la famille MP4/QuickTime passe la signature.
    foreach (['isom', 'mp42', 'qt  ', '3gp4', 'avc1'] as $marque) {
        $fichier = tests_envoi_fichier_iso($marque, true);
        $controle = envoi_verifier_contenu($fichier, 'mp4');
        verifier(
            'media : la marque ' . trim($marque) . ' du conteneur ISO est acceptée pour .mp4',
            $controle['ok'],
            $controle['error']
        );
        @unlink($fichier);
    }

    // Les refus restent des refus, avec une phrase simple (pas de type technique).
    $faux = [
        'un exécutable' => "MZ" . str_repeat("\x90", 2000),
        'un script'     => "#!/bin/sh\necho bonjour\n" . str_repeat('x', 500),
        'un PDF'        => "%PDF-1.4\n" . str_repeat('0', 1000),
        'une archive'   => "PK\x03\x04" . str_repeat("\x00", 1000),
    ];
    foreach ($faux as $quoi => $contenu) {
        $fichier = storage_path('tmp') . '/faux-' . bin2hex(random_bytes(6)) . '.mp4';
        file_put_contents($fichier, $contenu);
        $controle = envoi_verifier_contenu($fichier, 'mp4');
        verifier(
            'media : ' . $quoi . ' renommé .mp4 est refusé, message simple sans type technique',
            !$controle['ok']
                && str_contains($controle['error'], 'n’est pas celui d’une vidéo')
                && str_contains($controle['error'], 'convertissez-le en MP4')
                && !str_contains($controle['error'], 'type détecté'),
            $controle['error']
        );
        @unlink($fichier);
    }

    // Son pur sous extension vidéo : ffprobe tranche quand il est là…
    $son = tests_envoi_fichier_iso('M4A ', false);
    try {
        conversion_sonde_forcer(static fn (): array => [
            'video' => false, 'audio' => true, 'codec_video' => null, 'codec_audio' => 'aac',
        ]);
        $controle = envoi_verifier_contenu($son, 'mp4');
        verifier(
            'media : un son pur en .mp4 est refusé par la sonde, avec la phrase du rapport',
            !$controle['ok']
                && str_contains($controle['error'], '.MP4 mais le site n’y a trouvé que du son')
                && str_contains($controle['error'], 'convertissez-la en MP4 (H.264) puis recommencez'),
            $controle['error']
        );

        // …la même sonde voit la piste vidéo du vrai film à marque M4A…
        conversion_sonde_forcer(static fn (): array => [
            'video' => true, 'audio' => true, 'codec_video' => 'h264', 'codec_audio' => 'aac',
        ]);
        $video = tests_envoi_fichier_iso('M4A ', true);
        $controle = envoi_verifier_contenu($video, 'mp4');
        verifier('media : avec une piste vidéo, la marque M4A est acceptée malgré le type audio', $controle['ok'], $controle['error']);
        @unlink($video);

        // …et sans ffprobe, la signature du conteneur suffit (la conversion décidera).
        conversion_sonde_forcer(static fn () => null);
        $controle = envoi_verifier_contenu($son, 'mp4');
        verifier(
            'media : sans ffprobe, un son en .mp4 de bonne famille est accepté (la conversion décidera)',
            $controle['ok'],
            $controle['error']
        );
    } finally {
        conversion_sonde_forcer(null);
    }
    @unlink($son);

    // La lecture de la sortie ffprobe est une fonction pure, testable partout.
    $sonde = conversion_interpreter_sonde("video,h264\naudio,aac\n");
    verifier(
        'media : la sonde lit les pistes video/h264 et audio/aac',
        $sonde !== null && $sonde['video'] && $sonde['audio']
            && $sonde['codec_video'] === 'h264' && $sonde['codec_audio'] === 'aac'
    );
    verifier('media : une sonde vide ne dit rien (null, pas d’invention)', conversion_interpreter_sonde('') === null);

    // Les nouvelles extensions vidéo et audio sont autorisées (liste blanche).
    foreach (['3g2', 'mpg', 'mpeg', 'mts', 'm2ts', 'wmv', 'flv', 'vob', 'aac', 'flac'] as $ext) {
        verifier(
            'media : l’extension .' . $ext . ' est dans la liste autorisée',
            in_array($ext, allowed_extensions(), true)
        );
    }

    // Messages simples pour les autres refus de l’envoi par morceaux.
    $vide = envoi_creer($compte, 'vide.mp4', 0);
    verifier(
        'media : un fichier vide est refusé avec une action possible',
        !$vide['ok'] && str_contains($vide['error'], 'rien à envoyer')
            && str_contains($vide['error'], 'recommencez'),
        $vide['error']
    );
    $mauvaise = envoi_creer($compte, 'archive.zip', 1000);
    verifier(
        'media : une extension refusée dit quoi envoyer à la place',
        !$mauvaise['ok'] && str_contains($mauvaise['error'], 'Extension non autorisée')
            && str_contains($mauvaise['error'], 'Convertissez votre fichier'),
        $mauvaise['error']
    );
}
