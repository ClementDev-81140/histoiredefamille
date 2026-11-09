# Journal des versions

Toutes les dates sont au format `AAAA-MM-JJ`.
Le projet suit un numéro de version simple : `majeure.mineure.correctif`.

## [1.38.1] — 2026-11-09

### Corrigé
- Accueil d'un compte connecté : sur ordinateur, la colonne « Dans la famille »
  (à droite de « À la une ») s'affichait vide dans les navigateurs récents. Un
  `<details>` fermé masque son contenu par `::details-content`
  (`content-visibility: hidden`) ; la 1.38.0 n'avait qu'une règle `display`,
  sans effet. La feuille de style rend maintenant ce contenu visible sur
  grand écran ; sur téléphone le bloc reste replié.
- Après la fusion de cette correction dans la branche principale, le
  nouveau contrôle `tools/navigateur/actions-famille-visible.mjs`
  (Chromium récent, texte mesuré à 1280 px, voir `docs/doublons.md`) a
  montré que le lien « Tout voir » du bas du bloc, prévu pour le
  téléphone, réapparaissait aussi sur ordinateur (conflit de
  spécificité avec la règle d'affichage forcé). Il est de nouveau
  masqué sur grand écran ; le contrôle échoue si l'un ou l'autre
  problème revient.

## [1.38.0] — 2026-11-09

### Chasse aux doublons sur tout le site
- **Tableau de bord** : l'avertissement « aucune sauvegarde » (et ses
  variantes « a échoué » / « date de plusieurs jours ») n'apparaît plus
  deux fois. Le bandeau présent en haut de toutes les pages de
  l'administration suffit ; les encadrés propres à la page sont retirés.
- **Accueil connecté** : le bloc « Dans la famille » était écrit deux
  fois dans la page (une version repliée pour le téléphone, une version
  ouverte pour le bureau, l'une masquée selon la largeur). Un seul
  `<details>` sert maintenant les deux largeurs : fermé sur téléphone,
  toujours déplié à l'écran. Aucun changement de contenu ni de droits.
- **Contrôle automatique réutilisable** : `rendre-tout.php` +
  `actions-doublons.mjs` parcourent toutes les pages de la matrice
  d'accès (cinq profils, 1280 et 390 px, thèmes papier et bleu) et
  signalent titres, liens, boutons, phrases d'aide et blocs affichés
  deux fois ; l'essai `php app/cli/tests.php doublons` couvre les pages
  clés. Les rares doublons volontaires (filtre « Tout » des listes,
  lien « frise chronologique » dans une phrase du calendrier, colonne
  « Sous la une » des deux accueils réglés dans Composition) sont
  listés et justifiés dans
  `tools/navigateur/doublons-liste-blanche.json`. Rapport complet :
  `docs/doublons.md`.
- Aucune migration, aucun changement de droits ni de menu :
  `git pull` suivi de `deploy/install.sh` suffit.

## [1.37.1] — 2026-11-09

### Un journaliste « avec validation » ne publie plus rien directement
- **Albums** : en 1.37.0, un journaliste « avec validation » créait un
  album visible tout de suite. Désormais, son album naît privé et
  « en attente » (invisible de tous les autres comptes : listes,
  compteurs, fiches), avec la visibilité choisie gardée comme suggestion,
  appliquée par l'administrateur au bouton « Valider » de la liste des
  albums (Administration → Albums photos). Son auteur le relit par son
  adresse directe et le retrouve dans « Mes propositions ».
- **Modifications directes refusées (403)** pour un journaliste
  « avec validation » : corriger un album, un événement, une fiche
  personne ou un lien de parenté. Il n'existe pas de file d'attente pour
  ces modifications ; le refus est net et expliqué à l'écran. Articles,
  documents et recettes gardaient déjà le texte publié intact (version en
  attente), événements créés toujours « en attente », comme avant.
- **Défaut « privé »** : les formulaires de création ouverts aux
  journalistes présélectionnent « Privé » (album, personne, événement ;
  les propositions le faisaient déjà). Un journaliste « sans validation »
  n'est pas touché.
- **Migration 025** : colonnes `albums.moderation_state` et
  `albums.suggested_visibility` en ajout seul (« publie » par défaut),
  plus le rattrapage demandé : les albums déjà créés en 1.37.0 par un
  journaliste dont le réglage actuel est « avec validation » repassent en
  attente et en privé, visibilité d'origine gardée comme suggestion.
  Aucune ligne supprimée ; les journalistes « sans validation » et les
  autres profils ne changent pas. Comme toujours, `migrate.php` écrit sa
  copie de sécurité avant d'appliquer.

## [1.37.0] — 2026-11-08

### Droits des journalistes réglables
- Deux réglages par journaliste, dans sa fiche de compte (Administration →
  Comptes), modifiables à tout moment par un administrateur, avec effet
  immédiat : le **contrôle** (« avec validation » — comportement historique —
  ou « sans validation » — publication directe) et le **périmètre**
  d'écriture (« toutes les branches » ou une sélection de branches
  cochées). La fiche affiche, pour un journaliste « sans validation »,
  l'avertissement « Ce journaliste publie directement, y compris en
  public. » Migration 024 en ajout seul : les comptes existants gardent
  exactement leur comportement tant qu'un administrateur ne change rien.
- La règle « un contenu relève d'une branche » est définie une seule fois
  (`app/lib/journalistes.php`, documentée dans `docs/droits-journalistes.md`) :
  rattachement explicite du contenu (`content_branches`) d'abord, branche
  de l'auteur sinon ; pour les personnes, seul le rattachement compte.
  Dans son périmètre, un journaliste peut créer, publier et corriger ses
  contenus et ceux des autres (articles, documents, recettes, événements,
  albums), ajouter et corriger des fiches personnes et des liens de
  parenté simples, et choisir n'importe quelle visibilité. Un contenu
  qu'il ne peut pas voir ne fuit pas en édition (404). La lecture ne
  change pas (`visibility_clause()` / `can_view()` inchangés).
- Un journaliste « sans validation » publie directement ; chaque action
  est notée dans le journal d'activité et l'ancienne version du contenu
  est gardée (`contenu_historique`). Les administrateurs disposent d'une
  page « Journal des journalistes » (Administration → Journal) pour
  relire ces actions et **dépublier** un contenu en un clic (privé,
  jamais supprimé). Sans validation ou non, un journaliste ne supprime
  rien et les pages d'administration (comptes, journaux de connexion,
  réglages, sauvegardes, conversions, apparence, validations des autres)
  lui restent fermées (403/404, y compris par adresse directe).

## [1.36.0] — 2026-11-08

### Vocabulaire — « Type » et « Thème »
- Le mot « catégorie » disparaît de l'interface : ce qui décrivait un sujet
  s'appelle désormais un **thème** (« Un thème regroupe des contenus de tous
  types qui parlent du même sujet. »), et ce qu'est un contenu (Article,
  Document, Photo, Vidéo, Recette, Témoignage-son) est son **type**, donné par
  le contenu lui-même. Administration « Catégories » → « Thèmes ». Les
  anciennes adresses `?categorie=` et `?categorie_id=` redirigent en 301 vers
  `?theme=` ; les noms internes du code ne changent pas.
- Cartes, une et fiches : chaque information une seule fois — le thème en
  surtitre, le type en petit dans les métadonnées, sans répéter un mot déjà
  visible. Les compteurs de thèmes ne comptent que les contenus visibles par
  le compte connecté. Onglets de la recherche étiquetés par type.

### Accueil — blocs seulement s'ils sont remplis
- « Petites annonces » devient « **Anniversaires à venir** » (anniversaires
  de naissance et de mariage des 30 prochains jours, jamais de décès,
  personnes invisibles pour le compte exclues) ; « Édito » devient « **Le mot
  de la famille** ». Les blocs annonces, accès, chiffres et nouveautés de
  l'accueil de la famille n'apparaissent que remplis : bloc vide = ni titre,
  ni cadre, ni phrase « rien à signaler ».

### Archives discrètes et dossiers d'archives
- Case « **Classer en archive** » (décochée par défaut) dans l'envoi par
  morceaux, les formulaires d'un document et d'une vidéo (administration) et
  les propositions des journalistes, avec la phrase : « Un document d'archive
  n'apparaît pas à la une et ne prévient personne. On le retrouve dans les
  dossiers d'archives et par la recherche. » Un document d'archive ne change
  ni de visibilité ni de droits (privé par défaut) ; il n'apparaît jamais à
  la une, jamais dans les nouveautés, jamais dans la gazette du mois, jamais
  en notification ni en pastille. On le retrouve dans Documents (filtre
  « Archives »), par la recherche, dans son album et dans son dossier. La
  bascule journal ↔ archive reste possible à tout moment par la fiche.
- **Dossiers d'archives** : un administrateur crée des dossiers (nom,
  description facultative, personne de l'arbre facultative) depuis
  Contenus → « Dossiers d'archives ». Un document d'archive se range dans
  **un** dossier, à l'envoi ou plus tard, et se déplace librement. Page par
  dossier listant le contenu selon les droits du compte ; un dossier sans
  rien de visible pour un compte est invisible pour lui (ni nom, ni nombre,
  réponse 404 identique à un dossier inexistant). Supprimer un dossier rend
  ses documents « sans dossier », sans jamais les supprimer. La fiche d'une
  personne liée propose ses dossiers, seulement ceux visibles pour le compte.
  Aucune entrée « Archives » au menu principal : l'accès passe par Documents
  (filtre et section « Archives et dossiers »). Une archive proposée par un
  journaliste reste soumise à validation, privée et invisible avant.
- Migration **023 en ajout seul** (table `dossiers`,
  `documents.est_archive`, `documents.dossier_id`, `uploads.archive`) : tous
  les documents existants restent « journal ». `install.sh` sauvegarde la
  base avant migration : `git pull` + `install.sh` suffisent.

### Menu principal
- Renommages seuls (structure et règles par profil inchangées) : « Arbre
  généalogique », « Témoignages (sons) », « Albums photos ». Une phrase
  d'aide d'une ligne sous le titre des grandes pages (Articles, Documents,
  Vidéos, Albums, Calendrier, Arbre, Recettes, Témoignages). Recherche et
  Tchat inchangés.

