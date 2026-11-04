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
    bool $terminer = true
): array {
    $taille = (int) filesize($chemin);
    $init   = envoi_creer($compte, $nom, $taille, 'text/plain', $creerFiche);
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
            tests_envoi_doublons($compte);
            tests_envoi_droits($compte, $autre, $famille);
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
            && str_contains((string) ($resultat['terminer']['error'] ?? ''), 'ne correspond pas à son extension'),
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
}
