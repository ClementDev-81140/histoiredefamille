<?php
/**
 * Courriels du site : petits messages envoyés à la famille.
 *
 * Trois règles tiennent tout ce fichier :
 *
 *  1. AUCUN envoi sans configuration ET sans accord. Un compte sans adresse,
 *     ou sans la case cochée, ne reçoit jamais rien. Une adresse saisie par un
 *     administrateur ne vaut pas accord.
 *  2. AUCUN envoi pendant une requête web. Tout passe par une file (table
 *     email_queue) vidée par la commande app/cli/envoyer-courriels.php.
 *  3. Les paramètres du serveur d'envoi (identifiant, mot de passe) ne vivent
 *     que dans config.local.php : jamais en base, jamais dans les journaux,
 *     jamais affichés.
 *
 * Le client SMTP est écrit à la main (sockets) : aucune bibliothèque, aucun
 * Composer. Il gère STARTTLS, TLS implicite, AUTH LOGIN / PLAIN, et des délais
 * d'attente courts (le Raspberry Pi ne doit pas rester bloqué).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Configuration (config.local.php uniquement)
// ---------------------------------------------------------------------------

/** Réglages du serveur d'envoi, tels que lus dans config.local.php. */
function courriel_config(array $surcharge = []): array
{
    $mail = array_merge((array) config('mail'), $surcharge);

    return [
        'host'       => trim((string) ($mail['host'] ?? '')),
        'port'       => (int) ($mail['port'] ?? 587),
        'encryption' => strtolower(trim((string) ($mail['encryption'] ?? 'tls'))),
        'username'   => (string) ($mail['username'] ?? ''),
        'password'   => (string) ($mail['password'] ?? ''),
        'from'       => courriel_adresse_normaliser((string) ($mail['from_address'] ?? '')),
        'from_name'  => trim((string) ($mail['from_name'] ?? '')),
        'timeout'    => max(3, min(30, (int) ($mail['timeout'] ?? 10))),
    ];
}

/** Le serveur d'envoi est-il configuré ? (Aucune information secrète rendue.) */
function courriel_configure(array $surcharge = []): bool
{
    $c = courriel_config($surcharge);
    return $c['host'] !== ''
        && $c['port'] > 0 && $c['port'] <= 65535
        && $c['from'] !== ''
        && courriel_adresse_valide($c['from']);
}

/** Explication en français simple quand la configuration manque. */
function courriel_raison_non_configuree(array $surcharge = []): string
{
    $c = courriel_config($surcharge);
    if ($c['host'] === '') {
        return 'Aucun serveur d’envoi n’est indiqué : les courriels ne partent pas. '
            . 'Renseignez le bloc « mail » de config.local.php (voir le README, « Envoyer des courriels »).';
    }
    if ($c['from'] === '' || !courriel_adresse_valide($c['from'])) {
        return 'L’adresse d’expéditeur est absente ou incorrecte dans config.local.php (clé « from_address »).';
    }
    return 'La configuration du serveur d’envoi est incomplète dans config.local.php.';
}

// ---------------------------------------------------------------------------
// Adresses
// ---------------------------------------------------------------------------

/** Longueur maximale d'une adresse (norme pratique). */
const COURRIEL_ADRESSE_MAX = 190;

