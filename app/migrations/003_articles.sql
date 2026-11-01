-- ---------------------------------------------------------------------------
-- Rubrique « Articles » : fil d'actualité de la famille (type journal).
-- Un article a une visibilité (comme les documents et les pages), un statut
-- (brouillon / publié) et une date de publication, qui peut être dans le
-- futur (publication programmée). Les branches autorisées sont enregistrées
-- dans content_branches avec kind = 'article'.
-- ---------------------------------------------------------------------------

CREATE TABLE articles (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    slug         TEXT    NOT NULL UNIQUE,
    title        TEXT    NOT NULL,
    summary      TEXT    NOT NULL DEFAULT '',      -- chapô (résumé court)
    body         TEXT    NOT NULL DEFAULT '',
    category     TEXT    NOT NULL DEFAULT '',      -- « Souvenirs », « Nouvelles »…
    image_id     INTEGER REFERENCES documents(id) ON DELETE SET NULL,  -- image à la une
    visibility   TEXT    NOT NULL DEFAULT 'prive'
                 CHECK (visibility IN ('prive', 'public', 'famille', 'branches')),
    status       TEXT    NOT NULL DEFAULT 'brouillon'
                 CHECK (status IN ('brouillon', 'publie')),
    published_at INTEGER NOT NULL,                 -- date affichée ; future = programmé
    author_id    INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    created_at   INTEGER NOT NULL,
    updated_at   INTEGER NOT NULL
);
CREATE INDEX idx_articles_publication ON articles(status, published_at DESC);
CREATE INDEX idx_articles_category ON articles(category);
