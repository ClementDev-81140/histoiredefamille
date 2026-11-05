<?php
/**
 * Habillage « gazette » : textes de l'en-tête et blocs de la colonne latérale
 * de l'espace famille (petites annonces, courrier des lecteurs).
 *
 * Rien ici ne change les droits d'accès : on réutilise les fonctions du
 * calendrier et du tchat, qui filtrent déjà selon la personne connectée.
 */

declare(strict_types=1);

/** Devise affichée sous le titre : réglage « devise », sinon le sous-titre du site. */
function gazette_devise(): string
{
    $devise = trim(site_setting('devise'));
    return $devise !== '' ? $devise : trim(site_setting('subtitle'));
}

/**
 * Numéro de l'édition, à droite sous le titre : « N° 44 — 2026 ».
 * C'est le numéro de la semaine (norme ISO), suivi de l'année : il change
 * chaque semaine sans rien demander à personne.
 */
function gazette_numero(?int $horodatage = null): string
{
    $quand = $horodatage ?? time();
    return 'N° ' . date('W', $quand) . ' — ' . date('Y', $quand);
}

/** Année de fondation (« Fondée en 1889 »), vide si non renseignée. */
function gazette_fondee(): string
{
    $annee = trim(site_setting('fondee'));
    return preg_match('/^\d{4}$/', $annee) === 1 ? $annee : '';
}

/**
 * Les prochains anniversaires et événements de l'arbre (30 jours à venir),
 * pour les « petites annonces ».
 *
 * @return list<array{jour:int, mois:int, texte:string, lien:string}>
 */
function gazette_annonces(int $limite = 5): array
{
    // Les événements sont calculés une seule fois, dans calendrier.php, avec
    // les droits de la personne connectée : ici, on ne fait que les écrire.
    return array_map(
        static function (array $evenement): array {
            $ans = (int) $evenement['ans'];
            $texte = match ($evenement['genre']) {
                'mariage'   => 'MARIAGE. ' . $evenement['nom'] . ' : ' . $ans . ' an' . ($ans > 1 ? 's' : '') . ' de mariage.',
                'naissance' => 'ANNIVERSAIRE. ' . $evenement['nom'] . ' aurait ' . $ans . ' ans.',
                default     => 'SOUVENIR. ' . $evenement['nom'] . ' nous quittait il y a ' . $ans . ' ans.',
            };
            return [
                'jour'  => (int) $evenement['jour'],
                'mois'  => (int) $evenement['mois'],
                'texte' => $texte,
                'lien'  => (string) $evenement['lien'],
            ];
        },
        calendrier_prochains(30, $limite)
    );
}

/**
 * Derniers messages du tchat, présentés comme un « courrier des lecteurs ».
 * Vide si le tchat est fermé.
 *
 * @return list<array>
 */
function gazette_courrier(int $limite = 3): array
{
    if (!tchat_actif() || !is_logged_in()) {
        return [];
    }
    try {
        return tchat_messages($limite);
    } catch (Throwable) {
        return [];
    }
}

/** Le fichier de la police gothique du titre est-il présent ? (sinon repli sur Old Standard TT) */
function gazette_police_titre(): bool
{
    $dossier = HDF_ROOT . '/public/assets/fonts/';
    return is_file($dossier . 'UnifrakturMaguntia.woff2') || is_file($dossier . 'UnifrakturMaguntia.woff');
}
