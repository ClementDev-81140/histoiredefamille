-- ---------------------------------------------------------------------------
-- Histoire de famille — versions des propositions (migration 017)
--
-- Un journaliste peut modifier une proposition DÉJÀ VALIDÉE. Jusqu'ici, sa
-- modification écrivait par-dessus le contenu publié : le texte visible des
-- lecteurs changeait donc avant toute relecture, ou disparaissait le temps de
-- la validation. Ce n'est pas ce que promet l'écran « Mes propositions ».
--
-- À partir de cette migration, une modification d'un contenu publié crée une
-- VERSION EN ATTENTE : une ligne supplémentaire, qui porte le nouveau texte et
-- reste invisible (état « attente »), tandis que la ligne d'origine continue
-- d'être servie telle quelle. À la validation, le texte de la version remplace
-- celui de l'original ; en cas de refus, l'original n'est pas touché.
--
-- Migration EN AJOUT SEUL :
--   - une seule colonne par table, vide par défaut ;
--   - aucun contenu existant n'est modifié : replaces_id NULL signifie
--     « ceci n'est pas une version » ;
--   - sans cette migration, le site continue de fonctionner (les
--     modifications s'appliquent directement, comme avant).
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

ALTER TABLE articles  ADD COLUMN replaces_id INTEGER;
ALTER TABLE documents ADD COLUMN replaces_id INTEGER;
ALTER TABLE recipes   ADD COLUMN replaces_id INTEGER;
