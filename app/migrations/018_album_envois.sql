-- ---------------------------------------------------------------------------
-- Histoire de famille — ajouter des photos directement dans un album
-- (migration 018)
--
-- Jusqu'ici, une photo envoyée par morceaux créait une fiche privée, puis il
-- fallait la retrouver dans « Documents & photos » et la cocher dans l'album.
-- La page d'un album propose maintenant « Ajouter des photos » : le fichier
-- envoyé devient une photo de CET album, tout de suite.
--
-- Migration EN AJOUT SEUL :
--   - une seule colonne, vide par défaut sur les envois existants ;
--   - aucun contenu existant n'est modifié ;
--   - sans cette migration, le site continue de fonctionner : les envois
--     ordinaires ne connaissent simplement pas d'album.
--
-- `album_id` est la trace de la destination choisie au dépôt du fichier : un
-- envoi n'est rattaché qu'à UN album, et cette valeur n'est jamais relue du
-- navigateur ensuite (elle ne sert qu'à créer la photo et à la ranger).
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

ALTER TABLE uploads ADD COLUMN album_id INTEGER REFERENCES albums(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_uploads_album ON uploads(album_id, state);
