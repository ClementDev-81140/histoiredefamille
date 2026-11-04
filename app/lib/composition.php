<?php
/**
 * Composition des pages et modèles d'article.
 *
 * Ce fichier répond à une seule question : « dans quel ordre, et lesquels ? »
 * pour les blocs de la page d'accueil publique (`/`) et de l'espace famille
 * (`/famille`). Les administrateurs peuvent masquer un bloc ou le faire monter
 * et descendre ; chacun reste dans sa colonne.
 *
 * Rien ici ne touche aux droits : un bloc masqué n'est pas calculé du tout
 * (l'économie est réelle), et un bloc affiché continue de filtrer avec
 * visibility.php comme avant.
 *
 * Tout est stocké dans la table `settings`, en JSON, et relu avec une
 * validation stricte : un JSON abîmé ou bricolé à la main redonne la
 * composition d'origine, jamais une page cassée.
 */

declare(strict_types=1);

/** Les pages dont la composition est réglable. */
function composition_pages(): array
{
    return [
        'public'  => 'Page d’accueil publique (/)',
        'famille' => 'Espace famille (/famille)',
    ];
}

/**
 * Les blocs d'une page, dans l'ordre d'origine, avec leur colonne et leur nom.
 *
 * `colonne` vaut « principale », « laterale » ou « pleine » (sous la une, sur
 * toute la largeur). Un bloc reste toujours dans la même colonne : les boutons
 * Monter / Descendre ne le font que glisser parmi ses voisins.
 *
 * @return array<string, array{nom:string, colonne:string, aide:string, fixe?:bool}>
 */
function composition_catalogue(string $page): array
{
    if ($page === 'public') {
        return [
            'entrees' => [
                'nom'     => 'Les deux entrées (Consulter, Se connecter)',
                'colonne' => 'hero',
                'aide'    => 'Toujours affichées : ce sont les portes d’entrée du site.',
                'fixe'    => true,
            ],
            'egalement' => [
                'nom'     => '« Également publié »',
                'colonne' => 'hero',
                'aide'    => 'Les autres rubriques, seulement si elles ont du contenu public.',
            ],
            'chiffres' => [
                'nom'     => 'Le site en chiffres',
                'colonne' => 'hero',
                'aide'    => 'Documents, pages et personnes visibles.',
            ],
            'reserve' => [
                'nom'     => 'Le mot sur l’espace réservé',
                'colonne' => 'hero',
                'aide'    => 'La phrase qui invite les familles à se connecter.',
            ],
            'une' => [
                'nom'     => 'À la une (les articles)',
                'colonne' => 'pleine',
                'aide'    => 'Les derniers articles publiés, le plus récent en grand.',
            ],
            'documents' => [
                'nom'     => 'Derniers documents',
                'colonne' => 'pleine',
                'aide'    => 'Les derniers documents et photos visibles par tous.',
            ],
            'lire' => [
                'nom'     => 'À lire (les pages d’information)',
                'colonne' => 'pleine',
                'aide'    => 'Les pages d’information publiques.',
            ],
        ];
    }

    return [
        'une' => [
            'nom'     => 'Article à la une',
            'colonne' => 'principale',
            'aide'    => 'Le dernier article, en grand.',
        ],
        'colonnes' => [
            'nom'     => 'Articles en colonnes',
            'colonne' => 'principale',
            'aide'    => 'Les articles suivants, en colonnes.',
        ],
        'annonces' => [
            'nom'     => 'Petites annonces',
            'colonne' => 'laterale',
            'aide'    => 'Anniversaires et souvenirs des trente prochains jours.',
        ],
        'editions' => [
            'nom'     => 'Les éditions',
            'colonne' => 'laterale',
            'aide'    => 'Le nombre d’articles par branche.',
        ],
        'courrier' => [
            'nom'     => 'Courrier des lecteurs',
            'colonne' => 'laterale',
            'aide'    => 'Les derniers messages du tchat (si le tchat est ouvert).',
        ],
        'acces' => [
            'nom'     => 'Accès rapides',
            'colonne' => 'laterale',
            'aide'    => 'Les liens vers les rubriques ouvertes à ce compte.',
        ],
        'chiffres' => [
            'nom'     => 'En chiffres',
            'colonne' => 'laterale',
            'aide'    => 'Documents, personnes et pages que ce compte peut voir.',
        ],
        'videos' => [
            'nom'     => 'Dernières vidéos',
            'colonne' => 'pleine',
            'aide'    => 'Les dernières vidéos visibles.',
        ],
        'albums' => [
            'nom'     => 'Albums photos',
            'colonne' => 'pleine',
            'aide'    => 'Les derniers albums visibles.',
        ],
        'pages' => [
            'nom'     => 'Pages d’information',
            'colonne' => 'pleine',
            'aide'    => 'Les pages d’information visibles.',
        ],
        'documents' => [
            'nom'     => 'Derniers documents',
            'colonne' => 'pleine',
            'aide'    => 'Les derniers documents visibles.',
        ],
        'personnes' => [
            'nom'     => 'Personnes récemment mises à jour',
            'colonne' => 'pleine',
            'aide'    => 'Quelques personnes de l’arbre.',
        ],
    ];
}