/** L'adresse est-elle utilisable telle quelle ? */
function courriel_adresse_valide(?string $adresse): bool
{
    $adresse = trim((string) $adresse);

    if ($adresse === '' || strlen($adresse) > COURRIEL_ADRESSE_MAX) {
        return false;
    }
    // Pas d'espace, pas de retour à la ligne, pas de virgule : on refuse tout
    // ce qui permettrait d'ajouter un second destinataire.
    if (preg_match('/[\s,;<>"()\[\]\\\\]/', $adresse) === 1) {
        return false;
    }
    if (substr_count($adresse, '@') !== 1) {
        return false;
    }

    return filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Adresse rangée en minuscules : « Marie.Dupont@Exemple.FR » et
 * « marie.dupont@exemple.fr » sont la même adresse (pas de majuscule
 * significative).
 */
function courriel_adresse_normaliser(?string $adresse): string
{
    return mb_strtolower(trim((string) $adresse));
}

/** L'adresse est-elle déjà utilisée par un autre compte actif ? */
function courriel_adresse_libre(string $adresse, int $exclureId = 0): bool
{
    $adresse = courriel_adresse_normaliser($adresse);
    if ($adresse === '') {
        return true;
    }

    $trouve = db_value(
        'SELECT id FROM accounts WHERE email = ? AND is_active = 1 AND id <> ? LIMIT 1',
        [$adresse, $exclureId],
        null
    );

    return $trouve === null;
}

/**
 * Adresse masquée pour les journaux et les messages : « m***@exemple.fr ».
 * On ne recopie jamais une adresse complète dans le journal d'activité.
 */
function courriel_masquer(?string $adresse): string
{
    $adresse = trim((string) $adresse);
    if ($adresse === '' || !str_contains($adresse, '@')) {
        return '';
    }
    [$avant, $domaine] = explode('@', $adresse, 2);
    $premier = mb_substr($avant, 0, 1);

    return $premier . '***@' . $domaine;
}

// ---------------------------------------------------------------------------
// Jetons de désinscription
// ---------------------------------------------------------------------------

/**
 * Secret local du site (64 caractères hexadécimaux), créé au premier usage.
 *
 * Il sert à fabriquer les jetons de désinscription : le jeton est calculé, son
 * empreinte est enregistrée en base, et personne ne peut le deviner sans ce
 * fichier. Il vit dans les données du site (donc dans la sauvegarde), jamais
 * dans le dépôt Git.
 */
function courriel_secret(): string
{
    $fichier = rtrim((string) config('storage.root'), '/') . '/courriel-secret';

    if (is_readable($fichier)) {
        $secret = trim((string) @file_get_contents($fichier));
        if (preg_match('/^[0-9a-f]{64}$/', $secret) === 1) {
            return $secret;
        }
    }

    $secret = bin2hex(random_bytes(32));
    $dossier = dirname($fichier);
    if (!is_dir($dossier)) {
        @mkdir($dossier, 0770, true);
    }
    if (@file_put_contents($fichier, $secret . "\n") !== false) {
        @chmod($fichier, 0600);
    }

    return $secret;
}

/** Types d'envoi pouvant être désactivés en un clic. */
function courriel_buts(): array
{
    return [
        'articles' => 'nouveaux articles',
        'gazette'  => 'gazette du mois',
    ];
}

/**
 * Jeton de désinscription d'une personne pour un type d'envoi.
 *
 * Le jeton est long (64 caractères), propre à la personne et au type, et sans
 * date d'expiration : un lien vieux de trois ans marche encore. Seule son
 * empreinte est enregistrée en base.
 */
function courriel_jeton(int $accountId, string $but): string
{
    if (!array_key_exists($but, courriel_buts())) {
        return '';
    }

    $jeton = hash_hmac('sha256', 'desinscription|' . $but . '|' . $accountId, courriel_secret());
    $hash  = hash('sha256', $jeton);

    $existe = db_value('SELECT id FROM email_tokens WHERE token_hash = ?', [$hash], null);
    if ($existe === null) {
        try {
            db_insert('email_tokens', [
                'account_id' => $accountId,
                'purpose'    => $but,
                'token_hash' => $hash,
                'created_at' => time(),
            ]);
        } catch (Throwable) {
            // Deux requêtes simultanées : l'index unique fait son travail.
        }
    }

    return $jeton;
}

/** Compte correspondant à un jeton, ou null (jeton inconnu, modifié, révoqué). */
function courriel_jeton_compte(string $jeton, string $but): ?int
{
    if (!array_key_exists($but, courriel_buts()) || preg_match('/^[0-9a-f]{64}$/', $jeton) !== 1) {
        return null;
    }

    $ligne = db_one(
        'SELECT account_id FROM email_tokens WHERE token_hash = ? AND purpose = ?',
        [hash('sha256', $jeton), $but]
    );

    return $ligne !== null ? (int) $ligne['account_id'] : null;
}

/** Adresse complète du site (pour les liens des courriels). */
function courriel_adresse_site(): string
{
    $adresse = trim(site_adresse_publique());
    return $adresse !== '' ? rtrim($adresse, '/') : 'https://histoiredefamille.eu';
}

/** Lien de désinscription, prêt à être mis dans un courriel. */
function courriel_lien_desinscription(int $accountId, string $but): string
{
    return courriel_adresse_site() . '/desinscription?type=' . rawurlencode($but)
        . '&jeton=' . courriel_jeton($accountId, $but);
}

/** Lien « gérer mes préférences » (page « Mon compte »). */
function courriel_lien_preferences(): string
{
    return courriel_adresse_site() . '/mon-compte';
}

// ---------------------------------------------------------------------------
// Réglages modifiables dans l'administration (jamais de secret ici)
// ---------------------------------------------------------------------------

/** Nombre maximum de courriels envoyés par jour (réglable). */
function courriel_plafond_jour(): int
{
    $valeur = (int) site_setting('courriel_plafond');
    return $valeur >= 1 && $valeur <= 500 ? $valeur : 50;
}

function courriel_regler_plafond(int $valeur): void
{
    save_site_setting('courriel_plafond', (string) max(1, min(500, $valeur)));
}

/** Adresse de réponse (facultative) : « Répondre à ». */
function courriel_adresse_reponse(): string
{
    $valeur = courriel_adresse_normaliser(site_setting('courriel_reponse'));
    return courriel_adresse_valide($valeur) ? $valeur : '';
}

// ---------------------------------------------------------------------------
// File d'envoi
// ---------------------------------------------------------------------------

/**
 * Met un message dans la file. Ne l'envoie pas : c'est la commande
 * app/cli/envoyer-courriels.php qui s'en occupe, par petits lots.
 *
 * @param array{account_id?:int|null, kind?:string, recipient:string, subject:string,
 *              text:string, html?:string, dedupe?:string} $message
 * @return int identifiant du message (0 si doublon ou si déjà annulé)
 */
function courriel_enfiler(array $message): int
{
    $destinataire = courriel_adresse_normaliser((string) ($message['recipient'] ?? ''));
    if (!courriel_adresse_valide($destinataire)) {
        return 0;
    }

    $dedupe = (string) ($message['dedupe'] ?? '');
    if ($dedupe !== '') {
        $existe = db_value('SELECT id FROM email_queue WHERE dedupe_key = ?', [$dedupe], null);
        if ($existe !== null) {
            return 0;
        }
    }

    try {
        return db_insert('email_queue', [
            'account_id' => isset($message['account_id']) ? (int) $message['account_id'] : null,
            'kind'       => (string) ($message['kind'] ?? 'article'),
            'recipient'  => $destinataire,
            'subject'    => mb_substr((string) ($message['subject'] ?? ''), 0, 200),
            'body_text'  => (string) ($message['text'] ?? ''),
            'body_html'  => (string) ($message['html'] ?? ''),
            'status'     => 'attente',
            'attempts'   => 0,
            'dedupe_key' => $dedupe,
            'created_at' => time(),
        ]);
    } catch (Throwable) {
        return 0;
    }
}

/** Compteurs affichés dans l'administration. */
function courriel_compteurs(): array
{
    $debutJour = strtotime('today');

    return [
        'attente'  => (int) db_value("SELECT COUNT(*) FROM email_queue WHERE status = 'attente'", [], 0),
        'envoyes'  => (int) db_value(
            "SELECT COUNT(*) FROM email_queue WHERE status = 'envoye' AND sent_at >= ?",
            [$debutJour],
            0
        ),
        'echecs'   => (int) db_value("SELECT COUNT(*) FROM email_queue WHERE status = 'echec'", [], 0),
        'abonnes'  => (int) db_value(
            "SELECT COUNT(*) FROM accounts WHERE is_active = 1 AND email <> ''
                AND (notify_articles = 1 OR notify_gazette = 1)",
            [],
            0
        ),
    ];
}

/** Les derniers envois (50 par défaut), pour la page « Courriels ». */
function courriel_derniers(int $limite = 50): array
{
    return db_all(
        'SELECT id, account_id, kind, recipient, subject, status, attempts, last_error, created_at, sent_at
         FROM email_queue ORDER BY id DESC LIMIT ' . max(1, min(200, $limite))
    );
}

/** Nombre d'envois réussis depuis le début de la journée. */
function courriel_envoyes_aujourdhui(): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM email_queue WHERE status = 'envoye' AND sent_at >= ?",
        [strtotime('today')],
        0
    );
}

