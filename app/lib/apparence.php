<?php
/**
 * Apparence du journal : les petites lignes de la « une », la palette de
 * couleurs et la feuille de style /theme.css qui en découle.
 *
 * Règle d'or : tant que l'administrateur n'a rien changé, tout retombe sur les
 * valeurs d'aujourd'hui (aucun réglage « apparence_* » en base), et le site
 * s'affiche exactement comme avant. Les réglages ne sont lus qu'une fois par
 * page (cache mémoire) : le Pi 3B+ a 1 Go de mémoire, on ne fait pas 30
 * requêtes pour afficher un en-tête.
 *
 * Tout ce qui vient de la saisie est du TEXTE brut (jamais du HTML), nettoyé
 * et borné. Les couleurs sont des #RRGGBB strictement validés : rien d'autre
 * ne peut entrer dans /theme.css, donc aucune injection CSS n'est possible.
 */

declare(strict_types=1);

/** Longueur maximale de chaque texte réglable (caractères). */
const APPARENCE_LONGUEURS = [
    'bandeau_milieu' => 80,
    'bandeau_droite' => 80,
    'numero_texte'   => 40,
    'pied'           => 200,
    'accueil'        => 200,
    'titre_annonces' => 40,
    'titre_courrier' => 40,
    'titre_editions' => 40,
    'titre_acces'    => 40,
    'titre_chiffres' => 40,
    'titre_une'      => 40,
    'titre_lire'     => 40,
    // Encarts de la gazette du mois (lot 2).
    'titre_gazette_articles'    => 40,
    'titre_gazette_photos'      => 40,
    'titre_gazette_videos'      => 40,
    'titre_gazette_temoignages' => 40,
    'titre_gazette_recettes'    => 40,
    'titre_gazette_personnes'   => 40,
];

/** Textes par défaut quand le champ est vide (ceux d'aujourd'hui). */
const APPARENCE_TEXTES = [
    'bandeau_milieu' => 'Édition des familles réunies',
    'bandeau_droite' => 'Prix : un sourire',
    'titre_annonces' => 'Petites annonces',
    'titre_courrier' => 'Courrier des lecteurs',
    'titre_editions' => 'Les éditions',
    'titre_acces'    => 'Accès rapides',
    'titre_chiffres' => 'En chiffres',
    'titre_une'      => 'À la une',
    'titre_lire'     => 'À lire aussi',
    'titre_gazette_articles'    => 'Les articles du mois',
    'titre_gazette_photos'      => 'Photos et albums',
    'titre_gazette_videos'      => 'Vidéos',
    'titre_gazette_temoignages' => 'Témoignages sonores',
    'titre_gazette_recettes'    => 'Recettes',
    'titre_gazette_personnes'   => 'Nouvelles personnes',
];

/** Pied de page et phrase d'accueil par défaut. */
const APPARENCE_PIED_DEFAUT = 'Site privé de famille. Les documents sont protégés et accessibles uniquement selon les droits définis par les administrateurs.';

/**
 * Description du site livrée par défaut (page d'accueil et référencement).
 *
 * Elle ne parle plus de l'arbre généalogique : un visiteur ne le voit pas.
 */
const APPARENCE_DESCRIPTION_DEFAUT = 'Les souvenirs de la famille : documents, photos, récits et nouvelles.';

/**
 * Ancienne description par défaut, livrée jusqu'à la version 1.25.0.
 *
 * Les sites installés avant gardent ce texte dans « Réglages » ou dans
 * `config.local.php` : on le reconnaît pour afficher la nouvelle phrase,
 * sans jamais toucher à une description écrite par un administrateur.
 */
const APPARENCE_DESCRIPTION_ANCIENNE = 'Documents, photos, vidéos et arbre généalogique de la famille.';

/** Modes de numéro d'édition. */
const APPARENCE_NUMERO_MODES = [
    'auto'  => 'Automatique (numéro de la semaine)',
    'libre' => 'Texte libre',
    'aucun' => 'Aucun numéro',
];

/** Modes de couleurs. */
const APPARENCE_COULEURS_MODES = [
    'papier' => 'Papier ancien (par défaut)',
    'bleu'   => 'Bleu d’origine',
    'perso'  => 'Couleurs personnalisées',
];

/** Couleurs de base du mode « Papier ancien » (le site d'aujourd'hui). */
const APPARENCE_PAPIER = [
    'fond'    => '#f3e7ce',
    'texte'   => '#1d140d',
    'accent'  => '#8f3126',
    'bordure' => '#362c24',
];

/** Couleurs de base du mode « Bleu d'origine » (l'ancienne palette). */
const APPARENCE_BLEU = [
    'fond'    => '#f6f5f2',
    'texte'   => '#1d1d1b',
    'accent'  => '#2b4a68',
    'bordure' => '#dcd8d0',
];

/** Contrastes minimums (WCAG AA renforcé pour le texte principal). */
const APPARENCE_CONTRASTES = [
    'texte'      => 7.0,
    'texte-doux' => 4.5,
    'accent'     => 4.5,
    'sur-accent' => 4.5,
];

/** Libellés des contrastes, en français simple. */
const APPARENCE_CONTRASTES_LIBELLES = [
    'texte'      => 'Texte sur le papier',
    'texte-doux' => 'Texte gris (date, mentions) sur le papier',
    'accent'     => 'Rouge (liens, titres) sur le papier',
    'sur-accent' => 'Texte des boutons sur le rouge',
];

/**
 * Réglages par défaut. Une valeur vide veut dire « comme aujourd'hui » :
 * c'est ce qui garantit un site identique tant que rien n'est enregistré.
 *
 * @return array<string, string>
 */
function apparence_defauts(): array
{
    $defauts = [
        'numero_mode'     => 'auto',
        'numero_texte'    => '',
        'pied'            => '',
        'accueil'         => '',
        'montrer_bandeau' => '1',
        'montrer_devise'  => '1',
        'montrer_fondee'  => '1',
        'montrer_numero'  => '1',
        'gothique'        => '1',
        'couleurs_mode'   => 'papier',
        'couleur_fond'    => '',
        'couleur_texte'   => '',
        'couleur_accent'  => '',
        'couleur_bordure' => '',
        'version'         => '1',
    ];

    foreach (array_keys(APPARENCE_LONGUEURS) as $cle) {
        $defauts[$cle] = '';
    }

    return $defauts;
}

