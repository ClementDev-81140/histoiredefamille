# Journal des versions

Toutes les dates sont au format `AAAA-MM-JJ`.
Le projet suit un numéro de version simple : `majeure.mineure.correctif`.

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
