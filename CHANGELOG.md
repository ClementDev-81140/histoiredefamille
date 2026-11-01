# Journal des versions

Toutes les dates sont au format `AAAA-MM-JJ`.
Le projet suit un numéro de version simple : `majeure.mineure.correctif`.

## [1.1.0] — 2026-11-01

Habillage « journal », onglet **Vidéos** et ménage du dépôt.

### Présentation (partie publique et espace famille)

- Nouvelle feuille de style inspirée des quotidiens anciens : filets fins,
  aucun coin arrondi, aucune ombre, boutons carrés en majuscules, tableaux et
  fiches à filets, titres de rubriques en majuscules espacées.
- En-tête « une » : ligne de date française en italique (« dimanche
  1er novembre 2026 », écrite en PHP, sans l'extension `intl`), grand titre
  centré, sous-titre italique, barre de navigation en majuscules espacées
  entre un filet fin et un double filet.
- Texte justifié avec césure sur ordinateur, aligné à gauche et sans césure
  sur téléphone ; largeur de lecture d'environ 44 rem ; aucun débordement
  horizontal à 390 px.
- Police à empattements **Old Standard TT** (licence OFL) **auto-hébergée** :
  trois fichiers `woff2` (normal, italique, gras), `font-display: swap`,
  aucune requête vers un service tiers.
- Les couleurs ne changent pas : la palette reste définie dans `:root`.
- Accueil : le nom du site n'est plus affiché deux fois (un `h1` réservé aux
  lecteurs d'écran), chiffres et note centrés en italique, sous-titre non
  répété.
- Pages d'information présentées comme des articles (titre centré, date de
  mise à jour en italique, double filet).
- Les aperçus des listes ne montrent plus la mise en forme brute
  (« **gras** », « ## Titre »).
- Administration : seule la police change (mise en page inchangée).

### Vidéos

- Nouvel onglet **Vidéos**, visible seulement pour les comptes connectés, à
  côté de « Documents » et « Arbre ».
- `/videos` : liste filtrée par les droits (public, familles, branches, compte
  « voit toutes les branches », administrateurs), grille de cartes avec
  miniature, titre, date, durée et résumé. Tri : mises en avant, puis ordre
  d'affichage, puis les plus récentes. Pagination.
- `/videos/{id}` : lecteur `<video controls preload="metadata" playsinline>`
  sur `/video/{id}`, date, durée, branches concernées, bouton « Télécharger »
  seulement si l'administrateur l'autorise, lien de retour.
- Limite de lectures simultanées atteinte : message « Trop de personnes
  regardent des vidéos, réessayez dans une minute » au lieu d'une erreur
  technique (le flux répond toujours 503 avec `Retry-After`).
- Vidéo non autorisée : même page 404 que les autres documents.
- Administration → Vidéos : liste complète, recherche, filtre de visibilité,
  mise en avant en un clic, ordre d'affichage, durée, autorisation de
  téléchargement, import depuis le dossier arrivée (déplacement du fichier,
  jamais en mémoire), modification et suppression.
- Migration `002_videos.sql` : quatre colonnes ajoutées à `documents`
  (`is_featured`, `display_order`, `allow_download`, `duration_seconds`) et un
  index de tri. **Ajout seulement** : aucune colonne ni donnée supprimée, les
  valeurs par défaut conservent le comportement précédent.
- Données de démonstration : trois vidéos (famille, branche, privée) avec un
  petit MP4 H.264 + AAC de deux secondes, pour tester la lecture sans
  embarquer de vrai film.

### Ménage

- Suppression des copies parasites envoyées par erreur à la racine
  (`accueil.php`, `layout.php`, `style.css`, `app/app/`, `app/public/`).

### Vérifications faites

- Les 71 fichiers PHP sont syntaxiquement valides.
- Parcours testé de bout en bout (PHP + base SQLite réelle) : visiteur non
  connecté, compte de famille, compte « voit tout », administrateur, accès
  direct à une vidéo privée, lecture par morceaux (206 / 416), limite de
  lectures simultanées, import depuis le dossier arrivée, CSRF invalide.
- Captures d'écran versées dans `docs/captures/` (ordinateur 1280 px et
  téléphone 390 px).

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
