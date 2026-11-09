-- ---------------------------------------------------------------------------
-- Histoire de famille — droits réglables des journalistes (migration 024, 1.37.0)
--
-- MIGRATION EN AJOUT SEUL : aucune colonne, aucune donnée existante ne sont
-- modifiées, rien n'est supprimé ni renommé.
--
--   accounts.journaliste_validation     1 (défaut) = « avec validation » :
--                                       comportement historique, ses ajouts
--                                       restent en attente jusqu'à validation
--                                       par un administrateur.
--                                       0 = « sans validation » : publication
--                                       directe, journalisée.
--   accounts.journaliste_toutes_branches 1 (défaut) = périmètre « toutes les
--                                       branches » : comportement historique.
--                                       0 = seulement les branches cochées
--                                       dans journaliste_branches.
--   journaliste_branches                les branches du périmètre restreint
--                                       d'un journaliste (vide = aucune tant
--                                       que toutes_branches vaut 1, ce qui
--                                       rend la migration sans effet).
--   contenu_historique                  trace des modifications directes
--                                       (version précédente, au format JSON) :
--                                       un contenu modifié par un journaliste
--                                       « sans validation » n'écrase jamais
--                                       l'ancien texte sans laisser de trace.
--
-- Les comptes lecteurs et administrateurs ne sont pas touchés : ces colonnes
-- ne sont lues que pour les comptes de type « personne » (journalistes), et
-- leurs valeurs par défaut reproduisent exactement le comportement actuel.
-- ---------------------------------------------------------------------------

ALTER TABLE accounts ADD COLUMN journaliste_validation INTEGER NOT NULL DEFAULT 1;
ALTER TABLE accounts ADD COLUMN journaliste_toutes_branches INTEGER NOT NULL DEFAULT 1;

CREATE TABLE journaliste_branches (
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    branch_id  INTEGER NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    PRIMARY KEY (account_id, branch_id)
);

CREATE TABLE contenu_historique (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    kind       TEXT    NOT NULL,
    content_id INTEGER NOT NULL,
    account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    action     TEXT    NOT NULL,
    avant      TEXT,
    created_at INTEGER NOT NULL
);

CREATE INDEX idx_historique_contenu ON contenu_historique(kind, content_id, created_at DESC);
CREATE INDEX idx_historique_compte ON contenu_historique(account_id, created_at DESC);
