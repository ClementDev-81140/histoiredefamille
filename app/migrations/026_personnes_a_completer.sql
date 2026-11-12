-- 1.41.0 : indicateur d'une fiche personne créée pour être complétée ensuite.
-- Ajout seul : aucune ligne existante n'est supprimée ou modifiée ; toutes
-- les fiches existantes gardent le comportement historique (valeur 0).
ALTER TABLE persons ADD COLUMN needs_completion INTEGER NOT NULL DEFAULT 0
    CHECK (needs_completion IN (0, 1));
