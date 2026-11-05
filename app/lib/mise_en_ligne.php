<?php
/**
 * Vérification avant ouverture du site au public.
 *
 * Chaque contrôle rend un des quatre verdicts :
 *   - « ok »       : c'est bon ;
 *   - « corriger » : il faut agir AVANT d'ouvrir le site ;
 *   - « avertir »  : à lire avant d'ouvrir, mais qui ne bloque jamais
 *                    l'ouverture (données de démonstration, HTTPS, courriels,
 *                    minuteurs, âge de la dernière sauvegarde) ;
 *   - « info »     : à savoir, sans bloquer.
 *
 * Chaque verdict est accompagné d'une explication en français simple et d'un
 * remède (la commande ou le geste à faire).
 *
 * Deux usages, mêmes contrôles :
 *   1. la commande `php app/cli/verifier-mise-en-ligne.php` fait tout, y
 *      compris une vraie requête HTTP locale à Apache (en-têtes, cookies) ;
 *   2. la page « État du site » relit le dernier résultat enregistré (lecture
 *      seule, instantanée) et refait les contrôles sans risque (fichiers,
 *      comptes, sauvegarde) — jamais de commande système.
 */

declare(strict_types=1);

/** Réglages de la vérification (chemins, seuils). */
function mise_en_ligne_options(array $override = []): array
{
    return array_replace([
        'storage_root'  => rtrim((string) config('storage.root'), '/'),
        'database'      => (string) config('database'),
        'public_dir'    => HDF_ROOT . '/public',
        'config_file'   => (string) config('_config_file'),
        'admin_min'     => 2,
        'espace_min_mb' => 512,
        // Une sauvegarde de plus de 48 h est signalée : sur une carte SD, deux
        // jours de travail perdu, c'est déjà beaucoup.
        'sauvegarde_max_jours' => 2,
        'restauration_max_jours' => 180,
        'https_only'    => (bool) config('https_only'),
        'adresse'       => mise_en_ligne_adresse(),
        'sauvegarde'    => [],
        'reponse_http'  => null,
        'http'          => true,
        'systeme'       => true,
        // Adresse testée localement : Apache écoute sur la boucle locale.
        'http_url'      => 'http://127.0.0.1/',
        'http_timeout'  => 3,
    ], $override);
}

/** Fichier où la commande enregistre son dernier résultat. */
function mise_en_ligne_fichier(?string $fichier = null): string
{
    if ($fichier !== null && $fichier !== '') {
        return $fichier;
    }
    return rtrim((string) config('storage.root'), '/') . '/mise-en-ligne.json';
}

/** Ajoute un contrôle au tableau. */
function mise_en_ligne_ajouter(array &$controles, string $cle, string $titre, string $statut, string $detail, string $remede = ''): void
{
    $controles[] = [
        'cle'    => $cle,
        'titre'  => $titre,
        'statut' => $statut,
        'detail' => $detail,
        'remede' => $remede,
    ];
}

/** Adresse publique du site (réglage, sinon configuration). */
function mise_en_ligne_adresse(): string
{
    $adresse = trim((string) site_setting('adresse'));
    if ($adresse === '') {
        $adresse = trim((string) config('site.url', ''));
    }
    return $adresse;
}

/**
 * Envoie une vraie requête HTTP à un serveur local et renvoie l'état, les
 * en-têtes et le corps (limité).
 *
 * @return array{ok: bool, erreur: string, statut: int, entetes: array<string, string>, corps: string}
 */
