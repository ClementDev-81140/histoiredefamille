# Journal des versions

Toutes les dates sont au format `AAAA-MM-JJ`.
Le projet suit un numéro de version simple : `majeure.mineure.correctif`.

## [1.5.0] — 2026-11-01

### Albums photos et personnes identifiées

- Nouvel onglet **Albums** (`/albums`, `/albums/{adresse}`) : les photos déjà
  envoyées sont regroupées par époque, voyage ou fête. Aucun fichier n'est copié
  ni déplacé : un album est un rangement, pas une seconde médiathèque.
- Chaque album a un titre, une description, une **visibilité** (mêmes quatre
  niveaux que le reste du site), un ordre d'affichage et une **photo de
  couverture** (la première par défaut). Supprimer un album ne supprime aucune
  photo.
- Les photos gardent leur **propre** visibilité : une photo privée placée dans
  un album public reste invisible pour un visiteur. Un album vide n'affiche
  jamais de contenu confidentiel.
- **Personnes identifiées** : Administration → Documents → bouton « Personnes »
  sur un document. On cherche un nom dans l'arbre et on l'associe en un clic
  (`document_persons`). Seuls les noms visibles par le visiteur apparaissent
  dans les légendes.
- La fiche d'un document affiche « Personnes sur ce document » et « Dans les
  albums » ; la fiche d'une personne affiche « Photos et documents ».
- Migration `005_albums.sql` : trois tables (`albums`, `album_documents`,
  `document_persons`). Aucune donnée existante n'est modifiée ni supprimée.
