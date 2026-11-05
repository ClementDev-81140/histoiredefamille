<?php
/**
 * Vérification avant d'ouvrir le site à la famille.
 *
 * Utilisation :
 *
 *     php app/cli/verifier-mise-en-ligne.php
 *
 * La commande passe en revue tout ce qui doit être vrai le jour de
 * l'ouverture : HTTPS, comptes, données de démonstration, permissions,
 * emplacements des fichiers, extensions PHP, sauvegarde, espace disque,
 * purge planifiée, en-têtes HTTP réellement envoyés par Apache et cookies.
 *
 * Chaque ligne rend :
 *   OK        c'est bon ;
 *   À CORRIGER il faut agir AVANT d'ouvrir (la commande le dit comment) ;
 *   INFO      à savoir, sans bloquer.
 *
 * Le dernier résultat est enregistré pour la page « Administration → État du
 * site » (aucune donnée personnelle, aucun mot de passe).
 *
 * Code de sortie : 0 = prêt à ouvrir, 1 = au moins un point à corriger.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

$controles = mise_en_ligne_controles();
$verdict   = mise_en_ligne_verdict($controles);

echo "Vérification avant ouverture du site\n";
echo "------------------------------------\n\n";

$largeur = 0;
foreach ($controles as $controle) {
    $largeur = max($largeur, mb_strlen($controle['titre']));
}

foreach ($controles as $controle) {
    $etiquette = match ($controle['statut']) {
        'ok'      => 'OK           ',
        'corriger' => 'À CORRIGER   ',
        'avertir' => 'AVERTISSEMENT',
        default   => 'INFO         ',
    };

    echo $etiquette, '  ', str_pad($controle['titre'], $largeur), '  ', $controle['detail'], "\n";
    if (in_array($controle['statut'], ['corriger', 'avertir'], true) && $controle['remede'] !== '') {
        echo '            → ', $controle['remede'], "\n";
    }
}

echo "\n";

if ($verdict['pret']) {
    echo "PRÊT À OUVRIR\n";
    if ($verdict['avertissements'] > 0) {
        echo $verdict['avertissements'], " avertissement(s) : le site peut ouvrir, mais lisez-les ci-dessus.\n";
    }
    if ($verdict['infos'] > 0) {
        echo $verdict['infos'], " information(s) affichée(s) ci-dessus (sans blocage).\n";
    }
    echo "\nVous pouvez prévenir la famille et envoyer l’adresse du site.\n";
} else {
    echo $verdict['texte'], "\n";
    echo "Corrigez les lignes « À CORRIGER » ci-dessus, puis relancez cette commande.\n";
}

$enregistre = mise_en_ligne_enregistrer($controles);
if ($enregistre) {
    echo "\nRésultat enregistré : Administration → État du site.\n";
} else {
    cli_err("\nAttention : le résultat n’a pas pu être enregistré pour la page « État du site »");
    cli_err('(dossier de stockage non inscriptible ?).');
}

exit($verdict['pret'] && $enregistre ? 0 : 1);
