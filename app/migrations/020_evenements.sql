-- ---------------------------------------------------------------------------
-- Histoire de famille — événements du calendrier (migration 020)
--
-- Jusqu'ici le calendrier ne montrait que les dates de l'arbre (naissances,
-- mariages, décès). Cette migration ajoute de VRAIS événements de famille :
-- fête, réunion, voyage, messe du souvenir… saisis dans l'administration.
--
-- Un événement a une date de début (texte « AAAA-MM-JJ »), une heure
-- facultative, une fin facultative, un lieu en texte libre, une description,
-- une visibilité (PRIVÉE par défaut) et une répétition annuelle facultative.
--
-- Les branches autorisées sont enregistrées dans content_branches avec
-- kind = 'event' : aucune nouvelle colonne, aucune table en double.
--
-- Migration EN AJOUT SEUL : une table, aucune donnée existante touchée.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT    NOT NULL,
    start_date  TEXT    NOT NULL,                 -- « 2026-06-12 »
    start_time  TEXT,                             -- « 15:00 » ou NULL
    end_date    TEXT,                             -- « 2026-06-13 » ou NULL
    end_time    TEXT,                             -- « 18:00 » ou NULL
    place       TEXT    NOT NULL DEFAULT '',      -- lieu en texte libre
    description TEXT    NOT NULL DEFAULT '',
    visibility  TEXT    NOT NULL DEFAULT 'prive'
                CHECK (visibility IN ('prive', 'public', 'famille', 'branches')),
    annual      INTEGER NOT NULL DEFAULT 0,       -- 1 = revient chaque année
    created_by  INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    created_at  INTEGER NOT NULL,
    updated_at  INTEGER NOT NULL
);
CREATE INDEX idx_events_date ON events(start_date, start_time);
CREATE INDEX idx_events_visibility ON events(visibility);
