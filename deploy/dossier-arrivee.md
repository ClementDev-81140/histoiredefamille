# Déposer des vidéos dans le dossier « arrivée »

Les vidéos pèsent 10 à 50 Go : impossible de les envoyer par le navigateur
(Cloudflare bloque les requêtes au-delà de ~100 Mo, et un envoi de 10 Go
prendrait des heures). On procède donc en deux temps :

1. vous copiez le film **sur le réseau de la maison**, directement dans le
   dossier `/mnt/ssd/histoiredefamille/arrivee` du Raspberry Pi ;
2. dans l'administration du site, page **Importer depuis le dossier arrivée**,
   vous donnez un titre, une date, une branche, une visibilité, et le fichier
   est rangé définitivement au bon endroit.

Aucun fichier déposé dans `arrivee/` n'est accessible depuis Internet : ce
dossier n'est pas dans la racine web.

---

## 1. Avant tout : convertir la vidéo en MP4 (H.264 + AAC)

Le site **ne réencode pas** les vidéos : c'est volontaire, un Raspberry Pi
3B+ mettrait des dizaines d'heures à convertir un film et chaufferait pour
rien. Il faut donc fournir un fichier que tous les navigateurs savent lire :

- conteneur **MP4**
- vidéo **H.264** (profil High level 4.0, `yuv420p`)
- audio **AAC** stéréo
- « faststart » (l'index au début du fichier, indispensable pour démarrer la
  lecture sans télécharger tout le fichier)

### Avec HandBrake (le plus simple, interface graphique)

1. Ouvrir HandBrake, glisser le fichier source.
2. Préréglage : **Fast 1080p30** (ou *Fast 720p30* pour gagner de la place).
3. Onglet *Video* : codec **H.264**, qualité **RF 22**.
4. Onglet *Audio* : **AAC**, 128 ou 160 kb/s, stéréo.
5. Onglet *Summary* : cocher **Web Optimized** (c'est le faststart).
6. Enregistrer en `.mp4`, puis copier le fichier sur le Pi (étape 3).

### Avec ffmpeg (en ligne de commande, sur un PC)

```bash
ffmpeg -i film-original.avi \
  -c:v libx264 -preset slow -crf 22 -profile:v high -level 4.0 -pix_fmt yuv420p \
  -c:a aac -b:a 160k -ac 2 \
  -movflags +faststart \
  film-famille.mp4
```

Vérifier le résultat (doit afficher `h264` et `aac`) :

```bash
ffprobe -hide_banner -show_streams film-famille.mp4 | grep -E 'codec_name|width|height'
```

> Un film de 1 h 30 en 720p pèse environ 1,5 à 2 Go : c'est le bon compromis
> pour un Pi. En 1080p, comptez 3 à 4 Go.

---

## 2. Copier le fichier sur le Pi (réseau local)

Le Raspberry Pi doit être allumé et connecté à la même box que votre
ordinateur. Remplacez `IP-DU-PI` par l'adresse locale du Pi
(à trouver avec `hostname -I` sur le Pi).

### Méthode A — SFTP / SCP (rien à installer sur le Pi)

**Windows** : ouvrez l'Explorateur de fichiers et tapez dans la barre
d'adresse :

```
sftp://votre-utilisateur@IP-DU-PI/mnt/ssd/histoiredefamille/arrivee
```

Windows demande le mot de passe du compte `votre-utilisateur` (celui du Pi, pas celui
du site), puis vous pouvez glisser-déposer le fichier.

**macOS / Linux / Windows avec PowerShell** :

```bash
scp film-famille.mp4 votre-utilisateur@IP-DU-PI:/mnt/ssd/histoiredefamille/arrivee/
```

**Avec FileZilla** (graphique, si vous préférez) :

- Hôte : `sftp://IP-DU-PI`, port `22`, protocole **SFTP**
- Identifiant : `votre-utilisateur`, mot de passe : celui du Pi
- Dossier distant : `/mnt/ssd/histoiredefamille/arrivee`

> Si la copie échoue avec « Permission denied » : `/mnt/ssd/histoiredefamille/arrivee`
> appartient à `www-data`. Le plus simple :
>
> ```bash
> sudo chown votre-utilisateur:votre-utilisateur /mnt/ssd/histoiredefamille/arrivee
> sudo chmod 775 /mnt/ssd/histoiredefamille/arrivee
> sudo usermod -aG www-data votre-utilisateur   # puis se déconnecter/reconnecter
> ```
>
> Le script `deploy/install.sh` laisse le dossier accessible à `www-data` ;
> l'import le remet dans le bon état après chaque fichier déplacé.

### Méthode B — partage Samba (le dossier apparaît comme un lecteur réseau)

Pratique si vous voulez déposer des fichiers régulièrement depuis Windows.

```bash
sudo apt-get install -y samba
sudo tee -a /etc/samba/smb.conf >/dev/null <<'CONF'

[arrivee]
   comment = Dépôt des vidéos pour histoiredefamille.eu
   path = /mnt/ssd/histoiredefamille/arrivee
   browseable = yes
   read only = no
   create mask = 0660
   directory mask = 0770
   force group = www-data
   valid users = votre-utilisateur
CONF

sudo smbpasswd -a votre-utilisateur   # choisit un mot de passe pour le partage
sudo systemctl restart smbd
```

Sous Windows : ouvrir `\\IP-DU-PI\arrivee` (ou *Connecter un lecteur
réseau*), coller le fichier.

> Ne pas exposer Samba sur Internet : il ne sert qu'au réseau de la maison.
> Vérifiez qu'aucun port n'est redirigé sur la box (c'est déjà le cas puisque
> le site passe par le tunnel Cloudflare).

---

## 3. Importer depuis l'administration

1. Se connecter au site avec un compte administrateur.
2. Menu **Administration → Importer depuis le dossier arrivée**.
3. La page liste les fichiers présents (nom, taille, date de dépôt).
4. Pour chacun : titre, description, date du souvenir, catégorie, branche,
   visibilité — puis **Importer**.
5. Le fichier est déplacé dans `medias/année/mois/` sous un nom aléatoire,
   la miniature est créée si c'est une image, et le dossier `arrivee/` est
   vidé de ce fichier.

> ⚠️ **Visibilité** : si vous ne cochez rien, le document reste **privé**
> (visible uniquement par les administrateurs). Pour le rendre visible aux
> familles, choisissez *Toutes les familles* ou une branche précise.

### Bon à savoir

- Les fichiers déposés dans `arrivee/` ne sont **pas** visibles sur le site
  tant qu'ils ne sont pas importés.
- Un fichier de plus de 4 Go sur une clé USB en FAT32 ne passera pas :
  utilisez le SSD (ext4), qui n'a pas cette limite.
- L'import lit la taille du fichier mais ne le charge jamais en mémoire :
  même un film de 20 Go ne posera pas de problème de RAM.
- Vous pouvez supprimer un fichier déposé par erreur :
  `sudo rm /mnt/ssd/histoiredefamille/arrivee/nom-du-fichier.mp4`.
