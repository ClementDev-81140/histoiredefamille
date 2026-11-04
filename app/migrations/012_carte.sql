-- ---------------------------------------------------------------------------
-- 012 — Lieux de la carte
--
-- Migration AJOUT SEULEMENT : aucune colonne et aucune donnée existante ne sont
-- modifiées. Les champs de lieu du site (personnes : lieux de naissance et de
-- décès ; familles : lieu du mariage) restent des textes libres : cette table
-- ne fait que leur donner des coordonnées, sans jamais réécrire un texte.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS places (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nom        TEXT    NOT NULL,                    -- « Toulouse »
    pays       TEXT    NOT NULL DEFAULT 'France',
    lat        REAL,                                -- latitude (degrés décimaux)
    lon        REAL,                                -- longitude (degrés décimaux)
    code       TEXT    NOT NULL DEFAULT '',         -- code INSEE ou identifiant libre
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

-- Un même lieu ne peut pas être placé deux fois (comparaison sans casse).
CREATE UNIQUE INDEX IF NOT EXISTS idx_places_nom ON places(nom COLLATE NOCASE, pays COLLATE NOCASE);

-- Retrouver les lieux proches d'un point (regroupement des points voisins).
CREATE INDEX IF NOT EXISTS idx_places_geo ON places(lat, lon);
