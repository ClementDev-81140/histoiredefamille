# Contrôle « vrai navigateur »

Ces trois fichiers servent à vérifier **dans un vrai navigateur** ce qu'aucun
essai PHP ne peut voir : le comportement du formulaire pendant et après un
envoi par morceaux (champ « Fichier », champ caché `envoi`, lien-ancre, clic
sur le bouton d'enregistrement), à 390 px et à 1280 px.

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
