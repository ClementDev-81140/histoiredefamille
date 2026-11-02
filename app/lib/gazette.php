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
    $aujourdhui = new DateTimeImmutable('today');
    $fin        = $aujourdhui->modify('+30 days');
    $annonces   = [];

    $mois = [(int) $aujourdhui->format('n')];
    if ((int) $fin->format('n') !== $mois[0]) {
        $mois[] = (int) $fin->format('n');
    }

    foreach ($mois as $m) {
        foreach (calendrier_evenements($m) as $jour => $liste) {
            // Année courante ou suivante selon le mois (passage décembre → janvier).
            $annee = $m < (int) $aujourdhui->format('n') ? (int) $aujourdhui->format('Y') + 1 : (int) $aujourdhui->format('Y');
            try {
                $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $annee, $m, (int) $jour));
            } catch (Throwable) {
                continue;
            }
            if ($date < $aujourdhui || $date > $fin) {
                continue;
            }
            foreach ($liste as $evenement) {
                $ans = $annee - (int) $evenement['annee'];
                if ($evenement['genre'] === 'mariage') {
                    $nom   = person_full_name($evenement['epoux']) . ' et ' . person_full_name($evenement['epouse']);
                    $texte = 'MARIAGE. ' . $nom . ' : ' . $ans . ' an' . ($ans > 1 ? 's' : '') . ' de mariage.';
                    $lien  = '/arbre/' . (int) $evenement['epoux']['id'];
                } else {
                    $nom   = person_full_name($evenement['personne']);
                    $lien  = '/arbre/' . (int) $evenement['personne']['id'];
                    $texte = $evenement['genre'] === 'naissance'
                        ? 'ANNIVERSAIRE. ' . $nom . ' aurait ' . $ans . ' ans.'
                        : 'SOUVENIR. ' . $nom . ' nous quittait il y a ' . $ans . ' ans.';
                }
                $annonces[] = ['date' => $date, 'texte' => $texte, 'lien' => $lien];
            }
        }
    }

    usort($annonces, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

    return array_map(
        static fn (array $a): array => [
            'jour'  => (int) $a['date']->format('j'),
            'mois'  => (int) $a['date']->format('n'),
            'texte' => $a['texte'],
            'lien'  => $a['lien'],
        ],
        array_slice($annonces, 0, $limite)
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
    return is_file(HDF_ROOT . '/public/assets/fonts/UnifrakturMaguntia.woff2');
}
