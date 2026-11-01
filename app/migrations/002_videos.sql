-- ---------------------------------------------------------------------------
-- Onglet « Vidéos » : mise en avant et ordre d'affichage.
-- Colonnes ajoutées à la table des documents (ne concernent que les vidéos).
-- Aucune donnée existante n'est modifiée ni supprimée.
-- ---------------------------------------------------------------------------

ALTER TABLE documents ADD COLUMN featured   INTEGER NOT NULL DEFAULT 0;
ALTER TABLE documents ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0;

CREATE INDEX idx_documents_videos ON documents(kind, featured, sort_order);