function mise_en_ligne_requete_http(string $url, int $timeout = 3, array $entetesEnvoyes = [], string $nomHote = ''): array
{
    $parties = parse_url($url);
    if (!is_array($parties) || empty($parties['host'])) {
        return ['ok' => false, 'erreur' => 'Adresse locale invalide.', 'statut' => 0, 'entetes' => [], 'corps' => ''];
    }

    $hote  = (string) $parties['host'];
    $port  = (int) ($parties['port'] ?? 80);
    $chemin = ($parties['path'] ?? '/') . (isset($parties['query']) ? '?' . $parties['query'] : '');

    $socket = @fsockopen($hote, $port, $numero, $message, $timeout);
    if ($socket === false) {
        return [
            'ok'     => false,
            'erreur' => 'Apache ne répond pas sur ' . $hote . ':' . $port . ' (' . $message . ').',
            'statut' => 0,
            'entetes' => [],
            'corps'  => '',
        ];
    }

    stream_set_timeout($socket, $timeout);
    // On demande la page au serveur local, mais avec le nom du site : Apache
    // choisit ainsi le bon VirtualHost (celui du tunnel), sans passer par
    // Internet ni par Cloudflare.
    $nomHote = $nomHote !== '' ? $nomHote : $hote;
    $requete = 'GET ' . $chemin . " HTTP/1.0\r\n"
        . 'Host: ' . $nomHote . "\r\n"
        . "User-Agent: histoiredefamille-verification\r\n"
        . "Accept: text/html\r\n"
        . "Connection: close\r\n";
    foreach ($entetesEnvoyes as $nom => $valeur) {
        $requete .= $nom . ': ' . $valeur . "\r\n";
    }
    $requete .= "\r\n";
    fwrite($socket, $requete);

    $brut = '';
    while (!feof($socket)) {
        $morceau = fread($socket, 8192);
        if ($morceau === false) {
            break;
        }
        $brut .= $morceau;
        if (strlen($brut) > 262144) {
            break;
        }
    }
    fclose($socket);

    if ($brut === '') {
        return [
            'ok'     => false,
            'erreur' => 'Apache n’a rien répondu sur ' . $hote . ':' . $port . '.',
            'statut' => 0,
            'entetes' => [],
            'corps'  => '',
        ];
    }

    $reponse          = mise_en_ligne_analyser_reponse($brut);
    $reponse['ok']    = true;
    $reponse['erreur'] = '';

    return $reponse;
}

/**
 * Décode une réponse HTTP brute : code, en-têtes (noms en minuscules, les
 * doublons sont conservés avec « | ») et corps.
 *
 * @return array{ok: bool, erreur: string, statut: int, entetes: array<string, string>, corps: string}
 */
function mise_en_ligne_analyser_reponse(string $brut): array
{
    if ($brut === '') {
        return ['ok' => false, 'erreur' => 'Réponse vide.', 'statut' => 0, 'entetes' => [], 'corps' => ''];
    }

    [$enteteBrut, $corps] = array_pad(explode("\r\n\r\n", $brut, 2), 2, '');
    $lignes = explode("\r\n", $enteteBrut);
    $statut = 0;
    if (preg_match('#^HTTP/\d\.\d\s+(\d{3})#', (string) ($lignes[0] ?? ''), $m) === 1) {
        $statut = (int) $m[1];
    }

    $entetes = [];
    foreach (array_slice($lignes, 1) as $ligne) {
        if (!str_contains($ligne, ':')) {
            continue;
        }
        [$nom, $valeur] = explode(':', $ligne, 2);
        $nom = strtolower(trim($nom));
        $valeur = trim($valeur);
        $entetes[$nom] = isset($entetes[$nom]) ? $entetes[$nom] . ' | ' . $valeur : $valeur;
    }

    return ['ok' => true, 'erreur' => '', 'statut' => $statut, 'entetes' => $entetes, 'corps' => $corps];
}

/**
 * Tous les contrôles.
 *
 * @return array<int, array{cle: string, titre: string, statut: string, detail: string, remede: string}>
 */
