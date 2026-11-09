<?php
/**
 * Comptes : trois profils, et seulement trois.
 *
 *   - « Lecteur » (account_type « branche ») : l'identifiant partagé d'une
 *     branche (MARTIN). Il lit ce que sa branche a le droit de voir. Il ne
 *     peut rien écrire ni proposer, sauf dans le tchat et « Mon compte » ;
 *   - « Journaliste » (account_type « personne ») : un compte par personne
 *     (elise.martin). Il lit avec les droits de sa branche, et il peut
 *     PROPOSER des contenus (articles, documents, photos, recettes), qui
 *     passent par la validation d'un administrateur ;
 *   - « Administrateur » (role « admin ») : tout, et c'est lui qui valide.
 *
 * Les identifiants techniques en base ne changent pas (« branche »,
 * « personne », « admin ») : seuls les libellés montrés aux gens changent.
 * Du plus courant au plus rare : Lecteur, Journaliste, Administrateur.
 *
 * Règles tenues ici :
 *   - l'identifiant est fabriqué à partir du prénom et du nom : minuscules,
 *     sans accent, sans espace, sans apostrophe (« Jean-Paul Le Gall » →
 *     « jean-paul.legall »), puis complété par 2, 3… en cas de doublon ;
 *   - le mot de passe provisoire est généré par le site (password_generate),
 *     lisible et sans caractère ambigu. Seul son HACHAGE part en base : le
 *     mot de passe en clair ne vit que dans la réponse HTTP de l'écran de
 *     résultat, jamais dans la base, ni dans le journal, ni dans un fichier ;
 *   - toutes les valeurs sont vérifiées ici, pour qu'aucun contrôleur ne
 *     puisse en oublier une.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Types de comptes
// ---------------------------------------------------------------------------

/**
 * Types techniques de comptes et leur libellé.
 *
 * Clé = valeur stockée dans accounts.account_type (jamais renommée : aucune
 * migration risquée pour un simple changement de vocabulaire).
 */
function compte_types(): array
{
    return [
        'branche'  => 'Lecteur',
        'personne' => 'Journaliste',
        'admin'    => 'Administrateur',
    ];
}

/** Ce que chaque profil a le droit de faire, en une phrase (écrans d'aide). */
function compte_profil_resume(string $type): string
{
    return match ($type) {
        'personne' => 'lit avec les droits de sa branche et propose des contenus, '
            . 'publiés après validation d’un administrateur — sauf si l’administrateur '
            . 'l’autorise à publier directement (réglage dans sa fiche).',
        'admin'    => 'gère tout le site et valide les propositions.',
        default    => 'lit ce que sa branche a le droit de voir ; ne peut rien écrire ni proposer '
            . '(sauf dans le tchat).',
    };
}

/** Type d'un compte (une base d'avant la migration répond « branche »). */
function compte_type(array $compte): string
{
    $type = (string) ($compte['account_type'] ?? '');
    return array_key_exists($type, compte_types()) ? $type : 'branche';
}

/** Libellé lisible du type (« Lecteur », « Journaliste », « Administrateur »). */
function compte_type_libelle(array $compte): string
{
    $type = compte_type($compte);
    if ($type === 'branche' && ($compte['role'] ?? '') === 'admin') {
        return 'Administrateur';
    }
    return compte_types()[$type];
}

/** Ce compte est-il un simple lecteur (compte de branche, sans écriture) ? */
function compte_est_lecteur(?array $compte): bool
{
    if ($compte === null || ($compte['role'] ?? '') === 'admin') {
        return false;
    }

    return compte_type($compte) === 'branche';
}

/** Ce compte est-il un journaliste (compte personnel, qui peut proposer) ? */
function compte_est_journaliste(?array $compte): bool
{
    if ($compte === null || ($compte['role'] ?? '') === 'admin') {
        return false;
    }

    return compte_type($compte) === 'personne';
}

/**
 * Le compte connecté peut-il proposer des contenus ?
 *
 * Un journaliste oui ; un administrateur aussi (il publie directement, mais
 * les mêmes écrans lui servent). Un lecteur et un visiteur, jamais.
 */
function peut_proposer(?array $compte = null): bool
{
    $compte = $compte ?? current_account();

    return $compte !== null && (($compte['role'] ?? '') === 'admin' || compte_est_journaliste($compte));
}

