-- ---------------------------------------------------------------------------
-- Histoire de famille — propositions des journalistes (migration 016)
--
-- Un journaliste peut proposer un article, un document (photo, vidéo…) ou une
-- recette. Sa proposition n'est JAMAIS publiée directement : elle naît « en
-- attente » et privée, puis un administrateur la valide (en choisissant la
-- visibilité) ou la refuse (avec un motif écrit).
--
-- Migration EN AJOUT SEUL, comme les précédentes :
--   - aucune colonne n'est supprimée ni renommée ;
--   - les contenus déjà en base reçoivent « publie » et restent donc publiés
--     exactement comme avant, avec la même visibilité ;
--   - validated_at reste vide pour eux : ils n'ont jamais eu besoin d'être
--     validés.
--
-- Trois tables sont concernées : articles, documents (photos, vidéos, sons,
-- PDF…) et recipes (recettes). Les pages d'informations, les albums, les
-- catégories et l'arbre restent réservés aux administrateurs.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

-- Les articles -------------------------------------------------------------
ALTER TABLE articles ADD COLUMN moderation_state    TEXT    NOT NULL DEFAULT 'publie';
ALTER TABLE articles ADD COLUMN moderation_note     TEXT    NOT NULL DEFAULT '';
ALTER TABLE articles ADD COLUMN submitted_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL;
ALTER TABLE articles ADD COLUMN submitted_at        INTEGER;
ALTER TABLE articles ADD COLUMN validated_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL;
ALTER TABLE articles ADD COLUMN validated_at        INTEGER;
ALTER TABLE articles ADD COLUMN suggested_visibility TEXT   NOT NULL DEFAULT '';

-- Les documents (photos, vidéos, sons, PDF…) ------------------------------
ALTER TABLE documents ADD COLUMN moderation_state    TEXT    NOT NULL DEFAULT 'publie';
ALTER TABLE documents ADD COLUMN moderation_note     TEXT    NOT NULL DEFAULT '';
ALTER TABLE documents ADD COLUMN submitted_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL;
ALTER TABLE documents ADD COLUMN submitted_at        INTEGER;
ALTER TABLE documents ADD COLUMN validated_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL;
ALTER TABLE documents ADD COLUMN validated_at        INTEGER;
ALTER TABLE documents ADD COLUMN suggested_visibility TEXT   NOT NULL DEFAULT '';

-- Les recettes -------------------------------------------------------------
ALTER TABLE recipes ADD COLUMN moderation_state    TEXT    NOT NULL DEFAULT 'publie';
ALTER TABLE recipes ADD COLUMN moderation_note     TEXT    NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN submitted_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL;
ALTER TABLE recipes ADD COLUMN submitted_at        INTEGER;
ALTER TABLE recipes ADD COLUMN validated_by        INTEGER REFERENCES accounts(id) ON DELETE SET NULL;
ALTER TABLE recipes ADD COLUMN validated_at        INTEGER;
ALTER TABLE recipes ADD COLUMN suggested_visibility TEXT   NOT NULL DEFAULT '';

CREATE INDEX idx_articles_moderation ON articles(moderation_state);
CREATE INDEX idx_documents_moderation ON documents(moderation_state);
CREATE INDEX idx_recipes_moderation ON recipes(moderation_state);
CREATE INDEX idx_articles_auteur ON articles(submitted_by);
CREATE INDEX idx_documents_auteur ON documents(submitted_by);
CREATE INDEX idx_recipes_auteur ON recipes(submitted_by);
