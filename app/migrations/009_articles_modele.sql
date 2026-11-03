-- ---------------------------------------------------------------------------
-- 009 — Modèle de mise en page d'un article
--
-- Un article pouvait avoir une seule allure : celle du « journal » (chapô,
-- photo à la une, texte en colonnes). On ajoute le choix du modèle :
--   ''        = comme le site (réglage « Composition des pages »)
--   'journal' = la mise en page d'aujourd'hui
--   'simple'  = titre, texte, une seule colonne, sans ornement
--   'photo'   = la photo en grand, puis le texte
--
-- Aucune donnée n'est perdue ni déplacée : la colonne a une valeur par
-- défaut vide, et « vide » veut dire « comme maintenant ». Une valeur
-- inconnue (base ancienne, saisie bricolée) retombe sur le modèle du site.
-- ---------------------------------------------------------------------------

ALTER TABLE articles ADD COLUMN modele TEXT NOT NULL DEFAULT '';
