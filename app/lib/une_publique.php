<?php
/**
 * La Une du site public (1.31.0).
 *
 * L'accueil d'un visiteur ne montre plus qu'une chose : **tout le contenu
 * public du site**, du plus récent au plus ancien — articles, photos, vidéos,
 * documents, recettes et pages d'information — avec le plus récent en grand.
 *
 * Rien n'est filtré « à la main » ici : chaque source passe par
 * `visibility_clause()`, la même règle que partout ailleurs. Un contenu privé,
 * réservé à la famille, réservé à des branches, en attente de validation ou
 * encore dans la file de conversion n'entre donc jamais dans cette liste, et
 * une adresse d'image n'est proposée que si le document est visible par la
 * personne qui regarde.
 *
 * Aucun droit n'est ouvert par ce fichier : il ne fait que lire ce que les
 * pages existantes montrent déjà.
 */

declare(strict_types=1);

/** Nombre d'éléments par page de la Une. */
function une_publique_par_page(): int
{
    return 24;
}

/**
 * Les rubriques de la Une, dans l'ordre où elles sont regroupées.
 *
 * @return array<string, string>
 */
function une_publique_rubriques(): array
{
    return [
        'articles'  => 'Articles',
        'photos'    => 'Photos',
        'videos'    => 'Vidéos',
        'documents' => 'Documents',
        'recettes'  => 'Recettes',
        'pages'     => 'Pages d’information',
    ];
}

/**
 * La requête qui réunit tout le contenu public, quel que soit son type.
 *
 * Une seule requête plutôt que cinq : le tri « du plus récent au plus ancien »
 * et la pagination restent justes, et le Raspberry Pi ne parcourt la base
 * qu'une fois par page affichée.
 *
 * Chaque branche de l'UNION utilise la clause de visibilité de son type
 * (`article_visible_clause()` pour les articles, qui ajoute le statut
 * « publié » et la date ; `visibility_clause()` pour les autres, qui ajoute la
 * conversion des vidéos et la modération des propositions).
 *
 * Colonnes : type, id, titre, resume, image_id, slug, ext, tri, date_texte.
 */
function une_publique_sql(array &$params): string
{
    $articles = article_visible_clause('a', $params);
    $documents = visibility_clause('document', 'd', $params);
    $recettes  = visibility_clause('recipe', 'r', $params);
    $pages     = visibility_clause('page', 'p', $params);

    // Les vidéos et les photos gardent leur type pour le regroupement ; le
    // reste des documents reste « documents ».
    return "
        SELECT 'articles' AS type, a.id AS id, a.title AS titre,
               a.summary AS resume, COALESCE(a.image_id, 0) AS image_id,
               a.slug AS slug, '' AS ext, 1 AS vignette,
               '' AS vintage_aspect,
               a.published_at AS tri, '' AS date_texte
          FROM articles a
         WHERE {$articles}

        UNION ALL
        SELECT CASE d.kind
                   WHEN 'photo' THEN 'photos'
                   WHEN 'video' THEN 'videos'
                   ELSE 'documents'
               END AS type,
               d.id AS id, d.title AS titre, d.description AS resume,
               d.id AS image_id, '' AS slug, d.ext AS ext,
               -- Une vignette n'existe que si le document en a une :
               -- `thumbnail_url()` dit la même chose ailleurs (1.31.0).
               CASE WHEN d.thumb_path IS NULL OR d.thumb_path = '' THEN 0 ELSE 1 END AS vignette,
               d.vintage_aspect AS vintage_aspect,
               d.created_at AS tri,
               COALESCE(NULLIF(TRIM(d.doc_date), ''), '') AS date_texte
          FROM documents d
         WHERE d.is_attachment = 0 AND d.est_archive = 0 AND {$documents}

        UNION ALL
        SELECT 'recettes' AS type, r.id AS id, r.title AS titre,
               COALESCE(r.origin, '') AS resume,
               COALESCE(r.photo_document_id, 0) AS image_id,
               r.slug AS slug, '' AS ext, 1 AS vignette,
               '' AS vintage_aspect,
               r.created_at AS tri, '' AS date_texte
          FROM recipes r
         WHERE {$recettes}

        UNION ALL
        SELECT 'pages' AS type, p.id AS id, p.title AS titre, p.body AS resume,
               0 AS image_id, p.slug AS slug, '' AS ext, 1 AS vignette,
               '' AS vintage_aspect,
               p.updated_at AS tri, '' AS date_texte
          FROM pages p
         WHERE p.is_published = 1 AND {$pages}
    ";
}

