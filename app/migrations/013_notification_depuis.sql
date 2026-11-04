-- ---------------------------------------------------------------------------
-- 013 — Depuis quand une personne accepte les nouveaux articles
--
-- Migration AJOUT SEULEMENT : aucune colonne et aucune donnée existante n'est
-- modifiée ni supprimée.
--
-- But : une personne ne doit recevoir que les articles publiés APRÈS le moment
-- où elle a coché « Prévenez-moi des nouveaux articles ». Sans cette date, un
-- nouvel inscrit recevait aussitôt les articles des 30 derniers jours — des
-- nouvelles qui ne sont pas neuves pour le site, et parfois déjà lues.
--
-- 0 = aucune inscription en cours (case décochée, ou compte jamais inscrit).
-- Décocher puis recocher repose la date : on repart du moment du nouvel accord.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

ALTER TABLE accounts ADD COLUMN notify_articles_depuis INTEGER NOT NULL DEFAULT 0;

-- Les personnes déjà inscrites au moment de la mise à jour gardent leur
-- abonnement : leur date d'accord est posée à l'instant de la migration. Sans
-- cette ligne, elles cesseraient toutes de recevoir les nouveaux articles.
UPDATE accounts
   SET notify_articles_depuis = CAST(strftime('%s', 'now') AS INTEGER)
 WHERE notify_articles = 1
   AND notify_articles_depuis = 0;
