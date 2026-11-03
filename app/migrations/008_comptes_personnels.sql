-- ---------------------------------------------------------------------------
-- 008 — Comptes « personne » à côté des comptes « branche »
--
-- Jusqu'ici, un compte servait toute une branche : un identifiant en
-- majuscules (MARTIN, DURAND…) et un mot de passe partagé par la famille.
--
-- On garde exactement ce fonctionnement, et on ajoute un second type de
-- compte : la personne. Chaque personne a son identifiant (elise.martin),
-- son mot de passe, son nom et son prénom. Les deux types coexistent : rien
-- n'est supprimé, rien n'est renommé.
--
-- Aucune donnée n'est perdue : les colonnes ajoutées ont une valeur par
-- défaut, et les comptes existants deviennent « branche » (ou « admin »).
-- ---------------------------------------------------------------------------

-- Type du compte : « personne », « branche » ou « admin ».
-- ('admin' évite de mélanger les deux informations ; le rôle reste dans la
-- colonne « role », qui garde son sens : « admin » ou « famille ».)
ALTER TABLE accounts ADD COLUMN account_type TEXT NOT NULL DEFAULT 'branche';

-- Nom et prénom séparés, pour les comptes « personne ».
-- Vide pour les comptes de branche et les administrateurs (inchangés).
ALTER TABLE accounts ADD COLUMN first_name TEXT NOT NULL DEFAULT '';
ALTER TABLE accounts ADD COLUMN last_name  TEXT NOT NULL DEFAULT '';

-- « Voit toutes les branches » propre à une personne.
-- Pour un compte de branche, la case reste celle de la branche
-- (table branches), comme avant : aucun comportement ne change pour eux.
ALTER TABLE accounts ADD COLUMN see_all INTEGER NOT NULL DEFAULT 0;

-- Les administrateurs existants sont des comptes « admin ».
UPDATE accounts SET account_type = 'admin' WHERE role = 'admin';

-- Jusqu'ici, une branche ne pouvait avoir qu'un seul compte : l'index
-- ci-dessous le garantissait. Plusieurs personnes peuvent désormais
-- appartenir à la même branche ; on limite donc la règle aux comptes de
-- branche, pour ne rien changer à leur sujet.
DROP INDEX IF EXISTS idx_accounts_branch;
CREATE UNIQUE INDEX idx_accounts_branch ON accounts(branch_id)
    WHERE branch_id IS NOT NULL AND account_type = 'branche';

-- Recherche par type (page « Comptes » de l'administration).
CREATE INDEX idx_accounts_type ON accounts(account_type, is_active);
