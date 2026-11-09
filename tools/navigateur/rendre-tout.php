<?php
/**
 * Rend en lot les pages de la matrice d'accès (GET répondant 200), pour le
 * contrôle des doublons `actions-doublons.mjs`.
 *
 * Ce n'est pas un point d'entrée web : le fichier est hors de public/.
 * Toutes les données sont celles des fixtures de la matrice (fictives),
 * créées dans une transaction annulée ; aucun fichier du site n'est servi.
 *
 *   HDF_CONFIG=… php tools/navigateur/rendre-tout.php /tmp/rendus-doublons
 *
 * Écrit, dans le dossier donné : un fichier HTML par (profil, route), et
 * `manifest.json` qui les décrit. Profils : visiteur, lecteur,
 * journaliste (avec validation, réglage par défaut), journaliste_direct
 * (sans validation, créé pour ce contrôle), administrateur.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

// Même nom que dans les essais : hdf_terminer() la reconnaît et
// matrice_requete() l'attrape (fin normale d'une page qui redirige).
class HdfTestArret extends Exception
{
}

require_once HDF_APP . '/router.php';
require_once HDF_APP . '/routes.php';
require_once HDF_APP . '/lib/matrice_acces.php';

$sortie = (string) ($argv[1] ?? '');
if ($sortie === '') {
    exit("Usage : rendre-tout.php <dossier-de-sortie>\n");
}
if (!is_dir($sortie) && !@mkdir($sortie, 0770, true) && !is_dir($sortie)) {
    exit("Dossier de sortie impossible : {$sortie}\n");
}

$pdo = db();
$profilsComptes = [];
$manifest = [];
$rendus = 0;

try {
    $pdo->beginTransaction();
    $f = matrice_fixtures();

    // Le journaliste « sans validation » n'existe pas dans les profils de
    // la matrice (le journaliste d'essai est « avec validation », réglage
    // par défaut) : on le crée ici, même branche fictive B.
    $direct = db_insert('accounts', [
        'username' => 'matrice.direct.' . bin2hex(random_bytes(3)),
        'display_name' => 'Journaliste direct (essai)',
        'password_hash' => (string) db_value('SELECT password_hash FROM accounts LIMIT 1', [], ''),
        'role' => 'famille', 'account_type' => 'personne',
        'first_name' => 'Direct', 'last_name' => '(essai)',
        'branch_id' => (int) $f['branch_b'], 'is_active' => 1,
        'journaliste_validation' => 0, 'journaliste_toutes_branches' => 1,
        'created_at' => time(), 'updated_at' => time(),
    ]);

    $profilsComptes = [
        'visiteur' => null,
        'lecteur' => (int) $f['compte_branche_a'],
        'journaliste' => (int) $f['compte_personne_b'],
        'journaliste_direct' => $direct,
        'admin' => (int) $f['account_admin'],
    ];

    // Profil « attendu » de la matrice pour le journaliste direct : mêmes
    // réponses que le journaliste, sauf les pages de modification qui lui
    // sont ouvertes (200 au lieu de 403). On rend donc, pour lui, toute
    // route GET qui répond 200 à l'un des deux journalistes.
    foreach (matrice_routes() as $route) {
        if ($route['methode'] !== 'GET') {
            continue;
        }
        $url = matrice_url($route['motif'], $f);
        foreach ($profilsComptes as $profil => $compte) {
            $profilMatrice = $profil === 'journaliste_direct' ? 'journaliste' : $profil;
            $attendu = matrice_attendu($route['methode'], $route['motif'], $profilMatrice);
            $aRendre = $attendu === 200
                || ($profil === 'journaliste_direct' && $attendu === 403
                    && matrice_famille($route['methode'], $route['motif']) === 'journaliste_ecriture');
            if (!$aRendre) {
                continue;
            }

            $_SESSION = $compte === null ? [] : ['account_id' => $compte, '_csrf' => 'jeton-essai-doublons'];
            $reponse = matrice_requete('GET', $url, $compte);
            if ($reponse['code'] !== 200 || trim($reponse['corps']) === '') {
                continue;
            }

            $nom = $profil . '__' . preg_replace('/[^a-z0-9]+/i', '-', $route['motif']) . '.html';
            $nom = trim((string) $nom, '-');
            file_put_contents($sortie . '/' . $nom, $reponse['corps']);
            $manifest[] = [
                'profil' => $profil,
                'route'  => $route['motif'],
                'url'    => $url,
                'fichier' => $nom,
            ];
            $rendus++;
        }
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    matrice_supprimer_fichiers($f ?? []);
}

file_put_contents($sortie . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo $rendus . " page(s) rendue(s) dans {$sortie}\n";