function mise_en_ligne_controles(array $options = []): array
{
    $o        = mise_en_ligne_options($options);
    $controles = [];

    // 1. HTTPS obligatoire -----------------------------------------------------
    if ($o['https_only']) {
        mise_en_ligne_ajouter($controles, 'https_only', 'HTTPS obligatoire', 'ok',
            'Le site force les cookies sécurisés et l’en-tête HSTS.');
    } else {
        mise_en_ligne_ajouter($controles, 'https_only', 'HTTPS obligatoire', 'avertir',
            'Le réglage « https_only » est à false : le site accepterait une connexion non chiffrée.',
            'Dans /var/www/histoiredefamille/config.local.php, mettez ' . "'https_only' => true" . ', puis rechargez Apache.');
    }

    // 2. Adresse publique en https --------------------------------------------
    $adresse = (string) $o['adresse'];
    if ($adresse === '') {
        mise_en_ligne_ajouter($controles, 'adresse', 'Adresse publique', 'corriger',
            'Aucune adresse publique n’est renseignée : les messages d’accès envoyés à la famille seraient incomplets.',
            'Administration → Réglages → « Adresse du site », ou « site.url » dans config.local.php.');
    } elseif (str_starts_with(strtolower($adresse), 'https://')) {
        mise_en_ligne_ajouter($controles, 'adresse', 'Adresse publique', 'ok', $adresse);
    } else {
        mise_en_ligne_ajouter($controles, 'adresse', 'Adresse publique', 'corriger',
            'L’adresse publique n’est pas en https : ' . $adresse,
            'Utilisez l’adresse du tunnel Cloudflare, commençant par https://.');
    }

    // 3. Données de démonstration --------------------------------------------
    $demo = mise_en_ligne_demo();
    if ($demo === '') {
        mise_en_ligne_ajouter($controles, 'demo', 'Aucune donnée de démonstration', 'ok',
            'Aucun contenu ni compte marqué « (démo) ».');
    } else {
        mise_en_ligne_ajouter($controles, 'demo', 'Aucune donnée de démonstration', 'avertir',
            'Des données fictives sont encore présentes : ' . $demo . '.',
            'Lancez la commande de purge : sudo -u www-data php /var/www/histoiredefamille/app/cli/purger-demo.php '
                . '(elle montre ce qu’elle supprimerait, demande de taper OUI, et fait une sauvegarde avant). '
                . 'Les comptes ne sont jamais supprimés par cette commande : désactivez-les depuis Administration → Comptes.');
    }

    // 4. Au moins deux administrateurs actifs --------------------------------
    $admins = (int) db_value("SELECT COUNT(*) FROM accounts WHERE role = 'admin' AND is_active = 1", [], 0);
    if ($admins >= (int) $o['admin_min']) {
        mise_en_ligne_ajouter($controles, 'admins', 'Administrateurs actifs', 'ok',
            $admins . ' compte(s) administrateur actif(s).');
    } else {
        mise_en_ligne_ajouter($controles, 'admins', 'Administrateurs actifs', 'avertir',
            'Il n’y a que ' . $admins . ' administrateur actif : en cas d’oubli de mot de passe ou d’absence, personne ne peut gérer le site.',
            'Créez au moins deux comptes : sudo -u www-data php /var/www/histoiredefamille/app/cli/create-admin.php');
    }

    // 5. Mots de passe d'essai connus ----------------------------------------
    $faibles = mise_en_ligne_mots_de_passe_faibles();
    if ($faibles === []) {
        mise_en_ligne_ajouter($controles, 'mots_de_passe', 'Mots de passe d’essai', 'ok',
            'Aucun compte ne correspond aux mots de passe d’essai connus.');
    } else {
        mise_en_ligne_ajouter($controles, 'mots_de_passe', 'Mots de passe d’essai', 'corriger',
            'Compte(s) utilisant un mot de passe d’essai connu : ' . implode(', ', $faibles) . '.',
            'Administration → Comptes / Branches / Administrateurs → « Réinitialiser le mot de passe », puis transmettez le nouveau mot de passe.');
    }

    // 6. Permissions du fichier de configuration ------------------------------
    $fichier = (string) $o['config_file'];
    if (!is_file($fichier)) {
        mise_en_ligne_ajouter($controles, 'config_droits', 'Fichier de configuration', 'corriger',
            'Aucun fichier config.local.php : le site tourne avec des chemins par défaut.',
            'sudo cp /var/www/histoiredefamille/config.example.php /var/www/histoiredefamille/config.local.php puis adaptez les chemins, et sudo chmod 640.');
    } else {
        $permissions = substr(sprintf('%o', fileperms($fichier)), -4);
        if (in_array($permissions, ['0600', '0640', '0400', '0440'], true)) {
            mise_en_ligne_ajouter($controles, 'config_droits', 'Fichier de configuration', 'ok',
                $fichier . ' — droits ' . $permissions . '.');
        } else {
            mise_en_ligne_ajouter($controles, 'config_droits', 'Fichier de configuration', 'corriger',
                'Les droits du fichier de configuration sont trop larges : ' . $permissions . ' (attendu 600 ou 640).',
                'sudo chown root:www-data /var/www/histoiredefamille/config.local.php && sudo chmod 640 /var/www/histoiredefamille/config.local.php');
        }
    }

    // 7. Base et données hors de public/ -------------------------------------
    $publicReel = realpath((string) $o['public_dir']);
    $horsPublic = true;
    foreach ([$o['database'], $o['storage_root']] as $chemin) {
        $reel = realpath((string) $chemin);
        if ($publicReel !== false && $reel !== false
            && ($reel === $publicReel || str_starts_with($reel . '/', $publicReel . '/'))) {
            $horsPublic = false;
        }
    }
    if ($horsPublic) {
        mise_en_ligne_ajouter($controles, 'hors_public', 'Données hors du dossier web', 'ok',
            'La base et les fichiers sont en dehors de ' . $o['public_dir'] . '.');
    } else {
        mise_en_ligne_ajouter($controles, 'hors_public', 'Données hors du dossier web', 'corriger',
            'La base de données ou les fichiers se trouvent dans public/ : ils seraient téléchargeables.',
            'Déplacez-les sur le SSD (voir deploy/montage-disques.md) et corrigez « database » et « storage.root ».');
    }

    // 8. Aucun fichier sensible dans public/ ---------------------------------
    $sensibles = mise_en_ligne_fichiers_sensibles((string) $o['public_dir']);
    if ($sensibles === []) {
        mise_en_ligne_ajouter($controles, 'public_propre', 'Dossier public sans fichier sensible', 'ok',
            'Aucune configuration, base, sauvegarde ni dossier .git dans public/.');
    } else {
        mise_en_ligne_ajouter($controles, 'public_propre', 'Dossier public sans fichier sensible', 'corriger',
            'Fichier(s) à retirer de public/ : ' . implode(', ', $sensibles) . '.',
            'Déplacez-les hors de public/ (ils n’ont rien à y faire) et vérifiez le .htaccess.');
    }

    // 9. PHP et extensions ---------------------------------------------------
    $manquantes = mise_en_ligne_extensions_manquantes();
    if (version_compare(PHP_VERSION, '8.1', '>=') && $manquantes === []) {
        mise_en_ligne_ajouter($controles, 'php', 'PHP et extensions', 'ok',
            'PHP ' . PHP_VERSION . ' — extensions présentes.');
    } else {
        mise_en_ligne_ajouter($controles, 'php', 'PHP et extensions', 'corriger',
            'PHP ' . PHP_VERSION . ' ; manquant : ' . implode(', ', $manquantes) . '.',
            'sudo apt install php-sqlite3 php-gd php-mbstring php-curl php-xml puis sudo systemctl reload apache2');
    }

    // 10 à 13. Sauvegarde ----------------------------------------------------
    $sauvegarde = sauvegarde_options((array) $o['sauvegarde']);
    $support = sauvegarde_support_ok($sauvegarde);
    if ($support['ok'] && ($support['avertissement'] ?? '') !== '') {
        // Mode « sans disque externe » demandé à la main dans config.local.php :
        // c'est un avertissement, jamais un blocage. Le site peut ouvrir.
        mise_en_ligne_ajouter($controles, 'sauvegarde_support', 'Clé de sauvegarde montée', 'avertir',
            (string) $support['avertissement'],
            'Branchez le disque dès qu’il arrive, retirez « backup_allow_same_disk » de config.local.php, puis relancez cette vérification.');
    } else {
        mise_en_ligne_ajouter($controles, 'sauvegarde_support', 'Clé de sauvegarde montée', $support['ok'] ? 'ok' : 'corriger',
            $support['ok'] ? 'La clé est montée (' . $support['montage'] . ').' : $support['raison'],
            $support['ok'] ? '' : 'Branchez la clé USB, vérifiez « df -h /mnt/sauvegarde », puis relancez la vérification.');
    }

    $etat = sauvegarde_etat_lire((string) $sauvegarde['state']);
    if ($etat === null) {
        mise_en_ligne_ajouter($controles, 'sauvegarde_recente', 'Dernière sauvegarde', 'corriger',
            'Aucune sauvegarde enregistrée.',
            'sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php');
    } else {
        $jours = (int) floor((time() - (int) ($etat['date'] ?? 0)) / 86400);
        if (($etat['status'] ?? '') === 'ok' && $jours <= (int) $o['sauvegarde_max_jours']) {
            mise_en_ligne_ajouter($controles, 'sauvegarde_recente', 'Dernière sauvegarde', 'ok',
                'Réussie ' . (string) ($etat['date_humain'] ?? '') . ' (il y a ' . $jours . ' jour(s)).');
        } else {
            mise_en_ligne_ajouter($controles, 'sauvegarde_recente', 'Dernière sauvegarde', 'avertir',
                ($etat['status'] ?? '') !== 'ok'
                    ? 'La dernière sauvegarde a échoué : ' . (string) ($etat['message'] ?? '')
                    : 'Aucune sauvegarde réussie depuis ' . $jours . ' jour(s) : la dernière date de '
                        . (string) ($etat['date_humain'] ?? '?') . ' (attendu : moins de 48 heures).',
                'Lancez la commande de sauvegarde, lisez le message, puis vérifiez « Administration → État du site ».');
        }
    }

    if ($o['systeme']) {
        $timer = '/etc/systemd/system/timers.target.wants/histoiredefamille-sauvegarde.timer';
        if (is_link($timer) || is_file($timer)) {
            mise_en_ligne_ajouter($controles, 'sauvegarde_timer', 'Sauvegarde automatique planifiée', 'ok',
                'Le minuteur systemd est activé (tous les jours à 3 h 30).');
        } else {
            mise_en_ligne_ajouter($controles, 'sauvegarde_timer', 'Sauvegarde automatique planifiée', 'avertir',
                'Le minuteur de sauvegarde n’est pas activé : aucune sauvegarde ne sera faite la nuit.',
                'sudo systemctl enable --now histoiredefamille-sauvegarde.timer puis sudo systemctl list-timers histoiredefamille-sauvegarde.timer');
        }
    } else {
        mise_en_ligne_ajouter($controles, 'sauvegarde_timer', 'Sauvegarde automatique planifiée', 'info',
            'Vérifiable seulement en ligne de commande (systemctl list-timers).');
    }

    $controle = restauration_etat_lire((string) $sauvegarde['restore_state']);
    if ($controle === null) {
        mise_en_ligne_ajouter($controles, 'restauration', 'Test de restauration', 'corriger',
            'Aucun test de restauration n’a encore été fait : une sauvegarde non testée n’est pas une sauvegarde.',
            'sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php /mnt/sauvegarde/histoiredefamille --verifier');
    } else {
        $jours = (int) floor((time() - (int) ($controle['date'] ?? 0)) / 86400);
        if (($controle['status'] ?? '') === 'ok' && $jours <= (int) $o['restauration_max_jours']) {
            mise_en_ligne_ajouter($controles, 'restauration', 'Test de restauration', 'ok',
                'Dernier contrôle le ' . (string) ($controle['date_humain'] ?? '') . ' (il y a ' . $jours . ' jour(s)).');
        } else {
            mise_en_ligne_ajouter($controles, 'restauration', 'Test de restauration', 'corriger',
                'Le dernier test de restauration date de ' . $jours . ' jours ou a échoué.',
                'Relancez la commande de contrôle de restauration (option --verifier).');
        }
    }

    // 13 bis. Courriels : configuration et minuteur d'envoi -------------------
    // Ce sont des avertissements, jamais des blocages : un site sans courriel
    // fonctionne (la file d'attente et les écrans suffisent), il prévient
    // simplement moins bien la famille.
    if (courriel_configure()) {
        mise_en_ligne_ajouter($controles, 'courriel', 'Envoi de courriels', 'ok',
            'Un serveur d’envoi est configuré : les notifications peuvent partir.');
    } else {
        mise_en_ligne_ajouter($controles, 'courriel', 'Envoi de courriels', 'avertir',
            'Aucun courriel n’est configuré : aucune notification ne partira (création de compte, '
                . 'article publié, mot de passe oublié).',
            'Renseignez le bloc « mail » de config.local.php (voir le README, « Envoyer des courriels »), '
                . 'puis vérifiez la file : Administration → Courriels.');
    }

    if ($o['systeme']) {
        $timerCourriels = '/etc/systemd/system/timers.target.wants/histoiredefamille-courriels.timer';
        if (is_link($timerCourriels) || is_file($timerCourriels)) {
            mise_en_ligne_ajouter($controles, 'courriel_timer', 'Envoi automatique des courriels', 'ok',
                'Le minuteur d’envoi est activé : la file part toute seule.');
        } else {
            mise_en_ligne_ajouter($controles, 'courriel_timer', 'Envoi automatique des courriels', 'avertir',
                'Le minuteur d’envoi des courriels n’est pas activé : les messages resteront dans la file.',
                'sudo systemctl enable --now histoiredefamille-courriels.timer '
                    . 'puis sudo systemctl list-timers histoiredefamille-courriels.timer');
        }
    } else {
        mise_en_ligne_ajouter($controles, 'courriel_timer', 'Envoi automatique des courriels', 'info',
            'Vérifiable seulement en ligne de commande (systemctl list-timers).');
    }

    // 14. Espace disque ------------------------------------------------------
    $seuil = (int) $o['espace_min_mb'] * 1048576;
    $libreDonnees = sauvegarde_espace_libre((string) $o['storage_root']);
    $libreCle     = sauvegarde_espace_libre((string) $sauvegarde['backup_dir']);
    if ($libreDonnees !== null && $libreDonnees >= $seuil && ($libreCle === null || $libreCle >= $seuil)) {
        mise_en_ligne_ajouter($controles, 'espace', 'Espace disque', 'ok',
            'Données : ' . sauvegarde_taille($libreDonnees) . ' libres'
            . ($libreCle !== null ? ' — clé : ' . sauvegarde_taille($libreCle) . ' libres' : '') . '.');
    } else {
        mise_en_ligne_ajouter($controles, 'espace', 'Espace disque', 'corriger',
            'Espace libre insuffisant : '
            . ($libreDonnees !== null ? 'données ' . sauvegarde_taille($libreDonnees) : 'données inconnues')
            . ($libreCle !== null ? ', clé ' . sauvegarde_taille($libreCle) : ', clé absente') . '.',
            'Libérez de la place (vidéos, anciennes sauvegardes) ou changez de disque avant d’ouvrir le site.');
    }

    // 15. Purge planifiée ----------------------------------------------------
    if ($o['systeme']) {
        $cron = @file_get_contents('/etc/cron.d/histoiredefamille');
        if (is_string($cron) && str_contains($cron, 'maintenance.php')) {
            mise_en_ligne_ajouter($controles, 'purge', 'Purge planifiée', 'ok',
                'La maintenance hebdomadaire supprime les vieilles tentatives et journaux.');
        } else {
            mise_en_ligne_ajouter($controles, 'purge', 'Purge planifiée', 'corriger',
                'Aucune maintenance planifiée : les tentatives de connexion et le journal grossiront sans fin.',
                'sudo bash /var/www/histoiredefamille/deploy/install.sh (ou ajoutez la ligne de deploy/crontab.example dans /etc/cron.d/histoiredefamille).');
        }
    } else {
        mise_en_ligne_ajouter($controles, 'purge', 'Purge planifiée', 'info',
            'Vérifiable seulement en ligne de commande (/etc/cron.d/histoiredefamille).');
    }

    // 16. CF-Connecting-IP : jamais cru hors du tunnel ----------------------
    $cf = mise_en_ligne_verifier_cf();
    mise_en_ligne_ajouter($controles, 'cf_ip', 'Adresse IP derrière le tunnel', $cf['ok'] ? 'ok' : 'corriger',
        $cf['message'],
        $cf['ok'] ? '' : 'Dans app/lib/helpers.php, client_ip() ne doit lire CF-Connecting-IP que si REMOTE_ADDR est 127.0.0.1 ou ::1.');

    // 17 à 19. En-têtes HTTP, cookies, signature du serveur -----------------
    if (!$o['http']) {
        mise_en_ligne_ajouter($controles, 'entetes', 'En-têtes HTTP réels', 'info',
            'Vérifiable seulement en ligne de commande (requête locale à Apache).');
    } else {
        $nomHote = (string) parse_url((string) $o['adresse'], PHP_URL_HOST);
        $reponse = is_array($o['reponse_http'])
            ? (array) $o['reponse_http']
            : mise_en_ligne_requete_http((string) $o['http_url'], (int) $o['http_timeout'], [], $nomHote);
        if (!$reponse['ok']) {
            mise_en_ligne_ajouter($controles, 'entetes', 'En-têtes HTTP réels', 'corriger',
                $reponse['erreur'],
                'Vérifiez qu’Apache tourne : sudo systemctl status apache2. La vérification des en-têtes ne peut se faire que depuis le Raspberry Pi.');
        } else {
            $entetes = $reponse['entetes'];
            $csp = (string) ($entetes['content-security-policy'] ?? '');
            $manquants = [];
            if (!str_contains($csp, "default-src 'self'")) {
                $manquants[] = 'Content-Security-Policy';
            }
            if (strtoupper((string) ($entetes['x-frame-options'] ?? '')) !== 'DENY') {
                $manquants[] = 'X-Frame-Options: DENY';
            }
            if (strtolower((string) ($entetes['x-content-type-options'] ?? '')) !== 'nosniff') {
                $manquants[] = 'X-Content-Type-Options: nosniff';
            }
            if (!isset($entetes['referrer-policy'])) {
                $manquants[] = 'Referrer-Policy';
            }
            if ($o['https_only'] && !isset($entetes['strict-transport-security'])) {
                $manquants[] = 'Strict-Transport-Security (HSTS)';
            }
            if ($manquants === []) {
                mise_en_ligne_ajouter($controles, 'entetes', 'En-têtes HTTP réels', 'ok',
                    'CSP, X-Frame-Options, nosniff, Referrer-Policy'
                    . ($o['https_only'] ? ' et HSTS' : '') . ' sont bien envoyés (HTTP ' . $reponse['statut'] . ').');
            } else {
                mise_en_ligne_ajouter($controles, 'entetes', 'En-têtes HTTP réels', 'corriger',
                    'En-tête(s) manquant(s) : ' . implode(', ', $manquants) . '.',
                    'Vérifiez deploy/apache/histoiredefamille.conf (mod_headers activé) puis sudo systemctl reload apache2.');
            }

            // Cookies de session.
            $cookie = (string) ($entetes['set-cookie'] ?? '');
            $problemes = [];
            if ($cookie === '') {
                $problemes[] = 'aucun cookie de session reçu';
            } else {
                $cookieMin = strtolower($cookie);
                if (!str_contains($cookieMin, 'httponly')) {
                    $problemes[] = 'HttpOnly absent';
                }
                if (!str_contains($cookieMin, 'samesite=lax')) {
                    $problemes[] = 'SameSite=Lax absent';
                }
                if ($o['https_only'] && !str_contains($cookieMin, 'secure')) {
                    $problemes[] = 'Secure absent (obligatoire en https)';
                }
            }
            mise_en_ligne_ajouter($controles, 'cookies', 'Cookies de session', $problemes === [] ? 'ok' : 'corriger',
                $problemes === [] ? 'HttpOnly, SameSite=Lax' . ($o['https_only'] ? ' et Secure' : '') . ' présents.'
                    : 'Problème(s) : ' . implode(', ', $problemes) . '.',
                $problemes === [] ? '' : 'Vérifiez app/lib/security.php (session_start_secure) et rechargez Apache.');

            // Signature du serveur.
            $signature = [];
            if (isset($entetes['x-powered-by'])) {
                $signature[] = 'X-Powered-By: ' . $entetes['x-powered-by'];
            }
            // « Apache » tout court n'apprend rien ; « Apache/2.4.62 » ou
            // « PHP/8.3.33 » indiquent en revanche quelles failles chercher.
            if (isset($entetes['server'])
                && preg_match('/\d+\.\d+/', (string) $entetes['server']) === 1) {
                $signature[] = 'Server: ' . $entetes['server'];
            }
            mise_en_ligne_ajouter($controles, 'signature', 'Signature du serveur masquée', $signature === [] ? 'ok' : 'corriger',
                $signature === [] ? 'Aucune version d’Apache ni de PHP annoncée.' : 'Le serveur annonce : ' . implode(', ', $signature) . '.',
                $signature === [] ? '' : 'Dans deploy/apache/histoiredefamille.conf, gardez « Header unset X-Powered-By », « Header unset Server » et « ServerTokens Prod », puis rechargez Apache.');
        }
    }

    return $controles;
}

