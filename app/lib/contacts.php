<?php
/**
 * Adresses affichées sur le site, et réglage « les courriels sont-ils actifs ? ».
 *
 * Décision de la version 1.28.0 : le site n'envoie **aucun** courriel. Les deux
 * adresses ci-dessous ne servent qu'à être lues par un humain : elles sont
 * affichées (pied de page, connexion, « Mon compte », accueil, pages d'erreur)
 * pour qu'on puisse écrire depuis l'extérieur. Le site ne s'en sert jamais
 * comme expéditeur ni comme destinataire.
 *
 * Elles sont modifiables dans Administration → Réglages et rangées dans la
 * table `settings` ; `site_adresse()` est la **seule** fonction de lecture,
 * utilisée partout (aucune vue n'écrit d'adresse en dur).
 */

declare(strict_types=1);

/**
 * Adresses livrées avec le site : elles servent de valeur initiale et de repli
 * quand le réglage est vide ou invalide (jamais d'adresse cassée à l'écran).
 */
const SITE_ADRESSES_DEFAUT = [
    'contact' => 'contact@histoiredefamille.eu',
    'support' => 'support@histoiredefamille.eu',
];

/** Clés admises pour site_adresse(). */
const SITE_ADRESSES_CLES = ['contact', 'support'];

/**
 * Adresse affichée (« contact » ou « support »).
 *
 * Une adresse enregistrée mais invalide (saisie à la main dans la base, par
 * exemple) n'est jamais affichée : on retombe sur celle livrée avec le site.
 */
function site_adresse(string $cle = 'contact'): string
{
    if (!in_array($cle, SITE_ADRESSES_CLES, true)) {
        return '';
    }

    $valeur = courriel_adresse_normaliser(site_setting($cle));
    if ($valeur !== '' && courriel_adresse_valide($valeur)) {
        return $valeur;
    }

    return SITE_ADRESSES_DEFAUT[$cle];
}

/**
 * Toutes les adresses affichées, prêtes pour l'administration.
 *
 * @return array{contact: string, support: string}
 */
function site_adresses(): array
{
    return [
        'contact' => site_adresse('contact'),
        'support' => site_adresse('support'),
    ];
}

/**
 * Le site envoie-t-il des courriels ?
 *
 * Faux par défaut, et faux quand le réglage est absent : c'est l'état normal
 * du site depuis la 1.28.0. Le code d'envoi n'est pas supprimé pour autant :
 * il se réactive en cochant la case dans Administration → Réglages **et** en
 * remplissant le bloc « mail » de config.local.php.
 */
function site_courriels_actifs(): bool
{
    $valeur = strtolower(trim(site_setting('courriels_actifs')));

    return in_array($valeur, ['1', 'oui', 'true', 'on'], true);
}
