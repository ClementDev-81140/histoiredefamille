-- ---------------------------------------------------------------------------
-- Histoire de famille — recettes de famille
--
-- Migration 006 : une table, aucune donnée existante modifiée.
--
-- Une recette est un contenu à part entière (titre, origine, ingrédients,
-- préparation, photo), avec la même visibilité que les documents. Elle vit dans
-- sa propre table pour rester simple à saisir et à imprimer.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE recipes (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    title             TEXT    NOT NULL,
    slug              TEXT    NOT NULL UNIQUE,
    origin            TEXT,                    -- « Recette de Mamie », « Cuisine du Sud-Ouest »
    servings          TEXT,                    -- « 6 personnes », « 2 litres »
    ingredients       TEXT    NOT NULL DEFAULT '',   -- une ligne par ingrédient
    preparation       TEXT    NOT NULL DEFAULT '',   -- texte simple, sauts de ligne conservés
    notes             TEXT,                    -- astuce, variante, souvenir
    photo_document_id INTEGER REFERENCES documents(id) ON DELETE SET NULL,
    visibility        TEXT    NOT NULL DEFAULT 'prive'
                      CHECK (visibility IN ('prive', 'public', 'famille', 'branches')),
    created_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    created_at        INTEGER NOT NULL,
    updated_at        INTEGER NOT NULL
);
CREATE INDEX idx_recipes_visibility ON recipes(visibility);
CREATE INDEX idx_recipes_title ON recipes(title COLLATE NOCASE);
