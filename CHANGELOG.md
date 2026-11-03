# Journal des versions

Toutes les dates sont au format `AAAA-MM-JJ`.
Le projet suit un numéro de version simple : `majeure.mineure.correctif`.

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