/** Le compte connecté est-il un simple lecteur ? */
function est_lecteur(): bool
{
    return compte_est_lecteur(current_account());
}

/** Le compte connecté est-il un journaliste ? */
function est_journaliste(): bool
{
    return compte_est_journaliste(current_account());
}

/** Réserve une action aux journalistes et aux administrateurs (403 sinon). */
function require_proposer(): void
{
    require_login();
    if (!peut_proposer()) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Accès refusé',
            'message' => 'Votre compte de lecteur ne permet pas de proposer des contenus. '
                . 'Demandez à un administrateur de vous ouvrir un compte journaliste.',
        ]);
        hdf_terminer();
    }
}

/**
 * Nom affiché : « Prénom Nom » quand on les connaît tous les deux,
 * sinon le nom affiché enregistré (comptes de branche, administrateurs).
 */
function compte_nom_complet(array $compte): string
{
    $prenom = trim((string) ($compte['first_name'] ?? ''));
    $nom    = trim((string) ($compte['last_name'] ?? ''));

    $morceaux = array_values(array_filter([$prenom, $nom], static fn (string $m): bool => $m !== ''));
    if ($morceaux !== []) {
        return implode(' ', $morceaux);
    }

    return trim((string) ($compte['display_name'] ?? '')) !== ''
        ? (string) $compte['display_name']
        : (string) ($compte['username'] ?? '');
}

/** Prénom seul (pour « Bonjour Élise »), vide si inconnu. */
function compte_prenom(array $compte): string
{
    return trim((string) ($compte['first_name'] ?? ''));
}

// ---------------------------------------------------------------------------
// Identifiants : prénom.nom
// ---------------------------------------------------------------------------

/** Retire les accents (table explicite : aucune dépendance à iconv). */
function compte_sans_accent(string $texte): string
{
    $table = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a',
        'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e',
        'ě' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o',
        'ő' => 'o',
        'ř' => 'r', 'ŕ' => 'r',
        'ś' => 's', 'š' => 's', 'ş' => 's',
        'ť' => 't', 'ţ' => 't',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'ł' => 'l', 'đ' => 'd', 'ð' => 'd', 'þ' => 'th',
    ];

    $texte = strtr($texte, $table);
    // Les majuscules accentuées (É, À, Ç…) : on repasse en minuscules d'abord,
    // puis on applique la même table.
    $texte = mb_strtolower($texte, 'UTF-8');

    return strtr($texte, $table);
}

/**
 * Un morceau d'identifiant : minuscules, sans accent, lettres et chiffres,
 * traits d'union conservés (Jean-Paul → jean-paul), apostrophes et espaces
 * retirés (Le Gall → legall, N'Diaye → ndiaye).
 */
function compte_morceau_identifiant(string $texte): string
{
    $texte = compte_sans_accent(trim($texte));
    $texte = str_replace(["'", '’', '`'], '', $texte);
    $texte = preg_replace('/[^a-z0-9\-]+/', '', $texte) ?? '';
    $texte = trim($texte, '-');

    return $texte;
}

/**
 * Identifiant de base : prenom.nom.
 * « Élise Martin » → « elise.martin » ; « Jean-Paul Le Gall » → « jean-paul.legall ».
 */
function compte_identifiant_base(string $prenom, string $nom): string
{
    $p = compte_morceau_identifiant($prenom);
    $n = compte_morceau_identifiant($nom);

    $base = $p;
    if ($n !== '') {
        $base .= ($base !== '' ? '.' : '') . $n;
    }

    // Un identifiant commence par une lettre : sinon, on préfixe.
    if ($base === '' || preg_match('/^[a-z]/', $base) !== 1) {
        $base = 'personne' . ($base !== '' ? '.' . $base : '');
    }

    return substr($base, 0, 30);
}

/** Identifiant conforme à la règle des comptes (3 à 30 caractères). */
function compte_identifiant_valide(string $identifiant): bool
{
    return preg_match('/^[a-z][a-z0-9._-]{2,29}$/', $identifiant) === 1;
}