/**
 * Réglages bruts lus en base (une seule lecture par page).
 *
 * @param bool $rafraichir relit la base (tests automatiques)
 * @return array<string, string>
 */
function apparence_brut(bool $rafraichir = false): array
{
    static $cache = null;
    if ($cache !== null && !$rafraichir) {
        return $cache;
    }

    $valeurs = apparence_defauts();
    foreach (array_keys($valeurs) as $cle) {
        $valeur = site_setting('apparence_' . $cle);
        if ($valeur !== '') {
            $valeurs[$cle] = $valeur;
        }
    }

    $cache = $valeurs;
    return $cache;
}

/**
 * Nettoie un texte réglable : pas de HTML (il sera de toute façon échappé à
 * l'affichage), pas de retour à la ligne, pas de caractère de contrôle, pas
 * d'espace en trop, longueur bornée.
 */
function apparence_nettoyer(mixed $valeur, int $max): string
{
    if (!is_string($valeur) && !is_int($valeur) && !is_float($valeur)) {
        return '';
    }

    $texte = (string) $valeur;
    // Retours à la ligne, tabulations et caractères de contrôle : supprimés.
    $texte = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $texte);
    // Espaces multiples (dont l'espace insécable et l'espace fine) : un seul.
    $texte = (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $texte);
    $texte = trim($texte);

    if ($max > 0 && mb_strlen($texte, 'UTF-8') > $max) {
        $texte = rtrim(mb_substr($texte, 0, $max, 'UTF-8'));
    }

    return $texte;
}

/**
 * Texte réglable prêt à afficher : la saisie si elle existe, sinon le texte
 * d'aujourd'hui (jamais de zone vide inattendue).
 */
function apparence_texte(string $cle, ?string $defaut = null): string
{
    $brut = apparence_brut()[$cle] ?? '';
    $valeur = apparence_nettoyer($brut, APPARENCE_LONGUEURS[$cle] ?? 200);

    if ($valeur !== '') {
        return $valeur;
    }

    return $defaut ?? (APPARENCE_TEXTES[$cle] ?? '');
}

/**
 * Titre d'un encart du journal (renommable ; vide = nom par défaut).
 *
 * @param string|null $defaut nom de secours quand aucun titre n'est réglé
 */
function apparence_titre_encart(string $cle, ?string $defaut = null): string
{
    return apparence_texte('titre_' . $cle, $defaut);
}

/** Case à cocher : absente ou vide = valeur par défaut (affiché). */
function apparence_actif(string $cle): bool
{
    $valeur = apparence_brut()[$cle] ?? '';
    return $valeur === '' ? true : $valeur === '1';
}

/** Mode du numéro d'édition : auto, libre ou aucun. */
function apparence_numero_mode(): string
{
    $mode = apparence_brut()['numero_mode'] ?? 'auto';
    return array_key_exists($mode, APPARENCE_NUMERO_MODES) ? $mode : 'auto';
}

/**
 * Numéro d'édition affiché : automatique (numéro de semaine), texte libre,
 * ou rien du tout.
 */
function apparence_numero(?int $horodatage = null): string
{
    if (!apparence_actif('montrer_numero')) {
        return '';
    }

    return match (apparence_numero_mode()) {
        'aucun' => '',
        'libre' => apparence_texte('numero_texte') !== ''
            ? apparence_texte('numero_texte')
            : gazette_numero($horodatage),
        default => gazette_numero($horodatage),
    };
}

/** Pied de page (texte libre, sinon texte d'aujourd'hui). */
function apparence_pied(): string
{
    return apparence_texte('pied', APPARENCE_PIED_DEFAUT);
}

/**
 * Description du site, telle qu'elle doit apparaître à un visiteur.
 *
 * Une description écrite par un administrateur est respectée telle quelle.
 * Vide, elle reprend la phrase livrée avec le site ; si c'est encore
 * l'ancienne phrase par défaut (celle qui mentionnait l'arbre), la nouvelle
 * prend sa place — un visiteur ne doit plus lire cette mention.
 */
function apparence_description_site(): string
{
    $texte = trim((string) site_setting('description'));

    if ($texte === '' || $texte === APPARENCE_DESCRIPTION_ANCIENNE) {
        $config = trim((string) config('site.description', ''));
        if ($config !== '' && $config !== APPARENCE_DESCRIPTION_ANCIENNE) {
            return $config;
        }
        return APPARENCE_DESCRIPTION_DEFAUT;
    }

    return $texte;
}

/** Phrase d'accueil de la page publique (vide = description du site). */
function apparence_accueil_phrase(): string
{
    $phrase = apparence_nettoyer(apparence_brut()['accueil'] ?? '', APPARENCE_LONGUEURS['accueil']);
    return $phrase !== '' ? $phrase : apparence_description_site();
}

/** Titre en gothique ? (décoché, repli Old Standard TT en petites capitales) */
function apparence_gothique(): bool
{
    return apparence_actif('gothique');
}

/** Vrai si la police gothique peut être utilisée (case cochée + fichier présent). */
function apparence_gothique_disponible(): bool
{
    return apparence_gothique() && gazette_police_titre();
}

// ---------------------------------------------------------------------------
// Couleurs : validation, contrastes, dérivation
// ---------------------------------------------------------------------------

/** Une couleur #RRGGBB ? (trois ou six chiffres hexadécimaux, rien d'autre) */
function apparence_hex_valide(mixed $valeur): ?string
{
    if (!is_string($valeur)) {
        return null;
    }

    $valeur = strtolower(trim($valeur));
    if ($valeur !== '' && $valeur[0] !== '#') {
        $valeur = '#' . $valeur;
    }

    if (preg_match('/^#([0-9a-f]{6})$/', $valeur, $trouve) === 1) {
        return '#' . $trouve[1];
    }
    if (preg_match('/^#([0-9a-f]{3})$/', $valeur, $trouve) === 1) {
        [$r, $g, $b] = str_split($trouve[1]);
        return '#' . $r . $r . $g . $g . $b . $b;
    }

    return null;
}