/**
 * Messages à envoyer maintenant : au plus $lot, sans dépasser le plafond
 * quotidien ni les trois essais.
 *
 * @return array{messages: list<array>, plafond: int, deja: int, restant: int}
 */
function courriel_a_envoyer(int $lot = 10, int $essais = 3): array
{
    $plafond = courriel_plafond_jour();
    $deja    = courriel_envoyes_aujourdhui();
    $restant = max(0, $plafond - $deja);

    if ($restant === 0) {
        return ['messages' => [], 'plafond' => $plafond, 'deja' => $deja, 'restant' => 0];
    }

    $messages = db_all(
        "SELECT * FROM email_queue
         WHERE status = 'attente' AND attempts < ?
         ORDER BY id
         LIMIT " . max(0, min($lot, $restant)),
        [$essais]
    );

    return ['messages' => $messages, 'plafond' => $plafond, 'deja' => $deja, 'restant' => $restant];
}

/**
 * Le cœur de la commande d'envoi : prépare, envoie, réessaie, purge.
 *
 * Aucun affichage et aucune sortie ici : la commande
 * (app/cli/envoyer-courriels.php) se contente de rapporter ce que cette
 * fonction a fait, et les tests peuvent l'appeler avec une conversation SMTP
 * scriptée — jamais de réseau.
 *
 * @param array{lot?: int, pause?: int, essais?: int, purger?: int, echus?: bool} $options
 * @param callable(string): void|null $trace reçoit chaque ligne déjà rédigée
 * @param object|null $connexion conversation SMTP prête (tests uniquement)
 * @param array $surcharge réglages d'envoi imposés (tests uniquement)
 * @return array{enfiles: int, envoyes: int, echecs: int, restant: int, purges: int,
 *               plafond: int, aujourdhui: int, configure: bool, raison: string,
 *               arret: string, lignes: list<string>}
 */
