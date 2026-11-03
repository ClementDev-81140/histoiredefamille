# À faire

Ce fichier liste ce qui reste à faire, classé par priorité. Il n'y a pas de
suivi automatique : cochez au fur et à mesure.

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

(Tous les lots prévus sont terminés. La suite dépend des envies de la famille :
voir « Idées à étudier » plus bas.)

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