/**
 * Composantes d'une couleur #RRGGBB, bornées.
 *
 * @return array{0:int,1:int,2:int}
 */
function apparence_rgb(string $hex): array
{
    $hex = apparence_hex_valide($hex) ?? '#000000';
    return [
        (int) hexdec(substr($hex, 1, 2)),
        (int) hexdec(substr($hex, 3, 2)),
        (int) hexdec(substr($hex, 5, 2)),
    ];
}

/** Couleur #RRGGBB à partir de trois composantes. */
function apparence_hex(int $r, int $g, int $b): string
{
    $borne = static fn (int $v): int => max(0, min(255, $v));
    return sprintf('#%02x%02x%02x', $borne($r), $borne($g), $borne($b));
}

/**
 * Mélange deux couleurs : 0 % = la première, 100 % = la seconde.
 * Un pourcentage négatif éloigne de la seconde (utile pour éclaircir).
 */
function apparence_melange(string $a, string $b, float $pourcent): string
{
    $pourcent = max(-100.0, min(200.0, $pourcent)) / 100.0;
    [$r1, $g1, $b1] = apparence_rgb($a);
    [$r2, $g2, $b2] = apparence_rgb($b);

    return apparence_hex(
        (int) round($r1 + ($r2 - $r1) * $pourcent),
        (int) round($g1 + ($g2 - $g1) * $pourcent),
        (int) round($b1 + ($b2 - $b1) * $pourcent)
    );
}

/** Luminance relative WCAG d'une couleur. */
function apparence_luminance(string $couleur): float
{
    $canal = static function (int $valeur): float {
        $v = $valeur / 255;
        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    };

    [$r, $g, $b] = apparence_rgb($couleur);
    return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
}

/** Contraste WCAG entre deux couleurs (1:1 à 21:1). */
function apparence_contraste(string $a, string $b): float
{
    $la = apparence_luminance($a);
    $lb = apparence_luminance($b);
    $clair = max($la, $lb);
    $sombre = min($la, $lb);

    return round(($clair + 0.05) / ($sombre + 0.05), 2);
}

/** Le fond est-il clair ? (sert à savoir dans quel sens éclaircir) */
function apparence_fond_clair(string $fond): bool
{
    return apparence_luminance($fond) >= 0.35;
}

/**
 * Rapproche doucement une couleur du contraste demandé avec son fond, en
 * l'éloignant par petits pas (1 %) : l'ajustement le plus proche possible.
 */
function apparence_ajuster(string $couleur, string $fond, float $minimum): string
{
    $couleur = apparence_hex_valide($couleur) ?? '#000000';
    if (apparence_contraste($couleur, $fond) >= $minimum) {
        return $couleur;
    }

    $vers = apparence_fond_clair($fond) ? '#000000' : '#ffffff';

    for ($pas = 1; $pas <= 100; $pas++) {
        $essai = apparence_melange($couleur, $vers, $pas);
        if (apparence_contraste($essai, $fond) >= $minimum) {
            return $essai;
        }
    }

    return $vers;
}

/**
 * Le texte qui se détache le mieux d'un fond (blanc, noir, ou le papier).
 * On essaie d'abord le blanc et le noir, puis on ajuste si aucun ne suffit.
 */
function apparence_texte_sur(string $fond, float $minimum = 4.5): string
{
    $blanc = apparence_contraste('#ffffff', $fond) >= $minimum ? '#ffffff' : null;
    $noir  = apparence_contraste('#000000', $fond) >= $minimum ? '#000000' : null;

    if ($blanc !== null && $noir !== null) {
        // Les deux conviennent : on prend celui qui tranche le plus.
        return apparence_contraste('#ffffff', $fond) >= apparence_contraste('#000000', $fond)
            ? '#ffffff'
            : '#000000';
    }

    return $blanc ?? $noir ?? apparence_ajuster('#ffffff', $fond, $minimum);
}

/**
 * Palette « papier ancien » : les valeurs d'aujourd'hui, une par une.
 * Ce sont aussi les valeurs par défaut écrites dans la fin de style.css.
 *
 * @return array<string, string>
 */
