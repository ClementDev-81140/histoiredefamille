# Histoire de famille — histoiredefamille.eu

Site familial privé : documents, photos, vidéos et arbre généalogique,
accessible aux membres de la famille par un simple navigateur.

- **Adresse** : <https://histoiredefamille.eu>
- **Hébergement** : un Raspberry Pi 3B+ à la maison (1 Go de RAM, Ubuntu Server),
  publié par un tunnel Cloudflare (aucun port ouvert sur la box).
- **Technique** : PHP 8.3 + SQLite, Apache, un peu de JavaScript « fait main ».
  Aucun framework, aucune bibliothèque externe, **aucun Node.js en production**.
- **Langue** : français uniquement.

Le site est volontairement simple : peu de dépendances, donc peu de pannes,
et une installation qui tient en quelques commandes à copier-coller.

---

## Sommaire

1. [Ce que le site sait faire](#1-ce-que-le-site-sait-faire)
2. [Comptes et droits](#2-comptes-et-droits)
3. [Comment le code est organisé](#3-comment-le-code-est-organisé)
4. [Installer le site sur le Raspberry Pi](#4-installer-le-site-sur-le-raspberry-pi)
5. [Mettre le site en ligne (Cloudflare)](#5-mettre-le-site-en-ligne-cloudflare)
6. [Utiliser le site au quotidien](#6-utiliser-le-site-au-quotidien)
7. [Mettre à jour le site](#7-mettre-à-jour-le-site)
8. [Sauvegarde et restauration](#8-sauvegarde-et-restauration)
9. [Vidéos : convertir avant de déposer](#9-vidéos--convertir-avant-de-déposer)
10. [Arbre généalogique et GEDCOM](#10-arbre-généalogique-et-gedcom)
11. [En cas de problème](#11-en-cas-de-problème)
12. [Confidentialité et sécurité](#12-confidentialité-et-sécurité)
13. [Ouvrir le site au public : liste de contrôle du jour J](#13-ouvrir-le-site-au-public--liste-de-contrôle-du-jour-j)

---

## 1. Ce que le site sait faire

### Partie publique (visible par tous)

- Une page d'accueil qui tient en **trois entrées** : *Parcourir les
  documents*, *Voir l'arbre généalogique* et *Espace famille* (la connexion).
  C'est voulu : le site se consulte d'abord en privé, avec le compte de sa
  branche.
- Les autres rubriques (Articles, Vidéos, Albums, Calendrier, Recherche)
  n'apparaissent dans le menu et sur l'accueil **que si quelque chose y a été
  publié en « Public »**. Un visiteur ne tombe jamais sur une rubrique vide.
  Dès qu'un article public paraît, l'onglet Articles revient de lui-même.
- Les documents marqués « Public » : photos avec miniature, PDF consultables
  dans le navigateur, sons écoutables directement, autres fichiers
  téléchargeables.
- Des **albums photos** : les photos publiques regroupées par époque, voyage ou
  fête, avec le nom des personnes identifiées sous chaque photo.
- Un **calendrier familial** (naissances, mariages et décès du mois, toutes
  années confondues) et une **frise chronologique** (les mêmes événements, plus
  les documents datés, période par période).
- Les **témoignages sonores** (enregistrements, interviews) et les **recettes de
  famille**, chacun selon sa visibilité. Les pages s'impriment proprement.
- Une **recherche générale** qui porte d'un seul coup sur les documents, les
  articles, les pages d'information et les personnes de l'arbre.
- Les pages d'information publiques (histoire de la famille, annonces).
- L'arbre généalogique pour les personnes marquées « Public ».

### Partie famille (après connexion)

- Tout ce que voit le public, **plus** les documents réservés à la famille ou
  à une branche précise.
- Un **compte de branche** (identifiant en majuscules, un mot de passe partagé
  par les membres de cette branche) ou un **compte personnel** (identifiant
  `prenom.nom`, un mot de passe par personne). Les deux donnent exactement les
  mêmes droits ; le compte personnel signe le tchat à son nom.
- Une page **« Mon compte »** (menu, à côté de « Déconnexion ») pour changer
  son propre mot de passe.
- Un **tchat** privé : un seul salon commun à toutes les branches et aux
  administrateurs. Les messages sont signés « BRANCHE/Prénom » et ne sont
  visibles que des personnes connectées. Aucune application à installer :
  tout se passe dans le navigateur.

### Administration (3 comptes personnels)

- Tableau de bord séparé en deux blocs : **Partie publique** et **Partie famille**.
- Documents : envoi par le navigateur (photos, PDF, sons, documents bureautiques
  jusqu'à 50 Mo), titre, description, date, catégorie, étiquettes, **visibilité**,
  modification et suppression.
- **Albums photos** : création, rangement des photos (aucune copie de fichier),
  ordre d'affichage, photo de couverture, visibilité.
- **Personnes identifiées** sur une photo (ou un témoignage) : recherche dans
  l'arbre et association en deux clics.
- **Recettes de famille** : création, modification, suppression, photo
  facultative, visibilité.
- **Export GEDCOM** de tout l'arbre en un clic (Arbre généalogique →
  Exporter GEDCOM).
- Catégories de documents (créées par les administrateurs).
- Branches familiales : création, modification, suppression, **réinitialisation
  du mot de passe en un clic** (mot de passe lisible affiché une seule fois),
  désactivation/réactivation du compte. Case à cocher
  « **voit toutes les branches** » pour un compte qui doit tout voir.
- **Comptes de la famille** : une personne ou toute une liste d'un coup (100
  au plus par envoi), identifiant `prenom.nom` proposé automatiquement et
  modifiable, **aperçu obligatoire** avant création, mot de passe provisoire
  affiché **une seule fois** avec un message prêt à copier pour un SMS ou
  WhatsApp. Ensuite : modifier, désactiver/réactiver, réinitialiser le mot de
  passe, supprimer (les messages du tchat restent lisibles).
- Comptes administrateurs : 3 comptes personnels, mot de passe modifiable.
- Pages d'information : texte simple (markdown léger) avec visibilité.
- Arbre généalogique : saisie manuelle des personnes et des couples, ou import
  **GEDCOM** avec aperçu avant validation.
- **Vidéos** : liste complète (même les privées), recherche, filtre de
  visibilité, **mise en avant** en un clic, **ordre d'affichage**, durée,
  autorisation de téléchargement, import depuis le dossier arrivée,
  modification et suppression.
- **Import depuis le dossier arrivée** : pour les gros fichiers (vidéos)
  déposés sur le Pi par le réseau local.
- **Journal des connexions** : qui s'est connecté, quand, depuis quelle
  adresse ; les échecs avec leur raison ; les comptes bloqués après trop
  d'essais. Une **alerte** apparaît sur le tableau de bord quand un
  administrateur se connecte depuis une adresse inconnue, ou quand un compte
  reçoit plus de dix échecs en une heure.
- Page **État du site** : version de PHP, limites d'envoi, taille de la base,
  espace disque libre, dernières lignes du journal de sauvegarde, contrôles
  de sécurité.
- Réglages du site : nom, sous-titre, description, adresse de contact,
  ouverture du **tchat** et durée de conservation de ses messages.
- **Apparence du journal** : les textes de la une (bandeau, devise, numéro
  d'édition, pied de page, titres des encarts), les lignes à afficher ou à
  masquer, le titre en gothique et les **couleurs** — palette d'origine,
  bleu d'avant, ou quatre couleurs choisies, avec contrôle des contrastes et
  correction automatique en un clic.
- **Composition des pages** : afficher ou masquer les blocs de l'accueil et de
  l'espace famille, les faire monter ou descendre, régler la une (nombre
  d'articles, longueur du résumé, photo) et choisir le **modèle d'article**
  par défaut (Journal, Simple, Grande photo).

### Journal des connexions et alertes

Trois administrateurs se partagent le site : il faut pouvoir répondre à la
question « qui est entré, et est-ce normal ? ».

- **Journal des connexions** (`/admin/connexions`, menu de gauche) : les
  dernières connexions, les plus récentes en haut — identifiant essayé, date et
  heure, adresse, résultat (réussie ou refusée) et la raison du refus (« mot de
  passe incorrect », « compte bloqué après trop d'essais »…). Des boutons
  filtrent sur les réussites, les échecs ou les adresses nouvelles.
- **Alerte sur le tableau de bord** dans deux cas seulement :
  - un **administrateur** se connecte depuis une adresse jamais vue pour lui
    (la toute première connexion d'un compte ne déclenche rien) ;
  - un compte reçoit **plus de dix échecs en une heure** — quelqu'un essaie
    d'entrer à la chaîne.
  Le bouton « **J'ai vu** » efface l'alerte ; le journal, lui, garde la trace.
- **Durée de conservation** réglable dans la même page : 30, 90 (valeur par
  défaut), 180 ou 365 jours. Au-delà, les lignes sont effacées par la tâche
  d'entretien du dimanche (`app/cli/maintenance.php`).
- Rien de tout cela n'est visible d'un compte de famille : la page et les
  boutons répondent « page introuvable » (404) aux autres, exactement comme
  pour une page qui n'existe pas.
- Un mot de passe n'est **jamais** écrit dans ce journal : seulement
  l'identifiant essayé, l'adresse et la raison du refus.

### Articles (le journal de la famille)

Un onglet **Articles**, à côté d'« Accueil », sert de fil d'actualité : les
nouvelles de la famille, les souvenirs, les annonces.

- La page d'accueil met les articles les plus récents **à la une**.
- Chaque article a : un titre, une adresse (`/articles/mon-titre`), une
  rubrique (« Souvenirs », « Nouvelles »…), un résumé, un texte mis en forme
  légère, une image à la une (choisie parmi les photos) et une **visibilité**
  comme les documents.
- Un article peut rester en **brouillon** (personne d'autre que les
  administrateurs ne le voit) ou être **publié**. La date de publication peut
  être dans le futur : c'est une **publication programmée**, l'article apparaît
  tout seul le jour venu, sans intervention.
- Un article **privé** ou en brouillon renvoie une page « introuvable », comme
  un contenu interdit.
- Administration → **Articles** : liste de tous les articles (brouillons et
  programmés compris), création, modification, suppression. Un administrateur
  peut relire un brouillon en cliquant dessus.
- **Filtrer l'édition** : en haut de la page Articles, deux rangées de liens
  servent de sommaire de journal.
  - **Édition** : « Toutes », puis une entrée par branche familiale. Une
    personne ne voit que les branches auxquelles elle a droit.
  - **Années** : une frise par décennie (`1950`, `1960`…). L'année est celle
    racontée dans l'article (« Mariage de 1958 » → 1958), pas la date de mise
    en ligne ; à défaut, on prend l'année de publication.
  - Chaque clic recharge la page avec `?branche=…` ou `?decennie=…` : aucun
    JavaScript, le lien se partage par courriel. Les deux filtres peuvent se
    combiner, et les règles de visibilité restent les mêmes (un article
    réservé ne sort jamais pour un visiteur).
  - Si rien ne correspond : « Aucune nouvelle pour cette édition. La rédaction
    attend vos plis ! ».

### Vidéos

Un onglet **Vidéos** figure dans le menu à côté de « Documents » et « Arbre »,
pour tout le monde. Les vidéos sont des documents comme les autres : mêmes
droits, même stockage, aucun second système.

- Liste filtée par les droits : un visiteur ne connecté voit les vidéos
  **publiques** ; un compte de famille voit en plus celles réservées aux
  familles et à ses branches ; un administrateur voit tout, et un compte
  « voit toutes les branches » voit toutes les branches. Une vidéo **privée**
  n'apparaît jamais dans l'espace famille.
- Grille de cartes : miniature, titre, date, durée, court résumé.
- Tri : les vidéos **mises en avant** d'abord, puis l'ordre d'affichage choisi
  par l'administrateur, puis les plus récentes. Pagination automatique.
- Fiche d'une vidéo : lecteur `<video controls preload="metadata" playsinline>`,
  date, durée, branches concernées, bouton **Télécharger** (seulement si
  l'administrateur l'autorise), lien de retour. Elle est accessible à un
  visiteur non connecté **si la vidéo est publique**.
- Vidéo non autorisée : même page « introuvable » que les autres documents
  (on ne révèle pas son existence).
- Lecture dans le navigateur **sans réencodage** (le Pi ne pourrait pas
  convertir un film), diffusion par morceaux (HTTP Range) : on peut se déplacer
  dans le film sans télécharger tout le fichier, et la mémoire du Pi n'est
  jamais saturée.
- Limite de lectures simultanées (2 par défaut). Au-delà, le site affiche
  « **Trop de personnes regardent des vidéos, réessayez dans une minute** »
  au lieu d'une erreur technique, et le flux répond 503 avec `Retry-After`.

### Tchat de la famille

Un onglet **Tchat** apparaît dans le menu **uniquement pour les personnes
connectées**. C'est un seul salon commun : toutes les branches et les
administrateurs y écrivent, tout le monde lit tout.

- **Compte personnel** : la signature est automatique, c'est le prénom du
  compte — « **MARTIN/Élise** » —, sans rien demander à la connexion.
- **Compte de branche** : au premier passage, on indique le **prénom** qui
  signera les messages (1 à 30 caractères, lettres, espace et tiret). La
  signature devient « **MARTIN/Jean** » ; un administrateur signe
  « **ADMIN/Clément** ».
  Le prénom est gardé le temps de la session (le compte d'une branche est
  partagé : chacun met le sien sur son appareil) et se change à tout moment
  via « Changer mon prénom ». Les messages déjà envoyés gardent leur signature
  d'origine.
- **Texte brut uniquement**, 1000 caractères au maximum. Le HTML n'est jamais
  interprété ; les sauts de ligne sont conservés et une adresse web tapée
  devient un lien qui s'ouvre dans un nouvel onglet (jamais de lien
  `javascript:`).
- **Anti-spam** : un message par seconde et 30 par minute et par compte ; un
  message vide est refusé.
- **Affichage en journal** : séparateur par journée, heure de chaque message,
  défilement automatique vers le message le plus récent, 50 derniers messages
  puis un bouton « Voir les messages plus anciens » (par paquets de 50 : le Pi
  n'a jamais tout l'historique en mémoire).
- **Mise à jour automatique** : le navigateur demande seulement les messages
  plus récents que le dernier affiché, toutes les 5 secondes. Après trois
  minutes sans nouveauté il passe à 30 secondes, et il s'arrête complètement
  quand l'onglet est caché ou la page quittée. Aucun service permanent côté
  serveur (pas de WebSocket, pas de Node, pas de Redis).
- **Pastille** : une petite bulle sur l'onglet indique le nombre de nouveaux
  messages depuis la dernière visite (une seule requête légère par page).
- **Administrateurs** : bouton « Supprimer » sur chaque message. La
  suppression est inscrite dans le journal d'activité (auteur, date, début du
  texte).
- **Tchat fermé** : Administration → Réglages permet de fermer le salon (l'onglet
  disparaît, aucune lecture, aucune écriture) ; les messages sont conservés.
- **Conservation** : Administration → Réglages choisit la durée (jamais,
  1 an — valeur par défaut —, 6 mois, 3 mois). La tâche d'entretien
  `app/cli/maintenance.php` efface les messages plus anciens ; avec « jamais »,
  rien n'est jamais supprimé automatiquement.

### Recherche générale et fiches de personnes

Un onglet **Recherche** figure dans le menu, pour tout le monde.

- Une seule case à remplir : les mots tapés sont cherchés dans les **titres,
  descriptions et étiquettes** des documents, dans les **articles**, dans les
  **pages d'information** et dans les **noms et lieux** des personnes de
  l'arbre.
- Les accents, les majuscules et la ponctuation n'ont pas d'importance :
  « begles », « Bègles » et « BÈGLES » donnent le même résultat, et « 1928 »
  trouve les documents et les personnes de cette année-là.
- Un lien par catégorie permet de ne voir que les documents, que les articles,
  que les pages ou que les personnes.
- **Chacun ne trouve que ce qu'il a le droit de voir** : un visiteur non
  connecté ne voit que le public, une branche voit en plus ce qui la concerne.
  Les résultats interdits n'apparaissent pas du tout.
- Les fiches de personnes sont plus complètes : une **chronologie** (naissance,
  mariages, naissances des enfants, décès), la **famille élargie**
  (grands-parents, oncles et tantes, petits-enfants, neveux et nièces), les
  **lieux cliquables** (ils lancent une recherche) et « **Autres personnes
  portant le nom …** » pour repérer les homonymes et les branches cousines.

L'index de recherche se remplit tout seul à chaque ajout, modification ou
suppression, et il est reconstruit une fois par semaine par la tâche
d'entretien (`app/cli/maintenance.php`). Si un résultat manque après une
restauration de sauvegarde, lancer cette commande le remet d'aplomb.

### Albums photos et personnes identifiées

Un onglet **Albums** range les photos déjà envoyées : aucun fichier n'est copié
ni déplacé, un album n'est qu'un rangement.

- Chaque album a un titre, une description, une **visibilité**, un ordre
  d'affichage et une **photo de couverture** (la première par défaut).
- Les photos gardent leur propre visibilité : une photo privée glissée dans un
  album public reste invisible pour un visiteur. Un album vide n'affiche donc
  jamais rien de confidentiel.
- Sur la fiche d'une photo, la section « **Personnes sur ce document** » mène
  aux fiches des personnes concernées ; la fiche d'une personne affiche en
  retour « **Photos et documents** » avec les photos où elle figure.
- Seuls les noms que le visiteur a le droit de voir sont affichés : une
  personne privée n'apparaît pas dans une légende publique.
- « **Imprimer** » sur un album ou une fiche donne une page sobre, sans menu ni
  boutons (mise en page spéciale pour l'imprimante).

### Calendrier familial et frise chronologique

Deux façons de traverser les dates de la famille, ouvertes à tout le monde.

- **Calendrier** (`/calendrier`) : une grille mensuelle, toutes années
  confondues. Chaque jour signale les naissances, mariages et décès qui y sont
  tombés, avec le détail sous la grille (« 18 novembre 1982 — décès de Marie
  LEROY, 77 ans »). Les flèches changent de mois.
- **Frise** (`/frise`) : les mêmes événements, plus les documents datés, année
  par année, par périodes de cinquante ans (1900-1949, 1950-1999…).
- Les dates sont lues telles que la famille les a écrites : « 12 juin 1928 »,
  « 12/06/1928 », « 1928-06-12 », « 1er février 1975 », « juin 1928 » ou
  simplement « vers 1930 ». Une date sans jour ni mois ne peut pas entrer dans
  le calendrier : elle reste dans la frise, à son année.
- Comme partout, un visiteur ne voit que les événements des personnes et des
  documents auxquels il a droit ; un mariage n'apparaît que si les deux époux
  sont visibles.
- Les deux pages s'impriment proprement (bouton « Imprimer »).

### Témoignages sonores et recettes de famille

- **Témoignages** (`/temoignages`) : les enregistrements audio (MP3, M4A, OGG)
  envoyés comme des documents. Lecture directe dans le navigateur, avance
  rapide comprise ; le nom des personnes qui parlent apparaît sous le titre
  (elles sont identifiées sur le document, comme sur une photo). L'onglet
  n'apparaît dans le menu que s'il existe au moins un enregistrement visible.
- **Recettes** (`/recettes`) : titre, origine (« recette de Mamie »), quantités,
  ingrédients (un par ligne), préparation, astuce et souvenir, plus une photo
  si on veut. Visibilité habituelle, page propre et imprimable pour le carnet
  de cuisine. L'onglet apparaît dès qu'une recette existe.
- **Impression** : sur une fiche de personne, un album, une recette ou la frise,
  le bouton « **Imprimer** » ouvre l'impression du navigateur avec une mise en
  page sans menu, sans bouton et sans couleur de fond, avec le nom du site et
  la date en en-tête.

### Visibilité : la règle la plus importante

Chaque document, page, personne et article a un niveau de visibilité :

| Niveau | Qui le voit |
|---|---|
| **Public** | tout le monde, sans connexion |
| **Toutes les familles** | tous les comptes de famille connectés et les administrateurs |
| **Branches précises** | uniquement les branches cochées (+ administrateurs et comptes « voit toutes les branches ») |
| **Privé** | uniquement les administrateurs |

> **Rien n'est public par défaut.** Si aucune visibilité n'est choisie, le
> contenu est enregistré comme **privé**. Il faut toujours un geste volontaire
> pour rendre un document visible.

---

## 2. Comptes et droits

| Type de compte | Identifiant | Mot de passe | Droits |
|---|---|---|---|
| Administrateur | `votre-identifiant`, plus deux autres (3 comptes personnels) | personnel, modifiable | tout : documents, branches, comptes, arbre, pages, réglages |
| Branche familiale | `MARTIN`, `DURAND`… (en majuscules) | un mot de passe partagé par la branche | lecture des documents de la branche (+ publics) |
| Branche « voit tout » | par exemple `RAVIART` | idem | lecture de tous les documents « famille » et « branches » (mais pas des documents privés) |
| Personne | `prenom.nom` (`elise.martin`) | un mot de passe par personne | ceux de sa branche (+ les mêmes options) ; signe le tchat à son nom |

Points importants :

- **Aucune inscription publique** : seuls les administrateurs créent des comptes.
- Un compte de famille est rattaché à **une branche**, et il en existe de deux
  sortes :
  - le **compte de branche**, partagé par toute la famille (un seul mot de
    passe : simple pour les cousins qui n'ont qu'un identifiant à retenir) ;
  - le **compte personnel**, un par personne (`elise.martin`), avec son propre
    mot de passe et son prénom sur le tchat. Il donne **exactement les mêmes
    droits** que le compte de sa branche, jamais plus.
- Un compte personnel (ou de branche) ne peut **pas** administrer le site : les
  administrateurs se créent uniquement en ligne de commande
  (`php app/cli/create-admin.php`).
- « Voit toutes les branches » est une **case à cocher sur la branche**, pas un
  nom en dur dans le code : aucune famille n'est privilégiée par le programme.
- Après 5 échecs de connexion, l'identifiant **et** l'adresse IP sont bloqués
  15 minutes.
- Les mots de passe sont stockés hachés (Argon2id si disponible, sinon bcrypt).
  Personne, pas même un administrateur, ne peut les relire.
- Un administrateur peut réinitialiser le mot de passe d'une branche ou d'une
  personne : un mot de passe lisible du type `shxn-2qb6-5et5` est généré et
  affiché **une seule fois**, avec un message prêt à copier. Notez-le et
  transmettez-le.
- Le mot de passe d'un compte personnel **ne s'affiche jamais** ailleurs : ni
  dans la base (seule l'empreinte est gardée), ni dans les journaux, ni dans
  une adresse web.

### La matrice d'accès (preuve, pas promesse)

Le fichier **[docs/matrice-acces.md](docs/matrice-acces.md)** liste **toutes**
les adresses du site (136) et ce que chacune répond aux cinq profils : visiteur,
branche A, personne de la branche B, compte « voit toutes les branches »,
administrateur.

Il est **engendré par les tests**, pas écrit à la main : il décrit ce que le
site répond vraiment. Pour le régénérer après une modification :

```bash
php app/cli/tests.php matrice
```

Le même test vérifie les cas sensibles : un identifiant de fichier ne donne pas
accès à un document d'une autre branche, une recherche ne fait jamais sortir un
contenu privé, l'export GEDCOM reste réservé aux administrateurs, le tchat est
fermé aux visiteurs, et **toutes** les actions d'administration en POST sont
refusées sans jeton CSRF comme pour un compte non administrateur.

> Si une route est ajoutée dans `app/routes.php` sans que sa règle d'accès soit
> connue, le test **échoue** : il faut décider explicitement qui peut l'ouvrir.

---

## 3. Comment le code est organisé

```
histoiredefamille/
├── public/                  ← SEUL dossier visible depuis le web
│   ├── index.php            point d'entrée unique (toutes les URL passent ici)
│   ├── maintenance.html     page statique affichée en cas de 503
│   ├── .htaccess            URL propres, blocage des fichiers sensibles, en-têtes
│   └── assets/              style.css, app.js et polices woff2 auto-hébergées
│                            (aucune bibliothèque ni ressource externe)
├── app/                     ← code de l'application (hors racine web)
│   ├── bootstrap.php        démarrage, gestion des erreurs
│   ├── config.php           configuration par défaut + lecture de config.local.php
│   ├── router.php           routeur minimal
│   ├── routes.php           table des URL
│   ├── controllers/         une fonction par page
│   ├── lib/                 base de données, sécurité, stockage, images, vidéos,
│   │                        GEDCOM, visibilité, textes, tchat, recherche,
│   │                        albums, calendrier, recettes, journal des connexions,
│   │                        gazette, rubriques publiques, comptes de la famille,
│   │                        apparence du journal (textes et couleurs),
│   │                        composition des pages et modèles d'article,
│   │                        vérification avant ouverture (mise_en_ligne)…
│   ├── views/               gabarits PHP (public, famille, admin)
│   ├── migrations/          fichiers SQL numérotés
│   │                        (001_init … 009_articles_modele)
│   └── cli/                 commandes : migrate, create-admin, demo, maintenance,
│                            sauvegarde, restauration, verifier-mise-en-ligne, tests
├── deploy/
│   ├── install.sh           installation complète sur le Pi
│   ├── systemd/             minuteur de sauvegarde (tous les jours à 3 h 30)
│   ├── backup.sh            ancien point d'entrée (appelle la commande PHP)
│   ├── crontab.example      tâches planifiées
│   ├── apache/              VirtualHost Apache
│   ├── php/                 réglages PHP pour le Pi
│   ├── cloudflared/         configuration du tunnel
│   ├── montage-disques.md   SSD et clé USB (fstab, droits)
│   └── dossier-arrivee.md   déposer des vidéos par le réseau local
├── config.example.php       modèle de configuration (à copier)
├── config.local.php         VOTRE configuration (jamais dans Git)
├── storage/                 stockage local de développement (jamais dans Git)
├── docs/captures/           captures d'écran du site (ordinateur et mobile)
├── docs/matrice-acces.md    qui peut ouvrir quoi (engendré par les tests)
├── docs/chiffrement-cle-usb.md  chiffrer la clé de sauvegarde (facultatif)
├── CHANGELOG.md             historique des versions
└── TODO.md                  ce qui reste à faire
```

Autour du dossier du site :

```
/mnt/ssd/histoiredefamille/      ← SSD : base, photos, vidéos, miniatures
    histoire.sqlite
    medias/  miniatures/  arrivee/  tmp/  verrous/
/mnt/sauvegarde/histoiredefamille/  ← clé USB : sauvegardes
```

Pourquoi séparer le SSD de la carte SD : la carte SD des Raspberry Pi meurt
souvent. Le code se réinstalle en dix minutes ; les photos, elles, ne se
reconstituent pas. Elles sont donc sur le SSD, et sauvegardées sur la clé USB.

### Le style et les polices

La présentation s'inspire des quotidiens anciens : filets fins, aucun coin
arrondi, aucune ombre, titres de rubriques en majuscules espacées, texte
justifié (aligné à gauche sur téléphone), en-tête « une » avec la date en
français écrite par PHP (aucune dépendance à l'extension `intl`).

Depuis la version 1.9, le site est habillé en **« gazette »** : papier crème,
encre brune, rouge brique, bandeau « date · édition · prix » et, dans l'espace
famille, une mise en page de « une » (article à la une, colonnes, encarts
« Petites annonces » et « Courrier des lecteurs »).

Trois blocs de `public/assets/style.css` s'en occupent, à la fin du fichier :

1. `DESIGN « GAZETTE »` : la palette et l'en-tête de journal ;
2. `DESIGN « GAZETTE » — COMPLÉMENTS` : les couleurs des champs, encadrés,
   badges et tableaux, plus les réglages du téléphone ;
3. `GAZETTE 1.9.2 — LE MOULE DU JOURNAL` : la mise en page de journal
   (papier vieilli, titre de « une », colonnes, lettrines, encarts, plaques de
   repli, page d'article). Ce bloc ne renomme rien : il arrive après les deux
   autres et les surcharge.

Depuis la 1.11, un article peut prendre trois allures (bloc
`COMPOSITION DES PAGES (1.11.0)`, à la fin du fichier) : « Journal » (la mise en
page décrite ci-dessus), « Simple » (une colonne, sans ornement) et
« Grande photo » (l'image en pleine largeur avant le texte). Ces règles
n'existent que sous les classes `.article--modele-simple` et
`.article--modele-photo` : un article resté en « Journal » n'a aucune classe de
modèle et s'affiche exactement comme avant.

Depuis la 1.9.2, le fond par défaut est une **feuille de papier** : crème (`#f3e7ce`) avec
deux halos bruns et une ombre intérieure, tenue en place pendant que le texte
défile. Sur un téléphone, le fond redevient fixe par rapport à la page (même
aspect, moins de travail pour le processeur). L'administration garde un
en-tête compact : le grand titre de journal est réservé aux pages publiques.

Toutes les couleurs passent par des **variables** déclarées dans le bloc
`:root`, tout en haut de `public/assets/style.css` : le papier, le texte,
l'encre, l'accent et ses variantes, les bordures, les fonds de messages, les
couleurs du code. Aucune couleur n'est écrite en dur ailleurs (sauf dans le
bloc d'impression, qui reste en noir sur blanc). Les classes CSS n'ont pas
changé de nom.

Depuis la 1.11, ces variables peuvent être remplacées sans toucher au CSS :
l'administration écrit une feuille `/theme.css` qui ne contient que les
couleurs choisies, et `layout.php` la charge **après** `style.css`, seulement
quand une autre palette que « Papier ancien » est active. Pour revenir à
l'ancienne palette bleue, il suffit donc de choisir « Bleu d'origine » dans
**Administration → Apparence du journal**.

Deux réglages facultatifs donnent la devise sous le titre et l'année de
fondation : `devise` et `fondee` (dans la table `settings`, comme les autres
réglages). S'ils sont absents, la devise reprend le sous-titre du site. Ils se
modifient désormais dans **Apparence du journal** (voir 6.4), qui les relit
avec le titre du site (`name`) et le sous-titre (`subtitle`).

La police gothique du titre (UnifrakturMaguntia, licence libre OFL) est
fournie dans `public/assets/fonts/` (`UnifrakturMaguntia.woff`, licence dans
`OFL-UnifrakturMaguntia.txt`). Si le fichier disparaît, le titre revient tout
seul à Old Standard TT.

La police à empattements (**Old Standard TT**, licence OFL) est **hébergée sur
le site** dans `public/assets/fonts/` (3 fichiers `woff2` : normal, italique,
gras, avec `font-display: swap`). Aucun appel à Google Fonts ni à un autre
service : la CSP peut rester `default-src 'self'`, et le site fonctionne même
si l'accès à Internet tombe. La licence est fournie à côté des fichiers
(`OFL.txt`).

Pour changer de police plus tard : remplacer les trois `woff2` et la famille
dans `@font-face` (variable `--serif`), rien d'autre.

---

## 4. Installer le site sur le Raspberry Pi

Cette partie se fait une seule fois. Toutes les commandes sont à copier-coller
telles quelles, sauf celles marquées « à adapter ».

### 4.1 Préparer la carte SD (Ubuntu Server)

1. Installer **Ubuntu Server 24.04 LTS (64 bits)** avec *Raspberry Pi Imager*.
2. Dans les options avancées de l'outil, avant d'écrire :
   - activer SSH (clé publique si vous en avez une),
   - définir un identifiant (`votre-utilisateur`) et un mot de passe,
   - régler le nom d'hôte (`histoiredefamille`) et le Wi-Fi.
3. Insérer la carte, brancher le SSD et la clé USB, démarrer, attendre une
   minute, puis se connecter depuis un autre ordinateur :

```bash
ssh votre-utilisateur@histoiredefamille.local
```

(Si le nom ne se résout pas, utilisez l'adresse IP affichée sur l'écran ou
donnée par la box.)

```bash
sudo apt-get update && sudo apt-get upgrade -y
sudo timedatectl set-timezone Europe/Paris
```

### 4.2 Monter le SSD et la clé USB

À faire **avant** d'installer le site : c'est là que vivront les données.

👉 Suivez le guide pas à pas : **[`deploy/montage-disques.md`](deploy/montage-disques.md)**

Résumé de ce que vous devez obtenir à la fin :

```bash
df -h /mnt/ssd /mnt/sauvegarde
```

deux lignes doivent s'afficher (le SSD et la clé), et le test suivant doit
répondre « OK » :

```bash
sudo -u www-data test -w /mnt/ssd/histoiredefamille && echo "OK"
```

### 4.3 Récupérer le code

```bash
cd /home/votre-utilisateur
git clone https://github.com/VOTRE-COMPTE/histoiredefamille.git
cd histoiredefamille
```

> Le dépôt est public : il ne contient **aucune** donnée familiale, aucun mot
> de passe. Vos photos et votre base restent sur le Pi.

### 4.4 Lancer l'installation

```bash
sudo bash deploy/install.sh
```

Ce script :

1. installe Apache, PHP et ses extensions (`php-sqlite3`, `php-gd`,
   `php-mbstring`, `php-curl`), `sqlite3`, `rsync`, `curl` ;
2. active les modules Apache `rewrite`, `headers`, `expires`, `deflate` ;
3. copie le site dans `/var/www/histoiredefamille` ;
4. crée `config.local.php` à partir du modèle ;
5. crée les dossiers de stockage sur le SSD, avec les droits de `www-data` ;
6. applique les réglages PHP adaptés au Pi (mémoire, envois, opcache) ;
7. active le VirtualHost sur le port 80 ;
8. crée la base SQLite et applique les migrations ;
9. installe et active le minuteur de sauvegarde (tous les jours à 3 h 30) ;
10. garde la maintenance hebdomadaire dans `/etc/cron.d/histoiredefamille`.

### 4.5 Adapter la configuration

```bash
sudo nano /var/www/histoiredefamille/config.local.php
```

Les seules lignes à vérifier :

```php
'site' => [
    'name'        => 'Histoire de la famille Martin',   // le titre affiché
    'subtitle'    => 'Nos souvenirs, nos photos, notre histoire',
    'url'         => 'https://histoiredefamille.eu',
    'description' => 'Documents, photos et arbre généalogique de la famille.',
    'contact'     => '',                                // facultatif
],
'https_only' => true,   // le site est servi en HTTPS par Cloudflare
'debug'      => false,  // ne jamais laisser à true en production
'database'   => '/mnt/ssd/histoiredefamille/histoire.sqlite',
```

Les chemins de `storage` et `maintenance` conviennent en général tels quels.

### 4.6 Créer le premier administrateur

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/create-admin.php
```

La commande demande un identifiant, un nom affiché et un mot de passe
(**12 caractères minimum**). Le mot de passe est saisi au clavier : il n'est
écrit ni dans le code, ni dans un fichier. Rien ne s'affiche pendant la frappe,
c'est normal.

Répétez l'opération pour créer **les deux autres comptes administrateurs** :
trois comptes personnels, c'est plus sûr qu'un compte partagé (on peut
désactiver un compte sans bloquer les autres).

### 4.7 Vérifier que le site répond

```bash
curl -I http://localhost/
```

Réponse attendue : `HTTP/1.1 200 OK`. Ouvrez aussi la page d'état
(après connexion) : **Administration → État du site**.

En cas d'erreur 500, le journal donne la cause exacte :

```bash
sudo tail -50 /var/log/apache2/histoiredefamille-error.log
```

### 4.8 (Facultatif) Données de démonstration

Pour visiter le site avec du contenu **fictif** avant de saisir le vôtre :

```bash
cd /var/www/histoiredefamille
sudo -u www-data php app/cli/demo.php
```

Le script affiche à la fin les mots de passe (aléatoires) des comptes de
branche de démonstration. Les contenus sont imaginaires : noms, dates et
personnes n'ont aucun rapport avec votre famille. Pour repartir de zéro,
voir « Repartir d'une base vierge » au § 8.6.

---

## 5. Mettre le site en ligne (Cloudflare)

Objectif : `https://histoiredefamille.eu` accessible depuis n'importe où,
**sans ouvrir aucun port** sur la box. Le Pi ouvre une connexion sortante vers
Cloudflare, qui renvoie les visites vers Apache.

### 5.1 Le domaine doit être chez Cloudflare

1. Créer un compte sur <https://dash.cloudflare.com> et ajouter le domaine
   `histoiredefamille.eu`.
2. Chez le registrar (celui où le domaine a été acheté), remplacer les serveurs
   DNS par ceux indiqués par Cloudflare (`xxx.ns.cloudflare.com`).
3. Attendre la propagation (de quelques minutes à quelques heures).

### 5.2 Installer `cloudflared` sur le Pi

```bash
sudo mkdir -p --mode=0755 /usr/share/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" | sudo tee /etc/apt/sources.list.d/cloudflared.list
sudo apt-get update && sudo apt-get install -y cloudflared
```

### 5.3 Créer le tunnel

```bash
sudo cloudflared tunnel login
```

Cette commande affiche une adresse : ouvrez-la dans le navigateur de votre
ordinateur, choisissez le domaine `histoiredefamille.eu`, autorisez. Le Pi
enregistre alors un certificat.

```bash
sudo cloudflared tunnel create histoiredefamille
```

La commande affiche un **identifiant** (une suite de lettres et de chiffres)
et crée un fichier `TUNNEL-ID.json` dans `/root/.cloudflared/`.

### 5.4 Configurer le tunnel

```bash
sudo mkdir -p /etc/cloudflared
sudo cp /var/www/histoiredefamille/deploy/cloudflared/config.yml /etc/cloudflared/config.yml
sudo nano /etc/cloudflared/config.yml
```

Remplacez `TUNNEL-ID` par l'identifiant affiché à l'étape précédente, par
exemple :

```yaml
tunnel: histoiredefamille
credentials-file: /root/.cloudflared/1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d.json

protocol: quic
no-autoupdate: true

originRequest:
  noTLSVerify: true
  connectTimeout: 30s
  tcpKeepAlive: 30s

ingress:
  - hostname: histoiredefamille.eu
    service: http://localhost:80
  - hostname: www.histoiredefamille.eu
    service: http://localhost:80
  - service: http_status:404

loglevel: info
logfile: /var/log/cloudflared.log
```

```bash
sudo chmod 600 /etc/cloudflared/config.yml
sudo chown root:root /etc/cloudflared/config.yml
```

> Le fichier `TUNNEL-ID.json` est une **clé d'accès** : il ne doit jamais être
> copié dans le dépôt Git ni envoyé par e-mail.

### 5.5 Déclarer le nom de domaine et installer le service

```bash
sudo cloudflared tunnel route dns histoiredefamille histoiredefamille.eu
sudo cloudflared tunnel route dns histoiredefamille www.histoiredefamille.eu
sudo cloudflared service install
sudo systemctl enable --now cloudflared
sudo systemctl status cloudflared
```

Le statut doit indiquer `active (running)` et les journaux doivent montrer
`Registered tunnel connection`.

> Si `cloudflared service install` échoue, copiez le modèle fourni :
> `sudo cp /var/www/histoiredefamille/deploy/cloudflared/cloudflared.service /etc/systemd/system/`
> puis `sudo systemctl daemon-reload && sudo systemctl enable --now cloudflared`.

### 5.6 Vérifier

Depuis un téléphone en 4G (donc hors du réseau de la maison) :

<https://histoiredefamille.eu>

Vérifications utiles :

```bash
sudo journalctl -u cloudflared -n 50 --no-pager   # tunnel
sudo tail -20 /var/log/apache2/histoiredefamille-error.log
curl -I https://histoiredefamille.eu              # doit répondre 200
```

Avant d'ouvrir le site à la famille, suivez la liste complète :
[13. Ouvrir le site au public](#13-ouvrir-le-site-au-public--liste-de-contrôle-du-jour-j).

### 5.7 Réglages conseillés dans Cloudflare

Dans le tableau de bord Cloudflare, onglet **SSL/TLS** :

- mode **Full** (le trafic Cloudflare → Pi est chiffré ; le certificat
  d'origine est vérifié par le tunnel, pas besoin de « Full strict »).
- activer **Always Use HTTPS**.
- activer **Automatic HTTPS Rewrites**.

Onglet **Rules → Transform Rules** : forcer `www` vers le domaine principal
si vous préférez une adresse unique.

Onglet **Speed** : laisser la compression **désactivée pour les vidéos**
(Cloudflare ne les compresse pas de toute façon, et le site envoie déjà
`no-gzip` pour les fichiers `.mp4`).

> **Important** : Cloudflare limite une requête à environ 100 Mo. C'est pour
> cela que les envois par le navigateur sont plafonnés à 50 Mo et que les
> vidéos passent par le dossier « arrivée ».

---

## 6. Utiliser le site au quotidien

### 6.1 Ajouter des photos et des documents

1. **Administration → Documents → Nouveau document**.
2. Choisir le fichier (jusqu'à 50 Mo), saisir un titre, éventuellement une
   description, une date, une catégorie et des étiquettes.
3. Choisir la **visibilité** — c'est le point à ne pas oublier.
4. Enregistrer.

Le fichier est renommé avec un nom aléatoire et rangé hors de la racine web :
il n'est jamais accessible par une URL directe. Toutes les lectures passent
par un script PHP qui vérifie les droits.

Une **miniature** est créée automatiquement pour les photos (480 px de côté
maximum) : les listes restent rapides même sur un petit écran et sur le Pi.

Formats acceptés : `pdf`, `txt`, `jpg`, `jpeg`, `png`, `webp`, `gif`, `mp4`,
`m4v`, `docx`, `xlsx`, `odt`, `ods`, `mp3`, `m4a`, `ogg`. Le type réel du
fichier est vérifié : renommer un fichier n'aide pas à contourner la règle.

### 6.2 Créer une branche familiale

1. **Administration → Branches → Nouvelle branche**.
2. Nom de la branche (`MARTIN`), nom complet (« Famille Martin »), cocher
   « voit toutes les branches » si nécessaire.
3. Un mot de passe est généré : **notez-le tout de suite**, il ne sera plus
   affiché. En cas d'oubli, utilisez le bouton **Réinitialiser le mot de passe**.

### 6.3 Créer les comptes de la famille

Il y a **deux sortes de comptes**, et elles cohabitent très bien :

| Compte | Qui l'utilise | Identifiant |
|---|---|---|
| **Compte de branche** | une famille entière, avec un seul mot de passe partagé | en majuscules : `MARTIN` |
| **Compte personnel** | une personne, avec son propre mot de passe | `prenom.nom` : `elise.martin` |

Les deux voient la même chose : les documents publics, ceux ouverts à toutes
les familles, et ceux de leur branche. Un compte personnel n'a aucun droit de
plus (et ne peut pas administrer le site).

**a. Ajouter une personne**

1. **Administration → Comptes → « Ajouter des personnes »**.
2. Bloc **« Une personne »** : nom, prénom, branche. Cochez « Voit toutes les
   branches » seulement si cette personne doit voir les autres branches.
3. **« Voir l'aperçu »** : le site propose un identifiant (`elise.martin`) que
   vous pouvez corriger à la main, à condition qu'il soit libre.
4. **« Créer les comptes cochés »** : le mot de passe provisoire s'affiche
   **une seule fois**.

**b. Ajouter toute une liste d'un coup**

1. Même page, bloc **« Plusieurs personnes »**.
2. Collez la liste, **une personne par ligne**. Trois écritures possibles :
   - `Martin;Élise` — le plus sûr, à conseiller ;
   - `Élise Martin` ou `Martin Élise` — deux mots : le site comprend
     « prénom puis nom » et le rappelle dans l'aperçu ;
   - dès qu'un nom ou un prénom compte plusieurs mots (`Le Gall`, `Jean-Paul`),
     écrivez `Le Gall;Jean-Paul`.
3. Choisissez la branche commune, puis **« Voir l'aperçu »**.
4. L'aperçu montre chaque personne, son identifiant (modifiable) et signale en
   rouge les lignes à corriger (vide, trop long, caractères bizarres, plus de
   deux mots sans `;`). Une personne déjà présente dans la branche est
   décochée : cochez-la si vous voulez vraiment un second compte.
5. **« Créer les comptes cochés »**. 100 personnes au maximum par envoi.

**c. Transmettre les mots de passe**

L'écran de résultat affiche, pour chaque personne, un message prêt à envoyer :

```
Bonjour Élise, voici ton accès au site de la famille :
Adresse : https://histoiredefamille.eu
Identifiant : elise.martin
Mot de passe : k7fd-3mqp-9xte
(Tu pourras le changer dans « Mon compte ».)
```

- **« Copier le message »** copie celui de la personne ; **« Tout copier »**
  copie la série entière. Il ne reste qu'à coller dans un SMS, WhatsApp ou un
  courriel.
- L'adresse du message vient du réglage **Administration → Réglages → Adresse
  publique du site**.
- **Les mots de passe ne sont plus affichés après cet écran.** Ils ne sont
  enregistrés nulle part en clair : ni dans la base, ni dans le journal
  d'activité, ni dans les fichiers de log.

**d. Quelqu'un a oublié son mot de passe ?**

1. **Administration → Comptes**, retrouvez la personne (recherche par nom, ou
   filtre par branche).
2. **« Nouveau mot de passe »** : un mot de passe provisoire s'affiche une
   seule fois, avec le même bouton **« Copier le message »**. L'ancien ne
   fonctionne plus.

La personne peut aussi changer son mot de passe elle-même : menu
**« Mon compte »**, à côté de « Déconnexion » (ancien mot de passe, nouveau
mot de passe de 10 caractères minimum, confirmation).

**e. Modifier, désactiver, supprimer**

- **Modifier** : nom, prénom, identifiant, branche et « voit toutes les
  branches ».
- **Désactiver** : la personne ne peut plus se connecter, et sa session en
  cours est fermée à la page suivante (rien n'est supprimé).
- **Supprimer** : le compte disparaît ; ses messages du tchat restent lisibles
  avec la signature qu'ils avaient.

Toutes ces actions sont enregistrées dans le journal d'activité (qui a créé,
modifié, réinitialisé ou désactivé quel compte), **sans jamais y écrire un mot
de passe**.

### 6.4 Changer les textes et les couleurs du journal

**Administration → Apparence du journal** (menu de gauche, juste sous
« Réglages »). Tout se règle sur une seule page, avec un aperçu du bandeau
en haut qui suit la saisie.

- **Les textes de la une** : le texte du milieu du bandeau (« Édition des
  familles réunies »), celui de droite (« Prix : un sourire »), la devise, le
  « Fondée en » et le titre du site. La date, elle, reste automatique. Un
  champ vidé reprend le texte d'aujourd'hui.
- **Le numéro d'édition** : « Automatique » (le numéro de la semaine),
  « Texte libre » (« N° 12 », « Hiver 2026 »…) ou « Aucun ».
- **Le pied de page** et la **phrase d'accueil** de la page publique.
- **Les titres des encarts** (« Petites annonces », « Courrier des lecteurs »,
  « Les éditions », « Accès rapides », « En chiffres », « À la une »,
  « À lire aussi ») : chacun peut être renommé.
- **Quatre cases** pour afficher ou masquer : le bandeau du haut, la devise,
  le « Fondée en » et le numéro d'édition.
- **Le titre en gothique** : une case. Décochée, le titre s'écrit en Old
  Standard TT gras, en petites capitales. Si le fichier de la police
  gothique n'est pas sur le serveur, le site le dit et utilise le repli.

**Les couleurs.** Trois possibilités :

1. **Papier ancien** (par défaut) : le site tel qu'il est aujourd'hui.
2. **Bleu d'origine** : la palette claire d'avant le design « gazette »,
   avec un fond uni (ni halo ni ombre de papier).
3. **Couleurs personnalisées** : quatre couleurs seulement — le papier,
   le texte, l'accent (liens, boutons, titres) et l'encre (bordures). Le site
   calcule tout le reste : panneaux, textes gris, ombres, messages d'erreur.

Le site **vérifie les contrastes** avant d'enregistrer, et refuse une
combinaison illisible avec un message clair, par exemple :

> Le texte choisi (#cccccc) est trop proche du papier (#f3e7ce) :
> contraste 1,3 pour 1 (il faut 7 pour 1).

Un tableau sous les couleurs affiche les quatre contrastes et se met à jour
pendant la saisie. Si le refus arrive quand même, le bouton **« Corriger pour
moi »** rapproche juste ce qu'il faut le texte et l'accent : vous n'avez rien
à calculer.

Le bouton **« Rétablir les valeurs d'origine »** (avec une confirmation) remet
tous les réglages de cette page comme au premier jour.

**Comment ça marche, en une phrase.** Le site écrit une petite feuille de
style à part (`/theme.css`) qui ne contient que des couleurs vérifiées ; elle
est chargée après la feuille principale, et seulement si vous avez choisi une
autre palette. Tant que vous ne touchez à rien, elle ne change rien.

**Sans JavaScript**, la page fonctionne quand même : les champs, les cases et
les boutons sont un simple formulaire ; seuls l'aperçu et le calcul des
contrastes en direct ne s'animent pas.

### 6.5 Composer les pages d'accueil

**Administration → Composition des pages** (menu de gauche, juste sous
« Apparence du journal »). Deux pages se règlent ici : l'accueil public
(`/`, visible par tous) et l'espace famille (`/famille`, après connexion).

**Afficher, masquer, monter, descendre.** Chaque bloc de la page est listé avec
trois boutons :

- **↑ Monter** et **↓ Descendre** : le bloc change de place **dans sa
  colonne**, jamais de colonne ;
- **Masquer** (ou **Afficher**) : le bloc disparaît de la page, ou revient.

Ce sont de vrais boutons : **aucun JavaScript n'est nécessaire**. Un bloc
masqué **n'est pas calculé du tout** — le site ne va même pas chercher les
vidéos ou les annonces, ce qui allège le Raspberry Pi. Les trois entrées de
l'accueil (Parcourir les documents, Voir l'arbre, Espace famille) restent
toujours affichées : ce sont les portes du site.

Les blocs de **l'espace famille** :

| Colonne | Blocs |
| --- | --- |
| Principale | Article à la une, Articles en colonnes |
| Colonne de droite | Petites annonces, Les éditions, Courrier des lecteurs, Accès rapides, En chiffres |
| Toute la largeur | Dernières vidéos, Albums photos, Pages d'information, Derniers documents, Personnes récemment mises à jour |

Ceux de **l'accueil public** : les trois entrées, « Également publié », les
chiffres, le mot sur l'espace réservé, « À la une » (les articles), les
derniers documents, « À lire » (les pages d'information) et l'arbre.

**Réglages de la une.** Sur la même page :

- **Articles en colonnes** : de 3 à 12 (4 aujourd'hui). L'article le plus
  récent s'affiche en grand, les suivants en colonnes.
- **Longueur du résumé** : court, moyen ou long.
- **Photo de l'article à la une** : affichée ou non.
- **Modèle d'article par défaut** : voir plus bas.

**Les modèles d'article.** Chaque article peut avoir sa présentation, choisie
dans le formulaire d'article (champ « Modèle de mise en page ») :

- **Journal** : la mise en page d'aujourd'hui (chapô, photo, texte en deux
  colonnes sur grand écran) ;
- **Simple** : une seule colonne, sans ornement, pour un texte court ;
- **Grande photo** : la photo en pleine largeur, puis le texte ;
- **Comme le site** : suit le modèle par défaut choisi dans cette page.

Un article qui n'a rien choisi garde la présentation d'aujourd'hui : **rien ne
change tant que vous ne touchez à rien**.

Le bouton **« Rétablir la composition d'origine »** (avec une confirmation)
réaffiche tous les blocs dans leur ordre d'origine et remet les réglages de la
une comme au premier jour. Les articles, les photos et les droits ne sont pas
touchés.

### 6.6 Ajouter une vidéo

Deux façons, selon la taille du fichier :

**a. Petit film (moins de 50 Mo, la limite d'envoi du navigateur)**

1. **Administration → Vidéos → Ajouter une vidéo**.
2. Choisir le fichier MP4, saisir un titre, éventuellement une description,
   une date, une durée, une catégorie.
3. Cocher **Mettre en avant** pour la placer en tête de l'onglet Vidéos,
   et régler l'**ordre d'affichage** (0 = pas de classement).
4. Décider si le bouton **Télécharger** est proposé.
5. Choisir la **visibilité** — comme pour un document.

**b. Gros film (plusieurs centaines de Mo)**

Voir **[`deploy/dossier-arrivee.md`](deploy/dossier-arrivee.md)** et le § 9
ci-dessous. Résumé :

```bash
# depuis votre ordinateur, sur le réseau de la maison
scp film-famille.mp4 votre-utilisateur@histoiredefamille.local:/mnt/ssd/histoiredefamille/arrivee/
```

puis **Administration → Vidéos**, section « Depuis le dossier arrivée »
(ou **Importer depuis le dossier arrivée** pour les autres formats). Le
fichier est **déplacé** sur le disque, jamais chargé en mémoire : un film
volumineux passe sans problème.

### 6.7 Remplir l'arbre généalogique

**Administration → Arbre** :

1. Créer les personnes (nom, prénom, dates, lieux, notes, photo, visibilité).
2. Créer les **couples** (deux personnes + dates de mariage).
3. Pour chaque personne, indiquer les parents (liste déroulante).

L'arbre se consulte sur `/arbre` (vue par défaut) ou depuis la fiche d'une
personne. Le déplacement dans l'arbre est fait par un petit script maison,
sans bibliothèque externe.

### 6.8 Écrire un article

**Administration → Articles → Écrire un article** :

1. **Titre**, puis l'**adresse** de la page. Laissez-la vide : elle est
   fabriquée automatiquement à partir du titre (utile pour un lien à envoyer
   par courriel).
2. **Rubrique** (« Souvenirs », « Nouvelles »…) : elle sert de filtre sur la
   page `/articles`.
3. **Résumé** (affiché dans les listes et en haut de l'article) et **texte**,
   avec la même mise en forme légère que les pages d'information
   (`## titre`, `**gras**`, `*italique*`, `- liste`, `[lien](adresse)`).
4. **Image à la une** (facultatif) : une photo déjà envoyée sur le site.
5. **Visibilité** : qui a le droit de lire l'article.
6. **Statut** :
   - *Brouillon* : invisible pour tout le monde sauf les administrateurs.
   - *Publié* : visible selon la visibilité, et seulement à partir de la
     **date de publication**.
7. **Date de publication** : laissez vide pour « maintenant ». En mettant une
   date future, l'article est **programmé** : il apparaîtra tout seul ce
   jour-là, sans rien faire. Il reste en aperçu pour les administrateurs.

> Un article publié **public** est visible par des visiteurs qui ne font pas
> partie de la famille. C'est le seul contenu du site dans ce cas (avec les
> pages et les documents marqués « Public »).

### 6.9 Pages d'information

**Administration → Pages** : texte simple, avec quelques mises en forme
(`## titre`, `**gras**`, `*italique*`, `- liste`, `[lien](adresse)`), une
visibilité par page. Exemple de contenu : l'histoire de la famille, les
annonces de réunion, une page « qui contacter ».

### 6.10 Utiliser le tchat

1. Se connecter avec l'identifiant de sa branche (ou son compte
   administrateur), puis cliquer sur **Tchat** dans le menu.
2. La première fois, indiquer son **prénom** : les messages seront signés
   « BRANCHE/Prénom » (par exemple « MARTIN/Jean »). Pour le changer plus tard,
   ouvrir « Changer mon prénom » en haut de la page.
3. Écrire dans la zone du bas, puis **Envoyer** (ou la touche Entrée ;
   Maj+Entrée ajoute une ligne).
4. La conversation se met à jour toute seule. Quand on remonte dans les
   anciens messages, un bouton prévient de l'arrivée de nouveaux messages.
5. Un administrateur peut supprimer un message fautif : le journal d'activité
   garde la trace de la suppression.

Réglages utiles (Administration → Réglages, bloc « Tchat de la famille ») :

- **Ouvrir le tchat** : décoché, le salon disparaît du menu et personne ne peut
  plus lire ni écrire. Les messages restent en base.
- **Conservation des messages** : « 1 an » par défaut. Les messages plus
  anciens sont effacés lors de la tâche d'entretien hebdomadaire
  (`app/cli/maintenance.php`, déjà installée par `deploy/install.sh`).

> Le tchat n'est **pas** un service de messagerie instantanée : les messages
> arrivent avec quelques secondes de décalage, et rien n'est envoyé par
> courriel ou par notification. C'est volontaire : aucun service à maintenir
> sur le Raspberry Pi.

### 6.11 Ranger des photos dans un album

1. Envoyer d'abord les photos normalement (Administration → Documents).
2. Ouvrir **Administration → Albums photos → Nouvel album** : titre,
   description, visibilité, puis cocher les photos à ranger.
3. Choisir la photo de couverture, puis **Créer l'album**.
4. Pour nommer les personnes d'une photo : Administration → Documents →
   bouton **Personnes** sur la ligne de la photo, puis taper un nom et
   **Ajouter**. Le nom apparaît sous la photo, dans l'album et sur la fiche de
   la personne.

### 6.12 Suivre le calendrier et la frise

- **Calendrier** : le mois en cours s'affiche ; les flèches permettent de
  revenir au mois précédent ou d'aller au suivant. Les dates des personnes
  doivent comporter un jour et un mois pour apparaître.
- **Frise** : les périodes de cinquante ans se parcourent avec « ← » et « → ».
  Les documents datés y figurent aussi : une photo de 1928 apparaît en 1928.
- Pour corriger une date, la modifier sur la fiche de la personne
  (Administration → Arbre généalogique) : le calendrier et la frise suivent
  automatiquement.

### 6.13 Envoyer un témoignage sonore ou écrire une recette

- **Témoignage** : Administration → Documents → **Ajouter un document**, choisir
  un fichier MP3, M4A ou OGG, puis, sur la ligne du document, le bouton
  **Personnes** pour indiquer qui parle. L'onglet **Témoignages** apparaît tout
  seul dans le menu.
- **Recette** : Administration → **Recettes** → Nouvelle recette. Remplir le
  titre, les ingrédients (un par ligne) et la préparation ; choisir une photo
  déjà envoyée si on veut. La recette s'imprime avec le bouton **Imprimer**.

### 6.14 Chercher dans le site

1. Cliquer sur **Recherche** dans le menu.
2. Taper un nom, un mot ou une année, puis **Chercher**. Par exemple
   « martin », « mariage 1928 » ou « Bègles ».
3. Les résultats arrivent groupés par catégorie (documents, articles, pages
   d'information, personnes). Cliquer sur « Tout », « Documents », « Articles »,
   « Pages d'information » ou « Personnes » pour n'en voir qu'une.
4. Sur la fiche d'une personne, **Chronologie** rassemble les grands moments de
   sa vie, et « Autres personnes portant le nom … » mène aux homonymes.

Rien à régler : l'index se met à jour à chaque modification. Même sur une base
de plusieurs milliers de personnes, une recherche répond en moins d'une seconde
sur le Raspberry Pi.

---

### 6.15 Choisir ce que voit un visiteur

Rien à cocher : le site s'en occupe.

- Un visiteur non connecté ne voit que **trois entrées** : documents, arbre et
  connexion. Les autres onglets apparaissent **seulement** quand leur rubrique
  contient au moins un élément public (un article publié et daté d'aujourd'hui
  ou du passé, une vidéo publique, un album public, une personne publique avec
  des dates).
- Pour faire apparaître une rubrique : publier quelque chose dedans en
  visibilité « **Public** » (Administration → Articles / Vidéos / Albums, ou
  l'arbre pour le calendrier). Pour la faire disparaître : repasser ce contenu
  en « Famille » ou « Privé ».
- Le tchat reste toujours privé : il n'apparaît jamais dans le menu public.
- Un compte de famille ou d'administration garde, lui, **tout** le menu.

### 6.16 Vérifier qui s'est connecté

Une fois par semaine, ou dès qu'une alerte apparaît sur le tableau de bord :

1. ouvrez le tableau de bord, puis **Journal des connexions** dans le menu de
   gauche ;
2. regardez les lignes en rouge : ce sont les tentatives refusées. Un échec de
   temps en temps est normal (mot de passe tapé trop vite) ;
3. une **suite d'échecs sur un compte** ou une connexion d'administrateur
   depuis une adresse inattendue : changez le mot de passe de ce compte
   (Branches familiales pour un compte de famille, votre compte pour un
   administrateur) ;
4. cliquez sur « **J'ai vu** » sur le tableau de bord pour retirer l'alerte.

Réglages utiles, dans la même page : « Échecs refusés » / « Adresses
nouvelles » pour filtrer, et la durée de conservation du journal.

## 7. Mettre à jour le site

Les mises à jour ne touchent **ni** `config.local.php`, **ni** le SSD, **ni**
la clé USB.

```bash
cd /home/votre-utilisateur/histoiredefamille
git pull
sudo bash deploy/install.sh
```

`install.sh` recopie le code dans `/var/www/histoiredefamille`, réapplique les
migrations SQL (les nouvelles seulement, sans rien effacer) et recharge Apache.

Si une migration échoue, la base n'est pas modifiée à moitié : chaque
migration est appliquée dans une transaction. La sauvegarde de la nuit
précédente reste disponible sur la clé USB.

Sans passer par `install.sh`, la seule commande à connaître pour une nouvelle
version est celle des migrations (elles sont numérotées, appliquées une seule
fois, et n'effacent jamais de données) :

```bash
php app/cli/migrate.php
```

Après une mise à jour, les petits tests automatiques (mise en forme des
messages du tchat, préparation du texte cherché, chronologie d'une fiche de
personne, albums, calendrier, GEDCOM, recettes, journal des connexions,
rubriques publiques, menu et comptes de la famille) se lancent en une
commande. Le nom d'une famille
peut être ajouté pour ne lancer qu'elle (`php app/cli/tests.php rubriques`) :

```bash
cd /home/votre-utilisateur/histoiredefamille
php app/cli/tests.php
```

Chaque test affiche `ok`, un résumé s'affiche à la fin ; rien n'est modifié
dans la base ni dans les dossiers.

---

## 8. Sauvegarde et restauration

### 8.1 Ce qui est sauvegardé

Une seule commande fait tout :

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php
```

Elle est lancée **automatiquement tous les jours à 3 h 30** par le minuteur
systemd `histoiredefamille-sauvegarde.timer` (installé par `deploy/install.sh`).
Vous pouvez la relancer à la main quand vous voulez : elle ne recopie que ce
qui a changé.

Ce qu'elle fait, dans l'ordre :

1. **Vérifie que la clé USB est montée.** Si la clé est absente, elle s'arrête
   et **n'écrit rien du tout** : sans cette précaution, la sauvegarde
   atterrirait sur la carte SD et la remplirait.
2. **Copie la base** avec l'API de SQLite (`VACUUM INTO`) : la copie est
   cohérente même pendant que le site tourne — **jamais `cp`**, qui donnerait
   un fichier abîmé. Le résultat est contrôlé (`PRAGMA integrity_check`) avant
   d'être conservé.
3. **Copie les fichiers** (photos, miniatures, vidéos, audio, PDF, dossier
   `arrivee`) de façon incrémentale : seuls les fichiers nouveaux ou modifiés
   sont réécrits. `tmp/` et `verrous/` sont ignorés (fichiers jetables).
4. **Range la copie** : 7 sauvegardes **quotidiennes**, 4 **hebdomadaires**
   (une par semaine) et 6 **mensuelles** (une par mois). Une sauvegarde en
   cours est fabriquée dans un dossier temporaire puis déplacée d'un bloc :
   l'ancienne reste intacte jusqu'au bout.
5. **Écrit un journal** dans `/mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log`
   et un fichier d'état lu par la page **État du site**.
6. **Affiche un résumé clair** (« Base : ok, 2,4 Mo ; fichiers : 312 copiés,
   0 erreur ; durée 41 s ») et **termine en erreur** (code de sortie non nul)
   si quelque chose n'a pas fonctionné, pour que systemd le signale.

> **`config.local.php` n'est pas dans la sauvegarde.** Ce fichier contient les
> chemins et les réglages de sécurité du site ; il ne change pas. Gardez-en une
> copie **à part**, par exemple dans votre gestionnaire de mots de passe ou sur
> une feuille imprimée. Sans lui, un nouveau Raspberry Pi ne saurait pas où
> sont les données.

> **La clé USB doit avoir une capacité supérieure au volume des données.**
> Base + photos + vidéos doivent tenir dessus, avec de la marge. Pour 50 Go de
> vidéos, prenez une clé de 64 Go minimum.

Vérifier que le minuteur est bien en place :

```bash
systemctl list-timers histoiredefamille-sauvegarde.timer
systemctl status histoiredefamille-sauvegarde.service
journalctl -u histoiredefamille-sauvegarde.service -n 30
```

La dernière commande affiche le compte rendu de la dernière nuit.

### 8.2 Tester la sauvegarde (à faire une fois par mois)

Le plus simple : ouvrir **Administration → État du site**. La page indique la
date et le résultat de la dernière sauvegarde, l'espace libre sur la clé et sur
le disque des données.

En ligne de commande :

```bash
# 1. lancer une sauvegarde et lire le résumé
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php

# 2. regarder les fichiers produits
ls -lh /mnt/sauvegarde/histoiredefamille/base | tail -5
ls -lh /mnt/sauvegarde/histoiredefamille/hebdo /mnt/sauvegarde/histoiredefamille/mensuel
du -sh /mnt/sauvegarde/histoiredefamille/fichiers

# 3. vérifier que la dernière copie est lisible (ne modifie rien)
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --verifier
```

Chaque archive porte la date et l'heure de son début : `histoire-2026-11-03_033000.sqlite.gz`.
La page **État du site** rappelle aussi combien de copies sont conservées dans
chaque dossier (quotidiennes, hebdomadaires, mensuelles), et la date du dernier
contrôle de restauration.

### 8.3 Restaurer (en cas de perte du SSD ou de fausse manœuvre)

La restauration est aussi une commande, et elle est **prudente** : par défaut
elle ne remplace rien, elle reconstitue la sauvegarde dans un **dossier
séparé** pour que vous puissiez regarder avant.

**Étape 1 — vérifier la sauvegarde (ne modifie rien, 10 secondes) :**

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --verifier
```

**Étape 2 — essai dans un dossier séparé (2 à 10 minutes) :**

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille
```

Le résultat s'écrit dans `/mnt/ssd/restauration-AAAA-MM-JJ_HHMM` :

```
restauration-2026-11-03_1530/
├── histoire.sqlite    la base restaurée
├── storage/           les photos, vidéos et documents restaurés
└── LISEZ-MOI.txt      ce qu'il faut faire ensuite
```

**Rien n'a été remplacé.** Le site continue de fonctionner normalement.

**Étape 3 — remplacer le site (10 à 30 minutes selon le volume) :**

```bash
# le site doit être arrêté
sudo systemctl stop apache2

sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --remplacer
```

La commande affiche ce qu'elle va faire, puis demande :

```
Pour confirmer, tapez OUI puis Entrée (autre chose annule) :
```

Tapez **OUI** en majuscules. Autre chose annule tout : le site n'est pas
modifié. Avant de remplacer, elle :

- vérifie l'intégrité de la sauvegarde (`PRAGMA integrity_check`) ;
- **copie l'ancienne base** dans `/mnt/sauvegarde/histoiredefamille/avant-restauration/AAAA-MM-JJ_HHMMSS/` ;
- **déplace** (sans les effacer) les fichiers qui n'existent pas dans la
  sauvegarde vers `…/avant-restauration/…/fichiers-retires/` ;
- réinstalle la base et les fichiers, puis affiche le nombre de lignes par table.

Puis redémarrez et vérifiez :

```bash
sudo systemctl start apache2
curl -I http://localhost/
```

> **Rien n'est jamais effacé** : l'ancienne base et les fichiers retirés restent
> sur la clé. Si vous vous êtes trompé de sauvegarde, vous pouvez revenir en
> arrière en rejouant la même commande avec le dossier
> `avant-restauration/AAAA-MM-JJ_HHMMSS`.

> La page **État du site** rappelle la date du dernier contrôle de restauration
> (commande `--verifier`) : si elle date de plus de six mois, refaites-en un.

### 8.4 Que faire si…

Cinq pannes, de la plus grave à la plus bénigne. Pour chacune : ce qu'il faut
avoir sous la main, les commandes, le temps approximatif.

#### La carte SD est morte (le Pi ne démarre plus, ou s'arrête au logo)

> C'est la panne la plus fréquente sur un Raspberry Pi. **Le SSD et la clé ne
> sont pas concernés** : photos, vidéos et base sont dessus.

**À avoir sous la main :** une carte microSD neuve (32 Go suffisent), le
lecteur de cartes, un ordinateur, la clé USB de sauvegarde, et une copie de
`config.local.php` (gestionnaire de mots de passe ou feuille imprimée).

**Temps : 1 h 30 environ** (dont 20 min de copie de l'image système).

```bash
# 1. sur votre ordinateur : écrire Ubuntu Server sur la carte neuve
#    (Raspberry Pi Imager : choisir Ubuntu Server 24.04 LTS 64 bits,
#     régler le nom d'hôte, l'utilisateur et le Wi-Fi)

# 2. remettre la carte dans le Pi, démarrer, puis se connecter en SSH
ssh votre-utilisateur@IP-DU-PI

# 3. remonter le SSD et la clé (voir deploy/montage-disques.md)
sudo mkdir -p /mnt/ssd /mnt/sauvegarde
sudo mount -a
df -h /mnt/ssd /mnt/sauvegarde        # les deux doivent apparaître

# 4. récupérer le code et installer
cd /home/votre-utilisateur
git clone https://github.com/VOTRE-COMPTE/histoiredefamille.git
cd histoiredefamille
sudo bash deploy/install.sh

# 5. remettre votre configuration (celle gardée de côté)
sudo nano /var/www/histoiredefamille/config.local.php

# 6. restaurer les données depuis la clé
sudo systemctl stop apache2
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --remplacer      # taper OUI
sudo systemctl start apache2

# 7. vérifier
curl -I http://localhost/
```

#### Le SSD est mort (le site répond, mais les photos manquent ou la base est illisible)

**À avoir sous la main :** un SSD USB neuf, la clé de sauvegarde,
`config.local.php`.

**Temps : 1 h environ** (dont la copie des fichiers).

```bash
sudo systemctl stop apache2

# 1. brancher le SSD neuf, le monter (deploy/montage-disques.md)
lsblk -o NAME,SIZE,MODEL        # repérer le disque
sudo mkdir -p /mnt/ssd
# … voir deploy/montage-disques.md pour l'entrée /etc/fstab définitive

# 2. recréer les dossiers de données
sudo mkdir -p /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous}
sudo chown -R www-data:www-data /mnt/ssd/histoiredefamille

# 3. restaurer la base ET les fichiers depuis la clé (taper OUI)
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --remplacer

sudo systemctl start apache2
```

#### Un document a été supprimé par erreur

**Temps : 10 minutes**, et le site peut rester ouvert.

```bash
# 1. restaurer dans un dossier séparé (rien n'est remplacé)
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille

# 2. retrouver le fichier : il est dans
#    /mnt/ssd/restauration-AAAA-MM-JJ_HHMM/storage/medias/…

# 3. si c'est une photo ou un document, il suffit de le réimporter par
#    l'administration (Documents → Ajouter). Sinon, refermez le dossier :
sudo rm -rf /mnt/ssd/restauration-AAAA-MM-JJ_HHMM
```

Si c'est le **texte** qui a été vidé (article, page, fiche de personne),
l'import ne suffit pas : remontez à hier (§ ci-dessous) ou copiez le texte
depuis la base restaurée.

#### Remonter à hier (fausse manœuvre : suppression en masse, mauvais import)

**Temps : 20 minutes.** Vous perdez les modifications faites depuis la
sauvegarde de la nuit précédente (3 h 30).

```bash
sudo systemctl stop apache2
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille --remplacer      # taper OUI
sudo systemctl start apache2
```

Pour choisir une sauvegarde **plus ancienne** (par exemple celle d'il y a trois
jours), indiquez son fichier au lieu du dossier :

```bash
ls -1t /mnt/sauvegarde/histoiredefamille/base/
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php \
    /mnt/sauvegarde/histoiredefamille/base/histoire-2026-11-01_033000.sqlite.gz --remplacer
```

> La restauration d'un fichier ne réinstalle que la base : les fichiers de
> `fichiers/` (photos, vidéos) restent ceux de la clé, ce qui est voulu.

#### Changer de Raspberry Pi (pièce de rechange, déménagement, don)

**À avoir sous la main :** le nouveau Pi, la carte SD du nouveau Pi (ou
l'ancienne si elle est saine), le SSD et la clé. **Temps : 1 h à 2 h.**

1. Sur le nouveau Pi, installez Ubuntu Server et le site (§ 4.1 à 4.6 du
   README) — **inutile de créer un administrateur**, la base restaurée contient
   déjà les comptes.
2. Remontez le SSD et la clé (`deploy/montage-disques.md`).
3. Si le SSD a changé de nom (`/dev/sda` → `/dev/sdb`), utilisez les **UUID**
   dans `/etc/fstab` : c'est déjà ce que recommande le guide.
4. Restaurez la base et les fichiers (taper OUI) :
   `sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php /mnt/sauvegarde/histoiredefamille --remplacer`
5. Remettez `config.local.php`, le tunnel Cloudflare (§ 5), puis vérifiez que
   le site s'ouvre (§ 5.6).
6. Vérifiez que le minuteur de sauvegarde est actif :
   `systemctl list-timers histoiredefamille-sauvegarde.timer`.

#### Le Pi répond mais la page affiche une erreur 500

Ce n'est pas une panne de données. Regardez le journal avant de restaurer :

```bash
sudo tail -50 /var/log/apache2/histoiredefamille-error.log
sudo systemctl status apache2
df -h /mnt/ssd /mnt/sauvegarde        # disque plein ?
```

Une restauration ne se justifie que si la base est réellement abîmée
(`sqlite3 … 'PRAGMA integrity_check;'` renvoie autre chose que `ok`).

### 8.5 Alertes en cas d'échec

Deux mécanismes, tous les deux consultables sans ligne de commande :

1. **Un bandeau rouge en haut de l'administration** dès que la dernière
   sauvegarde réussie date de **plus de 3 jours**, qu'elle est **en échec**, ou
   que **la clé USB n'est pas branchée**. Le bandeau renvoie vers la page
   « État du site ».
2. **Le journal** : `/mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log`,
   recopié dans `journalctl -u histoiredefamille-sauvegarde.service`.

La page **État du site** donne le détail : date, résultat, taille de la base,
nombre de fichiers copiés, espace libre sur la clé et sur le disque des
données, nombre de copies conservées.

### 8.6 Chiffrer la clé USB (facultatif)

Une clé perdue, c'est toute l'histoire de la famille lisible par un inconnu.
Le chiffrement LUKS protège contre cela, mais **le mot de passe devient la clé
de tout** : oublié, les sauvegardes sont perdues.

Avantages, risques et commandes : **[docs/chiffrement-cle-usb.md](docs/chiffrement-cle-usb.md)**.
Ne chiffrez pas la clé si personne ne peut retrouver le mot de passe.

### 8.7 Surveiller l'espace disque

```bash
df -h /mnt/ssd /mnt/sauvegarde
du -sh /mnt/ssd/histoiredefamille/medias
```

La page **État du site** affiche aussi l'espace libre du SSD.

### 8.8 Repartir d'une base vierge (développement seulement)

> ⚠️ Efface tous les documents, comptes et personnes.

```bash
sudo systemctl stop apache2
sudo rm -f /mnt/ssd/histoiredefamille/histoire.sqlite*
sudo -u www-data php /var/www/histoiredefamille/app/cli/migrate.php
sudo -u www-data php /var/www/histoiredefamille/app/cli/create-admin.php
sudo systemctl start apache2
```

Les fichiers de `medias/` restent sur le disque mais ne sont plus référencés
par la base ; supprimez-les si vous voulez vraiment tout effacer.

---

## 9. Vidéos : convertir avant de déposer

Le site **ne réencode pas** les vidéos : un Raspberry Pi mettrait des dizaines
d'heures à convertir un film. Le fichier déposé doit donc déjà être lisible
par les navigateurs :

- conteneur **MP4**, vidéo **H.264**, audio **AAC**, option « faststart ».

Avec HandBrake (interface graphique) : préréglage **Fast 1080p30**, audio AAC
160 kb/s, cocher **Web Optimized**.

Avec ffmpeg :

```bash
ffmpeg -i film-original.avi \
  -c:v libx264 -preset slow -crf 22 -profile:v high -level 4.0 -pix_fmt yuv420p \
  -c:a aac -b:a 160k -ac 2 \
  -movflags +faststart \
  film-famille.mp4
```

Compter 1,5 à 2 Go par heure de film en 720p (le bon compromis pour un Pi).

Ensuite, déposez le fichier sur le Pi et importez-le :
**[`deploy/dossier-arrivee.md`](deploy/dossier-arrivee.md)**.

### Limite de lectures simultanées

Deux lectures vidéo en même temps au maximum (réglable dans
`config.local.php`, clé `video.max_concurrent_streams`). Au-delà, la page de
la vidéo affiche « **Trop de personnes regardent des vidéos, réessayez dans
une minute** » et le flux répond 503 avec l'en-tête `Retry-After`. C'est volontaire : le Pi doit rester
capable d'afficher les pages et l'arbre pendant qu'un film est regardé.

Les verrous sont automatiquement libérés au bout de deux minutes sans
activité (nettoyage aussi par la tâche `maintenance.php` du dimanche).

---

## 10. Arbre généalogique et GEDCOM

### 10.1 Importer un fichier GEDCOM

1. **Administration → Arbre → Importer un fichier GEDCOM**.
2. Choisir le fichier `.ged` (25 Mo maximum).
3. Le site affiche un **aperçu** : nombre de personnes et de familles
   détectées, et la liste des anomalies tolérées (dates illisibles, personnes
   sans nom, liens vers des personnes absentes…).
4. Après validation, les personnes sont créées en une seule transaction.

Règles appliquées à l'import :

- les données incomplètes sont **tolérées** : un prénom seul, une date
  approximative (`vers 1850`, `avant 1900`) ou un lieu absent ne bloquent pas ;
- les personnes importées sont visibles par **« Toutes les familles »** par
  défaut, jamais « Public » — c'est une protection contre une fuite
  involontaire de données familiales ;
- les doublons exacts (même nom, même prénom, même année de naissance) sont
  signalés dans l'aperçu ;
- rien n'est écrit tant que vous n'avez pas confirmé.

### 10.2 Compléter après l'import

L'import ne devine pas tout. Après coup, dans **Arbre** :

- vérifier les photos (elles ne viennent pas du GEDCOM) ;
- corriger les dates affichées `?` ;
- créer les couples manquants ;
- ajuster la visibilité des personnes récentes (les personnes nées il y a
  moins de 100 ans restent en « Toutes les familles »).

### 10.3 Exporter l'arbre (GEDCOM)

**Administration → Arbre généalogique → Exporter GEDCOM** télécharge un fichier
`histoire-famille-AAAA-MM-JJ.ged`, lisible par Gramps, Geneanet, MyHeritage,
Ancestry et la plupart des logiciels de généalogie. Il contient **toutes** les
fiches, y compris privées : c'est pourquoi l'export est réservé aux
administrateurs. Le fichier est écrit au fur et à mesure, sans jamais occuper
toute la mémoire du Pi.

Les dates sont converties au format international de la généalogie
(« 14 mars 1901 » devient « 14 MAR 1901 », « vers 1930 » devient « ABT 1930 »).

### 10.4 Conseils de saisie

- Un seul lieu par champ, tel qu'il apparaît sur l'acte (« Saint-Jean-de-Luz (64) »).
- Pour les femmes mariées, saisir le **nom de naissance** ; indiquez le nom
  d'usage dans les notes.
- Utiliser les notes pour les anecdotes : c'est souvent le plus émouvant.

---

## 11. En cas de problème

| Symptôme | Cause probable | Que faire |
|---|---|---|
| Page blanche ou erreur 500 | erreur PHP | `sudo tail -50 /var/log/apache2/histoiredefamille-error.log` |
| « Site en cours d'installation » | `config.local.php` absent | `sudo cp /var/www/histoiredefamille/config.example.php /var/www/histoiredefamille/config.local.php` |
| « Base non initialisée » | migrations non lancées | `sudo -u www-data php /var/www/histoiredefamille/app/cli/migrate.php` |
| `database is locked` | écritures simultanées | le site réessaie déjà 3 fois ; vérifier qu'aucune autre application n'ouvre la base et que le SSD n'est pas monté en lecture seule |
| Erreur 502 / 1033 via Cloudflare | tunnel arrêté ou Apache arrêté | `sudo systemctl status cloudflared` puis `sudo systemctl restart cloudflared` ; `sudo systemctl status apache2` |
| Le site est très lent | Pi surchargé, vidéo en cours | normal avec 2 lectures vidéo ; fermer un film, vérifier `htop` |
| Envoi de photo refusé « fichier trop volumineux » | plus de 50 Mo | réduire la photo, ou augmenter `uploads.max_mb` **et** `upload_max_filesize` / `post_max_size` (max 90 Mo à cause de Cloudflare) |
| Miniatures noires ou vides | image CMYK ou très grande | convertir en JPEG RVB ; au-delà de 24 Mpx le site refuse (limite mémoire du Pi) |
| Mot de passe de branche perdu | — | Administration → Branches → **Réinitialiser le mot de passe** |
| Compte bloqué après 5 essais | protection anti-force brute | attendre 15 minutes, ou `sudo -u www-data sqlite3 /mnt/ssd/histoiredefamille/histoire.sqlite "DELETE FROM login_attempts;"` |
| Page « Site momentanément indisponible » | Apache répond 503 (surcharge, redémarrage) ou PHP est arrêté | `sudo systemctl status apache2` ; la page est `public/maintenance.html`, modifiable librement |
| Sauvegarde en échec | clé USB absente ou pleine | `df -h /mnt/sauvegarde`, rebrancher, relancer `sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php` |
| Le Pi ne démarre plus | disque monté dans `fstab` et absent | `nofail` doit être présent ; sinon démarrer sans le disque et corriger `/etc/fstab` |
| Vidéo qui ne démarre pas | format non compatible | reconvertir en MP4 H.264 + AAC (§ 9) |

Commandes de diagnostic utiles :

```bash
# état des services
systemctl status apache2 cloudflared

# journaux du site
sudo tail -50 /var/log/apache2/histoiredefamille-error.log
sudo tail -50 /var/log/apache2/php-histoiredefamille-error.log

# espace disque et mémoire
df -h
free -m
htop

# base de données
sudo -u www-data sqlite3 /mnt/ssd/histoiredefamille/histoire.sqlite 'PRAGMA integrity_check;'
sudo -u www-data sqlite3 /mnt/ssd/histoiredefamille/histoire.sqlite 'SELECT COUNT(*) FROM documents;'

# version de PHP et extensions
php -v
php -m | grep -E 'sqlite|gd|mbstring|fileinfo'
```

---

## 12. Confidentialité et sécurité

### Le dépôt Git est public

Il contient **uniquement du code** :

- aucun mot de passe, aucune clé, aucun secret ;
- aucune photo, aucun document, aucune donnée familiale réelle ;
- les noms de famille des données de démonstration sont **fictifs**.

Le **tchat** est stocké dans la même base, sur le SSD : les messages ne sont
lisibles que par des personnes connectées (page, flux de mise à jour et envoi
vérifient toutes la session), jamais depuis l'extérieur du site.

Vos données vivent exclusivement sur le Raspberry Pi, dans
`/mnt/ssd/histoiredefamille`. La configuration locale (`config.local.php`) et
le dossier `storage/` sont listés dans `.gitignore`.

### Ce que le code applique

- **Mots de passe** : `password_hash` (Argon2id si disponible, sinon bcrypt
  coût 12) ; jamais de mot de passe en clair, jamais de mot de passe dans un
  fichier.
- **Mots de passe provisoires** (comptes de la famille) : affichés **une seule
  fois**, dans une page qui interdit la mise en cache (`Cache-Control:
  no-store`), jamais dans une adresse web, jamais dans un cookie, jamais dans
  le journal d'activité. Seule l'empreinte Argon2id est enregistrée : un mot
  de passe oublié ne se retrouve pas, il se **réinitialise**.
- **Comptes personnels** : une page « Mon compte » permet de changer son mot
  de passe (ancien mot de passe exigé, 10 caractères minimum, refus si le mot
  de passe est l'identifiant) ; l'identifiant de session est régénéré aussitôt.
- **Comptes désactivés** : la connexion est refusée **et** la session en cours
  est fermée à la page suivante.
- **Formulaires** : jeton CSRF sur toutes les actions qui modifient quelque
  chose ; message « Session expirée » si le jeton manque.
- **Base de données** : requêtes préparées PDO partout (aucune concaténation
  de SQL avec une valeur saisie).
- **Affichage** : tout est échappé avec `htmlspecialchars` (via la fonction
  `e()`), y compris les noms venant d'un GEDCOM et les messages du tchat.
  Dans le tchat, seules les adresses `http`/`https` deviennent des liens,
  toujours avec `rel="noopener noreferrer"`.
- **Sessions** : cookie `HttpOnly` + `Secure` + `SameSite=Lax`, identifiant
  régénéré à la connexion, expiration après 4 h d'inactivité et 7 jours au
  maximum.
- **Connexion** : 5 échecs puis blocage 15 minutes, par identifiant **et** par
  adresse IP. L'en-tête `CF-Connecting-IP` de Cloudflare n'est pris en compte
  que si la requête arrive de la boucle locale (`127.0.0.1`), c'est-à-dire du
  tunnel : sinon n'importe qui sur le réseau de la maison pourrait le forger
  pour échapper au blocage par IP.
- **Envois** : stockés hors de la racine web, type réel vérifié avec `finfo`,
  liste blanche d'extensions, nom aléatoire, nom d'origine conservé seulement
  en base, jamais exécutables.
- **Fichiers privés** : jamais accessibles par URL directe ; tout passe par
  `/f/{id}`, `/media/miniature/{id}` ou `/video/{id}` avec contrôle des droits
  et en-tête `Cache-Control: private, no-store`.
- **En-têtes** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Content-Security-Policy` (aucune ressource externe, aucun script en ligne),
  `Permissions-Policy`.
- **Couleurs choisies** : la feuille `/theme.css` ne contient que des valeurs
  validées par le serveur (codes `#RRGGBB` et couleurs calculées à partir
  d'elles). Aucun texte saisi n'y entre, donc aucune injection CSS n'est
  possible ; les contrastes trop faibles sont refusés à l'enregistrement.
- **Apache** : `.git`, `.env`, `config*.php`, `*.sqlite`, `*.bak`, `*.log` sont
  bloqués, y compris le dossier `app/` (hors racine web) et le contenu du SSD.
- **SQLite** : mode WAL, `busy_timeout` 5000 ms, 3 tentatives espacées de
  300 ms sur les écritures sensibles (la cause du « database is locked » vue
  sur un autre site).
- **Recherche** : index plein texte SQLite (FTS5) tenu à jour par des
  déclencheurs. Si la version de SQLite du serveur ne sait pas le faire, le
  code bascule tout seul sur une recherche plus lente mais correcte (`GLOB`).
  Dans les deux cas, seuls les contenus autorisés pour la personne connectée
  sont cherchés, et rien ne quitte le serveur.

### Ce que vous devez faire de votre côté

- Ne jamais committer `config.local.php` (le fichier est déjà dans `.gitignore`).
- Ne jamais copier `TUNNEL-ID.json` (dans `/root/.cloudflared/`) ailleurs.
- Utiliser un mot de passe différent pour chaque compte administrateur.
- Transmettre les mots de passe de branche par un moyen discret (appel,
  message privé), pas dans un groupe public.
- Garder le Pi à jour : `sudo apt-get update && sudo apt-get upgrade`.

### Signaler un problème

Le site est un projet familial. En cas de doute sur une page qui affiche un
contenu de trop, commencez par vérifier la visibilité du document dans
**Administration → Documents** : c'est presque toujours là qu'est la cause.

---

## 13. Ouvrir le site au public : liste de contrôle du jour J

Cette liste se suit **dans l'ordre**, sans sauter d'étape. Chaque étape indique
le temps à prévoir, ce qu'il faut avoir sous la main et la commande exacte.
**Si une étape ne se passe pas comme prévu, arrêtez-vous** : réglez-la avant de
passer à la suivante.

À avoir sous la main : le Raspberry Pi allumé et connecté à la box, le SSD, la
clé USB, un ordinateur avec un navigateur et un téléphone en 4G (pour l'essai
depuis l'extérieur).

> Toutes les commandes se font sur le Raspberry Pi, en SSH :
> `ssh votre-utilisateur@histoiredefamille.local`.
> Le code installé est dans `/var/www/histoiredefamille` ; c'est le dossier de
> travail du serveur web. Les commandes qui écrivent dans les données se
> lancent avec `sudo -u www-data` (l'utilisateur du site), sinon les fichiers
> risquent d'appartenir au mauvais compte.

### 1. Monter le SSD et la clé USB — 10 min

**Matériel** : SSD, clé USB, les deux câbles.

Les deux disques doivent apparaître, et **séparément** (deux lignes, deux
tailles différentes) :

```bash
df -h /mnt/ssd /mnt/sauvegarde
```

Le guide détaillé (fstab, droits, UUID) est ici :
[`deploy/montage-disques.md`](deploy/montage-disques.md).

⚠️ Tant que cette commande n'affiche pas deux lignes distinctes, **ne
continuez pas** : les données iraient sur la carte SD, et la sauvegarde avec
elles.

### 2. Déplacer les données sur le SSD — 5 min + le temps de copie

**Matériel** : rien de plus.

Si le site tournait déjà sur la carte SD, copiez la base et les fichiers vers
le SSD, puis indiquez les nouveaux chemins dans `/var/www/histoiredefamille/config.local.php` :

```php
'database' => '/mnt/ssd/histoiredefamille/histoire.sqlite',
'storage'  => [ 'root' => '/mnt/ssd/histoiredefamille', /* … */ ],
```

```bash
sudo systemctl reload apache2
```

Vérifiez ensuite que le site s'ouvre toujours : une page blanche ou une erreur
503 signifie que le chemin est faux.

### 3. Créer trois administrateurs — 10 min

**Matériel** : les noms des trois personnes de confiance.

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/create-admin.php
```

La commande demande un identifiant et un mot de passe : rien ne s'affiche
pendant la saisie, c'est normal. Répétez pour les trois personnes. Un seul
administrateur ne suffit pas : en cas d'oubli de mot de passe ou d'absence, plus
personne ne pourrait gérer le site.

### 4. Créer les comptes de la famille — 5 min par branche

**Matériel** : la liste des personnes et de leur branche.

Dans **Administration → Branches & comptes**, créez une branche par famille,
puis les comptes. Chaque personne reçoit son identifiant et un mot de passe
provisoire, à changer à la première connexion.

### 5. Mettre `https_only` à `true` — 2 min

**Matériel** : rien.

Dans `/var/www/histoiredefamille/config.local.php` :

```php
'https_only' => true,
```

```bash
sudo systemctl reload apache2
```

Cela met le drapeau « Secure » sur le cookie de session et active HSTS : le
navigateur refuse toute connexion non chiffrée.

### 6. Ouvrir le tunnel Cloudflare — 15 min

**Matériel** : l'accès au compte Cloudflare du domaine.

Suivez [5. Mettre le site en ligne (Cloudflare)](#5-mettre-le-site-en-ligne-cloudflare),
puis vérifiez depuis un téléphone en 4G que l'adresse du site répond.

### 7. Lancer la vérification — 2 min

**Matériel** : rien.

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/verifier-mise-en-ligne.php
```

La commande passe tout en revue et rend un verdict :

- **`PRÊT À OUVRIR`** : vous pouvez continuer ;
- **`PAS PRÊT : N point(s) à corriger`** : chaque ligne `À CORRIGER` indique
  **ce qui ne va pas et quoi faire** (la flèche `→`). Corrigez, puis relancez
  la commande jusqu'à obtenir `PRÊT À OUVRIR`.

Les lignes `INFO` sont des informations : elles ne bloquent pas.

Ce qui est contrôlé, et pourquoi :

| Contrôle | Ce que cela évite |
|---|---|
| HTTPS obligatoire, adresse publique en `https://` | un mot de passe qui circule en clair |
| Aucun contenu marqué « (démo) », aucun compte fictif | ouvrir le site avec des données qui ne sont pas les vôtres |
| Deux administrateurs actifs au moins, aucun mot de passe d'essai connu | un site que personne ne peut plus administrer |
| `config.local.php` en 600 ou 640, base et données hors de `public/` | des données familiales téléchargeables par n'importe qui |
| `public/` sans fichier sensible (`.git`, base, sauvegarde, configuration) | la même chose, par un chemin détourné |
| PHP 8.1 ou plus, extensions SQLite, GD, mbstring, fileinfo, sodium/Argon2id | des photos sans miniature, des mots de passe mal protégés |
| Clé montée et inscriptible, dernière sauvegarde de moins de 3 jours, minuteur actif, test de restauration daté | découvrir le problème le jour où l'on a besoin de la sauvegarde |
| Espace libre sur le SSD et sur la clé | un site qui tombe en panne parce que le disque est plein |
| Purge planifiée | des tables de journal qui grossissent sans fin |
| En-têtes HTTP réellement envoyés par Apache (CSP, X-Frame-Options, nosniff, HSTS) et signature masquée | une page piégée qui lit les données de la famille |
| Cookies `Secure`, `HttpOnly`, `SameSite` | un cookie volé par un script |
| `CF-Connecting-IP` ignoré hors du tunnel | un visiteur qui falsifie son adresse IP pour contourner le blocage après 5 essais |

Le résultat est enregistré pour la page **Administration → État du site**, qui
l'affiche en lecture seule (aucune commande n'est exécutée depuis le
navigateur). Pour l'actualiser, relancez la commande.

### 8. Faire la première sauvegarde — 2 min

**Matériel** : la clé USB branchée.

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php
```

Le résumé doit afficher une base « ok », les fichiers copiés et zéro erreur.
Vérifiez ensuite que le minuteur est actif :

```bash
systemctl list-timers histoiredefamille-sauvegarde.timer
```

La ligne doit indiquer la prochaine exécution (tous les jours à 3 h 30).

### 9. Tester la restauration — 5 min

**Matériel** : rien.

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/restauration.php /mnt/sauvegarde/histoiredefamille --verifier
```

Cette commande **ne touche à rien** : elle relit la dernière sauvegarde, vérifie
qu'elle s'ouvre et compte les lignes de chaque table. Une sauvegarde jamais
relue n'est pas une sauvegarde.

### 10. Ouvrir — 5 min

**Matériel** : un téléphone en 4G.

1. Ouvrez l'adresse du site depuis le téléphone (hors du Wi-Fi de la maison).
2. Connectez-vous avec un compte de la famille, puis avec un compte
   administrateur.
3. Vérifiez qu'une page non autorisée renvoie bien « page introuvable »
   (par exemple un document privé, avec un compte de branche).
4. Dans **Administration → État du site**, l'encadré « Vérification avant
   ouverture » doit afficher `PRÊT À OUVRIR`, et aucune alerte rouge ne doit
   apparaître en haut de l'administration.

C'est fait. Envoyez l'adresse et les identifiants à la famille.

### Si quelque chose ne va pas

| Symptôme | Où regarder |
|---|---|
| Le site ne répond pas du tout | `sudo systemctl status apache2` et `sudo systemctl status cloudflared` |
| Erreur 503 « installation à terminer » | `config.local.php` absent ou chemins faux (étapes 2 et 5) |
| Alerte rouge « sauvegarde » en haut de l'administration | `journalctl -u histoiredefamille-sauvegarde.service -n 50` |
| La vérification dit « Apache ne répond pas » | Apache n'est pas lancé, ou le site n'écoute pas sur `127.0.0.1:80` |
| Un point reste « À CORRIGER » | la flèche `→` de la ligne donne la commande ou le réglage à faire |

---

## Licence

Code privé, réservé à l'usage familial. Aucune donnée de tiers n'est incluse.
Les contenus de démonstration (`app/cli/demo.php`) sont entièrement fictifs.
