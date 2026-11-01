-- ---------------------------------------------------------------------------
-- 002 — Vidéos : mise en avant, ordre d'affichage, durée, téléchargement
--
-- Migration AJOUT SEULEMENT : aucune colonne ni donnée existante n'est
-- supprimée. Les vidéos restent des documents (table documents, kind = 'video'),
-- on ajoute juste les réglages utiles à l'onglet « Vidéos ».
--
-- Les valeurs par défaut sont choisies pour ne rien changer au comportement
-- actuel : aucune mise en avant, ordre 0 (donc tri par date), téléchargement
-- autorisé (comme avant).
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

-- 1 = affichée en tête de l'onglet Vidéos.
ALTER TABLE documents ADD COLUMN is_featured INTEGER NOT NULL DEFAULT 0;

-- Ordre croissant parmi les vidéos de même « mise en avant » (0 = non classée).
ALTER TABLE documents ADD COLUMN display_order INTEGER NOT NULL DEFAULT 0;

-- 1 = le bouton « Télécharger » est proposé (0 = lecture seule).
ALTER TABLE documents ADD COLUMN allow_download INTEGER NOT NULL DEFAULT 1;

-- Durée en secondes, si l'administrateur la saisit (aucun transcodage : on ne
-- lit pas le fichier, on se contente de ce qui est indiqué).
ALTER TABLE documents ADD COLUMN duration_seconds INTEGER;

-- Accélère le tri de l'onglet Vidéos.
CREATE INDEX IF NOT EXISTS idx_documents_videos
    ON documents(is_featured, display_order, created_at);