/**
 * Détecte les restes du jeu de démonstration.
 *
 * Deux sources, dans cet ordre : le registre écrit par demo.php (exact), puis
 * le marqueur « (démo) » pour les bases remplies par une version antérieure.
 * Les comptes sont comptés à part : ils ne sont jamais supprimés
 * automatiquement, seulement désactivés à la main.
 */
function mise_en_ligne_demo(): string
{
    $present = demo_present();

    return $present['message'];
}

/**
 * Comptes dont le mot de passe fait partie des mots de passe d'essai connus.
 *
 * @return string[] identifiants concernés (jamais les mots de passe)
 */
function mise_en_ligne_mots_de_passe_faibles(): array
{
    $connus = ['motdepasse', 'motdepasse123', 'password', 'azerty', '123456', 'histoiredefamille'];

    $comptes = db_all(
        "SELECT username, password_hash FROM accounts
         WHERE role = 'admin' OR username IN ('MARTIN', 'DURAND', 'LEROY', 'testadmin')
            OR display_name LIKE '%(démo)%'
         LIMIT 40"
    );

    $trouves = [];
    foreach ($comptes as $compte) {
        foreach ($connus as $motDePasse) {
            if (password_verify($motDePasse, (string) $compte['password_hash'])) {
                $trouves[] = (string) $compte['username'];
                break;
            }
        }
    }

    return $trouves;
}

