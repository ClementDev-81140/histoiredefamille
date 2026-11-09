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

    $album = db_one(
        "SELECT * FROM albums a
         WHERE a.slug = :slug AND {$clause}",
        $params + [':slug' => $slug]
    );
    if ($album !== null) {
        return $album;
    }

    // L'auteur d'un album en attente de validation (1.37.1) peut le relire
    // par son adresse directe — personne d'autre. Il n'apparaît dans
    // aucune liste ni aucun compteur tant qu'il n'est pas validé.
    $compte = current_account();
    if ($compte !== null && album_moderation_faite()) {
        $attente = db_one('SELECT * FROM albums WHERE slug = ?', [$slug]);
        if ($attente !== null
            && (string) ($attente['moderation_state'] ?? 'publie') === 'attente'
            && (int) ($attente['created_by'] ?? 0) === (int) $compte['id']) {
            return $attente;
        }
    }

    return null;
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
function albums_visible(int $limit = 0, int $offset = 0, ?int &$total = null, int $categorieId = 0): array
{
    $params = [];
    $clause = album_visible_clause('a', $params);
    $catClause = category_where('a', $categorieId > 0 ? $categorieId : null, $params, 'album');
    if ($catClause !== '') {
        $clause .= ' AND ' . $catClause;
    }
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

/**
 * Ajoute une photo à la fin d'un album.
 *
 * Utilisé par l'envoi direct dans un album : la photo arrive après celles
 * déjà en place, comme si on l'avait cochée en bas de la liste. Si elle y est
 * déjà, rien ne change (clé primaire album_id + document_id).
 */
function album_ajouter_photo(int $albumId, int $documentId): void
{
    if ($albumId <= 0 || $documentId <= 0) {
        return;
    }

    $position = (int) db_value(
        'SELECT COALESCE(MAX(position), -1) + 1 FROM album_documents WHERE album_id = ?',
        [$albumId],
        0
    );

    db_exec(
        'INSERT OR IGNORE INTO album_documents (album_id, document_id, position) VALUES (?, ?, ?)',
        [$albumId, $documentId, $position]
    );
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
// Verser dans un album des documents déjà présents sur le site (1.30.0)
// ---------------------------------------------------------------------------

/**
 * Filtres de la liste « Ajouter depuis les documents du site ».
 *
 * Rien n'est deviné : un type ou une visibilité inconnus retombent sur la
 * valeur sûre, et un thème qui n'existe pas est ignorée. La case « pas
 * encore dans cet album » est cochée par défaut — l'absence du paramètre veut
 * dire « coché », et le formulaire envoie `pas_album=0` quand on la décoche.
 *
 * @return array{q:string, type:string, visibilite:string, categorie:int, pas_album:bool, page:int}
 */
function album_documents_filtres(): array
{
    // Par défaut, les photos ; « Tous les documents » est un choix explicite.
    $type = isset($_GET['type']) ? query('type') : 'photo';
    if ($type !== '' && $type !== 'photo') {
        $type = 'photo';
    }

    $visibilite = query('visibilite');
    if (!array_key_exists($visibilite, visibility_levels())) {
        $visibilite = '';
    }

    $categorie = query_int('categorie', 0);
    if ($categorie > 0 && find_category($categorie) === null) {
        $categorie = 0;
    }

    $pasAlbum = !isset($_GET['pas_album']) || query('pas_album') !== '0';

    return [
        'q'          => mb_substr(query('q'), 0, 100, 'UTF-8'),
        'type'       => $type,
        'visibilite' => $visibilite,
        'categorie'  => $categorie,
        'pas_album'  => $pasAlbum,
        'page'       => max(1, query_int('page', 1)),
    ];
}

/**
 * Documents que l'on peut verser dans un album : les photos d'abord, puis les
 * autres documents, du plus récent au plus ancien.
 *
 * Aucune règle de droits ici : la page est celle de l'administration, qui voit
 * tout. Ce sont les documents eux-mêmes qui gardent leur visibilité — verser
 * dans un album ne la change pas.
 *
 * @return array{documents:list<array>, total:int, pagination:array, dansAlbum:list<int>}
 */
function album_documents_candidats(int $albumId, array $filtres): array
{
    $params = [];
    $where  = ['d.is_attachment = 0'];

    if (($filtres['type'] ?? '') === 'photo') {
        $where[] = "d.kind = 'photo'";
    }

    if (($filtres['visibilite'] ?? '') !== '') {
        $where[] = 'd.visibility = :vis';
        $params[':vis'] = (string) $filtres['visibilite'];
    }

    if ((int) ($filtres['categorie'] ?? 0) > 0) {
        $where[] = 'd.category_id = :categorie';
        $params[':categorie'] = (int) $filtres['categorie'];
    }

    if (($filtres['q'] ?? '') !== '') {
        $where[] = "(d.title LIKE :q OR d.description LIKE :q OR d.original_name LIKE :q
                     OR EXISTS (SELECT 1 FROM document_tags dt
                                JOIN tags t ON t.id = dt.tag_id
                                WHERE dt.document_id = d.id AND t.name LIKE :q))";
        $params[':q'] = '%' . (string) $filtres['q'] . '%';
    }

    if (!empty($filtres['pas_album'])) {
        $where[] = 'NOT EXISTS (SELECT 1 FROM album_documents ad
                                 WHERE ad.album_id = :album AND ad.document_id = d.id)';
        $params[':album'] = $albumId;
    }

    $whereSql = implode(' AND ', $where);

    $total = (int) db_value("SELECT COUNT(*) FROM documents d WHERE {$whereSql}", $params, 0);
    $pg    = pagination($total, 24, (int) ($filtres['page'] ?? 1));

    $documents = db_all(
        "SELECT d.id, d.title, d.kind, d.ext, d.visibility, d.doc_date, d.created_at,
                d.thumb_path, c.name AS category_name
         FROM documents d
         LEFT JOIN categories c ON c.id = d.category_id
         WHERE {$whereSql}
         ORDER BY (d.kind = 'photo') DESC, d.created_at DESC, d.id DESC
         LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
        $params
    );

    return [
        'documents'  => $documents,
        'total'      => $total,
        'pagination' => $pg,
        'dansAlbum'  => album_photo_ids($albumId),
    ];
}

