-- ---------------------------------------------------------------------------
-- Histoire de famille — albums photos et personnes identifiées
--
-- Migration 005 : deux nouveautés, aucune donnée existante n'est modifiée.
--
--   albums            un album = un titre, une description, une visibilité,
--                     et des photos (les documents déjà en base).
--   album_documents   quelles photos sont dans quel album, et dans quel ordre.
--   document_persons  qui figure sur une photo (ou un témoignage audio).
--
-- Les photos d'un album gardent leur propre visibilité : une photo privée
-- glissée dans un album public reste invisible pour un visiteur.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE albums (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    title             TEXT    NOT NULL,
    slug              TEXT    NOT NULL UNIQUE,
    description       TEXT,
    cover_document_id INTEGER REFERENCES documents(id) ON DELETE SET NULL,
    visibility        TEXT    NOT NULL DEFAULT 'prive'
                      CHECK (visibility IN ('prive', 'public', 'famille', 'branches')),
    position          INTEGER NOT NULL DEFAULT 0,
    created_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    created_at        INTEGER NOT NULL,
    updated_at        INTEGER NOT NULL
);
CREATE INDEX idx_albums_visibility ON albums(visibility);
CREATE INDEX idx_albums_position ON albums(position, title);

CREATE TABLE album_documents (
    album_id    INTEGER NOT NULL REFERENCES albums(id) ON DELETE CASCADE,
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    position    INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (album_id, document_id)
);
CREATE INDEX idx_album_documents_document ON album_documents(document_id);

CREATE TABLE document_persons (
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    person_id   INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,
    PRIMARY KEY (document_id, person_id)
);
CREATE INDEX idx_document_persons_person ON document_persons(person_id);