/** L'identifiant est-il déjà pris (ou réservé dans le même envoi) ? */
function compte_identifiant_pris(string $identifiant, int $exclureId = 0, array $reserves = []): bool
{
    $identifiant = mb_strtolower(trim($identifiant), 'UTF-8');

    foreach ($reserves as $reserve) {
        if (mb_strtolower((string) $reserve, 'UTF-8') === $identifiant) {
            return true;
        }
    }

    $ligne = db_one(
        'SELECT id FROM accounts WHERE username = ? COLLATE NOCASE AND id <> ?',
        [$identifiant, $exclureId]
    );

    return $ligne !== null;
}

/**
 * Identifiant libre : le plus proche de la base, complété par 2, 3…
 * (elise.martin, elise.martin2, elise.martin3…). Sans dépasser 30 caractères.
 *
 * @param list<string> $reserves identifiants déjà attribués dans le même envoi
 */
function compte_identifiant_libre(string $base, int $exclureId = 0, array $reserves = []): string
{
    $base = trim(substr($base, 0, 30), '.-');
    if ($base === '') {
        $base = 'personne';
    }

    $candidat = $base;
    $numero   = 1;
    while (compte_identifiant_pris($candidat, $exclureId, $reserves)) {
        $numero++;
        $suffixe  = (string) $numero;
        $candidat = rtrim(substr($base, 0, 30 - strlen($suffixe)), '.-') . $suffixe;
    }

    return $candidat;
}

// ---------------------------------------------------------------------------
// Noms et prénoms
// ---------------------------------------------------------------------------

/**
 * Nettoie un nom ou un prénom : espaces réduits, casse remise proprement
 * (« élise » → « Élise », « jean-paul » → « Jean-Paul »).
 * Renvoie '' si la saisie est refusée (vide, trop longue, caractères bizarres).
 */