function apparence_palette_papier(): array
{
    return [
        'fond'               => '#f3e7ce',
        'surface'            => '#f8edd8',
        'texte'              => '#1d140d',
        'texte-doux'         => '#5a493d',
        'accent'             => '#8f3126',
        'accent-fonce'       => '#6e231a',
        'accent-clair'       => '#e6d5bb',
        'bordure'            => '#362c24',
        'sur-accent'         => '#f3e7ce',
        'sur-encre'          => '#ffffff',
        'sur-voile'          => '#ffffff',
        'voile'              => 'rgba(0, 0, 0, 0.26)',
        'voile-fort'         => 'rgba(0, 0, 0, 0.72)',
        'focus-anneau'       => 'rgba(143, 49, 38, 0.18)',
        'erreur-voile'       => 'rgba(179, 36, 31, 0.07)',
        'panneau-public-bordure' => '#2f6b46',
        'info-fond'          => '#ecdcc0',
        'info-texte'         => '#4a3526',
        'info-bordure'       => '#b89c78',
        'info-bordure-2'     => '#c3d5e5',
        'succes-fond'        => '#e4ead2',
        'succes-texte'       => '#40501f',
        'succes-bordure'     => '#b3bf95',
        'succes-bordure-2'   => '#b9d8c4',
        'erreur-fond'        => '#f4d9cf',
        'erreur-texte'       => '#8d1a16',
        'erreur-bordure'     => '#d3a596',
        'erreur-bordure-2'   => '#e5bdb8',
        'attention-fond'     => '#f3edda',
        'attention-fond-2'   => '#f0e3c4',
        'attention-texte'    => '#6b5518',
        'attention-bordure'  => '#ddce9f',
        'attention-bordure-2' => '#c8b083',
        'alerte'             => '#b3241f',
        'danger-fonce'       => '#8d1a16',
        'danger-fonce-2'     => '#6f1f19',
        'badge-prive-fond'   => '#e7dcc9',
        'badge-prive-bordure' => '#b9a68c',
        'prive-texte'        => '#55443a',
        'note-bordure'       => '#b89c78',
        'papier-clair'       => '#f5e9d2',
        'papier-moyen'       => '#e8dcc4',
        'papier-fonce'       => '#e7dcc9',
        'papier-blanc'       => '#fdf6e6',
        'tableau-fond'       => '#e7dcc9',
        'tableau-fond-doux'  => '#efece6',
        'etiquette-fond'     => '#efe6d2',
        'code-fond'          => '#24292f',
        'code-texte'         => '#f0f0f0',
        'code-fond-doux'     => '#efede8',
        'ligne-survol'       => '#f5e9d2',
        'desactive-fond'     => '#f4f2ee',
        'desactive-texte'    => '#b3aea5',
        'surface-douce'      => '#f2f0eb',
        'calendrier-vide'    => '#f5e9d2',
        'calendrier-mariage' => '#8a6f4b',
        'apercu-fond'        => '#111111',
        'apercu-texte'       => '#f2f0eb',
        'apercu-document'    => '#e9e6df',
        'bordure-douce'      => '#b9b3a8',
        'plaque-1'           => '#d7af88',
        'plaque-2'           => '#704b36',
        'plaque-3'           => '#a57a5a',
        'plaque-4'           => '#c9a07a',
        'plaque-5'           => '#6f4c37',
        'plaque-lettre'      => 'rgba(243, 231, 206, 0.42)',
        'halo-1'             => 'rgba(215, 184, 148, 0.30)',
        'halo-2'             => 'rgba(208, 164, 129, 0.30)',
        'ombre-page'         => 'rgba(148, 102, 64, 0.30)',
        'ombre-panneau'      => 'rgba(148, 102, 64, 0.35)',
        'filet-papier'       => 'rgba(54, 44, 36, 0.35)',
        'impression-fond'    => '#ffffff',
        'impression-texte'   => '#000000',
    ];
}

/**
 * Palette « bleu d'origine » : l'ancienne palette, étendue aux variables
 * nouvelles (les effets de papier sont neutralisés).
 *
 * @return array<string, string>
 */
function apparence_palette_bleu(): array
{
    $palette = apparence_palette_papier();

    $bleu = [
        'fond'          => '#f6f5f2',
        'surface'       => '#ffffff',
        'texte'         => '#1d1d1b',
        'texte-doux'    => '#5d5a55',
        'accent'        => '#2b4a68',
        'accent-fonce'  => '#1d3349',
        'accent-clair'  => '#e9eff5',
        'bordure'       => '#dcd8d0',
        'sur-accent'    => '#ffffff',
        'sur-encre'     => '#ffffff',
        'sur-voile'     => '#ffffff',
        'voile'         => 'rgba(0, 0, 0, 0.26)',
        'voile-fort'    => 'rgba(0, 0, 0, 0.72)',
        'focus-anneau'  => 'rgba(43, 74, 104, 0.18)',
        'erreur-voile'  => 'rgba(179, 36, 31, 0.07)',
        'panneau-public-bordure' => '#2f6b46',
        'info-fond'     => '#eaf1f7',
        'info-texte'    => '#2c4a68',
        'info-bordure'  => '#c3d5e5',
        'info-bordure-2' => '#bfd2e3',
        'succes-fond'   => '#e7f2ea',
        'succes-texte'  => '#235c39',
        'succes-bordure' => '#b9d8c4',
        'succes-bordure-2' => '#b3bf95',
        'erreur-fond'   => '#fbeae8',
        'erreur-texte'  => '#8b2a22',
        'erreur-bordure' => '#e5bdb8',
        'erreur-bordure-2' => '#d3a596',
        'attention-fond' => '#f3edda',
        'attention-fond-2' => '#f0e3c4',
        'attention-texte' => '#6b5518',
        'attention-bordure' => '#ddce9f',
        'attention-bordure-2' => '#c8b083',
        'alerte'        => '#b3241f',
        'danger-fonce'  => '#8b2a22',
        'danger-fonce-2' => '#6f1f19',
        'note-bordure'  => '#dcd8d0',
        'papier-clair'  => '#f6f5f2',
        'papier-moyen'  => '#efede8',
        'papier-fonce'  => '#eceae5',
        'papier-blanc'  => '#ffffff',
        'tableau-fond'  => '#eceae5',
        'tableau-fond-doux' => '#f1eeea',
        'etiquette-fond' => '#efede8',
        'ligne-survol'  => '#faf9f6',
        'desactive-fond' => '#f4f2ee',
        'desactive-texte' => '#b3aea5',
        'surface-douce' => '#f2f0eb',
        'calendrier-vide' => '#f8f7f4',
        'calendrier-mariage' => '#2b4a68',
        'apercu-document' => '#e9e6df',
        'bordure-douce' => '#b9b3a8',
        'plaque-1'      => '#dbe3ea',
        'plaque-2'      => '#2b4a68',
        'plaque-3'      => '#9fb4c7',
        'plaque-4'      => '#c9d6e2',
        'plaque-5'      => '#1d3349',
        'plaque-lettre' => 'rgba(255, 255, 255, 0.42)',
        // Aucun halo, aucune ombre de papier : fond uni.
        'halo-1'        => 'transparent',
        'halo-2'        => 'transparent',
        'ombre-page'    => 'transparent',
        'ombre-panneau' => 'transparent',
        'filet-papier'  => 'rgba(29, 29, 27, 0.15)',
        'badge-prive-fond' => '#f1eeea',
        'badge-prive-bordure' => '#cfc7bc',
        'prive-texte'   => '#5d5a55',
    ];

    return array_merge($palette, $bleu);
}

/**
 * Palette complète, quelles que soient les couleurs choisies : tout est
 * calculé à partir de quatre couleurs de base (papier, texte, accent, encre).
 *
 * @param array{fond:string, texte:string, accent:string, bordure:string} $base
 * @return array<string, string>
 */