/** Nom lisible d'un bloc (« une » → « Article à la une »). */
function composition_nom(string $page, string $cle): string
{
    return composition_catalogue($page)[$cle]['nom'] ?? $cle;
}

/** Nom lisible des colonnes. */
function composition_colonnes_libelles(): array
{
    return [
        'hero'       => 'Dans l’en-tête de l’accueil',
        'principale' => 'Colonne principale',
        'laterale'   => 'Colonne de droite',
        'pleine'     => 'Sous la une, sur toute la largeur',
    ];
}

/**
 * Blocs d'une page regroupés par colonne, avec leur état.
 *
 * @return array<string, list<array{cle:string, nom:string, aide:string, actif:bool, fixe:bool}>>
 */
function composition_groupes(string $page): array
{
    $catalogue = composition_catalogue($page);
    $groupes   = [];

    foreach (composition_blocs($page, true) as $bloc) {
        $cle  = $bloc['cle'];
        $info = $catalogue[$cle] ?? null;
        if ($info === null) {
            continue;
        }
        $groupes[$info['colonne']][] = [
            'cle'   => $cle,
            'nom'   => $info['nom'],
            'aide'  => $info['aide'],
            'actif' => (bool) $bloc['actif'],
            'fixe'  => !empty($info['fixe']),
        ];
    }

    return $groupes;
}

/** Longueur de résumé choisie (court, moyen, long). */
function composition_resume_choisi(): string
{
    $valeur = site_setting('apparence_une_resume');
    return array_key_exists($valeur, COMPOSITION_RESUMES) ? $valeur : 'moyen';
}

/** Nom du réglage qui garde la composition d'une page. */
function composition_cle_reglage(string $page): string
{
    return $page === 'public' ? 'apparence_blocs_public' : 'apparence_blocs_famille';
}

/**
 * Composition par défaut : tous les blocs, dans l'ordre d'origine (celui du
 * site d'aujourd'hui).
 *
 * @return list<array{cle:string, actif:bool}>
 */
function composition_defauts(string $page): array
{
    $blocs = [];
    foreach (composition_catalogue($page) as $cle => $info) {
        $blocs[] = ['cle' => $cle, 'actif' => true];
    }
    return $blocs;
}

/**
 * Lit une composition enregistrée, en la validant.
 *
 * Règles : le JSON doit être une liste d'objets `{cle, actif}` ; une clé
 * inconnue est ignorée ; un doublon est ignoré ; un bloc absent reprend sa
 * place à la fin, affiché. Un JSON illisible redonne la composition
 * d'origine, sans erreur visible.
 *
 * @return list<array{cle:string, actif:bool}>
 */
function composition_valider(string $page, ?string $json): array
{
    $catalogue = composition_catalogue($page);
    $defauts   = composition_defauts($page);

    if ($json === null || trim($json) === '') {
        return $defauts;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || !array_is_list($data)) {
        return $defauts;
    }

    $blocs = [];
    $vus   = [];
    foreach ($data as $item) {
        if (!is_array($item) || !isset($item['cle']) || !is_string($item['cle'])) {
            continue;
        }
        $cle = $item['cle'];
        if (!array_key_exists($cle, $catalogue) || isset($vus[$cle])) {
            continue;
        }
        $actif = $item['actif'] ?? true;
        if (!is_bool($actif)) {
            $actif = in_array($actif, [1, '1', 'true'], true);
        }
        // Les blocs fixes restent toujours affichés.
        if (!empty($catalogue[$cle]['fixe'])) {
            $actif = true;
        }
        $blocs[] = ['cle' => $cle, 'actif' => $actif];
        $vus[$cle]  = true;
    }

    if ($blocs === []) {
        return $defauts;
    }

    // Les blocs ajoutés par une version ultérieure gardent leur place.
    foreach ($defauts as $defaut) {
        if (!isset($vus[$defaut['cle']])) {
            $blocs[] = $defaut;
        }
    }

    return $blocs;
}

/**
 * Composition en vigueur pour une page.
 *
 * @param bool $rafraichir relit les réglages (après un enregistrement)
 * @return list<array{cle:string, actif:bool}>
 */