/**
 * Contenu public, du plus récent au plus ancien, page par page.
 *
 * @param int $parPage nombre d'éléments par page (24 par défaut)
 * @param int $page    page demandée, à partir de 1
 * @param array|null $exclure un élément à ne pas répéter (celui de la une),
 *                            sous la forme ['type' => …, 'id' => …]
 * @return array{items:list<array>, total:int, pagination:array}
 */
function une_publique_page(int $parPage = 0, int $page = 1, ?array $exclure = null): array
{
    $parPage = $parPage > 0 ? $parPage : une_publique_par_page();
    $params  = [];
    $sql     = une_publique_sql($params);

    // L'élément choisi pour la une n'est pas répété dans la liste : on
    // l'écarte ici, pour que le total et la pagination restent justes.
    $filtre = '';
    if ($exclure !== null && (int) ($exclure['id'] ?? 0) > 0) {
        $filtre = ' WHERE NOT (type = :ex_type AND id = :ex_id)';
        $params[':ex_type'] = (string) $exclure['type'];
        $params[':ex_id']   = (int) $exclure['id'];
    }

    $total = (int) db_value("SELECT COUNT(*) FROM ({$sql}) AS u{$filtre}", $params, 0);
    $pg    = pagination($total, $parPage, $page);

    $lignes = db_all(
        "SELECT * FROM ({$sql}) AS u{$filtre}
         ORDER BY tri DESC, id DESC
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    return [
        'items'      => une_publique_items($lignes),
        'total'      => $total,
        'pagination' => $pg,
    ];
}

/**
 * Les contenus **publics** que l'on peut choisir pour la une (réglage de
 * « Composition des pages »).
 *
 * La liste est calculée comme la verrait un visiteur : seuls des contenus
 * publics y figurent, donc le choix d'un administrateur est toujours affichable
 * sur la page publique. Trier puis limiter : inutile de proposer tout le site.
 *
 * @return list<array{cle:string, libelle:string, titre:string}>
 */
function une_publique_candidats(int $limite = 40): array
{
    $params = [];
    $now    = ':cnow';
    $params[$now] = time();

    $articles  = proposition_clause('article', 'a', $params);
    $documents = proposition_clause('document', 'd', $params);
    $recettes  = proposition_clause('recipe', 'r', $params);

    $sql = "
        SELECT 'articles' AS type, a.id AS id, a.title AS titre, a.published_at AS tri
          FROM articles a
         WHERE a.status = 'publie' AND a.published_at <= {$now}
           AND a.visibility = 'public' AND {$articles}

        UNION ALL
        SELECT CASE d.kind WHEN 'photo' THEN 'photos' WHEN 'video' THEN 'videos' ELSE 'documents' END,
               d.id, d.title, d.created_at
          FROM documents d
         WHERE d.is_attachment = 0 AND d.est_archive = 0 AND d.visibility = 'public'
           AND " . conversion_pretes_sql('d') . " AND {$documents}

        UNION ALL
        SELECT 'recettes', r.id, r.title, r.created_at
          FROM recipes r
         WHERE r.visibility = 'public' AND {$recettes}

        UNION ALL
        SELECT 'pages', p.id, p.title, p.updated_at
          FROM pages p
         WHERE p.is_published = 1 AND p.visibility = 'public'

         ORDER BY tri DESC, id DESC
         LIMIT " . max(1, $limite);

    $libelles = une_publique_rubriques();
    $candidats = [];
    foreach (db_all($sql, $params) as $ligne) {
        $type = (string) $ligne['type'];
        if (!array_key_exists($type, $libelles)) {
            continue;
        }
        $candidats[] = [
            'cle'     => $type . ':' . (int) $ligne['id'],
            'libelle' => $libelles[$type],
            'titre'   => (string) $ligne['titre'],
        ];
    }

    return $candidats;
}

/**
 * Un élément public précis, s'il est bien visible par la personne qui regarde.
 *
 * Sert au réglage « À la une » : un contenu choisi par un administrateur puis
 * repassé en privé, ou supprimé, n'est plus proposé — la page retombe sur le
 * contenu public le plus récent.
 *
 * @return array|null
 */
function une_publique_element(string $type, int $id): ?array
{
    if ($id <= 0 || !array_key_exists($type, une_publique_rubriques())) {
        return null;
    }

    $params = [];
    $sql    = une_publique_sql($params);
    $params[':un_type'] = $type;
    $params[':un_id']   = $id;

    $ligne = db_one(
        "SELECT * FROM ({$sql}) AS u WHERE u.type = :un_type AND u.id = :un_id",
        $params
    );

    if ($ligne === null) {
        return null;
    }

    $items = une_publique_items([$ligne]);
    return $items[0] ?? null;
}

/**
 * Le réglage « À la une », lu et vérifié.
 *
 * Stored as `type:id` in `settings` (clé `une_publique`) : aucune migration,
 * et une valeur bricolée à la main retombe simplement sur « automatique ».
 *
 * @return array{type:string, id:int}|null
 */
function une_publique_choix(): ?array
{
    $valeur = trim((string) site_setting('une_publique'));
    if ($valeur === '') {
        return null;
    }

    if (preg_match('/^([a-z]+):(\d{1,10})$/', $valeur, $trouve) !== 1) {
        return null;
    }

    $type = (string) $trouve[1];
    $id   = (int) $trouve[2];

    return array_key_exists($type, une_publique_rubriques()) && $id > 0
        ? ['type' => $type, 'id' => $id]
        : null;
}

/** Enregistre (ou efface) le choix de la une. Chaîne vide = automatique. */
function une_publique_enregistrer(string $valeur): void
{
    save_site_setting('une_publique', $valeur);
}

/**
 * L'élément qui tient la une : celui choisi par un administrateur, sinon le
 * contenu public le plus récent.
 *
 * @param list<array> $items première page (déjà calculée quand on peut)
 * @return array|null
 */
function une_publique_une(array $items = []): ?array
{
    $choix = une_publique_choix();
    if ($choix !== null) {
        $element = une_publique_element($choix['type'], $choix['id']);
        if ($element !== null) {
            return $element;
        }
    }

    return $items[0] ?? null;
}

/**
 * Le « genre » (type au singulier) à afficher en petit sur une carte de la
 * une (1.36.0) : vide s'il répète la rubrique déjà montrée en surtitre
 * (« Photos » en grand et « Photo » en petit, c'est deux fois le même mot).
 */
function une_genre_affiche(array $item): string
{
    $genre    = trim((string) ($item['genre'] ?? ''));
    $rubrique = trim((string) ($item['rubrique'] ?? ''));
    $norm = static fn (string $mot): string => mb_strtolower(rtrim($mot, 's'));
    if ($genre === '' || $norm($genre) === $norm($rubrique)) {
        return '';
    }
    return $genre;
}

/**
 * Met les lignes SQL en forme pour l'affichage : adresse, image, date, genre.
 *
 * Les adresses d'images ne sont produites que pour des documents visibles :
 * un article public dont la photo est privée s'affiche sans image, et aucune
 * adresse de média réservé n'apparaît dans la page.
 *
 * @param list<array> $lignes
 * @return list<array>
 */
function une_publique_items(array $lignes): array
{
    if ($lignes === []) {
        return [];
    }

    // Les images des articles et des recettes sont des documents à part : on
    // vérifie en une seule requête qu'ils sont visibles par cette personne.
    $aVerifier = [];
    foreach ($lignes as $ligne) {
        if (in_array((string) $ligne['type'], ['articles', 'recettes'], true)
            && (int) ($ligne['image_id'] ?? 0) > 0) {
            $aVerifier[(int) $ligne['image_id']] = true;
        }
    }

    $visibles = [];
    if ($aVerifier !== []) {
        $params = [];
        $clause = visibility_clause('document', 'd', $params);
        $places = [];
        $i      = 0;
        foreach (array_keys($aVerifier) as $imageId) {
            $nom            = ':img' . $i;
            $places[]       = $nom;
            $params[$nom]   = (int) $imageId;
            $i++;
        }
        $sql = 'SELECT d.id, d.vintage_aspect FROM documents d WHERE d.id IN (' . implode(', ', $places) . ')'
            . " AND d.thumb_path IS NOT NULL AND d.thumb_path <> '' AND {$clause}";
        foreach (db_all($sql, $params) as $trouve) {
            $visibles[(int) $trouve['id']] = (string) ($trouve['vintage_aspect'] ?? 'suivre');
        }
    }

    $libelles = une_publique_rubriques();
    $items    = [];

    foreach ($lignes as $ligne) {
        $type    = (string) $ligne['type'];
        $id      = (int) $ligne['id'];
        $imageId = (int) ($ligne['image_id'] ?? 0);
        $tri     = (int) $ligne['tri'];

        $image  = null;
        $aspect = '';
        // Les documents, photos et vidéos portent leur propre vignette : la
        // clause de visibilité les a déjà filtrés. Sans vignette (une vidéo
        // sans image d'aperçu, par exemple), on n'en propose pas : la carte
        // affiche alors son repère de rubrique, jamais une image cassée.
        if (in_array($type, ['photos', 'videos', 'documents'], true)) {
            $image = (int) ($ligne['vignette'] ?? 0) === 1 ? '/media/miniature/' . $id : null;
            if ($image !== null) {
                // L'aspect « photo ancienne » suit le réglage du site, sauf
                // réglage propre au document (1.32.0).
                $aspect = aspect_photo_classe(['vintage_aspect' => (string) ($ligne['vintage_aspect'] ?? 'suivre')]);
            }
        } elseif ($imageId > 0 && isset($visibles[$imageId])) {
            $image = '/media/miniature/' . $imageId;
            $aspect = aspect_photo_classe(['vintage_aspect' => (string) $visibles[$imageId]]);
        }

        $adresse = match ($type) {
            'articles'  => '/articles/' . (string) $ligne['slug'],
            'videos'    => '/videos/' . $id,
            'recettes'  => '/recettes/' . (string) $ligne['slug'],
            'pages'     => '/pages/' . (string) $ligne['slug'],
            default     => '/documents/' . $id,
        };

        $dateTexte = trim((string) ($ligne['date_texte'] ?? ''));
        if ($dateTexte === '') {
            $dateTexte = format_date($tri);
        }

        // Thèmes du contenu (1.36.0) : le surtitre de la carte, quand il y
        // en a un ; le type reste en petit dans les métadonnées.
        $kindTheme = match ($type) {
            'articles' => 'article',
            'photos'   => 'photo',
            'videos'   => 'video',
            'documents' => 'document',
            'recettes' => 'recipe',
            default    => '',
        };

        $items[] = [
            'type'       => $type,
            'rubrique'   => $libelles[$type] ?? 'Documents',
            'themes'     => $kindTheme !== '' ? themes_noms_pour($kindTheme, $id) : [],
            'id'         => $id,
            'titre'      => (string) $ligne['titre'],
            'resume'     => text_summary((string) ($ligne['resume'] ?? ''), 200),
            'date'       => $tri,
            'date_texte' => $dateTexte,
            'url'        => $adresse,
            'image'      => $image,
            'image_aspect' => $aspect,
            'genre'      => in_array($type, ['photos', 'videos', 'documents'], true)
                ? document_kind_label(
                    match ($type) {
                        'photos' => 'photo',
                        'videos' => 'video',
                        default  => 'document',
                    },
                    (string) ($ligne['ext'] ?? '')
                )
                : ($libelles[$type] ?? 'Document'),
        ];
    }

    return $items;
}

/**
 * Regroupe des éléments par rubrique, dans l'ordre de `une_publique_rubriques()`.
 *
 * Sert à la « grille de journal » quand il y a plus de six éléments : chaque
 * rubrique garde son titre, et les éléments restent du plus récent au plus
 * ancien à l'intérieur.
 *
 * @param list<array> $items
 * @return array<string, array{libelle:string, items:list<array>}>
 */
function une_publique_groupes(array $items): array
{
    $groupes = [];

    foreach (une_publique_rubriques() as $cle => $libelle) {
        $dedans = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['type'] === $cle
        ));
        if ($dedans !== []) {
            $groupes[$cle] = ['libelle' => $libelle, 'items' => $dedans];
        }
    }

    return $groupes;
}

/**
 * Combien d'éléments publics au total ? (page d'accueil vide ou non)
 */
function une_publique_existe(): bool
{
    $page = une_publique_page(1, 1);
    return $page['total'] > 0;
}
