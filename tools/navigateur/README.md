# Contrôle « vrai navigateur »

Ces fichiers servent à vérifier **dans un vrai navigateur** ce qu'aucun essai
PHP ne peut voir : le comportement du formulaire pendant et après un envoi par
morceaux (champ « Fichier », champ caché `envoi`, lien-ancre, clic sur le
bouton d'enregistrement), les deux boutons de la liste des fichiers reçus
(**Créer le document**, **Supprimer** et sa confirmation), et l'ajout de photos
dans un album (**Ajouter des photos**, dépôt, formulaires de la liste), et les
**adresses affichées** avec les écrans de courriel masqués (1.28.0), et la
**position verticale du titre de la une** dans l'accueil d'un compte connecté
(1.29.0), à 390 px et à 1280 px, en thème **papier** et **bleu**.

Ils ne font **pas** partie du site : `tools/` est hors de la racine web et rien
n'y est chargé en production.

## Pourquoi un petit serveur ?

Il n'y a pas d'Apache sur une machine de développement : `serveur.mjs` sert
les **vraies** pages (rendues par le routeur du site, donc le vrai HTML) et les
**vrais** scripts de `public/assets`. Seules les quatre adresses du protocole
d'envoi par morceaux sont simulées, en renvoyant exactement ce que renvoie le
site (mêmes clés, mêmes codes).

Le protocole, la base et les droits sont vérifiés par les essais PHP
(`php app/cli/tests.php envoi`) ; ici, on vérifie le navigateur.

## Mise en place (une fois)

```bash
npm install puppeteer-core @sparticuz/chromium
```

## Lancer

```bash
# 1. Rendues des deux pages par le routeur du site (compte administrateur 1).
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /admin/videos/nouvelle /tmp/video.html 1
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /admin/documents/nouveau /tmp/document.html 1

# 2. Le serveur d'essai, dans un autre terminal.
PAGE_VIDEO=/tmp/video.html PAGE_DOCUMENT=/tmp/document.html \
    node tools/navigateur/serveur.mjs

# 3. Le contrôle : largeur et page (video | document).
node tools/navigateur/essai.mjs 390 video
node tools/navigateur/essai.mjs 1280 video
node tools/navigateur/essai.mjs 390 document
node tools/navigateur/essai.mjs 1280 document
```

### Vidéo d'archive à marque M4A (1.35.1)

```bash
# La page d'envoi rendue par le routeur réel (compte administrateur), le
# fichier synthétique des essais PHP (conteneur ISO, marque M4A, piste
# vidéo fabriquée octet par octet) et un fichier vide pour le refus.
HDF_CONFIG=/chemin/config.local.php HDF_FORCE_CLI=1 \
    HDF_ARGV='["/admin/envoyer","/tmp/envoyer.html","14"]' \
    php tools/navigateur/rendre.php
PAGE_ENVOYER=/tmp/envoyer.html \
    PAGES_ALBUM='{"/admin/envoyer":"/tmp/envoyer.html"}' \
    node tools/navigateur/serveur.mjs

CAPTURES=/tmp FICHIER_VIDEO='/tmp/Bienvenue sur la lune.MP4' \
    FICHIER_VIDE=/tmp/fichier-vide.mp4 \
    node actions-envoi-media.mjs 1280
# (puis 390)
```

7 contrôles par largeur : l'envoi du fichier à marque M4A aboutit, aucun
refus de type ne s'affiche, le refus d'un fichier vide est une phrase
simple avec une action, les nouvelles extensions sont annoncées dans
l'attribut `accept`, propreté habituelle. Le protocole est simulé (mêmes
clés JSON) : le jugement du contenu réel appartient aux essais PHP
(`tests.php envoi` / `conversion`).

### Exploration de l'arbre et parenté (1.35.0)

```bash
# Trois pages par le routeur réel (compte administrateur) : arbre complet,
# huit enfants, un enfant.
HDF_CONFIG=/chemin/config.local.php HDF_FORCE_CLI=1 \
    HDF_ARGV='["/arbre?racine=15","/tmp/arbre-complet.html","7"]' \
    php tools/navigateur/rendre.php
# (même principe pour les racines des cas « huit » et « un »)

PAGES_ALBUM='{"/arbre-complet":"/tmp/arbre-complet.html","/arbre-huit":"/tmp/arbre-huit.html","/arbre-un":"/tmp/arbre-un.html"}' \
    THEME_CSS=/tmp/theme-papier.css node tools/navigateur/serveur.mjs

CAPTURES=/tmp node actions-arbre-exploration.mjs 1280
CAPTURES=/tmp node actions-arbre-exploration.mjs 390
# puis la palette bleu comme pour le chêne (1.33.0).
```

