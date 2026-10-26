<?php
/**
 * Point d'entrée unique du site (racine web = dossier public/).
 *
 * Toutes les URL passent par ce fichier grâce à la règle de réécriture
 * d'Apache (voir public/.htaccess). Aucun fichier privé n'est exposé ici :
 * les documents sont servis par PHP après vérification des droits.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/router.php';

// Routes de l'application.
require_once dirname(__DIR__) . '/app/routes.php';

dispatch_request();