/**
 * Fichiers qui n'ont rien à faire dans public/.
 *
 * @return string[] chemins relatifs (5 au maximum)
 */
function mise_en_ligne_fichiers_sensibles(string $publicDir): array
{
    if (!is_dir($publicDir)) {
        return [];
    }

    $interdits = [];
    $elements = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($elements as $element) {
        $relatif = ltrim(substr($element->getPathname(), strlen(rtrim($publicDir, '/'))), '/');
        $nom     = strtolower($element->getFilename());

        // Le .htaccess est normal (il porte les règles de réécriture).
        if ($relatif === '.htaccess') {
            continue;
        }
        if (str_starts_with($relatif, '.git/') || $nom === '.git' || $nom === '.env' || str_starts_with($nom, '.env.')) {
            $interdits[] = $relatif;
            continue;
        }
        if (preg_match('/\.(sqlite|sqlite3|db|sql|bak|dump|log|sh|gz|zip|tar|ini)$/', $nom) === 1
            || preg_match('/^config.*\.php$/', $nom) === 1) {
            $interdits[] = $relatif;
        }
        if (count($interdits) >= 5) {
            break;
        }
    }

    return $interdits;
}

/** Extensions PHP indispensables au site. */
function mise_en_ligne_extensions_manquantes(): array
{
    $manquantes = [];
    if (!extension_loaded('pdo_sqlite')) {
        $manquantes[] = 'pdo_sqlite';
    }
    if (!extension_loaded('gd') && !extension_loaded('imagick')) {
        $manquantes[] = 'gd (ou imagick)';
    }
    if (!extension_loaded('mbstring')) {
        $manquantes[] = 'mbstring';
    }
    if (!extension_loaded('fileinfo')) {
        $manquantes[] = 'fileinfo';
    }
    if (!extension_loaded('sodium') && !defined('PASSWORD_ARGON2ID')) {
        $manquantes[] = 'sodium ou Argon2id';
    }
    return $manquantes;
}

