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

---

## 1. Ce que le site sait faire

### Partie publique (visible par tous)

- Une page d'accueil sobre : présentation, derniers documents publics, pages
  d'information.
- Les documents marqués « Public » : photos avec miniature, PDF consultables
  dans le navigateur, autres fichiers téléchargeables.
- La recherche simple (titre, description, étiquettes).
- Les pages d'information publiques (histoire de la famille, annonces).
- L'arbre généalogique pour les personnes marquées « Public ».

### Partie famille (après connexion)

- Tout ce que voit le public, **plus** les documents réservés à la famille ou
  à une branche précise.
- Un compte par branche familiale (un identifiant et un mot de passe partagés
  par les membres de cette branche).

### Administration (3 comptes personnels)

- Tableau de bord séparé en deux blocs : **Partie publique** et **Partie famille**.
- Documents : envoi par le navigateur (photos, PDF, sons, documents bureautiques
  jusqu'à 50 Mo), titre, description, date, catégorie, étiquettes, **visibilité**,
  modification et suppression.
- Catégories de documents (créées par les administrateurs).
- Branches familiales : création, modification, suppression, **réinitialisation
  du mot de passe en un clic** (mot de passe lisible affiché une seule fois),
  désactivation/réactivation du compte. Case à cocher
  « **voit toutes les branches** » pour un compte qui doit tout voir.
- Comptes administrateurs : 3 comptes personnels, mot de passe modifiable.
- Pages d'information : texte simple (markdown léger) avec visibilité.
- Arbre généalogique : saisie manuelle des personnes et des couples, ou import
  **GEDCOM** avec aperçu avant validation.
- **Import depuis le dossier arrivée** : pour les gros fichiers (vidéos)
  déposés sur le Pi par le réseau local.
- Page **État du site** : version de PHP, limites d'envoi, taille de la base,
  espace disque libre, dernières lignes du journal de sauvegarde, contrôles
  de sécurité.
- Réglages du site : nom, sous-titre, description, adresse de contact.

### Vidéos

- Lecture dans le navigateur avec `<video>`, **sans réencodage** (le Pi ne
  pourrait pas convertir un film).
- Diffusion par morceaux (HTTP Range) : on peut se déplacer dans la vidéo
  sans télécharger tout le fichier, et la mémoire du Pi n'est jamais saturée.
- Limite de lectures simultanées (2 par défaut) pour ne pas bloquer le site.

### Visibilité : la règle la plus importante

Chaque document, page et personne a un niveau de visibilité :

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

Points importants :

- **Aucune inscription publique** : seuls les administrateurs créent des comptes.
- Un compte de famille est rattaché à **une branche**. Toute la branche partage
  le même mot de passe : c'est simple pour les cousins, qui n'ont qu'un
  identifiant à retenir.
- « Voit toutes les branches » est une **case à cocher sur la branche**, pas un
  nom en dur dans le code : aucune famille n'est privilégiée par le programme.
- Après 5 échecs de connexion, l'identifiant **et** l'adresse IP sont bloqués
  15 minutes.
- Les mots de passe sont stockés hachés (Argon2id si disponible, sinon bcrypt).
  Personne, pas même un administrateur, ne peut les relire.
- Un administrateur peut réinitialiser le mot de passe d'une branche : un mot
  de passe lisible du type `shxn-2qb6-5et5` est généré et affiché **une seule
  fois**. Notez-le et transmettez-le à la branche.

---

## 3. Comment le code est organisé

```
histoiredefamille/
├── public/                  ← SEUL dossier visible depuis le web
│   ├── index.php            point d'entrée unique (toutes les URL passent ici)
│   ├── maintenance.html     page statique affichée en cas de 503
│   ├── .htaccess            URL propres, blocage des fichiers sensibles, en-têtes
│   └── assets/              style.css et app.js (aucune bibliothèque externe)
├── app/                     ← code de l'application (hors racine web)
│   ├── bootstrap.php        démarrage, gestion des erreurs
│   ├── config.php           configuration par défaut + lecture de config.local.php
│   ├── router.php           routeur minimal
│   ├── routes.php           table des URL
│   ├── controllers/         une fonction par page
│   ├── lib/                 base de données, sécurité, stockage, images, vidéos,
│   │                        GEDCOM, visibilité, textes…
│   ├── views/               gabarits PHP (public, famille, admin)
│   ├── migrations/          fichiers SQL numérotés (001_init.sql, …)
│   └── cli/                 commandes : migrate, create-admin, demo, maintenance
├── deploy/
│   ├── install.sh           installation complète sur le Pi
│   ├── backup.sh            sauvegarde quotidienne + test de restauration
│   ├── crontab.example      tâches planifiées
│   ├── apache/              VirtualHost Apache
│   ├── php/                 réglages PHP pour le Pi
│   ├── cloudflared/         configuration du tunnel
│   ├── montage-disques.md   SSD et clé USB (fstab, droits)
│   └── dossier-arrivee.md   déposer des vidéos par le réseau local
├── config.example.php       modèle de configuration (à copier)
├── config.local.php         VOTRE configuration (jamais dans Git)
├── storage/                 stockage local de développement (jamais dans Git)
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
9. installe la sauvegarde quotidienne dans `/etc/cron.d/histoiredefamille`.

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

### 6.3 Ajouter un film (gros fichier)

Voir **[`deploy/dossier-arrivee.md`](deploy/dossier-arrivee.md)** et le § 9
ci-dessous. Résumé :

```bash
# depuis votre ordinateur, sur le réseau de la maison
scp film-famille.mp4 votre-utilisateur@histoiredefamille.local:/mnt/ssd/histoiredefamille/arrivee/
```

puis **Administration → Importer depuis le dossier arrivée**.

### 6.4 Remplir l'arbre généalogique

**Administration → Arbre** :

1. Créer les personnes (nom, prénom, dates, lieux, notes, photo, visibilité).
2. Créer les **couples** (deux personnes + dates de mariage).
3. Pour chaque personne, indiquer les parents (liste déroulante).

L'arbre se consulte sur `/arbre` (vue par défaut) ou depuis la fiche d'une
personne. Le déplacement dans l'arbre est fait par un petit script maison,
sans bibliothèque externe.

### 6.5 Pages d'information

**Administration → Pages** : texte simple, avec quelques mises en forme
(`## titre`, `**gras**`, `*italique*`, `- liste`, `[lien](adresse)`), une
visibilité par page. Exemple de contenu : l'histoire de la famille, les
annonces de réunion, une page « qui contacter ».

---

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

---

## 8. Sauvegarde et restauration

### 8.1 Ce qui est sauvegardé

Chaque nuit à 3 h 15, `deploy/backup.sh` :

1. copie la base avec `sqlite3 .backup` — **jamais `cp`**, qui produirait un
   fichier corrompu sur une base en cours d'utilisation ;
2. conserve les **14 dernières copies** de la base (deux semaines) ;
3. synchronise les fichiers (photos, vidéos, PDF) avec `rsync` : seuls les
   nouveaux fichiers sont copiés, c'est rapide même avec 50 Go ;
4. écrit un journal dans `/mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log` ;
5. écrit un fichier d'état lu par la page **État du site** ;
6. signale les échecs (clé USB absente, espace insuffisant, base illisible).

> **La clé USB doit avoir une capacité supérieure au volume des données.**
> Base + photos + vidéos doivent tenir dessus, avec de la marge. Pour 50 Go de
> vidéos, prenez une clé de 64 Go minimum.

### 8.2 Tester la sauvegarde (à faire une fois par mois)

```bash
sudo -u www-data /var/www/histoiredefamille/deploy/backup.sh --verifier
```

Résultat attendu :

```
Test de restauration de : /mnt/sauvegarde/histoiredefamille/base/histoire-2026-10-25_0315.sqlite.gz
  · intégrité SQLite : ok
  · comptes : 6
  · documents : 148
  · personnes : 312
Restauration vérifiée avec succès.
```

Cette commande ne modifie rien : elle décompresse la dernière copie dans un
dossier temporaire, vérifie son intégrité et compte les lignes.

Vérifier aussi que les fichiers sont bien copiés :

```bash
ls /mnt/sauvegarde/histoiredefamille/base | tail -5
du -sh /mnt/sauvegarde/histoiredefamille/fichiers
```

### 8.3 Restaurer (en cas de perte du SSD ou de fausse manœuvre)

```bash
# 1. arrêter le site
sudo systemctl stop apache2

# 2. garder l'ancienne base de côté (au cas où)
sudo mv /mnt/ssd/histoiredefamille/histoire.sqlite /mnt/ssd/histoiredefamille/histoire.sqlite.avant-restauration 2>/dev/null || true
sudo rm -f /mnt/ssd/histoiredefamille/histoire.sqlite-wal /mnt/ssd/histoiredefamille/histoire.sqlite-shm

# 3. remettre la dernière sauvegarde
ls -1t /mnt/sauvegarde/histoiredefamille/base/histoire-*.sqlite.gz | head -1
sudo gzip -dc "$(ls -1t /mnt/sauvegarde/histoiredefamille/base/histoire-*.sqlite.gz | head -1)" \
  | sudo tee /mnt/ssd/histoiredefamille/histoire.sqlite >/dev/null
sudo chown www-data:www-data /mnt/ssd/histoiredefamille/histoire.sqlite
sudo chmod 640 /mnt/ssd/histoiredefamille/histoire.sqlite

# 4. vérifier puis redémarrer
sudo -u www-data sqlite3 /mnt/ssd/histoiredefamille/histoire.sqlite 'PRAGMA integrity_check;'
sudo systemctl start apache2
```

Pour restaurer **aussi les fichiers** (photos, vidéos) :

```bash
sudo systemctl stop apache2
sudo rsync -a --delete /mnt/sauvegarde/histoiredefamille/fichiers/ /mnt/ssd/histoiredefamille/
sudo chown -R www-data:www-data /mnt/ssd/histoiredefamille
sudo systemctl start apache2
```

> Si la carte SD est morte : réinstallez Ubuntu, remontez le SSD et la clé
> (§ 4.2), réinstallez le site (§ 4.3 à 4.6), puis restaurez la base comme
> ci-dessus. La base contient les comptes : inutile de les recréer.

### 8.4 Alertes en cas d'échec

Trois mécanismes, du plus simple au plus pratique :

1. **Le journal** : `/mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log`.
2. **La page État du site** : un bandeau rouge apparaît sur le tableau de bord
   si la dernière sauvegarde a échoué ou date de plus de deux jours.
3. **Une notification HTTP** (facultatif) : si vous avez une adresse de webhook
   (service de notifications, par exemple), ajoutez-la dans la tâche cron :

```bash
sudo nano /etc/cron.d/histoiredefamille
```

```cron
15 3 * * * www-data WEBHOOK_URL="https://exemple.test/alerte" /var/www/histoiredefamille/deploy/backup.sh >/dev/null 2>&1
```

Le script envoie alors un court message en cas d'échec.

### 8.5 Surveiller l'espace disque

```bash
df -h /mnt/ssd /mnt/sauvegarde
du -sh /mnt/ssd/histoiredefamille/medias
```

La page **État du site** affiche aussi l'espace libre du SSD.

### 8.6 Repartir d'une base vierge (développement seulement)

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
`config.local.php`, clé `video.max_concurrent_streams`). Au-delà, le site
répond « Trop de vidéos sont en cours de lecture » avec un code 503 et
demande de réessayer dans 30 secondes. C'est volontaire : le Pi doit rester
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