function composition_blocs(string $page, bool $rafraichir = false): array
{
    static $cache = [];

    if (isset($cache[$page]) && !$rafraichir) {
        return $cache[$page];
    }

    $cache[$page] = composition_valider($page, site_setting(composition_cle_reglage($page)));
    return $cache[$page];
}

/** Le bloc est-il affiché ? */
function composition_visible(string $page, string $cle): bool
{
    foreach (composition_blocs($page) as $bloc) {
        if ($bloc['cle'] === $cle) {
            return $bloc['actif'];
        }
    }
    return false;
}

/**
 * Les blocs affichés d'une colonne, dans l'ordre.
 *
 * @return list<string>
 */
function composition_colonne(string $page, string $colonne): array
{
    $catalogue = composition_catalogue($page);
    $cles      = [];

    foreach (composition_blocs($page) as $bloc) {
        if (!$bloc['actif']) {
            continue;
        }
        if (($catalogue[$bloc['cle']]['colonne'] ?? '') === $colonne) {
            $cles[] = $bloc['cle'];
        }
    }

    return $cles;
}

/**
 * Affiche ou masque un bloc.
 *
 * @return bool le nouvel état (true = affiché)
 */
function composition_basculer(string $page, string $cle): bool
{
    $catalogue = composition_catalogue($page);
    if (!isset($catalogue[$cle])) {
        return false;
    }
    if (!empty($catalogue[$cle]['fixe'])) {
        return true; // bloc toujours affiché : rien ne change.
    }

    $blocs = composition_blocs($page, true);
    $etat  = true;
    foreach ($blocs as $i => $bloc) {
        if ($bloc['cle'] === $cle) {
            $etat = !$bloc['actif'];
            $blocs[$i]['actif'] = $etat;
        }
    }

    composition_enregistrer($page, $blocs);
    return $etat;
}

/**
 * Fait monter (-1) ou descendre (+1) un bloc parmi les blocs de sa colonne.
 * Le bloc ne quitte jamais sa colonne.
 */
function composition_deplacer(string $page, string $cle, int $sens): bool
{
    $catalogue = composition_catalogue($page);
    if (!isset($catalogue[$cle]) || !in_array($sens, [-1, 1], true)) {
        return false;
    }

    $blocs     = composition_blocs($page, true);
    $colonne   = $catalogue[$cle]['colonne'];

    // Voisins de la même colonne, dans l'ordre actuel.
    $indices = [];
    foreach ($blocs as $i => $bloc) {
        if (($catalogue[$bloc['cle']]['colonne'] ?? '') === $colonne) {
            $indices[] = $i;
        }
    }
    $position = array_search($cle, array_map(static fn (int $i): string => $blocs[$i]['cle'], $indices), true);
    if ($position === false) {
        return false;
    }

    $cible = $position + $sens;
    if ($cible < 0 || $cible >= count($indices)) {
        return false; // déjà tout en haut ou tout en bas.
    }

    $i = $indices[$position];
    $j = $indices[$cible];
    [$blocs[$i], $blocs[$j]] = [$blocs[$j], $blocs[$i]];

    composition_enregistrer($page, $blocs);
    return true;
}

/**
 * Enregistre une composition (JSON compact, validé avant écriture).
 *
 * @param list<array{cle:string, actif:bool}> $blocs
 */
function composition_enregistrer(string $page, array $blocs): void
{
    $propres = [];
    foreach ($blocs as $bloc) {
        $propres[] = [
            'cle'   => (string) $bloc['cle'],
            'actif' => !empty($bloc['actif']),
        ];
    }

    save_site_setting(composition_cle_reglage($page), json_encode($propres, JSON_UNESCAPED_UNICODE) ?: '');
    composition_blocs($page, true);
}

/** « Rétablir la composition d'origine » : efface la composition enregistrée. */
function composition_retablir(?string $page = null): void
{
    foreach (array_keys(composition_pages()) as $cle) {
        if ($page !== null && $page !== $cle) {
            continue;
        }
        db_exec('DELETE FROM settings WHERE key = ?', [composition_cle_reglage($cle)]);
    }

    // Les effacements ne passent pas par save_site_setting() : on force la
    // relecture, sinon la page afficherait encore l'ancienne composition.
    site_settings_oublier();
    composition_blocs('public', true);
    composition_blocs('famille', true);
}

// ---------------------------------------------------------------------------
// Réglages de la une
// ---------------------------------------------------------------------------

/** Valeurs acceptées pour la longueur des résumés de la une. */
const COMPOSITION_RESUMES = [
    'court' => 'Court (une ligne)',
    'moyen' => 'Moyen (deux ou trois lignes)',
    'long'  => 'Long (un paragraphe)',
];