### Essais
- Nouvelle famille `tests.php archives36` (24 essais : migration, exclusions
  des vitrines, droits et non-fuite des dossiers, suppression sans perte,
  proposition d'archive en attente, vocabulaire, menu). Matrice d'accès
  régénérée : 206 routes, **0 anomalie**. Contrôle navigateur
  `tools/navigateur/actions-136.mjs` : 35 contrôles × 4 (papier/bleu ×
  1280/390 px), captures dans `docs/captures/navigateur-136-*`.

## [1.35.2] — 2026-11-08

### Modifié
- Administration : le menu complet du site (bandeau, titre, devise, menu
  principal, bandeau du compte) s'affiche au-dessus du tableau de bord, comme
  sur le reste du site. La petite barre « Voir le site / Mon compte /
  Déconnexion » est supprimée. L'en-tête est un seul fichier commun
  (`app/views/partials/entete_journal.php`) ; les règles CSS d'en-tête compact
  de l'administration disparaissent.

## [1.35.1] — 2026-11-08

### Corrigé — les vidéos d'archives à marque « M4A » n'étaient plus refusées à tort
Une vraie vidéo nommée « Bienvenue sur la lune.MP4 » était refusée à
l'envoi par morceaux : « Le contenu du fichier ne correspond pas à son
extension (type détecté : audio/mp4) ». **Cause** : le contrôle comparait
le type détecté par la signature (finfo) à une liste stricte par
extension — pour `.mp4`, seuls `video/mp4` et `application/mp4`
passaient. Or un conteneur ISO dont la marque est `M4A` (ou `qt`, `3gp4`…)
est annoncé `audio/mp4` / `video/quicktime` / `video/3gpp` alors qu'il
contient une vraie piste vidéo.

- La correspondance extension → types est maintenant raisonnée en
  **familles de conteneurs** : MP4/QuickTime/3GP (`audio/mp4`,
  `video/mp4`, `video/quicktime`, `video/x-m4v`, `video/3gpp`,
  `application/mp4`…), MKV, AVI (`video/x-msvideo`), MPEG (`video/mpeg`),
  MPEG-TS (`video/mp2t`), WMV (`video/x-ms-asf`), FLV, VOB, WebM. Revue
  des sons au passage : `.aac` et `.flac` sont acceptés, `.ogg` tolère
  `video/ogg`, `.m4a` aussi `audio/3gpp`.
- Nouvelles extensions autorisées (elles manquaient) : `.3g2`, `.mpg`,
  `.mpeg`, `.mts`, `.m2ts`, `.wmv`, `.flv`, `.vob`, `.aac`, `.flac`.
- Quand **ffprobe** est disponible (c'est le cas sur le Pi), il tranche
  sur les pistes réelles : une piste vidéo fait une vidéo, quelle que
  soit la marque ; un fichier **sans piste vidéo** sous une extension
  vidéo est refusé avec la phrase « Ce fichier a l'extension .MP4 mais
  le site n'y a trouvé que du son… ». Sans ffprobe, la signature de la
  famille suffit et la conversion décide ensuite — un fichier sans image
  finit alors marqué, dans son état de conversion, « ne contient pas
  d'image vidéo » (échec définitif, sans réessais inutiles).
- La sécurité ne bouge pas : aucun type passe-partout, un exécutable, un
  script, un PDF ou une archive renommés restent refusés (essais dédiés),
  fichiers hors racine web, aucune migration, aucun droit modifié.

### Conversion : le codec des MP4 est vérifié
Un MP4 de bon conteneur mais en **HEVC** (ou sans AAC) entre maintenant
dans la file quand ffprobe peut le voir ; un MP4 H.264 + AAC reste
intouché. Tous les formats peu courants du tableau ci-dessus entrent
dans la file existante (une conversion à la fois, `nice`, original
conservé, état visible sans bloquer l'envoi). La page Conversions
rappelle qu'une vidéo de 100 Mo peut prendre de longues minutes sur le
Pi — sans bloquer le site.

### Messages d'envoi en français simple
Le refus de contenu ne montre plus de type technique (il va au journal) :
« Ce fichier a l'extension .MP4 mais son contenu réel n'est pas celui
d'une vidéo… convertissez-le en MP4 (H.264) puis recommencez. » Même
traitement pour l'extension refusée (avec les formats possibles), le
fichier trop gros (avec l'action) et le fichier vide — côté serveur et
côté script d'envoi.

### Essais
- Envoi : 150 essais (dont le cas du rapport joué en protocole complet,
  les marques isom/mp42/qt/3gp4/avc1, les faux exe/script/PDF/archive,
  le son pur sondé, les messages). Conversion : 142 essais (chaque
  format en file, HEVC sondé en file, H.264 + AAC hors file).
- Suite complète : 1 570 essais, 1 569 réussis — l'échec unique
  (« arbre : la page retombe… ») est l'artefact de banc déjà documenté,
  la famille arbre passe seule (87/87), matrice 0 anomalie.
- Navigateur réel (Chromium) : `actions-envoi-media.mjs`, 7 contrôles à
  1 280 et 390 px — le fichier synthétique à marque M4A déposé sur
  `/admin/envoyer` aboutit, le refus d'un fichier vide est lisible ;
  captures dans `docs/captures/`. Le protocole y est simulé comme pour
  les autres contrôles d'envoi : le contenu réel est jugé par les essais
  PHP. Aucune migration : `git pull` puis `install.sh` suffisent.

## [1.35.0] — 2026-11-08

### Explorer l'arbre : zoom, déplacement, recentrage
La page `/arbre` gagne une barre d'outils « Exploration de l'arbre »
(au-dessus du dessin) : **Zoomer / Dézoomer** avec le niveau en
pourcentage (25 % à 250 %), **Voir à gauche / en haut / en bas / à
droite**, **Centrer la personne sélectionnée** (zoom conservé) et
**Voir tout l'arbre**. Au doigt et à la souris : glisser pour déplacer,
molette pour zoomer, pincement à deux doigts sur téléphone. Le cadre
fait environ 65 % de la hauteur de l'écran (entre 320 et 640 px), la
page n'a jamais de défilement horizontal, et Tab sort du cadre — aucun
piège au clavier. `prefers-reduced-motion` coupe l'animation.

Tout cela tient dans `public/assets/arbre-exploration.js`, chargé
uniquement sur `/arbre`, avec la CSP actuelle : les positions passent
par des propriétés CSS personnalisées posées en API CSSOM (vérifié dans
le navigateur : le transform calculé suit). **Si le script ne se charge
pas, les boutons sont cachés et l'arbre reste celui du serveur**,
complet et cliquable. Au chargement, la personne sélectionnée est
centrée ; sur téléphone, le zoom de départ montre le tronc et la
génération des parents.

### Parenté lisible sur chaque carte
- Chaque carte écrit sa relation avec la personne sélectionnée, en
  petit au-dessus du nom, et dans son nom accessible (« Casimir
  BERNARD, conjoint ») : Personne sélectionnée, Conjoint/conjointe,
  Parent, Grand-parent, Arrière-grand-parent, Enfant, Petit-enfant,
  Frère/sœur, Beau-parent, sinon Famille.
- Un conjoint est toujours un conjoint : deux personnes qui forment un
  couple ne sont jamais présentées comme frère et sœur, même si les
  données les disaient aussi enfants des mêmes parents. Et les ancêtres
  affichés sont ceux de la personne sélectionnée, jamais ceux de son
  conjoint venu d'une autre famille.
- Traits dessinés par le serveur (SVG, pourcentages calculés d'après
  les rangées) : trait en pointillés entre les deux membres d'un couple,
  trait vertical depuis le point central du couple, barre horizontale,
  puis un trait vers chaque enfant. **Aucun trait vers une carte
  absente** : une personne privée n'apparaît ni en nom, ni en lien, ni
  en nombre, ni en trait — les droits sont inchangés.

### Essais
- `tests_arbre.php` grandit (87 essais) : barre et boutons libellés,
  script externe, relations du jeu de démo, conjoint ≠ sœur, ancêtres du
  conjoint absents du dessin, segments de traits comptés pour un
  lecteur (2) et un administrateur (4).
- Suite complète : 1 530 essais, 1 530 réussis.
- Navigateur réel (Chromium) : `actions-arbre-exploration.mjs`,
  27 contrôles à 1 280 et 390 px, en papier et en bleu, 0 échec, et
  l'ancien contrôle du chêne (38 contrôles) toujours vert. Le
  pincement est synthétisé par le protocole CDP : à confirmer sur un
  vrai téléphone. Aucune migration : sur le Pi, `git pull` puis
  `deploy/install.sh` suffisent.

## [1.34.0] — 2026-11-08

### Corrigé — l'étiquette « Années 2020 » au-dessus des articles
La décennie affichée au-dessus du titre d'un article était déduite du
« millésime » de l'article : l'année écrite dans son titre ou son résumé,
à défaut **l'année de publication**. Un article d'actualité publié
aujourd'hui portait donc « Années 2020 », qui se lisait comme une date
erronée alors que la ligne de publication, plus bas, est correcte.
Désormais, l'étiquette n'existe que pour un article qui raconte un
souvenir daté (année dans le titre ou le résumé), sous la forme
« **Souvenir des années 1950** » — sur la page de l'article, ses cartes
(listes, accueil, « à lire aussi ») et la frise des décennies. Un article
sans date de souvenir n'affiche aucune étiquette, nulle part. La ligne
« Par … — date de publication » est inchangée.

### Calendrier : ajouter un événement sans passer par l'administration
- Bouton « **Ajouter un événement** » en haut de `/calendrier` pour les
  administrateurs (les événements se créaient déjà dans
  Administration → Calendrier depuis la 1.32.0, mais rien ne le disait
  sur la page). Formulaire simple : titre, date, type, visibilité
  (**privée par défaut**), lieu et note facultatifs. Modification et
  suppression restent dans Administration → Calendrier, comme avant.
- Un **journaliste** y voit « Proposer un événement » : sa proposition
  naît « en attente » et reste **invisible pour tous les autres comptes,
  partout** (calendrier, accueil, listes, compteurs), jusqu'au bouton
  « Valider » d'un administrateur (colonne « État » de la liste).
- Les événements gagnent un **type** (fête, réunion, voyage, messe du
  souvenir, anniversaire, autre), affiché dans le détail du calendrier.
- Le calendrier parle d'**anniversaires** : « Anniversaire de Prénom NOM
  (62 ans) », « Anniversaire de mariage de X et Y (40 ans) » — jamais de
  « naissances ». La frise chronologique, relevé historique, ne change pas.
- Les **décès quittent le calendrier par défaut**. Nouveau réglage
  administrateur « Afficher les dates de décès dans le calendrier »
  (Administration → Réglages, décochée par défaut) ; activée, l'entrée
  s'écrit sobrement « In memoriam : Prénom NOM ».
- Les droits des personnes sont inchangés : quelqu'un que le compte n'a
  pas le droit de voir n'apparaît ni en anniversaire, ni en mariage, ni
  en décès, ni en nombre.

### Corrigé — plus aucune ligne « Décès » vide
Sur la fiche d'une personne sans date de décès, la ligne « Décès : — »
a disparu, comme toutes les lignes de repères vides (naissance sans date
ni lieu, etc.) ; les champs ne réapparaissent que dans les formulaires
de saisie. Les dates de vie des listes et de l'arbre ne gardent plus de
tiret en attente (« 1901 » au lieu de « 1901 – »). Une date de décès
connue reste affichée telle quelle.

### Migration 022 (en ajout seul)
`events` gagne deux colonnes : `status` (« publie » par défaut — tous les
événements existants le deviennent, rien ne change pour eux) et `type`
(vide par défaut). Aucune ligne touchée, rien de supprimé. Sur le
Raspberry Pi, `git pull` puis `deploy/install.sh` suffisent : le script
lance les migrations comme d'habitude.

### Essais
- Nouvelle famille `tests_calendrier34.php` : 32 essais (étiquette,
  libellés, décès réglés, bouton selon le compte, proposition invisible
  puis validée, fiche sans décès).
- Suite complète : 1 490 essais, 1 489 réussis ; le seul échec
  (« arbre : la page retombe alors sur une personne visible ») existe à
  l'identique sur le code d'avant la 1.34.0 lancé sur le même banc, et
  la famille « arbre » passe seule (47/47) : ce n'est pas une régression.
- Matrice d'accès : 201 routes, 0 anomalie (document régénéré).
- Navigateur réel (Chromium) : `tools/navigateur/actions-ajouts34.mjs`,
  17 contrôles à 1 280 et 390 px, en papier et en bleu, 0 échec ;
  captures dans `docs/captures/`.

## [1.33.1] — 2026-11-08

### Modifié
- Accueil d'un visiteur : la phrase d'introduction et le gros bouton
  « Se connecter » ne sont plus affichés en tête de page. La page commence
  directement par la une ; « Se connecter » reste dans le menu. (Un site sans
  aucun contenu public garde son bouton et ses deux adresses.)

## [1.33.0] — 2026-11-07

### L'arbre généalogique devient un chêne gravé

La page `/arbre` change de visage : l'arbre n'est plus une suite de listes, mais
un **chêne gravé**. Les **ancêtres** sont dans les branches, la **personne
choisie et son conjoint** sur le tronc, les **descendants** dans les racines —
le tout sur une gravure de chêne servie par le site, dans le papier ancien du
journal.

Rien ne change dans les droits : `/arbre` reste **réservé aux personnes
connectées** (décision 1.31.0), et **aucune visibilité n'est assouplie**. Une
personne privée reste invisible **en nom, en lien et en nombre**, y compris
dans le sélecteur et dans le « voir plus » ; une fiche privée demandée dans
l'adresse ne remplace pas la personne par défaut, et la page ne dit pas qu'elle
existe.

#### 1. Trois générations d'ancêtres, deux de descendants

- **Branches** : parents, grands-parents, arrière-grands-parents (3 niveaux).
- **Tronc** : la personne centrale, `ET` son ou ses conjoints.
- **Racines** : enfants et petits-enfants (2 niveaux).

Chaque génération porte son **étiquette** (« Parents », « Grands-parents »,
« Arrière-grands-parents », « Enfants », « Petits-enfants ») et son **compte**
de personnes visibles.

#### 2. Un sélecteur de personne centrale

Un formulaire en haut de page (`GET /arbre`) met au centre la personne de votre
choix : une liste déroulante **filtrable**, remplie avec les seules personnes
que votre compte peut voir, et un bouton « **Voir cet arbre** ». Sans choix, la
page prend la personne visible la plus récemment modifiée qui a de la famille
autour d'elle : un premier écran n'est jamais vide. Le lien « Retour à l'arbre
par défaut » ramène à cette vue.

#### 3. « Voir plus » plutôt que du chevauchement

Huit personnes tiennent dans une génération. Au-delà, la page affiche
« **Voir les N autres personnes de la famille** » : un lien `?plus=1` qui
affiche la totalité, en autant de rangées qu'il faut — et propose alors
« **Voir moins** ». Les cartes ne se superposent jamais : leur largeur vient de
la rangée (`--1` à `--8`, moitiés, tiers, quarts, cinquièmes… huitièmes), pas
d'une position calculée.

#### 4. Téléphone : la gravure en bandeau, les cartes en colonne

À moins de **64 rem**, la gravure sort du dessin et devient un **bandeau
décoratif au-dessus** de l'arbre ; les cartes s'empilent en **une colonne**,
dans l'ordre ancêtres → tronc → descendants. Mesuré à **390 px** : une seule
colonne (les 18 cartes partagent la même abscisse, 14 px), aucune carte hors du
dessin, aucune superposition. Sur grand écran, la gravure est le **fond du
dessin**, dans un cadre aux proportions de l'image (2 / 3) : à **1 280 px**, les
cartes se répartissent en **six rangées** à l'intérieur du cadre.

#### 5. PHP, HTML et CSS seuls — et la même règle de sécurité

La page est écrite **entièrement par le serveur** : aucune position en style en
ligne, aucun script en ligne, aucune ressource extérieure (gravure servie depuis
`/assets/`, polices du site). Le déplacement dans l'arbre ne dépend donc plus
d'aucun script : tout se fait au clavier, avec un **contour de focus visible**
sur chaque carte. L'ancien explorateur (panneau à boutons) n'est plus affiché ;
**aucune route n'est retirée** : `/api/arbre` et son code restent en place.

La gravure est livrée en **WebP** (`public/assets/arbre-chene.webp`, 860 × 1 290,
**261 Ko**) avec un **repli JPEG** (`public/assets/arbre-chene.jpg`, 274 Ko),
publiée par `<picture>` — jamais une image extérieure. Le fichier de départ
n'est pas dans le dépôt ; la façon de le remplacer est décrite dans
`docs/arbre-chene.md`.

#### Ce qui a été vérifié

- **Essais PHP** (famille `arbre`, 47 essais, 47 réussis) : rendu dédié avec
  trois générations d'ancêtres et deux de descendants ; visiteur renvoyé vers la
  connexion ; personne privée invisible (nom, lien, nombre, sélecteur) pour un
  lecteur, et visible pour un administrateur ; racine privée ignorée sans le
  dire ; huit cartes puis « Voir les 2 autres personnes de la famille », dix
  cartes en deux rangées avec « Voir moins » ; aucun `style=`, aucun `<script>`
  en ligne, aucune ressource extérieure dans le HTML ; un seul `h1` ; la base
  est rendue intacte.
- **Vrai navigateur** (Chromium, `tools/navigateur/actions-arbre-chene.mjs`,
  **38 contrôles** par largeur et par palette) : **38/38 à 1 280 px** et
  **38/38 à 390 px**, en **papier** et en **bleu** ; pas de superposition
  (rectangles comparés), une colonne sur téléphone et des rangées sur grand
  écran, gravure chargée uniquement au-dessus de 1 024 px et bandeau au-dessus
  sur téléphone, contraste ≥ 4,5 sur les noms (15,6) et les légendes (7,0) —
  légendes désormais posées sur un **papier opaque** pour ne pas dépendre des
  hachures —, focus clavier visible, aucune sortie du dessin, aucune erreur
  JavaScript. Captures dans `docs/captures/` (ordinateur et mobile, un enfant et
  huit enfants).

#### Ce qui n'a pas pu être vérifié ici

L'installation sur le Raspberry Pi (`deploy/install.sh` en root, `systemctl`),
Apache et Cloudflare, la sauvegarde `/mnt/sauvegarde`, l'impression réelle et
les lecteurs d'écran. La **rapidité** n'a été mesurée que sur le banc d'essai
(PHP en WebAssembly : environ 100 ms pour 17 cartes) ; sur le Pi, PHP natif et
SQLite devraient être bien plus rapides, mais je ne l'ai pas mesuré.

## [1.32.1] — 2026-11-07

### Un accueil public sans tuiles vides

Dans « Dans le journal », un contenu sans image laissait une **tuile grise** à
la place de la vignette, surmontée d'un bloc de rubrique. Sur la page d'accueil
d'un visiteur, cela faisait des trous : un article sans photo, une page
d'information, une photo dont la miniature n'a pas encore été fabriquée
occupaient la place d'une image qui n'existait pas — et, sur téléphone, une
carte sur deux semblait cassée.

Trois règles, tenues par les gabarits et par des **classes CSS ajoutées** :
aucune classe existante n'est renommée, aucun attribut `style`, aucun script en
ligne, et la règle de sécurité du site ne bouge pas.

#### 1. Dans la grille : une carte texte seule

Un contenu **sans image** donne une carte `une-carte une-carte--texte` : le
**titre**, la **date**, l'**extrait** quand il y en a un — et rien d'autre.
**Aucune zone d'image** n'est réservée, **aucune tuile grise** n'est peinte,
**aucun bloc de rubrique** ne figure sous la carte. La règle vaut pour tout
contenu qui n'a pas d'image : article sans photo, page d'information, document
ou photo sans miniature.

#### 2. Dans « À la une » : le texte prend toute la largeur

L'élément de la une porte `une-grande__article--sans-image` quand il n'a pas
d'image ; à partir de 48 rem, il passe d'une grille « image + texte » à **une
seule colonne**. Le texte ne laisse plus de **colonne vide à droite** : mesuré
à 1 048 px sur 1 048 px à 1280 px, 363 px sur 363 px à 390 px.

#### 3. Vidéo sans miniature : le repère de la 1.32.0, jamais un carré

Une vidéo dont la miniature n'a pas encore été extraite garde le repère de la
1.32.0 : une vignette `une-carte__vignette--video` sur la couleur du site (fond
`--surface`, filet en bas) avec l'**icône de lecture** `une-carte__lecture`
(« ▶ », masquée aux lecteurs d'écran), et **aucune balise d'image**. Dès que
`php app/cli/miniatures-videos.php --executer` a fabriqué l'image, la carte
reprend la vraie miniature, comme avant.

#### Ce qui ne change pas

- **Aucune règle d'accès** n'est touchée, et **rien de nouveau n'est rendu
  public** : seuls changent l'aspect des cartes et la largeur du texte.
- **Aucune classe n'est retirée** : la règle `.une-carte__vide` de la 1.31.0
  reste dans la feuille de style, même si plus aucun gabarit ne la pose.
- **Aucune migration**, aucune colonne, aucun réglage de plus ; ni style ni
  script en ligne : la politique de sécurité du contenu reste identique.

#### Un défaut repéré, laissé tel quel

Sur la une et sur les cartes **avec** image, l'étiquette de rubrique et le
« genre » affiché en fin de ligne disent la même chose pour un article, une
recette ou une page : on lit « ARTICLES » puis, dans la même ligne, « Articles »
(le genre d'une photo ou d'une vidéo, lui, est bien distinct : « Photo »,
« Vidéo »). Ce doublon vient de la 1.31.0 et n'a rien à voir avec les tuiles
vides ; il n'a pas été touché ici pour ne pas mêler deux sujets dans une même
livraison. Il est noté dans `TODO.md`.

#### Vérifications

- **10 essais nouveaux** dans `app/cli/tests_une.php` (famille « une », qui
  compte désormais **48 essais**) : la une sans image occupe toute la largeur
  et n'a ni image ni zone d'image ; c'est bien l'article sans image qui la
  tient ; un article sans image donne une carte texte seule (aucun `<img>`,
  aucune vignette, aucun bloc de rubrique, aucune tuile) ; la carte porte
  titre, extrait et date ; une page d'information suit la même règle ; une
  photo sans vignette ne montre aucune image cassée ; une vidéo sans aperçu
  garde le repère de lecture et une vidéo avec aperçu sa miniature ; aucune
  tuile nulle part ; aucune balise d'image sans source dans la page.
- Familles : « accueil » **49/49** (dont les essais de la 1.31.1 fusionnée),
  « une » **48/48**, suite complète **1 410 essais, 0 échec** (1 395 en 1.32.0,
  plus 10 pour les cartes sans image et 5 pour la redirection après
  connexion).
- Navigateur réel (Chromium), **112 contrôles** : le nouveau
  `tools/navigateur/actions-une-sans-image.mjs` (28 contrôles) joué à
  **1280 px** et à **390 px**, palette **papier** et palette **bleu**. Ce que
  le navigateur a vu : aucune carte sans image ne contient de balise `img`, de
  vignette ou de bloc de rubrique ; **aucun descendant d'une carte n'a la
  couleur `--papier-moyen`** (la tuile grise, mesurée et non devinée) ; chaque
  carte porte son titre, sa date et son extrait ; le texte de la une est aussi
  large que l'article ; l'icône de lecture d'une vidéo sans miniature est
  visible, sur fond `--surface` ; aucune image cassée, aucun `style=`, aucun
  script en ligne, aucune ressource extérieure, aucun débordement horizontal,
  aucune erreur JavaScript.
- Captures dans `docs/captures/` : `ordinateur-` / `mobile-`
  `accueil-sans-image.png` et les mêmes en `-bleu` (4 fichiers).
- **1.31.1 fusionnée** au passage : après la connexion, **tout le monde** —
  lecteur, journaliste, administrateur — arrive sur l'accueil du journal
  (`home_for_account()` renvoie `/`). Le tableau de bord reste à un clic par
  « Administration ». Le paramètre `next` n'est pas touché : une adresse locale
  connue est conservée, une adresse externe reste refusée. **Aucun droit
  modifié** ; 6 essais dans la famille « accueil ».
- **Non vérifié ici** : un vrai Raspberry Pi, un vrai téléphone, un lecteur
  d'écran et l'impression papier — seuls Chromium et deux largeurs de fenêtre
  ont été mesurés. Le doublon d'étiquettes de la 1.31.0 reste ouvert (voir
  plus haut).

## [1.32.0] — 2026-11-07

### Cinq améliorations : photo ancienne, image des vidéos, articles sans photo, carte fermée, catégories et calendrier

Cinq demandes du propriétaire, d'un seul tenant. Aucune règle d'accès n'a été
assouplie, **tout contenu nouveau est privé par défaut**, aucune classe CSS
n'a été renommée, les migrations sont **en ajout seul**, et rien ne s'affiche
par un attribut `style` ni par un script en ligne.

#### 1. L'aspect « photo ancienne »

*Administration → Apparence du journal* reçoit un réglage de plus : **Aspect
« photo ancienne »** — **Aucun** (le défaut), **Doux** ou **Marqué**. Il donne
aux photos un rendu sépia, de plus en plus appuyé.

- **Un filtre d'affichage, jamais une retouche** : le fichier d'origine n'est
  pas modifié. Les téléchargements et l'impression restent **en couleurs**, et
  le lien « **Télécharger l'original** » de la fiche donne bien l'image
  d'origine.
- Appliqué **partout où une image du site apparaît** : vignettes des listes,
  grandes images, albums, article à la une, accueil.
- **Par des classes CSS seulement** (`aspect-photo--doux`,
  `aspect-photo--marque`) : aucun `style=`, la règle de sécurité du site
  (`style-src 'self'`, pas d'inline) reste intacte, et l'imprimante ne reçoit
  pas de filtre.
- **Chaque photo peut s'écarter du réglage** : sur sa fiche, trois choix —
  *Suivre le réglage du site* (le défaut), *Forcer l'aspect*, *Désactiver*.
  Une valeur inconnue en base retombe sur « suivre ».
- **Un aperçu en direct** montre le résultat sous les trois boutons ; sans
  JavaScript, il montre le réglage déjà enregistré.

Migration `019_aspect_photo.sql` : une colonne
(`documents.vintage_aspect`, défaut `suivre`), rien d'autre.

#### 2. Une image pour les vidéos, à la place du carré gris

Une vidéo dont on n'avait pas d'image s'affichait comme un carré gris. Elle
reçoit maintenant une **vraie image extraite du film** :

- **extraction par ffmpeg** à **une seconde** du début, ou à **10 % de la
  durée** si le film est plus court qu'une seconde de marge ;
- **réduite comme les autres miniatures** (JPEG 480 px), rangée **avec elles**
  dans le dossier des miniatures ;
- fabriquée **à la fin de l'envoi** (MP4 déjà lisible) ou **à la fin de la
  conversion** (film reconverti), sans intervention ;
- **commande pour l'existant** : `php app/cli/miniatures-videos.php` liste ce
  qui manque, `--executer` traite **une vidéo à la fois** (relançable,
  `--lot=N` pour un plus grand lot), ffmpeg en **priorité basse** ;
- **ffmpeg absent, ou extraction en échec** : rien n'est cassé — la carte
  affiche une **icône de lecture** sobre (pas de carré gris, pas d'image
  cassée) et la page **Conversions** porte un message clair avec la commande
  d'installation ;
- la **miniature suit exactement la visibilité de la vidéo** : demandée par
  une personne qui n'a pas le droit de voir la vidéo, elle répond la **même
  page « introuvable »**, au même code ; on ne peut pas déduire l'existence
  d'un film par son image ;
- l'**administrateur peut envoyer une image de couverture** depuis la fiche de
  la vidéo : elle remplace l'image extraite, et le fichier envoyé pour la
  fabriquer n'est jamais servi tel quel.

#### 3. Un article sans photo n'a plus de cadre vide

Partout — accueil, « À la une », listes, recherche, gazette, page de l'article,
impression — un article sans photo n'affiche **aucun carré, aucune lettre,
aucun cadre vide** : la mise en page se réorganise autour du texte, sans trou.
Les trois gabarits (*Journal*, *Simple*, *Grande photo*) sont conservés ; un
article réglé en « Grande photo » **sans photo retombe sur « Journal »**, sans
image. Aucune balise d'image, aucun élément de remplacement n'est écrit dans le
HTML (vérifié par les essais et par le navigateur).

#### 4. La carte est fermée par défaut

Nouveau réglage **« Carte des lieux »** dans *Administration → Réglages*,
**décoché au départ**. Tant qu'il l'est :

- le lien « **Carte** » disparaît de **tous** les menus, de l'accueil et des
  fiches (« Voir sur la carte ») ;
- « **Lieux à placer** » quitte le menu d'administration ;
- `/carte` et `/lieu/{id}` répondent la page « **introuvable** » (404) **pour
  tout le monde, administrateur compris** ;
- `/admin/lieux` **redirige vers Réglages** avec « La carte est désactivée ».

**Rien n'est supprimé** : le code, les données et la table `places` restent en
place, et une seule case suffit à tout rallumer (aucune migration, aucune
commande). `app/cli/verifier-mise-en-ligne.php` ne signale plus aucun
avertissement lié à la carte quand elle est fermée.

#### 5. Les catégories servent aux six types de contenu

**Ce que disait le modèle, avant d'y toucher** (c'était la première demande) :
une catégorie ne servait **qu'aux documents**, et un document n'en avait
**qu'une** (`documents.category_id`) ; les articles avaient un simple **texte**
de rubrique (`articles.category`), et les albums, photos, vidéos et recettes
n'avaient **rien**. Deux notions différentes, donc, à ne pas confondre : la
**rubrique** d'un article (texte libre, listée par la page Articles) et la
**catégorie** (table `categories`).

Désormais :

- une catégorie s'applique à **un ou plusieurs types** — article, document,
  photo, album, vidéo, recette — et porte un **ordre d'affichage**. Dans
  *Administration → Catégories*, les six cases sont **cochées par défaut**
  pour une catégorie neuve ;
- **tous les formulaires** proposent les cases « **Catégories** » : article,
  document, photo, album, vidéo, recette, **envois par morceaux** (y compris
  le bouton « **Créer le document** » des fichiers reçus) et « **Ajouter des
  photos à un album** ». Seules les catégories du type sont proposées, et
  **plusieurs choix** sont possibles ;
- sur « **Envoyer des fichiers** » et « **Ajouter des photos à un album** »,
  les catégories cochées s'appliquent à **tout le lot** ;
- **filtre par catégorie** (formulaire `GET`, sans JavaScript) sur les listes
  publiques — `/documents?categorie=`, `/videos?categorie=`,
  `/recettes?categorie=`, `/albums?categorie=`, `/articles?categorie_id=` — et
  sur les listes d'administration ;
- le **compteur ne compte que les contenus visibles par la personne** : un
  visiteur ne peut déduire ni l'existence d'un contenu privé par un nombre, ni
  celle d'une catégorie vide (une catégorie dont rien ne lui est visible
  n'apparaît pas du tout) ;
- une valeur d'adresse inventée (`?categorie=999999`, ou la catégorie d'un
  autre type) est **ignorée** : aucun filtre n'est inventé à partir de l'URL.

Migration `021_categories_types.sql`, **en ajout seul** : une colonne
(`categories.kinds`, défaut « les six types »), une table de liaison
(`category_links`), et la **recopie** des catégories déjà posées sur les
documents — **aucune catégorie perdue**, l'ancienne colonne
`documents.category_id` restant tenue à jour pour les écrans qui la lisent.

#### 6. Le calendrier reçoit de vrais événements

**Ce que disait l'existant** : le calendrier était **entièrement dérivé de
l'arbre** (naissances, mariages, décès de `persons` et `families`), filtré par
la visibilité, et « Dans la famille » s'en nourrissait ; il n'existait **aucune
table d'événements**.

*Administration → Calendrier* ouvre maintenant la liste des événements de
famille, avec « **Nouvel événement** » : titre, date de début, **heure
facultative**, **fin facultative**, lieu en texte libre, description,
**visibilité** et **répétition annuelle facultative**. Modification, et
**suppression avec confirmation** dans le navigateur (définitive).

- **Un nouvel événement est PRIVÉ** : personne ne le voit tant que
  l'administrateur n'a pas choisi « Famille », « Branches précises » (avec les
  branches, kind `event` dans `content_branches`) ou « Public ».
- Un événement **annuel revient chaque année** à la même date ; un événement
  sans répétition n'existe que dans son année.
- Il apparaît dans le **calendrier** du mois (point du jour, détail sous la
  grille, mention « événement de famille ») et dans « **Dans la famille** »
  quand sa date approche — toujours selon la visibilité de la personne qui
  regarde.
- Les dates de l'arbre continuent de s'afficher comme avant : les deux sources
  se complètent.

**Limite assumée** : les événements se créent dans l'administration, pas depuis
un compte de journaliste. Le mécanisme de proposition (articles, documents,
recettes) n'a **pas** été étendu aux événements : ses colonnes de modération
sont posées table par table, et l'étendre demanderait une migration et un écran
de plus. C'est dit ici pour que personne ne le cherche.

Migration `020_evenements.sql` : une table `events`, **visibilité privée par
défaut**, aucune donnée existante touchée.

#### 7. Deux défauts trouvés et corrigés au passage

- Les essais « Une » ont trouvé, en cours de route, une **erreur SQL** dans la
  requête `UNION ALL` de la une publique (les colonnes de deux branches ne
  correspondaient plus depuis l'ajout de l'aspect photo) : l'accueil public
  d'un visiteur tombait sur une page d'erreur. Requête alignée, essais verts.
- Un formulaire bricolé pouvait envoyer à une fiche la **catégorie d'un autre
  type** : depuis, les catégories retenues sont filtrées sur le type réel de la
  fiche, et l'ancienne colonne `documents.category_id` n'enregistre jamais
  celle d'un autre type.

#### Vérifications

- Suite complète : **1 395 essais, 0 échec**. Depuis la 1.31.0, **85 essais
  nouveaux** : 17 pour les « catégories par type », 20 pour les « événements du
  calendrier », 3 sur les migrations 019 à 021, et 45 pour les lots 1 à 4
  (aspect 13, articles sans photo 8, carte fermée 11, miniatures de vidéos 13),
  le tout dans des familles qui se lancent seules (`php app/cli/tests.php`).
  Couverture : types par défaut,
  restriction à certains types, plusieurs catégories par contenu, catégorie
  d'un autre type ignorée, compteur d'un visiteur contre celui d'un
  administrateur, catégorie vide invisible, filtres publics, cases des cinq
  formulaires (document, vidéo, article, album, recette), enregistrement d'une
  vidéo, migration sans perte, colonne et liaison synchronisées ;
  événement privé par défaut, quatre profils (visiteur, famille,
  journaliste, administrateur) contre quatre visibilités, calendrier du mois,
  « Dans la famille », répétition annuelle, événement non annuel limité à son
  année, création et suppression par les vraies pages (avec et sans jeton
  CSRF), refus d'un compte non administrateur.
- Matrice d'accès régénérée : **198 routes**, **792 vérifications** (route ×
  profil), **0 anomalie**, **258 contrôles ciblés** sans un échec — les six
  routes `/admin/calendrier…` y entrent, avec la même règle que le reste de
  l'administration, et les notes du rapport disent désormais ce que répondent
  `/carte` et `/lieu/{id}` carte fermée (404 pour tous) comme carte ouverte.
- Navigateur réel (Chromium), **142 contrôles** : `actions-une-publique.mjs`
  (18 à 19 contrôles par passage) et le nouveau `actions-132.mjs`
  (**17 contrôles**), à **1280 px** et à **390 px**, palette **papier** et
  palette **bleu**. Ce que le navigateur a vu : deux vidéos **sans image**
  n'affichent **ni carré gris ni balise d'image**, seulement l'icône de lecture
  (fond de l'aperçu = papier, pas de gris) ; deux articles **sans photo**
  n'ont aucun cadre, aucune lettre, aucun élément de remplacement, et gardent
  une hauteur lisible ; toutes les images présentes sont réellement chargées ;
  aucun attribut `style=`, aucun script en ligne, aucune ressource extérieure,
  aucun débordement horizontal, aucune erreur JavaScript ; la une publique,
  l'accueil vide et la page « introuvable » d'un contenu privé se comportent
  comme en 1.31.0.
- Captures dans `docs/captures/` : `ordinateur-` / `mobile-`
  `articles-sans-photo.png`, `videos-sans-image.png`, et les mêmes en `-bleu`
  (8 fichiers), plus les captures de la 1.31.0.
- `app/cli/verifier-mise-en-ligne.php` gagne un contrôle « **Schéma de la base
  à jour** » : une mise à jour du code sans migration est signalée « À
  CORRIGER » avec la commande à lancer — sans quoi le calendrier tomberait en
  erreur sur une base non migrée.
- **Non vérifié ici** : un vrai `ffmpeg` (absent du banc : extraction et
  conversion essayées avec un ffmpeg simulé), un vrai Raspberry Pi, un vrai
  lecteur d'écran, l'impression papier, et le rendu sur un vrai téléphone —
  seuls Chromium et deux largeurs de fenêtre ont été mesurés.

## [1.31.1] — 2026-11-07

### Après la connexion, tout le monde arrive sur l'accueil

Un administrateur était envoyé sur le tableau de bord, un lecteur sur « Espace
famille ». Tous les profils (lecteur, journaliste, administrateur) arrivent
maintenant sur l'accueil du journal (`/`). Le tableau de bord reste à un clic,
par « Administration » dans le menu.

- `home_for_account()` renvoie `/` pour tout le monde.
- Le paramètre « next » est inchangé : une adresse locale connue est conservée,
  les adresses externes (`https://…`, `//…`, `/\…`) restent refusées.
- 6 essais ajoutés (famille « accueil »). Aucun droit modifié.

## [1.31.0] — 2026-11-07

### Une seule Une de journal, et un menu de visiteur réduit

Un visiteur qui arrivait sur le site trouvait une page d'accueil qui n'était pas
un journal : une phrase, une ligne de chiffres, deux boutons — « Consulter » et
« Se connecter » —, puis des rubriques à découvrir une par une, alors que
**quatre autres rubriques** figuraient déjà dans son menu. Le contenu public,
lui, n'était nulle part : il fallait ouvrir les listes pour savoir ce qui avait
été publié.

Décision du propriétaire : **une seule Une**, celle du journal, et **deux
entrées de menu** — « Accueil » et « Se connecter » (le bouton mène à
`/connexion`). L'accueil public fait
désormais office de « Consulter », et il présente **tout le contenu public du
site**, du plus récent au plus ancien.

#### 1. Le menu d'un visiteur : Accueil et Connexion

Le menu d'une personne non connectée ne contient plus que **« Accueil »** et
**« Se connecter »**. « Consulter » et « Carte » n'y sont plus : la première menait
à la liste des documents que l'accueil montre maintenant lui-même, la seconde
n'était pas une lecture.

Le menu d'un **compte connecté ne change pas** d'un caractère : mêmes rubriques,
même ordre, mêmes droits.

Au passage, la mise en page n'interroge plus la base pour des rubriques qui ne
figurent pas dans le menu d'un visiteur : **trois requêtes de moins** sur chaque
page publique — ce qui compte sur le Raspberry Pi.

#### 2. La carte et les fiches de lieu demandent la connexion

`/carte` et `/lieu/{id}` renvoient un visiteur vers **`/connexion`** (code
**303**, comme `/arbre` depuis la 1.24.0), avec le chemin de retour. Un compte
connecté ouvre la carte et les fiches comme avant.

**Aucun autre droit ne change.** La matrice d'accès, régénérée, le montre route
par route : pour un visiteur, `/articles`, `/documents`, `/albums`, `/videos`,
`/recherche`, `/calendrier`, `/recettes`, `/frise`, `/temoignages` répondent
**200** — exactement comme avant la version. Rien n'a été rendu plus ouvert :
ce sont les deux seules routes qui bougent, et elles se ferment.

#### 3. L'accueil d'un visiteur est la Une du journal

Sous le bandeau de la famille (titre, date, numéro), la page commence par la
phrase d'accueil et par le gros bouton **« Se connecter »**, dans le premier
écran. Puis :

- **À LA UNE** : le contenu public le plus récent, **en grand** — rubrique,
  titre, date, type, vignette et résumé. Un administrateur peut en choisir un
  autre (voir plus bas) ;
- **la grille du journal** : tous les autres contenus publics, avec vignette,
  titre, date et type. Dès qu'il y en a **plus de six**, ils sont regroupés par
  rubrique — Articles, Photos, Vidéos, Documents, Recettes, Pages d'information —
  comme les pages intérieures d'un journal ;
- **« Voir plus »** (un simple lien `GET`, sans JavaScript) au-delà de
  **24 contenus**, avec le nombre de pages et le total.

Chaque contenu mène à **sa page publique existante** (`/articles/{slug}`,
`/videos/{id}`, `/recettes/{slug}`, `/pages/{slug}`, sinon `/documents/{id}`).
Le seul autre lien de la page est « Se connecter ». Aucune ressource externe,
aucun style ni script en ligne : les nouvelles classes (`.une-intro`,
`.une-grande`, `.une-grille`, `.une-carte`, `.une-rubrique`, `.une-vide`) sont
dans `public/assets/style.css` ; **aucune classe existante n'a été renommée**.

#### 4. Choisir le contenu de la Une

Sur **Administration → Composition des pages**, un nouveau réglage :
« **À la une du site public** ». La liste ne contient que des **contenus
publics** ; « Automatique » (le défaut) garde le plus récent. Le choix est
revérifié à chaque affichage : un contenu repassé en privé, en attente de
validation ou encore en conversion ne s'affiche pas — la page reprend alors le
plus récent toute seule, sans erreur. Le bandeau de la une dit « Choisi par la
rédaction » quand le contenu a été choisi, « Le contenu public le plus récent »
sinon.

L'accueil d'un visiteur n'est plus composé de blocs : il ne suit donc plus la
composition des pages. Les **quatre blocs de l'ancien en-tête public** (« les
deux entrées », « Également publié », « le site en chiffres », « le mot sur
l'espace réservé ») ne s'affichaient plus pour personne depuis que l'accueil
d'un visiteur est fixe : ils sont **retirés de la page d'administration**, où
ils proposaient des boutons sans effet. Les quatre blocs de l'accueil des
comptes connectés restent, inchangés, et la page le dit en une phrase.

#### 5. Quand il n'y a aucun contenu public

Une Une **sobre**, sans bloc vide ni rubrique fantôme : la phrase d'accueil, le
titre « Le journal paraîtra bientôt », une explication d'une phrase, le gros
bouton **« Se connecter »**, puis les deux adresses — **contact@** pour écrire,
**support@** pour demander un compte — en liens `mailto:` ordinaires.

#### 6. Rien d'autre ne bouge

- **L'accueil d'un compte connecté est celui de la 1.29.0** : bandeau, une,
  « Dans la famille », documents, « À lire ». La page est identique (les essais
  d'accueil, de composition et de carte le vérifient).
- **Aucun droit nouveau ni élargi** ; les pages publiques existantes gardent
  leurs règles.
- **Aucune migration**, aucun réglage à faire : la version s'installe seule.
- L'adresse de contact reste dans le pied de page de toutes les pages ; la
  phrase « Nous écrire » de l'ancienne vitrine a disparu avec elle (une seule
  fois, donc, dans le pied de page).

#### 7. Un défaut corrigé au passage

Le test de fuite demandé par le propriétaire a trouvé une vraie faille :
`visibility_clause()` appliquait la règle de modération des propositions
(`proposition_clause()`) aux **comptes connectés**, mais pas à un **visiteur
non connecté**. Un article **en attente de validation** pouvait donc apparaître
sur la Une publique. La branche « visiteur » applique désormais la même règle
que les autres : attente de validation, visibilité, conversion des vidéos —
mêmes conditions pour tout le monde. Vérifié par un essai qui crée un contenu
privé, un contenu famille, un contenu branches, un contenu en attente et une
vidéo en conversion, puis lit la page d'un visiteur : **aucun de ces contenus
n'apparaît**, ni en vignette, ni en titre, ni dans le code de la page, ni dans
une adresse d'image.

#### Vérifications

- Suite complète : **1 310 essais, 0 échec**, dont **42 nouveaux** : la famille
  « Une » (38 essais), deux essais de composition des pages, un sur la carte
  (un compte de famille l'ouvre) et un sur le pied de page. Contenu public le
  plus récent en tête, choix de l'administrateur respecté puis abandonné si le
  contenu cesse d'être public, regroupement par rubrique au-delà de six
  éléments, « Voir plus » et pagination, accueil vide, document sans vignette
  qui n'affiche pas d'image cassée, test de fuite des neuf contenus réservés,
  menu d'un visiteur réduit à deux entrées, menu d'un compte connecté
  inchangé.
- Matrice d'accès régénérée : **192 routes**, **768 vérifications** (route ×
  profil), **0 anomalie** ; `/carte` et `/lieu/{id}` y apparaissent en **303**
  pour un visiteur et **200** pour les trois profils connectés.
- Navigateur réel (`tools/navigateur/actions-une-publique.mjs`, Chromium) :
  **19 vérifications** à **1280 px** et à **390 px**, palette **papier** et
  palette **bleu** — soit 76 contrôles : deux entrées de menu et pas une de
  plus, bouton « Se connecter » entier dans le premier écran (54 px de haut),
  une en grand avec vignette chargée, grille regroupée par rubrique au-delà de
  six contenus, **aucun lien** hors contenu public / connexion / contact,
  aucun style ni script en ligne, aucune ressource extérieure, aucun
  débordement horizontal, aucune erreur JavaScript, accueil vide sans une ni
  grille, contenu privé par adresse directe affichant exactement la page
  « introuvable » (sans son titre ni son adresse).
- Captures dans `docs/captures/` : `ordinateur-` / `mobile-une-publique.png`,
  `-une-vide.png`, `-contenu-public.png`, `-contenu-prive-404.png`, et les
  mêmes en `-bleu` (16 fichiers).
- **Non vérifié ici** : un vrai lecteur d'écran sur la grille de la Une,
  l'impression de la page, et le rendu sur un vrai téléphone — seuls Chromium
  et deux largeurs de fenêtre ont été mesurés.

## [1.30.0] — 2026-11-07

### Verser dans un album les photos déjà sur le site

Pour ranger dans un album une photo **déjà envoyée**, il fallait la renvoyer. Le
site en gardait alors deux fichiers pour la même image, et deux visibilités à
tenir à jour — celle de l'ancienne fiche et celle de la nouvelle. La page
**Ajouter des photos** d'un album propose maintenant, sous le bloc d'envoi, une
grille de vignettes à cocher : le document entre dans l'album **sans être
renvoyé**, sans copie, et **sans que sa visibilité change**.

#### 1. Une grille sous le bloc d'envoi

La section « **Ajouter depuis les documents du site** » liste les photos déjà
présentes dans *Documents & photos*, puis les autres documents. Chaque vignette
porte le **titre**, la **date**, le **type** et le **badge de visibilité** de la
famille (Privé, Branches, Famille, Public) — ceux des listes de
l'administration, sans nouvelle couleur ni nouvelle classe de badge.

Les filtres sont un **formulaire GET** ordinaire, donc utilisable sans
JavaScript :

- **recherche** par titre *ou* par étiquette ;
- **type** : Photos, ou Tous les documents ;
- **visibilité** et **catégorie** ;
- « **Pas encore dans cet album** », **coché au départ**.

Une case décochée montre aussi ce qui est **déjà dans l'album** : ces documents
sont marqués « Déjà dans l'album » et leur case est **désactivée** — impossible
de les cocher deux fois. La grille affiche **24 documents par page** (photos
d'abord, puis les plus récents), avec la pagination habituelle.

#### 2. L'ajout, en fin de liste et une seule fois

Le bouton « **Ajouter la sélection à l'album** » range les documents cochés
**à la fin de l'album**, **dans l'ordre de la sélection** (`MAX(position) + 1`).
Un document déjà présent est **ignoré sans erreur** : un clic ne crée jamais de
doublon dans `album_documents`, et un double clic non plus. Le message de
confirmation dit combien d'éléments sont entrés, combien ont été ignorés, et
rappelle que **leur visibilité n'a pas changé**. L'ajout est noté au journal des
connexions.

Comme la sélection ne survit pas au changement de page, deux boutons la
remplacent :

- « **Tout ajouter sur cette page** » : un simple bouton `submit`, **sans
  JavaScript** — le serveur relit la page courante avec les mêmes filtres ;
- « **Tout cocher sur cette page** » : coche les cases affichées, avec
  JavaScript, pour ceux qui l'ont.

#### 3. La visibilité ne change jamais toute seule

L'ajout à un album **ne touche pas** à `documents.visibility`. Une photo privée
versée dans un album « Toutes les familles » **reste privée** : la famille ne la
voit pas dans l'album, l'administrateur si.

Quand un document ajouté est **moins visible que l'album**, la page le dit
franchement, juste après l'ajout : « **1 photo reste privée : la famille ne la
verra pas dans l'album.** » (la phrase s'accorde en nombre, en genre et en type :
photos ou documents). Un bouton séparé, « **Leur donner la visibilité de
l'album** », permet de corriger — **action distincte, confirmée par la boîte de
dialogue du navigateur, jamais automatique**. Elle est réservée à
l'administrateur, protégée par le jeton CSRF, et ne traite que des documents
**de cet album** (identifiants revérifiés côté serveur). L'aide sous le bouton
prévient que ce changement se voit **partout sur le site**, pas seulement dans
cet album ; pour un album en visibilité « Branches », les branches de l'album
sont recopiées sur le document.

#### 4. Qui peut, qui ne peut pas

- **Administrateur** : la grille, l'ajout et le changement de visibilité.
- **Lecteur, journaliste** : refusés — **403**, comme le reste de la page.
- **Visiteur** : renvoyé vers **`/connexion`**.
- **Sans jeton CSRF** : refusé (403).

Rien ne vient du navigateur hormis des **identifiants** : le serveur vérifie que
chaque document existe, qu'il n'est pas déjà dans l'album, et — pour l'action de
visibilité — qu'il appartient bien à cet album. Aucun titre, aucune date, aucune
visibilité n'est lue dans le formulaire.

Depuis la page **publique** d'un album (administrateur connecté) et depuis le
formulaire d'album, le bouton « **Ajouter des photos** » mène à cette page ; un
lien direct ouvre la grille (`#ajouter-documents`).

#### 5. Ce qui ne change pas

- **Aucun droit nouveau** : la grille montre exactement les documents que
  l'administration voit déjà, avec les mêmes règles de visibilité qu'ailleurs ;
  verser un document dans un album ne l'ouvre à personne.
- « **Retirer de l'album** » est toujours là, avec sa confirmation, et ne
  supprime pas le document.
- **Aucune classe renommée** : `.album-…`, `.grille-selection` et
  `.choix-document…` (nouvelles) sont ajoutées à côté ; `.filtres`, `.champ`,
  `.message--attention` sont réutilisées telles quelles.
- **Aucun style ni script en ligne** : le CSS est dans `public/assets/style.css`,
  le peu de JavaScript dans `public/assets/app.js` (bloc 9). La page est
  utilisable filtrer et ajouter sans JavaScript.
- **Aucune migration** : les tables `albums` et `album_documents` existaient.

#### Vérifications

- Suite complète : **1 268 essais, 0 échec**, dont **27 nouveaux** (famille
  « Albums ») : droits, ajout multiple dans l'ordre, doublon ignoré
  sans erreur, identifiant inconnu ignoré, visibilité conservée, document privé
  invisible au lecteur après l'ajout, avertissement et action de visibilité
  séparée, CSRF, `tout=1`.
- Navigateur réel (`tools/navigateur/actions-album-documents.mjs`, Chromium) :
  **22 / 22** vérifications à **1280 px** et à **390 px**, palette **papier** et
  palette **bleu** — soit 88 contrôles : grille présente, cases cochables, POST
  partant avec le jeton et exactement les documents cochés, « tout cocher » et
  « tout ajouter », album montrant les documents ajoutés, photo privée invisible
  au lecteur puis visible après l'action de visibilité, confirmation demandée,
  aucun débordement horizontal, aucun style ni script en ligne, aucune erreur
  JavaScript.
- Captures dans `docs/captures/` : `ordinateur-album-ajouter-documents[-bleu].png`,
  `mobile-album-ajouter-documents[-bleu].png`,
  `ordinateur-album-visibilite-a-corriger[-bleu].png`,
  `mobile-album-visibilite-a-corriger[-bleu].png`,
  `ordinateur-album-apres-ajout[-bleu].png`, `mobile-album-apres-ajout[-bleu].png`.
- Matrice d'accès régénérée après l'ajout des deux routes : **192 routes**,
  **768 vérifications** (route × profil), **0 anomalie** ; les deux nouvelles
  routes répondent **303** au visiteur, **403** au lecteur, au journaliste et à
  un POST sans jeton, **303** à l'administrateur.
- **Non vérifié ici** : le glisser-déposer d'un vrai téléphone, un lecteur
  d'écran sur la grille de cases, et l'impression de cette page d'administration
  — seuls Chromium 153, en 1280 et 390 px, ont été mesurés.

## [1.29.0] — 2026-11-07

### La une d'abord, pour tout compte connecté

Sur l'accueil d'un compte connecté, il fallait **défiler** pour voir le journal.
Avant « À la une » venaient la phrase d'accueil, la ligne de chiffres
(« 4 documents accessibles / 0 page d'information »), « Nous écrire », puis tout
le bloc « Dans la famille ». Sur un écran de 1280 px, le titre de l'article du
jour commençait à **1026 px** : le journal n'était plus un journal.

#### 1. L'ordre : une, puis la famille

L'accueil d'un compte connecté suit désormais l'ordre du papier : **bandeau**
(titre, date, menu), puis **la une** — l'article principal, en grand —, puis
**« Dans la famille »**, puis les derniers documents et « À lire ».

À partir de **64 rem**, « Dans la famille » tient dans une **colonne à droite**
de la une (19 rem), filet à gauche ; en dessous, il passe **juste dessous**,
jamais avant. Sur grand écran, la photo de la une se place à droite du titre
plutôt que sous lui : le titre et la photo tiennent alors dans le premier écran
sans rapetisser la manchette.

Mesures au navigateur, fenêtre de 800 px de haut : le titre de la une commence à
**439 px** en 1280 et à **647 px** en 390, où il tient entièrement (bas à
749 px) ; la photo est entièrement visible en 1280 (haut à 437 px, bas à
644 px). Avant la version, ce titre commençait à 1026 px et à 1616 px.

#### 2. La vitrine du visiteur ne s'affiche plus pour un compte connecté

La phrase d'accueil longue, la ligne de chiffres et « Nous écrire » ont quitté
l'accueil d'un compte connecté : elles poussaient la une hors de l'écran.
L'adresse de contact reste dans le pied de page, **une seule fois**.

À leur place, **une seule ligne discrète sous la date** : « Bonjour Élise » —
le prénom du compte, en petit et en italique, supprimée si le compte n'en a pas.
Elle ne coûte qu'une ligne.

**La page d'un visiteur ne change pas** : phrase, chiffres, « Nous écrire » et
les deux portes sont là comme avant (vérifié par un essai et par comparaison des
pages rendues avant / après : seuls des espaces changent).

#### 3. « Dans la famille » : cinq lignes, « Tout voir », replié sur téléphone

Le bloc montrait quatre panneaux entiers — de quoi remplir un écran à lui seul.
Il montre maintenant **cinq lignes au plus**, réparties entre les sections non
vides (un tour chacune : anniversaires, événements, personnes ajoutées, depuis
votre dernière visite), et un lien **« Tout voir »** vers l'espace famille.

Sur téléphone, au-delà de **trois** lignes, le bloc est **replié** dans un
`<details>` : une ligne « Dans la famille », le contenu au clic. **Aucun
JavaScript** n'est nécessaire — c'est le comportement natif du navigateur, et
l'essai le vérifie par un vrai clic. Sur grand écran, le bloc reste ouvert.

#### 4. Plus jamais une page sans article

- Bloc « une » **masqué** dans « Composition des pages » : la une est tout de
  même affichée (le dernier article publié accessible), sans titre de rubrique.
- **Aucun article du tout** : « Dans la famille » prend la première place.

Les articles restent ceux que la personne a le droit de lire : `recent_articles()`
applique les mêmes règles de visibilité qu'avant.

#### 5. Ce qui ne change pas

- **Aucun droit** : « Dans la famille » reste strictement dans les droits du
  compte (anniversaires, événements, personnes, nouveautés filtrés comme avant).
  La matrice d'accès est inchangée.
- **Aucune classe renommée** : `.article-carte--une`, `.dans-la-famille`,
  `.panneau--famille`, `.entete__date`… gardent leur nom. Les nouvelles
  (`.accueil-journal`, `.accueil-famille`, `.entete__salutation`) sont ajoutées
  à côté.
- **Aucun style ni script en ligne** : tout est dans `public/assets/style.css`,
  et l'essai le vérifie sur la page rendue.
- **Aucune migration** : rien à changer dans la base.

#### Vérifications

- Suite complète : **1 241 essais, 0 échec** (dont 17 nouveaux pour cette
  version, famille « accueil »).
- Navigateur (`tools/navigateur/essai-une.mjs`, Chromium, fenêtre 1280 × 800 et
  390 × 800, palette **papier** et **bleu**) : **13 / 13** vérifications à
  chaque largeur et chaque palette.
- Captures avant / après dans `docs/captures/` :
  `ordinateur-accueil-une-avant.png` / `-apres.png`, `mobile-accueil-une-…`,
  et leurs variantes `-bleu`.
- **Non vérifié ici** : l'affichage sur un vrai iPad ou un vrai téléphone
  (largeurs intermédiaires), l'usage avec un lecteur d'écran, et l'impression de
  cet accueil — seuls Chromium 153, en 1280 et 390 px, ont été mesurés.

## [1.28.1] — 2026-11-07

### Relancer l'installateur ne doit rien défaire

Trois corrections dans `deploy/install.sh`, toutes visibles au **deuxième**
passage — celui qu'on fait après avoir changé un réglage, branché un disque ou
mis le code à jour.

#### 1. La configuration garde ses droits

Le script finissait par `chmod 644` sur **tous** les fichiers du site, y compris
`config.local.php` : après chaque installation, la configuration — qui peut
contenir l'identifiant et le mot de passe du serveur d'envoi — devenait lisible
par n'importe quel compte de la machine. Le message « config.local.php existe
déjà : inchangé » était donc trompeur : le contenu ne changeait pas, mais les
droits, si.

Désormais le `chmod` global **exclut** `config.local.php`, et une fonction
s'occupe de lui :

- les droits trouvés sont **conservés** s'ils sont au moins aussi stricts que
  `640` (600, 640, 440…) ;
- ils sont **remis à `640 root:www-data`** s'ils sont plus larges (644, 664,
  777…), avec la raison affichée : « config.local.php était lisible trop
  largement (644) : droits remis à 640 root:www-data » ;
- le propriétaire est toujours `root`, le groupe toujours `www-data` (c'est le
  compte du site) ; si les droits conservés empêchent `www-data` de lire le
  fichier (600, 400), le script le dit au lieu de laisser le site tomber en
  panne à la première page.

#### 2. Le minuteur des courriels n'est plus rallumé pour rien

Depuis la 1.28.0, le site n'envoie aucun courriel par défaut. L'installateur
activait malgré tout `histoiredefamille-courriels.timer` : une tâche toutes les
cinq minutes pour ne rien faire, et l'impression que le site correspond.

Le script demande maintenant son état **au site**, par une commande nouvelle :
`php app/cli/etat-installation.php` (deux lignes, `courriels_actifs=0|1` et
`sauvegarde_sans_disque=0|1`), qui s'appuie sur `installation_etat()`.

- réglage **vrai** : les unités sont installées et le minuteur activé, comme
  avant ;
- réglage **faux** (le cas normal) : les unités sont posées, prêtes à servir,
  mais le minuteur est **arrêté** — et relancer l'installation ne le rallume
  pas. Le script affiche **« Courriels désactivés : minuteur laissé arrêté »**,
  avec les deux gestes à faire pour l'envoi (la case dans Administration →
  Réglages, le bloc `mail` de `config.local.php`).

#### 3. « Sans disque externe » n'est plus une erreur

Le mode « premiers jours sans clé USB » (`backup_allow_same_disk`) est une
dérogation **assumée** : le site sauvegarde sur la carte SD. L'installateur
affichait pourtant « ATTENTION : /mnt/sauvegarde n'est pas un point de montage »
et « la sauvegarde refusera de s'exécuter », ce qui était faux dans ce mode et
inquiétant pour rien.

Trois cas, maintenant, dans cet ordre : clé montée (message vert habituel) ;
sinon, mode sans disque externe actif → « **Mode sans disque externe actif
(backup_allow_same_disk) : la sauvegarde écrit sur la carte SD** », avec le
rappel de brancher la clé et de retirer le réglage ; sinon seulement, l'alerte
d'origine.

#### Essais

Nouvelle famille **« installation »** : **24/24**. Elle vérifie le `chmod` global
qui exclut la configuration, la fonction de réparation (masque 0037, `640
root:www-data`, avertissement quand `www-data` ne pourrait pas lire), les deux
branches du minuteur et le message exact, les trois cas du disque, et — pour de
vrai — `installation_etat()` sous trois réglages, ainsi que la commande
`etat-installation.php` (deux lignes `clé=valeur`, réservée à la ligne de
commande, code 0 en marche normale).

Deux familles existantes supposaient, sans le dire, que les courriels étaient
allumés : `admin` (le lien « Courriels » du menu) et `propositions` (le message
mis en file à la validation). Elles passaient sur une base où la 1.28.0 avait
laissé le réglage à `1`, et échouaient sur une base neuve — celle d'une
installation réelle. Elles appellent maintenant `tests_courriels_activer()` en
tête, et le réglage est rendu à la fin de la suite.

Suite complète : **1 224 essais, 0 échec**. Lint : **195 fichiers, 0 erreur**.
Matrice regénérée : 190 routes, 760 vérifications, 0 anomalie.

## [1.28.0] — 2026-11-07

### Le site n'envoie plus de courriel ; il affiche deux adresses

Un site de famille n'a pas besoin d'une messagerie. Les courriels apportaient
leur lot de pannes (mot de passe SMTP, plafond du fournisseur, messages classés
indésirables, liens de désinscription périmés) pour un service que le **tchat**
rend déjà : depuis la 1.28.0, **le site n'envoie aucun courriel**. Tout ce qui
relevait de l'envoi est **coupé**, pas supprimé : le code est toujours là, prêt à
repartir.

Deux adresses restent, et servent seulement à **être jointes depuis
l'extérieur** : `contact@histoiredefamille.eu` et
`support@histoiredefamille.eu`. Le site les **affiche** ; il ne s'en sert jamais
pour écrire, et ne les met ni en expéditeur ni en destinataire.

#### Les deux adresses se règlent dans l'administration

**Administration → Réglages → Adresses affichées** contient deux champs,
« Adresse de contact » et « Adresse d'assistance ». Elles sont rangées dans les
**réglages du site** (table `settings`), jamais dans le code : on les change
sans toucher à un fichier. Le format est vérifié à l'enregistrement (une adresse
invalide est refusée, avec un exemple), l'adresse est rangée en minuscules, et
un champ laissé vide remet la valeur livrée avec le site — il n'y a donc jamais
de page sans adresse à écrire.

Une **seule fonction** lit ces adresses, `site_adresse('contact' | 'support')` :
toutes les pages passent par elle. Si la valeur enregistrée était abîmée, elle
retombe sur celle livrée avec le site plutôt que d'afficher une adresse cassée.

#### Où elles apparaissent

Un lien `mailto:` ordinaire, en texte lisible et copiable — pas de JavaScript,
pas de style en ligne, pas de formulaire :

| Page | Ligne affichée |
|---|---|
| Pied de page de **toutes** les pages, site et administration | « Une question ? » suivi de l'adresse de contact |
| `/connexion` | « Mot de passe oublié ou besoin d'un compte ? Écrivez à » l'assistance |
| **Mon compte** | « Besoin d'aide ? » suivi de l'adresse d'assistance |
| Accueil public | « Nous écrire : » suivi de l'adresse de contact |
| Pages d'erreur (404 et accès refusé) | une ligne vers l'adresse d'assistance |

Aucune adresse personnelle de compte n'est affichée, nulle part : les adresses
déjà saisies dans les comptes restent en base, invisibles à l'écran.

#### Ce qui est coupé tant que les courriels sont désactivés

Le réglage `courriels_actifs` est **faux par défaut**, et **faux quand il est
absent** : une installation neuve, ou une base d'avant la 1.28.0, n'envoie donc
rien. Dans cet état :

- **rien n'entre dans la file** et rien ne part : article publié, gazette,
  création de compte, mot de passe — les fonctions d'envoi refusent en le
  disant (« Les courriels sont désactivés volontairement… »), pour qu'on ne
  prenne pas une décision pour une panne ;
- `php app/cli/envoyer-courriels.php` ne fait rien, **écrit pourquoi** et sort
  sans erreur : la tâche automatique ne casse pas, elle s'arrête ;
- `verifier-mise-en-ligne.php` ne réclame plus de configuration : la ligne
  « Envoi de courriels » passe en **OK**, « courriels désactivés
  volontairement » (elle réclamait auparavant un serveur SMTP) ;
- le **menu « Courriels »** disparaît de l'administration ; la page
  `/admin/courriels`, atteinte par une adresse directe, explique la décision et
  n'offre plus ni réglage d'envoi ni essai ;
- **Mon compte** n'a plus de section « Mes courriels » : ni champ d'adresse, ni
  cases de consentement, ni lien de désinscription ;
- les formulaires de **création et de modification d'un compte** n'ont plus de
  bloc courriel ; enregistrer un compte **conserve** l'adresse et les accords
  déjà en base (ils ne sont pas remis à zéro par un champ absent) ;
- la **gazette** perd son envoi par courriel : plus de bouton « Envoyer la
  gazette », plus de colonne « Envoyé le », plus de panneau « Envoi
  automatique », et l'aperçu n'explique plus l'envoi. « Passer ce mois-ci »
  reste ; la lecture en ligne, elle, ne change pas ;
- la page publique de la gazette n'affiche plus « Gérer mes courriels » ;
- le formulaire d'article n'a plus la case « Prévenir les inscrits par
  courriel ».

La **création d'un compte** garde le bouton **« Copier le message »** : le mot de
passe se transmet à la main, comme avant. Une **réinitialisation** de mot de
passe passe par un **administrateur**.

#### Ce qui ne change pas

Le **tchat** et les notifications **dans le site** (accueil, « Dans la
famille », « À valider ») sont intacts. Aucune règle d'accès ne bouge : la
matrice rejoue les mêmes 190 routes et les mêmes 760 vérifications, sans une
anomalie. Aucune migration n'est nécessaire : les colonnes `email`,
`notify_articles` et `notify_gazette` restent telles quelles, et les adresses
déjà enregistrées ne sont ni effacées ni affichées.

#### Réactiver l'envoi, si un jour

Le code d'envoi est **toujours là**. Il repart en deux gestes :

1. cocher **« Le site envoie des courriels »** dans Administration →
   Réglages (case décrite plus bas dans le formulaire) ;
2. remplir le bloc `mail` de `config.local.php` sur le Raspberry Pi, comme
   décrit au README § 14.

Décocher la case suffit à tout recouper : les messages déjà en file restent en
file, sans être traités.

#### Essais

Les captures de `docs/captures/` ont été **refaites** avec la base de
démonstration : accueil, connexion et « Mon compte » en 1280 et 390 px, palette
papier et bleu, plus les Réglages (bloc « Adresses affichées », case
« Courriels ») en bleu. Deux outils de développement ont été ajoutés pour que
la fabrication soit reproductible : `tools/navigateur/inline-medias.php` (les
images réelles de la page en `data:`) et `tools/navigateur/capture.mjs` (la
photo).

Nouvelle famille **« contacts »** : **43/43**. Elle vérifie les adresses (repli,
réglage, format, normalisation, enregistrement par le formulaire), leur
présence aux cinq endroits demandés en lien `mailto:`, l'absence de toute
adresse de compte à l'écran, le refus d'enfiler et d'envoyer quand le réglage
est faux, l'arrêt de la commande, la ligne « désactivé volontairement » de la
vérification avant ouverture, chaque écran masqué — et leur retour quand le
réglage est rallumé. Suite complète : **1 200 essais, 0 échec**. Lint :
**193 fichiers, 0 erreur**.

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
