# À faire

Ce fichier liste ce qui reste à faire, classé par priorité. Il n'y a pas de
suivi automatique : cochez au fur et à mesure.

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

- [ ] Export GEDCOM (aujourd'hui l'import existe, pas l'export).
- [ ] Purge automatique des fichiers orphelins (fichiers présents dans
      `medias/` mais absents de la base).
- [ ] Aperçu des PDF dans les listes (première page en miniature).
- [ ] Bouton « copier le mot de passe » après réinitialisation d'une branche.
- [ ] Afficher la dernière connexion d'une branche dans la liste des comptes.

### Moyen terme

- [ ] Chapitrage des vidéos (marqueurs de temps saisis par l'administrateur).
- [ ] Commentaires sur les documents, réservés aux comptes de famille.
- [ ] Album photo : regroupement manuel de plusieurs photos.
- [ ] Envoi multiple de photos en une fois (avec progression).
- [ ] Livret imprimable (PDF) d'une branche de l'arbre.

### Technique / exploitation

- [ ] Vérifier la consommation de RAM avec deux lectures vidéo simultanées et
      ajuster `video.max_concurrent_streams` si nécessaire.
- [ ] Mettre en place une alerte de saturation du SSD (courriel ou webhook).
- [ ] Sauvegarde hors du domicile : copie chiffrée de la clé USB chez un
      proche, une fois par trimestre.
- [ ] Automatiser le test de restauration (aujourd'hui hebdomadaire via cron,
      vérifier le journal de temps en temps).
- [ ] Documenter une procédure de remplacement de la carte SD (avec la liste
      exacte des paquets à réinstaller).

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
