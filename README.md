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
9. [Vidéos : le site convertit tout seul](#9-vidéos--le-site-convertit-tout-seul)
10. [Arbre généalogique et GEDCOM](#10-arbre-généalogique-et-gedcom)
11. [En cas de problème](#11-en-cas-de-problème)
12. [Confidentialité et sécurité](#12-confidentialité-et-sécurité)
13. [Ouvrir le site au public : liste de contrôle du jour J](#13-ouvrir-le-site-au-public--liste-de-contrôle-du-jour-j)
14. [Envoyer des courriels](#14-envoyer-des-courriels)
15. [La gazette du mois](#15-la-gazette-du-mois)
16. [La carte des lieux](#16-la-carte-des-lieux)

---

## 1. Ce que le site sait faire

### Partie publique (visible par tous)

- Une page d'accueil qui est **la Une du journal** (1.31.0) : le bandeau de la
  famille, un gros bouton **Se connecter**, puis **tout le contenu public du
  site** — articles, photos, vidéos, documents, recettes, pages d'information —
  du plus récent au plus ancien. Le plus récent tient la une, en grand ; les
  autres suivent en grille, regroupés par rubrique dès qu'ils sont plus de six.
  Le menu d'un visiteur ne contient que **« Accueil »** et **« Se connecter »**
  (le bouton mène à `/connexion`).
  Une fois connecté, la page **commence par la une** de la famille : l'article
  du jour dans le premier écran, puis le bloc **« Dans la famille »** — dans une
  colonne à droite sur grand écran, juste dessous sur téléphone (voir plus bas).
  **L'arbre généalogique et la carte ne sont pas publics** : ils s'ouvrent une
  fois connecté.
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
  articles, les pages d'information et les personnes.
- Les pages d'information publiques (histoire de la famille, annonces).
- Une **carte des lieux** (naissance, décès, mariage), dessinée par le site
  lui-même : aucun service externe, aucune connexion à Internet, aucun
  JavaScript. Chaque point mène à la liste des personnes rattachées à ce lieu.
  Elle n'apparaît dans le menu que si au moins un lieu a été placé.

### Partie famille (après connexion)

- Tout ce que voit le public, **plus** les documents réservés à la famille ou
  à une branche précise, et **l'arbre généalogique**.
- Sur la page d'accueil, un bloc **« Dans la famille »**, strictement dans les
  droits du compte : anniversaires des trente prochains jours, événements du
  calendrier à venir, personnes ajoutées récemment, documents et articles
  parus depuis la dernière visite. Bloc vide : il n'est pas affiché.
- Un **compte Lecteur** (identifiant en majuscules, un mot de passe partagé
  par les membres de cette branche) ou un **compte Journaliste** (identifiant
  `prenom.nom`, un mot de passe par personne, et la possibilité de proposer
  des contenus, publiés après validation d'un administrateur). Le compte
  journaliste signe le tchat à son nom.
- Une page **« Mon compte »** (menu, à côté de « Déconnexion ») pour changer
  son propre mot de passe.
- Un **tchat** privé : un seul salon commun à toutes les branches et aux
  administrateurs. Les messages sont signés « BRANCHE/Prénom » et ne sont
  visibles que des personnes connectées. Aucune application à installer :
  tout se passe dans le navigateur.

### Administration (3 comptes personnels)

- Un **menu de gauche rangé par métier**, pas par partie du site : *Contenus*
  (articles, pages, documents, albums, vidéos, recettes, catégories), *Envois*,
  *Personnes* (arbre, comptes, branches, administrateurs, et « lieux »
  **seulement si la carte est active**), *Journal* (gazette, apparence,
  composition, **calendrier**, et « courriels » **seulement si l’envoi est
  réactivé** — voir § 14) et *Système*, avec « À valider » et son compteur en
  tête.
- Un **tableau de bord en une carte « Contenus »** : documents, articles,
  pages, vidéos, personnes, comptes et propositions à valider, plus une ligne
  « dont publics : N » et des raccourcis vers les listes déjà filtrées sur
  « Public ».
- Documents : envoi par le navigateur (photos, PDF, sons, documents bureautiques
  jusqu'à 50 Mo), titre, description, date, catégorie, étiquettes, **visibilité**,
  modification et suppression.
- **Envoyer des fichiers** : une page dédiée aux **gros fichiers** (vidéos,
  scans, lots de photos), **jusqu'à 4 Go** et depuis n'importe où. Le navigateur
  découpe le fichier en morceaux de 5 Mo ; si la connexion tombe, l'envoi
  **reprend** là où il s'était arrêté, même après avoir fermé la page. Le
  fichier arrive **privé** et attend sa fiche (une à une, ou tout un lot d'un
  coup). Rien n'est jamais chargé en mémoire sur le Pi.
- **Albums photos** : création, rangement des photos (aucune copie de fichier),
  ordre d'affichage, photo de couverture, visibilité.
- **Personnes identifiées** sur une photo (ou un témoignage) : recherche dans
  l'arbre et association en deux clics.
- **Recettes de famille** : création, modification, suppression, photo
  facultative, visibilité.
- **Export GEDCOM** de tout l'arbre en un clic (Arbre généalogique →
  Exporter GEDCOM).
- **Catégories** (créées par les administrateurs) : depuis la 1.32.0, une
  catégorie s’applique à un ou plusieurs **types** de contenu (article,
  document, photo, album, vidéo, recette), avec un **ordre d’affichage** ; un
  contenu peut en porter plusieurs, et chaque liste a son filtre par catégorie.
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
- **Conversions** : la file d'attente des vidéos converties en MP4 (H.264 +
  AAC) en arrière-plan, une seule à la fois. État, avancement, nombre d'essais,
  message d'erreur clair, **Relancer** et **Annuler**. La famille ne voit une
  vidéo qu'une fois la conversion terminée (§ 9).
- **Import depuis le dossier arrivée** : pour les fichiers (vidéos) déposés
  sur le Pi par le réseau local, quand l'envoi par le navigateur ne convient pas.
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
- **Un article sans photo n'affiche aucun cadre** (1.32.0) : ni carré gris, ni
  lettre de remplacement, ni emplacement vide — la mise en page se réorganise
  autour du texte. C'est vrai partout : accueil, « À la une », listes,
  recherche, gazette, page de l'article et impression. Les trois gabarits
  (*Journal*, *Simple*, *Grande photo*) restent inchangés ; un article réglé en
  « Grande photo » qui n'a pas d'image retombe simplement sur « Journal », sans
  image.
- **Sur l'accueil public** (1.32.1), la règle va plus loin : un contenu sans
  image donne une **carte texte seule** dans « Dans le journal » — titre, date,
  extrait, et **rien d'autre** : aucune tuile grise, aucun bloc de rubrique,
  aucune hauteur réservée. Une page d'information et une photo sans vignette
  suivent la même règle. Dans « À la une », le texte prend **toute la largeur**
  quand il n'y a pas d'image : il ne reste pas de colonne vide à droite. Une
  **vidéo sans miniature** garde le repère de lecture de la 1.32.0 (▶ sur le
  papier du site) au lieu d'un carré vide.
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
  - **Étiquette de souvenir (1.34.0)** : au-dessus du titre d'un article et
    sur ses cartes, la décennie ne s'affiche que si l'article raconte un
    souvenir daté (une année dans son titre ou son résumé), et sous la forme
    « Souvenir des années 1950 ». Un article d'actualité publié aujourd'hui
    n'affiche rien : fini le « Années 2020 » qui se lisait comme une date de
    publication erronée. La ligne « Par … — date » ne change pas.
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
- Grille de cartes : **image d'aperçu**, titre, date, durée, court résumé.
  Depuis la 1.32.0, l'image est **extraite de la vidéo** par ffmpeg (à une
  seconde du début, ou à 10 % de la durée si le film est plus court), puis
  réduite comme les autres miniatures ; elle est fabriquée **à la fin de
  l'envoi**, ou de la conversion pour une vidéo reconvertie. Sans ffmpeg, ou si
  l'extraction échoue, la carte affiche une **icône de lecture** sobre — jamais
  un carré gris — et la page **Conversions** dit pourquoi. La miniature suit
  **exactement** la visibilité de la vidéo : une miniature privée répond la même
  page « introuvable » que la vidéo. Un administrateur peut aussi **envoyer une
  image de couverture** depuis la fiche de la vidéo.
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

- **Compte Journaliste** (compte personnel) : la signature est automatique,
  c'est le prénom du compte — « **MARTIN/Élise** » —, sans rien demander à la
  connexion.
- **Compte Lecteur** (compte de branche) : au premier passage, on indique le **prénom** qui
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
- Un administrateur connecté trouve sur la page d'un album, et sur sa page
  d'administration, le bouton **Ajouter des photos** : il envoie les fichiers
  par morceaux et les range directement dans cet album (voir § 6.19), ou y
  verse des photos **déjà présentes** sur le site (voir § 6.20).

### Calendrier familial et frise chronologique

Deux façons de traverser les dates de la famille, ouvertes à tout le monde.

- **Calendrier** (`/calendrier`) : une grille mensuelle, toutes années
  confondues. Chaque jour signale les anniversaires qui y tombent, avec le
  détail sous la grille : « Anniversaire de Luc MARTIN (63 ans) »,
  « Anniversaire de mariage de Jean MARTIN et Marie LEROY (98 ans) ».
  Les dates de décès n'y figurent que si un administrateur a coché
  « Afficher les dates de décès dans le calendrier »
  (Administration → Réglages ; décochée par défaut), sous le libellé sobre
  « In memoriam : Prénom NOM ». Les flèches changent de mois.
  En haut de la page, un administrateur trouve le bouton
  « Ajouter un événement » (un journaliste y voit « Proposer un événement ») :
  formulaire simple — titre, date, type, visibilité privée par défaut, note.
  Une proposition de journaliste reste invisible partout tant qu'un
  administrateur ne l'a pas validée dans Administration → Calendrier
  (bouton « Valider » sur la ligne, colonne « État »).
- **Frise** (`/frise`) : les mêmes événements, plus les documents datés, année
  par année, par périodes de cinquante ans (1900-1949, 1950-1999…).
- Les dates sont lues telles que la famille les a écrites : « 12 juin 1928 »,
  « 12/06/1928 », « 1928-06-12 », « 1er février 1975 », « juin 1928 » ou
  simplement « vers 1930 ». Une date sans jour ni mois ne peut pas entrer dans
  le calendrier : elle reste dans la frise, à son année.
- Depuis la 1.32.0, le calendrier affiche aussi de **vrais événements de
  famille** saisis dans *Administration → Calendrier* : une fête, une réunion,
  un voyage, une messe du souvenir. Chacun a un titre, une date de début, une
  heure facultative, une fin facultative, un lieu en texte libre, une
  description, une **visibilité** et, s'il le faut, une **répétition annuelle**
  (il revient alors chaque année à la même date). Le point du jour et le détail
  sous la grille le signalent comme « événement de famille ».
- Comme partout, un visiteur ne voit que les événements des personnes et des
  documents auxquels il a droit ; un mariage n'apparaît que si les deux époux
  sont visibles. Un événement créé dans l'administration est **privé par
  défaut** : personne ne le voit tant que l'administrateur n'a pas choisi
  « Famille », « Branches précises » ou « Public ».
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
- Trois profils, et seulement trois :
  - **Lecteur** : le compte partagé d'une branche (un seul mot de passe pour
    les cousins qui n'ont qu'un identifiant à retenir). Il **lit** ce que sa
    branche a le droit de voir et ne peut **rien** écrire ni proposer, sauf
    dans le tchat et « Mon compte » ;
  - **Journaliste** : un compte personnel (`elise.martin`), avec son propre mot
    de passe et son prénom sur le tchat. Il lit avec les droits de sa branche,
    et il peut **proposer** des articles, documents, photos et recettes — qui
    ne sont publiés qu'après validation d'un administrateur ;
  - **Administrateur** : tout, et c'est lui qui valide.
- Seul un administrateur peut créer des comptes : les administrateurs
  eux-mêmes se créent en ligne de commande (`php app/cli/create-admin.php`).
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
les adresses du site et ce que chacune répond aux quatre profils : visiteur,
lecteur (branche A), journaliste (branche B), administrateur.

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
│   │                        vérification avant ouverture (mise_en_ligne),
│   │                        carte des lieux, envoi de gros fichiers (envoi),
│   │                        conversion des vidéos (conversion), données de
│   │                        démonstration et purge de ces données (demo),
│   │                        adresses affichées et réglage des courriels
│   │                        (contacts), événements du calendrier
│   │                        (evenements), miniatures des vidéos (images)…
│   ├── views/               gabarits PHP (public, famille, admin)
│   ├── migrations/          fichiers SQL numérotés
│   │                        (001_init … 021_categories_types)
│   └── cli/                 commandes : migrate, create-admin, demo, purger-demo,
│                            maintenance, sauvegarde, restauration,
│                            verifier-mise-en-ligne, convertir (vidéos),
│                            miniatures-videos (images d'aperçu), envoyer-courriels,
│                            tests
├── deploy/
│   ├── install.sh           installation complète sur le Pi
│   ├── systemd/             minuteurs : sauvegarde (3 h 30), courriels
│   │                        (5 min), conversion des vidéos (10 min)
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
├── docs/arbre-chene.md      l'arbre gravé de /arbre (1.33.0)
├── docs/chiffrement-cle-usb.md  chiffrer la clé de sauvegarde (facultatif)
├── CHANGELOG.md             historique des versions
└── TODO.md                  ce qui reste à faire
```

Autour du dossier du site :

```
/mnt/ssd/histoiredefamille/      ← SSD : base, photos, vidéos, miniatures
    histoire.sqlite
    medias/  miniatures/  arrivee/  tmp/  verrous/  envois/
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
4. crée `config.local.php` à partir du modèle, en `640 root:www-data` — et,
   s'il existe déjà, **conserve ses droits** ou les remet à `640 root:www-data`
   s'ils étaient plus larges (relancer l'installation n'ouvre jamais la
   configuration à tout le monde) ;
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

Le fichier appartient à `root` et se lit avec le groupe `www-data` (`640`) : il
reste ainsi hors de portée des autres comptes de la machine, et relancer
`deploy/install.sh` ne change ni ses droits ni son contenu.

Les seules lignes à vérifier :

```php
'site' => [
    'name'        => 'Histoire de la famille Martin',   // le titre affiché
    'subtitle'    => 'Nos souvenirs, nos photos, notre histoire',
    'url'         => 'https://histoiredefamille.eu',
    'description' => 'Les souvenirs de la famille : documents, photos, récits et nouvelles.',
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
curl -sI http://localhost/
```

Réponse attendue : `HTTP/1.1 200 OK`, puis **une seule ligne par en-tête de
sécurité** (voir [7. Lancer la vérification](#7-lancer-la-vérification--2-min) :
`curl -sI http://localhost` sert aussi à contrôler qu'aucun n'est envoyé deux
fois). Ouvrez aussi la page d'état (après connexion) : **Administration →
État du site**.

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
personnes n'ont aucun rapport avec votre famille.

**Retirer la démonstration avant d'ouvrir le site** — sans toucher aux
comptes, ni à vos propres fiches, ni à vos fichiers :

```bash
cd /var/www/histoiredefamille
# 1. Voir ce qui serait retiré (ne supprime rien)
sudo -u www-data php app/cli/purger-demo.php --liste
# 2. Supprimer, après avoir lu la liste
sudo -u www-data php app/cli/purger-demo.php --oui
```

La commande ne supprime que les contenus **créés par `demo.php`** : elle les
reconnaît au registre laissé à l'installation
(`storage/demo-registry.json`, recopié dans les réglages du site pour survivre
à une reconstruction du dossier `storage`) et, à défaut, au mot « (démo) »
écrit dans leurs titres. Les documents partent avec leurs fichiers et leurs miniatures ;
les fichiers encore utilisés ailleurs sont conservés. **Les comptes ne sont
jamais supprimés** : la commande les compte et vous renvoie vers
*Comptes* pour les désactiver. Avant d'effacer quoi que ce soit elle lance une
**sauvegarde** ; si la sauvegarde échoue (clé absente, disque plein), **rien
n'est supprimé** et la commande s'arrête avec un message clair.

Pour tout effacer, y compris les comptes, voir « Repartir d'une base vierge »
au § 8.8.

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
   description, une date, **une ou plusieurs catégories** et des étiquettes.
3. Choisir la **visibilité** — c'est le point à ne pas oublier.
4. Enregistrer.

Le fichier est renommé avec un nom aléatoire et rangé hors de la racine web :
il n'est jamais accessible par une URL directe. Toutes les lectures passent
par un script PHP qui vérifie les droits.

Une **miniature** est créée automatiquement pour les photos (480 px de côté
maximum) : les listes restent rapides même sur un petit écran et sur le Pi.

**Les catégories, depuis la 1.32.0.** Une catégorie ne sert plus seulement aux
documents : *Administration → Catégories* indique, pour chacune, les **types de
contenu** auxquels elle s'applique — **article, document, photo, album, vidéo,
recette** — et son **ordre d'affichage**. Une catégorie neuve vaut pour les six
(ses cases sont cochées au départ) ; décocher un type la retire des formulaires
de ce type. Un contenu peut porter **plusieurs catégories** : les cases
« Catégories » des formulaires ne proposent que celles prévues pour son type.
Sur *Envoyer des fichiers* et *Ajouter des photos à un album*, les catégories
cochées s'appliquent à **tout le lot**. Chaque liste — publique ou
d'administration — a un filtre « Catégorie » en formulaire `GET`
(`/documents?categorie=3`, `/videos?categorie=3`, `/recettes?categorie=3`,
`/albums?categorie=3`, `/articles?categorie_id=3`), et le **compteur** affiché
ne compte que les contenus que **vous** pouvez voir : un visiteur ne peut donc
pas déduire l'existence d'un contenu privé par le nombre affiché, et une
catégorie dont rien ne lui est visible n'apparaît pas du tout. La migration
`021_categories_types.sql` recopie les catégories déjà posées sur les
documents : **aucune n'est perdue**, et l'ancienne colonne
`documents.category_id` reste tenue à jour pour les écrans qui la lisent.

Formats acceptés : `pdf`, `txt`, `jpg`, `jpeg`, `png`, `webp`, `gif`, `mp4`,
`m4v`, `mov`, `avi`, `mkv`, `webm`, `3gp`, `docx`, `xlsx`, `odt`, `ods`, `mp3`,
`m4a`, `ogg`. Le type réel du fichier est vérifié : renommer un fichier n'aide
pas à contourner la règle.

### 6.2 Créer une branche familiale

1. **Administration → Branches → Nouvelle branche**.
2. Nom de la branche (`MARTIN`), nom complet (« Famille Martin »), cocher
   « voit toutes les branches » si nécessaire.
3. Un mot de passe est généré : **notez-le tout de suite**, il ne sera plus
   affiché. En cas d'oubli, utilisez le bouton **Réinitialiser le mot de passe**.

### 6.3 Créer les comptes de la famille

Il y a **trois profils**, et seulement trois :

| Profil | Qui l'utilise | Identifiant | Peut écrire ? |
|---|---|---|---|
| **Lecteur** | une famille entière, avec un seul mot de passe partagé | en majuscules : `MARTIN` | non (sauf le tchat) |
| **Journaliste** | une personne, avec son propre mot de passe | `prenom.nom` : `elise.martin` | propose, un administrateur valide |
| **Administrateur** | le ou les gestionnaires du site | selon création | oui, tout |

Les trois lisent la même chose : les documents publics, ceux ouverts à toutes
les familles, et ceux de leur branche. Un journaliste ne publie jamais
directement : ses propositions attendent la validation d'un administrateur.

**Les propositions (1.23.0).** Un journaliste ouvre **Mes propositions** (menu,
ou encart sur son accueil) et dépose un **article**, un **document ou une
photo** (envoi simple, ou par morceaux pour les gros fichiers) ou une
**recette**. Chaque proposition naît **en attente** et **privée** : elle n'est
visible que de son auteur et des administrateurs, nulle part ailleurs (listes,
recherche, fil, carte, gazette, courriels, adresses directes). Le journaliste ne
choisit pas la visibilité : il peut seulement la **suggérer**.

L'administrateur ouvre **Administration → À valider** (compteur dans le menu et
sur le tableau de bord), lit l'aperçu, puis :
- **Valider** — en choisissant la visibilité, **privé par défaut** ; le contenu
  est publié comme s'il avait été créé par un administrateur, et les lecteurs
  sont prévenus par courriel selon leurs préférences (si l'envoi a été
  réactivé : par défaut, le site n'envoie rien, voir § 14) ;
- **Refuser** — avec un **motif obligatoire**, que l'auteur lit dans son espace.

Une proposition se corrige à tout moment : tant qu'elle n'est pas validée, elle
reste « en attente » (ou « refusée », avec son motif) ; si son auteur modifie un
contenu **déjà publié**, celui-ci repasse « en attente » et redevient invisible
pour les autres jusqu'à la prochaine validation.

La page **Comptes → Ajouter un compte** propose le profil **Lecteur** par
défaut ; le champ « Profil » d'un compte existant permet de passer de Lecteur
à Journaliste (et inversement).

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

**Administration → Apparence du journal** (menu de gauche, groupe *Journal*).
Tout se règle sur une seule page, avec un aperçu du bandeau
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

**L'aspect « photo ancienne » (1.32.0).** Un réglage de plus sur cette page :
**Aucun** (le défaut), **Doux** ou **Marqué**. Il donne aux photos un rendu
sépia, de plus en plus appuyé, et s'applique aux vignettes comme aux grandes
images — listes, albums, article à la une, accueil. **C'est un filtre
d'affichage** : le fichier d'origine n'est **jamais** modifié, les
téléchargements et l'impression restent **en couleurs**, et le lien
« **Télécharger l'original** » de la fiche donne bien l'image d'origine. Le
rendu ne passe que par des **classes CSS** (`aspect-photo--doux`,
`aspect-photo--marque`), jamais par un attribut `style` : il ne coûte donc rien
au navigateur et reste compatible avec la règle de sécurité du site. Sur la
fiche d'un document image, trois choix : **Suivre le réglage du site** (le
défaut), **Forcer l'aspect photo ancienne**, **Désactiver** (garder les
couleurs). Un **aperçu en direct** montre le résultat sous les trois boutons ;
sans JavaScript, il montre le réglage déjà enregistré.

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

**Administration → Composition des pages** (menu de gauche, groupe *Journal*,
juste sous « Apparence du journal »). Deux pages se règlent ici : l'accueil public
(`/`, visible par tous) et l'espace famille (`/famille`, après connexion).

**Afficher, masquer, monter, descendre.** Chaque bloc de la page est listé avec
trois boutons :

- **↑ Monter** et **↓ Descendre** : le bloc change de place **dans sa
  colonne**, jamais de colonne ;
- **Masquer** (ou **Afficher**) : le bloc disparaît de la page, ou revient.

Ce sont de vrais boutons : **aucun JavaScript n'est nécessaire**. Un bloc
masqué **n'est pas calculé du tout** — le site ne va même pas chercher les
vidéos ou les annonces, ce qui allège le Raspberry Pi.

Les blocs de **l'espace famille** :

| Colonne | Blocs |
| --- | --- |
| Principale | Article à la une, Articles en colonnes |
| Colonne de droite | Petites annonces, Les éditions, Courrier des lecteurs, Accès rapides, En chiffres |
| Toute la largeur | Dernières vidéos, Albums photos, Pages d'information, Derniers documents, Personnes récemment mises à jour |

Ceux de **l'accueil des comptes connectés** : « Dans la famille », « À la une »
(les articles), les derniers documents et « À lire » (les pages d'information).

**L'accueil d'un visiteur ne se compose pas** : depuis la 1.31.0, c'est la Une
du journal (tout le contenu public, du plus récent au plus ancien). Les quatre
blocs de l'ancien en-tête public (les deux entrées, « Également publié », les
chiffres, « Nous écrire ») ne s'affichaient plus pour personne : ils ont été
retirés de cette page, avec leurs boutons sans effet.

**Un contenu sans image, sur cet accueil** (1.32.1) : la grille ne réserve
**aucune zone d'image**. La carte se réduit au texte — titre, date, extrait —
sans tuile grise, sans cadre et sans bloc de rubrique ; la une, elle, donne
toute la largeur à son texte. C'est un état normal, pas un manque : le journal
d'une famille n'a pas de photo pour tout.

Pour un **compte connecté**, la une passe devant (1.29.0) : une seule ligne de
bienvenue prend place sous la date, et « Dans la famille » suit la une — colonne
de droite à partir de 64 rem, jamais avant. Le bloc montre **cinq lignes** au
plus, avec un lien « Tout voir » vers l'espace famille ; sur téléphone, au-delà
de trois lignes, il est replié dans un `<details>` (aucun JavaScript).

**Réglages de la une.** Sur la même page :

- **Articles en colonnes** : de 3 à 12 (4 aujourd'hui). L'article le plus
  récent s'affiche en grand, les suivants en colonnes.
- **Longueur du résumé** : court, moyen ou long.
- **Photo de l'article à la une** : affichée ou non.
- **À la une du site public** : le contenu que voit un visiteur en grand sur
  l'accueil (1.31.0). La liste ne propose que des contenus publics ;
  « Automatique » — le défaut — garde le plus récent. Si ce contenu cesse
  d'être public, la page reprend le plus récent toute seule.
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
2. Choisir le fichier — MP4, mais aussi le MOV d'un téléphone, un AVI, un MKV
   ou un WebM : tout format que les navigateurs ne lisent pas partout est
   converti automatiquement en MP4 (H.264 + AAC) après l'envoi. Depuis la
   1.35.1, une vidéo d'archive dont le conteneur annonce « que du son »
   (marque M4A dans un fichier .MP4) n'est plus refusée : le site regarde
   les pistes réelles quand ffprobe est là, et sinon la famille du
   conteneur (MP4/QuickTime/3GP, MKV, AVI, MPEG, MPEG-TS, WMV, FLV, VOB…)
   suffit à accepter, la conversion tranchant ensuite. Un fichier qui ne
   contient que du son sous une extension vidéo est refusé avec
   l'explication et quoi faire. Les formats peu courants acceptés
   (.mov, .avi, .wmv, .mkv, .mpg, .mts, .flv, .3gp, .vob…) entrent dans la
   file de conversion ; un MP4 déjà en H.264 + AAC n'y entre pas — un MP4
   en HEVC, si, quand ffprobe peut le vérifier. Saisir ensuite
   un titre, éventuellement une description, une date, une durée, une catégorie.
3. Cocher **Mettre en avant** pour la placer en tête de l'onglet Vidéos,
   et régler l'**ordre d'affichage** (0 = pas de classement).
4. Décider si le bouton **Télécharger** est proposé.
5. Choisir la **visibilité** — comme pour un document.

**b. Gros film (plusieurs centaines de Mo, voire plusieurs Go)** — deux voies :

1. **Depuis n'importe où, par le navigateur** : **Administration → Envoyer des
   fichiers**. Le fichier part par morceaux et l'envoi reprend après une
   coupure. C'est la bonne solution depuis Paris, ou depuis un téléphone ; tout
   est expliqué au § 6.18.
2. **Par le réseau de la maison** : copier le fichier dans le dossier
   `arrivee` du Pi, puis **Administration → Vidéos**, section « Depuis le
   dossier arrivée » (ou **Importer depuis le dossier arrivée** pour les autres
   formats). Voir **[`deploy/dossier-arrivee.md`](deploy/dossier-arrivee.md)**.

   ```bash
   # depuis votre ordinateur, sur le réseau de la maison
   scp film-famille.mp4 votre-utilisateur@histoiredefamille.local:/mnt/ssd/histoiredefamille/arrivee/
   ```

   Le fichier est **déplacé** sur le disque, jamais chargé en mémoire : un film
   volumineux passe sans problème.

### 6.7 Remplir l'arbre généalogique

**Administration → Arbre** :

1. Créer les personnes (nom, prénom, dates, lieux, notes, photo, visibilité).
2. Créer les **couples** (deux personnes + dates de mariage).
3. Pour chaque personne, indiquer les parents (liste déroulante).

L'arbre se consulte sur `/arbre` (vue par défaut) ou depuis la fiche d'une
personne. **Depuis la 1.33.0**, la page est un « **chêne gravé** » :

- la **gravure du chêne** sert de fond au dessin sur grand écran, et de bandeau
  au-dessus sur téléphone ; c'est une image du site (`/assets/arbre-chene.webp`,
  avec un repli `.jpg`), jamais une ressource extérieure ;
- les **ancêtres** sont dans les branches (trois générations : parents,
  grands-parents, arrière-grands-parents), la **personne choisie et son
  conjoint** sur le tronc, les **descendants** dans les racines (deux
  générations : enfants, petits-enfants) ;
- un **sélecteur** en haut de page met au centre la personne de votre choix
  (liste filtrable, ou lien « Arbre » depuis la liste des personnes) ;
- au-delà de **huit personnes** dans une génération, la page n'entasse rien :
  elle affiche « Voir les N autres personnes de la famille », et tout reste
  lisible, sur téléphone comme sur ordinateur ;
- tout est écrit par le serveur, **sans JavaScript** : aucune position en style
  en ligne, seulement des classes de la feuille de style. Le déplacement dans
  l'arbre ne dépend donc d'aucun script, et se fait au clavier comme à la
  souris ;
- **depuis la 1.35.0, chaque carte dit sa relation** avec la personne au
  centre, en petit au-dessus du nom (« Conjointe », « Parent »,
  « Grand-parent », « Arrière-grand-parent », « Enfant », « Petit-enfant »…)
  et dans son nom accessible (« Odile MARTIN, conjointe »). Un conjoint
  n'est jamais présenté comme un frère ou une sœur, et les ancêtres sont
  ceux de la personne sélectionnée — jamais ceux de son conjoint. Un trait
  en pointillés relie les deux membres d'un couple, et les enfants sont
  reliés à leurs parents par des traits dessinés par le serveur ;
- **exploration (1.35.0)** : au-dessus de l'arbre, une barre d'outils permet
  de zoomer (25 % à 250 %), de se déplacer, de **centrer la personne
  sélectionnée** ou de **voir tout l'arbre** ; au doigt et à la souris, on
  glisse pour déplacer, on zoome à la molette ou au pincement. Tout tient
  dans le script `public/assets/arbre-exploration.js`, chargé seulement sur
  cette page : **sans JavaScript, la barre est cachée et l'arbre reste
  entier et cliquable**, comme avant. Le cadre fait environ 65 % de la
  hauteur de l'écran ; la page elle-même ne défile jamais de travers, et
  la touche Tab sort du cadre au clavier.
- **seules les personnes que votre compte a le droit de voir** apparaissent :
  ni nom, ni lien, ni compteur ne trahissent une fiche privée, et une personne
  privée demandée dans l'adresse (`?racine=…`) ne remplace pas la personne par
  défaut.

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

Les **événements de famille** (une fête, une réunion, un voyage) se créent
depuis le bouton « **Ajouter un événement** » en haut de `/calendrier`
(administrateurs), ou dans **Administration → Calendrier** :

1. « **Nouvel événement** » : titre, date de début, **heure facultative**,
   date et heure de fin facultatives, **lieu** en texte libre, description ;
2. une case « **revient chaque année à la même date** » si l'événement se
   répète ;
3. la **visibilité**, **privée par défaut** — choisissez « Famille »,
   « Branches précises » ou « Public » pour qu'il soit vu ;
4. « Créer l'événement ».

La liste montre chaque événement avec sa date, son lieu, sa visibilité et sa
répétition, et permet de le **modifier** ou de le **supprimer**. La suppression
demande **confirmation** dans le navigateur : elle est définitive. Les
événements visibles apparaissent aussi dans le bloc « **Dans la famille** » de
l'accueil des comptes connectés, quand leur date approche.

Un **journaliste** peut proposer un événement depuis `/calendrier`
(« Proposer un événement ») : il naît « en attente », invisible pour tous
les autres comptes — calendrier, accueil, compteurs — et apparaît dans
Administration → Calendrier avec le bouton « **Valider** ». La validation
le publie avec la visibilité proposée, que l'administrateur peut ajuster
avant avec « Modifier ». Depuis la 1.34.0, les événements ont aussi un
**type** (fête, réunion, voyage, messe du souvenir, anniversaire, autre) et
un **statut** (migration 022, en ajout seul : les événements existants
deviennent « publie », rien ne change pour eux).

Sur la fiche d'une personne, aucune ligne vide ne s'affiche plus (1.34.0) :
sans date ni lieu de décès, la ligne « Décès » disparaît — ni tiret, ni
« inconnu » — et les dates de vie ne gardent plus de tiret en attente
(« 1901 » suffit). Une date de décès connue reste affichée telle quelle.

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

- Un visiteur non connecté voit **une seule page** : la Une du journal
  (1.31.0). Elle présente **tout ce qui est public**, du plus récent au plus
  ancien, et son menu ne contient que **« Accueil »** et **« Se connecter »**.
  **L'arbre généalogique, la carte et les fiches de lieu ne sont pas publics** :
  leurs adresses (`/arbre`, `/carte`, `/lieu/{id}`) renvoient vers la connexion.
  Les rubriques publiques qui existent (`/articles`, `/documents`, `/albums`,
  `/videos`, `/recherche`, `/calendrier`, `/recettes`) gardent exactement leurs
  règles d'accès : elles répondent à un visiteur comme avant, simplement elles
  ne sont plus dans son menu.
- Pour faire apparaître une rubrique : publier quelque chose dedans en
  visibilité « **Public** » (Administration → Articles / Vidéos / Albums, ou
  l'arbre pour le calendrier). Pour la faire disparaître : repasser ce contenu
  en « Famille » ou « Privé ».
- Le tchat reste toujours privé : il n'apparaît jamais dans le menu public.
- Un compte de famille ou d'administration garde, lui, **tout** le menu. Son
  accueil est celui de la 1.29.0 : la une d'abord, puis « Dans la famille »
  (anniversaires, événements, personnes ajoutées, nouveautés depuis la dernière
  visite), toujours filtré par ses droits.
- Dans l'administration, chaque liste (articles, pages, documents, albums,
  vidéos, recettes) porte un filtre **« Visibilité »** : *Toutes*, *Public*,
  *Familles*, *Branches*, *Privé*. Le choix reste dans l'adresse
  (`?visibilite=public`), donc il se met en favori et le tableau de bord peut
  y renvoyer directement.
- Les listes d'administration ont aussi, depuis la 1.32.0, un filtre
  **« Catégorie »**, limité aux catégories du type de la liste : le choix reste
  dans l'adresse (`?categorie=3`), les autres filtres sont conservés.

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

### 6.17 Placer les lieux sur la carte

**La carte est désactivée par défaut depuis la 1.32.0.** Pour l'utiliser,
cochez « **Carte des lieux** » dans *Administration → Réglages* : la page
`/carte`, le lien « Carte » des menus et « **Lieux à placer** » réapparaissent
aussitôt, avec les lieux déjà enregistrés (rien n'a été supprimé). Décochée,
`/carte` et `/lieu/{id}` répondent « introuvable » à tout le monde, et
`/admin/lieux` renvoie vers **Réglages**.

La carte ne devine aucun lieu. Un texte écrit dans une fiche (« Toulouse »,
« Saint-Étienne », « le hameau de la Croix ») reste **exactement** comme il est :
la carte lui donne seulement des coordonnées. Tant qu'un texte n'a pas de
coordonnées, il n'apparaît pas sur la carte — la fiche, elle, ne change pas.

1. ouvrez **Lieux à placer** dans le menu de gauche de l'administration (ou
   cliquez sur « Placer les lieux » depuis la page `/carte`) ;
2. la liste du haut montre les textes de lieu des fiches qui attendent une
   position, avec le nombre de fiches concernées. Cliquez sur « **Chercher** »
   à côté d'un lieu ;
3. tapez les premières lettres du nom (deux suffisent), puis cliquez sur
   « **Placer** » sur la bonne commune : c'est fini ;
4. pour un hameau, un lieu-dit ou un village à l'étranger, descendez à
   « **À la main** » : écrivez le nom **exactement comme dans les fiches**,
   puis la latitude et la longitude (par exemple `43.6047` et `1.4442`). Sur une
   carte en ligne, un clic droit sur le lieu donne souvent ces deux nombres ;
5. en bas, « **Lieux déjà placés** » rappelle tout ce qui est sur la carte.
   « **Retirer** » ne modifie aucune fiche : le lieu quitte la carte et revient
   dans « À placer ».

Bon à savoir :

- **rien n'est envoyé sur Internet** : la liste des communes est dans le site,
  la carte est dessinée par le serveur. Elle fonctionne même si le Pi n'a pas
  de connexion sortante ;
- un texte de lieu **inconnu est simplement ignoré** sur la carte. Les fiches
  ne sont jamais réécrites, et un import GEDCOM reste sans effet sur la carte ;
- les accents et les majuscules ne comptent pas : « Saint-Étienne » et
  « SAINT ETIENNE » désignent le même point ;
- **on ne voit que ce qu'on a le droit de voir** : un visiteur ne compte que
  les personnes publiques ; un lieu dont aucune personne ne lui est visible
  n'apparaît pas du tout, et sa fiche répond « Lieu introuvable » ;
- la page propose deux vues (**France** et **Monde entier**), un filtre par
  **branche** et un filtre par **décennie de naissance**, et sous la carte un
  **tableau** qui donne les mêmes renseignements (utile pour imprimer ou avec
  un lecteur d'écran).

La méthode complète (origine du fond de carte, licence, projection, cache) est
décrite dans [`docs/carte.md`](docs/carte.md).

### 6.18 Envoyer de gros fichiers depuis Paris

Un film de vacances, un registre scanné, deux cents photos de mariage : ces
fichiers ne passent pas par le formulaire ordinaire (50 Mo par fichier), et le
dossier « arrivée » demande d'être à la maison, sur le réseau du Pi. La page
**Envoyer des fichiers** est faite pour eux : elle accepte **jusqu'à 4 Go par
fichier** et fonctionne **depuis n'importe où**, par un simple navigateur.

Rien à installer, rien à préparer : le navigateur découpe le fichier en
**morceaux de 5 Mo** et les envoie un par un. Chaque requête reste petite, donc
elle passe le tunnel Cloudflare, le réseau de l'hôtel et le Wi-Fi de la
famille, même lents.

**Comment faire**

1. **Administration → Envoyer des fichiers**.
2. Cliquez sur **Choisir des fichiers** (ou glissez vos fichiers sur la zone
   pointillée). Plusieurs fichiers à la fois : chacun a sa propre barre de
   progression.
3. Laissez l'onglet ouvert pendant l'envoi. Vous pouvez continuer à vous
   servir du site dans un autre onglet.
4. Quand le fichier est reçu, sa fiche est créée **en privé** : personne ne le
   voit. Deux façons de la compléter :
   - **Ouvrir la fiche** (titre, description, date, catégorie, étiquettes,
     visibilité) ;
   - **Compléter ce lot d'un coup** (bouton en bas de la page) : les réglages
     du haut s'appliquent à tous les fichiers cochés (type, catégorie, album,
     branche, visibilité), puis vous ajustez titre, date et visibilité fichier
     par fichier.

**Dans le formulaire d'une vidéo ou d'un document**

Le même bloc d'envoi par morceaux est présent dans les formulaires
**Nouvelle vidéo** et **Nouveau document**, pour un fichier plus gros que la
limite de l'envoi simple. Une fois l'envoi terminé :

- le message nomme le **vrai bouton** : « Envoyé. Le fichier est prêt : cliquez
  sur « Envoyer la vidéo » en bas du formulaire » (ou « Envoyer le
  document ») ;
- un lien **« Aller au bouton d'enregistrement »** amène directement au bouton,
  tout en bas de la page ;
- la case **Fichier** n'est plus obligatoire : le fichier déjà reçu remplace ce
  qu'elle aurait envoyé. Rien à cocher, rien à re-choisir ;
- si le formulaire revient avec une erreur (titre vide, par exemple), le
  fichier reste rattaché : il n'y a pas à le renvoyer.

Côté serveur, le fichier n'est accepté que s'il appartient à l'administrateur
connecté, s'il est terminé et s'il n'a pas déjà servi : un même envoi ne peut
pas être rattaché à deux fiches.

**Fichiers reçus, en attente de formulaire**

Un fichier peut arriver entier sans que le formulaire ait été validé : il
attend, en privé, dans le tableau **Fichiers reçus, en attente de formulaire**
de la page **Envoyer des fichiers**. Chaque ligne offre deux actions :

- **Créer le document** : la fiche naît tout de suite, **en privé**, avec le
  fichier déjà en place — sans repasser par le formulaire. Le titre vient du nom
  du fichier ; le bouton mène directement à la fiche à compléter (au formulaire
  vidéo, s'il s'agit d'un film), où vous choisissez description, date,
  catégorie, étiquettes et visibilité ;
- **Supprimer** : après confirmation, le **fichier assemblé** et les **morceaux**
  reçus sont effacés.

Un fichier reçu ne devient **qu'une seule fois** un document : la réservation
est atomique, donc un double clic ou deux onglets ne peuvent pas créer deux
fiches. Une fois la fiche créée, le fichier lui appartient : c'est depuis la
fiche qu'on le remplace ou qu'on le supprime, et la ligne quitte la liste (le
bouton « Supprimer » ne s'applique qu'aux fichiers encore en attente). Seul le
compte qui a envoyé le fichier peut agir ; les autres administrateurs le voient
dans **Tous les envois**, en lecture seule.

La page **Administration → Mes envois** liste vos envois des 30 derniers jours
et tout ce qui n'est pas terminé, avec le bouton **Reprendre**. Une vue
« Tous les envois » montre ceux de tous les administrateurs, en lecture seule :
chacun ne peut reprendre ou annuler que les siens.

**Si la connexion tombe**

C'est prévu, et c'est le point important de cette page :

- un fichier interrompu (coupure, mise en veille de l'ordinateur, onglet fermé)
  **n'est pas perdu** : les morceaux déjà reçus restent sur le Pi ;
- pour reprendre, retournez sur **Envoyer des fichiers** (ou cliquez sur
  **Reprendre** depuis *Mes envois*) et **redéposez le même fichier** : le site
  reconnaît le fichier et ne redemande que les morceaux manquants ;
- le bouton **Mettre en pause** arrête proprement, **Reprendre** continue,
  **Annuler** supprime les morceaux déjà reçus ;
- après cinq échecs de suite, l'envoi s'arrête de lui-même et affiche
  « Vérifiez votre réseau, puis cliquez sur Reprendre ».

**Bon à savoir**

- **tout arrive privé** : aucun fichier envoyé n'est visible avant que vous
  n'ayez choisi sa visibilité. La règle du site ne change pas ;
- **les doublons sont signalés, jamais supprimés** : si le même fichier
  (empreinte SHA-256 identique) est déjà sur le site, le site vous le dit avant
  d'ajouter quoi que ce soit et vous choisissez « **Garder quand même** » ou
  « **Ignorer ce fichier** » ;
- **trois envois à la fois** au maximum ; les autres attendent leur tour ;
- **limites réglables** dans **Administration → Réglages** : taille d'un
  morceau (1 à 20 Mo, 5 Mo par défaut) et taille maximale d'un fichier (50 Mo à
  16 Go, 4 Go par défaut). Une taille de morceau plus petite est plus lente
  mais passe mieux sur une connexion très mauvaise ;
- **l'espace disque est vérifié** : le site refuse un envoi s'il ne resterait
  pas au moins 2 Go libres après. Mieux vaut refuser que remplir le SSD ;
- **les morceaux sont hors de la portée du web** (dossier `envois`, à côté de
  `medias`), sous un nom tiré au hasard : personne ne peut télécharger un
  fichier à moitié reçu ni deviner son adresse. Les validations habituelles
  (extension autorisée, type réel du fichier, taille, nom nettoyé) sont
  rejouées à la fin, sur le fichier assemblé ;
- **le disque du Pi ne travaille presque pas** : tout est copié par blocs de
  1 Mo, la mémoire du serveur ne dépend pas de la taille du fichier. Un envoi
  de 3 Go a été essayé de bout en bout : empreinte identique, 8 Mo de mémoire
  au plus ;
- **les envois abandonnés sont nettoyés** au bout de **48 h** (tâche
  d'entretien du dimanche). Un fichier interrompu depuis plus longtemps doit
  donc être renvoyé ;
- sur un téléphone : branchez-le, gardez l'écran allumé et l'application du
  navigateur au premier plan. Une vidéo de 2 Go demandera de toute façon
  plusieurs dizaines de minutes ;
- si l'envoi par le navigateur ne convient pas (fichier de plus de 4 Go,
  réseau local très rapide), le **dossier arrivée** reste la solution de
  secours : voir § 6.6 et [`deploy/dossier-arrivee.md`](deploy/dossier-arrivee.md).

### 6.19 Ajouter des photos directement dans un album

Plus besoin d'envoyer les photos d'un côté puis de les cocher dans l'album :
le bouton **Ajouter des photos** ouvre l'envoi par morceaux (voir § 6.18) là
où vous êtes.

1. Ouvrir un album (**Administration → Albums photos → Modifier**) ou, une
   fois connecté comme administrateur, la page publique de l'album
   (`/albums/…`).
2. Cliquer sur **Ajouter des photos** : la zone de dépôt apparaît, on peut
   glisser plusieurs fichiers ou les choisir d'un coup.
3. Chaque fichier reçu devient tout de suite une **photo de l'album**, ajoutée
   **en fin de liste**. Rien d'autre à valider.

Ce que fait le site pour chaque photo, sans rien demander :

- **photos seulement** : jpg, jpeg, png, webp et gif. Un film ou un PDF
  déposé ici est refusé, avec le message qui dit quoi faire ;
- **visibilité = celle de l'album** : une photo ajoutée à un album privé naît
  privée, une photo ajoutée à un album public naît publique. Vous pouvez
  ensuite la passer en privé depuis sa fiche ;
- **titre = nom du fichier**, sans l'extension (`noces-1962.jpg` donne
  « noces-1962 »), et la **date** est celle du fichier quand elle est lisible ;
- **vignettes** générées comme pour tout envoi ;
- **doublons signalés, jamais supprimés** : si l'empreinte SHA-256 est déjà
  connue, le site demande « Garder quand même » ou « Ignorer ce fichier ».

L'envoi terminé, la page affiche le lien **Voir l'album** et la liste des
photos ajoutées, avec pour chacune :

- un champ **Titre** et un champ **Date**, modifiables sur place, puis
  **Enregistrer** ;
- le bouton **Retirer de l'album** (après confirmation) : la photo quitte
  l'album, mais **le document n'est pas supprimé** — il reste dans
  *Documents*, et vous pouvez le remettre dans un album quand vous voulez.

Ces deux formulaires sont des formulaires ordinaires : ils fonctionnent même
si le JavaScript est coupé. Une photo ne peut pas être rangée deux fois dans le
même album, et un envoi n'est rattaché qu'à **un seul album** : le reprendre
depuis un autre album ne le détourne pas.

Seul un **administrateur** voit et utilise ce bouton ; un visiteur, un lecteur
ou un journaliste ne l'a jamais. Côté serveur, tout envoi vers un album est
réservé au compte connecté, protégé par le jeton CSRF, et la création de la
fiche est atomique : un double clic ne peut pas créer deux photos.

### 6.20 Verser dans un album une photo déjà sur le site

Une photo envoyée la semaine dernière n'est pas perdue : elle est dans
*Documents & photos*. Pour la ranger dans un album, inutile de la renvoyer.

1. Ouvrir **Administration → Albums photos**, puis **Ajouter des photos** sur
   l'album voulu (le même bouton existe sur la page publique de l'album, et un
   lien « choisir dans les documents du site » depuis le formulaire d'album).
2. Sous la zone de dépôt, la section « **Ajouter depuis les documents du site** »
   montre les photos déjà présentes — puis les autres documents — en vignettes
   avec leur titre, leur date et leur **badge de visibilité**.
3. Filtrer au besoin : **recherche** (titre ou étiquette), **type** (Photos /
   Tous les documents), **visibilité**, **catégorie**. La case « **Pas encore
   dans cet album** » est cochée au départ ; décochée, elle montre aussi ce qui
   y est déjà, marqué « Déjà dans l'album » et impossible à cocher.
4. Cocher les documents, puis **Ajouter la sélection à l'album** : ils entrent
   **à la fin** de l'album, **dans l'ordre de la sélection**. Un document déjà
   présent est **ignoré sans erreur**.

Vingt-quatre documents par page, avec la pagination habituelle. Deux boutons
remplacent la sélection perdue d'une page à l'autre : « **Tout cocher sur cette
page** » (JavaScript) et « **Tout ajouter sur cette page** » (sans JavaScript :
c'est le serveur qui reprend la page affichée).

**La visibilité ne change jamais.** Verser une photo privée dans un album
« Toutes les familles » ne la rend pas publique : elle reste invisible à la
famille dans l'album. Le site vous le dit après l'ajout — « 1 photo reste
privée : la famille ne la verra pas dans l'album. » — et propose un bouton
séparé, « **Leur donner la visibilité de l'album** », qui demande confirmation
et prévient que le changement se voit **partout sur le site**. Rien n'est fait
automatiquement.

Seul un **administrateur** voit cette grille ; un lecteur ou un journaliste
reçoit un refus (403), un visiteur est renvoyé vers la connexion. Seuls des
**identifiants** de documents circulent dans le formulaire : le serveur vérifie
que le document existe, qu'il n'est pas déjà dans l'album, et — pour l'action de
visibilité — qu'il appartient bien à cet album. « **Retirer de l'album** »
existe toujours et ne supprime pas le document.

### 6.21 Écrire au site : les deux adresses affichées

Le site **n'envoie aucun courriel**. La seule conversation entre les personnes,
c'est le **tchat** (§ 6.10) et les notifications affichées dans le site
(accueil, « Dans la famille », « À valider »). Deux adresses servent uniquement
à **être jointes depuis l'extérieur** :

| Adresse | À quoi elle sert |
|---|---|
| `contact@histoiredefamille.eu` | la question générale, le pied de page de toutes les pages, « Nous écrire » sur l'accueil |
| `support@histoiredefamille.eu` | le coup de main : mot de passe oublié, besoin d'un compte, page d'erreur, « Mon compte » |

Elles s'affichent en **texte lisible et copiable**, sous forme de lien `mailto:`
ordinaire : cliquer dessus ouvre votre logiciel de courrier, avec l'adresse déjà
écrite. Rien d'autre ne se passe — pas de formulaire, pas de JavaScript, et le
site ne voit jamais votre message.

**Les changer** : Administration → Réglages → *Adresses affichées*. Deux champs,
« Adresse de contact » et « Adresse d'assistance ». Ils sont enregistrés dans les
réglages du site (pas dans le code), leur format est vérifié, et un champ laissé
vide remet l'adresse livrée avec le site — il n'y a donc jamais de page sans
adresse à écrire. Une adresse enregistrée mais illisible n'est jamais montrée :
le site retombe alors sur celle livrée avec lui.

Aucune adresse personnelle de compte (celle d'un lecteur, d'un journaliste) n'est
jamais affichée, ni dans l'administration, ni ailleurs.

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

Une mise à jour qui touche `public/.htaccess` (c'est le cas de la 1.26.1, où
les en-têtes de sécurité y sont désormais déclarés une seule fois) prend effet
dès la recopie du code : `git pull` suffit, puis

```bash
sudo systemctl reload apache2
curl -sI http://localhost
```

Si `install.sh` n'a pas été relancé, l'ancien VirtualHost peut encore déclarer
des en-têtes : `public/.htaccess` les efface avant de les reposer, donc la
réponse reste propre. Relancer `install.sh` remet aussi le VirtualHost à jour —
c'est la voie normale.

Sans passer par `install.sh`, la seule commande à connaître pour une nouvelle
version est celle des migrations (elles sont numérotées, appliquées une seule
fois, et n'effacent jamais de données) :

```bash
php app/cli/migrate.php
```

Après une mise à jour, les petits tests automatiques (mise en forme des
messages du tchat, préparation du texte cherché, chronologie d'une fiche de
personne, albums, calendrier, GEDCOM, recettes, journal des connexions,
rubriques publiques, envoi de gros fichiers, conversion des vidéos, carte des
lieux, menu et comptes de la famille) se lancent en une commande. Le nom d'une famille
peut être ajouté pour ne lancer qu'elle (`php app/cli/tests.php rubriques`) :

```bash
cd /home/votre-utilisateur/histoiredefamille
php app/cli/tests.php
```

Chaque test affiche `ok`, un résumé s'affiche à la fin ; rien n'est modifié
dans la base ni dans les dossiers.

---

## 8. Sauvegarde et restauration

### 8.0 Premiers jours sans disque externe

Le SSD et la clé USB ne sont pas encore là ? Le site fonctionne quand même :
**tout tient sur la carte SD**, dans `/var/www/histoiredefamille/storage` (ou
le dossier indiqué par `storage.root`). Quelques règles suffisent pour ces
premiers jours.

**1. La sauvegarde reste possible, mais elle ne protège de rien.** Par défaut,
la commande de sauvegarde **refuse** de tourner tant que la clé n'est pas
montée : c'est voulu, pour ne pas remplir la carte SD avec des copies. Pour
accepter une sauvegarde écrite sur la carte SD elle-même, décommentez une
seule ligne dans `config.local.php` :

```php
'maintenance' => [
    // … le reste du bloc ne change pas
    'backup_allow_same_disk' => true,
],
```

Ce mode n'est **jamais actif par défaut**. Tant qu'il est en place,
l'administration affiche en permanence un **bandeau rouge** (« Mode sans
disque externe : … »), et `php app/cli/verifier-mise-en-ligne.php` le signale
en **avertissement** : le site peut ouvrir (« PRÊT À OUVRIR »), mais la
sauvegarde est sur la même carte que le site. Une carte qui lâche emporte les
deux. Retirez la ligne dès que le disque arrive, puis relancez une sauvegarde.

**2. Surveillez l'espace libre.** Une carte SD de 32 Go suffit pour le texte,
les photos et quelques vidéos, pas pour des heures de film. La page
**Administration → État du site** affiche l'espace libre ; en ligne de commande :

```bash
df -h /var/www/histoiredefamille/storage
```

Sous 2 Go libres, arrêtez les envois de vidéos : une conversion peut réclamer
la place d'un second fichier. Le site refuse d'ailleurs de lancer une
conversion sous **1 Go de marge** (réglage `conversion.min_free_mb`), et
refuse un envoi s'il n'y a pas la place pour le fichier plus 2 Go.

**3. Faites une copie manuelle, hors du Pi.** C'est la seule vraie sauvegarde
de ces premiers jours. Depuis votre ordinateur, avec `scp` :

```bash
scp -r pi@histoiredefamille.local:/var/www/histoiredefamille/storage ~/copie-$(date +%F)
```

Ou avec un client SFTP (FileZilla, Cyberduck) : même principe, on télécharge le
dossier `storage` entier. À refaire après chaque grosse séance de saisie. Le
fichier `config.local.php` se copie **à part** (il contient les réglages, pas
les données).

**4. Quand le disque arrive.** Montez-le (voir `deploy/montage-disques.md`),
déplacez le dossier de données, adaptez `storage.root` et `maintenance` dans
`config.local.php`, retirez `backup_allow_same_disk`, puis lancez une
sauvegarde à la main :

```bash
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php
```

Le bandeau rouge disparaît, la clé devient le lieu de sauvegarde, et la
vérification avant ouverture repasse au vert. La copie manuelle faite la
veille reste votre filet de sécurité jusqu'à la première sauvegarde réussie
sur le disque.

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
   atterrirait sur la carte SD et la remplirait. Seule exception, volontaire et
   visible : le mode « premiers jours sans disque externe » (section 8.0).
2. **Copie la base** avec l'API de SQLite (`VACUUM INTO`) : la copie est
   cohérente même pendant que le site tourne — **jamais `cp`**, qui donnerait
   un fichier abîmé. Le résultat est contrôlé (`PRAGMA integrity_check`) avant
   d'être conservé.
3. **Copie les fichiers** (photos, miniatures, vidéos, audio, PDF, dossier
   `arrivee`) de façon incrémentale : seuls les fichiers nouveaux ou modifiés
   sont réécrits. `tmp/`, `verrous/` et `envois/` sont ignorés (fichiers
   jetables : un fichier à moitié reçu n'a rien à faire dans une sauvegarde).
   La **file de conversion** (table `conversions`) est dans la base : une
   vidéo en attente est donc sauvegardée, et le travail reprend après une
   restauration.
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
sudo mkdir -p /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous,envois}
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

## 9. Vidéos : le site convertit tout seul

Depuis la version 1.21, le site **convertit lui-même** les vidéos : envoyer un
fichier MOV d'un téléphone, un AVI du caméscope ou un MKV de la famille ne
demande plus aucune préparation. La conversion se fait **en arrière-plan**,
une seule vidéo à la fois, sans jamais bloquer une visite.

### Pourquoi en arrière-plan

Un Raspberry Pi n'a pas de puce vidéo : convertir une heure de film prend
plusieurs heures. Ce travail ne peut donc pas se faire pendant l'envoi (la
requête serait coupée, et la personne attendrait devant une page blanche).
L'envoi se contente d'inscrire le fichier dans une **file d'attente**, et une
commande séparée la vide tranquillement :

```bash
# une seule fois, à l'installation : ffmpeg (installation par deploy/install.sh)
sudo apt-get install -y ffmpeg

# ce que fait le minuteur, toutes les 10 minutes
sudo -u www-data php /var/www/histoiredefamille/app/cli/convertir.php
```

Le minuteur `histoiredefamille-conversion.timer` est posé par
`deploy/install.sh`. Il tourne en **priorité basse** (le site reste réactif)
et **une seule conversion à la fois**.

### Ce qui est converti

- **vidéos** : MOV, AVI, MKV, WebM, MP4 en HEVC… deviennent **MP4 (H.264 +
  AAC)**, le format que tous les navigateurs savent lire. Un MP4 déjà en
  H.264 n'est **jamais** reconverti (ce serait un double encodage, donc une
  perte de qualité pour rien) ;
- **sons** : WAV et AMR (dictaphones, vieux téléphones) deviennent **M4A
  (AAC)**, sans image ;
- les photos et les documents ne sont jamais touchés.

### Ce que voit la famille

Tant qu'une vidéo n'est pas convertie, elle **n'apparaît pas** : ni dans
l'onglet Vidéos, ni dans les albums, ni dans la recherche. Aucun lecteur ne
s'affiche donc sur un fichier illisible. Les administrateurs, eux, voient la
fiche et son état (« en attente », « en cours — 42 % », « échec »).

### Les images d'aperçu des vidéos (1.32.0)

L'image d'une vidéo est **extraite du film** par ffmpeg (à une seconde, ou à
10 % de la durée pour un film très court), puis réduite en JPEG comme les
autres miniatures et rangée avec elles. Elle est fabriquée **automatiquement** :
à la fin de l'envoi pour un MP4, à la fin de la conversion pour un film
reconverti. Rien à faire à la main.

Pour les vidéos **déjà sur le site** avant la 1.32.0, une commande les rattrape
**une par une**, en tâche de fond, sans bloquer le site :

```bash
# 1. lister ce qui manque (aucune modification)
sudo -u www-data php /var/www/histoiredefamille/app/cli/miniatures-videos.php

# 2. traiter UNE vidéo (relancer la commande pour la suivante)
sudo -u www-data php /var/www/histoiredefamille/app/cli/miniatures-videos.php --executer

# ou traiter jusqu'à trois vidéos d'un coup
sudo -u www-data php /var/www/histoiredefamille/app/cli/miniatures-videos.php --executer --lot=3
```

La commande est **relançable** sans risque : elle saute les vidéos qui ont déjà
une image et n'en fabrique jamais deux fois. Sans ffmpeg, elle le dit et ne
fait rien ; les cartes affichent alors l'icône de lecture de remplacement, et
la page **Conversions** porte un message qui explique comment installer ffmpeg.
Un administrateur peut toujours **envoyer une image de couverture** sur la
fiche d'une vidéo : elle remplace l'image extraite.

### Suivre et décider : Administration → Conversions

La page liste la file avec, pour chaque fichier : la vidéo, son état, la
taille de l'original et du fichier converti, le nombre d'essais et le message
d'erreur s'il y en a un.

- **Relancer** remet un travail échoué ou annulé dans la file (les trois
  essais repartent de zéro) ;
- **Annuler** arrête une conversion en cours : ffmpeg est stoppé au palier
  suivant, le fichier d'origine reste en place ;
- **Les ajouter à la file** inscrit les vidéos déjà présentes sur le site qui
  ne sont pas au bon format (utile après avoir installé ffmpeg) ;
- si ffmpeg est absent, la page le dit **clairement** et propose la commande à
  taper : rien ne casse, la file attend.

### Réglages (Administration → Réglages)

- **Convertir les vidéos en MP4** : oui (conseillé) ou non. Désactivé, aucun
  fichier n'entre dans la file et les vidéos restent telles quelles ;
- **Qualité** : *légère* (720p, la plus rapide), *équilibrée* (1080p,
  conseillée), *haute* (hauteur d'origine, plus lente et plus lourde) ;
- **Effacer le fichier d'origine sept jours après une conversion réussie** :
  décoché par défaut (deux versions occupent alors la place). Coché, l'original
  n'est effacé qu'après **sept jours**, jamais si la conversion a échoué, et
  jamais s'il est encore utilisé par une autre fiche.

### Ce que le site garantit

- **rien n'est servi à moitié** : le fichier converti est écrit sous un nom
  temporaire, puis la fiche est mise à jour **en une seule transaction**.
  Jamais de lecteur pointant vers un fichier incomplet ;
- **l'original n'est jamais perdu** : il reste sur le disque jusqu'à ce que
  vous ayez explicitement demandé son effacement, sept jours après ;
- **trois essais, puis un message clair** : « Le fichier d'origine est abîmé »,
  « Le disque est plein », « ffmpeg ne sait pas encoder en H.264 »… jamais une
  erreur technique ;
- **la visibilité ne change pas** : convertir une vidéo ne modifie ni ses
  droits, ni sa catégorie, ni ses albums ;
- **la mémoire reste basse** : la commande lit la sortie de ffmpeg ligne par
  ligne, ffmpeg écrit directement dans un fichier ; aucun film n'est jamais
  chargé en mémoire.

### Si vous préférez convertir sur votre ordinateur

C'est toujours possible, et parfois plus rapide : le site accepte alors
directement le MP4, sans passer par la file.

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

Ensuite, deux voies : **Administration → Envoyer des fichiers** (§ 6.18, par
morceaux, depuis n'importe où), ou le dépôt dans le dossier `arrivee` du Pi
puis l'import : **[`deploy/dossier-arrivee.md`](deploy/dossier-arrivee.md)**.

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
| Envoi de photo refusé « fichier trop volumineux » | plus de 50 Mo | réduire la photo, ou augmenter `uploads.max_mb` **et** `upload_max_filesize` / `post_max_size` (max 90 Mo à cause de Cloudflare). Au-delà, passer par **Envoyer des fichiers** (§ 6.18) |
| Envoi d'un gros fichier qui s'arrête sans cesse | Wi-Fi instable, ordinateur en veille, onglet fermé | rouvrir la page, redéposer **le même** fichier : l'envoi reprend où il s'était arrêté. Réduire la taille d'un morceau à 2 Mo dans **Réglages** aide sur une mauvaise ligne |
| « Il n'y a pas assez de place » à l'envoi | moins de 2 Go libres après l'envoi | `df -h /mnt/ssd`, faire de la place (ou attendre le nettoyage des envois abandonnés, 48 h) |
| Miniatures noires ou vides | image CMYK ou très grande | convertir en JPEG RVB ; au-delà de 24 Mpx le site refuse (limite mémoire du Pi) |
| Mot de passe de branche perdu | — | Administration → Branches → **Réinitialiser le mot de passe** |
| Compte bloqué après 5 essais | protection anti-force brute | attendre 15 minutes, ou `sudo -u www-data sqlite3 /mnt/ssd/histoiredefamille/histoire.sqlite "DELETE FROM login_attempts;"` |
| Page « Site momentanément indisponible » | Apache répond 503 (surcharge, redémarrage) ou PHP est arrêté | `sudo systemctl status apache2` ; la page est `public/maintenance.html`, modifiable librement |
| Sauvegarde en échec | clé USB absente ou pleine | `df -h /mnt/sauvegarde`, rebrancher, relancer `sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php` |
| Le Pi ne démarre plus | disque monté dans `fstab` et absent | `nofail` doit être présent ; sinon démarrer sans le disque et corriger `/etc/fstab` |
| Vidéo qui ne démarre pas | format non compatible, conversion pas encore faite | ouvrir **Administration → Conversions** : la file dit où en est le fichier (§ 9) |
| « ffmpeg est introuvable » | programme absent du Pi | `sudo apt-get install -y ffmpeg` puis `sudo bash deploy/install.sh`. En attendant, la file attend et les vidéos restent dans leur format d'origine |
| Une conversion échoue | fichier abîmé, disque plein, format exotique | lire le message dans **Conversions** ; « Relancer » après avoir corrigé la cause. L'original reste servi tel quel |
| Les conversions ne démarrent pas | minuteur arrêté | `systemctl list-timers histoiredefamille-conversion.timer` puis `sudo systemctl start histoiredefamille-conversion.service` ; journal : `journalctl -u histoiredefamille-conversion.service -n 50` |

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
- **Conversion des vidéos** : ffmpeg est lancé **sans passer par le shell**
  (arguments en tableau : aucun nom de fichier ne peut être interprété), sous
  l'utilisateur `www-data`, en priorité basse, un seul fichier à la fois. La
  fiche n'est mise à jour qu'à la fin, en une transaction ; l'original n'est
  jamais effacé sans un réglage explicite et un délai de sept jours.
- **Envois par morceaux** : les morceaux attendent dans le dossier `envois`,
  **hors de la racine web**, sous un identifiant aléatoire de **128 bits**
  contrôlé par une expression stricte (aucun chemin n'est construit à partir
  d'une valeur saisie) ; ils ne sont assemblés, vérifiés et déplacés vers
  `medias` qu'à la dernière étape. Chaque requête (déposer, interroger,
  terminer, annuler) exige une **session administrateur** et, pour les actions
  qui modifient, un **jeton CSRF** ; un envoi n'appartient qu'à son auteur.
- **Fichiers privés** : jamais accessibles par URL directe ; tout passe par
  `/f/{id}`, `/media/miniature/{id}` ou `/video/{id}` avec contrôle des droits
  et en-tête `Cache-Control: private, no-store`.
- **En-têtes** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Content-Security-Policy` (aucune ressource externe, aucun script en ligne),
  `Permissions-Policy`. Ils sont posés **une seule fois**, par Apache, dans
  `public/.htaccess` — le PHP ne les envoie plus, sinon ils partaient deux fois
  avec deux valeurs (corrigé en 1.26.1). `Strict-Transport-Security` (HSTS) et
  le cache privé restent en PHP : ils dépendent de la configuration.
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

Et, pour voir de ses yeux ce qu'Apache envoie vraiment (une ligne par
en-tête, une seule fois chacun) :

```bash
curl -sI http://localhost
```

Attendu : **un** `X-Content-Type-Options: nosniff`, **un**
`X-Frame-Options: DENY`, **un** `Referrer-Policy`, **un**
`Permissions-Policy`, **un** `Content-Security-Policy` commençant par
`default-src 'self'` et sans `unsafe-inline`. Si un nom apparaît deux fois,
c'est qu'une autre configuration le pose : cherchez `Header` dans
`/etc/apache2/sites-enabled/` (et dans `deploy/apache/`) — la seule source est
`public/.htaccess`. La commande de vérification le signale alors en
**avertir** (« envoyé 2 fois »), sans bloquer l'ouverture.

La commande passe tout en revue et rend un verdict :

- **`PRÊT À OUVRIR`** : vous pouvez continuer ;
- **`PAS PRÊT : N point(s) à corriger`** : chaque ligne `À CORRIGER` indique
  **ce qui ne va pas et quoi faire** (la flèche `→`). Corrigez, puis relancez
  la commande jusqu'à obtenir `PRÊT À OUVRIR`.

Quatre états, et deux seulement comptent pour le verdict :

| État | Ce que cela veut dire |
|---|---|
| `OK` | tout va bien |
| `INFO` | une information (minuteur, en-têtes) : rien à faire |
| `À CORRIGER` | **bloque l'ouverture** : le site n'est pas prêt |
| `AVERTIR` | à regarder, mais **n'empêche jamais d'ouvrir** : le site fonctionne |

Les avertissements sont là pour être lus, pas pour empêcher d'ouvrir : une clé
USB qu'on branchera demain, un HTTPS obligatoire qu'on activera après le premier
essai en local. (L'envoi de courriels, lui, n'est plus une source
d'avertissement : désactivé volontairement, il est annoncé **OK** —
« courriels désactivés volontairement ».) La commande les affiche à part
et conclut quand même `PRÊT À OUVRIR`.

Ce qui est contrôlé, et pourquoi :

| Contrôle | Ce que cela évite |
|---|---|
| Adresse publique en `https://` (bloquant) et HTTPS obligatoire (avertissement) | un mot de passe qui circule en clair |
| Aucun contenu marqué « (démo) », aucun compte fictif (avertissement) | ouvrir le site avec des données qui ne sont pas les vôtres |
| Deux administrateurs actifs au moins, aucun mot de passe d'essai connu (avertissement) | un site que personne ne peut plus administrer |
| `config.local.php` en 600 ou 640, base et données hors de `public/` | des données familiales téléchargeables par n'importe qui |
| `public/` sans fichier sensible (`.git`, base, sauvegarde, configuration) | la même chose, par un chemin détourné |
| PHP 8.1 ou plus, extensions SQLite, GD, mbstring, fileinfo, sodium/Argon2id | des photos sans miniature, des mots de passe mal protégés |
| Clé montée et inscriptible, dernière sauvegarde de **moins de 48 heures**, minuteur actif, test de restauration daté | découvrir le problème le jour où l'on a besoin de la sauvegarde |
| Courriel de contact et minuteur d'envoi (avertissement) | des messages qui restent dans la file sans que personne ne le voie |
| Espace libre sur le SSD et sur la clé | un site qui tombe en panne parce que le disque est plein |
| Purge planifiée | des tables de journal qui grossissent sans fin |
| En-têtes HTTP réellement envoyés par Apache (CSP, X-Frame-Options, nosniff, Permissions-Policy, HSTS), **une seule fois chacun**, et signature masquée | une page piégée qui lit les données de la famille, ou deux politiques contradictoires dont le navigateur applique la plus faible |
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

## 14. Envoyer des courriels

> **Les courriels sont désactivés sur ce site.** Depuis la 1.28.0, le site
> **n'envoie rien** : pas de message à la création d'un compte, à la
> publication d'un article, à la validation d'une proposition, ni de gazette
> par courriel. Rien n'entre dans la file d'envoi, la tâche automatique
> s'arrête en le disant, et la vérification avant ouverture ne réclame plus de
> configuration SMTP. Tout le reste marche : **tchat**, et notifications
> affichées dans le site. Pour joindre la famille depuis l'extérieur, ce sont
> les deux adresses affichées (§ 6.21).
>
> **Pour réactiver l'envoi**, si le besoin revient : (1) dans
> **Administration → Réglages**, cocher **« Le site envoie des courriels »** ;
> (2) remplir le bloc `mail` de `config.local.php` sur le Raspberry Pi comme ce
> chapitre l'explique, puis recharger Apache. Les écrans masqués reviennent
> d'eux-mêmes ; décocher la case les recoupe. Le code n'a rien perdu : les
> colonnes `email`, `notify_articles` et `notify_gazette` sont intactes, et les
> adresses déjà saisies n'ont pas été effacées.

Le site peut prévenir la famille de deux façons, **toujours avec l'accord de la
personne** :

- un message quand un **nouvel article** paraît ;
- la **gazette du mois** (voir la section suivante).

Tout le reste fonctionne sans courriel : si l'envoi n'est pas configuré, les
messages attendent sagement dans une file, et l'administration l'explique.

### 14.1 Ce qu'il faut me demander

Pour configurer l'envoi, j'ai besoin de **cinq renseignements** que vous seul
pouvez obtenir (ils viennent de votre boîte mail, pas du site) :

| Ce qu'il me faut | Exemple | Où le trouver |
|---|---|---|
| Le **serveur d'envoi** (SMTP) | `smtp.exemple.fr` | Aide de votre fournisseur de messagerie |
| Le **port** | `587` (le plus courant) ou `465` | Idem |
| Le **chiffrement** | `tls` (STARTTLS) ou `ssl` | Idem |
| L'**identifiant** | souvent votre adresse complète | Idem |
| Le **mot de passe** | mot de passe de la boîte, ou « mot de passe d'application » | Idem |

⚠️ **Ne me donnez jamais ce mot de passe dans un message public** (dépôt GitHub,
ticket, forum). Il se met uniquement dans `config.local.php`, sur le Raspberry
Pi, par vous.

### 14.2 Les paramètres des fournisseurs courants

Ces valeurs sont publiques et changent rarement. Vérifiez toujours sur le site
d'aide du fournisseur : c'est lui qui fait foi.

| Fournisseur | Serveur | Port | Chiffrement | Remarque |
|---|---|---|---|---|
| Orange / Wanadoo | `smtp.orange.fr` | 465 | `ssl` | Identifiant = adresse complète |
| Free | `smtp.free.fr` | 465 | `ssl` | Identifiant = adresse complète |
| Gmail / Google Workspace | `smtp.gmail.com` | 587 | `tls` | **Mot de passe d'application** obligatoire (validation en deux étapes) |
| Outlook / Hotmail | `smtp-mail.outlook.com` | 587 | `tls` | Identifiant = adresse complète |
| OVH | `ssl0.ovh.net` | 465 | `ssl` | Identifiant = adresse complète |
| Infomaniak | `mail.infomaniak.com` | 587 | `tls` | Identifiant = adresse complète |
| Ionos / 1&1 | `smtp.ionos.fr` | 587 | `tls` | Identifiant = adresse complète |

Deux pièges fréquents :

- **Gmail** refuse votre mot de passe habituel : il faut créer un « mot de passe
  d'application » dans les réglages de sécurité du compte Google.
- Certains fournisseurs limitent le **nombre de messages par jour** (souvent
  100 à 300). Le plafond du site (50 par défaut) est en dessous : gardez-le.

### 14.3 Le réglage, pas à pas

1. Sur le Raspberry Pi, ouvrez le fichier de configuration :

```bash
sudo nano /var/www/histoiredefamille/config.local.php
```

2. Complétez le bloc `'mail'` (les autres blocs ne changent pas) :

```php
'mail' => [
    'host'         => 'smtp.exemple.fr',   // serveur fourni par votre boîte
    'port'         => 587,
    'encryption'   => 'tls',               // 'tls', 'ssl' ou 'none'
    'username'     => 'votre-adresse@exemple.fr',
    'password'     => 'le-mot-de-passe-fourni',
    'from_address' => 'votre-adresse@exemple.fr',
    'from_name'    => 'Histoire de famille',
    'timeout'      => 10,
],
```

3. Enregistrez (`Ctrl+O`, `Entrée`, puis `Ctrl+X`) et protégez le fichier :

```bash
sudo chown root:www-data /var/www/histoiredefamille/config.local.php
sudo chmod 640 /var/www/histoiredefamille/config.local.php
```

4. Rechargez Apache :

```bash
sudo systemctl reload apache2
```

5. Dans **Administration → Courriels**, vérifiez que la page affiche
   « L'envoi est configuré », puis cliquez sur
   **« Envoyer un courriel d'essai à moi-même »**.

   Pour cela, votre propre compte doit avoir une adresse : ajoutez-la dans
   **Mon compte → Mes courriels**.

6. Si l'essai n'arrive pas : regardez dans les indésirables, puis vérifiez le
   message d'erreur dans le tableau « 50 derniers envois ». Les causes
   fréquentes sont le mot de passe (Gmail), le port et le chiffrement.

### 14.4 Qui reçoit quoi, et qui décide

- L'adresse électronique est **facultative**. Sans adresse, on ne reçoit rien.
- Deux cases, **décochées par défaut**, dans **Mon compte → Mes courriels** :
  « Prévenez-moi des nouveaux articles » et « Je veux recevoir la gazette du
  mois ».
- Une adresse saisie par un administrateur **ne vaut pas accord** : seules les
  cases cochées par la personne elle-même comptent.
- Chaque message contient un lien **« Se désinscrire »** qui fonctionne **sans
  se connecter** (un clic, puis une confirmation), et un lien
  **« Gérer mes préférences »** vers « Mon compte ».
- Personne ne voit l'adresse de quelqu'un d'autre : un message par personne,
  jamais de copie multiple, et les adresses sont masquées (`m***@exemple.fr`)
  dans l'administration et dans le journal.
- Un contenu réservé n'est **jamais** envoyé à quelqu'un qui n'a pas le droit
  de le voir : la règle de visibilité du site est appliquée avant l'envoi.

### 14.5 Prévenir la famille d'un nouvel article

Rien à faire : c'est automatique, à deux conditions.

1. Dans le formulaire de l'article, la case **« Prévenir les inscrits par
   courriel »** est cochée à la première publication (elle est absente pour un
   brouillon). Décochez-la pour un article que personne n'a besoin de recevoir.
2. Les personnes concernées ont coché « Prévenez-moi des nouveaux articles ».

Ce qui se passe ensuite :

- **Publication immédiate** : le message est mis en file tout de suite, et
  part à la tâche suivante (moins de 5 minutes).
- **Publication programmée** (une date dans le futur) : le message part le
  jour de la publication, pas au moment où vous écrivez.
- **Article repassé en brouillon** : les messages encore en attente sont
  annulés.
- **Un seul message par personne et par article**, même si vous corrigez
  l'article dix fois.
- **Personne ne reçoit le retard.** Chacun ne reçoit que les articles publiés
  **après le moment où il a coché** « Prévenez-moi des nouveaux articles ».
  Quelqu'un qui s'inscrit aujourd'hui ne reçoit donc pas les articles des
  dernières semaines ; s'il décoche puis recoche, le compteur repart de ce
  nouveau moment. La date est retenue dans son compte
  (`notify_articles_depuis`) et n'apparaît nulle part à l'écran : c'est un
  détail technique, pas un réglage.
- Le message contient le **titre**, le **résumé**, la **rubrique**, la **date**
  et un **lien**. Jamais le texte intégral d'un article réservé, jamais la
  photo en pièce jointe.
- Pour un article non public, le lien mène d'abord à la connexion, puis à
  l'article.

### 14.6 Le fonctionnement technique (pour information)

- Un petit client SMTP écrit à la main (aucune bibliothèque à installer) :
  STARTTLS ou TLS implicite, authentification LOGIN, délais courts.
- **Aucun envoi pendant une visite du site** : le navigateur ne fait que
  remplir une file (`email_queue`). Une tâche automatique la vide toutes les
  5 minutes, par lots de 10 maximum, avec une pause d'une seconde entre deux
  envois et un plafond quotidien (50 par défaut, réglable).
- Un envoi raté est réessayé plus tard ; après **3 essais**, il est abandonné
  et l'erreur reste visible dans l'administration.
- Les messages terminés sont purgés au bout de **90 jours**.
- Les paramètres du serveur d'envoi ne sont **jamais** en base, jamais dans les
  journaux, jamais affichés.

### 14.7 Vérifier que la tâche d'envoi tourne

```bash
systemctl list-timers histoiredefamille-courriels.timer
journalctl -u histoiredefamille-courriels.service -n 30
```

Tant que les courriels sont désactivés, **il n'y a rien à voir** : `deploy/
install.sh` installe les unités mais laisse le minuteur **arrêté** (« Courriels
désactivés : minuteur laissé arrêté »), et relancer le script ne le rallume pas.
La liste des minuteurs ne montre donc pas celui des courriels — c'est normal.

Si le minuteur tourne malgré tout (installation antérieure, ou tâche lancée à la
main), la commande ne fait rien et l'écrit : « Les courriels sont désactivés
volontairement ». Ce message n'est pas une panne, c'est le réglage ; il
disparaît quand vous cochez la case dans Administration → Réglages, remplissez
le bloc `mail` de `config.local.php`, et relancez `deploy/install.sh`.
```

La première commande doit afficher une prochaine exécution (toutes les
5 minutes). La seconde montre les derniers envois, avec les adresses masquées.

---

## 15. La gazette du mois

Chaque mois, le site peut envoyer un petit journal à la famille : ce qui s'est
passé le mois précédent, mis en page avec les couleurs de votre journal.

Quatre choses à garder en tête :

1. **Personne n'est obligé de la recevoir.** Seules les personnes qui ont coché
   « Je veux recevoir la gazette du mois » dans **Mon compte** la reçoivent.
2. **Chacun ne reçoit que ce qu'il a le droit de voir.** Le site prépare un
   numéro par groupe de droits (la famille, chaque branche, et les personnes
   qui voient tout) : un article réservé à une branche n'apparaît jamais dans
   le numéro d'une autre branche.
3. **Un mois sans rien = aucun envoi.** On ne vous envoie pas une page vide.
4. **Un seul envoi par mois et par groupe.** Impossible de recevoir deux fois
   le même numéro.

### 15.1 Ce que contient la gazette

Tout est repris automatiquement du mois écoulé :

- les **articles** publiés dans le mois ;
- les **photos** et les **albums** ajoutés ;
- les **vidéos** ;
- les **témoignages** sonores ;
- les **recettes** ;
- les **nouvelles personnes** ajoutées à l'arbre ;
- les **petites annonces** (anniversaires et souvenirs des 30 prochains jours) ;
- les **chiffres** du mois.

Les titres de ces encarts se règlent dans **Administration → Apparence du
journal → Titres des encarts** (les six titres « Gazette : … »).

### 15.2 Le déroulement d'un mois

| Quand | Ce qui se passe |
|---|---|
| Le **1er du mois** | La tâche automatique (celle qui envoie les courriels, toutes les 5 minutes) prépare un numéro par groupe de droits, à partir du mois écoulé. Le numéro porte l'état **« À valider »**. Elle ne le fait que si l'envoi est réactivé (§ 14) ; sinon, le numéro se crée à la demande depuis **Administration → Gazette du mois**. |
| Quand vous voulez | **Administration → Gazette du mois** : vous relisez le numéro, vous pouvez **retirer un élément** (il ne partira pas), le remettre, et écrire un **mot de l'éditeur** (600 caractères). |
| Vous cliquez **Envoyer** (envoi réactivé seulement) | Le site met un message en file pour chaque personne du groupe. Les messages partent dans les cinq minutes. Le numéro passe à « Envoyé ». |
| Vous cliquez **Passer ce mois-ci** | Aucun message ne part. Le numéro reste consultable dans l'administration. |

### 15.3 L'aperçu est fidèle

L'écran de relecture montre **exactement** ce que les personnes recevront :
mêmes encarts, mêmes éléments, même ordre, mot de l'éditeur compris. Ce qui est
retiré n'apparaît ni dans l'aperçu, ni dans le courriel.

Si plusieurs groupes existent (deux branches, par exemple), un sélecteur en
haut de l'aperçu permet de passer d'un numéro à l'autre. Chacun est préparé et
envoyé séparément : c'est ce qui garantit qu'aucune branche ne voit le contenu
de l'autre.

### 15.4 L'envoi automatique (désactivé par défaut)

Sur la même page, la case **« Envoyer sans validation, au-delà de 3 jours »**
est décochée. Décochez-la si vous voulez décider chaque mois.

Si vous l'activez : un numéro encore « À valider » **3 jours après la fin du
mois** part tout seul. C'est un filet de sécurité pour les mois chargés — vous
pouvez toujours envoyer ou passer un numéro avant.

### 15.5 « Lire en ligne »

Chaque courriel contient un bouton **« Lire la gazette en ligne »** qui mène à
`/gazette/AAAA-MM` (par exemple `/gazette/2026-10`). Cette page est réservée
aux personnes connectées : chacun y retrouve **son** numéro, celui de son
groupe de droits. Un numéro qui ne vous concerne pas, ou qui n'a pas encore été
envoyé, répond « introuvable » comme partout ailleurs sur le site.

La page « Lire en ligne » ne montre aucun bouton d'administration : elle sert
à lire, pas à modifier.

### 15.6 Ce qu'il faut savoir avant d'ouvrir

- **Les courriels sont désactivés sur ce site** (§ 14) : un numéro se prépare,
  se relit, se publie **en ligne** — mais rien ne part par courriel, et le
  bouton « Envoyer la gazette » n'est pas proposé. Pour l'envoi par courriel,
  réactivez-le d'abord (§ 14) : sans configuration SMTP, les numéros seraient
  préparés mais **rien ne partirait**.
- Un numéro déjà envoyé ne peut plus être modifié (ni élément, ni mot).
- Les liens du numéro mènent aux pages du site : si un contenu a été supprimé
  depuis, le lien répond simplement « introuvable ».
- Les adresses ne sont jamais affichées : un message par personne, jamais de
  copie multiple.

### 15.7 Vérifier

```bash
systemctl list-timers histoiredefamille-courriels.timer
journalctl -u histoiredefamille-courriels.service -n 30
```

La première ligne de la sortie de la commande mentionne la préparation de la
gazette (« Gazette du mois : 2 numéro(s) préparé(s) pour … ») au début du mois.

---

## 16. La carte des lieux

La page `/carte` dessine les lieux de la famille. Depuis la 1.32.0, la carte est
**désactivée par défaut** : le réglage « **Carte des lieux** » de
*Administration → Réglages* est décoché. Tant qu'il l'est, `/carte` et
`/lieu/{id}` répondent la page « **introuvable** » (code 404) **à tout le
monde, administrateur compris** ; le lien « Carte » disparaît des menus, de
l'accueil et des fiches (« Voir sur la carte ») ; « **Lieux à placer** » quitte
le menu d'administration et `/admin/lieux` renvoie vers **Réglages** avec le
message « La carte est désactivée ». **Rien n'est supprimé** : le code, les
données et la table `places` restent en place, et une seule case suffit à tout
rallumer — il suffit de réactiver le réglage, sans migration ni commande.

Depuis la 1.31.0, quand elle est active, la carte demande la connexion : un
visiteur est renvoyé vers `/connexion`, comme pour l'arbre. Pour la placer au quotidien
(chercher une commune, saisir une position à la main, retirer un lieu), voir
[6.17](#617-placer-les-lieux-sur-la-carte). Ce chapitre explique ce qui se
passe derrière, pour pouvoir la réparer ou l'expliquer un jour.

### 16.1 Aucune ressource extérieure

Trois choses ne sont **jamais** demandées à Internet :

1. le **fond de carte** : un fichier livré avec le site
   (`app/data/carte_fond.php`, 37 Ko), deux vues (France et monde entier) ;
2. la **liste des communes** (`app/data/communes-fr.tsv`, 8 942 lignes) ;
3. le **dessin** : le serveur écrit du SVG, que le navigateur affiche comme
   une image. Il n'y a ni bibliothèque JavaScript, ni tuile, ni police
   téléchargée.

C'est un choix de confidentialité (aucune adresse IP transmise à un tiers,
aucun pistage) autant que de robustesse : si la connexion du Pi tombe, la
carte continue de s'afficher.

Origine et licence du fond : Natural Earth 4.1.0, **domaine public**
(contours projetés et simplifiés par le projet). Détail complet, projection et
format des fichiers : [`docs/carte.md`](docs/carte.md).

### 16.2 Une seule migration à appliquer

La carte ajoute une table `places` (migration **012**). Comme toujours, elle
**ajoute** sans rien modifier :

```bash
cd ~/histoiredefamille
git pull
sudo bash deploy/install.sh          # applique 012 (et 013 si besoin)
```

Si vous préférez la commande seule :

```bash
php app/cli/migrate.php
```

La migration 012 ne touche ni aux personnes, ni aux familles : elle crée une
table neuve, avec un index unique (un même lieu ne peut pas être placé deux
fois) et un index sur les coordonnées.

### 16.3 Ce que voit la famille

- **Filtres** : deux listes déroulantes (branche, décennie de naissance) et le
  choix de la vue (France / monde). C'est un simple formulaire `GET` : l'adresse
  obtenue peut être mise en favori ou envoyée par courriel.
- **Points** : un point par lieu, dont la taille suit le nombre de personnes
  rattachées (4 px pour un lieu isolé, 15 px pour le lieu le plus peuplé).
- **Fiche d'un lieu** (`/lieu/12`) : qui y est né, qui y est décédé, quels
  mariages y ont été célébrés — uniquement les personnes visibles par la
  personne qui regarde.
- **Tableau** sous la carte : la même information, lisible à l'impression et
  par un lecteur d'écran.
- **Lien « Voir sur la carte »** depuis la fiche d'une personne : il apparaît
  quand le lieu de naissance, de décès ou de mariage de cette personne est
  placé.

### 16.4 Droits : la règle du site, sans exception

La carte compte les personnes avec la **même** condition que partout ailleurs
(`visibility_clause('person', …)`). Un visiteur non connecté ne compte que les
personnes publiques. Un lieu dont aucune personne n'est visible **n'apparaît
pas**, et sa fiche répond « Lieu introuvable » avec exactement le même texte
que pour un lieu inexistant : impossible d'en déduire qu'il existe.

Aucune règle d'accès n'a été modifiée pour ce lot.

### 16.5 Le cache

Pour rester vif sur le Pi, le résultat est gardé **5 minutes** dans
`storage/cache/carte/`, un fichier par groupe de droits et par filtres. Le
fichier ne contient que des noms de lieux et des **nombres** de personnes. Le
cache est ignoré dès qu'une personne ou un lieu change, et il est effacé quand
un lieu est placé ou retiré. Pour forcer un recalcul : ouvrir `/carte` avec
`?recalculer=1`. Pour tout vider à la main :

```bash
sudo rm -f /mnt/ssd/histoiredefamille/storage/cache/carte/*.json
```

(rien n'est perdu : le dossier se remplit au prochain affichage).

### 16.6 Vérifier

```bash
cd ~/histoiredefamille
php app/cli/tests.php carte
```

Les tests vérifient la projection (bords du cadre, points hors vue,
coordonnées impossibles), les suggestions de communes, les filtres, les droits
(un visiteur ne compte pas une personne privée), le cache et le fait que le
dessin ne contient ni `style`, ni script, ni adresse extérieure. Le jeu complet
(`php app/cli/tests.php`) reste la référence avant une mise en ligne.

---

## Licence

Code privé, réservé à l'usage familial. Aucune donnée de tiers n'est incluse.
Les contenus de démonstration (`app/cli/demo.php`) sont entièrement fictifs,
et se retirent d'une commande (`app/cli/purger-demo.php`, § 4.8).
