-- ---------------------------------------------------------------------------
-- 011 — Gazette du mois
--
-- Migration AJOUT SEULEMENT : aucune table ni donnée existante n'est modifiée.
--
-- Une ligne = un numéro de la gazette, pour un mois donné et un groupe de
-- droits donné. Le contenu est figé au moment de la préparation (instantané
-- JSON) : ce que l'on relit le 12 du mois est bien ce que l'on a préparé le 1er,
-- et retirer un élément ne change que la ligne concernée.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS gazettes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    periode    TEXT    NOT NULL,                    -- « AAAA-MM » du mois raconté
    groupe     TEXT    NOT NULL,                    -- famille | branche:ID | tout
    status     TEXT    NOT NULL DEFAULT 'a_valider'
               CHECK (status IN ('a_valider', 'envoye', 'passe')),
    mot        TEXT    NOT NULL DEFAULT '',         -- mot de l'éditeur (600 car.)
    contenu    TEXT    NOT NULL DEFAULT '{}',       -- instantané JSON des encarts
    retire     TEXT    NOT NULL DEFAULT '[]',       -- clés des éléments retirés
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    sent_at    INTEGER
);

-- Jamais deux fois le même mois pour le même groupe de droits.
CREATE UNIQUE INDEX IF NOT EXISTS idx_gazettes_mois_groupe ON gazettes(periode, groupe);

-- Liste de l'administration : les numéros à valider d'abord.
CREATE INDEX IF NOT EXISTS idx_gazettes_status ON gazettes(status, periode);