27 contrôles par largeur et par palette : barre visible et boutons, niveau
en %, API CSSOM sous la CSP, personne centrée au chargement, parents
visibles à 390 px, zoom/déplacement/centrer/voir-tout, molette, glisser,
pincement **synthétisé par CDP** (à confirmer sur un vrai téléphone),
clavier sans piège, sans-JavaScript complet, mouvement réduit, huit
cartes sans superposition, propreté habituelle. L'ancien contrôle
`actions-arbre-chene.mjs` (38 contrôles) continue de tourner : il tolère
désormais le seul `style` posé par le script sur le dessin.

### Étiquette, calendrier et fiche sans décès (1.34.0)

```bash
# Les pages par le routeur réel : article d'actualité et article de souvenir
# (visiteur 0), calendrier d'un administrateur et fiche d'une personne sans
# date de décès (compte 1).
HDF_CONFIG=/chemin/config.local.php HDF_FORCE_CLI=1 \
    HDF_ARGV='["/articles/une-soupe-pour-l-hiver","/tmp/article-actu.html","0"]' \
    php tools/navigateur/rendre.php
HDF_CONFIG=/chemin/config.local.php HDF_FORCE_CLI=1 \
    HDF_ARGV='["/calendrier?mois=2026-06","/tmp/calendrier-admin.html","1"]' \
    php tools/navigateur/rendre.php
# (même principe pour l'article de souvenir et la fiche)

PAGES_ALBUM='{"/article-actu":"/tmp/article-actu.html","/article-souvenir":"/tmp/article-souvenir.html","/calendrier":"/tmp/calendrier-admin.html","/fiche":"/tmp/fiche-sans-deces.html"}' \
    THEME_CSS=/tmp/theme-papier.css node tools/navigateur/serveur.mjs

CAPTURES=/tmp node actions-ajouts34.mjs 1280
CAPTURES=/tmp node actions-ajouts34.mjs 390
# puis la palette bleu (THEME=bleu THEME_FOND='rgb(246, 245, 242)').
```

17 contrôles par largeur et par palette : aucune étiquette de décennie sur
l'article d'actualité, « Souvenir des années … » sur celui de souvenir, le
bouton « Ajouter un événement » visible, des anniversaires (jamais de
naissances), aucun décès sans le réglage, aucune ligne « Décès » sur la
fiche, et la propreté habituelle (style, scripts, débordement, erreurs JS).

### Le chêne gravé de `/arbre` (1.33.0)

```bash
# 1. Les deux pages par le routeur réel : huit enfants (racine 15) et un seul
#    enfant (racine 30), compte 7 = administrateur. Les fixtures viennent de
#    tests_envoi.php (familles BERNARD et GILBERT, données inventées).
HDF_CONFIG=/chemin/config.local.php HDF_FORCE_CLI=1 \
    HDF_ARGV='["/arbre?racine=15","/tmp/arbre-huit.html","7"]' \
    php tools/navigateur/rendre.php
HDF_CONFIG=/chemin/config.local.php HDF_FORCE_CLI=1 \
    HDF_ARGV='["/arbre?racine=30","/tmp/arbre-un.html","7"]' \
    php tools/navigateur/rendre.php

# 2. Le serveur d'essai, ces deux pages sous des adresses à nous.
PAGES_ALBUM='{"/arbre-huit":"/tmp/arbre-huit.html","/arbre-un":"/tmp/arbre-un.html"}' \
    node tools/navigateur/serveur.mjs

# 3. Le contrôle : 38 vérifications par largeur et par palette.
cd /chemin/outils   # le dossier où puppeteer-core est installé
CAPTURES=/tmp node actions-arbre-chene.mjs 1280
CAPTURES=/tmp node actions-arbre-chene.mjs 390
# puis la palette bleu : pages rendues après avoir choisi « bleu » dans
# Administration → Apparence, /theme.css récupéré par rendre.php, serveur
# relancé avec THEME_CSS=/tmp/theme-bleu.css, et
# THEME=bleu THEME_FOND='rgb(246, 245, 242)' pour la commande ci-dessus.
```