function courriel_traiter_file(array $options = [], ?callable $trace = null, $connexion = null, array $surcharge = []): array
{
    $lot    = max(1, (int) ($options['lot'] ?? 10));
    $pause  = max(0, (int) ($options['pause'] ?? 1));
    $essais = max(1, (int) ($options['essais'] ?? 3));
    $purger = (int) ($options['purger'] ?? 90);

    $res = [
        'enfiles' => 0, 'envoyes' => 0, 'echecs' => 0, 'restant' => 0,
        'purges' => 0, 'plafond' => courriel_plafond_jour(),
        'aujourdhui' => courriel_envoyes_aujourdhui(),
        'configure' => courriel_configure($surcharge),
        'raison' => '', 'arret' => '', 'lignes' => [],
    ];
    $dire = static function (string $ligne) use ($trace, &$res): void {
        $res['lignes'][] = $ligne;
        if ($trace !== null) {
            $trace($ligne);
        }
    };

    // 1. Articles programmés dont l'heure est arrivée. C'est ici, et pas à la
    //    création de l'article, qu'ils sont mis en file.
    if (($options['echus'] ?? true) === true) {
        try {
            $res['enfiles'] = courriel_articles_echus(static function (string $ligne) use ($dire): void {
                $dire('  · ' . $ligne);
            });
        } catch (Throwable $e) {
            $dire('  ! La préparation des notifications a échoué : ' . $e->getMessage());
        }
    }
    $dire('Nouvelles notifications mises en file : ' . $res['enfiles']);

    // 1 bis. Gazette du mois : préparation le 1er, envoi automatique éventuel
    //        au-delà du délai de grâce (réglage désactivé par défaut).
    if (($options['gazette'] ?? true) === true) {
        try {
            $prep = gazette_preparer();
            if ($prep['prepares'] > 0) {
                $dire('Gazette du mois : ' . $prep['prepares'] . ' numéro(s) préparé(s) pour '
                    . implode(', ', $prep['groupes']) . ' — à valider dans l’administration.');
            }
            $auto = gazette_valider_auto(null, static function (string $ligne) use ($dire): void {
                $dire('  · ' . $ligne);
            });
            if ($auto > 0) {
                $dire('Gazette du mois : ' . $auto . ' numéro(s) envoyé(s) automatiquement.');
            }
        } catch (Throwable $e) {
            $dire('  ! La préparation de la gazette a échoué : ' . $e->getMessage());
        }
    }

    // 2. Sans serveur d'envoi, rien ne part — et la file est conservée.
    if (!$res['configure']) {
        $res['raison'] = courriel_raison_non_configuree($surcharge);
        $res['arret']  = 'non_configure';
        $dire('');
        $dire($res['raison']);
        $dire('Les messages restent dans la file (« Administration → Courriels »).');
        $res['purges'] = courriel_purger($purger);
        if ($res['purges'] > 0) {
            $dire('Purge : ' . $res['purges'] . ' message(s) terminé(s) de plus de '
                . $purger . ' jours retiré(s).');
        }
        return $res;
    }

    // 3. Plafond quotidien, puis lot de messages.
    $selection = courriel_a_envoyer($lot, $essais);
    $res['plafond']    = $selection['plafond'];
    $res['aujourdhui'] = $selection['deja'];
    $res['restant']    = $selection['restant'];
    $messages          = $selection['messages'];

    if ($selection['restant'] === 0) {
        $res['arret'] = 'plafond';
        $dire('');
        $dire('Plafond quotidien atteint (' . $selection['plafond'] . ' courriels aujourd’hui) : '
            . 'rien n’est envoyé maintenant.');
        $dire('Le minuteur reprendra demain ; la file est conservée.');
        return $res;
    }

    $dire('');
    $dire($messages === []
        ? 'Aucun courriel en attente.'
        : 'Envoi de ' . count($messages) . ' courriel(s) au maximum (plafond : '
            . $selection['plafond'] . '/jour) :');

    // 4. Envoi, un message à la fois.
    foreach ($messages as $message) {
        $genre = (string) $message['kind'] === 'gazette' ? 'gazette' : 'articles';
        // La conversation peut être fabriquée par une fonction (tests) : une
        // vraie connexion ne sert qu'à un seul message.
        $lien = $connexion instanceof Closure ? $connexion() : $connexion;
        $resultat = courriel_envoyer([
            'recipient'      => (string) $message['recipient'],
            'subject'        => (string) $message['subject'],
            'text'           => (string) $message['body_text'],
            'html'           => (string) $message['body_html'],
            'desinscription' => courriel_lien_desinscription((int) ($message['account_id'] ?? 0), $genre),
        ], $lien, $surcharge);

        if ($resultat['ok']) {
            db_update('email_queue', (int) $message['id'], [
                'status'     => 'envoye',
                'sent_at'    => time(),
                'attempts'   => (int) $message['attempts'] + 1,
                'last_error' => '',
            ]);
            $res['envoyes']++;
            $dire('  ok      ' . courriel_masquer((string) $message['recipient'])
                . ' — ' . mb_substr((string) $message['subject'], 0, 60));
            log_activity(null, 'courriel_envoye', 'Envoi « ' . $genre . ' » à '
                . courriel_masquer((string) $message['recipient']));
        } else {
            $tentatives = (int) $message['attempts'] + 1;
            $definitif  = !empty($resultat['definitif']) || $tentatives >= $essais;
            db_update('email_queue', (int) $message['id'], [
                'status'     => $definitif ? 'echec' : 'attente',
                'attempts'   => $tentatives,
                'last_error' => mb_substr((string) $resultat['message'], 0, 300),
            ]);
            $res['echecs']++;
            $dire('  ÉCHEC   ' . courriel_masquer((string) $message['recipient']) . ' — '
                . $resultat['message'] . ($definitif ? ' (abandon)' : ' (nouvel essai plus tard)'));
        }

        // Pause entre deux envois : le Pi respire, le serveur d'envoi aussi.
        if ($pause > 0) {
            sleep($pause);
        }
    }

    $res['purges'] = courriel_purger($purger);
    if ($res['purges'] > 0) {
        $dire('Purge : ' . $res['purges'] . ' message(s) terminé(s) de plus de '
            . $purger . ' jours retiré(s).');
    }

    return $res;
}

/** Annule les envois encore en attente d'un article (repassé en brouillon). */
function courriel_annuler_article(int $articleId): int
{
    if ($articleId <= 0) {
        return 0;
    }

    $sql = "UPDATE email_queue SET status = 'annule', last_error = 'Article repassé en brouillon'
            WHERE status = 'attente' AND kind = 'article' AND dedupe_key LIKE ?";
    $requete = db_query($sql, ['article:' . $articleId . ':%']);

    return $requete->rowCount();
}

/**
 * Purge les messages terminés (envoyés, en échec définitif, annulés) de plus
 * de 90 jours : la file ne grossit pas indéfiniment sur la carte SD.
 */
function courriel_purger(int $jours = 90): int
{
    $limite = time() - max(7, $jours) * 86400;
    $requete = db_query(
        // CAST indispensable : sans type, COALESCE comparerait le texte « … »
        // à un nombre et supprimerait des messages tout récents.
        "DELETE FROM email_queue
         WHERE status IN ('envoye', 'echec', 'annule')
           AND CAST(COALESCE(sent_at, created_at) AS INTEGER) < CAST(? AS INTEGER)",
        [$limite]
    );

    return $requete->rowCount();
}

// ---------------------------------------------------------------------------
// Client SMTP (sockets, sans bibliothèque)
// ---------------------------------------------------------------------------

/**
 * Connexion scriptée (utilisée par les tests) : on lui donne les réponses que
 * le serveur aurait faites, elle enregistre ce que le client a écrit.
 */
final class CourrielSmtpScript
{
    /** @param string[] $reponses */
    public function __construct(private array $reponses)
    {
    }

    /** @var string[] */
    public array $ecrit = [];

    public bool $ferme = false;
    public bool $tls   = false;
    private int $index = 0;

    public function lire(): string
    {
        $reponse = $this->reponses[$this->index] ?? '500 Trop de commandes (test)';
        $this->index++;
        return $reponse;
    }

    public function ecrire(string $ligne): void
    {
        $this->ecrit[] = $ligne;
    }

    public function activer_tls(): bool
    {
        $this->tls = true;
        return true;
    }

