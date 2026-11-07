# Journal des versions

Toutes les dates sont au format `AAAA-MM-JJ`.
Le projet suit un numéro de version simple : `majeure.mineure.correctif`.

## [1.27.0] — 2026-11-07

### Ranger des photos dans un album demandait deux détours

Un album ne contenait rien par lui-même : il fallait d'abord envoyer les photos
depuis **Administration → Envoyer des fichiers**, attendre la création des
fiches, ouvrir l'album, puis cocher les photos une par une — et recommencer tout
ce chemin pour la suivante. La page d'un album, elle, n'offrait rien : ni sur sa
page publique, ni même dans son formulaire d'administration.

Désormais, un administrateur connecté trouve le bouton **Ajouter des photos**
aux deux endroits où il pense à son album :

- sur le formulaire de l'album (**Administration → Albums photos → Modifier**),
  en haut, à côté de « Voir l'album » ;
- sur la page publique de l'album (`/albums/…`), à côté de « Imprimer » et
  « Modifier ».

Le bouton ouvre la page **Ajouter des photos**. Un visiteur, un lecteur ou un
journaliste ne le voit **jamais** : le bouton n'est écrit que pour un
administrateur, et les trois routes concernées commencent par `require_admin()`.

#### Le même envoi par morceaux, pour des photos seulement

Rien de nouveau côté transport : la page réutilise le bloc d'envoi de la 1.26
(glisser-déposer, plusieurs fichiers, une barre par fichier, reprise après
coupure, trois envois simultanés). Trois différences, toutes dans le bloc :

- le champ de fichier n'accepte que **jpg, jpeg, png, webp et gif** ; un film
  déposé là est refusé **tout de suite**, avec le message « Cet album n'accepte
  que des photos (…) » — rien ne part ;
- chaque envoi porte l'**album de destination** (`data-envoi-album`), transmis
  à l'initialisation de l'envoi ;
- le bouton « Compléter ce lot d'un coup » n'est pas affiché : ici, chaque
  photo est déjà rangée.

Les validations habituelles (extension autorisée, type réel du fichier, taille,
nom nettoyé) sont **rejouées sur le fichier assemblé**, comme pour tout envoi.

#### Ce que devient une photo reçue

Dès qu'un fichier est complet, le serveur crée la fiche et la range :

| Champ | Valeur |
|---|---|
| type | **photo** |
| visibilité | **celle de l'album** (une photo ajoutée à un album privé naît privée) |
| titre | **nom du fichier sans extension** (`noces-1962.jpg` → « noces-1962 ») |
| date | celle lue dans le fichier, quand elle existe |
| place | **en fin de liste** de l'album |
| vignettes | générées comme pour tout envoi |

Un doublon (même empreinte SHA-256 qu'une photo déjà connue) est **signalé**,
jamais supprimé : « Garder quand même » ou « Ignorer ce fichier », comme
aujourd'hui.

#### La page reste la même, sans JavaScript indispensable

Sous le bloc d'envoi, la page liste **les photos que vous venez d'ajouter** à cet
album, avec pour chacune :

- un champ **Titre** et un champ **Date**, puis **Enregistrer le titre et la
  date** ;
- **Retirer de l'album**, après confirmation : la photo quitte l'album, **le
  document n'est pas supprimé** (il reste dans « Documents & photos », et peut
  revenir dans un album plus tard).

Ce sont des **formulaires ordinaires** (jeton CSRF, `document_id` caché) : ils
fonctionnent sans JavaScript, et le lien **Voir l'album** est écrit en haut et
en bas de la page. Côté navigateur, une fois toutes les photos arrivées, la page
se recharge **une seule fois** pour afficher la liste tenue par le serveur :
titre, date et retrait ne dépendent d'aucun script.

#### Un envoi, un seul album, une seule photo

- la destination est enregistrée dans l'envoi (`uploads.album_id`, migration
  **018**, en ajout seul : une colonne, un index, aucune ligne touchée) ;
- la conclusion de l'envoi réserve la ligne en **une seule** mise à jour
  conditionnelle (`en_cours` ou `doublon` → `termine`) : un double clic, deux
  onglets ou deux administrateurs ne peuvent pas créer deux photos ;
- la reprise tient compte de l'album : redéposer le même fichier sur la page
  d'un album reprend **cet** envoi-là, et un envoi destiné à un album ne se
  transforme pas en fiche privée (ni l'inverse) ;
- aucune photo d'un autre compte n'est touchée : les envois d'un administrateur
  ne sont ni repris ni modifiés par un autre.

#### Deux défauts de mise en page trouvés en regardant les captures

- **à 1280 px**, la colonne « Actions » du tableau des photos ajoutées sortait
  du cadre : le tableau impose une largeur minimale pensée pour de grands
  tableaux. Il se répartit maintenant sur la place disponible
  (`.tableau-defilant--photos`), et les deux commandes s'empilent ;
- **à 390 px**, ce tableau à trois colonnes était purement et simplement coupé.
  Sur téléphone, chaque photo devient une **fiche empilée** (vignette, titre,
  date, actions) : plus rien à faire glisser latéralement.

En cherchant pourquoi ces règles ne s'appliquaient pas sur écran étroit, un
**défaut plus ancien** est apparu dans `public/assets/style.css` : un bloc
`@media (min-width: 64rem)` ouvert après la 1.10.1 n'était **jamais refermé** (et
l'en-tête de commentaire qui le suivait avait perdu son `/*`). Tout le CSS situé
après — les « finitions comptes », la carte des lieux, les propositions, une
partie des écrans étroits, soit près de six cents lignes — n'était donc appliqué
qu'au-dessus de 1024 px. Le bloc est refermé, le commentaire rétabli, et un
essai vérifie désormais que les accolades de la feuille de style sont
**équilibrées**.

Aucune classe CSS renommée, aucun style ni script en ligne, aucune ressource
externe : une migration en ajout seul, trois routes (`GET
/admin/albums/{id}/photos`, `POST …/photos/titre`, `POST …/photos/retirer`),
une vue, et deux modifications de la feuille de style.

#### Comment cela a été vérifié

- **essais serveur** — nouvelle famille `album` : **54 vérifications, 54 ok**.
  Bouton présent pour l'administrateur sur les deux pages, absent pour le
  visiteur, le lecteur et le journaliste ; page d'envoi réservée (403 pour un
  journaliste, 400 pour un album inconnu, 303 pour un visiteur) ; photo créée
  avec la visibilité de l'album, le titre du fichier, en fin de liste, avec ses
  branches recopiées ; envoi rattaché à **un** album ; doublon signalé et **non
  supprimé** ; double clic qui ne crée qu'une photo ; reprise qui retrouve
  l'envoi de cet album et lui seul ; titre et date corrigés ; « Retirer de
  l'album » qui enlève la photo de l'album **sans** supprimer le document ;
  visibilité respectée pour un visiteur (photo privée invisible) et pour
  l'administrateur ; script d'envoi qui transmet l'album et refuse les
  non-photos ; rechargement unique de la page.
- **famille `envoi`** (régressions) : **122/122** ; `album` : **54/54**.
- **suite complète** : **1 157 essais, 0 échec** ; lint **190 fichiers, 0
  erreur** ; matrice d'accès **190 routes, 760 vérifications, 0 anomalie**
  (248 contrôles ciblés, 0 en échec).
- **vrai navigateur** (Chromium, pages rendues par le routeur, vrais `assets`,
  feuille `/theme.css` rendue par le site) — **21 vérifications sur 21**, quatre
  fois : **papier** et **bleu**, **390 px** et **1280 px**. Bouton visible et
  assez grand, qui mène à la page d'envoi ; zone de dépôt présente, album
  transmis, champ limité aux photos et `multiple` ; deux formulaires ordinaires
  avec jeton et `document_id` et **aucun autre champ** (corps relevé) ; la
  confirmation du retrait dit que le document n'est pas supprimé, et la refuser
  ne poste rien ; dépôt d'une **vraie photo** : l'album part avec l'envoi
  (`album=5` relevé dans le corps), la page se recharge **une fois** ; dépôt
  d'un **film** : refusé sur place, aucun envoi ; album public avec le bouton
  pour l'administrateur et **sans** bouton pour un visiteur ; palette du thème
  demandée réellement appliquée (couleur de fond mesurée) ; impression sans
  débordement ; aucune erreur JavaScript. L'outil est fourni :
  [`tools/navigateur/actions-album.mjs`](tools/navigateur/README.md) (et le
  compte des échecs de `actions.mjs`, qui comptait des objets et annonçait donc
  toujours « tout va bien », est corrigé) ;
- l'ancien contrôle navigateur de la 1.26.3 (`actions.mjs`) a été rejoué après
  la correction de la feuille de style : **12/12** à 390 px et à 1280 px.

#### Ce qui n'a pas pu être vérifié ici

- le **serveur réel** (Apache, Cloudflare, Pi) : le contrat est vérifié par les
  essais serveur ; les pages et les clics par un navigateur réel servi par un
  serveur d'essai local ;
- un **double clic réellement simultané** (deux requêtes en parallèle) : le banc
  d'essai est mono-thread. La garantie est la mise à jour conditionnelle, et le
  second passage est vérifié en séquence ;
- des **photos réelles** de la famille (les essais déposent un PNG de quelques
  kilo-octets) : les vignettes et la date lue dans le fichier sont celles du
  code d'envoi existant, vérifiées par la famille `envoi` ;
- le **rendu d'impression sur papier** : la feuille d'impression est appliquée
  par le navigateur (`emulateMediaType('print')`), mais aucune imprimante n'est
  disponible.

## [1.26.3] — 2026-11-07

### Les fichiers reçus n'avaient aucune action

Constat sur **Administration → Envoyer des fichiers**, tableau « Fichiers reçus,
en attente de formulaire » : ces fichiers étaient arrivés entiers, et la page ne
proposait **rien** pour eux. Trois colonnes (Fichier, Taille, Reçu le), aucun
bouton — et un texte d'aide qui renvoyait à « la liste ci-dessous », une liste
qui n'existe pas : la section suivante, « Reçus depuis hier », ne montre que les
fiches **déjà** créées. Il fallait donc retrouver le bon formulaire, le remplir
et revalider le fichier, ou passer par « Compléter un lot » — pour un fichier
déjà sur le serveur.

#### Deux actions par fichier

| Bouton | Ce qu'il fait |
|---|---|
| **Créer le document** | crée tout de suite la fiche, **en privé**, avec le fichier déjà en place. Aucun formulaire à repasser : le bouton mène **directement à la fiche à compléter** (au formulaire vidéo, s'il s'agit d'un film) |
| **Supprimer** | après **confirmation** (le nom du fichier est rappelé dans la question), efface le **fichier assemblé** et les **morceaux** reçus ; l'envoi quitte la liste |

La fiche créée reprend **exactement** ce que la fiche automatique de fin d'envoi
aurait écrit : titre tiré du nom du fichier, miniature, dimensions, date lue
dans le fichier (`date_meta`), empreinte `sha256`, visibilité `prive`, aucune
catégorie, aucun album. Rien n'est recalculé ni redemandé, et une vidéo dans un
format que les navigateurs ne lisent pas partout entre dans la file de
conversion comme depuis le formulaire.

#### Un fichier reçu ne devient un document qu'une seule fois

La création réserve le fichier **avant** d'écrire la fiche, par un
`UPDATE … WHERE state = 'pret'` conditionnel : un double clic, deux onglets ou
deux administrateurs ne peuvent pas en tirer deux fiches — le second reçoit un
message clair (« déjà rattaché à une fiche »). Si l'écriture de la fiche échoue,
la réservation est **annulée** : le fichier redevient « en attente », rien n'est
perdu.

Un fichier déjà rattaché à une fiche ne se supprime **pas** depuis cette liste :
le fichier appartient alors à la fiche, et c'est depuis sa page qu'on le
remplace ou qu'on le supprime (sinon la fiche pointerait sur un fichier
disparu). Le bouton « Supprimer » ne s'applique donc qu'à l'état « prêt ».

#### Seul le compte qui a envoyé agit

Les deux actions vérifient, dans cet ordre : administrateur connecté, jeton
CSRF, fichier existant, **fichier appartenant au compte connecté**, état
« prêt ». La page d'envoi ne liste de toute façon que **vos** fichiers, et la
vue **Tous les envois** (« Mes envois » → supervision) est en **lecture seule** :
elle montre le fichier d'un autre administrateur avec son auteur, sans aucun
bouton d'action (le lien « Compléter » qui s'y trouvait, et qui menait au lot de
celui qui regardait, a été retiré pour les fichiers des autres).

Aucun chemin ne vient du navigateur : les formulaires n'envoient qu'un jeton de
32 caractères hexadécimaux, et la redirection après action est fixe.

#### Deux défauts voisins corrigés

- **Le texte d'aide** du tableau ne renvoie plus à une liste inexistante : il
  explique ce que font les deux boutons, que le fichier reste privé, qu'il ne
  peut devenir un document qu'une fois, et que seul l'expéditeur peut agir ;
- **« Annuler » sur un fichier prêt ne perd plus le fichier** : `envoi_annuler()`
  n'effaçait le fichier assemblé que pour l'état « doublon ». Sur un envoi
  « prêt » (ou depuis « Mes envois »), les morceaux partaient mais le fichier
  restait sur le disque pour toujours. Il est maintenant supprimé pour les deux
  états — et **jamais** pour « terminé », où le fichier appartient à la fiche.

Aucune migration, aucune classe CSS renommée, aucun style ni script en ligne :
deux routes POST (`/admin/envoyer/creer-document`, `/admin/envoyer/supprimer`),
deux formulaires ordinaires avec jeton CSRF, et la confirmation passe par
l'attribut `data-confirmation` déjà lu par `app.js`.

#### Comment cela a été vérifié

- **essais serveur** (`php app/cli/tests.php envoi`, **122/122**, dont 30
  nouveaux) : création d'une fiche privée **sans formulaire** (visibilité
  `prive`, auteur, empreinte, fichier intact, titre, redirection vers la fiche),
  deuxième clic refusé (**une seule** fiche), fiche invisible pour la famille,
  envoi encore en cours refusé, **autre administrateur** refusé (ni création ni
  suppression, fichier intact), **compte de famille** refusé (403), **sans jeton
  CSRF** refusé (403), suppression qui **efface le fichier assemblé** et les
  morceaux, fichier déjà rattaché **non supprimé**, vidéo reçue qui ouvre le
  formulaire vidéo, et le texte de la page (colonne « Actions », deux boutons,
  confirmation, aide corrigée, aucun style ni script en ligne) ;
- **vrai navigateur** (Chromium, page réelle rendue par le routeur, vrais
  `assets`), à **390 px** et **1280 px** : **12 vérifications sur 12** par
  largeur — aucune barre horizontale, colonne « Actions » présente, les deux
  boutons dans l'ordre, deux POST avec jeton CSRF et jeton d'envoi (**aucun
  autre champ**), la confirmation qui **nomme le fichier** au clic sur
  « Supprimer », le refus de la confirmation qui ne poste rien, le clic sur
  « Créer le document » qui poste bien `_csrf` et `token`, ligne de tableau
  compacte, aucune erreur JavaScript. L'outil est fourni :
  [`tools/navigateur/actions.mjs`](tools/navigateur/README.md) ;
