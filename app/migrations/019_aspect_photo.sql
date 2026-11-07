-- ---------------------------------------------------------------------------
-- Histoire de famille — aspect « photo ancienne » (migration 019)
--
-- L'administrateur peut donner aux photos un rendu sépia (aucun / doux /
-- marqué). Ce filtre est un filtre d'AFFICHAGE : le fichier d'origine n'est
-- jamais retouché, et les téléchargements comme l'impression restent en
-- couleurs.
--
-- Chaque photo (ou document image) peut s'écarter du réglage du site :
--   suivre     suit le réglage global (valeur par défaut) ;
--   forcer     applique le filtre même si le site est en « aucun » ;
--   desactiver garde la photo en couleurs malgré un réglage global.
--
-- Migration EN AJOUT SEUL : une colonne, une valeur par défaut. Aucun contenu
-- existant n'est modifié, et sans cette migration le site fonctionne comme
-- avant (toutes les photos suivent le réglage du site).
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

ALTER TABLE documents ADD COLUMN vintage_aspect TEXT NOT NULL DEFAULT 'suivre';
