<?php
/**
 * Albums photos et personnes identifiées sur les documents.
 *
 * Un album n'est qu'un rangement : les photos restent des documents comme les
 * autres, avec leur propre visibilité et leurs propres droits. Une photo
 * privée glissée dans un album public reste donc invisible pour un visiteur.
 *
 * Les personnes identifiées servent à deux choses : afficher « Qui figure sur
 * cette photo ? » sur la fiche du document, et « Photos » sur la fiche d'une
 * personne (et, plus tard, la personne qui parle dans un témoignage audio).
 */

declare(strict_types=1);

/** Adresse publique d'un album. */
function album_url(array $album): string
{
    return '/albums/' . rawurlencode((string) $album['slug']);
}

/**
 * Phrase listant des personnes : « Jean MARTIN, Marie LEROY et 2 autres ».
 * Fonction pure (aucun accès à la base) pour être testable partout.
 */
function album_people_line(array $persons, int $max = 3): string
{
    $noms = [];
    foreach ($persons as $person) {
        // Une fiche sans nom n'apporte rien dans une légende.
        if (trim((string) ($person['first_name'] ?? '')) === ''
            && trim((string) ($person['last_name'] ?? '')) === '') {
            continue;
        }
        $noms[] = person_full_name($person);
    }

    $total = count($noms);
    if ($total === 0) {
        return '';
    }
    if ($total <= $max) {
        $dernier = array_pop($noms);
        return $noms === [] ? $dernier : implode(', ', $noms) . ' et ' . $dernier;
    }

    $visibles = array_slice($noms, 0, $max);
    $reste    = $total - $max;
    return implode(', ', $visibles) . ' et ' . $reste . ' autre' . ($reste > 1 ? 's' : '');
}

/** Condition SQL des albums visibles par la personne connectée. */
function album_visible_clause(string $alias, array &$params): string
{
    return visibility_clause('album', $alias, $params);
}

/** Un album par son identifiant (sans contrôle de droits). */
function find_album(int $id): ?array
{
    return db_one('SELECT * FROM albums WHERE id = ?', [$id]);
}

/** Un album visible par la personne connectée, par son adresse. */
function find_viewable_album(string $slug): ?array
{
    $params = [];
    $clause = album_visible_clause('a', $params);

    return db_one(
        "SELECT * FROM albums a
         WHERE a.slug = :slug AND {$clause}",
        $params + [':slug' => $slug]
    );
}

/** Nombre de photos visibles dans un album. */
function album_photo_count(int $albumId): int
{
    $params = [':album' => $albumId];
    $clause = visibility_clause('document', 'd', $params);

    return (int) db_value(
        "SELECT COUNT(*) FROM album_documents ad
         JOIN documents d ON d.id = ad.document_id
         WHERE ad.album_id = :album AND {$clause}",
        $params,
        0
    );
}

/**
 * Photos visibles d'un album, dans l'ordre choisi par l'administrateur.
 *
 * @param int      $limit  0 = tout
 * @param int|null $total  reçoit le nombre total de photos visibles
 */
function album_photos(int $albumId, int $limit = 0, int $offset = 0, ?int &$total = null): array
{
    $params = [':album' => $albumId];
    $clause = visibility_clause('document', 'd', $params);
    $total  = album_photo_count($albumId);

    $sql = "SELECT d.* FROM album_documents ad
            JOIN documents d ON d.id = ad.document_id
            WHERE ad.album_id = :album AND {$clause}
            ORDER BY ad.position, d.doc_date_sort IS NULL, d.doc_date_sort, d.id";

    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
    }

    return db_all($sql, $params);
}

/** Photo de couverture : celle choisie, sinon la première photo visible. */
function album_cover(array $album): ?array
{
    if (!empty($album['cover_document_id'])) {
        $params = [':id' => (int) $album['cover_document_id']];
        $clause = visibility_clause('document', 'd', $params);
        $doc    = db_one("SELECT d.* FROM documents d WHERE d.id = :id AND {$clause}", $params);
        if ($doc) {
            return $doc;
        }
    }

    $photos = album_photos((int) $album['id'], 1);
    return $photos[0] ?? null;
}

/** Albums visibles par la personne connectée. */
function albums_visible(int $limit = 0, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = album_visible_clause('a', $params);
    $total  = (int) db_value("SELECT COUNT(*) FROM albums a WHERE {$clause}", $params, 0);

    $sql = "SELECT a.* FROM albums a WHERE {$clause}
            ORDER BY a.position, a.title COLLATE NOCASE";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
    }

    return db_all($sql, $params);
}

/** Identifiants des photos d'un album (pour cocher les cases du formulaire). */
function album_photo_ids(int $albumId): array
{
    $rows = db_all('SELECT document_id FROM album_documents WHERE album_id = ? ORDER BY position', [$albumId]);
    return array_map(static fn (array $row): int => (int) $row['document_id'], $rows);
}

