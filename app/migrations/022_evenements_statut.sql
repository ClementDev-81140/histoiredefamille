-- ---------------------------------------------------------------------------
-- Histoire de famille — événements : statut de validation et type (1.34.0)
--
-- Deux colonnes en AJOUT SEUL sur la table events (migration 020) :
--
--   status  « publie » (défaut) ou « en_attente ». Un événement proposé par
--           un journaliste naît « en_attente » : il n'apparaît nulle part —
--           ni calendrier, ni « Dans la famille », ni compteurs — tant qu'un
--           administrateur ne l'a pas validé. Tous les événements déjà
--           enregistrés deviennent « publie » : rien ne change pour eux.
--   type    le genre d'événement choisi dans le formulaire (« fete »,
--           « reunion », « voyage », « souvenir », « anniversaire »,
--           « autre » ; vide = non précisé, pour les événements d'avant).
--
-- Aucune donnée existante n'est touchée, rien n'est supprimé ni renommé.
-- ---------------------------------------------------------------------------

ALTER TABLE events ADD COLUMN status TEXT NOT NULL DEFAULT 'publie';
ALTER TABLE events ADD COLUMN type TEXT NOT NULL DEFAULT '';

CREATE INDEX idx_events_status ON events(status);