/**
 * Ajoute des documents à la fin d'un album, dans l'ordre reçu.
 *
 * Chaque identifiant est vérifié côté serveur : il doit désigner un document
 * qui existe et qui n'est pas une pièce jointe de fiche. Un document déjà dans
 * l'album est ignoré sans erreur (et compte dans `ignores`), de même qu'un
 * identifiant inconnu. Rien d'autre ne vient du navigateur.
 *
 * @return array{ajoutes:list<int>, ignores:list<int>}
 */
function album_ajouter_documents(int $albumId, array $documentIds): array
{
    $ajoutes = [];
    $ignores = [];

    if ($albumId <= 0) {
        return ['ajoutes' => [], 'ignores' => array_map('intval', (array) $documentIds)];
    }

    $deja = album_photo_ids($albumId);

    foreach ((array) $documentIds as $valeur) {
        $documentId = (int) $valeur;
        if ($documentId <= 0) {
            continue;
        }
        if (in_array($documentId, $deja, true)) {
            $ignores[] = $documentId;
            continue;
        }
        if (db_one('SELECT id FROM documents WHERE id = ? AND is_attachment = 0', [$documentId]) === null) {
            $ignores[] = $documentId;
            continue;
        }

        // MAX(position) + 1 : la sélection arrive après ce qui est déjà là,
        // dans l'ordre où elle a été cochée.
        album_ajouter_photo($albumId, $documentId);
        $deja[]    = $documentId;
        $ajoutes[] = $documentId;
    }

    if ($ajoutes !== []) {
        db_update('albums', $albumId, ['updated_at' => time()]);
    }

    return ['ajoutes' => $ajoutes, 'ignores' => $ignores];
}

/** Rang de visibilité : 0 = le plus ouvert (public), 3 = le plus fermé. */
function album_visibilite_rang(?string $niveau): int
{
    return ['public' => 0, 'famille' => 1, 'branches' => 2, 'prive' => 3][$niveau ?? ''] ?? 3;
}

/**
 * Ce document est-il moins visible que cet album ?
 *
 * Vrai quand le document est plus fermé que l'album : la personne qui voit
 * l'album ne verra pas ce document dedans. Deux fois « branches » demandent
 * une comparaison des branches : si l'album couvre une branche que le document
 * ne couvre pas, une partie de la famille ne verra pas la photo.
 */
