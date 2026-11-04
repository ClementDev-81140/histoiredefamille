-- ---------------------------------------------------------------------------
-- 014 — Envoi de gros fichiers par morceaux
--
-- Migration AJOUT SEULEMENT : aucune colonne et aucune donnée existante ne sont
-- modifiées, rien n'est supprimé. On ajoute :
--   1. la table `uploads` (un envoi en cours ou terminé) ;
--   2. une colonne `sha256` sur les documents, pour reconnaître un fichier
--      déjà présent (doublon) sans relire tout le stockage.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS uploads (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    token         TEXT    NOT NULL UNIQUE,          -- 32 caractères hexadécimaux (128 bits)
    account_id    INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    original_name TEXT    NOT NULL,                 -- nom nettoyé, jamais un chemin
    size          INTEGER NOT NULL,                 -- taille annoncée par le navigateur
    mime_client   TEXT    NOT NULL DEFAULT '',      -- type annoncé (jamais cru sur parole)
    chunk_size    INTEGER NOT NULL,                 -- taille de morceau retenue
    chunks_total  INTEGER NOT NULL,
    state         TEXT    NOT NULL DEFAULT 'en_cours'
                  CHECK (state IN ('en_cours', 'doublon', 'pret', 'termine', 'annule', 'echec')),
    creer_fiche   INTEGER NOT NULL DEFAULT 1,       -- 0 = le fichier attend un formulaire
    sha256        TEXT    NOT NULL DEFAULT '',
    rel_path      TEXT    NOT NULL DEFAULT '',
    thumb_path    TEXT    NOT NULL DEFAULT '',
    ext           TEXT    NOT NULL DEFAULT '',
    mime          TEXT    NOT NULL DEFAULT '',
    kind          TEXT    NOT NULL DEFAULT 'document',
    width         INTEGER,
    height        INTEGER,
    date_meta     TEXT    NOT NULL DEFAULT '',      -- date lue dans le fichier, si connue
    document_id   INTEGER REFERENCES documents(id) ON DELETE SET NULL,
    message       TEXT    NOT NULL DEFAULT '',      -- explication en français, si échec
    created_at    INTEGER NOT NULL,
    updated_at    INTEGER NOT NULL,
    finished_at   INTEGER
);

-- Retrouver les envois d'un compte, et ceux qui traînent (nettoyage).
CREATE INDEX IF NOT EXISTS idx_uploads_compte ON uploads(account_id, state, updated_at);
CREATE INDEX IF NOT EXISTS idx_uploads_etat ON uploads(state, updated_at);

-- Empreinte du contenu : reconnaître un doublon sans relire les fichiers.
ALTER TABLE documents ADD COLUMN sha256 TEXT NOT NULL DEFAULT '';
CREATE INDEX IF NOT EXISTS idx_documents_sha256 ON documents(sha256);
