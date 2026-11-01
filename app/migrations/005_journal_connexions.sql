-- ---------------------------------------------------------------------------
-- 005 — Journal des connexions (administration)
--
-- Garde une trace lisible de chaque tentative de connexion (réussie, échouée
-- ou bloquée), pour que les administrateurs puissent repérer un comportement
-- anormal. La table « login_attempts » (limitation des essais) n'est pas
-- modifiée : elle sert seulement à bloquer, et se vide à chaque réussite.
--
-- Migration AJOUT SEULEMENT : une table, deux réglages. Rien n'est supprimé.
-- ---------------------------------------------------------------------------

CREATE TABLE login_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Compte concerné (NULL si l'identifiant n'existe pas ou si le compte
    -- a été supprimé depuis).
    account_id  INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    -- Identifiant tel que saisi (tronqué à 60 caractères).
    identifier  TEXT    NOT NULL,
    ip          TEXT    NOT NULL,
    success     INTEGER NOT NULL DEFAULT 0,
    -- « connexion réussie », « mot de passe incorrect », « identifiant inconnu »,
    -- « compte désactivé », « bloqué (trop d'essais) ».
    detail      TEXT    NOT NULL DEFAULT '',
    -- 1 = connexion d'un administrateur depuis une adresse jamais vue pour
    -- ce compte (alors qu'il s'était déjà connecté ailleurs).
    new_ip      INTEGER NOT NULL DEFAULT 0,
    created_at  INTEGER NOT NULL
);

CREATE INDEX idx_login_log_date ON login_log(created_at DESC);
CREATE INDEX idx_login_log_ip ON login_log(ip, created_at DESC);
CREATE INDEX idx_login_log_alerte ON login_log(new_ip, created_at DESC);

-- Conservation : 90 jours par défaut. « alertes_vues » = date du dernier
-- « J'ai pris connaissance » dans le tableau de bord.
INSERT OR IGNORE INTO settings (key, value) VALUES ('journal_conservation_jours', '90');
INSERT OR IGNORE INTO settings (key, value) VALUES ('journal_alertes_vues', '0');