/** Longueur d'un résumé de la une, selon le réglage. */
function composition_longueur_resume(bool $grand): int
{
    $mode = composition_resume_choisi();

    return match ($mode) {
        'court' => $grand ? 160 : 90,
        'long'  => $grand ? 480 : 280,
        default => $grand ? 320 : 180,
    };
}

/** Nombre d'articles en colonnes (3 à 12 ; 4 par défaut, comme aujourd'hui). */
function composition_nb_colonnes(): int
{
    $valeur = (int) site_setting('apparence_une_colonnes');
    if ($valeur < 3 || $valeur > 12) {
        return 4;
    }
    return $valeur;
}

/** Nombre total d'articles de la une (l'article en grand + les colonnes). */
function composition_nb_articles(): int
{
    return composition_nb_colonnes() + 1;
}

/** Afficher la photo du grand article de la une ? (oui par défaut) */
function composition_une_photo(): bool
{
    $valeur = site_setting('apparence_une_photo');
    return $valeur === '' ? true : $valeur === '1';
}

/**
 * Lit et valide les réglages de la une envoyés par le formulaire.
 *
 * @return array{0: array<string,string>, 1: list<string>} valeurs, erreurs
 */
function composition_lire_reglages(array $saisie): array
{
    $valeurs = [];
    $erreurs = [];

    $colonnes = (int) ($saisie['une_colonnes'] ?? 0);
    if ($colonnes < 1 || $colonnes > 12) {
        $erreurs[] = 'Nombre d’articles en colonnes : choisissez entre 3 et 12.';
        $colonnes  = composition_nb_colonnes();
    } elseif ($colonnes < 3) {
        $erreurs[] = 'Nombre d’articles en colonnes : trois au minimum.';
    }
    $valeurs['apparence_une_colonnes'] = (string) $colonnes;

    $resume = (string) ($saisie['une_resume'] ?? 'moyen');
    if (!array_key_exists($resume, COMPOSITION_RESUMES)) {
        $erreurs[] = 'Longueur du résumé : choisissez une des trois propositions.';
        $resume    = composition_resume_choisi();
    }
    $valeurs['apparence_une_resume'] = $resume;

    $valeurs['apparence_une_photo'] = ($saisie['une_photo'] ?? '') === '1' ? '1' : '0';

    $modele = (string) ($saisie['modele_defaut'] ?? '');
    if (!array_key_exists($modele, article_modeles())) {
        $erreurs[] = 'Modèle d’article par défaut : ce modèle n’existe pas.';
        $modele    = '';
    }
    $valeurs['apparence_modele_defaut'] = $modele;

    return [$valeurs, $erreurs];
}

/** Enregistre les réglages de la une. */
function composition_enregistrer_reglages(array $valeurs): void
{
    foreach ($valeurs as $cle => $valeur) {
        save_site_setting((string) $cle, (string) $valeur);
    }
}

/** « Rétablir » remet aussi les réglages de la une à leurs valeurs d'origine. */
function composition_retablir_reglages(): void
{
    foreach (['apparence_une_colonnes', 'apparence_une_resume', 'apparence_une_photo', 'apparence_modele_defaut'] as $cle) {
        db_exec('DELETE FROM settings WHERE key = ?', [$cle]);
    }
    site_settings_oublier();
}

// ---------------------------------------------------------------------------
// Modèles d'article
// ---------------------------------------------------------------------------

/**
 * Les modèles d'article proposés.
 *
 * La clé vide veut dire « comme le site » : l'article suit le modèle par
 * défaut choisi dans la composition des pages.
 *
 * @return array<string, string>
 */
function article_modeles(): array
{
    return [
        ''        => 'Comme le site',
        'journal' => 'Journal',
        'simple'  => 'Simple',
        'photo'   => 'Grande photo',
    ];
}

/** Modèle par défaut du site (« journal » si le réglage est absent ou inconnu). */
function article_modele_defaut(): string
{
    $modele = site_setting('apparence_modele_defaut');
    if (!array_key_exists($modele, article_modeles()) || $modele === '') {
        return 'journal';
    }
    return $modele;
}

/**
 * Modèle réellement appliqué à un article : celui de l'article s'il est
 * valide, sinon le modèle du site. Une valeur inconnue en base ne casse rien.
 */
function article_modele(array $article): string
{
    $modele = (string) ($article['modele'] ?? '');
    if ($modele !== '' && array_key_exists($modele, article_modeles())) {
        return $modele;
    }
    return article_modele_defaut();
}

/** Classe CSS du modèle (« » pour le journal : la mise en page d'origine). */
function article_modele_classe(string $modele): string
{
    return $modele === 'simple' || $modele === 'photo' ? ' article--modele-' . $modele : '';
}
