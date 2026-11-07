-- ---------------------------------------------------------------------------
-- Histoire de famille — catégories par type de contenu (migration 021)
--
-- Jusqu'ici une catégorie ne servait qu'aux documents, et un document n'en
-- avait qu'une (documents.category_id). Cette migration ouvre les catégories
-- aux six types de contenu du site — article, document, photo, album, vidéo,
-- recette — et permet d'en donner PLUSIEURS à un même contenu.
--
--   categories.kinds     les types auxquels la catégorie s'applique, séparés
--                        par des virgules. Valeur par défaut : les six types
--                        (« tous »), pour ne rien retirer aux catégories
--                        existantes.
--   category_links       la liaison (catégorie, type, contenu). Plusieurs
--                        lignes par contenu, donc plusieurs catégories.
--
-- MIGRATION EN AJOUT SEUL — aucune perte :
--   1. la colonne `documents.category_id` reste en place (les écrans qui la
--      lisent continuent de fonctionner) ;
--   2. chaque catégorie déjà posée sur un document est recopiée telle quelle
--      dans category_links : aucun document ne perd sa catégorie ;
--   3. rien n'est supprimé, rien n'est renommé.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

ALTER TABLE categories ADD COLUMN kinds TEXT NOT NULL DEFAULT 'article,document,photo,album,video,recette';

CREATE TABLE category_links (
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    kind        TEXT    NOT NULL,
    content_id  INTEGER NOT NULL,
    PRIMARY KEY (category_id, kind, content_id)
);

CREATE INDEX idx_category_links_contenu ON category_links(kind, content_id);

-- Les catégories déjà attribuées à des documents deviennent des liaisons : le
-- type est celui du document (photo, vidéo ou document).
INSERT OR IGNORE INTO category_links (category_id, kind, content_id)
SELECT category_id,
       CASE WHEN kind IN ('photo', 'video', 'document') THEN kind ELSE 'document' END,
       id
  FROM documents
 WHERE category_id IS NOT NULL;