/**
 * Vérifie que CF-Connecting-IP n'est pris en compte que depuis la boucle
 * locale (donc depuis cloudflared), et jamais depuis le réseau local.
 *
 * @return array{ok: bool, message: string}
 */
function mise_en_ligne_verifier_cf(): array
{
    $remoteAvant = $_SERVER['REMOTE_ADDR'] ?? null;
    $cfAvant     = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;

    try {
        // 1. Depuis le réseau local : l'en-tête doit être ignoré.
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';
        $local = client_ip();

        // 2. Depuis cloudflared (boucle locale) : l'en-tête est utilisé.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $tunnel = client_ip();
    } finally {
        if ($remoteAvant === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $remoteAvant;
        }
        if ($cfAvant === null) {
            unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        } else {
            $_SERVER['HTTP_CF_CONNECTING_IP'] = $cfAvant;
        }
    }

    if ($local !== '192.0.2.10') {
        return ['ok' => false, 'message' => 'L’en-tête CF-Connecting-IP est accepté depuis le réseau local : l’adresse IP réelle peut être falsifiée.'];
    }
    if ($tunnel !== '198.51.100.7') {
        return ['ok' => false, 'message' => 'L’en-tête CF-Connecting-IP n’est pas utilisé depuis le tunnel : les visiteurs seraient tous vus comme 127.0.0.1.'];
    }

    return ['ok' => true, 'message' => 'CF-Connecting-IP n’est accepté que depuis la boucle locale (cloudflared) ; il est ignoré depuis le réseau local.'];
}