### 10.3 Conseils de saisie

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
| Sauvegarde en échec | clé USB absente ou pleine | `df -h /mnt/sauvegarde`, rebrancher, relancer `sudo -u www-data /var/www/histoiredefamille/deploy/backup.sh` |
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

Vos données vivent exclusivement sur le Raspberry Pi, dans
`/mnt/ssd/histoiredefamille`. La configuration locale (`config.local.php`) et
le dossier `storage/` sont listés dans `.gitignore`.

### Ce que le code applique

- **Mots de passe** : `password_hash` (Argon2id si disponible, sinon bcrypt
  coût 12) ; jamais de mot de passe en clair, jamais de mot de passe dans un
  fichier.
- **Formulaires** : jeton CSRF sur toutes les actions qui modifient quelque
  chose ; message « Session expirée » si le jeton manque.
- **Base de données** : requêtes préparées PDO partout (aucune concaténation
  de SQL avec une valeur saisie).
- **Affichage** : tout est échappé avec `htmlspecialchars` (via la fonction
  `e()`), y compris les noms venant d'un GEDCOM.
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
- **Apache** : `.git`, `.env`, `config*.php`, `*.sqlite`, `*.bak`, `*.log` sont
  bloqués, y compris le dossier `app/` (hors racine web) et le contenu du SSD.
- **SQLite** : mode WAL, `busy_timeout` 5000 ms, 3 tentatives espacées de
  300 ms sur les écritures sensibles (la cause du « database is locked » vue
  sur un autre site).

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

## Licence

Code privé, réservé à l'usage familial. Aucune donnée de tiers n'est incluse.
Les contenus de démonstration (`app/cli/demo.php`) sont entièrement fictifs.
