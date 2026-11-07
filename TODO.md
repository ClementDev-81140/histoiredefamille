# À faire

Ce fichier liste ce qui reste à faire, classé par priorité. Il n'y a pas de
suivi automatique : cochez au fur et à mesure.

## Fait (2026-11-07, 1.32.0 — cinq améliorations demandées par le propriétaire)

- [x] **Filtre « photo ancienne »** : réglage global *Aucun / Doux / Marqué*
  (Administration → Apparence du journal), appliqué par **classes CSS**,
  vignettes comme grandes images ; choix par photo (suivre / forcer /
  désactiver) ; **l'original n'est jamais modifié** (téléchargements et
  impression en couleurs, lien « Télécharger l'original ») ; aperçu en direct.
- [x] **Image des vidéos** : extraite par ffmpeg (1 s, ou 10 % de la durée),
  réduite comme les autres miniatures, fabriquée en fin d'envoi ou de
  conversion ; commande `app/cli/miniatures-videos.php` (liste, puis une vidéo
  à la fois, relançable) ; ffmpeg absent → icône de lecture + message clair
  dans Conversions ; miniature **soumise à la visibilité de la vidéo** (404
  identique) ; image de couverture possible par l'administrateur.
- [x] **Articles sans photo** : aucun carré, aucune lettre, aucun cadre vide,
  partout (accueil, À la une, listes, recherche, gazette, article,
  impression) ; « Grande photo » sans photo retombe sur « Journal ».
- [x] **Carte désactivée par défaut** : lien retiré des menus, `/carte` et
  `/lieu/{id}` en 404 « introuvable » pour tout le monde, `/admin/lieux`
  redirige vers Réglages ; code, données et table `places` conservés.
- [x] **Catégories par type** : une catégorie sert à un ou plusieurs types
  (article, document, photo, album, vidéo, recette), ordre d'affichage, cases
  dans tous les formulaires (dont « Créer le document » et les envois par
  lot), filtre GET partout, compteur sans fuite ; migration 021 en ajout seul.
- [x] **Calendrier** : vrais événements de famille (Administration →
  Calendrier), **privés par défaut**, répétition annuelle, suppression
  confirmée, visibilité respectée dans le calendrier et « Dans la famille ».

## Fait (2026-11-07, 1.31.0 — une seule Une de journal, menu visiteur réduit)

- [x] **Menu d'un visiteur = « Accueil » et « Se connecter »** seulement ;
  « Consulter » et « Carte » retirés. Le menu d'un compte connecté ne change
  pas.
- [x] **`/carte` et `/lieu/{id}` renvoient un visiteur vers `/connexion`**
  (303, comme `/arbre`). Aucun autre droit ne bouge : `/articles`,
  `/documents`, `/albums`, `/videos`, `/recherche`, `/calendrier` répondent
  encore **200** à un visiteur (vérifié dans la matrice).
- [x] **Accueil public = la Une du journal** : phrase d'accueil, gros bouton
  « Se connecter » dans le premier écran, puis À LA UNE en grand (contenu
  public le plus récent, ou celui choisi par un administrateur), puis la grille
  de tout le contenu public — regroupée par rubrique au-delà de six éléments —
  avec « Voir plus » (GET) au-delà de 24, et un lien vers la page publique
  existante de chaque contenu.
- [x] **Nouveau réglage « À la une du site public »** (Composition des pages) :
  liste de contenus publics seulement, « Automatique » par défaut, choix
  revérifié à chaque affichage.
- [x] **Accueil vide** : Une sobre, phrase d'accueil, « Se connecter » en grand,
  contact@ et support@ en liens `mailto:` ; aucun bloc vide.
- [x] **Test de fuite** : contenu privé, famille, branches, en attente de
  validation, vidéo en conversion → aucun n'apparaît pour un visiteur (ni
  vignette, ni titre, ni dans le HTML, ni dans une adresse d'image).
- [x] **Défaut corrigé** : `visibility_clause()` applique `proposition_clause()`
  aussi à un visiteur non connecté (un contenu en attente de validation
  pouvait apparaître sur la Une publique).
- [x] **L'accueil des comptes connectés ne change pas** (1.29.0) ; aucune
  classe renommée, aucun style ni script en ligne, aucune ressource externe,
  aucune migration.
- [x] **Essais** : suite complète **1 310 essais, 0 échec** (dont 42 pour cette
  version) ; matrice régénérée (192 routes, 768 vérifications, 0 anomalie) ;
  navigateur réel 1280 et 390 px, papier et bleu ; captures
  `docs/captures/*une-publique*`, `*une-vide*`, `*contenu-public*`,
  `*contenu-prive-404*`.

## Fait (2026-11-07, 1.30.0 — verser dans un album ce qui est déjà sur le site)

- [x] **Section « Ajouter depuis les documents du site »** sous le bloc d'envoi :
  grille de vignettes à cocher (photos d'abord), titre, date, type, badge de
  visibilité, 24 par page.
- [x] **Filtres en GET** (recherche titre ou étiquette, type, visibilité,
  catégorie) et case « pas encore dans cet album » cochée par défaut ; les
  documents déjà dans l'album sont marqués et **non cochables**.
- [x] **Ajout en fin de liste** (`MAX(position) + 1`), dans l'ordre de la
  sélection ; doublon ou identifiant inconnu **ignorés sans erreur** (jamais
  deux fois le même document dans `album_documents`).
- [x] **« Tout cocher » (JavaScript) et « Tout ajouter sur cette page » (sans
  JavaScript)** pour ne pas perdre la sélection d'une page à l'autre.
- [x] **La visibilité ne change jamais** : message après l'ajout (« 1 photo
  reste privée : la famille ne la verra pas dans l'album. ») et action
  **séparée et confirmée** « Leur donner la visibilité de l'album ».
- [x] **Administrateur seulement** (lecteur et journaliste 403, visiteur →
  connexion), **CSRF**, aucun chemin ni valeur du navigateur hormis les
  identifiants, vérifiés côté serveur.
- [x] **Aucun droit nouveau**, aucune classe renommée, aucun style ni script en
  ligne, aucune migration ; « Retirer de l'album » est conservé.
- [x] **Essais** : suite complète **1 268 essais, 0 échec** (dont 27 pour cette
  version, famille « Albums ») ; navigateur **22/22** à 1280 et 390 px, palette
  papier et bleu ; captures `docs/captures/*album-ajouter-documents*`,
  `*album-visibilite-a-corriger*`, `*album-apres-ajout*` ; matrice régénérée
  (192 routes, 768 vérifications, 0 anomalie).

## Fait (2026-11-07, 1.29.0 — la une d'abord)

- [x] **La une dans le premier écran** pour tout compte connecté : titre à
  439 px en 1280 × 800 (photo entièrement visible) et à 647 px en 390 × 800
  (titre entier), mesuré au navigateur ; il commençait à 1026 px et 1616 px.
- [x] **L'ordre** : bandeau → une → « Dans la famille » → documents / à lire.
  La famille passe en **colonne à droite** à partir de 64 rem, jamais avant.
- [x] **La vitrine du visiteur** (phrase, chiffres, « Nous écrire ») a quitté
  l'accueil connecté ; une **seule ligne** discrète sous la date à la place.
  La page d'un visiteur ne change pas.
- [x] **« Dans la famille »** : cinq lignes au plus, lien « Tout voir », replié
  dans un `<details>` sur téléphone au-delà de trois lignes (sans JavaScript).
- [x] **Repli** : bloc « une » masqué → le dernier article publié reste en tête ;
  aucun article → « Dans la famille » prend la première place.
- [x] **Aucun droit modifié**, aucune classe renommée, aucun style ni script en
  ligne, aucune migration. Suite : 1 241 essais, 0 échec ; navigateur 13/13
  dans les deux palettes et les deux largeurs.

## Fait (2026-11-07, 1.28.1 — relancer install.sh ne défait plus rien)

- [x] **`config.local.php` garde ses droits** : le `chmod 644` global l'exclut ;
  une fonction conserve les droits s'ils sont au moins aussi stricts que `640`,
  les remet à `640 root:www-data` s'ils sont plus larges, et prévient si
  `www-data` ne pourrait pas lire le fichier.
- [x] **Minuteur des courriels** : `deploy/install.sh` demande l'état au site
  (`php app/cli/etat-installation.php` → `installation_etat()`) et, quand
  `courriels_actifs` est faux, installe les unités mais **laisse le minuteur
  arrêté** — « Courriels désactivés : minuteur laissé arrêté » — sans le
  rallumer au passage suivant.
- [x] **Mode sans disque externe** : `backup_allow_same_disk` vrai donne
  « Mode sans disque externe actif … la sauvegarde écrit sur la carte SD » au
  lieu de « ATTENTION : /mnt/sauvegarde n'est pas un point de montage » ;
  l'alerte ne reste que si rien n'est assumé.
- [x] **Essais** : famille « installation » **24/24** ; suite complète **1 224
  essais, 0 échec** ; `sauvegarde` 52/52, `conversion` 130/130, `contacts` 43/43 ;
  lint **195 fichiers, 0 erreur** ; matrice 0 anomalie.
- [x] **Deux essais qui mentaient corrigés** : `admin` et `propositions`
  supposaient les courriels allumés (vrai sur une base d'essai ancienne, faux
  sur une base neuve) ; ils les allument explicitement.
- [x] **Documentation** : CHANGELOG 1.28.1, README § 4.4, § 4.5 et § 14.7, TODO.

## Fait (2026-11-07, 1.28.0 — aucun courriel, deux adresses affichées)

- [x] **Le site n'envoie aucun courriel** : `courriels_actifs` **faux par
  défaut** (absent = faux). Tant qu'il est faux, rien n'entre dans la file et
  rien ne part (article, gazette, création de compte, proposition, mot de
  passe) ; `courriel_enfiler()`, `courriel_envoyer()` et
  `courriel_traiter_file()` refusent en disant que c'est **volontaire**.
- [x] **Deux adresses affichées seulement** : `contact@histoiredefamille.eu` et
  `support@histoiredefamille.eu`, en **réglages** (Administration → Réglages),
  jamais dans le code, lues par **une seule fonction** `site_adresse()`. Format
  vérifié, minuscules, champ vide → valeur livrée, valeur abîmée jamais affichée.
- [x] **Affichage** en lien `mailto:` ordinaire, texte lisible et copiable,
  **sans JavaScript, sans style en ligne, sans formulaire** : pied de page de
  toutes les pages, page de connexion, « Mon compte », accueil public, pages
  d'erreur 404 et accès refusé.
- [x] **Écrans masqués** : menu « Courriels » (et la page elle-même, qui
  explique la décision), réglages d'envoi et essai, bouton « Envoyer la
  gazette », colonne « Envoyé le », panneau « Envoi automatique », case
  « Prévenir les inscrits », champs d'adresse et cases de consentement de
  « Mon compte » et des formulaires de comptes, lien « Gérer mes courriels ».
- [x] **Rien de perdu** : le code d'envoi n'est pas supprimé (réactivation par
  la case + le bloc `mail` de `config.local.php`) ; enregistrer un compte
  **conserve** l'adresse et les accords déjà en base ; « Copier le message »
  reste à la création d'un compte ; réinitialisation de mot de passe par un
  administrateur ; **tchat et notifications du site inchangés** ; **aucune règle
  d'accès modifiée**, **aucune migration**, aucune adresse personnelle affichée.
- [x] **Commande et vérification** : `app/cli/envoyer-courriels.php` s'arrête en
  l'écrivant ; `verifier-mise-en-ligne.php` affiche « Envoi de courriels » en
  **OK** — « courriels désactivés volontairement ».
- [x] **Essais** : famille « contacts » **43/43** ; suite complète **1 200
  essais, 0 échec** ; lint **193 fichiers, 0 erreur** ; matrice regénérée.
- [x] **Documentation** : CHANGELOG 1.28.0, README § 6.21 et § 14 (bandeau
  « désactivés, comment réactiver »), § 15 (gazette sans envoi), TODO,
  `config.example.php`.
- [x] **Captures de référence refaites** (base de démonstration, images en
  `data:`) : accueil, connexion et « Mon compte » en **1280 et 390 px**, palette
  **papier** et **bleu**, plus les **Réglages** (bloc « Adresses affichées » et
  case « Courriels ») en bleu, 1265 px. `mobile-connexion.png` passe de 375 à
  390 px. Deux outils ajoutés pour que ce soit reproductible :
  `tools/navigateur/inline-medias.php` et `tools/navigateur/capture.mjs`.

## Fait (2026-11-07, 1.27.0 — ajouter des photos dans un album)

- [x] **Bouton « Ajouter des photos »** sur le formulaire d'un album
  (`/admin/albums/{id}/modifier`) et sur la page publique de l'album
  (`/albums/{slug}`), **pour un administrateur connecté seulement** : jamais
  pour un visiteur, un lecteur ou un journaliste.
- [x] **Envoi par morceaux existant** (glisser-déposer, plusieurs fichiers,
  barre par fichier, reprise), **photos seulement** (jpg, jpeg, png, webp,
  gif), mêmes limites et validations qu'aujourd'hui, rejouées sur le fichier
  assemblé par le serveur.
- [x] **Une photo complète = une photo de l'album** : document type `photo`,
  visibilité **celle de l'album**, titre tiré du **nom du fichier** sans
  extension, ajout **en fin de liste**, vignettes générées. Doublons SHA-256
  signalés comme ailleurs (« Garder quand même » / « Ignorer »), jamais
  supprimés.
- [x] **Après l'envoi** : lien **« Voir l'album »** et liste des photos ajoutées
  avec, pour chacune, **titre** et **date** modifiables et **« Retirer de
  l'album »** (le document reste) — formulaires ordinaires, sans JavaScript.
- [x] **Droits** : administrateur seulement, jeton CSRF, aucune photo d'un autre
  compte touchée, **un envoi = un seul album** (`uploads.album_id`), réservation
  conditionnelle à la conclusion (double clic ⇒ une seule fiche).
- [x] **Essais** : famille « album » **54/54** (dont droits visiteur / lecteur /
  journaliste / administrateur), `envoi` **122/122**, suite complète **1 157
  essais, 0 échec**, lint **190 fichiers, 0 erreur**, matrice **190 routes,
  760 vérifications, 0 anomalie** (248 contrôles ciblés).
- [x] **Défaut ancien corrigé** : un `@media (min-width: 64rem)` jamais refermé
  dans `public/assets/style.css` (et un commentaire sans son `/*`) avalait près
  de six cents lignes de CSS, appliquées seulement au-dessus de 1024 px ; un
  essai vérifie maintenant l'équilibre des accolades.
- [x] **Vrai navigateur** (Chromium) : **21/21** quatre fois — **papier** et
  **bleu**, **390 px** et **1280 px** : bouton visible et cliquable, absence
  pour un visiteur, dépôt d'une vraie photo qui part avec l'album et recharge la
  page une seule fois, refus d'un film, POST des formulaires, confirmation,
  palette mesurée, impression. L'ancien contrôle `actions.mjs` (1.26.3) rejoué :
  **12/12** aux deux largeurs.
- [x] **Documentation** : CHANGELOG 1.27.0, README § 6.19 (+ une ligne dans
  « Albums photos »), `tools/navigateur/README.md`.

## Fait (2026-11-07, 1.26.3 — deux actions sur les fichiers reçus)

- [x] **« Créer le document »** : le fichier reçu devient une fiche **privée**
  tout de suite, sans repasser par le formulaire — mêmes champs que la fiche
  automatique de fin d'envoi (titre, miniature, dimensions, date lue dans le
  fichier, empreinte), puis redirection vers la fiche à compléter (formulaire
  vidéo pour un film).
- [x] **« Supprimer »** : après **confirmation** nommant le fichier, efface le
  **fichier assemblé** et les **morceaux** ; l'envoi quitte la liste.
- [x] **Un seul document par fichier** : réservation atomique
  (`UPDATE … WHERE state = 'pret'`) avant l'écriture de la fiche ; réservation
  annulée si l'écriture échoue ; un fichier déjà rattaché n'est pas supprimable
  depuis cette liste (c'est la fiche qui possède le fichier).
- [x] **Droits** : administrateur, jeton CSRF, fichier existant, **fichier du
  compte connecté**, état « prêt ». La page ne liste que ses propres fichiers ;
  **Tous les envois** reste en lecture seule (lien « Compléter » retiré pour les
  fichiers des autres).
- [x] **Texte d'aide corrigé** : il ne renvoie plus à une « liste ci-dessous »
  qui n'existe pas ; il dit ce que font les deux boutons, que le fichier reste
  privé, qu'il ne devient un document qu'une fois, et que seul l'expéditeur agit.
- [x] **Fuite corrigée** : `envoi_annuler()` effaçait le fichier assemblé
  seulement pour l'état « doublon » ; il l'efface aussi pour « prêt » (jamais
  pour « terminé », où le fichier appartient à la fiche).
- [x] **Essais** : famille « envoi » **122/122** (+30) ; suite complète **1 101
  essais, 0 échec** ; lint **188 fichiers, 0 erreur** ; matrice **187 routes,
  748 vérifications, 0 anomalie**.
- [x] **Vrai navigateur** (Chromium) : **12/12** à **390 px** et **1280 px**
  (colonne « Actions », deux boutons, POST avec `_csrf` + `token` et rien
  d'autre, confirmation qui nomme le fichier, refus de la confirmation sans
  envoi, aucune barre horizontale, aucune erreur JavaScript) ; outil fourni,
  `tools/navigateur/actions.mjs`.
- [x] **Documentation** : CHANGELOG 1.26.3, README § 6.18 (« Fichiers reçus, en
  attente de formulaire »), `tools/navigateur/README.md`.

## Fait (2026-11-07, 1.26.2 — l'envoi par morceaux remplit enfin le formulaire)

- [x] **Le vrai défaut** : le script visait le premier `input[type=file]` de la
  page — celui, masqué, du bloc d'envoi — et laissait `required` sur la case
  « Fichier ». Le navigateur bloquait avant que le serveur ne voie la requête.
  Il vise maintenant `input[type="file"][name="fichier"]` (et exclut le champ
  du bloc avec `data-envoi-champ`).
- [x] **Message qui nomme le vrai bouton** : « Envoyé. Le fichier est prêt :
  cliquez sur « Envoyer la vidéo » » — le libellé est lu dans le formulaire
  (« Envoyer le document », « Enregistrer »…).
- [x] **Lien-ancre** « Aller au bouton d'enregistrement » sous le message
  (`#enregistrer`, lien simple, sans JavaScript ni style en ligne) ; le bouton
  des trois formulaires porte `id="enregistrer"`.
- [x] **Champ caché `envoi` rendu par le serveur** (document, vidéo,
  proposition) : le fichier reçu survit à une erreur de formulaire (titre vide).
- [x] **Serveur** : jeton de l'administrateur connecté seulement, envoi terminé,
  réservé une seule fois (`UPDATE … WHERE state = 'pret'`), aucun chemin venu
  du navigateur, validations rejouées, refus explicites pour un jeton inconnu,
  réutilisé ou appartenant à un autre compte.
- [x] **Fuite corrigée** : remplacer un fichier par l'envoi par morceaux
  supprime enfin l'ancien (documents et vidéos).
- [x] **Essais** : famille « envoi » **92/92** (+25) ; suite complète **1 071
  essais, 0 échec** ; lint **188 fichiers, 0 erreur** ; matrice **185 routes,
  740 vérifications, 0 anomalie**.
- [x] **Vrai navigateur** (Chromium) : 14 vérifications × 4 combinaisons
  (vidéo/document, 390/1280 px), toutes vertes ; outil fourni dans
  `tools/navigateur/` et documenté.
- [x] **Documentation** : CHANGELOG 1.26.2, README § 6.18 (« Dans le formulaire
  d'une vidéo ou d'un document »), `tools/navigateur/README.md`.

## Fait (2026-11-07, 1.26.1 — les en-têtes de sécurité partaient deux fois)

- [x] **Une seule source par en-tête** : `nosniff`, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy` et la CSP sont posés par **Apache**,
  dans `public/.htaccess` ; le PHP ne les envoie plus. Chaque en-tête est
  précédé de `Header unset` + `Header always unset` : la réponse n'en contient
  qu'un, même si un ancien `.conf` ou un module en ajoutait un.
- [x] **`deploy/apache/histoiredefamille.conf`** : plus aucun en-tête de
  sécurité (seulement `Header unset Server` et `X-Powered-By`).
- [x] **CSP la plus stricte** : `default-src 'self'`, aucune adresse externe,
  aucun `unsafe-inline` ; un essai compare le texte de `.htaccess` à
  `security_csp_valeur()` pour qu'ils ne divergent pas.
- [x] **Contrôle de `verifier-mise-en-ligne.php`** : noms et valeurs sans tenir
  compte de la casse, doublons reconnus sur deux lignes **ou** sur une ligne
  (« `DENY, DENY` »), « envoyé 2 fois » rangé en **avertir** (non bloquant) et
  distinct de « manquant » (**à corriger**) ; `Permissions-Policy` contrôlé ;
  une CSP avec `unsafe-inline` → **à corriger**.
- [x] **Essais** : 15 nouveaux essais dans `tests_entretien.php` (famille
  « verification » : 54/54) ; suite complète **1 045 essais, 0 échec** ;
  lint **187 fichiers, 0 erreur**.
- [x] **Documentation** : CHANGELOG 1.26.1, README (§ 4.7 `curl -sI`, § 5
  en-têtes, § 7 mise à jour, tableau des contrôles), `deploy/README.md`.
- [ ] **À faire sur le Pi** : `git pull`, `sudo bash deploy/install.sh`, puis
  `curl -sI http://localhost` — doit montrer **une seule** ligne par en-tête.

## Fait (2026-11-05, 1.26.0 — dernière passe avant la mise en ligne)

- [x] **Aucune fonctionnalité nouvelle** : contrôle et correction de ce qui
  casse. Règles de sécurité inchangées, aucune classe CSS renommée.
- [x] **Conversions vidéo** : report au passage suivant (au lieu d'un échec) si
  le disque est trop plein ou si un envoi par morceaux est en cours ;
  `nice` (priorité basse) ; reprise après coupure de courant
  (`conversion_recuperer`, aussi dans la maintenance) ; fichiers temporaires
  effacés ; l'auteur retrouve sa vidéo en préparation.
- [x] **Vidéo en échec** : invisible pour la famille (liste, recherche,
  adresse directe), visible de l'administrateur avec l'erreur.
- [x] **Versions des propositions** (migration **017**, ajout seul
  `replaces_id`) : modifier un contenu publié crée une version en attente ;
  l'original reste servi tel quel ; validation = recopie, refus = rien ne
  bouge ; une seule version à la fois ; le `slug` d'un contenu publié ne change
  jamais ; bouton **Retirer** pour l'auteur ; motif de refus visible de
  l'auteur et des administrateurs seulement.
- [x] **Purge des données de démonstration** : `demo.php` tient un registre
  (fichier **et** réglages du site) ; `app/cli/purger-demo.php --liste` /
  `--oui`, sauvegarde obligatoire avant, fichiers et miniatures effacés,
  liens de branche nettoyés, **comptes jamais supprimés**.
- [x] **Vérification avant ouverture** : quatrième état **`AVERTIR`** (ne bloque
  jamais), seuil de sauvegarde ramené à **48 h**, contrôles « courriel » et
  « minuteur d'envoi », avertissements rangés à part dans « État du site ».
- [x] **Espace disque en trois couleurs** (vert / orange < 20 % / rouge < 10 %
  ou < 5 Go) sur le tableau de bord et « État du site ».
- [x] **Essais** : familles « demo » et « cas limites » des propositions
  ajoutées ; essais d'entretien remis d'aplomb après le passage à `AVERTIR`.
- [x] **Documentation** : README (§ 4.8 purge, § 7 les quatre états, 48 h),
  CHANGELOG 1.26.0 (une seule entrée, avec ce qui n'a pas pu être vérifié).

## Fait (2026-11-05, lot 4 — alléger, et vivre sans disque externe)

- [x] **Textes explicatifs retirés** dans l'administration, l'espace famille et
  les pages publiques : encadrés de rappel, aides longues, introductifs
  répétés. Les libellés, les messages d'erreur, les avertissements de sécurité
  et les aides courtes qui évitent une erreur sont conservés. Le tchat n'a pas
  été touché. La liste complète est dans `CHANGELOG.md` (1.25.0).
- [x] **« Sauvegarde : Sauvegarde : »** : le titre est posé une seule fois, par
  la vue ; un test le vérifie.
- [x] **Mode « premiers jours sans disque externe »** : `backup_allow_same_disk`
  dans `config.local.php`, **jamais actif par défaut**. Bandeau rouge permanent
  dans l'administration, avertissement (non bloquant) dans
  `verifier-mise-en-ligne.php`, qui dit toujours « PRÊT À OUVRIR » ou non.
- [x] **Conversion vidéo** : refus de démarrer sous la marge d'espace libre
  (`conversion.min_free_mb`, 1 Go) ; la commande revérifie la place juste avant
  de lancer ffmpeg. Les minuteurs de courriel ne changent pas.
- [x] **README** : nouvelle section « Premiers jours sans disque externe »
  (tout sur la carte SD, surveillance de l'espace, copie manuelle `scp`/SFTP,
  transition à l'arrivée du disque).
- [x] **903 tests** (`php app/cli/tests.php`), 0 échec ; matrice d'accès
  régénérée, 0 anomalie ; captures 1280 et 390 px de seize pages, sans
  débordement (un chemin du Pi forçait une barre horizontale sur
  « Courriels » : corrigé).

## Fait (2026-11-04, lot 3 — carte des lieux)

- [x] **Page `/carte`** : les lieux de naissance, de décès et de mariage sur
  une carte **dessinée par le serveur** (SVG), sans service externe, sans
  JavaScript, sans ressource téléchargée.
- [x] **Fond embarqué** (37 Ko) : Natural Earth 4.1.0, **domaine public**,
  deux vues (France, monde entier) ; origine, licence et projection dans
  `docs/carte.md`.
- [x] **Table `places`** (migration **012**, ajout seul : une table, deux
  index) ; les textes de lieu des fiches ne sont **jamais** réécrits, un texte
  inconnu est ignoré sur la carte.
- [x] **Administration → Lieux à placer** : textes en attente, recherche dans
  **8 942 communes** embarquées (accents et casse ignorés), saisie à la main de
  la latitude et de la longitude, liste des lieux placés, retrait sans toucher
  aux fiches.
- [x] **Filtres** branche et décennie en `GET`, deux vues, légende, **tableau
  équivalent** sous la carte.
- [x] **Fiche d'un lieu** (`/lieu/{id}`) et lien **« Voir sur la carte »**
  depuis la fiche d'une personne.
- [x] **Droits inchangés** : seules les personnes visibles
  (`visibility_clause('person', …)`) ; lieu sans personne visible → même 404,
  même texte qu'un lieu inexistant.
- [x] **Cache court** (5 min) par groupe de droits, filtres et version des
  données ; projection en PHP pur, < 300 ms avec 2 000 personnes.
- [x] 58 vérifications de plus (601 au total) ; aucune règle d'accès modifiée,
  aucune migration existante touchée, aucune classe CSS renommée.

## Fait (2026-11-03, lot 10 — composition des pages)

- [x] **Administration → Composition des pages** (administrateurs seulement,
  POST + jeton CSRF, journalisé « composition »).
- [x] **Afficher / masquer** et **monter / descendre** chaque bloc de `/` et de
  `/famille`, sans JavaScript ; un bloc ne quitte jamais sa colonne.
- [x] **Bloc masqué jamais calculé** : ni requête, ni travail pour le Pi.
- [x] **Réglages de la une** : 3 à 12 articles en colonnes, longueur du résumé
  (court/moyen/long), photo de la une oui/non, valeurs bornées.
- [x] **Modèles d'article** : Journal (actuel), Simple, Grande photo ; défaut
  du site + choix par article (« Comme le site »), via la **migration 009**
  (`articles.modele`, vide par défaut).
- [x] **« Rétablir la composition d'origine »** avec confirmation.
- [x] Stockage en `settings`, JSON strictement validé (JSON abîmé → origine).
- [x] 52 vérifications de plus (296 au total) ; aucune règle d'accès modifiée,
  aucune classe CSS renommée, aucune migration existante touchée.

## Fait (2026-11-03, lot 9 — apparence du journal)

- [x] **Administration → Apparence du journal**, à côté de « Réglages »
  (administrateurs seulement, POST + jeton CSRF, journalisé « apparence
  modifiée » sans recopier les textes).
- [x] **Textes de la une** : milieu et droite du bandeau, titre, devise,
  « Fondée en », pied de page, phrase d'accueil, et les sept titres d'encarts.
  Champ vide = texte d'aujourd'hui.
- [x] **Numéro d'édition** : automatique, texte libre ou aucun.
- [x] **Quatre cases** pour afficher/masquer bandeau, devise, « Fondée en »,
  numéro.
- [x] **Titre gothique** : une case ; décochée, Old Standard TT gras en petites
  capitales. Police manquante = repli automatique, signalé dans la page.
- [x] **Couleurs**, trois modes : « Papier ancien » (défaut, aucune surcharge),
  « Bleu d'origine » (fond uni), « Personnalisée » (quatre couleurs, le site
  calcule le reste). Contrastes vérifiés (7:1 texte, 4,5:1 doux/accent/bouton),
  refus explicite, bouton « Corriger pour moi », « Rétablir les couleurs
  d'origine ».
- [x] **Feuille `/theme.css` calculée par PHP** : valeurs validées uniquement,
  ETag sur un numéro de version, cache puis revalidation ; liée après
  `style.css` et **seulement** si une autre palette est active.
- [x] **Aperçu en direct** de l'en-tête (`public/assets/app.js`, `textContent`
  et `setProperty` : aucun `innerHTML`, aucun `style=` en HTML). Sans
  JavaScript, le formulaire reste complet.
- [x] 57 vérifications de plus (244 au total), dont l'essai d'injection
  `red;} body{display:none}` dans chaque couleur.
- [x] Aucune migration, aucune règle d'accès modifiée, aucune classe CSS
  renommée.

## Fait (2026-11-03, lot 8)

- [x] **Comptes de la famille** : un compte par personne (`prenom.nom`), à
  côté des comptes de branche partagés ; mêmes droits, jamais plus.
- [x] Création à l'unité ou par liste collée (100 lignes au plus) avec
  **aperçu obligatoire** : identifiants proposés et modifiables, lignes
  fautives signalées, doublons nom + prénom + branche décochés.
- [x] Mot de passe provisoire affiché **une seule fois**, avec « Copier le
  message » / « Tout copier » ; jamais en base en clair, jamais dans un
  journal.
- [x] **Mon compte** : chaque personne change son propre mot de passe (10
  caractères minimum, refus si c'est l'identifiant, session régénérée).
- [x] Actions d'administration : modifier, désactiver/réactiver, réinitialiser,
  supprimer (messages du tchat conservés) ; filtre par branche et recherche.
- [x] Tchat : signature automatique `MARTIN/Élise` pour un compte personnel.
- [x] Migration `008_comptes_personnels.sql`, 40 vérifications de plus
  (184 au total).

## Fait (2026-11-02, lot 7)

- [x] **Design « Gazette »** intégré (palette papier ancien, en-tête de
  journal, espace famille en « une »).
- [x] Reste du design corrigé : champs, encadrés, badges, tableaux, calendrier,
  vignettes ; en-tête compact dans l'administration ; tableaux défilants sur
  téléphone. Rien n'est bleu, et le bloc `:root` d'origine est conservé.
- [x] **Accueil public réduit à trois entrées** ; les autres rubriques
  n'apparaissent que si elles contiennent quelque chose de public
  (`rubriques_publiques()`, `menu_rubriques()`).
- [x] **Espace famille enrichi** : dernières vidéos, albums, encart « Accès
  rapides » vers toutes les rubriques.
- [x] 16 vérifications de plus (126 au total).

## Fait (2026-11-01, lot 6)

- [x] **Journal des connexions** (`/admin/connexions`) : identifiant essayé,
  date, adresse, réussite ou refus avec sa raison, blocages ; filtres.
- [x] **Alertes sur le tableau de bord** : administrateur connecté depuis une
  adresse inconnue, plus de dix échecs en une heure pour un même compte ;
  bouton « J'ai vu ».
- [x] Durée de conservation réglable (30 / 90 / 180 / 365 jours) et purge par
  la tâche d'entretien du dimanche ; un mot de passe n'est jamais écrit.
- [x] Migration `007_journal_connexions.sql`, 22 vérifications de plus
  (110 au total).

## Fait (2026-11-01, lot 5)

- [x] **Témoignages sonores** : documents audio, lecture dans le navigateur,
      personnes qui parlent, onglet affiché seulement si un son existe.
- [x] **Recettes de famille** : ingrédients, préparation, astuce, photo,
      visibilité, administration complète (migration `006_recettes.sql`).
- [x] **Impression** : pages sobres sans menu ni boutons, en-tête de site,
      bouton « Imprimer » sur les fiches, albums, recettes et frise.
- [x] **Export GEDCOM** de tout l'arbre, réservé aux administrateurs, écrit
      sans charger l'arbre en mémoire.

## Fait (2026-11-01, lot 4)

- [x] **Calendrier familial** : grille mensuelle toutes années confondues,
      naissances, mariages et décès, détail au jour, navigation par mois.
- [x] **Frise chronologique** : événements et documents datés, par périodes de
      cinquante ans, 400 événements au plus par page.
- [x] Lecture des dates écrites à la main (ISO, française, « 12 juin 1928 »,
      « vers 1930 »), âges calculés, droits respectés.
- [x] Dates de démonstration complètes, 5 captures, 15 vérifications de plus.

## Fait (2026-11-01, lot 3)

- [x] **Albums photos** : rangement des photos existantes, titre, description,
      visibilité, ordre, photo de couverture ; aucune copie de fichier.
- [x] **Personnes identifiées** sur un document (photo, témoignage) :
      association depuis l'administration, affichage sur la fiche du document
      et sur celle de la personne, légendes filtrées par les droits.
- [x] Migration `005_albums.sql`, démonstration et 7 captures d'écran.

## Fait (2026-11-01, lot 2)

- [x] **Recherche générale** (`/recherche`) : documents, articles, pages
      d'information et personnes ; accents et majuscules ignorés, index `FTS5`
      tenu à jour par déclencheurs, repli `GLOB` si FTS5 manque.
- [x] Résultats groupés par catégorie, onglets de filtrage, pagination de 24,
      droits de visibilité respectés (aucun résultat interdit).
- [x] **Fiche personne enrichie** : chronologie, famille élargie
      (grands-parents, oncles et tantes, petits-enfants, neveux et nièces),
      lieux cliquables, homonymes.
- [x] Rapidité : 6000 personnes cherchées en 9–22 ms (`CROSS JOIN` avec
      l'index) au lieu de 5–8 s.
- [x] `maintenance.php` reconstruit l'index ; 48 vérifications automatiques ;
      4 captures d'écran.

## Fait (2026-11-01, lot 1)

- [x] **Tchat privé** : un salon commun réservé aux comptes connectés
      (onglet « Tchat », pastille des nouveaux messages).
- [x] Signature « BRANCHE/Prénom » (prénom demandé au premier passage, gardé en
      session, modifiable) ; « ADMIN/nom » pour les administrateurs.
- [x] Texte brut, 1000 caractères, liens sûrs, sauts de ligne conservés.
- [x] Mise à jour par interrogation (5 s, ralentie à 30 s, arrêtée quand
      l'onglet est caché) ; anti-spam 1/s et 30/min.
- [x] 50 derniers messages + « voir les messages plus anciens » ; séparateur par
      journée, défilement automatique.
- [x] Administrateur : suppression d'un message avec trace au journal d'activité.
- [x] Réglages : ouverture/fermeture du tchat, conservation
      (jamais / 1 an / 6 mois / 3 mois) appliquée par `maintenance.php`.
- [x] Migration `004_tchat.sql`, démonstration et 4 captures d'écran.

## Fait (2026-11-01, lot 0)

- [x] Rubrique **Articles** intégrée (branche `claude/articles`) : fil
      d'actualité, brouillons, publication programmée, « À la une ».
- [x] Onglet **Vidéos** ouvert à tout le monde : les visiteurs voient les
      vidéos publiques, les autres restent protégées.
- [x] Une seule fonction de date longue dans le code (`french_long_date()`).

## Fait (2026-11-01)

- [x] Habillage « journal » des pages publiques et de l'espace famille
      (palette inchangée, police Old Standard TT auto-hébergée, aucun arrondi,
      aucun appel externe).
- [x] Onglet **Vidéos** (liste filtrée par les droits, fiche avec lecteur,
      lecture par morceaux, limite de lectures simultanées avec message clair).
- [x] Administration des vidéos : mise en avant, ordre d'affichage, durée,
      autorisation de téléchargement, import depuis le dossier arrivée.
- [x] Migration `002_videos.sql` (ajout de colonnes seulement).
- [x] Captures d'écran versées dans `docs/captures/`.
- [x] Ménage des fichiers envoyés par erreur à la racine du dépôt.

## Prochains lots (un par un, après accord)

(Tous les autres lots prévus sont terminés. La suite dépend des envies de la
famille : voir « Idées à étudier » plus bas.)

## Avant la mise en ligne (fait par Clément)

- [ ] Acheter / vérifier le domaine `histoiredefamille.eu` et le placer chez
      Cloudflare (serveurs DNS).
- [ ] Installer Ubuntu Server sur la carte SD du Raspberry Pi 3B+.
- [ ] Formater le SSD et la clé USB, puis suivre `deploy/montage-disques.md`.
- [ ] Récupérer le code, lancer `sudo bash deploy/install.sh`.
- [ ] Adapter `config.local.php` (nom du site, description, adresse de contact).
- [ ] Créer **les trois comptes administrateurs** (un par personne).
- [ ] Créer les branches familiales et noter les mots de passe générés.
- [ ] Créer le tunnel Cloudflare (`cloudflared tunnel create`), copier
      `TUNNEL-ID.json` dans la configuration, router le domaine.
- [ ] Vérifier l'accès depuis un téléphone en 4G.
- [ ] Changer le mot de passe SSH du Pi, et si possible passer par une clé.
- [ ] Lancer une première sauvegarde à la main et vérifier
      `deploy/backup.sh --verifier`.
- [ ] **Sans disque externe (début)** : lire la section 8.0 du README, faire une
      copie manuelle du dossier `storage` sur un ordinateur, et ne poser
      `'backup_allow_same_disk' => true` que si l'on accepte une sauvegarde sur
      la carte SD, le temps que le disque arrive.

## Contenu (fait par la famille)

- [ ] Saisir ou importer l'arbre généalogique (GEDCOM si un fichier existe).
- [ ] Vérifier que les personnes récentes ne sont pas en « Public ».
- [ ] Envoyer les photos des albums papier (scan 300 dpi conseillé).
- [ ] Convertir les films en MP4 H.264 + AAC, les déposer dans `arrivee/`
      puis les importer.
- [ ] Rédiger les pages d'information : histoire de la famille, annonces.
- [ ] Relire les fiches de personnes âgées avec un membre de la famille.

## Développement (évolutions souhaitées, non planifiées)

### Court terme

- [ ] Inclure les **recettes** dans la recherche générale (index FTS5 à étendre,
      donc à faire avec une montée de version de l'index).
- [ ] Inclure les **albums** dans la recherche générale (même remarque).

- [ ] Export GEDCOM (aujourd'hui l'import existe, pas l'export).
- [ ] Purge automatique des fichiers orphelins (fichiers présents dans
      `medias/` mais absents de la base).
- [ ] Aperçu des PDF dans les listes (première page en miniature).

### Moyen terme

- [ ] Chapitrage des vidéos (marqueurs de temps saisis par l'administrateur).
- [ ] Commentaires sur les documents, réservés aux comptes de famille.
- [ ] Album photo : regroupement manuel de plusieurs photos.
- [ ] Envoi multiple de photos en une fois (avec progression).
- [ ] Livret imprimable (PDF) d'une branche de l'arbre.

### Technique / exploitation

- [ ] Sur le Pi : vérifier l'affichage du nouvel habillage dans un vrai
      navigateur (Firefox et Safari inclus) et la police auto-hébergée.
- [ ] Sur le Pi : lire un film MP4 de plusieurs centaines de Mo d'un bout à
      l'autre, avec avance rapide, et vérifier que la mémoire reste stable.
- [ ] Sur le Pi : vérifier que deux vidéos en parallèle déclenchent bien le
      message d'attente à la troisième.

- [ ] Vérifier la consommation de RAM avec deux lectures vidéo simultanées et
      ajuster `video.max_concurrent_streams` si nécessaire.
- [ ] Mettre en place une alerte de saturation du SSD (courriel ou webhook).
- [ ] Sauvegarde hors du domicile : copie chiffrée de la clé USB chez un
      proche, une fois par trimestre.
- [ ] Automatiser le test de restauration (aujourd'hui hebdomadaire via cron,
      vérifier le journal de temps en temps).
- [ ] Documenter une procédure de remplacement de la carte SD (avec la liste
      exacte des paquets à réinstaller).

## Idées à étudier (sans implémentation pour l'instant)

Rien n'est décidé : ces idées sont notées pour être étudiées plus tard, et ne
doivent pas être codées sans un accord explicite.

- **Courriel à la publication** : prévenir les personnes connectées quand un
  article ou un document paraît. À étudier : envoi depuis le Pi (le compte de
  messagerie est-il fiable ? risques de spam), ou simple résumé affiché à la
  prochaine visite. Un envoi de courriel demande un service SMTP et des mots de
  passe supplémentaires : à ne pas ajouter sans en mesurer le coût.
- **Lettre d'information mensuelle** : un courriel récapitulatif (nouveaux
  articles, photos, anniversaires) envoyé une fois par mois. Même question que
  ci-dessus, plus la gestion des personnes qui ne veulent pas la recevoir.
- **Carte des lieux** : afficher sur une carte les villes et villages de la
  famille. À étudier **sans service externe** (pas de Google Maps, pas de
  Leaflet avec des tuiles distantes) : soit une carte dessinée à la main en
  SVG, soit des fonds de carte libres stockés sur le Pi — en vérifiant le poids
  et la licence.

## Idées en vrac (à discuter)

- Un « mur des souvenirs » avec les documents récents en page d'accueil.
- Une page par branche avec ses documents et ses personnes.
- Un mode « grand écran » pour projeter l'arbre lors des réunions de famille.
- Un formulaire de suggestion pour les membres qui repèrent une erreur, sans
  leur donner de droits d'écriture.

## Ce qui ne sera pas fait

- Réencodage des vidéos sur le Pi : beaucoup trop long et trop chaud pour un
  Raspberry Pi 3B+.
- Application mobile : le site est responsive, c'est suffisant.
- Inscription publique : les comptes sont créés uniquement par les
  administrateurs.
- Commentaires ouverts au public : le site reste familial.