Le contrôle compare les **rectangles** des cartes (aucune superposition, aucune
sortie du dessin), vérifie la **disposition** (une colonne à 390 px, des rangées
à 1 280 px), la **gravure** (chargée seulement au-dessus de 1 024 px, bandeau
au-dessus sur téléphone), le **contraste** (noms, légendes — qui doivent avoir
un papier opaque), le **focus clavier**, et l'absence de `style=`, de `<script>`
en ligne, de ressource extérieure et d'erreur JavaScript. Les captures portent
le nom `ordinateur-arbre-*` / `mobile-arbre-*` (`-bleu` pour la palette bleue).

### Les deux boutons de la liste des fichiers reçus

Il faut un envoi **en attente de formulaire** (état `pret`) pour que le tableau
existe. Le plus simple : envoyer un fichier depuis **Envoyer des fichiers** en
décochant la création de la fiche (`creer=0` dans le protocole), ou poser la
ligne à la main dans la base d'essai (`uploads`, `state = 'pret'`,
`creer_fiche = 0`). Puis :

```bash
# 1. La page rendue par le routeur (compte administrateur 1).
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /admin/envoyer /tmp/envoyer.html 1

# 2. Le serveur d'essai, avec cette page en plus.
PAGE_VIDEO=/tmp/video.html PAGE_DOCUMENT=/tmp/document.html \
    PAGE_ENVOYER=/tmp/envoyer.html node tools/navigateur/serveur.mjs

# 3. Le contrôle, aux deux largeurs.
node tools/navigateur/actions.mjs 390
node tools/navigateur/actions.mjs 1280
```

### Ajouter des photos dans un album

Il faut deux albums **publics** dans la base d'essai (l'un rendu pour un
administrateur, l'autre pour un visiteur) dont l'un contient déjà une photo
liée par `album_documents`, et la feuille `/theme.css` **rendue par le site**
(la palette est un réglage : `public/` ne contient pas ce fichier).

```bash
# 1. Les pages, par le routeur réel.
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /admin/albums/7/modifier /tmp/album-modifier.html 1
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /admin/albums/7/photos /tmp/album-photos.html 1
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /albums/album-admin-banc /tmp/album-public-admin.html 1
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /albums/album-visiteur-banc /tmp/album-public-visiteur.html 0
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /theme.css /tmp/theme-papier.css 1

# 2. Le serveur d'essai (serveur.mjs), dans un autre terminal.
PAGES_ALBUM='{"/admin/albums/7/modifier":"/tmp/album-modifier.html",
  "/admin/albums/7/photos":"/tmp/album-photos.html",
  "/albums/album-admin-banc":"/tmp/album-public-admin.html",
  "/albums/album-visiteur-banc":"/tmp/album-public-visiteur.html"}' \
THEME_CSS=/tmp/theme-papier.css CAPTURES=/tmp node tools/navigateur/serveur.mjs

# 3. Le contrôle, aux deux largeurs et dans les deux thèmes.
ALBUM_ID=7 SKU_ADMIN=album-admin-banc SKU_VISITEUR=album-visiteur-banc \
    THEME=papier THEME_FOND='rgb(243, 231, 206)' \
    node tools/navigateur/actions-album.mjs 390
# puis 1280, puis THEME=bleu THEME_FOND='rgb(246, 245, 242)' avec
# THEME_CSS=/tmp/theme-bleu.css (rendu après avoir choisi le thème « bleu »
# dans Administration → Apparence).
```

### Verser dans un album des documents déjà présents (1.30.0)

Cinq rendus suffisent. Il faut un album d'essai (visibilité **famille**, ici
l'album 2, slug `album-banc-130`) qui contient déjà une photo, plus **une photo
privée et une photo publique** présentes dans « Documents & photos » mais pas
encore dans l'album. Le rendu de la page `?ajoutes=` doit être fait **avant**
de donner la visibilité de l'album, et celui du lecteur après — c'est ce que
montrent les deux captures.