    public function fermer(): void
    {
        $this->ferme = true;
    }
}

/** Connexion réelle (flux réseau). */
final class CourrielSmtpFlux
{
    /** @param resource $flux */
    public function __construct(private $flux)
    {
    }

    public function lire(): string
    {
        $lignes = [];

        while (($ligne = fgets($this->flux, 2048)) !== false) {
            $lignes[] = rtrim($ligne, "\r\n");
            // Une réponse multi-lignes continue tant que le 4e caractère est '-'.
            if (strlen($ligne) < 4 || $ligne[3] !== '-') {
                break;
            }
        }

        return implode("\n", $lignes);
    }

    public function ecrire(string $ligne): void
    {
        fwrite($this->flux, $ligne . "\r\n");
    }

    public function activer_tls(): bool
    {
        return @stream_socket_enable_crypto($this->flux, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === true;
    }

    public function fermer(): void
    {
        @fclose($this->flux);
    }
}

/** Ouvre une connexion vers le serveur d'envoi. */
function courriel_smtp_ouvrir(array $c): array
{
    $prefixe = $c['encryption'] === 'ssl' ? 'ssl://' : 'tcp://';
    $erreur  = 0;
    $message = '';

    $flux = @stream_socket_client(
        $prefixe . $c['host'] . ':' . $c['port'],
        $erreur,
        $message,
        $c['timeout'],
        STREAM_CLIENT_CONNECT
    );

    if ($flux === false) {
        return ['ok' => false, 'message' => 'Connexion au serveur d’envoi impossible (code ' . $erreur . ').'];
    }

    stream_set_timeout($flux, $c['timeout']);

    return ['ok' => true, 'message' => '', 'connexion' => new CourrielSmtpFlux($flux)];
}

/**
 * Conversation SMTP complète, de la bannière à QUIT.
 *
 * @param CourrielSmtpFlux|CourrielSmtpScript $connexion
 * @return array{ok: bool, message: string, definitif: bool}
 */
function courriel_smtp_conversation(object $connexion, array $c, string $de, string $vers, string $donnees): array
{
    $attendu = static function (string $reponse, array $codes) : bool {
        $code = (int) substr(trim($reponse), 0, 3);
        return in_array($code, $codes, true);
    };

    $banniere = $connexion->lire();
    if (!$attendu($banniere, [220])) {
        return ['ok' => false, 'message' => 'Le serveur d’envoi n’a pas répondu correctement à la connexion.', 'definitif' => false];
    }

    $nomServeur = 'histoiredefamille.local';
    $connexion->ecrire('EHLO ' . $nomServeur);
    $ehlo = $connexion->lire();

    if (!$attendu($ehlo, [250])) {
        // Serveur ancien : on retente en HELO.
        $connexion->ecrire('HELO ' . $nomServeur);
        if (!$attendu($connexion->lire(), [250])) {
            return ['ok' => false, 'message' => 'Le serveur d’envoi refuse de nous parler (EHLO/HELO).', 'definitif' => false];
        }
    }

    // 1. Chiffrement.
    if ($c['encryption'] === 'tls') {
        if (stripos($ehlo, 'STARTTLS') === false) {
            return ['ok' => false, 'message' => 'Le serveur n’annonce pas STARTTLS : envoi abandonné (le mot de passe ne doit pas passer en clair).', 'definitif' => true];
        }
        $connexion->ecrire('STARTTLS');
        if (!$attendu($connexion->lire(), [220])) {
            return ['ok' => false, 'message' => 'Le serveur a refusé STARTTLS.', 'definitif' => false];
        }
        if (!$connexion->activer_tls()) {
            return ['ok' => false, 'message' => 'Le chiffrement de la connexion (STARTTLS) a échoué.', 'definitif' => false];
        }
        // Après STARTTLS, on se re-présente (exigé par la norme).
        $connexion->ecrire('EHLO ' . $nomServeur);
        $connexion->lire();
    }

    // 2. Authentification (uniquement si un identifiant est fourni).
    if ($c['username'] !== '') {
        $connexion->ecrire('AUTH LOGIN');
        if (!$attendu($connexion->lire(), [334])) {
            return ['ok' => false, 'message' => 'Le serveur refuse l’authentification.', 'definitif' => true];
        }
        $connexion->ecrire(base64_encode($c['username']));
        if (!$attendu($connexion->lire(), [334])) {
            return ['ok' => false, 'message' => 'Le serveur refuse l’identifiant d’envoi.', 'definitif' => true];
        }
        $connexion->ecrire(base64_encode($c['password']));
        $reponse = $connexion->lire();
        if (!$attendu($reponse, [235])) {
            // 535 = identifiants refusés : inutile de réessayer tout de suite.
            $code = (int) substr(trim($reponse), 0, 3);
            return [
                'ok'        => false,
                'message'   => $code === 535
                    ? 'Le serveur d’envoi refuse l’identifiant ou le mot de passe (vérifiez config.local.php).'
                    : 'Authentification refusée par le serveur d’envoi.',
                'definitif' => $code === 535,
            ];
        }
    }

    // 3. Expéditeur, destinataire, message.
    $connexion->ecrire('MAIL FROM:<' . $de . '>');
    if (!$attendu($connexion->lire(), [250])) {
        return ['ok' => false, 'message' => 'Le serveur refuse notre adresse d’expéditeur.', 'definitif' => true];
    }

    $connexion->ecrire('RCPT TO:<' . $vers . '>');
    $reponse = $connexion->lire();
    if (!$attendu($reponse, [250, 251])) {
        $code = (int) substr(trim($reponse), 0, 3);
        return [
            'ok'        => false,
            'message'   => $code === 550 || $code === 551
                ? 'Le serveur indique que cette adresse n’existe pas.'
                : 'Le serveur refuse ce destinataire.',
            'definitif' => $code >= 500 && $code < 600,
        ];
    }

    $connexion->ecrire('DATA');
    if (!$attendu($connexion->lire(), [354])) {
        return ['ok' => false, 'message' => 'Le serveur refuse de recevoir le message.', 'definitif' => false];
    }

    foreach (explode("\n", $donnees) as $ligne) {
        // « Bourrage des points » : une ligne qui commence par un point doit
        // commencer par deux points, sinon elle finirait le message.
        $connexion->ecrire(str_starts_with($ligne, '.') ? '.' . $ligne : $ligne);
    }
    $connexion->ecrire('.');
    $reponse = $connexion->lire();
    if (!$attendu($reponse, [250])) {
        return ['ok' => false, 'message' => 'Le serveur n’a pas accepté le message.', 'definitif' => false];
    }

    $connexion->ecrire('QUIT');
    $connexion->fermer();

    return ['ok' => true, 'message' => '', 'definitif' => false];
}

/** Encode un sujet en « encoded-word » UTF-8 (les accents passent partout). */
function courriel_encoder_sujet(string $sujet): string
{
    $sujet = preg_replace('/[\r\n]+/', ' ', $sujet) ?? '';
    return '=?UTF-8?B?' . base64_encode($sujet) . '?=';
}

/**
 * Assemble le message brut (en-têtes + corps) envoyé en DATA.
 */
function courriel_message_brut(array $c, string $vers, string $sujet, string $texte, string $html, array $options = []): string
{
    $frontiere = 'hdf-' . bin2hex(random_bytes(8));
    $domaine   = substr(strrchr($c['from'], '@') ?: '@localhost', 1);
    $expediteur = $c['from_name'] !== ''
        ? '=?UTF-8?B?' . base64_encode($c['from_name']) . '?= <' . $c['from'] . '>'
        : $c['from'];

    $entetes = [
        'Date: ' . date('r'),
        'From: ' . $expediteur,
        'To: <' . $vers . '>',
        'Subject: ' . courriel_encoder_sujet($sujet),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domaine . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $frontiere . '"',
    ];

    $reponse = courriel_adresse_reponse();
    if ($reponse !== '') {
        $entetes[] = 'Reply-To: <' . $reponse . '>';
    }
    if (($options['desinscription'] ?? '') !== '') {
        $entetes[] = 'List-Unsubscribe: <' . $options['desinscription'] . '>';
        $entetes[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    foreach ((array) ($options['entetes'] ?? []) as $entete) {
        $entete = preg_replace('/[\r\n]+/', ' ', (string) $entete) ?? '';
        if ($entete !== '') {
            $entetes[] = $entete;
        }
    }

    $corps = [];
    $corps[] = '--' . $frontiere;
    $corps[] = 'Content-Type: text/plain; charset=UTF-8';
    $corps[] = 'Content-Transfer-Encoding: base64';
    $corps[] = '';
    $corps[] = chunk_split(base64_encode($texte), 76, "\r\n");
    $corps[] = '--' . $frontiere;
    $corps[] = 'Content-Type: text/html; charset=UTF-8';
    $corps[] = 'Content-Transfer-Encoding: base64';
    $corps[] = '';
    $corps[] = chunk_split(base64_encode($html), 76, "\r\n");
    $corps[] = '--' . $frontiere . '--';

    return implode("\r\n", $entetes) . "\r\n\r\n" . implode("\r\n", $corps);
}

/**
 * Envoie un message tout de suite (utilisé par la commande et le test d'envoi).
 *
 * @param array{recipient:string, subject:string, text:string, html?:string, desinscription?:string} $message
 * @return array{ok: bool, message: string, definitif: bool}
 */
function courriel_envoyer(array $message, $connexion = null, array $surcharge = []): array
{
    if (!courriel_configure($surcharge)) {
        return [
            'ok'         => false,
            'message'    => courriel_raison_non_configuree($surcharge),
            'definitif'  => false,
        ];
    }

    $c    = courriel_config($surcharge);
    $vers = courriel_adresse_normaliser((string) $message['recipient']);
    if (!courriel_adresse_valide($vers)) {
        return ['ok' => false, 'message' => 'Adresse de destinataire invalide.', 'definitif' => true];
    }

    $donnees = courriel_message_brut(
        $c,
        $vers,
        (string) $message['subject'],
        (string) $message['text'],
        (string) ($message['html'] ?? ''),
        ['desinscription' => (string) ($message['desinscription'] ?? '')]
    );

    if ($connexion === null) {
        $ouverture = courriel_smtp_ouvrir($c);
        if (!$ouverture['ok']) {
            return ['ok' => false, 'message' => $ouverture['message'], 'definitif' => false];
        }
        $connexion = $ouverture['connexion'];
    }

    try {
        return courriel_smtp_conversation($connexion, $c, $c['from'], $vers, $donnees);
    } catch (Throwable) {
        return ['ok' => false, 'message' => 'La conversation avec le serveur d’envoi a échoué.', 'definitif' => false];
    }
}

// ---------------------------------------------------------------------------
// Mise en page des courriels (couleurs de la palette, styles en ligne)
// ---------------------------------------------------------------------------

/**
 * Habille un message : en-tête avec le nom du site, corps, pied avec la
 * désinscription et « gérer mes préférences ».
 *
 * Les feuilles de style du site ne s'appliquent pas aux courriels : les styles
 * sont donc écrits en ligne, dans le HTML envoyé (ce n'est pas une page du
 * site : la CSP stricte ne concerne que le navigateur sur le site).
 *
 * @param list<string> $paragraphes paragraphes déjà échappés (texte simple)
 * @return array{text: string, html: string}
 */
function courriel_habiller(string $titre, array $paragraphes, array $options = []): array
{
    $palette = apparence_variables();
    $site    = trim(site_setting('name')) !== '' ? site_setting('name') : 'Histoire de famille';
    $lien    = (string) ($options['lien'] ?? '');
    $libelle = (string) ($options['libelle_lien'] ?? 'Lire la suite');
    $pied    = (string) ($options['pied'] ?? '');

    $texte = $titre . "\n\n";
    foreach ($paragraphes as $paragraphe) {
        $texte .= $paragraphe . "\n\n";
    }
    if ($lien !== '') {
        $texte .= $libelle . ' : ' . $lien . "\n\n";
    }
    if ($pied !== '') {
        $texte .= $pied . "\n";
    }
    $texte .= $site . ' — ' . courriel_adresse_site() . "\n";
    if (($options['desinscription'] ?? '') !== '') {
        $texte .= 'Se désinscrire : ' . $options['desinscription'] . "\n";
    }
    $texte .= 'Gérer mes préférences : ' . courriel_lien_preferences() . "\n";

    $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $corps = '';
    foreach ($paragraphes as $paragraphe) {
        $corps .= '<p style="margin:0 0 14px 0;font-size:16px;line-height:1.6;color:' . $e($palette['texte']) . ';">'
            . nl2br($e($paragraphe)) . '</p>';
    }
    if ($lien !== '') {
        $corps .= '<p style="margin:22px 0;"><a href="' . $e($lien) . '" '
            . 'style="display:inline-block;padding:12px 20px;background:' . $e($palette['accent']) . ';'
            . 'color:' . $e($palette['sur-accent']) . ';text-decoration:none;border-radius:3px;'
            . 'font-size:16px;border:1px solid ' . $e($palette['accent-fonce']) . ';">'
            . $e($libelle) . '</a></p>';
        $corps .= '<p style="margin:0 0 14px 0;font-size:13px;color:' . $e($palette['texte-doux']) . ';">'
            . 'Si le bouton ne marche pas, copiez cette adresse dans votre navigateur :<br>'
            . '<span style="word-break:break-all;">' . $e($lien) . '</span></p>';
    }
    if ($pied !== '') {
        $corps .= '<p style="margin:18px 0 0 0;font-size:13px;color:' . $e($palette['texte-doux']) . ';">'
            . nl2br($e($pied)) . '</p>';
    }

    $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $e($titre) . '</title></head>'
        . '<body style="margin:0;padding:0;background:' . $e($palette['fond']) . ';">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
        . 'style="background:' . $e($palette['fond']) . ';"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
        . 'style="width:100%;max-width:600px;background:' . $e($palette['surface']) . ';'
        . 'border:1px solid ' . $e($palette['bordure']) . ';">'
        . '<tr><td style="padding:18px 22px;border-bottom:3px double ' . $e($palette['bordure']) . ';">'
        . '<div style="font-size:22px;font-weight:bold;color:' . $e($palette['texte']) . ';">' . $e($site) . '</div>'
        . '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:' . $e($palette['accent']) . ';">'
        . $e((string) ($options['sur_titre'] ?? 'Le journal de la famille')) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:22px;">'
        . '<h1 style="margin:0 0 16px 0;font-size:24px;line-height:1.3;color:' . $e($palette['texte']) . ';">'
        . $e($titre) . '</h1>'
        . $corps
        . '</td></tr>'
        . '<tr><td style="padding:16px 22px;border-top:1px solid ' . $e($palette['bordure']) . ';'
        . 'font-size:12px;color:' . $e($palette['texte-doux']) . ';">'
        . $e($site) . ' — <a href="' . $e(courriel_adresse_site()) . '" style="color:' . $e($palette['accent']) . ';">'
        . $e(courriel_adresse_site()) . '</a><br>'
        . '<a href="' . $e(courriel_lien_preferences()) . '" style="color:' . $e($palette['accent']) . ';">'
        . 'Gérer mes préférences</a>'
        . (($options['desinscription'] ?? '') !== ''
            ? ' — <a href="' . $e($options['desinscription']) . '" style="color:' . $e($palette['accent']) . ';">'
                . 'Se désinscrire</a>'
            : '')
        . '</td></tr></table></td></tr></table></body></html>';

    return ['text' => $texte, 'html' => $html];
}

// ---------------------------------------------------------------------------
// Notifications d'articles
// ---------------------------------------------------------------------------

/** La personne peut-elle recevoir un courriel au sujet de cet article ? */
function compte_voit_article(array $compte, array $article): bool
{
    $branches = content_branch_ids('article', (int) $article['id']);
    return visibilite_compte($compte, (string) $article['visibility'], $branches);
}

/**
 * Met en file la notification d'un article publié, pour chaque personne qui
 * l'a demandé ET qui a le droit de le voir. Une seule fois par article et par
 * personne, même si l'on modifie ou republie l'article.
 *
 * @return int nombre de messages mis en file
 */
/**
 * Valeurs à enregistrer pour l'accord « nouveaux articles ».
 *
 * La date d'accord ne bouge pas tant que la case reste cochée ; elle se pose
 * au moment où on la coche (y compris après l'avoir décochée) et retombe à
 * zéro quand on la décoche.
 *
 * @return array{notify_articles: int, notify_articles_depuis: int}
 */
function courriel_accord_articles(?array $compte, bool $articles): array
{
    $avant    = (int) ($compte['notify_articles'] ?? 0) === 1;
    $depuis   = (int) ($compte['notify_articles_depuis'] ?? 0);

    if (!$articles) {
        return ['notify_articles' => 0, 'notify_articles_depuis' => 0];
    }
    if ($avant && $depuis > 0) {
        return ['notify_articles' => 1, 'notify_articles_depuis' => $depuis];
    }

    return ['notify_articles' => 1, 'notify_articles_depuis' => time()];
}

/**
 * Une nouvelle est-elle assez récente pour cette personne ?
 *
 * Règle : on ne prévient que des articles publiés APRÈS le moment où la
 * personne a coché « Prévenez-moi des nouveaux articles »
 * (accounts.notify_articles_depuis). Un nouvel inscrit ne reçoit donc jamais
 * le retard des 30 derniers jours, et décocher puis recocher repart de zéro.
 */
function courriel_article_apres_accord(array $article, array $compte): bool
{
    $depuis = (int) ($compte['notify_articles_depuis'] ?? 0);
    if ($depuis <= 0) {
        return false;
    }

    return (int) $article['published_at'] >= $depuis;
}

function courriel_notifier_article(array $article, ?callable $trace = null): int
{
    if ((int) ($article['notify'] ?? 1) !== 1) {
        return 0;
    }
    if ((string) $article['status'] !== 'publie' || (int) $article['published_at'] > time()) {
        return 0;
    }
    $articleId = (int) $article['id'];
    $comptes = db_all(
        "SELECT * FROM accounts
         WHERE is_active = 1 AND email <> '' AND notify_articles = 1
           AND notify_articles_depuis > 0
         ORDER BY id"
    );

    $envies = 0;
    foreach ($comptes as $compte) {
        if (!compte_voit_article($compte, $article)) {
            continue;
        }
        if (!courriel_article_apres_accord($article, $compte)) {
            continue;
        }

        $dejaVu = db_value(
            'SELECT account_id FROM article_notifications WHERE article_id = ? AND account_id = ?',
            [$articleId, (int) $compte['id']],
            null
        );
        if ($dejaVu !== null) {
            continue;
        }

        $message = courriel_message_article($article, $compte);
        if ($message === null) {
            continue;
        }

        db_exec(
            'INSERT OR IGNORE INTO article_notifications (article_id, account_id, created_at) VALUES (?, ?, ?)',
            [$articleId, (int) $compte['id'], time()]
        );

        $mis = courriel_enfiler([
            'account_id' => (int) $compte['id'],
            'kind'       => 'article',
            'recipient'  => (string) $compte['email'],
            'subject'    => (string) $message['subject'],
            'text'       => (string) $message['text'],
            'html'       => (string) $message['html'],
            'dedupe'     => 'article:' . $articleId . ':' . (int) $compte['id'],
        ]);

        if ($mis > 0) {
            $envies++;
            if ($trace !== null) {
                $trace('article « ' . $article['title'] . ' » → ' . courriel_masquer((string) $compte['email']));
            }
        }
    }

    return $envies;
}

/**
 * Rédige la notification d'un article pour une personne donnée.
 *
 * @return array{subject:string, text:string, html:string}|null
 */
function courriel_message_article(array $article, array $compte): ?array
{
    $visiblePublic = (string) $article['visibility'] === 'public';
    $lien = courriel_adresse_site() . '/articles/' . rawurlencode((string) $article['slug']);
    if (!$visiblePublic) {
        // Contenu réservé : on passe par la connexion, qui ramènera la
        // personne sur l'article (paramètre de retour déjà sécurisé).
        $lien = courriel_adresse_site() . '/connexion?next=' . rawurlencode('/articles/' . (string) $article['slug']);
    }

    $paragraphes = [];
    if (trim((string) $article['summary']) !== '') {
        $paragraphes[] = trim((string) $article['summary']);
    }
    $details = [];
    if (trim((string) $article['category']) !== '') {
        $details[] = 'Rubrique : ' . trim((string) $article['category']);
    }
    $details[] = 'Publié le ' . format_date((int) $article['published_at']);
    $details[] = 'Visibilité : ' . visibility_label((string) $article['visibility']);
    $paragraphes[] = implode(' — ', $details);
    if (!$visiblePublic) {
        $paragraphes[] = 'Cet article est réservé : connectez-vous avec votre compte pour le lire.';
    }

    $desinscription = courriel_lien_desinscription((int) $compte['id'], 'articles');

    $rendu = courriel_habiller('Nouvel article : ' . (string) $article['title'], $paragraphes, [
        'lien'           => $lien,
        'libelle_lien'   => $visiblePublic ? 'Lire l’article' : 'Se connecter pour lire',
        'desinscription' => $desinscription,
        'sur_titre'      => 'Nouvel article',
        'pied'           => 'Vous recevez ce message parce que vous avez coché « Prévenez-moi des nouveaux articles ».',
    ]);

    return [
        'subject' => (string) $article['title'],
        'text'    => $rendu['text'],
        'html'    => $rendu['html'],
    ];
}

/**
 * Publie les articles programmés dont l'heure est arrivée : c'est le moment
 * de prévenir la famille (jamais à la création de l'article).
 *
 * @return int nombre de messages mis en file
 */
function courriel_articles_echus(?callable $trace = null): int
{
    $articles = db_all(
        "SELECT a.* FROM articles a
         WHERE a.status = 'publie' AND a.published_at <= ? AND a.published_at >= ? AND a.notify = 1
           AND EXISTS (
                SELECT 1 FROM accounts ac
                WHERE ac.is_active = 1 AND ac.email <> '' AND ac.notify_articles = 1
                  AND ac.notify_articles_depuis > 0
                  AND a.published_at >= ac.notify_articles_depuis
                  AND NOT EXISTS (SELECT 1 FROM article_notifications n
                                  WHERE n.article_id = a.id AND n.account_id = ac.id)
           )
         ORDER BY a.published_at",
        [time(), time() - 30 * 86400]
    );

    $total = 0;
    foreach ($articles as $article) {
        $total += courriel_notifier_article($article, $trace);
    }

    return $total;
}