/**
 * Enregistre les photos d'un album, dans l'ordre reçu.
 * Une photo déjà présente n'est pas ajoutée deux fois.
 */
function set_album_photos(int $albumId, array $documentIds): void
{
    db_exec('DELETE FROM album_documents WHERE album_id = ?', [$albumId]);

    $position = 0;
    $vus      = [];
    foreach ($documentIds as $documentId) {
        $documentId = (int) $documentId;
        if ($documentId <= 0 || isset($vus[$documentId])) {
            continue;
        }
        $vus[$documentId] = true;
        db_exec(
            'INSERT OR IGNORE INTO album_documents (album_id, document_id, position) VALUES (?, ?, ?)',
            [$albumId, $documentId, $position++]
        );
    }
}

/** Albums (visibles) auxquels appartient un document. */
function albums_of_document(int $documentId): array
{
    $params = [':document' => $documentId];
    $clause = album_visible_clause('a', $params);

    return db_all(
        "SELECT a.* FROM album_documents ad
         JOIN albums a ON a.id = ad.album_id
         WHERE ad.document_id = :document AND {$clause}
         ORDER BY a.position, a.title COLLATE NOCASE",
        $params
    );
}

// ---------------------------------------------------------------------------
// Personnes identifiées sur un document
// ---------------------------------------------------------------------------

/**
 * Personnes identifiées sur un document, filtrées par la visibilité de
 * chacune : un visiteur ne voit pas le nom d'une personne qui lui est cachée.
 */
function document_persons(int $documentId): array
{
    $params = [':document' => $documentId];
    $clause = visibility_clause('person', 'p', $params);

    return db_all(
        "SELECT p.* FROM document_persons dp
         JOIN persons p ON p.id = dp.person_id
         WHERE dp.document_id = :document AND {$clause}
         ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE",
        $params
    );
}

/** Identifiants de toutes les personnes identifiées (sans filtre de droits). */
function document_person_ids(int $documentId): array
{
    $rows = db_all('SELECT person_id FROM document_persons WHERE document_id = ?', [$documentId]);
    return array_map(static fn (array $row): int => (int) $row['person_id'], $rows);
}

/** Remplace la liste des personnes identifiées sur un document. */
function set_document_persons(int $documentId, array $personIds): void
{
    db_exec('DELETE FROM document_persons WHERE document_id = ?', [$documentId]);

    foreach (array_unique(array_map('intval', $personIds)) as $personId) {
        if ($personId <= 0) {
            continue;
        }
        db_exec(
            'INSERT OR IGNORE INTO document_persons (document_id, person_id) VALUES (?, ?)',
            [$documentId, $personId]
        );
    }
}

/** Photos visibles où figure une personne. */
function person_photos(int $personId, int $limit = 12): array
{
    $params = [':person' => $personId];
    $clause = visibility_clause('document', 'd', $params);

    return db_all(
        "SELECT d.* FROM document_persons dp
         JOIN documents d ON d.id = dp.document_id
         WHERE dp.person_id = :person AND {$clause}
         ORDER BY d.doc_date_sort IS NULL, d.doc_date_sort, d.id
         LIMIT " . max(1, $limit),
        $params
    );
}

/** Nombre de photos visibles où figure une personne. */
function person_photo_count(int $personId): int
{
    $params = [':person' => $personId];
    $clause = visibility_clause('document', 'd', $params);

    return (int) db_value(
        "SELECT COUNT(*) FROM document_persons dp
         JOIN documents d ON d.id = dp.document_id
         WHERE dp.person_id = :person AND {$clause}",
        $params,
        0
    );
}

/** Témoignages audio visibles (documents dont le type MIME commence par audio/). */
function audio_documents(int $limit = 0, int $offset = 0, ?int &$total = null): array
{
    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $where  = "{$clause} AND d.mime LIKE 'audio/%'";

    $total = (int) db_value("SELECT COUNT(*) FROM documents d WHERE {$where}", $params, 0);

    $sql = "SELECT d.* FROM documents d WHERE {$where}
            ORDER BY d.doc_date_sort IS NULL, d.doc_date_sort DESC, d.created_at DESC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
    }

    return db_all($sql, $params);
}

/** Y a-t-il au moins un enregistrement sonore visible ? (pour le menu) */
function audio_exist(): bool
{
    static $existe = null;
    if ($existe !== null) {
        return $existe;
    }

    $params = [];
    $clause = visibility_clause('document', 'd', $params);
    $existe = (int) db_value(
        "SELECT COUNT(*) FROM documents d WHERE {$clause} AND d.mime LIKE 'audio/%'",
        $params,
        0
    ) > 0;

    return $existe;
}

/** Ce document est-il un son (témoignage, musique, interview) ? */
function document_is_audio(array $doc): bool
{
    return str_starts_with(strtolower((string) ($doc['mime'] ?? '')), 'audio/');
}