function apparence_deriver(array $base): array
{
    $fond    = apparence_hex_valide($base['fond'] ?? '') ?? APPARENCE_PAPIER['fond'];
    $texte   = apparence_hex_valide($base['texte'] ?? '') ?? APPARENCE_PAPIER['texte'];
    $accent  = apparence_hex_valide($base['accent'] ?? '') ?? APPARENCE_PAPIER['accent'];
    $bordure = apparence_hex_valide($base['bordure'] ?? '') ?? APPARENCE_PAPIER['bordure'];

    // Le texte et l'accent sont ceux que l'administrateur a choisis : s'ils
    // ne sont pas assez contrastés, le site refuse l'enregistrement (et
    // propose « Corriger pour moi »). On ne les modifie donc pas en silence.
    // Les couleurs dérivées (gris, textes des messages, des boutons), elles,
    // sont ajustées automatiquement au minimum lisible.
    $clair = apparence_fond_clair($fond);

    /** Teinte de papier : plus sombre sur fond clair, plus claire sur fond sombre. */
    $teinte = static fn (float $pourcent): string => $clair
        ? apparence_melange($fond, '#ffffff', -$pourcent)
        : apparence_melange($fond, '#ffffff', $pourcent * 0.75);

    /** Mélange d'une couleur vers le papier (fond de message, de badge). */
    $tinte = static fn (string $couleur, float $pourcent): string
        => apparence_melange($couleur, $fond, $clair ? $pourcent : $pourcent * 0.82);

    $doux = apparence_ajuster(apparence_melange($texte, $fond, 35), $fond, APPARENCE_CONTRASTES['texte-doux']);

    // Fonds colorés d'abord, puis les textes posés dessus : chacun doit
    // rester lisible (4,5 pour 1), même sur un thème sombre.
    $infoFond      = $tinte($accent, 88);
    $infoTexte     = apparence_ajuster(apparence_melange($accent, $texte, 30), $infoFond, 4.5);
    $succesFond    = $tinte('#2f7d4d', 88);
    $succesTexte   = apparence_ajuster('#2f7d4d', $succesFond, 4.5);
    $erreurFond    = $tinte('#b3322a', 88);
    $erreurTexte   = apparence_ajuster('#b3322a', $erreurFond, 4.5);
    $attentionFond = $tinte('#8a6a1e', 88);
    $attFond2      = $tinte('#8a6a1e', 82);
    $attentionTexte = apparence_ajuster('#8a6a1e', $attFond2, 4.5);
    $priveFond     = $teinte(6);
    $priveTexte    = apparence_ajuster($doux, $priveFond, 4.5);
    $desactiveFond = $teinte(5);
    $desactiveTexte = apparence_ajuster(apparence_melange($doux, $fond, 55), $desactiveFond, 4.5);

    // Texte des boutons : le blanc ou le noir, celui qui tranche le plus.
    $surAccent = apparence_texte_sur($accent, APPARENCE_CONTRASTES['sur-accent']);
    $surEncre  = apparence_texte_sur($texte);

    // Survol d'un bouton : on s'éloigne de la couleur de base, mais le texte
    // du bouton doit rester lisible sur cette nouvelle teinte.
    $accentFonce = $clair
        ? apparence_melange($accent, '#000000', 22)
        : apparence_melange($accent, '#ffffff', 20);
    if (apparence_contraste($surAccent, $accentFonce) < APPARENCE_CONTRASTES['sur-accent']) {
        $vers = $clair ? '#000000' : '#ffffff';
        for ($pas = 20; $pas <= 100; $pas++) {
            $essai = apparence_melange($accent, $vers, $pas);
            if (apparence_contraste($surAccent, $essai) >= APPARENCE_CONTRASTES['sur-accent']) {
                $accentFonce = $essai;
                break;
            }
        }
    }

    // Texte sur la teinte claire de l'accent (badge « famille »).
    $accentClair = apparence_melange($accent, $fond, $clair ? 82 : 80);
    $accentSurClair = apparence_ajuster($accentFonce, $accentClair, 4.5);

    // Plaque de repli des articles (photo absente) et plaques sombres.
    $apercuFond  = apparence_melange('#000000', $texte, 12);
    $apercuTexte = apparence_texte_sur($apercuFond);

    $codeFond  = $teinte(9);
    $codeTexte = apparence_ajuster($texte, $codeFond, 7.0);

    return [
        'fond'         => $fond,
        'surface'      => $clair ? apparence_melange($fond, '#ffffff', 30) : apparence_melange($fond, '#ffffff', 8),
        'texte'        => $texte,
        'texte-doux'   => $doux,
        'accent'       => $accent,
        'accent-fonce' => $accentFonce,
        'accent-clair' => $accentClair,
        'bordure'      => apparence_ajuster($bordure, $fond, 1.6),
        'sur-accent'   => $surAccent,
        'sur-encre'    => $surEncre,
        'sur-voile'    => '#ffffff',
        'voile'        => 'rgba(0, 0, 0, 0.26)',
        'voile-fort'   => 'rgba(0, 0, 0, 0.72)',
        'focus-anneau' => 'rgba(143, 49, 38, 0.18)',
        'erreur-voile' => 'rgba(179, 36, 31, 0.07)',
        'panneau-public-bordure' => $succesTexte,
        'info-fond'    => $infoFond,
        'info-texte'   => $infoTexte,
        'info-bordure' => $tinte($accent, 65),
        'info-bordure-2' => $tinte($accent, 60),
        'succes-fond'  => $succesFond,
        'succes-texte' => $succesTexte,
        'succes-bordure' => $tinte('#2f7d4d', 65),
        'succes-bordure-2' => $tinte('#2f7d4d', 60),
        'erreur-fond'  => $erreurFond,
        'erreur-texte' => $erreurTexte,
        'erreur-bordure' => $tinte('#b3322a', 65),
        'erreur-bordure-2' => $tinte('#b3322a', 60),
        'attention-fond' => $attentionFond,
        'attention-fond-2' => $attFond2,
        'attention-texte' => $attentionTexte,
        'attention-bordure' => $tinte('#8a6a1e', 65),
        'attention-bordure-2' => $tinte('#8a6a1e', 58),
        'alerte'       => $erreurTexte,
        'danger-fonce' => $erreurTexte,
        'danger-fonce-2' => $clair
            ? apparence_melange($erreurTexte, '#000000', 25)
            : apparence_melange($erreurTexte, '#ffffff', 22),
        'badge-prive-fond' => $priveFond,
        'badge-prive-bordure' => $teinte(22),
        'prive-texte'  => $priveTexte,
        'note-bordure' => $tinte($accent, 55),
        'papier-clair' => $clair ? apparence_melange($fond, '#ffffff', 20) : $teinte(4),
        'papier-moyen' => $teinte(8),
        'papier-fonce' => $teinte(10),
        'papier-blanc' => $clair ? apparence_melange($fond, '#ffffff', 35) : $teinte(3),
        'tableau-fond' => $teinte(10),
        'tableau-fond-doux' => $teinte(4),
        'etiquette-fond' => $teinte(5),
        'code-fond'    => $codeFond,
        'code-texte'   => $codeTexte,
        'code-fond-doux' => $teinte(6),
        'ligne-survol' => $clair ? apparence_melange($fond, '#ffffff', 30) : $teinte(6),
        'desactive-fond' => $desactiveFond,
        'desactive-texte' => $desactiveTexte,
        'surface-douce' => $teinte(4),
        'calendrier-vide' => $clair ? apparence_melange($fond, '#ffffff', 25) : $teinte(3),
        'calendrier-mariage' => apparence_melange($accent, $bordure, 45),
        'apercu-fond'  => $apercuFond,
        'apercu-texte' => $apercuTexte,
        'apercu-document' => $teinte(7),
        'bordure-douce' => $teinte(20),
        'plaque-1'     => $tinte($accent, 55),
        'plaque-2'     => apparence_melange($accent, $bordure, 40),
        'plaque-3'     => apparence_melange($accent, $bordure, 60),
        'plaque-4'     => $tinte($accent, 65),
        'plaque-5'     => $bordure,
        'plaque-lettre' => 'rgba(243, 231, 206, 0.42)',
        'halo-1'       => 'transparent',
        'halo-2'       => 'transparent',
        'ombre-page'   => 'transparent',
        'ombre-panneau' => 'transparent',
        'filet-papier' => 'rgba(54, 44, 36, 0.35)',
        'impression-fond' => '#ffffff',
        'impression-texte' => '#000000',
    ];
}

