-- ---------------------------------------------------------------------------
-- Histoire de famille — albums des journalistes « avec validation » (1.37.1)
--
-- En 1.37.0, un journaliste « avec validation » pouvait créer un album qui
-- paraissait tout de suite : les albums n'avaient pas d'état d'attente.
-- Cette migration corrige :
--
--   albums.moderation_state      « publie » (défaut) ou « attente », comme
--                                les articles, documents et recettes depuis
--                                la migration 016. Un album « attente » est
--                                invisible de tous les comptes (sauf les
--                                administrateurs, et son auteur par son
--                                adresse directe) jusqu'à validation.
--   albums.suggested_visibility  la visibilité demandée par le journaliste,
--                                appliquée par l'administrateur à la
--                                validation (l'album naît « prive »).
--
-- RATTRAPAGE (le seul UPDATE de cette migration, demandé explicitement) :
-- les albums déjà créés par un journaliste dont le réglage actuel est
-- « avec validation » repassent en attente et en privé, avec leur visibilité
-- d'origine gardée comme visibilité suggérée. Les albums des journalistes
-- « sans validation » et ceux des administrateurs ne sont pas touchés.
-- Aucune ligne n'est supprimée ; les deux colonnes sont en ajout seul, avec
-- « publie » par défaut : les albums existants restent publiés.
-- ---------------------------------------------------------------------------

ALTER TABLE albums ADD COLUMN moderation_state TEXT NOT NULL DEFAULT 'publie';
ALTER TABLE albums ADD COLUMN suggested_visibility TEXT;

CREATE INDEX idx_albums_moderation ON albums(moderation_state);

UPDATE albums
SET suggested_visibility = visibility,
    visibility = 'prive',
    moderation_state = 'attente'
WHERE moderation_state = 'publie'
  AND created_by IN (
      SELECT id FROM accounts
      WHERE role = 'famille'
        AND COALESCE(account_type, 'branche') = 'personne'
        AND journaliste_validation = 1
  );
