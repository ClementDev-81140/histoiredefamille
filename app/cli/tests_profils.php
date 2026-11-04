<?php
/**
 * Tests des trois profils de comptes (lot « profils »).
 *
 * Trois profils, et seulement trois :
 *   - Lecteur     (account_type « branche ») : lit, ne propose rien ;
 *   - Journaliste (account_type « personne ») : lit et propose, sans publier ;
 *   - Administrateur : tout, et il valide.
 *
 * Ce que l'on vérifie ici : les libellés, les aides de décision
 * (compte_est_lecteur, compte_est_journaliste, peut_proposer), la création
 * d'un lecteur (la branche et son compte), le changement de profil, et surtout
 * qu'un lecteur ne reçoit QUE des refus sur toutes les routes d'écriture.
 *
 * Tout se passe dans une transaction annulée : la base du site est rendue
 * intacte.
 */

declare(strict_types=1);

/** Compte d'essai d'un type donné, supprimé par le rollback. */
function tests_profils_compte(string $type, ?int $branche = null, bool $voirTout = false): int
{
    static $suffixe = null;
    $suffixe = $suffixe ?? bin2hex(random_bytes(3));

    return db_insert('accounts', [
        'username'      => 'essai-' . $type . '-' . bin2hex(random_bytes(4)),
        'display_name'  => 'Essai ' . $type,
        'password_hash' => password_make('MotDePasseEssai123!'),
        'role'          => $type === 'admin' ? 'admin' : 'famille',
        'account_type'  => $type,
        'first_name'    => $type === 'personne' ? 'Essai' : '',
        'last_name'     => $type === 'personne' ? 'Profil' : '',
        'branch_id'     => $branche,
        'see_all'       => $voirTout ? 1 : 0,
        'is_active'     => 1,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
}

/** Branche d'essai (créée pour la durée du test). */
function tests_profils_branche(string $nom): int
{
    return db_insert('branches', [
        'code'        => 'ESSAI' . strtoupper(bin2hex(random_bytes(2))),
        'name'        => $nom,
        'description' => null,
        'see_all'     => 0,
        'is_active'   => 1,
        'created_at'  => time(),
        'updated_at'  => time(),
    ]);
}

/** Toute la famille « profils ». */
function tests_profils(): void
{
    echo "Profils de comptes — lecteur, journaliste, administrateur\n";

    $avant = [
        'comptes'  => (int) db_value('SELECT COUNT(*) FROM accounts', [], 0),
        'branches' => (int) db_value('SELECT COUNT(*) FROM branches', [], 0),
    ];

    try {
        db_transaction(static function () use ($avant): void {
            tests_profils_libelles();
            tests_profils_droits();
            tests_profils_creation();
            tests_profils_changement();

            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    }

    // Rien ne doit rester (la transaction a tout annulé, on le vérifie).
    verifier(
        'profils : les comptes d’essai sont tous annulés',
        (int) db_value('SELECT COUNT(*) FROM accounts', [], 0) === (int) $avant['comptes']
            && (int) db_value('SELECT COUNT(*) FROM branches', [], 0) === (int) $avant['branches'],
        'comptes : ' . (int) db_value('SELECT COUNT(*) FROM accounts', [], 0)
            . ' au lieu de ' . (int) $avant['comptes']
    );
}

/** 1. Les trois libellés, et rien d'autre. */
function tests_profils_libelles(): void
{
    $types = compte_types();

    verifier(
        'profils : le site ne connaît que trois profils',
        array_keys($types) === ['branche', 'personne', 'admin'],
        implode(', ', array_keys($types))
    );
    verifier(
        'profils : « branche » s’appelle Lecteur (rien ne change en base)',
        $types['branche'] === 'Lecteur',
        $types['branche']
    );
    verifier(
        'profils : « personne » s’appelle Journaliste',
        $types['personne'] === 'Journaliste',
        $types['personne']
    );
    verifier(
        'profils : « admin » s’appelle Administrateur',
        $types['admin'] === 'Administrateur',
        $types['admin']
    );

    // Un libellé ancien ne doit plus traîner dans les écrans de comptes.
    $ecrans = [
        'views/admin/comptes.php',
        'views/admin/comptes_form.php',
        'views/admin/compte_form.php',
        'views/admin/comptes_apercu.php',
        'views/admin/comptes_resultat.php',
    ];
    $anciens = [];
    foreach ($ecrans as $ecran) {
        $code = (string) @file_get_contents(HDF_APP . '/' . $ecran);
        if (str_contains($code, 'Compte de branche') || str_contains($code, '>Personne<')
            || str_contains($code, 'Ajouter des personnes')) {
            $anciens[] = $ecran;
        }
    }
    verifier(
        'profils : les écrans de comptes ne parlent plus de « compte de branche » ni de « personne »',
        $anciens === [],
        implode(', ', $anciens)
    );

    verifier(
        'profils : le message d’accès annonce le profil et ce qu’il permet',
        (static function (): bool {
            $texte = compte_message_acces([
                'username' => 'martin', 'display_name' => 'Famille Martin', 'account_type' => 'branche',
            ], 'mot-de-passe-d-essai');
            return str_contains($texte, 'Profil : Lecteur')
                && str_contains($texte, 'ne peut rien écrire');
        })()
    );
}

/** 2. Ce que chaque profil peut faire, sans passer par une page. */
function tests_profils_droits(): void
{
    $branche = tests_profils_branche('Branche des profils');
    $lecteur = tests_profils_compte('branche', $branche);
    $journaliste = tests_profils_compte('personne', $branche);
    $admin = tests_profils_compte('admin');

    $compte = static fn (int $id): array => (array) db_one('SELECT * FROM accounts WHERE id = ?', [$id]);

    verifier(
        'profils : un lecteur est un lecteur',
        compte_est_lecteur($compte($lecteur)) && !compte_est_journaliste($compte($lecteur))
    );
    verifier(
        'profils : un journaliste est un journaliste',
        compte_est_journaliste($compte($journaliste)) && !compte_est_lecteur($compte($journaliste))
    );
    verifier(
        'profils : un administrateur n’est ni lecteur ni journaliste',
        !compte_est_lecteur($compte($admin)) && !compte_est_journaliste($compte($admin))
    );

    verifier(
        'profils : un lecteur ne peut rien proposer',
        peut_proposer($compte($lecteur)) === false
    );
    verifier(
        'profils : un journaliste peut proposer',
        peut_proposer($compte($journaliste)) === true
    );
    verifier(
        'profils : un administrateur peut proposer (et publier)',
        peut_proposer($compte($admin)) === true
    );
    verifier(
        'profils : un visiteur ne peut rien proposer',
        peut_proposer(null) === false
    );

    // --- Un lecteur est refusé partout où l'on écrit -----------------------
    $_SESSION = ['account_id' => $lecteur];
    $famille = [
        'GET /famille'              => tests_envoi_requete('GET', '/famille', $lecteur),
        'GET /mon-compte'           => tests_envoi_requete('GET', '/mon-compte', $lecteur),
        'GET /tchat'                => tests_envoi_requete('GET', '/tchat', $lecteur),
    ];
    $codes = array_values(array_map(static fn (array $r): int => (int) $r['code'], $famille));
    verifier(
        'profils : un lecteur garde « Mon compte », le tchat et l’espace famille',
        $codes === [200, 200, 200],
        json_encode($codes)
    );

    // Le tchat est la seule écriture qu'on lui laisse : il n'est jamais refusé
    // pour ses droits (422 = il manque seulement le prénom de signature, que
    // la session d'essai ne peut pas garder d'une requête à l'autre).
    $_SESSION = ['account_id' => $lecteur, '_csrf' => csrf_token()];
    $jeton = csrf_token();
    $tchat = tests_envoi_requete('POST', '/tchat/envoyer', $lecteur, [
        '_csrf' => $jeton, 'message' => 'Bonjour tout le monde',
    ]);
    verifier(
        'profils : un lecteur peut écrire dans le tchat (aucun refus de droits)',
        $tchat['code'] !== 403 && !str_contains($tchat['location'], '/connexion')
            && !str_contains($tchat['corps'], 'ne permet pas'),
        (string) $tchat['code']
    );

    // Toutes les autres écritures sont fermées.
    $ecritures = [
        'POST /admin/documents/nouveau' => '/admin/documents/nouveau',
        'POST /admin/articles/nouveau'  => '/admin/articles/nouveau',
        'POST /admin/recettes/nouvelle' => '/admin/recettes/nouvelle',
        'POST /admin/envoyer/init'      => '/admin/envoyer/init',
        'POST /admin/albums/nouveau'    => '/admin/albums/nouveau',
        'POST /admin/comptes/lecteur'   => '/admin/comptes/lecteur',
    ];
    foreach ($ecritures as $nom => $url) {
        $reponse = tests_envoi_requete('POST', $url, $lecteur, ['_csrf' => $jeton]);
        verifier(
            'profils : ' . $nom . ' — refusé à un lecteur (403)',
            $reponse['code'] === 403,
            (string) $reponse['code']
        );
    }

    // Un journaliste ne publie pas non plus : ces pages restent fermées.
    $refus = tests_envoi_requete('POST', '/admin/documents/nouveau', $journaliste, ['_csrf' => $jeton]);
    verifier(
        'profils : un journaliste ne publie pas par les pages d’administration (403)',
        $refus['code'] === 403,
        (string) $refus['code']
    );

    // Le visiteur, lui, est renvoyé vers la connexion.
    $visiteur = tests_envoi_requete('POST', '/admin/documents/nouveau', null, []);
    verifier(
        'profils : un visiteur est renvoyé vers la connexion',
        $visiteur['code'] === 303 && str_contains($visiteur['location'], '/connexion'),
        $visiteur['code'] . ' ' . $visiteur['location']
    );

    // L'administrateur, lui, ouvre la page de création de document.
    $page = tests_envoi_requete('GET', '/admin/documents/nouveau', $admin);
    verifier(
        'profils : un administrateur ouvre les pages d’écriture',
        $page['code'] === 200,
        (string) $page['code']
    );
}

/** 3. Créer un lecteur : la branche et son compte, d'un seul geste. */
function tests_profils_creation(): void
{
    $admin = tests_profils_compte('admin');
    $_SESSION = ['account_id' => $admin];

    $page = tests_envoi_requete('GET', '/admin/comptes/nouvelle', $admin);
    verifier(
        'création : la page « Ajouter un compte » propose le profil Lecteur par défaut',
        $page['code'] === 200 && str_contains($page['corps'], 'profil=lecteur')
            && str_contains($page['corps'], 'recherche__onglets'),
        (string) $page['code']
    );
    verifier_contient(
        'création : le profil Lecteur est celui qui s’ouvre par défaut',
        'Créer le lecteur',
        $page['corps']
    );

    $code = 'ESSAI' . strtoupper(bin2hex(random_bytes(2)));
    $resultat = compte_creer_lecteur($code, 'Famille d’essai', false, $admin);

    verifier(
        'création : un lecteur crée sa branche et son compte',
        $resultat['ok'] === true && $resultat['branche'] > 0 && $resultat['mot_de_passe'] !== '',
        $resultat['error']
    );

    $compte = $resultat['identifiant'] !== ''
        ? db_one('SELECT * FROM accounts WHERE username = ?', [$resultat['identifiant']])
        : null;
    verifier(
        'création : le compte créé est de type « branche » (Lecteur), dans sa nouvelle branche',
        $compte !== null && compte_type($compte) === 'branche'
            && (int) $compte['branch_id'] === (int) $resultat['branche'],
        $compte !== null ? compte_type($compte) . ' / branche ' . (string) $compte['branch_id'] : 'aucun compte'
    );
    verifier(
        'création : le mot de passe n’est jamais stocké en clair (hachage seul)',
        $compte !== null
            && !str_contains((string) $compte['password_hash'], $resultat['mot_de_passe'])
            && password_check($resultat['mot_de_passe'], (string) $compte['password_hash'])
    );
    verifier(
        'création : le message d’accès du lecteur dit « Profil : Lecteur »',
        $compte !== null && str_contains(compte_message_acces($compte, $resultat['mot_de_passe']), 'Lecteur')
    );

    // Deux fiches pour la même branche, jamais.
    $doublon = compte_creer_lecteur($code, 'Famille d’essai bis', false, $admin);
    verifier(
        'création : un identifiant déjà pris est refusé, sans rien créer',
        $doublon['ok'] === false && str_contains($doublon['error'], 'déjà'),
        $doublon['error']
    );

    $invalide = compte_creer_lecteur('martin', 'Famille Martin', false, $admin);
    verifier(
        'création : un identifiant en minuscules est refusé (règle des comptes de branche)',
        $invalide['ok'] === false && str_contains($invalide['error'], 'majuscules'),
        $invalide['error']
    );
    verifier(
        'création : un identifiant refusé n’a créé ni branche ni compte',
        db_one('SELECT id FROM branches WHERE code = ? COLLATE NOCASE', ['MARTIN']) === null
            && db_one('SELECT id FROM accounts WHERE username = ? COLLATE NOCASE', ['MARTIN']) === null
    );
}

/** 4. Changer le profil d'un compte existant. */
function tests_profils_changement(): void
{
    $admin   = tests_profils_compte('admin');
    $branche = tests_profils_branche('Branche du changement');
    $compte  = tests_profils_compte('personne', $branche);

    $page = tests_envoi_requete('GET', '/admin/comptes/' . $compte, $admin);
    verifier(
        'changement : la fiche d’un journaliste propose le choix du profil',
        $page['code'] === 200 && str_contains($page['corps'], 'name="profil"'),
        (string) $page['code']
    );

    $avant = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$compte]);

    $_SESSION = ['account_id' => $admin, '_csrf' => csrf_token()];
    $enregistre = tests_envoi_requete('POST', '/admin/comptes/' . $compte, $admin, [
        '_csrf'        => csrf_token(),
        'profil'       => 'lecteur',
        'nom'          => 'Famille du changement',
        'prenom'       => '',
        'identifiant'  => (string) $avant['username'],
        'branche'      => (string) $branche,
        'email'        => '',
    ]);

    $apres = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$compte]);
    verifier(
        'changement : passer un journaliste en lecteur change son type, pas son identifiant',
        $enregistre['code'] === 303 && compte_type($apres) === 'branche'
            && (string) $apres['username'] === (string) $avant['username'],
        $enregistre['code'] . ' / ' . compte_type($apres)
    );
    verifier(
        'changement : le mot de passe du compte n’est pas touché',
        (string) $apres['password_hash'] === (string) $avant['password_hash']
    );
    verifier(
        'changement : un lecteur ainsi créé ne peut toujours rien proposer',
        peut_proposer($apres) === false
    );

    // Retour en journaliste : le nom et le prénom redeviennent obligatoires.
    $_SESSION = ['account_id' => $admin, '_csrf' => csrf_token()];
    $sansNom = tests_envoi_requete('POST', '/admin/comptes/' . $compte, $admin, [
        '_csrf'       => csrf_token(),
        'profil'      => 'journaliste',
        'nom'         => '',
        'prenom'      => '',
        'identifiant' => (string) $avant['username'],
        'branche'     => (string) $branche,
        'email'       => '',
    ]);
    $inchange = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$compte]);
    verifier(
        'changement : un journaliste sans nom ni prénom est refusé (rien ne change)',
        $sansNom['code'] === 303 && compte_type($inchange) === 'branche',
        (string) $sansNom['code'] . ' / ' . compte_type($inchange)
    );

    $_SESSION = ['account_id' => $admin, '_csrf' => csrf_token()];
    $retour = tests_envoi_requete('POST', '/admin/comptes/' . $compte, $admin, [
        '_csrf'       => csrf_token(),
        'profil'      => 'journaliste',
        'nom'         => 'Durand',
        'prenom'      => 'Élise',
        'identifiant' => (string) $avant['username'],
        'branche'     => (string) $branche,
        'email'       => '',
    ]);
    $final = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$compte]);
    verifier(
        'changement : repasser un lecteur en journaliste le rend de nouveau capable de proposer',
        $retour['code'] === 303 && compte_type($final) === 'personne' && peut_proposer($final) === true,
        $retour['code'] . ' / ' . compte_type($final)
    );
    verifier(
        'changement : le prénom redevient le nom affiché du journaliste',
        (string) $final['display_name'] === 'Élise',
        (string) $final['display_name']
    );

    // Un administrateur ne se modifie pas ici : il a son écran.
    $autreAdmin = tests_profils_compte('admin');
    $versAdmin = tests_envoi_requete('POST', '/admin/comptes/' . $autreAdmin, $admin, [
        '_csrf'       => csrf_token(),
        'profil'      => 'lecteur',
        'nom'         => 'X',
        'identifiant' => 'x',
        'branche'     => (string) $branche,
    ]);
    $resteAdmin = (array) db_one('SELECT * FROM accounts WHERE id = ?', [$autreAdmin]);
    verifier(
        'changement : un administrateur ne peut pas être transformé en lecteur par cette page',
        $versAdmin['code'] === 303 && compte_type($resteAdmin) === 'admin',
        $versAdmin['code'] . ' / ' . compte_type($resteAdmin)
    );
}