- **suite complète** : **1 101 essais, 0 échec** ; lint **188 fichiers, 0
  erreur** ; matrice d'accès **187 routes, 748 vérifications, 0 anomalie**.

#### Ce qui n'a pas pu être vérifié ici

- les deux actions **sur le Pi** (Apache, Cloudflare) : le contrat est vérifié
  par les essais serveur, la page et les clics par un navigateur réel servi par
  un serveur d'essai local (les réponses du site y sont rejouées, seule la page
  rendue par le routeur est servie telle quelle) ;
- un **double clic réellement simultané** (deux requêtes en parallèle) : le banc
  d'essai est mono-thread. La garantie est celle de la réservation atomique
  (`WHERE state = 'pret'`), et le second passage est vérifié en séquence ;
- la **conversion réelle** d'une vidéo reçue ainsi (pas de `ffmpeg` sur la
  machine d'essai) : seule la mise en file est vérifiée.

## [1.26.2] — 2026-11-07

### Le fichier envoyé par morceaux ne remplissait pas le formulaire

Constat en navigateur, sur **Nouvelle vidéo** et **Nouveau document** : après un
envoi par morceaux réussi, le formulaire refusait de partir. Le navigateur
affichait « Veuillez sélectionner un fichier » et pointait la case **Fichier**,
restée vide ; le message, lui, disait seulement « enregistrez le formulaire
ci-dessous », et le bouton — nommé « Envoyer la vidéo » — se trouvait tout en
bas, hors de l'écran. Une personne a conclu qu'il n'y avait pas de bouton.

#### La cause exacte

Le script **écrivait bien** le jeton dans le champ caché `envoi`, et croyait
retirer l'obligation de la case « Fichier ». Mais il cherchait
`input[type="file"]` — et le bloc d'envoi a **son propre** champ de fichier,
masqué (`data-envoi-champ`). Le script tombait donc sur ce champ-là, qui n'a
jamais été obligatoire, et laissait `required` sur la vraie case `fichier` :
le navigateur bloquait, sans que le serveur voie jamais la requête.

#### Ce qui change pour la personne qui envoie

| Avant | Maintenant |
|---|---|
| « Le fichier est prêt : enregistrez le formulaire ci-dessous » | « Envoyé. Le fichier est prêt : cliquez sur « Envoyer la vidéo » » (le libellé est lu dans le formulaire : « Envoyer le document », « Enregistrer »…) |
| aucun moyen de retrouver le bouton, tout en bas de la page | un lien **« Aller au bouton d'enregistrement »** sous le message, lien-ancre ordinaire (`#enregistrer`, sans JavaScript, sans style en ligne) |
| la case **Fichier** restait obligatoire, le navigateur bloquait | elle n'est plus obligatoire dès que l'envoi est terminé, et le navigateur ne réclame plus rien |
| une erreur de formulaire (titre vide) faisait perdre le fichier reçu | le jeton est écrit dans un champ caché **rendu par le serveur** : le fichier reste rattaché après une erreur, sans le renvoyer |

#### Ce qui change côté serveur

Le fichier assemblé remplace `$_FILES['fichier']` **seulement** si le jeton est
valide, et la vérification est stricte :

- il doit appartenir à **l'administrateur connecté** (le jeton d'un autre compte
  est refusé, et reste « prêt » chez son propriétaire) ;
- il doit être **terminé** (état `pret`) : un jeton inconnu, expiré ou inventé
  est refusé avec un message clair ;
- il ne peut servir qu'à **une seule fiche** : la réservation est un
  `UPDATE … WHERE state = 'pret'` conditionnel, donc deux enregistrements
  simultanés (double clic, deux onglets) ne peuvent pas le rattacher deux fois ;
- **aucun chemin ne vient du navigateur** : le jeton ne sert qu'à retrouver une
  ligne de la base, le nom et l'emplacement du fichier sont ceux enregistrés à
  l'assemblage ;
- les **validations habituelles** (extension autorisée, type réel par `finfo`,
  taille, nom nettoyé) ont déjà été rejouées à l'assemblage, et le genre de
  fichier est contrôlé une seconde fois par le formulaire (une vidéo pour
  « Nouvelle vidéo »).

Deux défauts voisins corrigés au passage :

- en **modification**, remplacer un fichier par l'envoi par morceaux laissait
  l'ancien fichier sur le disque pour toujours ; il est maintenant supprimé
  après succès, comme pour l'envoi simple ;

#### Comment cela a été vérifié

- **vrai navigateur** (Chromium), sur les deux formulaires, à **390 px** et
  **1280 px** : 14 vérifications par combinaison, toutes vertes — message
  nommant le vrai bouton, lien-ancre qui amène le bouton dans l'écran, case
  « Fichier » non obligatoire, champ caché rempli, clic sur le bouton **sans**
  choisir de fichier et formulaire bien posté (`envoi` présent, aucun fichier
  joint), aucune erreur JavaScript, aucun défilement horizontal. L'outil est
  fourni : [`tools/navigateur/`](tools/navigateur/README.md) ;
- **essais serveur** (`php app/cli/tests.php envoi`, **92/92**) : création d'un
  document et d'une vidéo **sans** `$_FILES`, jeton réutilisé refusé, jeton d'un
  autre compte refusé, jeton inconnu refusé, absence de fichier et de jeton
  signalée, fichier qui n'est pas une vidéo refusé, MP4 déjà bon non converti,
  MOV en conversion donc invisible pour la famille, administrateur qui la voit ;
- **suite complète** : **1 071 essais, 0 échec** ; lint **188 fichiers, 0
  erreur** ; matrice d'accès **185 routes, 740 vérifications, 0 anomalie**.

#### Ce qui n'a pas pu être vérifié ici

- le **téléversement réel sur le Pi** (Apache, Cloudflare, vrais gros
  fichiers) : le contrat est vérifié par essais, le navigateur par un serveur
  local qui renvoie exactement les réponses du site ;
- la **conversion réelle** d'un MOV (pas de `ffmpeg` sur la machine d'essai) :
  seule la mise en file et l'invisibilité pendant la conversion sont vérifiées.

## [1.26.1] — 2026-11-07

### Les en-têtes de sécurité partaient deux fois

Constat sur le Pi, avec `curl -sI http://localhost` : `X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` et
`Content-Security-Policy` étaient envoyés **deux fois** — une fois par Apache,
une fois par PHP — et **avec deux valeurs différentes** pour la CSP et
`Permissions-Policy`. Le contrôle de `verifier-mise-en-ligne.php` lisait
« `DENY, DENY` » et annonçait donc « En-tête(s) manquant(s) », alors que les
en-têtes étaient bien là.

Deux valeurs, deux effets pervers : le navigateur applique **l'intersection**
des politiques, donc la plus faible des deux pour `Permissions-Policy`, et plus
personne ne peut dire laquelle fait foi en lisant la réponse.

#### Une seule source par en-tête

| En-tête | Source unique, depuis cette version |
|---|---|
| `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` | **Apache** — `public/.htaccess` |
| `Content-Security-Policy` | **Apache** — `public/.htaccess` (elle ne dépend pas de la page) |
| `Strict-Transport-Security` | **PHP** (`app/lib/security.php`), seulement si `https_only` : c'est un réglage, pas une constante |
| `Cache-Control` privé | **PHP**, pour la même raison |

Le PHP n'envoie plus les cinq en-têtes statiques (`app/lib/security.php`,
`app/controllers/files.php`, `app/controllers/admin_export.php`,
`app/lib/stream.php`). Apache les pose dans `public/.htaccess`, qui couvre
**aussi** les fichiers statiques (`maintenance.html`, `/assets/...`) — ce que
le PHP ne fait pas.

Chaque en-tête y est précédé de `Header unset` **et** `Header always unset`
avant son `Header always set` : même si un module, un ancien `.conf` ou un
mandataire en avait déjà ajouté un, la réponse n'en contient plus qu'un. Les
lignes en doublon ont été retirées de `deploy/apache/histoiredefamille.conf`,
qui garde seulement `Header unset Server` et `Header unset X-Powered-By` (ce
qu'Apache seul peut faire).

La CSP posée par Apache est **la plus stricte du site** : `default-src 'self'`,
aucune ressource externe (aucune adresse absolue, aucun `*`), et surtout aucun
`unsafe-inline`. Un essai compare le texte de `public/.htaccess` à
`security_csp_valeur()` : les deux ne peuvent plus diverger sans que les tests
le disent.

#### Le contrôle lit ce qui arrive vraiment

`verifier-mise-en-ligne.php` ne se laisse plus tromper :

- il **ignore la casse** des noms (`x-frame-options` = `X-Frame-Options`) et
  celle des valeurs (`deny` = `DENY`, `nosniff` = `NOSNIFF`) ;
- il reconnaît les trois façons dont un doublon arrive : deux lignes, une seule
  ligne avec des virgules (« `DENY, DENY` »), ou deux valeurs séparées par
  `|` dans la réponse analysée ;
- il distingue désormais **trois défauts** : « manquant » (**à corriger**),
  « pas assez strict » (**à corriger**, par exemple une CSP avec
  `unsafe-inline`), et **« envoyé 2 fois »** (**avertir**, non bloquant) ;
- quand chaque en-tête n'arrive qu'une fois, il repasse **au vert**, avec
  `Permissions-Policy` désormais contrôlé lui aussi.

#### Ce qui a été vérifié, et ce qui ne l'a pas été

- **Vérifié** : les 15 nouveaux essais (doublons sur deux lignes, doublons sur
  une ligne, casse libre, CSP affaiblie, source unique dans les fichiers,
  absence de doublon dans `deploy/apache/`) ; la suite complète (**1 045 essais,
  0 échec**) ; les pages, l'envoi par morceaux et la vidéo, qui passent par les
  mêmes essais qu'avant et restent vertes ; la CSP elle-même, contrôlée par
  essai (`default-src 'self'`, sans `unsafe-inline`, sans adresse externe).
- **Non vérifié** : Apache lui-même n'existe pas sur le banc d'essai (PHP en
  WebAssembly). La configuration a donc été écrite et relue, pas exécutée. Après
  la mise à jour sur le Pi, `curl -sI http://localhost` doit montrer **une seule
  ligne par en-tête** : c'est le contrôle à faire en premier, il prend
  dix secondes.

## [1.26.0] — 2026-11-05

### La dernière passe avant la mise en ligne

Cette version **n'ajoute aucune fonctionnalité**. C'est une passe de contrôle :
on a cherché ce qui casse, ce qui ment, et ce qui laisserait la famille devant
une page blanche ou un fichier perdu — puis on a corrigé. Aucune règle de
sécurité ne change : CSP stricte, CSRF, droits et matrice d'accès restent
identiques, et **aucune classe CSS n'est renommée**. Une seule migration est
ajoutée (`017`), en ajout seul (voir plus bas).

#### 1. La conversion des vidéos ne perd plus de travail

| Ce qui n'allait pas | Ce qui se passe maintenant |
|---|---|
| Un disque presque plein au moment de lancer `ffmpeg` = **échec définitif** de la vidéo | le travail est **reporté** au passage suivant du minuteur, sans consommer d'essai ; rien n'est abandonné |
| Deux gros travaux en même temps (un envoi par morceaux + une conversion) : le Pi n'a qu'**1 Go de mémoire** | la conversion **attend** la fin de l'envoi en cours (même règle de délai que l'écran « Mes envois ») |
| `ffmpeg` à pleine vitesse rendait le site et le tunnel Cloudflare presque inutilisables | la commande tourne sous **`nice`** (priorité basse), sans passer par un shell |
| Une **coupure de courant** laissait un travail « en cours » pour toujours : la file ne repartait plus jamais | `conversion_recuperer()` remet d'aplomb la file (au démarrage de la commande **et** dans la maintenance quotidienne) et efface les fichiers temporaires à moitié écrits |
| Une vidéo en conversion (ou en échec) était invisible pour tout le monde, **y compris pour la personne qui venait de l'envoyer** | son auteur la retrouve dans « Mes envois » avec l'état « en préparation, 42 % » ; les autres comptes de la famille ne la voient toujours pas |

Rien de tout cela n'ouvre un droit : la vidéo d'un autre reste invisible tant
qu'elle n'est pas prête, et les administrateurs continuent de tout voir.

#### 2. Une vidéo en échec reste invisible (et le dit)

Une conversion qui échoue est désormais traitée comme une vidéo « pas prête » :
elle n'apparaît ni dans les listes, ni dans la recherche, ni par une adresse
directe pour les comptes de famille. Avant, un échec pouvait laisser la famille
devant un lecteur qui ne lisait rien. L'administrateur, lui, voit la fiche,
l'état et le message d'erreur, avec **Relancer** et **Annuler**.

#### 3. Les propositions déjà validées ont enfin des versions

Jusqu'ici, un journaliste qui corrigeait **une proposition déjà validée**
écrivait par-dessus le contenu publié : le texte visible des lecteurs changeait
avant toute relecture, ou le contenu disparaissait le temps de la validation.
Ce n'est pas ce que promet l'écran « Mes propositions ».

- **Migration `017_versions_propositions.sql`, en ajout seul** : une colonne
  `replaces_id`, vide par défaut, sur `articles`, `documents` et `recipes`.
  Aucune ligne n'est modifiée ni supprimée ; sans elle, le site se comporte
  exactement comme avant.
- Modifier un contenu publié crée maintenant une **version en attente** : une
  ligne de plus, invisible des lecteurs, qui porte le nouveau texte. L'original
  continue d'être servi tel quel.
- **À la validation**, le texte de la version remplace celui de l'original et
  la version disparaît. **En cas de refus**, l'original n'est pas touché.
- **Une seule version à la fois** : modifier deux fois de suite le même contenu
  ne crée pas deux projets concurrents — c'est la version existante qu'on
  reprend.
- **L'adresse (le `slug`) d'un contenu publié ne change jamais** : les liens
  déjà envoyés à la famille continuent de fonctionner. Une version a sa propre
  adresse, ce qui évite un doublon dans la base.
- Nouveau bouton **« Retirer »** dans « Mes propositions » :
  `POST /propositions/{type}/{id}/retirer`. Seul l'auteur peut le faire, jamais
  sur un contenu publié ; les fichiers déposés sont effacés **s'ils ne sont plus
  référencés nulle part**. L'action est journalisée (`proposition_retiree`).
- L'aperçu « À valider » affiche désormais le **motif du refus** et un bandeau
  **« Version d'un contenu déjà publié »** avec le lien vers l'original.

#### 4. Le motif d'un refus reste privé

