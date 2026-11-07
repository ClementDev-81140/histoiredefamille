# Contrôle « vrai navigateur »

Ces quatre fichiers servent à vérifier **dans un vrai navigateur** ce qu'aucun
essai PHP ne peut voir : le comportement du formulaire pendant et après un
envoi par morceaux (champ « Fichier », champ caché `envoi`, lien-ancre, clic
sur le bouton d'enregistrement) et les deux boutons de la liste des fichiers
reçus (**Créer le document**, **Supprimer** et sa confirmation), à 390 px et à
1280 px.

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

Les captures d'écran et le corps du formulaire posté (fichiers `capture-*.txt`)
sont écrits dans `/tmp` (ou dans `CAPTURES=`).

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
