# Dossier `deploy/` — installation et exploitation

Tout ce qui concerne le Raspberry Pi est ici. Le mode d'emploi complet se
trouve dans le [README principal](../README.md) ; ce fichier sert d'index.

| Fichier | À quoi ça sert | Quand |
|---|---|---|
| [`montage-disques.md`](montage-disques.md) | monter le SSD (`/mnt/ssd`) et la clé USB (`/mnt/sauvegarde`) par UUID, avec `nofail` et les droits `www-data` | **avant** l'installation |
| [`install.sh`](install.sh) | installe Apache + PHP, copie le site dans `/var/www`, prépare le stockage, applique les migrations, installe le minuteur de sauvegarde | une fois, puis à chaque mise à jour |
| [`apache/histoiredefamille.conf`](apache/histoiredefamille.conf) | VirtualHost Apache : racine web limitée à `public/`, blocage des fichiers sensibles, signature du serveur masquée — **les en-têtes de sécurité ne sont pas ici** : ils vivent une seule fois dans `public/.htaccess` | copié par `install.sh` |
| [`php/99-histoiredefamille.ini`](php/99-histoiredefamille.ini) | réglages PHP pour 1 Go de RAM : mémoire 128 Mo, envois 64 Mo, opcache 48 Mo | copié par `install.sh` |
| [`cloudflared/config.yml`](cloudflared/config.yml) | tunnel Cloudflare : `histoiredefamille.eu` → `http://localhost:80`, aucun port ouvert | à l'étape « mise en ligne » |
| [`cloudflared/cloudflared.service`](cloudflared/cloudflared.service) | service systemd du tunnel (si `cloudflared service install` échoue) | facultatif |
| [`systemd/histoiredefamille-sauvegarde.timer`](systemd/histoiredefamille-sauvegarde.timer) | minuteur systemd : sauvegarde tous les jours à **3 h 30**, rattrapée après une coupure | installé et activé par `install.sh` |
| [`systemd/histoiredefamille-sauvegarde.service`](systemd/histoiredefamille-sauvegarde.service) | lance `php app/cli/sauvegarde.php` en tant que `www-data`, compte rendu dans `journalctl` | installé par `install.sh` |
| [`backup.sh`](backup.sh) | ancien point d'entrée, conservé : appelle la commande `app/cli/sauvegarde.php` | uniquement pour les installations anciennes |
| [`crontab.example`](crontab.example) | contenu de `/etc/cron.d/histoiredefamille` (maintenance hebdomadaire) | copié par `install.sh` |
| [`dossier-arrivee.md`](dossier-arrivee.md) | convertir et déposer les vidéos par le réseau local (SFTP ou Samba), puis les importer | à chaque nouveau film |

## Ordre des opérations

```bash
# 1. sur le Pi, une seule fois : les disques
#    → suivre deploy/montage-disques.md, puis vérifier :
df -h /mnt/ssd /mnt/sauvegarde

# 2. récupérer le code
cd /home/votre-utilisateur
git clone https://github.com/VOTRE-COMPTE/histoiredefamille.git
cd histoiredefamille

# 3. installer
sudo bash deploy/install.sh

# 4. adapter la configuration
sudo nano /var/www/histoiredefamille/config.local.php

# 5. créer les trois comptes administrateurs (mot de passe saisi au clavier)
sudo -u www-data php /var/www/histoiredefamille/app/cli/create-admin.php

# 6. vérifier
curl -I http://localhost/

# 7. première sauvegarde + minuteur
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php
systemctl list-timers histoiredefamille-sauvegarde.timer
```

Puis la mise en ligne (tunnel Cloudflare) : § 5 du README principal.

## Sauvegarde : les trois commandes à retenir

```bash
# lancer une sauvegarde maintenant
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php

# voir le minuteur et le compte rendu de la dernière nuit
systemctl list-timers histoiredefamille-sauvegarde.timer
journalctl -u histoiredefamille-sauvegarde.service -n 30

# lire le journal de sauvegarde
sudo tail -30 /mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log
```

## Réglages modifiables sans toucher au code

Tout se règle dans `config.local.php` (et non dans ces fichiers) :

| Réglage | Clé |
|---|---|
| Taille maximale d'un envoi par le navigateur | `uploads.max_mb` |
| Extensions acceptées | `uploads.allowed_extensions` |
| Taille des miniatures | `uploads.thumb_max` |
| Lectures vidéo simultanées | `video.max_concurrent_streams` |
| Blocage après échecs de connexion | `security.login_max_attempts`, `login_block_minutes` |
| Emplacement de la base et du stockage | `database`, `storage.root` |
| Dossier et journal de sauvegarde | `maintenance.backup_dir`, `backup_log` |

Après une modification de `config.local.php`, rien à redémarrer : le fichier
est lu à chaque requête. Après une modification des fichiers `.ini` ou Apache :
`sudo systemctl restart apache2`.