/** Mode de couleurs choisi (papier, bleu, perso). */
function apparence_mode_couleurs(): string
{
    $mode = apparence_brut()['couleurs_mode'] ?? 'papier';
    return array_key_exists($mode, APPARENCE_COULEURS_MODES) ? $mode : 'papier';
}

/**
 * Les quatre couleurs de base effectivement utilisées (saisie validée, sinon
 * celles du mode).
 *
 * @return array{fond:string, texte:string, accent:string, bordure:string}
 */
function apparence_couleurs(): array
{
    $brut = apparence_brut();
    $base = apparence_mode_couleurs() === 'bleu' ? APPARENCE_BLEU : APPARENCE_PAPIER;

    $couleurs = [];
    foreach (['fond', 'texte', 'accent', 'bordure'] as $cle) {
        $couleurs[$cle] = apparence_hex_valide($brut['couleur_' . $cle] ?? '') ?? $base[$cle];
    }

    return $couleurs;
}

/**
 * Palette complète du site, prête à être écrite dans une feuille de style.
 *
 * @return array<string, string>
 */
function apparence_variables(): array
{
    return match (apparence_mode_couleurs()) {
        'bleu'  => apparence_palette_bleu(),
        'perso' => apparence_deriver(apparence_couleurs()),
        default => apparence_palette_papier(),
    };
}

/**
 * Rapport de contraste, en français simple, pour l'affichage et les tests.
 *
 * @return list<array{cle:string, libelle:string, ratio:float, minimum:float, ok:bool}>
 */
function apparence_rapports(array $variables): array
{
    $rapports = [];
    foreach (APPARENCE_CONTRASTES as $cle => $minimum) {
        $ratio = match ($cle) {
            'texte'      => apparence_contraste($variables['texte'] ?? '#000000', $variables['fond'] ?? '#ffffff'),
            'texte-doux' => apparence_contraste($variables['texte-doux'] ?? '#000000', $variables['fond'] ?? '#ffffff'),
            'accent'     => apparence_contraste($variables['accent'] ?? '#000000', $variables['fond'] ?? '#ffffff'),
            'sur-accent' => apparence_contraste($variables['sur-accent'] ?? '#ffffff', $variables['accent'] ?? '#000000'),
            default      => 0.0,
        };

        $rapports[] = [
            'cle'     => $cle,
            'libelle' => APPARENCE_CONTRASTES_LIBELLES[$cle] ?? $cle,
            'ratio'   => $ratio,
            'minimum' => $minimum,
            'ok'      => $ratio >= $minimum,
        ];
    }

    return $rapports;
}

/**
 * Vérifie les quatre couleurs choisies : refus si un contraste est trop
 * faible, avec un message clair et la correction proposée.
 *
 * @param array{fond:string, texte:string, accent:string, bordure:string} $couleurs
 * @return list<string> messages d'erreur (vide = tout va bien)
 */
