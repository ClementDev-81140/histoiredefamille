# Journal des versions

Toutes les dates sont au format `AAAA-MM-JJ`.
Le projet suit un numéro de version simple : `majeure.mineure.correctif`.

## [1.0.0] — 2026-10-25

Première version complète, installable sur le Raspberry Pi.

### Socle

- Structure du projet sans framework : `public/` (seul dossier exposé),
  `app/` (code), `deploy/` (installation), `storage/` (développement).
- Base SQLite avec migrations SQL numérotées et suivies dans une table
  `migrations` ; mode WAL, `busy_timeout` 5 s, 3 tentatives sur les écritures
  sensibles.
- Connexion / déconnexion, sessions sûres (HttpOnly, Secure, SameSite=Lax,
  identifiant régénéré), blocage après 5 échecs par identifiant et par IP.
- Rôles : administrateur, compte de branche.
- Tableau de bord d'administration en deux blocs : « Partie publique » et
  « Partie famille ».
- Gestion des branches familiales (création, modification, suppression,
  réinitialisation du mot de passe en un clic, désactivation) et des trois
  comptes administrateurs personnels.
- Case « voit toutes les branches » portée par la branche (aucun nom codé en
  dur).
- Page d'accueil publique sobre, responsive, sans dépendance externe.

### Documents

- Catégories, envoi par le navigateur (50 Mo par défaut), titre, description,
  date, étiquettes, visibilité.
- Miniatures JPEG générées à l'envoi avec GD, dans la limite de 24 Mpx
  (protection de la mémoire du Pi).
- Recherche simple (titre, description, étiquettes).
- PDF et images affichés dans le navigateur ; autres formats en
  téléchargement.
- Fichiers stockés hors racine web, type réel vérifié (`finfo`), nom aléatoire,
  lecture uniquement par script PHP avec contrôle des droits.

### Vidéos

- Lecture `<video>` sans réencodage, flux PHP avec support de HTTP Range,
  sortie par morceaux (256 Ko) : la mémoire du Pi n'est jamais saturée.
- Limite de lectures simultanées (2 par défaut) avec verrous et libération
  automatique, réponse 503 explicite au-delà.
- Import depuis le dossier « arrivée » (dépôt SFTP/Samba sur le réseau local)
  avec saisie du titre, de la branche et de la visibilité.

### Généalogie

- Saisie manuelle des personnes et des couples, photo, notes, lieux, dates
  approximatives, visibilité par personne.
- Import GEDCOM tolérant aux données incomplètes, avec aperçu (compteurs,
  avertissements, doublons) et confirmation avant écriture.
- Personnes importées en « Toutes les familles » par défaut, jamais publiques.
- Arbre interactif en JavaScript maison, sans bibliothèque externe.

### Pages d'information

- Pages éditables par les administrateurs (markdown léger), visibilité
  par page, apparition automatique dans la navigation.

### Visibilité

- Quatre niveaux : public, toutes les familles, branches précises
  (multi-sélection), privé.
- Aucun niveau choisi = **privé** (jamais public par défaut).
- Administrateurs : accès total. Comptes « voit toutes les branches » :
  accès aux documents famille et branches, jamais aux documents privés.

### Sauvegarde, installation, documentation

- `deploy/install.sh` : installation complète (paquets, modules Apache, copie,
  droits, `config.local.php`, PHP adapté au Pi, VirtualHost, migrations, cron).
- `deploy/backup.sh` : sauvegarde nocturne (`sqlite3 .backup`, jamais `cp`),
  14 versions de base, `rsync` incrémental des fichiers, journal, fichier
  d'état lu par le site, alerte en cas d'échec, mode `--verifier` de test de
  restauration.
- `deploy/apache/`, `deploy/php/`, `deploy/cloudflared/` : configurations
  prêtes à copier.
- `deploy/montage-disques.md` : montage par UUID avec `nofail`, droits
  `www-data`, réduction de l'usure de la carte SD.
- `deploy/dossier-arrivee.md` : dépôt des films par SFTP ou Samba.
- `README.md` complet (installation, mise à jour, sauvegarde, dépannage).
- `app/cli/demo.php` : jeu de données **fictif** pour découvrir le site.

### Corrections apportées pendant la mise au point

- Envoi de fichiers (multipart) qui échouait systématiquement : le corps de
  requête est désormais transmis brut, les fichiers arrivent correctement.
- Miniatures qui s'écrasaient entre elles : chaque miniature a maintenant un
  nom aléatoire unique.
- Import GEDCOM : les identifiants de personnes (`@I1@`) étaient conservés avec
  leurs arobases, ce qui empêchait tous les liens de parenté ; ils sont
  normalisés à la lecture du fichier.
- Suppression d'une personne : erreur d'accès au compte courant provoquant une
  réponse 200 au lieu de la redirection attendue.
- Liste des documents : alias SQL manquant qui provoquait une erreur 500 sur
  la page d'accueil et l'espace famille.
- Formulaires d'administration : variable `$isEdit` non transmise à certaines
  vues, bouton d'enregistrement absent.
- Fichier `deploy/apache/histoiredefamille.conf` : balise de fermeture
  `</VirtualHost>` manquante, Apache refusait de démarrer.
- Adresse IP : l'en-tête `CF-Connecting-IP` n'est plus accepté que si la
  requête vient de la boucle locale (le tunnel), pour qu'il ne puisse pas être
  forgé depuis le réseau local et contourner le blocage par IP.
- Redirection après connexion : le paramètre `next` refuse désormais les
  antislashs (`/\site.com`), les doubles barres obliques et les caractères de
  contrôle, en plus des URL externes.
- Documentation : remplacement des informations personnelles (pseudo GitHub,
  identifiant du Pi, adresse IP locale) par des valeurs à adapter.
- Ajout de `public/maintenance.html`, la page d'attente référencée par
  `ErrorDocument 503` (elle manquait).