```bash
# 1. Les pages, par le routeur réel (compte 7 = administrateur, 4 = lecteur).
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /admin/albums/2/photos /tmp/photos.html 7
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    '/admin/albums/2/photos?ajoutes=11,12' /tmp/photos-apres.html 7
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /albums/album-banc-130 /tmp/album-admin.html 7
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /albums/album-banc-130 /tmp/album-lecteur.html 4
# les images de chaque page en `data:` (inline-medias.php), puis, la photo
# privée passée en visibilité « famille » dans la base d'essai :
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    /albums/album-banc-130 /tmp/album-lecteur-apres.html 4

# 2. Le serveur d'essai : les deux vues du lecteur sont servies sous des
#    adresses à nous (`/lecteur`, `/lecteur-apres`).
PAGES_ALBUM='{"/admin/albums/2/photos":"/tmp/photos-inline.html",
  "/admin/albums/2/photos/apres":"/tmp/photos-apres-inline.html",
  "/albums/album-banc-130":"/tmp/album-admin-inline.html",
  "/albums/album-banc-130/lecteur":"/tmp/album-lecteur-inline.html",
  "/albums/album-banc-130/lecteur-apres":"/tmp/album-lecteur-apres-inline.html"}' \
THEME_CSS=/tmp/theme-papier.css CAPTURES=/tmp \
    node tools/navigateur/serveur.mjs

# 3. Le contrôle : 22 vérifications par largeur et par palette.
ALBUM_ID=2 SKU_ALBUM=album-banc-130 CAPTURES=/tmp \
CAPTURE_PHOTOS=/tmp/photos.png CAPTURE_ALBUM=/tmp/album.png \
CAPTURE_AVERTISSEMENT=/tmp/avert.png \
    node tools/navigateur/actions-album-documents.mjs 1280
# puis 390, puis la palette bleu (THEME_CSS=/tmp/theme-bleu.css, avec les pages
# rendues après avoir choisi « bleu » dans Administration → Apparence : sans ce
# réglage, la page ne demande pas /theme.css du tout).
```

Les deux formulaires sont notés par le banc dans `capture-album-ajouter.txt` et
`capture-album-visibilite.txt` : le script vérifie le **navigateur** (cases
cochées, boutons, corps posté, dialogue de confirmation), pas la base.

### Les adresses affichées, et les écrans de courriel masqués (1.28.0)

Quatre pages suffisent : l'accueil public, la connexion, « Mon compte » (compte
1) et une adresse qui n'existe pas (la page 404). Les courriels doivent être
**coupés** dans la base d'essai (`courriels_actifs` à `0`), sinon « Mes
courriels » réapparaît dans « Mon compte » — c'est justement ce que le contrôle
vérifie.

```bash
# 1. Les pages par le routeur réel (compte 0 = visiteur).
for trio in "/ accueil 0" "/connexion connexion 0" "/mon-compte moncompte 1" \
            "/page-qui-n-existe-vraiment-pas erreur404 0"; do
    set -- $trio
    HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
        "$1" "/tmp/$2.html" "$3"
done

# 2. Le serveur d'essai (serveur.mjs), dans un autre terminal.
PAGES_ALBUM='{"/":"/tmp/accueil.html","/connexion":"/tmp/connexion.html",
  "/mon-compte":"/tmp/moncompte.html",
  "/page-qui-n-existe-vraiment-pas":"/tmp/erreur404.html"}' \
THEME_CSS=/tmp/theme-papier.css CAPTURES=/tmp node tools/navigateur/serveur.mjs

# 3. Le contrôle, aux deux largeurs et dans les deux thèmes.
THEME=papier THEME_FOND='rgb(243, 231, 206)' \
    node tools/navigateur/actions-contacts.mjs 390
# puis 1280, puis THEME=bleu THEME_FOND='rgb(246, 245, 242)' avec
# THEME_CSS=/tmp/theme-bleu.css (rendu après avoir choisi le thème « bleu »
# dans Administration → Apparence).
```

Il vérifie que chaque adresse est un lien `mailto:` **ordinaire** (ni `onclick`,
ni style en ligne), qu'elle est visible et **tient dans la largeur** à 390 px,
que son **contraste** dépasse 4,5 dans les deux palettes, que la page ne
déborde pas horizontalement, que « Mon compte » n'a **ni champ d'adresse, ni case
de consentement, ni section « Mes courriels »**, et qu'aucune page ne lève
d'erreur JavaScript.

Les captures d'écran et le corps du formulaire posté (fichiers `capture-*.txt`)
sont écrits dans `/tmp` (ou dans `CAPTURES=`).

### La une dans le premier écran (1.29.0)