function apparence_verifier(array $couleurs): array
{
    $variables = apparence_deriver($couleurs);
    $erreurs   = [];

    $ecart = static function (float $ratio, float $minimum): string {
        // 7 s'écrit « 7 », 4,5 s'écrit « 4,5 » : on n'arrondit pas au-dessus.
        $mini = $minimum === floor($minimum)
            ? number_format($minimum, 0, ',', ' ')
            : number_format($minimum, 1, ',', ' ');
        return number_format($ratio, 1, ',', ' ') . ' pour 1 (il faut ' . $mini . ' pour 1)';
    };

    $ratioTexte = apparence_contraste($variables['texte'], $variables['fond']);
    if ($ratioTexte < APPARENCE_CONTRASTES['texte']) {
        $erreurs[] = 'Le texte choisi (' . $couleurs['texte'] . ') est trop proche du papier ('
            . $couleurs['fond'] . ') : contraste ' . $ecart($ratioTexte, APPARENCE_CONTRASTES['texte'])
            . '. Prenez un texte plus sombre ou un papier plus clair.';
    }

    $ratioAccent = apparence_contraste($variables['accent'], $variables['fond']);
    if ($ratioAccent < APPARENCE_CONTRASTES['accent']) {
        $erreurs[] = 'L’accent choisi (' . $couleurs['accent'] . ') est trop clair sur ce papier ('
            . $couleurs['fond'] . ') : contraste ' . $ecart($ratioAccent, APPARENCE_CONTRASTES['accent'])
            . '. Prenez un rouge (ou un bleu) plus foncé.';
    }

    $ratioSurAccent = apparence_contraste($variables['sur-accent'], $variables['accent']);
    if ($ratioSurAccent < APPARENCE_CONTRASTES['sur-accent']) {
        $erreurs[] = 'Le texte des boutons ne se détache pas assez de la couleur d’accent ('
            . $couleurs['accent'] . ') : contraste ' . $ecart($ratioSurAccent, APPARENCE_CONTRASTES['sur-accent'])
            . '. Prenez un accent plus foncé ou plus clair.';
    }

    return $erreurs;
}

/**
 * Correction automatique la plus proche : éloigne le texte et l'accent du
 * papier juste ce qu'il faut pour atteindre les contrastes demandés.
 *
 * @param array{fond:string, texte:string, accent:string, bordure:string} $couleurs
 * @return array{fond:string, texte:string, accent:string, bordure:string}
 */
function apparence_corriger(array $couleurs): array
{
    $fond    = apparence_hex_valide($couleurs['fond'] ?? '') ?? APPARENCE_PAPIER['fond'];
    $texte   = apparence_hex_valide($couleurs['texte'] ?? '') ?? APPARENCE_PAPIER['texte'];
    $accent  = apparence_hex_valide($couleurs['accent'] ?? '') ?? APPARENCE_PAPIER['accent'];
    $bordure = apparence_hex_valide($couleurs['bordure'] ?? '') ?? APPARENCE_PAPIER['bordure'];

    $texte  = apparence_ajuster($texte, $fond, APPARENCE_CONTRASTES['texte']);
    $accent = apparence_ajuster($accent, $fond, APPARENCE_CONTRASTES['accent']);

    // Si le texte des boutons ne tranche pas assez, on éloigne encore
    // l'accent (vers le sombre sur fond clair, vers la lumière sur fond
    // sombre) jusqu'à ce que le bouton redevienne lisible.
    if (apparence_contraste(apparence_texte_sur($accent), $accent) < APPARENCE_CONTRASTES['sur-accent']) {
        $vers = apparence_fond_clair($fond) ? '#000000' : '#ffffff';
        for ($pas = 1; $pas <= 100; $pas++) {
            $essai = apparence_melange($accent, $vers, $pas);
            if (apparence_contraste(apparence_texte_sur($essai), $essai) >= APPARENCE_CONTRASTES['sur-accent']) {
                $accent = $essai;
                break;
            }
        }
    }

    return ['fond' => $fond, 'texte' => $texte, 'accent' => $accent, 'bordure' => $bordure];
}

/** Numéro de version de la feuille de style (sert d'ETag, évite de recalculer). */
function apparence_version(): int
{
    return (int) (apparence_brut()['version'] ?? '1');
}

/**
 * Faut-il charger /theme.css sur cette page ?
 *
 * Avec la palette « Papier ancien », la feuille ne contient rien : on ne la
 * demande même pas au navigateur (une requête de moins sur le Raspberry Pi).
 */
function apparence_feuille_active(): bool
{
    return apparence_mode_couleurs() !== 'papier';
}

/** Incrémente la version après un enregistrement : le navigateur recharge /theme.css. */
function apparence_version_bump(): void
{
    $version = apparence_version() + 1;
    save_site_setting('apparence_version', (string) $version);
    apparence_brut(true);
}

/**
 * Feuille de style du thème, prête à être servie par /theme.css.
 *
 * Rien d'autre que des valeurs validées n'y entre : les couleurs sortent de
 * apparence_hex_valide() ou sont calculées par le site, jamais d'un texte
 * saisi. En mode « papier ancien » sans personnalisation, le fichier ne
 * contient aucune règle : le site est exactement celui d'aujourd'hui.
 */
function apparence_css(): string
{
    if (apparence_mode_couleurs() === 'papier') {
        return "/* Thème « papier ancien » : aucun changement, voir /assets/style.css. */\n";
    }

    $variables = apparence_variables();
    $lignes    = [];
    foreach ($variables as $cle => $valeur) {
        if (preg_match('/^(#[0-9a-f]{6}|rgba\([0-9 ,.]+\)|transparent)$/', $valeur) !== 1) {
            continue; // ceinture et bretelles : rien d'inattendu ne sort.
        }
        $lignes[] = '    --' . $cle . ': ' . $valeur . ';';
    }

    $css = "/* Feuille de style du thème, calculée par le site. Ne pas modifier. */\n";
    $css .= ":root {\n" . implode("\n", $lignes) . "\n}\n";

    if (apparence_mode_couleurs() === 'bleu') {
        $css .= "/* Bleu d'origine : fond uni, sans halo ni ombre de papier. */\n"
            . "html, body { background-color: var(--fond); background-image: none; box-shadow: none; }\n";
    } else {
        $css .= "/* Couleurs personnalisées : fond uni (les contrastes calculés sont ceux du papier). */\n"
            . "html, body { background-color: var(--fond); background-image: none; box-shadow: none; }\n";
    }

    return $css;
}

