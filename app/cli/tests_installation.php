<?php
/**
 * Famille « installation » (1.28.1) : ce que fait `deploy/install.sh` quand on
 * le relance.
 *
 * Trois défauts corrigés en 1.28.1, un essai chacun :
 *
 *   1. la configuration n'est plus élargie : `config.local.php` garde ses
 *      droits (640 root:www-data) ou y revient s'ils étaient plus larges ;
 *   2. le minuteur d'envoi des courriels n'est plus rallumé quand le site
 *      n'envoie aucun courriel (« Courriels désactivés : minuteur laissé
 *      arrêté. ») ;
 *   3. le mode « sans disque externe » (backup_allow_same_disk) est annoncé
 *      comme tel, sans le « ATTENTION : /mnt/sauvegarde n'est pas un point de
 *      montage ».
 *
 * L'installateur ne peut pas être exécuté ici (il demande sudo, apt et
 * systemd) : on lit son code, et on éprouve pour de vrai la commande PHP qui
 * lui donne l'état du site (`etat-installation.php` → `installation_etat()`).
 */

declare(strict_types=1);

function tests_installation(): void
{
    echo "Installation — droits de la configuration, minuteur des courriels, mode sans disque\n";

    $install = (string) file_get_contents(dirname(__DIR__, 2) . '/deploy/install.sh');

    // =======================================================================
    // 1. config.local.php n'est jamais élargi
    // =======================================================================
    verifier(
        'droits : le chmod global des fichiers ne touche pas config.local.php',
        str_contains($install, "find \"\${SITE_DIR}\" -type f ! -name 'config.local.php' -exec chmod 644 {} \\;")
    );
    verifier(
        'droits : aucun chmod 644 ne vise config.local.php',
        preg_match('/chmod\s+644[^\n]*config\.local\.php/', $install) !== 1,
        'un chmod 644 sur la configuration est encore là'
    );

    // La fonction de réparation, telle qu'elle est écrite.
    $debut = strpos($install, 'droits_config_local() {');
    $fin   = $debut === false ? false : strpos($install, "\n}\n", (int) $debut);
    $fonction = ($debut === false || $fin === false) ? '' : substr($install, (int) $debut, (int) $fin - (int) $debut);
    verifier(
        'droits : une fonction remet la configuration à 640 root:www-data',
        $fonction !== ''
            && str_contains($fonction, 'stat -c')
            && str_contains($fonction, 'chmod 640')
            && str_contains($fonction, 'chown root:www-data'),
        $fonction === '' ? 'fonction absente' : 'fonction trouvée'
    );
    verifier(
        'droits : seuls les droits PLUS LARGES que 640 sont corrigés (masque du groupe et des autres)',
        str_contains($fonction, '8#${mode} & 8#037')
            && str_contains($fonction, 'droits conservés'),
        'masque ou branche « conservés » absent'
    );
    verifier(
        'droits : un fichier que www-data ne pourrait pas lire est signalé',
        str_contains($fonction, '8#${mode} & 8#040')
            && str_contains($fonction, 'www-data ne peut pas lire'),
        'avertissement de lecture absent'
    );
    verifier(
        'droits : le fichier existant est réparé, le contenu n’est pas touché',
        str_contains($install, 'config.local.php existe déjà : contenu inchangé.')
            && str_contains($install, 'droits_config_local "${CONFIG_LOCALE}"'),
        'branche « existe déjà » incorrecte'
    );
    verifier(
        'droits : un fichier neuf est créé en 640 root:www-data',
        str_contains($install, "cp \"\${SITE_DIR}/config.example.php\" \"\${CONFIG_LOCALE}\"")
            && str_contains($install, 'chmod 640 "${CONFIG_LOCALE}"')
    );

    // =======================================================================
    // 2. Le minuteur des courriels suit le réglage du site
    // =======================================================================
    verifier(
        'état : install.sh demande son état au site (etat-installation.php)',
        str_contains($install, 'app/cli/etat-installation.php')
            && str_contains($install, 's/^courriels_actifs=//p')
            && str_contains($install, 's/^sauvegarde_sans_disque=//p'),
        'lecture de l’état absente'
    );

    $debutBis = strpos($install, '# --- 8 bis.');
    $finBis   = $debutBis === false ? false : strpos($install, '# --- 8 ter.', (int) $debutBis);
    $sectionCourriels = ($debutBis === false || $finBis === false)
        ? ''
        : substr($install, (int) $debutBis, (int) $finBis - (int) $debutBis);

    verifier(
        'courriels : le minuteur n’est activé que si le réglage est vrai',
        $sectionCourriels !== ''
            && preg_match('/if \[ "\$\{COURRIELS_ACTIFS\}" = "1" \]; then\s*\n\s*systemctl enable --now histoiredefamille-courriels\.timer/', $sectionCourriels) === 1,
        $sectionCourriels === '' ? 'section 8 bis introuvable' : 'activation hors de la condition'
    );
    verifier(
        'courriels : sinon, le minuteur est arrêté et la raison est écrite',
        str_contains($sectionCourriels, 'systemctl disable --now histoiredefamille-courriels.timer')
            && str_contains($sectionCourriels, 'Courriels désactivés : minuteur laissé arrêté.'),
        'arrêt ou message manquant'
    );
    verifier(
        'courriels : relancer l’installation ne rallume pas un minuteur éteint exprès',
        str_contains($sectionCourriels, 'disable --now') && str_contains($sectionCourriels, 'Relancer ce script ne doit pas non plus rallumer'),
        'commentaire ou arrêt explicite manquant'
    );

    // =======================================================================
    // 3. Mode sans disque externe : un message, pas une alerte
    // =======================================================================
    $debutHuit = strpos($install, '# --- 8. Sauvegarde quotidienne');
    $finHuit   = $debutHuit === false ? false : strpos($install, '# --- 8 bis.', (int) $debutHuit);
    $sectionSauvegarde = ($debutHuit === false || $finHuit === false)
        ? ''
        : substr($install, (int) $debutHuit, (int) $finHuit - (int) $debutHuit);

    verifier(
        'sauvegarde : sans disque externe assumé, le message n’est plus une alerte',
        $sectionSauvegarde !== ''
            && str_contains($sectionSauvegarde, 'elif [ "${SAUVEGARDE_SANS_DISQUE}" = "1" ]; then')
            && str_contains($sectionSauvegarde, 'Mode sans disque externe actif'),
        $sectionSauvegarde === '' ? 'section 8 introuvable' : 'branche « sans disque » absente'
    );
    $positionDisque  = strpos($sectionSauvegarde, 'Mode sans disque externe actif');
    $positionAlerte  = strpos($sectionSauvegarde, "ATTENTION : /mnt/sauvegarde n'est pas un point de montage");
    verifier(
        'sauvegarde : l’alerte reste pour le cas où rien n’est assumé',
        $positionAlerte !== false
            && $positionDisque !== false
            && $positionAlerte > $positionDisque,
        'alerte ' . var_export($positionAlerte, true) . ', disque ' . var_export($positionDisque, true)
    );

    // =======================================================================
    // 4. La commande qui donne l'état, éprouvée pour de vrai
    // =======================================================================
    $etat = installation_etat();
    verifier(
        'état : installation_etat() rend les deux réponses attendues',
        is_array($etat)
            && array_keys($etat) === ['courriels_actifs', 'sauvegarde_sans_disque']
            && is_bool($etat['courriels_actifs'])
            && is_bool($etat['sauvegarde_sans_disque']),
        json_encode($etat)
    );
    verifier(
        'état : « sauvegarde_sans_disque » suit le réglage backup_allow_same_disk',
        $etat['sauvegarde_sans_disque'] === !empty(sauvegarde_options()['allow_same_disk']),
        json_encode($etat)
    );

    $pdo = db();
    $transaction = false;
    try {
        $pdo->beginTransaction();
        $transaction = true;

        save_site_setting('courriels_actifs', '0');
        verifier(
            'état : courriels coupés → false (le minuteur restera arrêté)',
            installation_etat()['courriels_actifs'] === false
        );

        save_site_setting('courriels_actifs', '1');
        verifier(
            'état : courriels rallumés → true (l’installateur peut activer le minuteur)',
            installation_etat()['courriels_actifs'] === true
        );

        save_site_setting('courriels_actifs', 'oui');
        verifier(
            'état : « oui » compte comme activé',
            installation_etat()['courriels_actifs'] === true
        );
    } finally {
        if ($transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    // La commande elle-même : même état, deux lignes lisibles par un script.
    $commande = (string) file_get_contents(dirname(__DIR__) . '/cli/etat-installation.php');
    verifier(
        'commande : php app/cli/etat-installation.php affiche deux lignes « clé=valeur »',
        str_contains($commande, "echo 'courriels_actifs='")
            && str_contains($commande, "echo 'sauvegarde_sans_disque='")
            && str_contains($commande, 'installation_etat()'),
        'clés ou appel absents'
    );
    verifier(
        'commande : réservée à la ligne de commande, et code 0 quand tout va bien',
        str_contains($commande, 'is_cli()')
            && str_contains($commande, 'http_response_code(403)')
            && str_contains($commande, 'exit(0)'),
        'garde ou code de sortie manquant'
    );
    verifier(
        'commande : une base absente est expliquée, avec un code d’erreur',
        str_contains($commande, 'db_is_installed()') && str_contains($commande, 'exit(1)')
    );

    // =======================================================================
    // 5. Le reste de l'installateur ne bouge pas
    // =======================================================================
    verifier(
        'installation : les minuteurs de sauvegarde et de conversion restent activés',
        str_contains($install, 'systemctl enable --now histoiredefamille-sauvegarde.timer')
            && str_contains($install, 'systemctl enable --now histoiredefamille-conversion.timer'),
        'un minuteur a disparu'
    );
    verifier(
        'installation : le script refuse toujours de tourner sans sudo',
        str_contains($install, 'if [ "$(id -u)" -ne 0 ]; then') && str_contains($install, 'sudo bash deploy/install.sh'),
        'garde sudo absente'
    );
    verifier(
        'installation : la configuration locale n’est jamais écrasée (rsync et écriture)',
        str_contains($install, "--exclude 'config.local.php'")
            && str_contains($install, 'if [ ! -f "${CONFIG_LOCALE}" ]; then'),
        'une copie pourrait écraser la configuration'
    );
}