C'est la seule chose qu'aucun essai PHP ne peut mesurer : **où** le titre de la
une tombe à l'écran, en pixels. L'essai PHP de la famille « accueil » vérifie
l'ordre du document ; ici, on mesure.

```bash
# 1. L'accueil d'un compte connecté (ici le compte 4) et ses images en data:.
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/rendre.php \
    / /tmp/accueil-lecteur.html 4
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/inline-medias.php \
    /tmp/accueil-lecteur.html /tmp/accueil-lecteur-images.html

# 2. Le banc, avec cette page à l'adresse « / » et la feuille du thème.
PAGES_ALBUM='{"/":"/tmp/accueil-lecteur-images.html"}' \
THEME_CSS=/tmp/theme-papier.css PORT=8199 node tools/navigateur/serveur.mjs

# 3. La mesure, aux deux largeurs (fenêtre de 800 px de haut).
CAPTURE=/tmp/une-1280.png node tools/navigateur/essai-une.mjs 1280
CAPTURE=/tmp/une-390.png  node tools/navigateur/essai-une.mjs 390
```

Il exige, et affiche la mesure :

- le **titre de la une** commence à moins de **800 px** (1280 × 800) ;
- à 1280, la **photo** commence aussi dans le premier écran et y tient
  entièrement ;
- à 390, le **titre entier** tient dans le premier écran (bas ≤ 800 px) ;
- « Dans la famille » vient **après** la une (jamais avant) et, à 1280, dans une
  **colonne à droite** (bord gauche au-delà du milieu de la page) ;
- la ligne de chiffres, « Nous écrire » et la phrase d'accueil ont **quitté**
  l'accueil d'un compte connecté ;
- le bloc montre **cinq lignes au plus** ;
- au-delà de trois lignes, il est **replié** (`<details>` fermé) sur téléphone et
  s'ouvre **au clic**, sans JavaScript ; sur grand écran il reste ouvert.

`HAUTEUR=` change la hauteur de fenêtre (800 par défaut), `PORT=` le port du
banc (8199 par défaut).

## Engendrer les captures de `docs/captures/`

Deux outils, en plus du serveur :

```bash
# 1. Une page rendue par le routeur (comme plus haut), puis ses images en data:.
HDF_CONFIG=/chemin/config.local.php php tools/navigateur/inline-medias.php \
    /tmp/accueil.html /tmp/accueil-images.html

# 2. Le serveur d'essai avec cette page et la feuille du thème voulu, puis la
#    capture (largeur, adresse, fichier, page entière = 1).
PAGES_ALBUM='{"/":"/tmp/accueil-images.html"}' \
THEME_CSS=/tmp/theme-papier.css PORT=8199 node tools/navigateur/serveur.mjs &
node tools/navigateur/capture.mjs 1280 / /tmp/accueil.png 1
node tools/navigateur/capture.mjs 390  / /tmp/mobile-accueil.png 1
```

`inline-medias.php` lit les fichiers réels du stockage (`/media/miniature/{id}`,
`/f/{id}`) et les écrit en `data:` dans la page : la capture montre les vraies
photos sans Apache. `capture.mjs` photographie la page servie par le banc, page
entière par défaut. Les deux sont des outils de développement : hors de
`public/`, jamais chargés par le site.

Fichiers d'essai : n'importe quel fichier fait l'affaire
(`FICHIER_VIDEO=/tmp/film.mp4`, `FICHIER_DOCUMENT=/tmp/lettre.pdf`).

## Ce qui est vérifié

- la case « Fichier » est obligatoire **au départ** ;
- après l'envoi : le message nomme le **vrai** bouton (« Envoyer la vidéo » /
  « Envoyer le document »), le lien « Aller au bouton d'enregistrement » vise
  bien ce bouton et l'amène dans l'écran, la case « Fichier » n'est plus
  obligatoire, et le champ caché `envoi` porte le jeton ;
- le navigateur ne réclame plus de fichier (`validationMessage` vide) ;
- au clic sur le bouton, le formulaire part avec `envoi` et **sans** fichier
  joint — c'est le défaut corrigé en 1.26.2 ;
- aucun défilement horizontal de page à la largeur demandée ;
- aucune erreur JavaScript.

Pour la liste des fichiers reçus (`actions.mjs`) :

- la colonne « Actions » est là, avec les deux boutons dans l'ordre ;
- chaque bouton est un **POST** vers sa route, avec le jeton CSRF et le jeton
  d'envoi — et **aucun autre champ** : rien venu du navigateur ne désigne un
  fichier ;