/**
 * Réglages à enregistrer depuis le formulaire d'apparence.
 * Validation stricte : renvoie [valeurs, erreurs].
 *
 * @param array<string, mixed> $saisie  champs du formulaire ($_POST)
 * @param string               $nom     nom du site (réglage partagé)
 * @param string               $devise  devise (réglage partagé)
 * @param string               $fondee  année de fondation (réglage partagé)
 * @return array{0: array<string, string>, 1: list<string>, 2: array<string,string>}
 *         (réglages nettoyés, messages d'erreur, couleurs corrigées proposées)
 */
function apparence_lire_saisie(array $saisie, string $nom, string $devise, string $fondee): array
{
    $valeurs = [];
    $erreurs = [];
    $annee   = (int) date('Y');

    foreach (array_keys(APPARENCE_LONGUEURS) as $cle) {
        $valeur = apparence_nettoyer($saisie[$cle] ?? '', APPARENCE_LONGUEURS[$cle]);
        // Une saisie trop longue est refusée plutôt que coupée en silence :
        // l'administrateur doit voir que son texte ne tient pas.
        $original = is_string($saisie[$cle] ?? null) ? trim((string) $saisie[$cle]) : '';
        if (mb_strlen($original) > APPARENCE_LONGUEURS[$cle]) {
            $erreurs[] = '« ' . (APPARENCE_TEXTES[$cle] ?? $cle) . ' » : '
                . APPARENCE_LONGUEURS[$cle] . ' caractères maximum.';
        }
        $valeurs[$cle] = $valeur;
    }

    $nom = apparence_nettoyer($nom, 100);
    if ($nom === '') {
        $erreurs[] = 'Le titre du site ne peut pas être vide.';
    }
    if (mb_strlen(trim($nom)) > 100) {
        $erreurs[] = 'Le titre du site est limité à 100 caractères.';
    }
    if (mb_strlen(trim($devise)) > 120) {
        $erreurs[] = 'La devise est limitée à 120 caractères.';
    }
    $devise = apparence_nettoyer($devise, 120);
    $fondee = apparence_nettoyer($fondee, 4);
    if ($fondee !== '' && (preg_match('/^\d{4}$/', $fondee) !== 1 || (int) $fondee < 1500 || (int) $fondee > $annee)) {
        $erreurs[] = '« Fondée en » doit être une année entre 1500 et ' . $annee . '.';
    }

    $mode = (string) ($saisie['numero_mode'] ?? 'auto');
    if (!array_key_exists($mode, APPARENCE_NUMERO_MODES)) {
        $mode = 'auto';
    }
    $valeurs['numero_mode'] = $mode;
    // Le titre, la devise et l'année de fondation sont des réglages déjà
    // existants (« Réglages du site ») : on ne les duplique pas.
    $valeurs['nom']    = $nom;
    $valeurs['devise'] = $devise;
    $valeurs['fondee'] = $fondee;

    if ($mode === 'libre' && $valeurs['numero_texte'] === '') {
        $erreurs[] = 'Numéro d’édition « Texte libre » : écrivez le texte à afficher (par exemple « N° 12 »).';
    }

    foreach (['montrer_bandeau', 'montrer_devise', 'montrer_fondee', 'montrer_numero', 'gothique'] as $cle) {
        $valeurs[$cle] = ($saisie[$cle] ?? '') === '1' ? '1' : '0';
    }

    $modeCouleurs = (string) ($saisie['couleurs_mode'] ?? 'papier');
    if (!array_key_exists($modeCouleurs, APPARENCE_COULEURS_MODES)) {
        $modeCouleurs = 'papier';
    }
    $valeurs['couleurs_mode'] = $modeCouleurs;

    $couleurs = [];
    foreach (['fond', 'texte', 'accent', 'bordure'] as $cle) {
        $champ = 'couleur_' . $cle;
        $brut  = trim((string) ($saisie[$champ] ?? ''));
        if ($brut === '') {
            $valeurs[$champ] = '';
            continue;
        }
        $hex = apparence_hex_valide($brut);
        if ($hex === null) {
            $erreurs[] = 'La couleur « ' . apparence_libelle_couleur($cle) . ' » n’est pas valide : écrivez #RRGGBB (par exemple #8f3126).';
            $valeurs[$champ] = '';
            continue;
        }
        $valeurs[$champ] = $hex;
        $couleurs[$cle]  = $hex;
    }

    if ($modeCouleurs === 'perso') {
        $completes = [];
        foreach (['fond', 'texte', 'accent', 'bordure'] as $cle) {
            $completes[$cle] = $couleurs[$cle]
                ?? apparence_hex_valide($valeurs['couleur_' . $cle] ?? '')
                ?? APPARENCE_PAPIER[$cle];
        }
        foreach (apparence_verifier($completes) as $message) {
            $erreurs[] = $message;
        }
        // La correction proposée est gardée pour le bouton « Corriger pour moi ».
        $corrigees = apparence_corriger($completes);
    } else {
        $corrigees = [];
    }

    return [$valeurs, $erreurs, $corrigees];
}

/** Nom français d'une des quatre couleurs. */
function apparence_libelle_couleur(string $cle): string
{
    return [
        'fond'    => 'papier',
        'texte'   => 'texte',
        'accent'  => 'accent',
        'bordure' => 'encre (bordure)',
    ][$cle] ?? $cle;
}

/** Enregistre les réglages d'apparence (les textes vides restent vides = défaut). */
function apparence_enregistrer(array $valeurs): void
{
    foreach ($valeurs as $cle => $valeur) {
        save_site_setting('apparence_' . $cle, (string) $valeur);
    }
    apparence_version_bump();
    apparence_brut(true);
}

/** Remet tout le lot A par défaut (textes, cases, numéro, couleurs). */
function apparence_retablir(): void
{
    db_exec("DELETE FROM settings WHERE key LIKE 'apparence\\_%' ESCAPE '\\'");
    // Le titre, la devise et l'année de fondation sont aussi dans le lot A :
    // ils repartent de la configuration du site.
    db_exec("DELETE FROM settings WHERE key IN ('name', 'devise', 'fondee')");
    // Les effacements ci-dessus ne passent pas par save_site_setting() :
    // on force la relecture des réglages.
    site_settings_oublier();
    save_site_setting('apparence_version', (string) (apparence_version() + 1));
    apparence_brut(true);
}