Le motif écrit par l'administrateur est visible **par l'auteur** (dans
« Mes propositions ») et **par les administrateurs** (dans l'aperçu). Il
n'apparaît **nulle part ailleurs** : ni dans les listes, ni dans la recherche,
ni sur les pages publiques, ni dans le tchat. Un essai le vérifie page par page.

#### 5. Retirer les données de démonstration, sans risque

Jusqu'ici, le seul moyen de se débarrasser des contenus fictifs était de tout
effacer. Deux nouveautés :

- `app/cli/demo.php` **note ce qu'il crée** dans `storage/demo-registry.json`
  (plages d'identifiants, par table) **et recopie la même liste dans les
  réglages du site** : si le dossier `storage` est reconstruit ou si l'on ne
  restaure que la base, la purge retrouve quand même sa trace.
- Nouvelle commande **`app/cli/purger-demo.php`** :
  `--liste` montre ce qui partirait (sans rien supprimer) ; `--oui` supprime.
  Elle reconnaît les contenus au registre **et**, à défaut, au mot « (démo) »
  écrit dans leurs titres — une base ancienne reste donc purgeable.
- Les documents partent **avec leurs fichiers et leurs miniatures** ; un fichier
  encore utilisé ailleurs est conservé.
- Les **liens de branche** (`content_branches`) sont nettoyés : cette table n'a
  pas de clé étrangère sur le contenu, donc une suppression ne l'efface pas
  toute seule — la purge laissait sinon des liens pointant vers des lignes
  disparues.
- **Les comptes ne sont jamais supprimés** : la commande les compte et renvoie
  vers *Comptes* pour les désactiver.
- Elle lance une **sauvegarde d'abord** : si la sauvegarde échoue, **rien n'est
  supprimé** et la commande s'arrête avec un code d'erreur.

#### 6. « Étant prêt à ouvrir » veut enfin dire quelque chose

La vérification avant mise en ligne gagne un quatrième état, **`AVERTIR`** :

| État | Verdict |
|---|---|
| `OK` / `INFO` | rien à faire |
| `À CORRIGER` | **bloque l'ouverture** |
| `AVERTIR` | à lire, mais **n'empêche jamais d'ouvrir** |

Passent en `AVERTIR` : HTTPS obligatoire, données de démonstration, nombre
d'administrateurs, dernière sauvegarde et son minuteur, courriel de contact et
son minuteur. Le seuil de sauvegarde passe de **3 jours à 48 heures**, et deux
contrôles sont ajoutés (courriel configuré, minuteur d'envoi actif).

La page **État du site** range maintenant ces avertissements dans **leur propre
tableau** (« À regarder, sans bloquer l'ouverture »), au lieu de les mêler aux
points bloquants.

#### 7. L'espace disque, en trois couleurs

Le tableau de bord et la page « État du site » affichent l'espace libre avec un
seuil clair : **orange sous 20 %**, **rouge sous 10 % ou sous 5 Go**, et
« inconnu » quand la mesure est impossible. Aucun blocage : c'est une alerte
pour agir avant que les envois ne s'arrêtent. Le pourcentage protège les
grandes cartes, le plancher de 5 Go protège les petites (un Pi 3B+ a souvent
32 Go).

#### 8. Corrections de détail

- Page « État du site » : plus d'avertissement PHP (« variable indéfinie
  `$espace` ») quand la vue est rendue hors de son contrôleur — elle recalcule
  l'état du disque.
- `tests_entretien.php` : un essai comparait une chaîne **interpolée** au lieu
  du texte du code (`$current !== '/admin/etat'`) ; il vérifie maintenant ce
  qu'il annonce.
- `tests_profils.php` : l'essai « un visiteur ne peut rien proposer » vidait
  mal la session et pouvait interroger le compte de l'essai précédent.

#### 9. Essais et documentation

- **1 028 essais** au total, dont les nouvelles familles « demo » (17 essais :
  registre, inventaire, purge, fichiers, comptes intacts, commande) et les
  « cas limites » des propositions (12 essais : motif privé, compte désactivé,
  retrait, versions).
- `README.md` : la purge des données de démonstration (§ 4.8), les quatre états
  de la vérification et le seuil de 48 heures (§ 7).
- `lint.php` : **187 fichiers** relus, 0 erreur.

#### Ce qui n'a pas pu être vérifié ici

- **La conversion réelle sur le Pi** : le banc d'essai n'a pas de PHP natif ni
  de `proc_open`. La commande `ffmpeg` a été construite et **exécutée depuis
  Node** sur un vrai fichier `.mov` (résultat MP4 lisible), mais pas par la
  commande PHP elle-même. Le comportement de `nice`, du report et de la reprise
  après coupure est vérifié par essais unitaires sur la base, pas par une vraie
  coupure de courant.
- **`fgets(STDIN)` de `purger-demo.php`** : le bac à sable n'a pas d'entrée
  standard. La confirmation OUI est vérifiée par lecture du code (`--oui`
  explicite), pas par une saisie réelle.
- **`purger-demo.php` sur une base de production** : la purge a été jouée sur
  une base d'essai, dans une transaction, avec de vrais fichiers. Le cas d'une
  clé USB pleine au moment de la sauvegarde préalable n'a pas été reproduit.
- **Les en-têtes HTTP réels d'Apache** : vérifiés par la commande
  `verifier-mise-en-ligne.php` sur la machine cible, pas sur le banc.
- **La migration `017` sur une base de plusieurs milliers de documents** : elle
  a été jouée sur la base d'essai (rapide). Sur un Pi, `ALTER TABLE ADD COLUMN`
  est instantané en SQLite, mais le temps réel n'a pas été mesuré.

## [1.25.2] — 2026-11-05

### L'administration ne se coupe plus en deux

Le menu rangeait ses pages en « Partie publique » et « Partie famille », et le
tableau de bord avait deux cartes du même nom : il fallait additionner de tête
pour savoir combien le site contient de documents. Un contenu n'a pourtant
qu'une visibilité, pas un camp. **Aucune règle d'accès ne change** : c'est un
rangement.

#### Le menu latéral, rangé par métier

Cinq groupes, sans les mots « public » ni « famille », et **tous les liens
d'avant** :

| Groupe | Pages |
|---|---|
| *(en tête)* | Tableau de bord, **À valider** avec son compteur |
| **Contenus** | Articles, Pages d'informations, Documents & photos, Albums photos, Vidéos, Recettes, Catégories |
| **Envois** | Envoyer des fichiers, Dossier arrivée, Conversions, Mes envois |
| **Personnes** | Arbre généalogique, Comptes, Branches & comptes, Administrateurs, Lieux à placer |
| **Journal** | Courriels, Gazette du mois, Apparence du journal, Composition des pages |
| **Système** | Réglages, Journal des connexions, État du site |

Deux précisions sur la répartition demandée : **Calendrier** ne figure pas dans
*Contenus* parce que l'administration n'a **aucun écran de calendrier** (le
calendrier est une page publique, alimentée par les fiches de personnes : rien
n'a été inventé) ; et **Composition des pages** est restée dans *Journal*, avec
*Apparence du journal*, parce qu'elle règle la forme du journal et non son
contenu. Aucune page n'est supprimée, aucune adresse ne change, aucun droit ne
bouge.

#### Le tableau de bord : une carte « Contenus »

- Les deux cartes côte à côte disparaissent au profit d'**une seule**, avec les
  sept chiffres demandés : documents, articles, pages d'information, vidéos,
  personnes, comptes (et branches actives) et propositions à valider.
- **Une ligne « dont publics : N »** donne la part visible sans compte —
  documents publics + articles publiés + pages publiées. Les personnes n'y
  sont pas : l'arbre est fermé au visiteur depuis la 1.24.0.
- **« Documents publics », « Articles publics », « Pages publiques »** sont
  désormais de simples raccourcis vers les listes, filtre déjà posé
  (`?visibilite=public`). « Voir le site public » reste un lien texte.
- Le dossier arrivée n'apparaît plus en permanence : une ligne d'information
  n'est affichée **que s'il y a des fichiers en attente**.

#### Le filtre « Visibilité » partout

- **Articles, Pages d'informations, Albums photos, Recettes** reçoivent le filtre
  qui existait déjà sur Documents et Vidéos : *Toutes*, *Public*, *Familles*,
  *Branches*, *Privé*. Formulaire **GET** (aucun JavaScript), choix conservé
  dans l'adresse et donc partageable ; une valeur inventée est ignorée et
  redonne la liste complète.
- Un seul fichier de vue porte ce formulaire
  (`views/admin/partials/filtre_visibilite.php`) pour les quatre nouvelles
  listes ; Documents et Vidéos gardent le leur, qui propose aussi la recherche
  et le type de fichier.

#### Vidéos : les vieilles phrases, une dernière fois

Un texte de démonstration disait encore « lecture par morceaux, **sans
conversion** » (le site convertit depuis la 1.21.0). Il est corrigé, et un test
relit les vues vidéo en cherchant huit tournures d'avant la conversion
automatique ("ne convertit rien", "sans conversion", "reste telle quelle : le
site"…). La page « Vidéos » et son formulaire disent la vérité : MP4 publié tel
quel, tout autre format converti en arrière-plan.

#### Vérifications

- **947 tests** (`php app/cli/tests.php`), **0 échec**, dont **31 nouveaux**
  (famille `admin`) : disparition des deux anciens titres, cinq groupes dans
  l'ordre, aucun intitulé contenant « public » ou « famille », « À valider » en
  tête, présence des 23 liens, carte unique, total « dont publics » comparé au
  décompte réel de la base, sept compteurs, raccourcis filtrés, filtre des
  quatre nouvelles listes dans les trois valeurs (tous/public/privé) et sur une
  valeur inventée, absence des vieilles phrases vidéo.
- Analyse syntaxique : **184 fichiers**, 0 erreur.
- Matrice d'accès régénérée : **184 routes**, **736 vérifications**,
  **240 contrôles**, **0 anomalie** — aucun droit n'a bougé. Une ligne
  s'ajoute aux « Points examinés » pour dire que `?visibilite=` ne change rien
  aux droits ; les numéros de fichiers de démonstration changent (l'ordre de la
  base d'essai), les codes répondus sont identiques.
- **Treize captures d'administration refaites** en 1265 px (la largeur des
  images déjà en place : 1280 moins la barre de défilement) et palette **bleu
  d'origine** : tableau de bord, listes (articles, documents, vidéos, albums,
  recettes), fiches et formulaires, tchat et réglages. Les pages publiques et
  les captures d'accueil n'ont pas bougé. Pages mesurées aussi en **390 px** :
  `scrollWidth` égal à la largeur de la fenêtre partout, aucun débordement
  (sauf le tableau des documents, déjà signalé avant cette version). Le
  `README` des captures dit maintenant quelles captures ont été refaites, et
  pourquoi leur largeur est 1265 et non 1280.

## [1.25.1] — 2026-11-05

### L'accueil d'un compte connecté, et trois retouches

Trois changements visibles seulement pour ceux qui les cherchaient : ce qu'un
compte connecté trouve sur la page d'accueil, la phrase qui la présente, et le
bouton « Se connecter » qui manquait d'air.

#### « Dans la famille », à la place des portes

- Sur la page d'accueil (`/`), un compte connecté — lecteur, journaliste,
  administrateur — ne voit plus les deux portes du visiteur : « Consulter »
  était une présentation destinée à qui découvre le site, elle n'a plus de
  sens une fois entré. « Se connecter » avait déjà disparu pour lui.
- À leur place, plus bas, un bloc **« Dans la famille »** en quatre panneaux :
  **anniversaires** des trente prochains jours, **événements à venir** du
  calendrier (mariages et souvenirs), **personnes ajoutées** récemment,
  **documents et articles parus depuis la dernière visite**.
- **Strictement dans les droits du compte** : les listes réutilisent le
  calendrier (`calendrier_evenements()`, filtré par `visibility.php`) et les
  conditions de visibilité des documents, articles et personnes. Aucune règle
  d'accès n'est écrite pour ce bloc, donc aucune ne peut diverger. Les tests de
  droits sont explicites : un lecteur de la branche A ne voit ni l'anniversaire
  ni la personne réservée à la branche B, ni une personne privée ; un
  administrateur, lui, les voit.
- **« Depuis votre dernière visite »** part de la connexion précédente
  (`accounts.last_login_at`, gardée dans la session avant d'être écrasée). À la
  toute première visite, faute de repère, la fenêtre est de sept jours : le
  bloc ne déverse pas tout le site.
- **Bloc vide : pas de section.** Un visiteur n'obtient rien du tout (la
  fonction n'est même pas appelée), et un compte qui n'a rien à voir ne reçoit
  ni titre ni cadre vides.
- Le bloc s'ajoute à la **composition des pages** (« Dans la famille (compte
  connecté) », sous la une) : il peut être masqué ou déplacé comme les autres,
  et un bloc masqué n'est pas calculé.
- Au passage, `gazette_annonces()` et le nouveau bloc partagent le même calcul
  d'événements (`calendrier_prochains()`) : les petites annonces du journal ne
  peuvent plus diverger des anniversaires de l'accueil.

#### La phrase d'accueil ne parle plus de l'arbre

- « Documents, photos, vidéos et arbre généalogique de la famille » devient
  **« Les souvenirs de la famille : documents, photos, récits et nouvelles. »**
  Un visiteur ne voit pas l'arbre : la phrase qui l'accueille n'a plus à le
  promettre. Nouvelle valeur dans `config.example.php` et dans les exemples du
  README.
- Les sites déjà installés ne sont pas oubliés : l'ancienne phrase par défaut
  est **reconnue** (`APPARENCE_DESCRIPTION_ANCIENNE`) et remplacée à
  l'affichage. Une description écrite par un administrateur, elle, est
  respectée au caractère près.
- La même phrase sert de **description pour les moteurs de recherche**
  (`<meta name="description">`) : elle est corrigée là aussi, et le champ
  « Réglages → Description » montre désormais le texte réellement affiché.
- Deux autres mentions vues par un visiteur sont corrigées : le texte de la
  page de démonstration « Bienvenue (démo) » et la phrase de la page
  Recherche (« les personnes de l'arbre » → « les personnes »). Les pages
  d'information écrites par un administrateur gardent leur texte : c'est le
  sien.

#### Le bouton « Se connecter » respire

- La pastille du menu public n'avait **aucune marge intérieure** : le texte
  touchait le fond plein. La règle `body:not(.page-admin) .nav a` (plus
  précise) écrasait le `padding` de `.nav__accent--connexion`. Une règle
  dédiée rétablit 0,55 rem / 1,25 rem (0,5 / 1 rem sur téléphone), vérifié sur
  les captures à 1280 et 390 px.

#### Vérifications

- **916 tests** (`php app/cli/tests.php`), **0 échec** : 11 nouveaux sur le
  bloc « Dans la famille » (visiteur sans bloc, lecteur avec ses anniversaires,
  branche voisine invisible, personne privée invisible, document récent,
  journaliste, administrateur, absence de la porte « Consulter », bloc vide non
  affiché, fonction muette pour un visiteur, fenêtre « depuis ») et 2 sur la
  description (nouvelle phrase, ancienne reconnue et remplacée). Les attentes
  de la composition passent de sept à huit blocs.
- Analyse syntaxique : **182 fichiers**, 0 erreur.
- Captures refaites en 1280 et 390 px, palettes « papier ancien » et « bleu
  d'origine » : page publique (visiteur) et la même page vue par un lecteur
  (`docs/captures/README.md`). Aucun débordement horizontal, `scrollWidth`
  égal à la largeur de la fenêtre dans les quatre cas.
- Aucune route, aucun droit, aucune migration ne change : la matrice d'accès
  de la 1.25.0 reste valable (184 routes, 736 vérifications, 0 anomalie).

## [1.25.0] — 2026-11-05

### Des pages plus calmes, et un site qui vit sans disque externe

Deux chantiers : **enlever les textes qui expliquent au lieu de faire agir**, et
**rendre le site utilisable dès les premiers jours**, avant l'arrivée du disque.

#### Textes retirés (aucune fonctionnalité perdue)

Rien de ce qui sert à agir n'a bougé : libellés, noms de champs, messages
d'erreur, avertissements de sécurité réels, aides courtes qui évitent une
erreur. Le **tchat n'a pas été touché**. Ce qui a été retiré (une partie pendant
le travail de la 1.24.0, le reste ici — même branche, même publication) :

- **Page publique** : l'encadré de l'espace réservé (« …par n'importe quel
  visiteur »), l'introduction des témoignages (« Des voix de la famille… chaque
  enregistrement est un document comme les autres »), la fin de l'introduction
  des vidéos (« les vidéos sont lues directement, sans conversion »), les
  rappels de la page document et de la carte.
- **Espace famille** : la phrase de droits sous « Bonjour » (remplacée par
  « Branche X »), les commentaires de l'accueil sur les nouveautés.
- **Mon compte** : explication longue du retard des courriels (remplacée par
  une phrase : « un seul message par article, et seulement pour les articles
  publiés après le moment où vous cochez »), rappel de désinscription en un
  clic, mention « tout est facultatif » raccourcie.
- **Mes propositions** : le paragraphe d'introduction est ramené à une phrase.
- **Administration** : notes d'introduction de « Tableau de bord » (deux
  panneaux + rappel vidéos), d'« Apparence du journal » (« rien n'est
  enregistré avant… », explication des polices), de « Composition des pages »
  (phrase dupliquée), des « Recettes », des « Albums », des « Vidéos »
  (déplacement vers le stockage), des « Courriels » (renvoi au README), des
  « Conversions », des « Réglages » (deux paragraphes et le panneau
  « Information »), des comptes (cases de courriel), des pages de formulaire
  (aide de visibilité, rappels de mise en forme, avertissements d'envoi).
  Environ **28 blocs** au total, dans 25 vues.
- **« Sauvegarde : Sauvegarde : »** ne s'affiche plus deux fois : le titre est
  posé par la vue, jamais par le message ; un test le vérifie.

#### Deux phrases ambiguës, corrigées

Repérées en relisant les textes restants :

- « Un compte unique par branche, identifiant en majuscules » (Branches et
  comptes) laissait croire qu'il fallait un identifiant unique dans toute la
  base. Devenu : « Un seul compte par branche. L'identifiant s'écrit en
  majuscules. »
- « Il faut aussi que le disque ait 2 Go de marge libre » (Réglages) ne disait
  pas **quel** disque. Devenu : « Le disque des données doit aussi garder 2 Go
  libres. »

Aucune autre tournure n'a paru ambiguë : les mentions de visibilité et les
messages d'erreur restent explicites.

#### Vivre sans disque externe

- **`backup_allow_same_disk`** : nouveau réglage **facultatif**, à poser à la
  main dans `config.local.php` (`'maintenance' => ['backup_allow_same_disk' =>
  true]`). **Jamais actif par défaut** : sans le disque, la sauvegarde continue
  de refuser de s'écrire sur la carte SD. Avec lui, elle accepte, mais
  l'administration affiche un **bandeau rouge permanent** et la page « État du
  site » rappelle que cette copie ne protège d'aucune panne de la carte.
- **`php app/cli/verifier-mise-en-ligne.php`** : nouveau statut
  **AVERTISSEMENT**. Le mode « sans disque externe » y apparaît comme un
  avertissement — **jamais bloquant** — et la commande dit toujours clairement
  **« PRÊT À OUVRIR »** ou la liste des points à corriger. Le code de sortie ne
  dépend que des points à corriger.
- **Conversion vidéo** : elle **refuse de démarrer sous la marge d'espace
  libre** prévue (`conversion.min_free_mb`, 1 Go par défaut), en comptant la
  taille du fichier d'origine. La commande `app/cli/convertir.php` revérifie la
  place **juste avant** de lancer ffmpeg : un disque rempli entre-temps arrête
  le travail avec un message clair, l'original reste en place. Les minuteurs de
  courriel ne changent pas.
- **README** : nouvelle section **« Premiers jours sans disque externe »** —
  tout sur la carte SD, surveillance de l'espace libre, copie manuelle par
  `scp` ou SFTP, transition à l'arrivée du disque.

#### Deux détails vus sur les captures

- **« État du site »** affichait deux fois la même alerte de sauvegarde (le
  bandeau de l'administration, puis le cadre de la page). Le bandeau est
  maintenant réservé aux autres pages ; « État du site » garde son cadre, avec
  le détail.
- **« Clé montée »** répondait « oui (/) » en mode sans disque externe, ce qui
  est faux et se contredit deux lignes plus haut. La page dit désormais :
  « non — sauvegarde sur la carte SD (mode sans disque externe) ».

#### Une barre horizontale en moins sur un téléphone

Les captures à 390 px ont révélé un débordement sur **Administration →
Courriels** (443 px de large) : un chemin du Pi
(`/var/www/histoiredefamille/config.local.php`) est un mot unique de quarante
caractères et sortait de l'écran. La règle `code` de la feuille de style porte
désormais `overflow-wrap: anywhere`, et deux tests la vérifient. Aucune autre
page capturée ne déborde : accueil public, documents, vidéos, témoignages,
accueil lecteur, accueil journaliste, Mon compte, Mes propositions, À valider,
tableau de bord, Réglages, Apparence, Branches, Recettes et formulaire d'article
tiennent à 390 comme à 1280 px. Les deux palettes ont été revérifiées
(« papier ancien » et « bleu d'origine ») : mêmes pages, mêmes mesures, aucun
débordement, et le texte des boutons pleins reste lisible dans les deux.

#### Vérifications

- **903 tests** (`php app/cli/tests.php`), **0 échec** : dont 8 nouveaux sur la
  marge d'espace de la conversion, 8 sur le mode « sans disque externe »
  (refus par défaut, avertissement, bandeau permanent, sauvegarde réelle,
  « État du site » qui ne répète pas le bandeau), 4 sur l'avertissement de la
  vérification avant ouverture et 2 sur les chemins longs qui ne débordent
  plus. La suite donne **le même résultat avec et sans**
  `backup_allow_same_disk` dans la configuration locale : les essais fixent
  eux-mêmes ce réglage au lieu de dépendre de la machine.
- Analyse syntaxique : **181 fichiers**, 0 erreur.
- Matrice d'accès régénérée : **184 routes**, **736 vérifications**,
  **240 contrôles**, **0 anomalie**.
- **Captures refaites** sur la base de démonstration : accueil public et accueil
  connecté (lecteur, journaliste), Mes propositions, À valider, formulaire
  d'article avec « Privé » coché, documents — en 1280 et 390 px. `docs/captures/README.md`
  dit désormais quelle image montre quelle page.

## [1.24.0] — 2026-11-04

### Privé par défaut, et deux portes d'entrée

Deux principes simples, appliqués partout : **ce qu'on crée n'est vu de
personne**, et **la page publique dit où aller en deux gestes**.

- **Privé par défaut, sans exception.** Tous les formulaires de création
  (articles, pages d'information, documents et photos, albums, recettes,
  vidéos, fiches de personnes, import, envoi par morceaux, dossier arrivée,
  propositions des journalistes) ouvrent sur **« Privé »** coché. La fiche
  d'une personne proposait « Toutes les familles » : c'est corrigé.
- **Rien ne devient public par accident.** Chaque contrôleur range la
  visibilité reçue avec `sanitize_visibility()` : une valeur absente, vide ou
  inventée devient **privée**, jamais publique. Un test parcourt **les dix
  formulaires et les neuf contrôleurs** et échoue si un défaut n'est pas
  « privé » ; un second vérifie en créant réellement des contenus sans
  visibilité (article, page, valeur inventée).
- **La page publique a deux portes** : **« Consulter »** et **« Se
  connecter »**, la seconde plus grosse et mise en avant, dans l'en-tête comme
  dans le corps de la page (police et contenu plus grands : elle se repère
  sans lire). Le menu du visiteur suit : « Accueil », « Consulter », la
  connexion en pastille, puis les rubriques qui ont vraiment du contenu
  public.
- **L'arbre généalogique n'est plus public.** Il disparaît de la page
  d'accueil, du menu du visiteur et du catalogue de composition ; ses adresses
  (`/arbre`, `/arbre/{id}`, `/api/arbre/{id}`) renvoient un visiteur vers la
  connexion. Une fois connecté, il est là, comme avant, dans le menu et dans
  les accès rapides.
- **La page publique reste belle sans contenu.** Elle ne compte plus les
  personnes « dans l'arbre » (elles ne sont plus publiques) et présente
  toujours ses deux portes et sa phrase d'accueil réglable.
- **L'accueil connecté est une vraie « une »** : encart du journaliste
  (« Proposer un article » + « Mes propositions » avec son compteur) ou
  compteur « À valider » pour un administrateur, puis **« Nouveautés de votre
  famille »** (derniers articles, documents et personnes ajoutés), la une des
  articles, les encarts habituels (petites annonces, accès rapides, chiffres).
  Tout est filtré par les droits du compte : un lecteur ne voit rien de plus
  que ce qu'il peut déjà ouvrir.
- **File « À valider » lisible partout** : le tableau tient dans la colonne à
  1280 px, et sur un téléphone il garde le titre (qui mène à l'aperçu) et
  l'auteur — plus aucun débordement latéral.

Tests : **881 au total (+19)**, dont une famille « défauts » (privé par
défaut, formulaires et enregistrements) et une famille « accueil » (deux
portes, arbre fermé au visiteur, nouveautés, encarts du journaliste et de
l'administrateur). Corrigé au passage : le test de la page « Mon compte »
(courriels) appelait un contrôleur qu'il ne chargeait pas — l'erreur était
avalée et le test se trompait. Matrice d'accès : **184 routes**,
**736 vérifications**,
**240 contrôles**, **0 anomalie**. Captures vérifiées à 1280 et 390 px. Les deux
palettes (bleue et « gazette ») ont été contrôlées : aucun débordement.

## [1.23.0] — 2026-11-04

### Les propositions passent par les administrateurs

Jusqu'ici, un journaliste pouvait ouvrir les formulaires d'administration et
publier directement. Ce n'est plus le cas : il **propose**, un administrateur
**valide**.

- **Un espace « Mes propositions »** (menu, et un encart sur l'accueil de la
  famille) : le journaliste y dépose un **article**, un **document ou une
  photo** (envoi simple, ou **par morceaux** pour les gros fichiers), une
  **recette**. Il y suit l'état de chacun et peut les corriger.
- **Quatre états** : `brouillon` (gardé pour soi), `attente`, `publie`,
  `refuse` — avec, en cas de refus, un **motif** que l'auteur lit. Toute
  proposition naît **« en attente »** et **privée** : le journaliste ne choisit
  pas la visibilité, il peut seulement la **suggérer** (un champ `visibility`
  glissé dans le formulaire est ignoré).
- **Rien ne fuit avant la validation.** Une condition SQL unique
  (`proposition_clause`) rétrécit toutes les listes, la recherche, l'accueil,
  le fil, la carte, la gazette et les courriels ; la **même règle** écrite en
  PHP garde les adresses directes (`/f/{id}`, `/media/miniature/{id}`,
  `/articles/{slug}`, `/recettes/{slug}`). Un lecteur, un autre journaliste ou
  un visiteur reçoit exactement la réponse d'un contenu inexistant.
- **Un seul écran « À valider »** dans l'administration (compteur dans le menu
  et sur le tableau de bord) : type, titre, auteur, date, visibilité souhaitée,
  puis un **aperçu complet** et deux décisions — **Valider** (choix de la
  visibilité, **privé par défaut**) ou **Refuser** (motif obligatoire, refusé
  s'il fait moins de trois caractères). Tout est noté au journal d'actions.
- **Les contenus existants ne bougent pas** : la **migration 016** est en ajout
  seul, et la valeur par défaut est `publie`. Les articles, documents et
  recettes déjà en ligne restent publiés, avec la même visibilité.
- **Les courriels attendent la validation** : les administrateurs peuvent être
  prévenus qu'une proposition arrive (si l'envoi est configuré, un seul
  message par proposition et par administrateur) ; les lecteurs ne sont
  prévenus qu'**au moment de la publication**, selon la visibilité retenue.
- **Modifier, c'est repasser en relecture** : tant qu'une proposition n'est pas
  validée, son auteur la corrige librement (elle reste « en attente », ou
  « refusée » avec son motif). S'il modifie un contenu **déjà publié**, celui-ci
  repasse « en attente » et redevient invisible pour les autres jusqu'à la
  prochaine validation — la trace de l'ancienne validation reste au journal
  d'actions.
- **Les événements du calendrier ne sont pas concernés** : il n'existe pas de
  contenu « événement » dans le site. Le calendrier est calculé à partir des
  dates des fiches de personnes, qui restent réservées aux administrateurs.
- **Technique** : le routeur accepte un motif `{type}` (mot en minuscules), et
  l'envoi par morceaux sert aussi les journalistes (`/propositions/fichier/*`)
  sans jamais créer de fiche : le fichier reste « prêt » jusqu'à
  l'enregistrement de la proposition.

Tests : **862 au total (+60)**, dont une famille « propositions » complète —
états, dépôt, file d'attente, validation (privé par défaut), refus sans motif
(refusé) puis avec motif, correction, article déjà publié verrouillé, brouillon,
et **onze vérifications de fuite** (article, document, recette × lecteur, autre
journaliste, visiteur ; pages, fichier, téléchargement, vignette, liste,
recherche, accueil, gazette, courriels). Matrice d'accès : **184 routes**,
**736 vérifications**, **240 contrôles**, **0 anomalie**.

## [1.22.0] — 2026-11-04

### Trois profils, et seulement trois

Le site avait deux sortes de comptes de famille aux droits identiques, ce qui
prêtait à confusion : « compte de branche » et « compte personnel ». Il a
maintenant **trois profils clairs**, et les mots employés partout sont les
mêmes que dans la vie courante.

- **Lecteur** (identifiant partagé d'une branche, en majuscules) : il **lit**
  ce que sa branche a le droit de voir. Il ne peut **rien** écrire ni proposer :
  aucun formulaire, aucun envoi de fichier. Il garde « Mon compte » (mot de
  passe, courriels) et le tchat, comme avant.
- **Journaliste** (compte personnel, `prenom.nom`) : il lit avec les droits de
  sa branche et il **propose** des contenus — qui ne seront publiés qu'après
  validation d'un administrateur.
- **Administrateur** : tout, et c'est lui qui valide.

Ce qui change concrètement :

- **Les libellés** : formulaires de création, listes, filtres, message
  d'accès copié dans un SMS (« Profil : Lecteur — lit ce que sa branche… »),
  pages « Mon compte », README. **Les identifiants techniques en base ne
  changent pas** (`branche`, `personne`, `admin`) : aucune migration, donc
  aucun risque pour les comptes existants.
- **Créer un lecteur** : la page **Comptes → Ajouter un compte** s'ouvre sur le
  profil **Lecteur**, proposé par défaut. Un lecteur est le compte partagé
  d'une branche : le formulaire crée la branche et son compte d'un seul geste,
  affiche le mot de passe **une seule fois**, et le refuse en double (un seul
  lecteur par branche).
- **Changer de profil** : la fiche d'un compte comporte un champ « Profil ».
  Passer un journaliste en lecteur (ou l'inverse) ne touche ni son identifiant,
  ni son mot de passe. Repasser en journaliste redemande un nom et un prénom.
  Un administrateur ne se transforme pas par cette page : il a son écran.
- **Un lecteur ne reçoit que des refus** sur toutes les routes d'écriture
  (documents, articles, recettes, albums, envois par morceaux, comptes), y
  compris en POST direct : 403, ou renvoi vers la connexion pour un visiteur.
  C'est vérifié route par route par la matrice d'accès.
- **Matrice d'accès** : elle teste désormais les **quatre profils du site**
  (visiteur, lecteur, journaliste, administrateur), sur **170 routes** —
  **680 vérifications, 236 contrôles ciblés, 0 anomalie**.

Tests : **802 au total (+42)**, dont une famille « profils » complète :
libellés, aides de décision, création d'un lecteur (hachage seul, doublon
refusé, identifiant en minuscules refusé), changement de profil dans les deux
sens, et refus systématique d'un lecteur sur les écritures.

## [1.21.1] — 2026-11-04

### Les vidéos de téléphone arrivent enfin jusqu'au bout

Le lot 1.21.0 savait convertir ; encore fallait-il que le site accepte le
fichier. C'est corrigé, avec les six retouches signalées après l'essai.

- **Les .mov, .avi, .mkv, .webm et .3gp sont acceptés.** Un iPhone filme en
  MOV, un caméscope en AVI, un vieux téléphone en 3GP : tous ces fichiers
  étaient refusés à l'envoi (« Extension non autorisée »), donc jamais
  convertis. Ils entrent maintenant dans le site et partent directement dans la
  file de conversion. Le **type réel** reste vérifié par `fileinfo` : renommer
  un fichier ne suffit pas à passer.
- **Un garde-fou pour les anciennes installations.** La liste des extensions
  autorisées est désormais l'**union** du réglage (`uploads.allowed_extensions`)
  et des types que le site sait reconnaître : un `config.local.php` recopié
  d'une version précédente ne peut plus bloquer les nouvelles extensions. Une
  liste écrite à la main reste donc valable sans retouche.
- **Les essais ne dépendent plus de la machine.** Les tests « sans ffmpeg »
  supposaient que ffmpeg était absent ; sur un serveur qui en possède un, ils
  auraient échoué ou, pire, lancé une vraie conversion. `conversion_outils_forcer()`
  impose maintenant les chemins des outils (ou leur absence) : les mêmes
  résultats partout, et un essai de plus vérifie que la conversion **redevient
  disponible** quand les deux exécutables sont en place.
- **Un .mov de bout en bout dans les tests** : envoi par morceaux avec un
  véritable en-tête QuickTime, fiche créée en privé avec son extension et son
  type, mise en file immédiate, puis **famille qui ne la voit pas** (même 404
  qu'un contenu interdit, et rien sur la page « Vidéos ») — et qui la voit dès
  que la conversion a réussi.
- **Plus de barre horizontale sur « Envoyer des fichiers ».** Le champ de
  fichier masqué (`.sr-only`) faisait la largeur de la fenêtre : la règle
  `input[type="file"] { width: 100% }`, plus précise, l'emportait sur la classe.
  La page mesurait 611 px dans une fenêtre de 390 : elle glissait latéralement
  au doigt. `.sr-only` vise maintenant aussi ce cas, et fixe `left`/`top` :
  vérifié au navigateur à 390, 768 et 1280 px (page rendue 390/390, 768/768,
  1280/1280 — plus aucun débordement).
- **Les textes ne mentent plus.** « Une vidéo envoyée reste telle quelle : le
  site ne la reconvertit pas » et « le Raspberry Pi ne convertit rien » datent
  d'avant le lot B. La page d'envoi, le tableau de bord, le formulaire vidéo,
  le formulaire document, la page d'import, la page « Vidéos » et
  `deploy/dossier-arrivee.md` disent maintenant la vérité : le MP4 est publié
  tel quel, tout autre format est converti en arrière-plan.
- **« Sauvegarde : Sauvegarde : »** ne s'affiche plus deux fois. Le message
  d'alerte ne porte plus le préfixe : le bandeau de l'administration et la page
  « État du site » le posent déjà en titre. Un test vérifie qu'il n'apparaît
  **qu'une fois** sur la page.

Tests : **760 au total (+14)** — 755 avant ce correctif, plus l'envoi `.mov`
de bout en bout, la disponibilité de la conversion imposée dans les deux sens,
et la mise en page du champ masqué. Lint : 170 fichiers, 0 erreur. Matrice
d'accès : 169 routes, 845 vérifications, 249 contrôles, 0 anomalie.

## [1.21.0] — 2026-11-04

### Le site convertit les vidéos tout seul

Un téléphone produit du MOV, un caméscope de l'AVI, un vieux dictaphone du WAV :
aucun de ces formats n'est lu par les navigateurs. Jusqu'ici, il fallait
convertir sur son ordinateur avant d'envoyer. Désormais, le site s'en charge,
**en arrière-plan**, une vidéo à la fois.

- **Une file d'attente, pas une requête.** L'envoi ne convertit rien : il
  inscrit le fichier dans la table `conversions` (**migration 015**, ajout
  seul). La commande `php app/cli/convertir.php`, lancée toutes les 10 minutes
  par un **minuteur systemd** (priorité basse, `www-data`, une seule à la
  fois), prend le travail le plus ancien et le fait tranquillement. Une visite
  du site n'attend donc jamais ffmpeg, et une conversion dure ce qu'elle dure.
- **Ce qui est converti** : les vidéos non lisibles (MOV, AVI, MKV, WebM,
  MP4 en HEVC) deviennent **MP4 H.264 + AAC, faststart, yuv420p**, hauteur
  bornée (1080p par défaut) ; les sons **WAV et AMR** deviennent **M4A (AAC)**,
  sans image. Un MP4 déjà en H.264 n'est **jamais** reconverti (double
  encodage = perte de qualité pour rien).
- **La famille ne voit rien d'inachevé** : une vidéo en attente ou en cours est
  retirée des listes, albums, recherches et fiches par une condition SQL qui
  **ne fait que rétrécir** la visibilité — aucun droit n'est ouvert, et les
  administrateurs continuent de tout voir. Une conversion annulée ou en échec
  ne bloque rien : c'est l'original qui est servi, comme avant.
- **Page Administration → Conversions** : état de chaque fichier, avancement,
  nombre d'essais, message d'erreur en français, **Relancer**, **Annuler**
  (ffmpeg est arrêté au palier suivant), et **« Les ajouter à la file »** pour
  les vidéos déjà présentes. Si ffmpeg manque, la page le dit et donne la
  commande à taper — rien ne casse, la file attend.
- **Trois essais, puis un message clair.** Fichier abîmé, disque plein, droits
  insuffisants, encodeur absent : chaque cas a sa phrase. L'original reste
  toujours en place après un échec.
- **Rien n'est servi à moitié** : le fichier converti est écrit sous un nom
  temporaire puis renommé, et la fiche n'est mise à jour qu'à la fin, **en une
  seule transaction**. La miniature et les dimensions ne sont jamais recalculées
  deux fois.
- **L'original n'est effacé que sur demande** : réglage « effacer l'original
  sept jours après une conversion réussie » (décoché par défaut), et jamais s'il
  est encore le fichier d'une autre fiche. La tâche d'entretien du dimanche
  s'en occupe, et purge aussi les lignes de plus de 90 jours.
- **Réglages** : conversion oui/non, trois qualités (légère 720p, équilibrée
  1080p, haute), et le choix de garder ou non les originaux. Tous les réglages
  sont bornés côté serveur : une valeur absurde retombe sur la valeur sûre.
- **Installation** : `deploy/install.sh` installe `ffmpeg`, pose le minuteur et
  prévient clairement s'il manque. `config.local.php` peut indiquer un autre
  chemin (`conversion.ffmpeg`, `conversion.ffprobe`).
- **Sauvegarde** : la file est dans la base, donc une vidéo en attente survit à
  une restauration ; les originaux déjà sur le disque sont sauvegardés comme le
  reste.

Tests : **746 au total (+83)**, dans quatre familles (file, prise et essais,
réussite, droits et entretien) : qui entre dans la file et qui n'y entre pas,
la qualité qui retombe sur la valeur sûre, la prise atomique (un même travail
n'est jamais pris deux fois), l'avancement borné, le premier échec qui remet en
attente puis l'échec définitif au troisième, la relance qui remet les compteurs
à zéro, l'annulation, la réussite (fichier remplacé, nom d'origine conservé
avec l'extension .mp4, durée enregistrée, visibilité intacte), le son converti
en M4A, le fichier vide refusé, la page fermée aux comptes de famille et aux
visiteurs, le jeton CSRF exigé, la clause de visibilité qui cache une vidéo en
préparation à un visiteur, l'échec qui ne bloque pas la lecture, la purge, la
libération des originaux après sept jours et le garde-fou sur une fiche qui
utilise encore le même fichier. La commande ffmpeg est éprouvée sans jamais
l'exécuter : arguments (tableau, jamais de shell), bornage de la hauteur,
lecture de `out_time_us`, messages d'erreur en français, durées lisibles.

## [1.20.0] — 2026-11-04

### Envoyer de gros fichiers depuis Paris

Un film de famille de 3 Go, un registre scanné, deux cents photos : jusqu'ici,
il fallait être à la maison pour les déposer dans le dossier « arrivée » du
Raspberry Pi. Désormais, **Administration → Envoyer des fichiers** accepte
**jusqu'à 4 Go par fichier**, depuis n'importe où, avec un simple navigateur.

- **Par morceaux, avec reprise.** Le navigateur découpe le fichier en tranches
  de 5 Mo (réglable de 1 à 20 Mo) et les envoie une par une : chaque requête
  reste petite, donc elle passe le tunnel Cloudflare, un Wi-Fi d'hôtel ou une
  ligne montante lente. Si la connexion tombe, si l'ordinateur se met en veille
  ou si l'onglet est fermé, **l'envoi reprend** là où il s'était arrêté : il
  suffit de redéposer le même fichier. Rien n'est jamais renvoyé deux fois.
- **Tout arrive privé.** Un fichier reçu n'est visible de personne : sa fiche
  est créée en **privé**, puis complétée une à une (« Ouvrir la fiche ») ou
  **tout un lot d'un coup** (type, catégorie, album, branche, visibilité
  communs, puis réglage fichier par fichier). Aucune règle de visibilité
  n'est modifiée : le fichier suit la règle ordinaire des documents.
- **Les doublons sont signalés.** Si le même fichier (empreinte SHA-256
  identique) est déjà sur le site, le site le dit **avant** d'ajouter quoi que
  ce soit, et propose « **Garder quand même** » ou « **Ignorer ce fichier** ».
  Rien n'est jamais supprimé automatiquement.
- **Les formulaires existants en profitent.** Le champ « Fichier » des fiches
  document et vidéo passe par le même mécanisme : les gros films ne dépendent
  plus de la limite de 50 Mo, et leur envoi reprend aussi après une coupure.
- **Deux pages de suivi.** *Mes envois* (les 30 derniers jours et tout ce qui
  n'est pas terminé, avec **Reprendre**) et une vue **Tous les envois**, en
  lecture seule : chacun ne peut reprendre ou annuler que les siens.
- **Rien dans le dossier web.** Les morceaux attendent dans `storage/envois`,
  **hors de la racine web**, sous un identifiant aléatoire de **128 bits**
  vérifié par une expression stricte (aucun chemin construit à partir d'une
  saisie). Les validations ordinaires (extension autorisée, type réel par
  `finfo`, taille, nom nettoyé) sont rejouées à la fin, sur le fichier
  assemblé. Session administrateur et jeton CSRF exigés à chaque étape.
- **Mémoire constante.** Tout est copié par blocs de 1 Mo : la mémoire du
  serveur ne dépend pas de la taille du fichier. Mesuré de bout en bout sur un
  fichier de **3 Go** coupé en deux : reprise, assemblage, **empreinte
  identique**, **8 Mo de mémoire au plus**.
- **Le disque est protégé.** Un envoi est refusé s'il ne resterait pas 2 Go
  libres après ; trois envois au maximum en même temps ; les tranches d'un
  envoi abandonné depuis plus de **48 h** sont effacées par la tâche
  d'entretien. Le dossier `envois` est exclu de la sauvegarde (un fichier à
  moitié reçu n'a rien à y faire).
- **Deux réglages** dans *Administration → Réglages* : taille d'un morceau
  (1 à 20 Mo) et taille maximale d'un fichier (50 Mo à 16 Go), avec des bornes
  qui tiennent compte des limites de Cloudflare, d'Apache et de PHP.
- **Configuration du Pi revue** : délais Apache (300 s, `RequestReadTimeout`)
  et PHP (300 s) allongés pour qu'un morceau de 20 Mo arrive sur une ligne
  montante lente, et pour que l'assemblage d'un fichier de plusieurs Go ait le
  temps de se terminer.
- **Sans JavaScript**, la page le dit clairement et renvoie vers l'envoi simple
  (50 Mo) ou le dossier arrivée : aucune fonction existante ne dépend du
  nouveau script.

Tests : **663 au total (+62)**, dans cinq familles (protocole, refus, doublons,
droits, ménage) : un envoi complet, une coupure simulée au milieu puis une
reprise qui ne redemande que les morceaux manquants, un morceau déposé deux
fois (sans effet), un morceau hors bornes ou d'une taille fausse, un jeton
inventé, un fichier trop gros, un disque plein simulé, un doublon accepté puis
ignoré, l'annulation, le nettoyage après 48 h, et les droits (un administrateur
ne peut ni reprendre ni annuler l'envoi d'un autre, un compte de la famille
n'atteint aucune de ces adresses). Les trois tests de bornes des réglages
Le bornage des réglages est vérifié : une valeur absurde (99 ou −9) reste
ramenée entre 1 et 20 Mo.

## [1.19.0] — 2026-11-04

### La carte des lieux

Les lieux de naissance, de décès et de mariage de la famille, sur une carte —
sans aucun service externe.

- **Aucune ressource extérieure** : le fond de carte est un fichier livré avec
  le site (Natural Earth, **domaine public**, contours projetés et simplifiés :
  37 Ko pour deux vues, France et monde entier). Le dessin est un **SVG
  fabriqué par le serveur** : ni JavaScript, ni tuile, ni bibliothèque, ni
  police. La carte fonctionne sans connexion sortante et ne transmet rien à un
  tiers.
- **UNE seule façon d'entrer sur la carte** : un texte de lieu (« Toulouse »,
  « le hameau de la Croix ») n'est jamais réécrit. La table `places`
  (**migration 012**, ajout seul : une table neuve, deux index) lui donne des
  coordonnées, une fois pour toutes.
- **Administration → Lieux à placer** : la liste des textes de lieu des fiches
  qui attendent une position, une recherche dans **8 942 communes** embarquées
  (deux lettres suffisent, accents et majuscules ignorés), et une saisie à la
  main de la latitude et de la longitude pour les hameaux et l'étranger.
  « Retirer » ne touche aucune fiche.
- **Page `/carte`** : points dont la taille suit le nombre de personnes, deux
  vues (France / monde), filtres **branche** et **décennie** en `GET` (adresse
  mise en favori), légende, et **tableau équivalent** sous la carte (lecteur
  d'écran, impression).
- **Fiche d'un lieu** (`/lieu/12`) : naissances, décès et mariages de ce lieu,
  avec le lien vers chaque personne ; et **« Voir sur la carte »** depuis la
  fiche d'une personne quand son lieu est placé.
- **Droits inchangés, sans exception** : seules les personnes visibles sont
  comptées (`visibility_clause('person', …)`). Un lieu sans personne visible
  n'apparaît pas, et sa fiche répond **exactement** comme un lieu inexistant
  (même 404, même texte).
- **Cache court** de 5 minutes, par groupe de droits, filtres et version des
  données ; le fichier ne contient que des noms de lieux publics et des
  nombres. Projection en PHP pur : moins de 300 ms avec 2 000 personnes.
- Couleurs uniquement par **variables CSS** : la carte suit la palette choisie
  (papier ancien, bleu d'origine, personnalisée). Aucun attribut `style`, aucun
  gestionnaire en ligne, aucune classe CSS existante renommée.
- GEDCOM inchangé : aucune donnée n'est réécrite, aucun champ ajouté aux
  fiches.

Tests : **601 au total (+58)**, dont la projection (bords, hors cadre,
coordonnées impossibles), les suggestions de communes, les filtres, les droits,
le cache, un contrôle du SVG (ni `style`, ni script, ni ressource externe) et
un garde-fou sur le tracé lui-même (aucun segment ne doit traverser la carte :
c'est ce qui arrivait quand un contour passait le méridien 180).

## [1.18.1] — 2026-11-04

### Trois corrections du quotidien

- **Fusion du correctif 1.16.1** (branche `claude/cache-assets`) : la feuille de
  style et le script portent un numéro de version dans leur adresse, donc une
  mise à jour s'affiche tout de suite. La correction s'applique aussi à la mise
  en page de l'administration, qui gardait l'ancien cache.
- **Plus de courriels en retard** : une personne ne reçoit que les articles
  publiés **après** le moment où elle a coché « Prévenez-moi des nouveaux
  articles » (nouvelle colonne `accounts.notify_articles_depuis`, migration
  013). Un nouvel inscrit ne reçoit plus les articles des 30 derniers jours ;
  décocher puis recocher repose la date. Les personnes déjà inscrites avant la
  mise à jour gardent leur abonnement : leur date est posée à l'instant de la
  migration.
- **Tableau « 50 derniers envois »** : sur un grand écran, les colonnes se
  partagent la place et l'objet comme l'erreur passent à la ligne. Plus de
  colonne coupée à droite, plus de défilement horizontal. Sur un téléphone, le
  tableau continue de défiler latéralement.

Tests : 544 au total (+19), dont 10 sur la date d'accord et 3 sur le tableau.

## [1.18.0] — 2026-11-03

### La gazette du mois

Un petit journal automatique, envoyé à celles et ceux qui l'ont demandé.

- **Préparation automatique le 1er du mois** (migration 011) : le site compose
  le résumé du mois écoulé — articles publiés, photos et albums, vidéos,
  témoignages sonores, recettes, nouvelles personnes de l'arbre, « Petites
  annonces » (anniversaires et souvenirs à venir) et chiffres. Statut « à
  valider » : rien ne part sans décision.
- **Un numéro par groupe de droits** : la famille, chaque branche, et les
  personnes qui voient tout. Le contenu est calculé en se mettant à la place
  d'un compte réel du groupe, avec les fonctions de visibilité du site : un
  article réservé à la branche A ne peut pas apparaître dans le numéro de la
  branche B. **Aucun élément invisible n'est repris.**
- **Un instantané, pas un calcul à la volée** : ce que l'on relit le 12 est
  exactement ce qui a été préparé le 1er ; retirer un élément ne modifie que
  le numéro concerné.
- **Administration → Gazette du mois** : liste des numéros et de leur état,
  aperçu fidèle avec **sélecteur de groupe**, bouton **« Retirer »** par
  élément (et « Remettre »), **mot de l'éditeur** (600 caractères), puis
  **« Envoyer »** ou **« Passer ce mois-ci »**.
- **Envoi automatique facultatif** (désactivé par défaut) : un numéro non
  validé part au-delà d'un **délai de grâce de 3 jours** après la fin du mois.
- **Jamais deux fois le même mois** : index unique (mois, groupe) et contrôle
  d'état ; un numéro vide ne produit aucun envoi (les petites annonces seules
  ne comptent pas).
- **Envoi par la file** : un message par personne, comme pour les articles ;
  aucune connexion SMTP pendant une visite du site. Le courriel contient le
  mot de l'éditeur, les titres d'encarts, les premiers éléments de chacun et un
  bouton **« Lire la gazette en ligne »**.
- **Page `/gazette/AAAA-MM`** réservée aux ayants droit : chacun lit le numéro
  de son groupe ; un numéro non envoyé ou passé répond 404, un visiteur est
  renvoyé vers la connexion. Aucune règle d'accès n'est élargie.
- **Titres des encarts réglables** dans « Apparence du journal » : six nouveaux
  titres « Gazette : … », vides = noms par défaut.
- **56 nouveaux tests** (525 au total) : mois et bornes, groupes de droits,
  contenus du mois, absence de fuite entre branches, préparation, doublon,
  mois vide, retrait et remise d'un élément, mot de l'éditeur, validation,
  refus, désinscription, délai de grâce, second passage, lecture en ligne et
  contrôle d'accès de l'administration.

Aucune migration existante modifiée, aucune dépendance ajoutée.

## [1.17.0] — 2026-11-03

### Courriels : prévenir la famille d'un nouvel article

**Un socle complet, sans bibliothèque et sans dépendance** : un petit client
SMTP écrit à la main (sockets), STARTTLS ou TLS implicite, authentification
LOGIN/PLAIN, délais courts. Les paramètres du serveur d'envoi ne vivent que
dans `config.local.php` (jamais en base, jamais dans les journaux, jamais
affichés). Sans configuration, tout le reste du site fonctionne et les
messages attendent dans la file.

- **Adresse facultative** sur les comptes (`accounts.email`, migration 010) :
  validation stricte, 190 caractères au plus, minuscules, une seule fois parmi
  les comptes actifs. Saisie dans « Ajouter des personnes » (formulaire
  individuel **et** colonne facultative « Nom;Prénom;adresse »), dans la fiche
  d'un compte et dans « Mon compte » — avec le **mot de passe actuel** exigé
  pour changer ou retirer son adresse.
- **Deux accords explicites**, décochés par défaut : « Prévenez-moi des
  nouveaux articles » et « Je veux recevoir la gazette du mois ». Une adresse
  saisie par un administrateur **ne vaut pas accord** (les cases repassent à
  zéro) ; l'administrateur ne les coche qu'« à la demande de la personne ».
- **Désinscription en un clic, sans connexion** : jeton long (64 caractères)
  propre à la personne et au type d'envoi, dont seule l'empreinte SHA-256 est
  stockée, sans date d'expiration. La page demande une confirmation en **POST**
  (un simple GET ne change rien) et les en-têtes `List-Unsubscribe` /
  `List-Unsubscribe-Post` sont envoyés (réponse « One-Click » prise en charge).
- **File d'envoi** (`email_queue`) : sujet, corps texte et HTML, destinataire,
  état, essais, date. Vidée par `app/cli/envoyer-courriels.php`, appelée par un
  minuteur systemd **toutes les 5 minutes** installé par `deploy/install.sh` :
  lots de 10, pause d'une seconde, nouvel essai plus tard (3 essais puis
  abandon), **plafond quotidien** réglable (50 par défaut), purge à 90 jours.
  **Jamais d'envoi pendant une requête web.**
- **Confidentialité** : un message par personne (jamais de copie multiple),
  adresses masquées (`m***@exemple.fr`) dans l'administration et dans le
  journal d'activité, aucun contenu réservé envoyé à qui n'a pas le droit de le
  voir — `can_view()` et l'envoi partagent désormais la même fonction
  (`visibilite_compte()`), donc la même règle, écrite une seule fois.
- **Notification à la publication** : titre, résumé, rubrique, date et lien
  (la connexion d'abord pour un article réservé), jamais le texte intégral ni
  la photo. Case « Prévenir les inscrits » dans le formulaire (cochée à la
  première publication, absente pour un brouillon), **un seul message par
  article et par personne**, article programmé prévenu **le jour de la
  publication**, article repassé en brouillon → envois en attente **annulés**.
- **Administration → Courriels** : état de la configuration (sans le mot de
  passe), bouton d'essai à soi-même, compteurs (en attente, envoyés du jour,
  échecs, inscrits), réglage du plafond et de l'adresse de réponse, 50 derniers
  envois avec l'erreur lisible.
- **En-tête et pied de chaque message** : nom du site, lien de désinscription,
  « gérer mes préférences », couleurs de la palette courante, HTML simple à
  styles en ligne, version texte lisible.
- **57 nouveaux tests** (461 au total) : adresses, unicité, jetons, file et
  doublons, conversation SMTP complète et ses échecs (mot de passe refusé,
  adresse inconnue, STARTTLS absent), mise en page, destinataires pour chaque
  visibilité, programmation, annulation, désinscription en un clic, plafond,
  purge, et absence d'adresse ou de mot de passe dans les journaux.

Aucune règle d'accès modifiée, aucune migration existante touchée, aucune
dépendance ajoutée.
## [1.16.1] — 2026-11-04

### Les mises à jour du site apparaissent tout de suite

- La feuille de style et le script portent maintenant un numéro de version
  dans leur adresse (`/assets/style.css?v=…`), qui change dès que le fichier
  change. Avant, après une mise à jour, le navigateur gardait l'ancien
  aspect jusqu'à 7 jours (il fallait une fenêtre de navigation privée).
- La police du titre au format `.woff` est aussi gardée en cache 30 jours.
- La page d'administration utilisait encore l'ancienne adresse : elle est
  corrigée de la même façon (le défaut était le même des deux côtés).
- Quatre tests vérifient ces adresses.

## [1.16.0] — 2026-11-03

### Vérification avant ouverture : « PRÊT À OUVRIR » ou la liste de ce qui bloque

Une seule commande, à lancer la veille et le jour de l'ouverture :

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/verifier-mise-en-ligne.php
```

Elle rend une ligne par contrôle, avec trois états seulement :

- **OK** : c'est bon ;
- **À CORRIGER** : il faut agir **avant** d'ouvrir le site ; la flèche `→`
  donne la commande ou le réglage à faire ;
- **INFO** : à savoir, sans bloquer.

Et un verdict global : **`PRÊT À OUVRIR`** ou **`PAS PRÊT : N point(s) à
corriger`** (code de sortie 0 ou 1, utilisable dans un script).

Ce qui est contrôlé :

- `https_only` à `true` et adresse publique en `https://` ;
- **aucune donnée de démonstration** (contenus et comptes marqués « (démo) ») ;
- **au moins deux administrateurs actifs**, et **aucun mot de passe d'essai
  connu** (seul le résultat est affiché, jamais le mot de passe) ;
- `config.local.php` en 600 ou 640 ; base et données **hors** de `public/` ;
- `public/` sans fichier sensible (configuration, base, `.git`, sauvegardes) ;
- version de PHP et extensions indispensables (SQLite, GD ou Imagick, mbstring,
  fileinfo, sodium ou Argon2id) ;
- sauvegarde : clé montée et inscriptible, dernière sauvegarde réussie de moins
  de 3 jours, minuteur systemd actif, **test de restauration daté** ;
- espace libre sur le SSD **et** sur la clé ;
- purge planifiée (maintenance hebdomadaire) ;
- **en-têtes HTTP réellement envoyés par Apache**, par une requête locale :
  CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, HSTS si `https_only`,
  et aucune version d'Apache ou de PHP annoncée ;
- cookies de session `Secure`, `HttpOnly`, `SameSite=Lax` ;
- `CF-Connecting-IP` ignoré hors du tunnel (essai à l'envers : l'en-tête est
  posé, la fonction doit le refuser depuis le réseau local).

- **Page « État du site »** : nouvel encadré « Vérification avant ouverture »,
  en **lecture seule** — elle affiche le dernier résultat enregistré (date,
  verdict, points à corriger et remèdes), sans lancer la moindre commande
  système et sans délai d'attente. Un résultat de plus d'une semaine est
  signalé.
- **README** : nouvelle section **13. Ouvrir le site au public : liste de
  contrôle du jour J**, dans l'ordre exact — monter SSD et clé, déplacer les
  données, trois administrateurs, comptes de la famille, `https_only`, tunnel
  Cloudflare, vérification, première sauvegarde, test de restauration,
  ouverture — avec le temps à prévoir, le matériel, les commandes et un tableau
  « si quelque chose ne va pas ».
- **30 nouveaux tests** (404 au total) : contrôles présents et bien formés,
  chaque point à corriger accompagné d'un remède, clé absente et clé présente,
  sauvegarde du jour et sauvegarde vieille de cinq jours, en-têtes complets et
  en-têtes manquants (sur une réponse HTTP réelle simulée), cookies faibles,
  signature du serveur, Apache muet, fichiers sensibles dans `public/`,
  enregistrement du résultat (aucun mot de passe dedans, droits 640), verdict,
  et affichage du résultat dans la page « État du site ».

Aucune règle d'accès modifiée, aucune migration, aucune nouvelle dépendance.

## [1.15.0] — 2026-11-03

### La matrice d’accès : 136 routes × 5 profils, vérifiées

Un test lit **toutes** les routes dans `app/routes.php` (elles ne sont jamais
recopiées) et appelle chaque page avec cinq profils : visiteur, compte de la
branche A, personne de la branche B, compte « voit toutes les branches »,
administrateur. Soit **680 vérifications**, plus **213 contrôles ciblés**.

- **Codes vérifiés** : 200 (page), 303 (renvoi vers la connexion), 403 (refusé),
  404 (introuvable — y compris pour un contenu non autorisé, sans révéler son
  existence), 405 (méthode refusée).
- **Contenus fictifs créés puis supprimés** (transaction annulée) : documents
  public / famille / branche A / branche B / privé (photo, vidéo, audio, PDF),
  album, article, page, personne, couple, recette, témoignage, message de tchat,
  avec miniature et fichiers réels sur le disque.
- **Contrôles ciblés** : identifiants de fichiers (`/f/{id}`, téléchargement,
  miniature), flux vidéo et audio avec `Range` (206, plage invalide 416),
  export GEDCOM, recherche (un visiteur ne fait jamais sortir un contenu
  privé), calendrier, album (une photo privée reste invisible), tchat (visiteur
  refusé), « Mon compte », **toutes** les actions d'administration en POST
  (refus sans jeton CSRF et pour un compte non administrateur), `/theme.css` et
  `/maintenance` sans fuite.
- **Rapport** : `docs/matrice-acces.md`, régénérable par
  `php app/cli/tests.php matrice`, avec profils en colonnes, routes en lignes,
  points examinés et anomalies. **Aucune anomalie** ni défaut d'accès constaté :
  les règles de visibilité n'ont pas été modifiées.
- **Une route nouvelle sans règle connue fait échouer le test** : impossible
  d'ajouter une adresse sans décider qui peut l'ouvrir.
- Les tests appellent désormais les pages dans le même processus (aucun serveur
  web nécessaire) : `hdf_terminer()` remplace `exit` pour les redirections et
  les refus, sans changer le comportement du site.

## [1.14.0] — 2026-11-03

### Restaurer sans risque, et savoir quoi faire en cas de panne

Une seule commande, trois usages, et jamais de remplacement sans accord
explicite :

```bash
# 1. contrôler la dernière sauvegarde (ne modifie rien)
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --verifier

# 2. restaurer dans un dossier séparé (essai)
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille

# 3. remplacer le site (le site doit être arrêté)
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --remplacer
```

- **Par défaut, rien n'est remplacé** : la sauvegarde est reconstituée dans un
  dossier séparé (`histoire.sqlite`, `storage/`, `LISEZ-MOI.txt`), pour regarder
  avant d'agir.
- **`--remplacer` exige de taper « OUI »** en majuscules. « oui », « Oui », une
  ligne vide : la commande annule et le site n'est pas modifié.
- **Avant de remplacer**, la sauvegarde est contrôlée (`PRAGMA
  integrity_check`), l'**ancienne base est copiée** dans
  `avant-restauration/AAAA-MM-JJ_HHMMSS/`, et les fichiers absents de la
  sauvegarde sont **déplacés** dans `fichiers-retires/` — jamais effacés.
- **Comparaison vérifiée** : le nombre de lignes de chaque table et la somme de
  contrôle des fichiers restaurés sont identiques à la sauvegarde.
- **Contrôle en lecture seule** (`--verifier`) : date notée dans
  `derniere-restauration.json` pour la page « État du site ».
- **README « Que faire si… »** : carte SD morte, SSD mort, document supprimé par
  erreur, remonter à hier, changer de Raspberry Pi — avec ce qu'il faut avoir
  sous la main, les commandes et le temps approximatif.
- **Test de bout en bout** dans `tests.php` sur base et dossiers **temporaires** :
  données fictives, sauvegarde, modifications, essai, comparaison, remplacement,
  archive abîmée refusée, confirmation exigée.

## [1.13.0] — 2026-11-03

### Des sauvegardes fiables, tous les soirs à 3 h 30

La sauvegarde devient une **commande unique**, testée, qui refuse de faire
semblant :

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php
```

- **Copie cohérente de la base** avec l'API de SQLite (`VACUUM INTO`), puis
  contrôle d'intégrité (`PRAGMA integrity_check`) : la copie est bonne même
  pendant que le site tourne. `cp` n'est jamais utilisé (fichier abîmé).
- **Fichiers copiés de façon incrémentale** (photos, miniatures, vidéos, audio,
  PDF, dossier `arrivée`) : seuls les nouveaux ou modifiés sont réécrits.
  `tmp/` et `verrous/` sont ignorés, la base n'est pas copiée deux fois.
- **Rien n'est écrit si la clé USB n'est pas montée** : la commande s'arrête,
  l'explique en français simple et laisse la carte SD intacte.
- **Rotation** : 7 sauvegardes quotidiennes, 4 hebdomadaires, 6 mensuelles.
  Une sauvegarde en cours est préparée dans un dossier temporaire puis déplacée
  d'un bloc : l'ancienne reste intacte jusqu'au bout.
- **Minuteur systemd** installé par `deploy/install.sh`, tous les jours à
  **3 h 30**, rattrapé si le Raspberry Pi était éteint (`Persistent=true`).
  L'ancienne tâche cron de sauvegarde est retirée ; la maintenance du dimanche
  reste. Compte rendu dans `journalctl -u histoiredefamille-sauvegarde.service`.
- **Page « État du site » enrichie** : date et résultat de la dernière
  sauvegarde, taille de la base, fichiers copiés/retirés/en erreur, durée,
  espace libre sur la clé **et** sur le disque des données, nombre de copies
  conservées. Un **bandeau rouge** apparaît en haut de toute l'administration
  si la dernière sauvegarde réussie date de plus de 3 jours, si elle a échoué
  ou si la clé est absente.
- **`config.local.php` n'est jamais copié** avec les données : le README
  explique où en garder une copie.
- **Chiffrement de la clé (facultatif)** : nouvelle page
  [`docs/chiffrement-cle-usb.md`](docs/chiffrement-cle-usb.md) — avantages,
  risques (mot de passe oublié = sauvegardes perdues) et commandes.
- **42 nouveaux tests** (338 au total) : copie pendant une écriture, intégrité,
  incrémental, suppressions, rotation, clé absente (rien n'est écrit), espace
  insuffisant, base introuvable, état et alertes, installation du minuteur.

## [1.12.1] — 2026-11-03

### Correctifs

- Un administrateur créé (commande `create-admin` ou page « Administrateurs »)
  est bien classé « administrateur » : le compteur et le filtre par type de
  la page « Comptes » étaient faussés (il était compté comme compte de branche).
- La police gothique n'est plus demandée au format `.woff2` absent : plus de
  page introuvable (404) à chaque affichage.
- Reprise des finitions d'affichage de la 1.10.1 (messages à copier lisibles,
  aperçu des comptes non coupé).

## [1.12.0] — 2026-11-03

### La composition des pages et les modèles d'article

Une nouvelle page **Administration → Composition des pages**, juste sous
« Apparence du journal » : les administrateurs décident de ce qui s'affiche
sur l'accueil public (`/`) et dans l'espace famille (`/famille`), et dans quel
ordre. **Tant que rien n'est modifié, les deux pages s'affichent exactement
comme avant** (vérifié page par page avec la version précédente).

- **Afficher, masquer, monter, descendre** : chaque bloc de chaque page a trois
  boutons (« ↑ Monter », « ↓ Descendre », « Masquer »/« Afficher »). Ce sont de
  vrais formulaires POST avec un jeton CSRF : **aucun JavaScript n'est
  nécessaire**, et le site fonctionne même sans script.
- **Chaque bloc reste dans sa colonne** : les flèches ne font que le glisser
  parmi ses voisins. Douze blocs pour l'espace famille (article à la une,
  articles en colonnes, petites annonces, éditions, courrier, accès rapides,
  chiffres, vidéos, albums, pages d'information, documents, personnes), huit
  pour l'accueil public.
- **Les trois entrées de l'accueil** (Parcourir les documents, Voir l'arbre,
  Espace famille) ne peuvent pas être masquées : ce sont les portes du site.
- **Un bloc masqué n'est pas calculé** : le contrôleur ne lance ni la requête
  des vidéos, ni celle des annonces, ni celle des albums. Sur un Raspberry Pi
  3B+, c'est du temps gagné à chaque affichage.
- **Réglages de la une** : nombre d'articles en colonnes (3 à 12), longueur du
  résumé (court, moyen, long) et photo de l'article à la une (oui ou non).
  Toute valeur hors bornes est refusée avec un message clair.
- **Modèles d'article** : « Journal » (la mise en page d'aujourd'hui),
  « Simple » (une colonne, sans ornement), « Grande photo » (l'image en pleine
  largeur avant le texte). Chaque article choisit le sien dans son formulaire
  (« Comme le site » par défaut) ; le site a aussi son modèle par défaut.
  Une valeur inconnue en base retombe sur le modèle du site, jamais sur une
  page cassée.
- **« Rétablir la composition d'origine »** (avec confirmation) réaffiche tous
  les blocs dans leur ordre d'origine et remet les réglages de la une comme au
  premier jour.
- **Stockage** : table `settings`, en JSON strictement validé. Un JSON abîmé,
  une clé inconnue, un doublon ou un bloc disparu ne cassent rien : les blocs
  manquants reviennent à la fin, affichés.
- **Migration 009** : une seule colonne ajoutée, `articles.modele`, vide par
  défaut (« comme avant »). Aucune migration existante n'est modifiée, aucune
  donnée n'est déplacée. Les articles déjà publiés ne changent pas d'allure.
- **Aucune règle d'accès modifiée** : l'espace famille demande toujours une
  connexion, et les blocs affichés continuent de filtrer avec les mêmes règles
  de visibilité que le reste du site.
- **52 vérifications de plus** (296 au total).
- **À faire sur le Pi** : `php app/cli/migrate.php` (migration 009).

## [1.11.0] — 2026-11-03

### L'apparence du journal (textes et couleurs)

Une nouvelle page **Administration → Apparence du journal**, juste sous
« Réglages » : les administrateurs changent eux-mêmes les petites lignes de
« la une » et les couleurs, sans toucher au code. **Tant que rien n'est
modifié, le site s'affiche exactement comme avant.**

- **Les textes** : milieu du bandeau (« Édition des familles réunies »),
  droite du bandeau (« Prix : un sourire »), titre, devise, « Fondée en »
  (quatre chiffres ou vide), pied de page, phrase d'accueil, et les titres des
  sept encarts (« Petites annonces », « Courrier des lecteurs », « Les
  éditions », « Accès rapides », « En chiffres », « À la une », « À lire
  aussi »). La date reste automatique. Un champ vidé reprend le texte actuel.
- **Le numéro d'édition** : automatique (numéro de la semaine), texte libre
  (« N° 12 », « Hiver 2026 »), ou aucun.
- **Quatre cases** pour afficher ou masquer le bandeau, la devise, le
  « Fondée en » et le numéro.
- **Titre gothique** : une case. Décochée, le titre s'écrit en Old Standard TT
  gras, en petites capitales. Si le fichier de la police gothique manque sur le
  serveur, la page le signale et le repli s'applique tout seul.
- **Les couleurs**, trois modes : « Papier ancien » (le site d'aujourd'hui),
  « Bleu d'origine » (la palette claire d'avant 1.9, avec un fond uni), et
  « Personnalisée » : quatre couleurs seulement (papier, texte, accent, encre),
  le site calcule les panneaux, les gris, les ombres et les messages.
- **Contrastes vérifiés** avant d'enregistrer : 7:1 pour le texte sur le
  papier, 4,5:1 pour les textes doux, l'accent sur le papier et le texte des
  boutons sur l'accent. Une combinaison illisible est **refusée** avec un
  message clair (« Le texte choisi (#cccccc) est trop proche du papier
  (#f3e7ce) : contraste 1,3 pour 1 (il faut 7 pour 1) »), un tableau recalculé
  pendant la saisie, et le bouton **« Corriger pour moi »** qui rapproche juste
  ce qu'il faut. Le texte et l'accent choisis ne sont **jamais** modifiés en
  silence : c'est le refus qui explique, la correction qui propose.
- **« Rétablir les valeurs d'origine »** (avec confirmation) remet tous les
  réglages de la page comme au premier jour : textes, cases, gothique, palette.
- **Aperçu en direct** du bandeau en haut de la page : il suit la saisie, la
  mise en page et les couleurs. Le script écrit du texte (`textContent`) et
  change les couleurs par le CSSOM (`setProperty`) : jamais de HTML inséré,
  aucun `style=` produit, donc la CSP stricte reste intacte. Sans JavaScript,
  le formulaire fonctionne normalement (seul l'aperçu ne s'anime pas).
- **Sous le capot** : une seule feuille `/theme.css` écrite par PHP à partir de
  codes `#RRGGBB` validés, avec un numéro de version dans les réglages servi
  comme ETag (le navigateur garde le fichier cinq minutes, puis demande
  seulement « a-t-il changé ? »). Elle est liée **après** `style.css` et
  **seulement** si une autre palette que « Papier ancien » est active : une
  requête de moins sur le Pi quand rien n'a été personnalisé.
- Les couleurs de `style.css` passent toutes par des variables `:root` (aucune
  couleur en dur hors du bloc d'impression) ; les classes existantes n'ont pas
  changé de nom, et le bloc `:root` d'origine est conservé.
- **Sécurité** : textes en clair échappés à l'affichage, longueurs bornées
  (40 à 200 caractères selon le champ), retours à la ligne et caractères de
  contrôle retirés, année entre 1500 et l'année en cours, palette refusée si
  les contrastes ne passent pas. `/theme.css` ne contient que des valeurs
  recalculées : l'essai d'injection `red;} body{display:none` ne produit rien.
  L'enregistrement est journalisé (« apparence modifiée ») sans recopier les
  textes.
- **57 vérifications de plus** (244 au total).
- `site_setting()` relit les réglages après un enregistrement ou un effacement
  (`site_settings_oublier()`) : « Rétablir » se voit tout de suite, sans
  attendre le rechargement suivant.
- Aucune migration, aucune règle d'accès modifiée.
- **À faire sur le Pi** : rien de particulier (pas de migration). Vider le
  cache du navigateur si le bandeau semble figé : le numéro de version force
  déjà le rechargement.
## [1.10.1] — 2026-11-03

### Finitions des écrans « Comptes »

- Les messages à copier (écran « Comptes créés ») sont lisibles : encre pleine.
- Le tableau d'aperçu avant création n'est plus coupé à droite : la colonne
  « Remarque » passe à la ligne.
- Les champs Nom et Prénom sont alignés.

## [1.10.0] — 2026-11-03

### Les comptes de la famille

Chaque personne peut maintenant avoir **son propre compte**, à côté des
comptes de branche partagés. Mêmes droits, mais un mot de passe par personne
et une signature à son nom sur le tchat.

- **Deux sortes de comptes** : « personne » (`elise.martin`) et « branche »
  (`MARTIN`, inchangé). Les comptes existants deviennent « branche », les
  administrateurs « administrateur » : rien à refaire, la migration le fait.
- **Créer une personne** : nom, prénom, branche, case « voit toutes les
  branches ». L'identifiant est proposé automatiquement — `prenom.nom`, en
  minuscules, sans accents ni espaces (`Jean-Paul Le Gall` → `jean-paul.legall`),
  avec `2`, `3`… en cas de doublon — et reste modifiable avant la création.
- **Créer toute une liste** : une personne par ligne, au format
  `Nom;Prénom`, `Prénom Nom` ou `Nom Prénom`. Une branche commune, puis un
  **aperçu obligatoire** : identifiants modifiables, lignes fautives signalées,
  personnes déjà présentes décochées. 100 lignes au maximum.
- **Mot de passe provisoire** généré pour chacun (aucun changement imposé à la
  première connexion), affiché **une seule fois** sur un écran de résultat, avec
  « Copier le message » et « Tout copier » : un message prêt pour un SMS ou
  WhatsApp, adresse comprise. L'avertissement « Les mots de passe ne seront
  plus affichés après cet écran : copiez-les maintenant. » est en haut de page.
- **Administration → Comptes** : liste (nom, prénom, identifiant, branche, type,
  actif, dernière connexion), filtre par branche, recherche par nom. Actions :
  modifier, désactiver/réactiver, réinitialiser le mot de passe, supprimer
  (les messages du tchat restent lisibles avec leur signature).
- **Page « Mon compte »**, à côté de « Déconnexion », pour tous les comptes
  (personne, branche, administrateur) : ancien mot de passe, nouveau mot de
  passe de 10 caractères minimum et confirmation. Refus si le nouveau mot de
  passe est l'identifiant, ou s'il est trop court. L'identifiant de session est
  régénéré, un message confirme l'enregistrement.
- **Tchat** : un compte personnel signe automatiquement `MARTIN/Élise` ; la
  question du prénom ne reste que pour les comptes de branche.
- **Accueil** : « Bonjour Élise » pour un compte personnel.

### Sécurité et corrections

- Le mot de passe provisoire n'est **jamais** conservé : seule l'empreinte
  Argon2id est enregistrée. Il n'apparaît ni dans l'adresse web, ni dans un
  cookie, ni dans le journal d'activité (qui note seulement « mot de passe
  réinitialisé pour … »), ni après rechargement de la page.
- Tout est en **POST + jeton CSRF**, réservé aux administrateurs ; les entrées
  sont validées (longueurs, caractères, branche existante, identifiant libre,
  identifiant interdit).
- Un compte désactivé ne peut plus se connecter **et** sa session en cours est
  fermée à la page suivante.
- Corrections trouvées en chemin :
  - un doublon « même nom, même prénom, même branche » n'était pas détecté
    (comparaison texte/nombre dans SQLite) ;
  - `db_transaction()` imbriquée validait la transaction extérieure : les
    appels internes rejoignent maintenant la transaction en cours ;
  - vérification du jeton CSRF centralisée dans `csrf_valide()` (comparaison à
    temps constant) ;
  - deux fonctions du contrôleur des comptes ont été renommées pour que le
    contrôle « un administrateur est exigé dès la première ligne » passe.
- `app/cli/demo.php` crée aussi trois comptes « personne » fictifs (Élise
  Martin, Jean-Paul Le Gall, Paul Durand) pour voir les écrans sans toucher à
  la vraie base.
- **40 essais de plus** (184 au total), dont : identifiants accentués, tirets,
  apostrophes, doublons, très longs ; les trois formats de liste, les lignes
  vides, ambiguës, la 101ᵉ ligne ; le hachage seul en base ; les droits d'un
  compte personnel et « voit tout » ; la désactivation (connexion + session) ;
  la réinitialisation ; le changement de mot de passe ; la signature du tchat.
- Aucune règle d'accès modifiée, aucune classe CSS existante renommée.
- **À faire sur le Pi** : `php app/cli/migrate.php` (migration 008).

## [1.9.3] — 2026-11-02

### Finitions du design Gazette

- Police gothique du titre (UnifrakturMaguntia, licence libre OFL) incluse
  dans `public/assets/fonts/` : le titre est maintenant en lettres gothiques.
- Article « à la une » plus compact, comme dans la maquette : titre sur toute
  la largeur, puis la photo (à gauche, 20 rem au plus) et le texte (à droite).
- Correction : un article public illustré par une photo réservée aux familles
  n'affiche plus d'image cassée aux visiteurs (plaque de remplacement).
  Aucune règle d'accès modifiée.
- 3 essais de plus (144 au total).

## [1.9.2] — 2026-11-02

### La maquette « gazette » appliquée à tout le site

- **Le papier** : fond crème `#f3e7ce`, deux halos bruns fixes (haut gauche,
  bas droite) et une ombre intérieure brune sur toute la page. Les plaques
  d'image de repli reçoivent la même ombre, en plus petit.
- **L'en-tête devient une manchette de journal** : titre en gothique
  `clamp(2.8rem, 9vw, 6rem)`, avec repli Old Standard TT 700 en petites
  capitales quand le fichier `UnifrakturMaguntia.woff2` n'est pas déposé (cas
  actuel). Ligne du haut « date · Édition des familles réunies · Prix : un
  sourire », puis « Fondée en 1889 », la devise en italique entre guillemets et
  le numéro d'édition, entre un filet fin et un filet double.
- **Deux réglages facultatifs de plus** (Administration → Réglages) : `devise`
  (120 caractères au plus, à défaut le sous-titre du site) et `fondee` (quatre
  chiffres). Ils s'affichent dans l'en-tête et n'altèrent rien s'ils sont
  vides.
- **Mise en page journal** : une à la une (titre `clamp(2.75rem, 6vw, 4.5rem)`,
  « Par … » en italique, plaque 4/3, filet double), les suivants en colonnes
  CSS (2 dès 40 rem, 3 dès 80 rem) avec filet vertical et signature « — Auteur »
  à droite, et une colonne latérale de 280 px avec filet.
- **Typographie** : titre de rubrique en majuscules espacées (`0.25em`)
  centré entre deux règles doubles, surtitres rouges en `0.3em`, corps justifié
  avec césure, interligne 1.6 et **lettrine** de 3.4em sur l'article à la une
  et sur chaque article ouvert.
- **Encarts** : « Petites annonces » en tirets, « Courrier des lecteurs » en
  italiques signées, « Les éditions », « Accès rapides », « En chiffres ».
- **Filtres d'édition** : `/articles` gagne une rangée « Édition » (branche
  familiale) et une frise par décennie, en simples liens `?branche=` et
  `?decennie=`. Aucun JavaScript, la visibilité s'applique comme partout :
  un visiteur ne voit jamais un article réservé, et les branches qui lui sont
  interdites n'apparaissent même pas. Message dédié quand rien ne sort :
  « Aucune nouvelle pour cette édition. La rédaction attend vos plis ! ».
- L'année d'un article est celle **racontée** dans son titre ou son résumé
  (`Mariage de 1958` → 1950), sinon celle de la publication.
- **Pages d'article** : surtitre, titre, signature, chapô, image, deux
  intertitres, marque de fin — et le même papier partout (documents, arbre,
  calendrier, vidéos, albums, tchat, recherche, connexion, administration).
  Formulaires en « panneau » de papier, boutons rectangulaires (plein brique
  ou contour encre), pied de page en filet double italique centré.
- **Téléphone (390 px)** : une seule colonne, aucune colonne CSS, aucun
  débordement horizontal, menu qui passe à la ligne, prix retiré de la ligne
  du haut, fond de page en défilement normal.
- **Accessibilité** : contrastes vérifiés (texte 14,8:1 ; texte atténué 7:1 ;
  brique sur papier 6,5:1 ; crème sur brique 6,5:1 ; alerte 5,4:1 — tous au-delà
  de 4,5:1). Aucun `style=` ni gestionnaire en ligne : la CSP ne bouge pas.
- **Jeu d'essai** : huit articles de démonstration (avec et sans photo, trois
  branches, visibilités public / famille / branches), ajoutés par
  `app/cli/demo.php` — **uniquement sur une base de démonstration**.
- 15 vérifications automatiques de plus (`editions`, 141 au total) : millésime,
  décennie, frise, filtres branche et décennie, et un visiteur qui ne voit
  jamais un article non public. Elles tournent dans une transaction annulée.

## [1.9.1] — 2026-11-02

### Accueil public simplifié, et design Gazette mené jusqu'au bout

- **Le site public tient en trois entrées** : Parcourir les documents, Voir
  l'arbre généalogique, Espace famille. Les autres rubriques (Articles,
  Vidéos, Albums, Calendrier, Recherche) n'apparaissent dans le menu et sur
  l'accueil que si quelque chose de **public** y a été publié : un visiteur ne
  tombe plus sur une page vide.
- Nouvelle fonction `rubriques_publiques()` (`app/lib/rubriques.php`) : une
  requête légère par rubrique, résultat gardé en mémoire pour la requête, et
  une seule règle pour le menu (`menu_rubriques()`) et pour l'accueil.
  - articles : un article publié, public, dont la date n'est pas future
    (un article programmé ne compte pas) ;
  - vidéos, albums : au moins un élément public ;
  - calendrier : seulement si des personnes publiques ont des dates ;
  - tchat : jamais en public.
- **Compte de famille et administration** : menu complet, comme avant (rien ne
  change pour eux ; l'ordre des onglets est identique).
- Une adresse de rubrique appelée directement par un visiteur continue de
  filtrer par visibilité : page vide ou 404, jamais de contenu privé, jamais
  d'erreur 500.
- **Espace famille** enrichi : dernières vidéos, albums photos, et un encart
  « Accès rapides » vers toutes les rubriques, dans la mise en page « une de
  journal ».
- **Design Gazette mené jusqu'au bout** : plus rien de bleu ni de blanc froid
  (champs de formulaire, encadrés, badges, tableaux, calendrier, vignettes),
  en-tête compact dans l'administration, et tableaux qui défilent sur
  téléphone (390 px). Le bloc `:root` de palette d'origine est conservé : le
  supprimer ramène au bleu.
- 16 vérifications automatiques de plus (`rubriques`, 126 au total). Elles
  tournent dans une transaction annulée : la base reste intacte.

## [1.9.0] — 2026-11-02

### Design « Gazette » (papier ancien, façon journal)

- Nouvelle palette (papier crème, encre brune, rouge brique) : bloc `:root`
  en fin de `public/assets/style.css`, à supprimer pour revenir au bleu.
- En-tête de journal : date · édition · prix, titre, devise et « Fondée en »
  (réglages facultatifs `devise` et `fondee`).
- Titre en lettres gothiques si le fichier
  `public/assets/fonts/UnifrakturMaguntia.woff2` est présent (police libre
  OFL, à ajouter à la main) ; sinon Old Standard TT.
- Espace famille en « une » de journal : article à la une, colonnes, encarts
  « Petites annonces » (anniversaires des 30 jours à venir) et « Courrier des
  lecteurs » (derniers messages du tchat).
- Aucune règle d'accès modifiée.

## [1.8.0] — 2026-11-01

### Journal des connexions et alertes pour les administrateurs

- Administration → **Journal des connexions** : chaque tentative (réussie,
  échec avec sa raison, blocage) est enregistrée avec la date, l'identifiant
  saisi et l'adresse IP de confiance ; filtres par type, période,
  identifiant ou IP ; pagination.
- Alertes discrètes sur le tableau de bord : plus de 10 échecs en une heure,
  et connexion d'un administrateur depuis une adresse jamais vue (la toute
  première connexion ne déclenche rien). Bouton « J'ai pris connaissance ».
- Durée de conservation réglable (30 jours, 90 jours par défaut, 6 mois, 1 an),
  appliquée par la tâche d'entretien du dimanche.
- Un robot qui insiste sur un compte déjà bloqué n'écrit qu'une ligne toutes
  les cinq minutes : le journal ne grossit pas sans fin, et la limitation des
  essais continue de s'appliquer à chaque tentative.
- 20 vérifications automatiques de plus (famille `journal`, 110 au total).
- Migration `007_journal_connexions.sql` (nouvelle table, aucune donnée touchée).
  La limitation des essais de connexion (`login_attempts`) est inchangée.

## [1.7.0] — 2026-11-01

### Témoignages sonores, recettes, impression et export GEDCOM

- **Témoignages** (`/temoignages`) : les documents audio (MP3, M4A, OGG) sont
  lus directement dans le navigateur (`<audio>`, avance rapide comprise grâce
  aux requêtes `Range` déjà en place pour les fichiers). Le nom des personnes
  qui parlent vient des personnes identifiées sur le document (lot 3). L'onglet
  du menu n'apparaît que s'il existe au moins un enregistrement visible.
- **Recettes de famille** (`/recettes`, `/recettes/{adresse}`) : titre, origine,
  quantités, ingrédients (un par ligne), préparation en mise en forme légère,
  astuce et souvenir, photo facultative, visibilité habituelle.
  Administration complète (liste, création, modification, suppression).
  Migration `006_recettes.sql`, aucune donnée existante touchée.
- **Impression** : feuille de style `@media print` (plus de menu, de boutons,
  de formulaires ni de fond coloré), en-tête « nom du site — date » visible
  seulement sur papier, et bouton « Imprimer » sur les fiches de personnes, les
  albums, les recettes et la frise. Le clic est traité dans `app.js` (aucun
  script en ligne, CSP « self » inchangée).
- **Export GEDCOM** (`/admin/export/gedcom`, réservé aux administrateurs) :
  fichier 5.5.1 (`HEAD`…`TRLR`) écrit personne par personne, donc sans charger
  l'arbre entier en mémoire. Dates converties (« 14 mars 1901 » → « 14 MAR
  1901 », « vers 1930 » → « ABT 1930 »), nom de famille en majuscules, « @ »
  doublé, retours à la ligne neutralisés.
- Démonstration : un témoignage fictif de quatre secondes (WAV généré) et deux
  recettes.
- **Tests** : familles « gedcom » et « recettes » (dates GEDCOM, textes,
  ingrédients) — 90 vérifications au total.
- Captures : `ordinateur-temoignages`, `mobile-temoignages`, `ordinateur-recettes`,
  `ordinateur-recette`, `mobile-recette`, `ordinateur-admin-recette`,
  `ordinateur-impression-fiche`.

## [1.6.0] — 2026-11-01

### Calendrier familial et frise chronologique

- Nouvelle page **Calendrier** (`/calendrier`) : grille d'un mois, toutes
  années confondues, avec les naissances, mariages et décès du jour ; détail
  sous la grille (année, âge, lieu du mariage) et navigation mois précédent /
  suivant.
- Nouvelle page **Frise chronologique** (`/frise`) : événements classés par
  année, par périodes de cinquante ans (navigation « Commencer », « ← », « → »),
  naissances, mariages, décès et **documents datés**. 400 événements au
  maximum par page : le Pi n'a jamais tout l'arbre en mémoire.
- **Lecture des dates** (`date_parts()`) : « 1928-06-12 », « 12/06/1928 »,
  « 12 juin 1928 », « 1er février 1975 », « juin 1928 », « vers 1930 ». Une
  date impossible (« 31 février ») est ignorée ; une date sans jour ni mois ne
  figure que dans la frise, à son année.
- Droits : un visiteur ne voit que les événements de ce qu'il peut voir ; un
  mariage n'apparaît que si **les deux** époux sont visibles. Quatre requêtes
  bornées par page (personnes, mariages, documents), aucune boucle sur l'arbre
  entier.
- Les deux pages s'impriment proprement (mise en page d'impression et bouton
  « Imprimer »).
- Données de démonstration : les dates de l'arbre fictif sont maintenant
  complètes (« 14 mars 1901 », mariage du « 12 juin 1928 ») pour que le
  calendrier soit vivant.
- **Tests** : `php app/cli/tests.php calendrier` (15 vérifications de dates et de
  grille) — 75 vérifications au total.
- Captures : `ordinateur-calendrier`, `ordinateur-calendrier-novembre`,
  `ordinateur-frise`, `mobile-calendrier`, `mobile-frise`.

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
