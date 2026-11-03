-- ---------------------------------------------------------------------------
-- 010 — Courriels : adresse des comptes, accords, file d'envoi
--
-- Rien n'est supprimé ni renommé : on ajoute des colonnes (vides ou à zéro)
-- et quatre tables neuves. Aucun compte existant ne reçoit d'adresse : un
-- courriel ne part que si la personne a donné son adresse ET son accord.
-- ---------------------------------------------------------------------------

-- Adresse électronique facultative (comptes « personne » surtout).
-- Vide par défaut : personne n'est inscrit sans l'avoir demandé.
ALTER TABLE accounts ADD COLUMN email TEXT NOT NULL DEFAULT '';

-- Accords explicites (« Prévenez-moi des nouveaux articles », « Je veux
-- recevoir la gazette du mois »). Par défaut : 0 = pas d'accord.
ALTER TABLE accounts ADD COLUMN notify_articles INTEGER NOT NULL DEFAULT 0;
ALTER TABLE accounts ADD COLUMN notify_gazette  INTEGER NOT NULL DEFAULT 0;

CREATE INDEX idx_accounts_email ON accounts(email) WHERE email <> '';

-- Jetons de désinscription : un jeton par personne et par type d'envoi.
-- Seule l'empreinte (SHA-256) du jeton est stockée. Sans date d'expiration.
CREATE TABLE email_tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    purpose    TEXT    NOT NULL CHECK (purpose IN ('articles', 'gazette')),
    token_hash TEXT    NOT NULL,
    created_at INTEGER NOT NULL
);
CREATE UNIQUE INDEX idx_email_tokens_hash ON email_tokens(token_hash);
CREATE INDEX idx_email_tokens_compte ON email_tokens(account_id, purpose);

-- File d'envoi : chaque message est écrit ici avant d'être envoyé.
-- Aucun envoi ne se fait pendant une requête web : la commande
-- app/cli/envoyer-courriels.php vide la file, par petits lots.
CREATE TABLE email_queue (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id  INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    kind        TEXT    NOT NULL DEFAULT 'article',   -- article | gazette | essai
    recipient   TEXT    NOT NULL,
    subject     TEXT    NOT NULL DEFAULT '',
    body_text   TEXT    NOT NULL DEFAULT '',
    body_html   TEXT    NOT NULL DEFAULT '',
    status      TEXT    NOT NULL DEFAULT 'attente'
                CHECK (status IN ('attente', 'envoye', 'echec', 'annule')),
    attempts    INTEGER NOT NULL DEFAULT 0,
    dedupe_key  TEXT    NOT NULL DEFAULT '',          -- évite les doublons
    last_error  TEXT    NOT NULL DEFAULT '',
    created_at  INTEGER NOT NULL,
    sent_at     INTEGER
);
CREATE UNIQUE INDEX idx_email_queue_dedupe ON email_queue(dedupe_key) WHERE dedupe_key <> '';
CREATE INDEX idx_email_queue_etat ON email_queue(status, created_at);
CREATE INDEX idx_email_queue_date ON email_queue(sent_at);

-- Une seule notification par article et par personne (même si l'article est
-- modifié, republié ou replanifié).
CREATE TABLE article_notifications (
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (article_id, account_id)
);

-- Case « Prévenir les inscrits » du formulaire d'article (cochée par défaut).
ALTER TABLE articles ADD COLUMN notify INTEGER NOT NULL DEFAULT 1;
