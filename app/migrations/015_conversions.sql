-- ---------------------------------------------------------------------------
-- 015 — Conversion des vidéos en MP4 (H.264 + AAC)
--
-- Migration AJOUT SEULEMENT : une table neuve et deux index. Aucune colonne,
-- aucune donnée existante n'est touchée ; les vidéos déjà en ligne continuent
-- de fonctionner exactement comme avant.
--
-- Une ligne par vidéo à convertir. La table sert de file d'attente : la
-- commande « php app/cli/convertir.php » prend la ligne la plus ancienne
-- encore en attente, une seule à la fois (le Raspberry Pi n'a pas la puissance
-- de convertir deux films en même temps).
--
-- Un enregistrement ne disparaît jamais tout seul : une conversion terminée
-- reste listée (avec le nom du fichier d'origine, qui n'est pas effacé sauf
-- réglage explicite), une conversion en échec aussi, avec sa raison.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE conversions (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Une seule conversion en vigueur par document : replanifier la remplace.
    document_id      INTEGER NOT NULL UNIQUE
                     REFERENCES documents(id) ON DELETE CASCADE,
    -- attente | en_cours | terminee | echec | ignoree
    state            TEXT    NOT NULL DEFAULT 'attente',
    attempts         INTEGER NOT NULL DEFAULT 0,
    progress         INTEGER NOT NULL DEFAULT 0,   -- 0 à 100
    message          TEXT    NOT NULL DEFAULT '',
    -- Fichier d'origine (dans le stockage) et fichier produit.
    source_path      TEXT    NOT NULL,
    source_size      INTEGER NOT NULL DEFAULT 0,
    target_path      TEXT,
    -- Conservé même quand l'original est supprimé : c'est la trace de ce qui
    -- a été converti, et depuis quel nom.
    original_name    TEXT    NOT NULL DEFAULT '',
    original_ext     TEXT    NOT NULL DEFAULT '',
    duration_seconds INTEGER,
    requested_by     INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    started_at       INTEGER,
    finished_at      INTEGER,
    created_at       INTEGER NOT NULL,
    updated_at       INTEGER NOT NULL
);

-- La file d'attente : la plus ancienne ligne en attente d'abord.
CREATE INDEX idx_conversions_file ON conversions(state, created_at);

-- Les conversions terminées récemment (page « Conversions », purge).
CREATE INDEX idx_conversions_fin ON conversions(finished_at);
