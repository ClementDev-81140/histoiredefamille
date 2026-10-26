-- ---------------------------------------------------------------------------
-- Histoire de famille — schéma initial de la base (SQLite)
--
-- Ce fichier est appliqué automatiquement par « php app/cli/migrate.php ».
-- Ne modifiez jamais un fichier de migration déjà appliqué : ajoutez-en un
-- nouveau (002_xxx.sql, 003_xxx.sql…).
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

-- Branches familiales : un compte de connexion par branche.
CREATE TABLE branches (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        TEXT    NOT NULL UNIQUE COLLATE NOCASE,  -- identifiant de connexion (majuscules)
    name        TEXT    NOT NULL,                        -- nom affiché
    description TEXT,
    see_all     INTEGER NOT NULL DEFAULT 0,              -- 1 = voit toutes les branches
    is_active   INTEGER NOT NULL DEFAULT 1,
    created_at  INTEGER NOT NULL,
    updated_at  INTEGER NOT NULL
);

-- Comptes : administrateurs (personnels) et comptes de branche (partagés).
CREATE TABLE accounts (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    display_name  TEXT    NOT NULL DEFAULT '',
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL CHECK (role IN ('admin', 'famille')),
    branch_id     INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    is_active     INTEGER NOT NULL DEFAULT 1,
    last_login_at INTEGER,
    created_at    INTEGER NOT NULL,
    updated_at    INTEGER NOT NULL
);
CREATE UNIQUE INDEX idx_accounts_branch ON accounts(branch_id) WHERE branch_id IS NOT NULL;
CREATE INDEX idx_accounts_role ON accounts(role, is_active);

-- Tentatives de connexion (limitation des essais par identifiant et par IP).
CREATE TABLE login_attempts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier TEXT    NOT NULL,
    ip         TEXT    NOT NULL,
    success    INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL
);
CREATE INDEX idx_attempts_identifier ON login_attempts(identifier, created_at);
CREATE INDEX idx_attempts_ip ON login_attempts(ip, created_at);

-- Catégories de documents.
CREATE TABLE categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    slug       TEXT    NOT NULL UNIQUE,
    position   INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL
);

-- Documents : photos, vidéos, PDF et autres fichiers.
-- Les fichiers eux-mêmes sont stockés HORS de la racine web.
CREATE TABLE documents (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    title            TEXT    NOT NULL,
    description      TEXT,
    doc_date         TEXT,                    -- date libre : « 1912 », « vers 1930 »…
    doc_date_sort    INTEGER,                 -- année pour le tri (si connue)
    category_id      INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    kind             TEXT    NOT NULL DEFAULT 'document'
                     CHECK (kind IN ('photo', 'video', 'document')),
    visibility       TEXT    NOT NULL DEFAULT 'prive'
                     CHECK (visibility IN ('prive', 'public', 'famille', 'branches')),
    rel_path         TEXT    NOT NULL,        -- chemin relatif dans le stockage
    thumb_path       TEXT,                    -- miniature (photos)
    original_name    TEXT    NOT NULL,
    mime             TEXT    NOT NULL,
    ext              TEXT    NOT NULL,
    size             INTEGER NOT NULL DEFAULT 0,
    width            INTEGER,
    height           INTEGER,
    is_attachment    INTEGER NOT NULL DEFAULT 0,  -- photo rattachée à une personne
    downloaded_count INTEGER NOT NULL DEFAULT 0,
    created_by       INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    created_at       INTEGER NOT NULL,
    updated_at       INTEGER NOT NULL
);
CREATE INDEX idx_documents_visibility ON documents(visibility);
CREATE INDEX idx_documents_kind ON documents(kind, is_attachment);
CREATE INDEX idx_documents_category ON documents(category_id);
CREATE INDEX idx_documents_created ON documents(created_at DESC);

-- Étiquettes (tags) et liaison avec les documents.
CREATE TABLE tags (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE
);

CREATE TABLE document_tags (
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    tag_id      INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (document_id, tag_id)
);

-- Branches associées à un contenu (documents, pages, personnes).
-- kind : 'document' | 'page' | 'person'
CREATE TABLE content_branches (
    kind       TEXT    NOT NULL,
    content_id INTEGER NOT NULL,
    branch_id  INTEGER NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    PRIMARY KEY (kind, content_id, branch_id)
);
CREATE INDEX idx_content_branches_content ON content_branches(kind, content_id);
CREATE INDEX idx_content_branches_branch ON content_branches(branch_id);

-- Pages d'informations (histoire de la famille, annonces…).
CREATE TABLE pages (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    slug         TEXT    NOT NULL UNIQUE,
    title        TEXT    NOT NULL,
    body         TEXT    NOT NULL DEFAULT '',
    visibility   TEXT    NOT NULL DEFAULT 'prive'
                 CHECK (visibility IN ('prive', 'public', 'famille', 'branches')),
    is_published INTEGER NOT NULL DEFAULT 0,
    show_in_nav  INTEGER NOT NULL DEFAULT 0,
    position     INTEGER NOT NULL DEFAULT 0,
    created_at   INTEGER NOT NULL,
    updated_at   INTEGER NOT NULL,
    updated_by   INTEGER REFERENCES accounts(id) ON DELETE SET NULL
);

-- Arbre généalogique : personnes.
CREATE TABLE persons (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    gedcom_id         TEXT,                     -- identifiant d'origine (import GEDCOM)
    first_name        TEXT NOT NULL DEFAULT '',
    last_name         TEXT NOT NULL DEFAULT '',
    sex               TEXT CHECK (sex IN ('M', 'F') OR sex IS NULL),
    birth_date        TEXT,
    birth_year        INTEGER,
    birth_place       TEXT,
    death_date        TEXT,
    death_year        INTEGER,
    death_place       TEXT,
    notes             TEXT,
    photo_document_id INTEGER REFERENCES documents(id) ON DELETE SET NULL,
    visibility        TEXT NOT NULL DEFAULT 'famille'
                      CHECK (visibility IN ('prive', 'public', 'famille', 'branches')),
    created_at        INTEGER NOT NULL,
    updated_at        INTEGER NOT NULL
);
CREATE INDEX idx_persons_name ON persons(last_name, first_name);
CREATE INDEX idx_persons_visibility ON persons(visibility);
CREATE UNIQUE INDEX idx_persons_gedcom ON persons(gedcom_id) WHERE gedcom_id IS NOT NULL;

-- Familles (couples) et enfants.
CREATE TABLE families (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    gedcom_id      TEXT,
    husband_id     INTEGER REFERENCES persons(id) ON DELETE SET NULL,
    wife_id        INTEGER REFERENCES persons(id) ON DELETE SET NULL,
    marriage_date  TEXT,
    marriage_place TEXT,
    notes          TEXT,
    created_at     INTEGER NOT NULL,
    updated_at     INTEGER NOT NULL
);
CREATE UNIQUE INDEX idx_families_gedcom ON families(gedcom_id) WHERE gedcom_id IS NOT NULL;

CREATE TABLE family_children (
    family_id INTEGER NOT NULL REFERENCES families(id) ON DELETE CASCADE,
    person_id INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,
    PRIMARY KEY (family_id, person_id)
);

-- Journal des actions d'administration.
CREATE TABLE activity_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    action     TEXT    NOT NULL,
    details    TEXT,
    ip         TEXT,
    created_at INTEGER NOT NULL
);
CREATE INDEX idx_activity_created ON activity_log(created_at DESC);

-- Réglages simples modifiables depuis l'administration.
CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
