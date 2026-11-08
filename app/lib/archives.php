<?php
/**
 * Archives et dossiers d'archives (1.36.0).
 *
 * Un document « d'archive » (`documents.est_archive = 1`) ne change ni de
 * visibilité ni de droits : il reste privé par défaut, régi par
 * `visibility_clause()` / `can_view()` comme les autres. Il change seulement
 * de *vitrine* : jamais à la une, jamais dans les nouveautés, jamais dans la
 * gazette du mois — mais présent dans la page Documents (filtre « Archives »),
 * dans la recherche, dans son album et dans son dossier.
 *
 * Un **dossier** (par exemple « Grand-père Michel ») range des documents
 * d'archive. Il n'a pas de visibilité propre : il n'existe, pour un compte,
 * que par les documents que ce compte peut voir dedans. Un dossier sans
 * aucun document visible pour un compte n'apparaît donc pour lui ni en nom,
 * ni en nombre — exactement comme un compteur de thème.
 */

declare(strict_types=1);

/** Ce document est-il classé en archive ? */
function document_est_archive(array $doc): bool
{
    return (int) ($doc['est_archive'] ?? 0) === 1;
}

/**
 * Condition SQL « pas un document d'archive », pour les vitrines (une,
 * nouveautés, gazette). À concaténer dans un WHERE existant.
 */
function document_non_archive_sql(string $alias): string
{
    return $alias . '.est_archive = 0';
}

/** Tous les dossiers, par nom (administration). */
function dossiers_liste(): array
{
    return db_all('SELECT * FROM dossiers ORDER BY name COLLATE NOCASE');
}

/** Un dossier par son identifiant. */
function dossier_par_id(int $id): ?array
{
    return $id > 0 ? db_one('SELECT * FROM dossiers WHERE id = ?', [$id]) : null;
}

/**
 * Nombre de documents d'un dossier **visibles par le compte courant**.
 * C'est ce compteur qui décide si le dossier existe aux yeux du compte.
 */
function dossier_nombre_visibles(int $dossierId): int
{
    if ($dossierId <= 0) {
        return 0;
    }
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $params[':dossier'] = $dossierId;
    return (int) db_value(
        "SELECT COUNT(*) FROM documents d
         WHERE d.dossier_id = :dossier AND d.is_attachment = 0 AND {$clause}",
        $params,
        0
    );
}

/**
 * Les dossiers qui ont au moins un document visible par le compte courant,
 * avec ce nombre. Un dossier vide pour ce compte est absent de la liste.
 *
 * @return list<array> lignes de `dossiers`, plus `nb` (documents visibles)
 */
function dossiers_visibles(): array
{
    $visibles = [];
    foreach (dossiers_liste() as $dossier) {
        $nb = dossier_nombre_visibles((int) $dossier['id']);
        if ($nb > 0) {
            $visibles[] = $dossier + ['nb' => $nb];
        }
    }
    return $visibles;
}

/** Ce compte peut-il voir quelque chose dans ce dossier ? */
function dossier_visible_pour_compte(int $dossierId): bool
{
    return dossier_nombre_visibles($dossierId) > 0;
}

/**
 * Documents d'un dossier, visibles par le compte courant, du plus récent
 * au plus ancien.
 */
function dossier_documents_visibles(int $dossierId): array
{
    if ($dossierId <= 0) {
        return [];
    }
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $params[':dossier'] = $dossierId;
    return db_all(
        "SELECT d.* FROM documents d
         WHERE d.dossier_id = :dossier AND d.is_attachment = 0 AND {$clause}
         ORDER BY (d.doc_date_sort IS NULL), d.doc_date_sort DESC, d.created_at DESC",
        $params
    );
}

/**
 * Dossiers liés à une personne de l'arbre, **visibles par le compte courant**
 * (même règle : rien de visible dedans = le dossier n'apparaît pas).
 *
 * @return list<array> lignes de `dossiers`, plus `nb`
 */
function dossiers_pour_personne(int $personId): array
{
    if ($personId <= 0) {
        return [];
    }
    $lies = db_all('SELECT * FROM dossiers WHERE person_id = ? ORDER BY name COLLATE NOCASE', [$personId]);
    $visibles = [];
    foreach ($lies as $dossier) {
        $nb = dossier_nombre_visibles((int) $dossier['id']);
        if ($nb > 0) {
            $visibles[] = $dossier + ['nb' => $nb];
        }
    }
    return $visibles;
}

/**
 * Documents d'archive sans dossier, visibles par le compte courant
 * (page Documents, filtre « Archives »).
 */
function archives_sans_dossier_visibles(): array
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    return db_all(
        "SELECT d.* FROM documents d
         WHERE d.est_archive = 1 AND d.dossier_id IS NULL AND d.is_attachment = 0 AND {$clause}
         ORDER BY (d.doc_date_sort IS NULL), d.doc_date_sort DESC, d.created_at DESC",
        $params
    );
}