- Ajouts à venir dans les lots suivants et déjà branchés ici : lecture des
  fichiers **audio** dans la fiche d'un document (`<audio>`), et bouton
  « Imprimer » sur les albums (mise en page d'impression).
- Démonstration : un album de trois photos et six personnes identifiées.
- **Tests** : `php app/cli/tests.php albums` (légendes « Jean MARTIN, Marie
  LEROY et 2 autres », adresse d'un album, reconnaissance d'un son) — 57
  vérifications au total.
- Captures : `ordinateur-albums`, `mobile-albums`, `ordinateur-album`,
  `mobile-album`, `ordinateur-admin-album`, `ordinateur-admin-personnes-photo`,
  `ordinateur-document-personnes`.

## [1.4.0] — 2026-11-01

### Recherche générale et fiches de personnes enrichies

- Nouvel onglet **Recherche** (`/recherche`) ouvert à tout le monde : une seule
  case, et les mots tapés sont cherchés dans les **documents** (titre,
  description, étiquettes, date), les **articles** (titre, résumé, texte), les
  **pages d'information** et les **personnes** de l'arbre (prénom, nom, lieux,
  dates).
- **Accents, majuscules et ponctuation ignorés** : le texte cherché est
  préparé une seule fois (`recherche_normaliser()`), six mots au maximum, mots
  répétés comptés une fois.
- **Index plein texte** SQLite (`FTS5`, `unicode61` avec
  `remove_diacritics 2`), tenu à jour par des déclencheurs sur les quatre
  tables : aucun ajout, aucune modification, aucune suppression ne peut le
  désynchroniser.
  - Si la version de SQLite du serveur ne connaît pas FTS5, le code bascule
    tout seul sur une recherche `GLOB` (plus lente, même résultat) ;
    `HDF_SEARCH_MODE=like` force ce repli pour les essais.
  - La version de l'index est rangée dans le réglage `recherche_version` (2) :
    après une restauration de sauvegarde ou une mise à jour du code, il est
    reconstruit automatiquement, une seule fois.
- **Rapidité** : sur 6000 personnes, la recherche est passée de 5–8 secondes à
  **9–22 ms**. La jointure entre l'index et la table est écrite avec un
  `CROSS JOIN` (l'optimiseur de SQLite faisait sinon parcourir toutes les
  fiches avant de filtrer). Le repli sans index reste sous 100 ms.
- **Droits respectés** : chaque catégorie de résultat réutilise les règles de
  visibilité existantes (`visibility_clause()`, `article_visible_clause()`),
  et les personnes passent par `filter_visible_persons()` : ce qui est interdit
  n'apparaît pas, même dans le compteur.
- Résultats groupés par catégorie, pastilles de comptage, onglets
  « Tout / Documents / Articles / Pages d'information / Personnes », et
  pagination de 24 résultats par page (aucun gros tableau en mémoire).
- **Fiche d'une personne** :
  - **Chronologie** : naissance, mariages (avec lieu), naissances des enfants
    (avec lien vers leur fiche) et décès, classés par année ; une date sans
    année lisible (« vers 1950 ») est reconnue et placée en fin de liste.
  - **Famille élargie** : grands-parents, oncles et tantes, petits-enfants,
    neveux et nièces, filtrés par les droits.
  - **Lieux cliquables** (naissance, décès, mariage) : un clic lance une
    recherche sur ce lieu.
  - **Autres personnes portant le nom …** : jusqu'à six homonymes pour repérer
    les branches cousines, avec un lien « Chercher ce nom ».
  - Ligne « Sexe » ajoutée au bloc « Repères ».
- `app/cli/maintenance.php` reconstruit l'index de recherche à chaque passage
  (une ligne de plus dans son résumé).
- **Tests** : `app/cli/tests.php` passe de 23 à **48 vérifications**
  (`php app/cli/tests.php recherche`, `php app/cli/tests.php personne`).
- Aucune migration : la recherche vit dans des tables à part, créées et
  recréées par le code ; aucune donnée existante n'est modifiée.
- Captures : `ordinateur-recherche`, `mobile-recherche`,
  `ordinateur-fiche-personne`, `mobile-fiche-personne`.

## [1.3.1] — 2026-11-01

### Correctif — liens et texte du tchat

- Le texte d'un message était échappé deux fois : une adresse contenant « & »
  affichait « &amp;amp; » dans le texte **et** dans le lien, et une adresse
  entre guillemets traînait un « &quot; » à la fin de son adresse de lien.
- Les adresses sont maintenant repérées sur le **texte brut**, avant tout
  échappement, en s'arrêtant sur les espaces, guillemets et chevrons ; chaque
  morceau (texte normal, adresse, ponctuation finale) est échappé **une seule
  fois**. Seuls `http` et `https` deviennent des liens (donc jamais
  `javascript:`), toujours avec `rel="noopener noreferrer"`.
- Nouveau fichier **`app/cli/tests.php`** : 23 vérifications automatiques
  (« & », guillemets, point final, parenthèse, `javascript:`, balise
  `<script>`, message de 1000 caractères sans espace, accents, sauts de
  ligne…). Commande : `php app/cli/tests.php`.

## [1.3.0] — 2026-11-01

### Tchat de la famille

- Nouvel onglet **Tchat**, visible seulement pour les personnes connectées :
  un seul salon commun aux branches et aux administrateurs.
- Signature « **BRANCHE/Prénom** » (« HERICHER/Jean », « ADMIN/Clément ») :
  prénom demandé au premier passage (1 à 30 caractères, casse remise
  proprement), gardé en session et modifiable à tout moment. Les messages déjà
  envoyés gardent leur signature d'origine.
- **Texte brut**, 1000 caractères au maximum, sauts de ligne conservés ; une
  adresse web devient un lien sûr (`rel="noopener noreferrer"`, jamais de
  `javascript:`). Tout est échappé à l'affichage.
- Mise à jour par **interrogation toutes les 5 s**, qui ne demande que les
  messages plus récents que le dernier affiché ; ralentissement à 30 s après
  trois minutes sans nouveauté ; arrêt complet quand l'onglet est caché ou la
  page quittée. Aucun service permanent (ni WebSocket, ni Node, ni Redis).
- Affichage « journal » : séparateur par journée, heure, défilement
  automatique, 50 derniers messages puis « Voir les messages plus anciens »
  par paquets de 50.
- **Anti-spam** : 1 message par seconde et 30 par minute et par compte,
  message vide refusé. Envoi par `fetch` avec jeton CSRF.
- **Pastille** des nouveaux messages dans le menu (une requête légère par page).
- **Administrateurs** : suppression d'un message, avec trace dans le journal
  d'activité (auteur, date, début du texte).
- **Réglages** : ouverture/fermeture du tchat et durée de conservation
  (jamais, 1 an par défaut, 6 mois, 3 mois), appliquée par
  `app/cli/maintenance.php`.
- Migration `004_tchat.sql` : nouvelle table `chat_messages` et deux réglages.
  Aucune donnée existante n'est modifiée ni supprimée.
- JavaScript ajouté dans `public/assets/app.js` (aucun script en ligne, CSP
  « self » inchangée) et styles dans `public/assets/style.css` (palette et
  habillage inchangés).
- Données de démonstration : trois messages fictifs dans le tchat.
- Captures : `ordinateur-tchat`, `mobile-tchat`, `ordinateur-tchat-admin`,
  `ordinateur-reglages-tchat`.

## [1.2.0] — 2026-11-01

### Articles

- Nouvelle rubrique **Articles** (fil d'actualité de la famille) : liste
  `/articles` avec rubriques et pagination, lecture `/articles/{adresse}`,
  « À la une » sur la page d'accueil.
- Chaque article a une visibilité (public, toutes les familles, branches
  précises, privé), un statut (brouillon / publié), une date de publication
  (une date future programme la publication), une rubrique, un résumé et une
  image à la une choisie parmi les photos.
- Administration → **Articles** : liste, création, modification, suppression ;
  aperçu des brouillons réservé aux administrateurs.
- Migration `003_articles.sql` (nouvelle table, aucune donnée existante touchée).
- Intégration harmonisée avec l'habillage « journal » : une seule fonction de
  date longue (`french_long_date()`, avec ou sans le jour de la semaine),
  résumés et chapôs nettoyés de leur mise en forme légère, étiquettes de visibilité
  affichées uniquement pour les contenus restreints.

### Vidéos ouvertes à tous

- L'onglet **Vidéos** est désormais visible par tout le monde, comme
  « Documents » et « Arbre ». Un visiteur non connecté voit les vidéos
  marquées « Public » et un message l'invitant à se connecter pour les autres ;
  une vidéo réservée reste inaccessible (même page 404 que pour un document).
- La page d'une vidéo et le flux `/video/{id}` appliquent les mêmes droits :
  contrôle par `visibility.php` avant toute lecture du fichier.
- Données de démonstration : une vidéo **publique** a été ajoutée, pour pouvoir
  tester l'onglet sans se connecter.

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