- « Supprimer » ouvre la confirmation du navigateur, qui nomme le fichier et
  prévient qu'il n'y a pas de retour ; refuser ne poste rien ;
- « Créer le document » poste bien le formulaire (corps relevé dans
  `capture-action-creer.txt`) ;
- la ligne du tableau reste compacte (pas de boutons empilés sur trois lignes).

Pour l'ajout de photos dans un album (`actions-album.mjs`) :

- sur le formulaire de l'album, le bouton **Ajouter des photos** est présent,
  assez grand pour être vu, et mène à la page d'envoi de l'album ;
- sur cette page : zone de dépôt visible, album transmis par le bloc d'envoi,
  lien « Voir l'album », champ de fichier limité aux **photos** et multiple ;
- la liste des photos ajoutées offre deux **formulaires ordinaires** (titre et
  date ; « Retirer de l'album »), chacun avec son jeton CSRF et l'identifiant du
  document, et **aucun champ de plus** ; la confirmation du retrait dit que le
  document n'est pas supprimé, et la refuser ne poste rien ;
- le dépôt d'une **vraie photo** part bien avec `album=<id>` (corps relevé dans
  `capture-envoi-init.txt`) et la page ne se recharge **qu'une fois** ;
- le dépôt d'un **film** est refusé localement, sans aucun envoi ;
- l'album public montre le bouton à un administrateur, **jamais** à un visiteur ;
- la palette du thème demandée est bien appliquée (couleur de fond du `body`) ;
- à l'impression (`emulateMediaType('print')`), la page tient dans la largeur.

### La Une du journal pour un visiteur (1.31.0)

Cinq rendus suffisent, tous en **compte 0** (visiteur). La page « vide » vient
d'une **copie** de la base de démonstration dont tout le contenu a été repassé
en visibilité « Famille » (aucun contenu public, donc).

```bash
# 1. Les pages, par le routeur réel (0 = visiteur).
cd /tmp/h
php_rendre() { HDF_ENV='{"HDF_FORCE_CLI":"1"}' \
    HDF_ARGV="[\"x\",\"$1\",\"$2\",\"0\"]" HDF_CONFIG="$3" \
    node runcode.mjs /home/user/histoiredefamille/tools/navigateur/rendre.php; }
php_rendre "/"                  /tmp/accueil.html           /tmp/demo/config.php
php_rendre "/documents/1"       /tmp/contenu-public.html    /tmp/demo/config.php
php_rendre "/documents/5"       /tmp/prive-404.html         /tmp/demo/config.php
php_rendre "/documents/999999"  /tmp/introuvable-404.html   /tmp/demo/config.php
php_rendre "/"                  /tmp/accueil-vide.html      /tmp/demo-vide/config.php
# puis les images en `data:` (inline-medias.php) et /theme.css du thème voulu.

# 2. Le serveur d'essai : la page vide et la page « introuvable » reçoivent des
#    adresses à nous, le banc ne servant que des fichiers.
PAGES_ALBUM='{"/":"/tmp/accueil-inline.html",
  "/vide":"/tmp/accueil-vide-inline.html",
  "/documents/1":"/tmp/contenu-public-inline.html",
  "/documents/5":"/tmp/prive-404.html",
  "/page-qui-n-existe-vraiment-pas":"/tmp/introuvable-404.html"}' \
THEME_CSS=/tmp/theme-papier.css CAPTURES=/tmp \
    node tools/navigateur/serveur.mjs

# 3. Le contrôle : 19 vérifications par largeur et par palette.
THEME=papier THEME_FOND='rgb(243, 231, 206)' CAPTURES=/tmp \
    node tools/navigateur/actions-une-publique.mjs 1280
# puis 390, puis la palette bleu (THEME_CSS=/tmp/theme-bleu.css,
# THEME_FOND='rgb(246, 245, 242)', pages rendues après avoir choisi « bleu »
# dans Administration → Apparence).
```

Ce que le banc exige, et qu'aucun essai PHP ne peut voir :

- le menu d'un visiteur contient **exactement deux** liens, « Accueil » (`/`)
  et « Se connecter » (`/connexion`) — ni « Consulter », ni « Carte » ;
- le bouton « Se connecter » tient **entièrement dans le premier écran**
  (800 px de haut) et mesure au moins 44 px de haut et 180 px de large ;
