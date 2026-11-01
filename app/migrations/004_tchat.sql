-- ---------------------------------------------------------------------------
-- 004 — Tchat privé
--
-- Un seul salon commun à tous les comptes connectés (branches et
-- administrateurs). Les messages ne sont visibles que des personnes
-- connectées : aucun accès depuis l'extérieur, aucune table publique.
--
-- Migration AJOUT SEULEMENT : création d'une table et de deux réglages.
-- Aucune colonne, aucune ligne et aucun fichier existant n'est supprimé.
-- ---------------------------------------------------------------------------

CREATE TABLE chat_messages (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Compte auteur. NULL si le compte a été supprimé : le message reste
    -- lisible avec sa signature d'origine (jamais de perte de message).
    account_id   INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    -- Signature au moment de l'envoi : « MARTIN/Jean » ou « ADMIN/Clément ».
    -- On la fige pour que l'historique reste compréhensible même si la
    -- personne change ensuite de prénom.
    author_label TEXT NOT NULL,
    -- Code de la branche (MARTIN, RAVIART…) ou « ADMIN ». Sert au filtrage
    -- et à l'affichage de la pastille.
    author_branch TEXT NOT NULL DEFAULT '',
    -- Texte brut, jamais du HTML. Échappé à l'affichage.
    body         TEXT NOT NULL,
    created_at   INTEGER NOT NULL
);

CREATE INDEX idx_chat_created ON chat_messages(created_at DESC);
CREATE INDEX idx_chat_auteur ON chat_messages(account_id, created_at DESC);

-- Réglages par défaut (sans écraser une valeur déjà choisie par un
-- administrateur) : le tchat est ouvert, messages conservés un an.
INSERT OR IGNORE INTO settings (key, value) VALUES ('tchat_actif', '1');
INSERT OR IGNORE INTO settings (key, value) VALUES ('tchat_conservation_mois', '12');