function compte_nettoyer_nom(string $brut): string
{
    $nom = preg_replace('/\s+/u', ' ', trim($brut)) ?? '';
    if ($nom === '' || mb_strlen($nom, 'UTF-8') > 40) {
        return '';
    }

    // Lettres, espace, tiret et apostrophe (O'Brien, N'Diaye) ; les chiffres
    // et les symboles sont refusés.
    if (preg_match("/^[\\p{L}][\\p{L} '’\\-]*$/u", $nom) !== 1) {
        return '';
    }

    $morceaux = preg_split("/([ \\-'’])/u", $nom, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    foreach ($morceaux as $index => $morceau) {
        if ($index % 2 === 1 || $morceau === '') {
            continue;
        }
        $morceaux[$index] = mb_strtoupper(mb_substr($morceau, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_strtolower(mb_substr($morceau, 1, null, 'UTF-8'), 'UTF-8');
    }

    return implode('', $morceaux);
}

// ---------------------------------------------------------------------------
// Saisie d'une liste de personnes
// ---------------------------------------------------------------------------

/** Nombre maximum de personnes acceptées en un seul envoi. */
const COMPTES_MAX_LIGNES = 100;

/**
 * Analyse une liste collée : une personne par ligne.
 *
 * Formats acceptés :
 *   - « Nom;Prénom » — sans ambiguïté, c'est la forme à conseiller dès
 *     qu'un nom ou un prénom compte plusieurs mots (« Le Gall;Jean-Paul ») ;
 *   - « Prénom Nom » ou « Nom Prénom » — deux mots : on retient le premier
 *     comme prénom et le second comme nom (usage le plus courant), et on
 *     prévient dans l'aperçu, car il faut le vérifier.
 *
 * Une ligne de plus de deux mots sans point-virgule est refusée : impossible
 * de deviner où s'arrête le prénom (« Jean-Paul Le Gall »).
 *
 * @return array{lignes:list<array>, erreurs:list<string>}
 *         chaque ligne : numero, nom, prenom, remarque, erreur
 */
function compte_analyser_liste(string $texte): array
{
    $resultat = ['lignes' => [], 'erreurs' => []];

    $brutes = preg_split('/\R/u', $texte) ?: [];
    $vues   = 0;

    foreach ($brutes as $index => $brute) {
        $brute = trim((string) $brute);
        if ($brute === '') {
            continue;
        }

        $vues++;
        if ($vues > COMPTES_MAX_LIGNES) {
            $resultat['erreurs'][] = sprintf(
                'La liste dépasse %d personnes : coupez-la en deux envois.',
                COMPTES_MAX_LIGNES
            );
            break;
        }

        $resultat['lignes'][] = compte_analyser_ligne($brute, $index + 1);
    }

    return $resultat;
}

/**
 * Analyse une ligne de la liste collée.
 *
 * @return array{numero:int, nom:string, prenom:string, remarque:string, erreur:string}
 */
function compte_analyser_ligne(string $brute, int $numero): array
{
    $ligne = ['numero' => $numero, 'nom' => '', 'prenom' => '', 'email' => '', 'remarque' => '', 'erreur' => ''];

    // 1. Forme stricte : « Nom;Prénom » ou « Nom;Prénom;adresse ».
    if (str_contains($brute, ';')) {
        $parties = array_map('trim', explode(';', $brute));
        if (count($parties) < 2 || count($parties) > 3
            || $parties[0] === '' || $parties[1] === '') {
            $ligne['erreur'] = 'Écrivez « Nom;Prénom » ou « Nom;Prénom;adresse » (morceaux séparés par des points-virgules).';
            return $ligne;
        }
        $nom    = compte_nettoyer_nom($parties[0]);
        $prenom = compte_nettoyer_nom($parties[1]);
        if ($nom === '' || $prenom === '') {
            $ligne['erreur'] = 'Le nom ou le prénom contient des caractères inattendus (ou dépasse 40 lettres).';
            return $ligne;
        }
        $ligne['nom']    = $nom;
        $ligne['prenom'] = $prenom;

        // Troisième morceau facultatif : l'adresse électronique.
        if (count($parties) === 3 && $parties[2] !== '') {
            $adresse = courriel_adresse_normaliser($parties[2]);
            if (!courriel_adresse_valide($adresse)) {
                $ligne['erreur'] = 'L’adresse électronique « ' . $parties[2] . ' » n’est pas valide.';
                return $ligne;
            }
            $ligne['email'] = $adresse;
        }

        return $ligne;
    }

    // 2. Formes avec espaces.
    $mots = preg_split('/\s+/u', $brute) ?: [];
    $mots = array_values(array_filter($mots, static fn (string $mot): bool => $mot !== ''));

    if (count($mots) < 2) {
        $ligne['erreur'] = 'Il faut un nom et un prénom.';
        return $ligne;
    }
    if (count($mots) > 2) {
        $ligne['erreur'] = 'Plusieurs mots : écrivez « Nom;Prénom » (par exemple « Le Gall;Jean-Paul »).';
        return $ligne;
    }

    $prenom = compte_nettoyer_nom($mots[0]);
    $nom    = compte_nettoyer_nom($mots[1]);
    if ($prenom === '' || $nom === '') {
        $ligne['erreur'] = 'Le nom ou le prénom contient des caractères inattendus (ou dépasse 40 lettres).';
        return $ligne;
    }

    $ligne['prenom']   = $prenom;
    $ligne['nom']      = $nom;
    $ligne['remarque'] = 'Ordre supposé : prénom puis nom. Pour un nom composé, écrivez « Nom;Prénom ».';

    return $ligne;
}

// ---------------------------------------------------------------------------
// Doublons
// ---------------------------------------------------------------------------

/**
 * Compte déjà existant avec le même nom, le même prénom et la même branche.
 * (La comparaison ignore la casse et les accents.)
 */
function compte_doublon(string $nom, string $prenom, ?int $brancheId, int $exclureId = 0): ?array
{
    // La comparaison se fait en PHP : accents, apostrophes et majuscules ne
    // doivent pas créer de faux doublons, et la table des comptes est petite
    // (quelques dizaines de lignes).
    // CAST(? AS INTEGER) : sans lui, SQLite compare le texte « 4 » à l'entier
    // 4 et ne trouve jamais rien (la fonction COALESCE n'a pas de type).
    $lignes = db_all(
        "SELECT id, username, first_name, last_name FROM accounts
         WHERE account_type = 'personne'
           AND id <> ?
           AND COALESCE(branch_id, 0) = CAST(? AS INTEGER)",
        [$exclureId, $brancheId ?? 0]
    );

    $nomCle    = mb_strtolower(compte_sans_accent(str_replace('’', "'", $nom)), 'UTF-8');
    $prenomCle = mb_strtolower(compte_sans_accent(str_replace('’', "'", $prenom)), 'UTF-8');

    foreach ($lignes as $ligne) {
        $memeNom = mb_strtolower(compte_sans_accent(str_replace('’', "'", (string) $ligne['last_name'])), 'UTF-8') === $nomCle;
        $memePrenom = mb_strtolower(compte_sans_accent(str_replace('’', "'", (string) $ligne['first_name'])), 'UTF-8') === $prenomCle;
        if ($memeNom && $memePrenom) {
            return $ligne;
        }
    }

    return null;
}

// ---------------------------------------------------------------------------
// Branches proposées
// ---------------------------------------------------------------------------

/**
 * Branches que l'on peut choisir pour une personne.
 * Les branches actives, plus la branche actuelle d'un compte que l'on
 * modifie (même désactivée : on ne la lui retire pas sans le vouloir).
 */
function comptes_branches_proposees(?int $brancheActuelle = null): array
{
    $branches = db_all(
        'SELECT * FROM branches WHERE is_active = 1 OR id = ? ORDER BY name COLLATE NOCASE',
        [$brancheActuelle ?? 0]
    );

    return $branches;
}

// ---------------------------------------------------------------------------
// Création des comptes
// ---------------------------------------------------------------------------

/**
 * Crée un lecteur : la branche et son compte de connexion, d'un seul geste.
 *
 * Un lecteur est le compte partagé d'une branche : sans branche, il n'aurait
 * aucun droit de lecture. On crée donc les deux ensemble, comme le fait la
 * page « Branches & comptes ».
 *
 * @return array{ok:bool, error:string, branche:int, identifiant:string, mot_de_passe:string}
 */
function compte_creer_lecteur(string $code, string $nom, bool $seeAll, int $adminId): array
{
    $resultat = ['ok' => false, 'error' => '', 'branche' => 0, 'identifiant' => '', 'mot_de_passe' => ''];

    // Pas de mise en majuscules silencieuse : un identifiant en minuscules est
    // refusé, avec un message qui explique la règle (comme la page Branches).
    $code = trim($code);
    $nom  = preg_replace('/\s+/u', ' ', trim($nom)) ?? '';

    if (preg_match('/^[A-Z][A-Z0-9_]{1,29}$/', $code) !== 1) {
        $resultat['error'] = 'L’identifiant doit être en majuscules, commencer par une lettre et ne '
            . 'contenir que des lettres, chiffres et tirets bas (2 à 30 caractères). Exemple : RAVIART.';
        return $resultat;
    }
    if ($nom === '' || mb_strlen($nom, 'UTF-8') > 100) {
        $resultat['error'] = 'Le nom de la branche est obligatoire (100 caractères maximum).';
        return $resultat;
    }
    if (db_one('SELECT id FROM branches WHERE code = ? COLLATE NOCASE', [$code]) !== null) {
        $resultat['error'] = 'Une branche utilise déjà cet identifiant.';
        return $resultat;
    }
    if (db_one('SELECT id FROM accounts WHERE username = ? COLLATE NOCASE', [$code]) !== null) {
        $resultat['error'] = 'Cet identifiant est déjà utilisé par un autre compte.';
        return $resultat;
    }

    $motDePasse = password_generate();
    $now        = time();

    $ids = db_transaction(static function () use ($code, $nom, $seeAll, $motDePasse, $now): array {
        $brancheId = db_insert('branches', [
            'code'        => $code,
            'name'        => $nom,
            'description' => null,
            'see_all'     => $seeAll ? 1 : 0,
            'is_active'   => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $compteId = db_insert('accounts', [
            'username'      => $code,
            'display_name'  => $nom,
            'password_hash' => password_make($motDePasse),
            'role'          => 'famille',
            'branch_id'     => $brancheId,
            'is_active'     => 1,
            'account_type'  => 'branche',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return ['branche' => $brancheId, 'compte' => $compteId];
    });

    log_activity($adminId > 0 ? $adminId : null, 'branche_ajoutee', $nom . ' (' . $code . ', profil lecteur)');

    $resultat['ok']           = true;
    $resultat['branche']      = (int) $ids['branche'];
    $resultat['identifiant']  = $code;
    $resultat['mot_de_passe'] = $motDePasse;

    return $resultat;
}

/**
 * Crée les comptes d'une liste préparée.
 *
 * @param list<array{nom:string, prenom:string, identifiant:string, branche:int, see_all:bool}> $entrees
 * @return array{crees:list<array>, erreurs:list<string>}
 *         chaque compte créé : id, nom, prenom, identifiant, mot_de_passe,
 *         branche_nom, message (texte prêt à envoyer)
 */
function comptes_creer(array $entrees, int $adminId): array
{
    $resultat = ['crees' => [], 'erreurs' => []];
    $now      = time();

    if ($entrees === []) {
        $resultat['erreurs'][] = 'Aucune personne à créer.';
        return $resultat;
    }
    if (count($entrees) > COMPTES_MAX_LIGNES) {
        $resultat['erreurs'][] = sprintf('Maximum %d personnes par envoi.', COMPTES_MAX_LIGNES);
        return $resultat;
    }

    // 1. Tout vérifier AVANT d'écrire quoi que ce soit.
    $vues       = [];
    $prepares   = [];
    foreach ($entrees as $index => $entree) {
        $numero = $index + 1;
        $nom    = compte_nettoyer_nom((string) ($entree['nom'] ?? ''));
        $prenom = compte_nettoyer_nom((string) ($entree['prenom'] ?? ''));
        $iden   = mb_strtolower(trim((string) ($entree['identifiant'] ?? '')), 'UTF-8');

        if ($nom === '' || $prenom === '') {
            $resultat['erreurs'][] = sprintf('Ligne %d : nom ou prénom incomplet.', $numero);
            continue;
        }
        if (!compte_identifiant_valide($iden)) {
            $resultat['erreurs'][] = sprintf('Ligne %d : identifiant invalide (« %s »).', $numero, $iden);
            continue;
        }
        if (isset($vues[$iden])) {
            $resultat['erreurs'][] = sprintf('Ligne %d : l’identifiant « %s » sert déjà à la ligne %d.', $numero, $iden, $vues[$iden]);
            continue;
        }
        if (compte_identifiant_pris($iden)) {
            $resultat['erreurs'][] = sprintf('Ligne %d : l’identifiant « %s » est déjà pris.', $numero, $iden);
            continue;
        }

        // Adresse électronique facultative : jamais obligatoire, et jamais
        // deux fois la même parmi les comptes actifs.
        $email = courriel_adresse_normaliser((string) ($entree['email'] ?? ''));
        if ($email !== '') {
            if (!courriel_adresse_valide($email)) {
                $resultat['erreurs'][] = sprintf('Ligne %d : l’adresse « %s » n’est pas valide.', $numero, $email);
                continue;
            }
            if (!courriel_adresse_libre($email)) {
                $resultat['erreurs'][] = sprintf('Ligne %d : l’adresse « %s » est déjà utilisée par un compte actif.', $numero, $email);
                continue;
            }
        }

        $brancheId = (int) ($entree['branche'] ?? 0);
        $branche   = $brancheId > 0 ? find_branch($brancheId) : null;
        if ($branche === null || (int) $branche['is_active'] !== 1) {
            $resultat['erreurs'][] = sprintf('Ligne %d : la branche choisie n’existe pas ou est désactivée.', $numero);
            continue;
        }

        $vues[$iden] = $numero;
        $prepares[]  = [
            'nom'         => $nom,
            'prenom'      => $prenom,
            'identifiant' => $iden,
            'email'       => $email,
            'branche'     => $branche,
            'see_all'     => !empty($entree['see_all']),
        ];
    }

    if ($resultat['erreurs'] !== []) {
        return $resultat;
    }

    // 2. Créer, en une seule transaction : tout ou rien.
    db_transaction(static function () use ($prepares, $now, &$resultat): void {
        foreach ($prepares as $prepare) {
            $motDePasse = password_generate();

            $id = db_insert('accounts', [
                // Le nom affiché d'un compte personnel est le prénom : c'est
                // lui qui s'affiche dans « Bonjour … » et comme auteur.
                'username'      => $prepare['identifiant'],
                'display_name'  => $prepare['prenom'],
                'password_hash' => password_make($motDePasse),
                'role'          => 'famille',
                'branch_id'     => (int) $prepare['branche']['id'],
                'is_active'     => 1,
                'account_type'  => 'personne',
                'first_name'    => $prepare['prenom'],
                'last_name'     => $prepare['nom'],
                'see_all'       => $prepare['see_all'] ? 1 : 0,
                'email'         => $prepare['email'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $resultat['crees'][] = [
                'id'          => $id,
                'nom'         => $prepare['nom'],
                'prenom'      => $prepare['prenom'],
                'identifiant' => $prepare['identifiant'],
                'email'       => $prepare['email'],
                'mot_de_passe' => $motDePasse,
                'branche_nom' => (string) $prepare['branche']['name'],
            ];
        }
    });

    return $resultat;
}

// ---------------------------------------------------------------------------
// Messages d'accès (SMS, WhatsApp, courriel)
// ---------------------------------------------------------------------------

/** Adresse publique du site (réglage, sinon fichier de configuration). */
function site_adresse_publique(): string
{
    $valeur = '';
    try {
        $valeur = (string) db_value("SELECT value FROM settings WHERE key = 'adresse'", [], '');
    } catch (Throwable) {
        $valeur = '';
    }
    if (trim($valeur) === '') {
        $valeur = (string) config('site.url', '');
    }

    return rtrim(trim($valeur), '/');
}

/**
 * Message prêt à coller dans un SMS, WhatsApp ou un courriel.
 * Exemple :
 *   Bonjour Élise, voici ton accès au site de la famille :
 *   Adresse : https://histoiredefamille.eu
 *   Identifiant : elise.martin
 *   Mot de passe : k7fd-3mqp-9xte
 *   (Tu pourras le changer dans « Mon compte ».)
 */
function compte_message_acces(array $compte, string $motDePasse): string
{
    $prenom = compte_prenom($compte);
    if ($prenom === '') {
        $prenom = trim((string) ($compte['prenom'] ?? ''));
    }
    if ($prenom === '') {
        $prenom = (string) ($compte['display_name'] ?? $compte['identifiant'] ?? $compte['username'] ?? '');
    }

    $adresse = site_adresse_publique();

    $lignes   = [];
    $lignes[] = sprintf(
        'Bonjour %s, voici ton accès au site de la famille :',
        $prenom !== '' ? $prenom : ''
    );
    $lignes[] = 'Adresse : ' . ($adresse !== '' ? $adresse : '(voir l’adresse du site)');
    $lignes[] = 'Identifiant : ' . (string) ($compte['identifiant'] ?? $compte['username'] ?? '');
    $lignes[] = 'Mot de passe : ' . $motDePasse;
    $libelle = compte_types()[compte_type($compte)] ?? 'Compte';
    $lignes[] = 'Profil : ' . $libelle . ' — ' . compte_profil_resume(compte_type($compte));
    $lignes[] = '(Tu pourras changer ton mot de passe dans « Mon compte ».)';

    return implode("\n", $lignes);
}

// ---------------------------------------------------------------------------
// Mot de passe : changement par la personne elle-même
// ---------------------------------------------------------------------------

/**
 * Change le mot de passe du compte connecté, après vérifications.
 *
 * @return list<string> erreurs (vide = mot de passe changé)
 */
function mot_de_passe_changer(array $compte, string $actuel, string $nouveau, string $confirmation): array
{
    $erreurs = [];

    if (!password_check($actuel, (string) ($compte['password_hash'] ?? ''))) {
        $erreurs[] = 'Le mot de passe actuel est incorrect.';
    }
    if (mb_strlen($nouveau) < 10) {
        $erreurs[] = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
    }
    if ($nouveau !== $confirmation) {
        $erreurs[] = 'Les deux nouveaux mots de passe ne sont pas identiques.';
    }
    if ($nouveau !== '' && mb_strtolower($nouveau) === mb_strtolower((string) ($compte['username'] ?? ''))) {
        $erreurs[] = 'Le mot de passe ne peut pas être identique à votre identifiant.';
    }
    if ($nouveau !== '' && $nouveau === $actuel) {
        $erreurs[] = 'Le nouveau mot de passe doit être différent de l’ancien.';
    }

    if ($erreurs !== []) {
        return $erreurs;
    }

    db_update('accounts', (int) $compte['id'], [
        'password_hash' => password_make($nouveau),
        'updated_at'    => time(),
    ]);

    return [];
}