function document_moins_visible_que_album(array $document, array $album): bool
{
    $rangDocument = album_visibilite_rang((string) ($document['visibility'] ?? 'prive'));
    $rangAlbum    = album_visibilite_rang((string) ($album['visibility'] ?? 'prive'));

    if ($rangDocument !== $rangAlbum) {
        return $rangDocument > $rangAlbum;
    }

    if ($rangDocument === 2) {
        $branchesAlbum    = content_branch_ids('album', (int) ($album['id'] ?? 0));
        $branchesDocument = content_branch_ids('document', (int) ($document['id'] ?? 0));
        return array_diff($branchesAlbum, $branchesDocument) !== [];
    }

    return false;
}

/**
 * Documents d'un album qui restent moins visibles que lui.
 *
 * @return list<array>
 */
function album_documents_moins_visibles(int $albumId): array
{
    $album = find_album($albumId);
    if ($album === null) {
        return [];
    }

    $documents = db_all(
        "SELECT d.id, d.title, d.kind, d.visibility, d.thumb_path, d.doc_date
         FROM album_documents ad
         JOIN documents d ON d.id = ad.document_id
         WHERE ad.album_id = ?
         ORDER BY ad.position, d.id",
        [$albumId]
    );

    return array_values(array_filter(
        $documents,
        static fn (array $document): bool => document_moins_visible_que_album($document, $album)
    ));
}

/**
 * Donne à des documents de cet album la visibilité de l'album.
 *
 * Action séparée, jamais automatique : c'est l'administrateur qui décide.
 * Les identifiants sont vérifiés (le document doit être dans CET album).
 *
 * @return int nombre de documents effectivement modifiés
 */
function album_documents_donner_visibilite(int $albumId, array $documentIds): int
{
    $album = find_album($albumId);
    if ($album === null) {
        return 0;
    }

    $dansAlbum = album_photo_ids($albumId);
    $visibilite = (string) $album['visibility'];
    $branches   = $visibilite === 'branches' ? content_branch_ids('album', $albumId) : [];
    $modifies   = 0;

    foreach ((array) $documentIds as $valeur) {
        $documentId = (int) $valeur;
        if ($documentId <= 0 || !in_array($documentId, $dansAlbum, true)) {
            continue;
        }
        if (db_one('SELECT id FROM documents WHERE id = ?', [$documentId]) === null) {
            continue;
        }

        db_update('documents', $documentId, [
            'visibility' => $visibilite,
            'updated_at' => time(),
        ]);
        if ($branches !== []) {
            set_content_branches('document', $documentId, $branches);
        }
        $modifies++;
    }

    return $modifies;
}

/**
 * Phrase d'avertissement après un ajout : « 2 photos restent privées : la
 * famille ne les verra pas dans l'album. »
 *
 * @param list<array> $documents documents restés moins visibles que l'album
 */
function album_message_moins_visibles(array $documents, array $album): string
{
    $n = count($documents);
    if ($n === 0) {
        return '';
    }

    $photos = true;
    foreach ($documents as $document) {
        if ((string) ($document['kind'] ?? '') !== 'photo') {
            $photos = false;
        }
    }

    $sujet = $photos
        ? $n . ' photo' . ($n > 1 ? 's' : '')
        : $n . ' document' . ($n > 1 ? 's' : '');
    $verbe = $n > 1 ? 'restent' : 'reste';

    $tousPrives = true;
    foreach ($documents as $document) {
        if ((string) ($document['visibility'] ?? '') !== 'prive') {
            $tousPrives = false;
        }
    }

    if ($tousPrives) {
        $adjectif = $photos ? ($n > 1 ? 'privées' : 'privée') : ($n > 1 ? 'privés' : 'privé');
        $pronom   = $n > 1 ? 'les' : ($photos ? 'la' : 'le');
        return $sujet . ' ' . $verbe . ' ' . $adjectif
            . ' : la famille ne ' . $pronom . ' verra pas dans l’album.';
    }

    $niveau = visibility_label((string) ($album['visibility'] ?? 'prive'));
    $pronom = $n > 1 ? 'les' : 'le';
    return $sujet . ' ' . $verbe . ' moins visible' . ($n > 1 ? 's' : '')
        . ' que l’album (' . $niveau . ') : une partie de la famille ne ' . $pronom
        . ' verra pas dans l’album.';
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
