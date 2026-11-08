-- ---------------------------------------------------------------------------
-- Histoire de famille — archives et dossiers d'archives (migration 023, 1.36.0)
--
-- MIGRATION EN AJOUT SEUL : aucune colonne, aucune donnée existante ne sont
-- modifiées, rien n'est supprimé ni renommé.
--
--   dossiers            les dossiers d'archives créés par un administrateur
--                       (nom, description facultative, personne de l'arbre
--                       facultative). Supprimer un dossier ne supprime jamais
--                       ses documents : ils deviennent « sans dossier »
--                       (ON DELETE SET NULL).
--   documents.est_archive  1 = document « d'archive » : jamais à la une,
--                       jamais dans les nouveautés ni la gazette, mais
--                       toujours régi par sa visibilité habituelle et
--                       retrouvable dans Documents, la recherche, son album
--                       et son dossier. 0 (défaut) = comportement actuel :
--                       tous les documents existants restent « journal ».
--   documents.dossier_id   le dossier où le document est rangé (un seul).
--   uploads.archive        le choix « Classer en archive » fait dès l'envoi
--                       par morceaux, repris quand la fiche est créée.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE dossiers (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    description TEXT,
    person_id   INTEGER REFERENCES persons(id) ON DELETE SET NULL,
    created_by  INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    created_at  INTEGER NOT NULL,
    updated_at  INTEGER NOT NULL
);

ALTER TABLE documents ADD COLUMN est_archive INTEGER NOT NULL DEFAULT 0;
ALTER TABLE documents ADD COLUMN dossier_id INTEGER REFERENCES dossiers(id) ON DELETE SET NULL;

ALTER TABLE uploads ADD COLUMN archive INTEGER NOT NULL DEFAULT 0;

CREATE INDEX idx_documents_archive ON documents(est_archive, created_at DESC);
CREATE INDEX idx_documents_dossier ON documents(dossier_id);