- « À LA UNE » montre un contenu public en grand, titre cliquable vers
  `/articles/…`, `/documents/…`, `/videos/…`, `/recettes/…` ou `/pages/…`, et sa
  vignette est chargée (jamais d'image cassée : un document sans aperçu reçoit
  un repère de rubrique) ;
- au-delà de six contenus, la grille est **regroupée par rubrique**, avec les
  seuls libellés du journal ;
- **aucun lien** de la page ne mène ailleurs qu'à du contenu public, à la
  connexion ou à l'adresse de contact du pied de page (liens `mailto:`) ;
- aucun attribut `style`, aucun `<script>` en ligne, aucune ressource
  extérieure, un seul `h1` (invisible), aucun débordement horizontal, aucune
  erreur JavaScript ;
- l'accueil vide n'a **ni une, ni grille, ni rubrique** : seulement le bloc
  « Le journal paraîtra bientôt », le bouton et les deux adresses ;
- un contenu **privé** ouvert par son adresse directe donne **exactement** le
  texte de la page « introuvable ». Le code **404** lui-même et l'égalité
  octet pour octet des deux réponses sont vérifiés par les essais PHP
  (`tests_une.php`) : le banc ne sert que des fichiers, il ne peut pas rendre
  un code HTTP.

### L'accueil public sans tuiles vides (1.32.1)

Un seul rendu, la page d'accueil d'un visiteur, dont la une **et** au moins
deux contenus de la grille sont sans image (l'essai ajoute un article public
sans photo, une page d'information et une vidéo publique sans miniature).

```bash
# 1. La page, puis ses images en data:, et la feuille du thème voulu.
cd /tmp/h
php_rendre() { HDF_ENV='{"HDF_FORCE_CLI":"1"}' \
    HDF_ARGV="[\"x\",\"$1\",\"$2\",\"0\"]" HDF_CONFIG="$3" \
    node runcode.mjs /home/user/histoiredefamille/tools/navigateur/rendre.php; }
php_rendre "/"         /tmp/accueil.html        /tmp/demo/config.php
php_rendre "/theme.css" /tmp/theme-papier.css   /tmp/demo/config.php
HDF_CONFIG=/tmp/demo/config.php node runcode.mjs \
    /home/user/histoiredefamille/tools/navigateur/inline-medias.php \
    /tmp/accueil.html /tmp/accueil-inline.html

# 2. Le serveur, puis le contrôle : 28 vérifications par largeur et palette.
PAGES_ALBUM='{"/":"/tmp/accueil-inline.html"}' THEME_CSS=/tmp/theme-papier.css \
    CAPTURES=/tmp node tools/navigateur/serveur.mjs
THEME=papier THEME_FOND='rgb(243, 231, 206)' CAPTURES=/tmp \
    node tools/navigateur/actions-une-sans-image.mjs 1280
# puis 390, puis la palette bleu (THEME_CSS=/tmp/theme-bleu.css,
# THEME_FOND='rgb(246, 245, 242)', page rendue après avoir choisi « bleu »
# dans Administration → Apparence).
```

Ce que le banc exige, et qu'aucun essai PHP ne peut voir :

- la une sans image porte `une-grande__article--sans-image`, n'a **ni image ni
  zone d'image**, et son bloc de texte est **aussi large que l'article** (une
  seule colonne : 1048 px sur 1048 px à 1280, 363 px sur 363 px à 390) ;
- les cartes `une-carte--texte` n'ont **ni image, ni vignette, ni bloc de
  rubrique, ni tuile `une-carte__vide`**, et **aucun descendant n'a la couleur
  `--papier-moyen`** : c'est la tuile grise, mesurée et non devinée ;
- chaque carte texte porte **titre, date et extrait**, un titre aussi large
  que la carte, et une hauteur qui reste celle du texte (aucune boîte vide) ;
- une vidéo sans miniature garde `une-carte__vignette--video` et
  `une-carte__lecture` : icône **visible**, couleur d'accent, fond `--surface`
  et **aucune balise d'image** ;
- aucune balise `img` de la page n'est vide, toutes sont chargées, aucun
  attribut `style`, aucun script en ligne, aucune ressource extérieure, aucun
  débordement horizontal, aucune erreur JavaScript ;
- la palette demandée est bien appliquée (fond du `body`).