/**
 * Verdict global.
 *
 * @return array{pret: bool, a_corriger: int, infos: int, texte: string}
 */
function mise_en_ligne_verdict(array $controles): array
{
    $aCorriger = array_values(array_filter($controles, static fn (array $c): bool => $c['statut'] === 'corriger'));
    $infos     = count(array_filter($controles, static fn (array $c): bool => $c['statut'] === 'info'));

    $avertissements = count(array_filter($controles, static fn (array $c): bool => $c['statut'] === 'avertir'));

    return [
        'pret'       => $aCorriger === [],
        'a_corriger' => count($aCorriger),
        'avertissements' => $avertissements,
        'infos'      => $infos,
        'texte'      => $aCorriger === []
            ? 'PRÊT À OUVRIR'
            : 'PAS PRÊT : ' . count($aCorriger) . ' point(s) à corriger',
    ];
}

/** Enregistre le résultat pour la page « État du site ». */
function mise_en_ligne_enregistrer(array $controles, ?string $fichier = null): bool
{
    $verdict = mise_en_ligne_verdict($controles);
    $etat = [
        'date'        => time(),
        'date_humain' => date('d/m/Y à H:i'),
        'verdict'     => $verdict['texte'],
        'pret'        => $verdict['pret'],
        'a_corriger'  => $verdict['a_corriger'],
        'avertissements' => $verdict['avertissements'],
        'controles'   => $controles,
    ];

    $fichier = mise_en_ligne_fichier($fichier);
    $json = json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $dossier = dirname($fichier);
    if (!is_dir($dossier) && !@mkdir($dossier, 0770, true) && !is_dir($dossier)) {
        return false;
    }

    $tmp = $fichier . '.partiel';
    if (@file_put_contents($tmp, $json . "\n") === false) {
        return false;
    }
    @chmod($tmp, 0640);

    return @rename($tmp, $fichier);
}

/** Dernier résultat enregistré, ou null. */
function mise_en_ligne_dernier(?string $fichier = null): ?array
{
    $fichier = mise_en_ligne_fichier($fichier);
    if (!is_readable($fichier)) {
        return null;
    }
    $json = json_decode((string) @file_get_contents($fichier), true);
    return is_array($json) ? $json : null;
}
