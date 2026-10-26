# Monter le SSD et la clé USB sur le Raspberry Pi

Le site est prévu pour trois supports :

| Support | Point de montage | Contenu |
|---|---|---|
| Carte SD | `/` | système Ubuntu + code du site uniquement |
| SSD USB | `/mnt/ssd` | base SQLite, photos, vidéos, miniatures |
| Clé USB | `/mnt/sauvegarde` | sauvegardes (le script `backup.sh` les y dépose) |

L'intérêt : si la carte SD meurt (c'est la panne la plus fréquente sur un
Raspberry Pi), le code se réinstalle en 10 minutes et **les photos, vidéos et
l'arbre généalogique sont intacts sur le SSD**.

---

## 1. Brancher et repérer les disques

Branchez le SSD et la clé USB, puis :

```bash
lsblk -o NAME,SIZE,TYPE,FSTYPE,LABEL,MOUNTPOINT
```

Vous voyez des lignes du genre :

```
sda           117,2G disk
└─sda1        117,2G part ext4   ssd-famille
sdb            57,3G disk
└─sdb1         57,3G part  vfat   SAUVEGARDE
```

- `sda1` = le SSD, `sdb1` = la clé USB (les lettres peuvent changer chez vous).

> ⚠️ Ne confondez pas avec `mmcblk0` : c'est la carte SD du système.

## 2. Formater le SSD en ext4 (une seule fois)

> ⚠️ Cette étape **efface** le disque. Vérifiez bien le nom (`sda`, pas `sdb`).

```bash
sudo umount /dev/sda1 2>/dev/null || true
sudo mkfs.ext4 -L ssd-famille /dev/sda1
```

Pour la clé USB, gardez le format d'origine (`vfat`/exFAT) si vous voulez la
relire sur Windows, ou formatez-la en ext4 si elle reste sur le Pi :

```bash
sudo mkfs.ext4 -L sauvegarde /dev/sdb1
```

## 3. Relever les identifiants (UUID)

Un disque branché en USB peut changer de nom (`sda` devient `sdb`), mais son
UUID ne change jamais : c'est lui qu'on utilise dans `/etc/fstab`.

```bash
sudo blkid -s UUID -s LABEL
```

Exemple de résultat :

```
/dev/sda1: LABEL="ssd-famille" UUID="1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d" TYPE="ext4"
/dev/sdb1: LABEL="sauvegarde"  UUID="9f8e7d6c-5b4a-3928-1706-f5e4d3c2b1a0" TYPE="ext4"
```

Notez les deux UUID (copiez-collez, ne les retapez pas).

## 4. Points de montage

```bash
sudo mkdir -p /mnt/ssd /mnt/sauvegarde
```

## 5. Déclarer les disques dans /etc/fstab

Sauvegardez le fichier avant de le modifier :

```bash
sudo cp /etc/fstab /etc/fstab.avant-histoiredefamille
sudo nano /etc/fstab
```

Ajoutez ces deux lignes à la fin (remplacez les UUID par les vôtres) :

```
# SSD des données de la famille
UUID=1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d  /mnt/ssd        ext4  defaults,nofail,x-systemd.device-timeout=10  0  2
# Clé USB de sauvegarde
UUID=9f8e7d6c-5b4a-3928-1706-f5e4d3c2b1a0  /mnt/sauvegarde  ext4  defaults,nofail,x-systemd.device-timeout=10  0  2
```

Pourquoi ces options :

- **`nofail`** : si le disque est absent (débranché, en panne), le Pi démarre
  quand même. Sans cette option, le Raspberry Pi reste bloqué au démarrage en
  attendant un disque qui n'arrivera jamais.
- **`x-systemd.device-timeout=10`** : n'attend que 10 secondes au lieu de 90.
- **`0 2`** : pas de sauvegarde `dump`, vérification `fsck` à l'ordre 2.

> Si la clé est formatée en `vfat`, remplacez `ext4` par `vfat` et ajoutez
> `uid=www-data,gid=www-data,umask=007`.

Tester sans redémarrer :

```bash
sudo mount -a
df -h /mnt/ssd /mnt/sauvegarde
```

Vous devez voir les deux lignes avec les tailles du SSD et de la clé.
Un `mount -a` sans message d'erreur = configuration correcte.

## 6. Droits d'accès (indispensable)

Apache tourne sous l'utilisateur `www-data`. C'est lui qui doit pouvoir écrire
dans le stockage.

```bash
sudo mkdir -p /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous}
sudo chown -R www-data:www-data /mnt/ssd/histoiredefamille
sudo chmod 750 /mnt/ssd/histoiredefamille
sudo chmod 770 /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous}

sudo chown www-data:www-data /mnt/sauvegarde
sudo chmod 750 /mnt/sauvegarde
```

Vérification :

```bash
sudo -u www-data touch /mnt/ssd/histoiredefamille/tmp/essai && echo "écriture SSD : OK"
sudo rm /mnt/ssd/histoiredefamille/tmp/essai
```

## 7. Vérifier après un redémarrage

```bash
sudo reboot
```

Après une minute, reconnectez-vous en SSH :

```bash
df -h /mnt/ssd /mnt/sauvegarde
mount | grep -E 'ssd|sauvegarde'
```

Les deux disques doivent être montés. Si ce n'est pas le cas :

```bash
sudo dmesg | grep -i -E 'usb|sd[a-z]' | tail -20
sudo tail -20 /var/log/syslog
```

## 8. Réduire l'usure de la carte SD (recommandé)

La base SQLite écrit souvent. Elle est déjà sur le SSD, mais le journal système
peut rester sur la carte :

```bash
# Le journal système en mémoire (perdu au redémarrage, sans importance ici)
sudo sed -i 's/^#\?Storage=.*/Storage=volatile/' /etc/systemd/journald.conf
sudo sed -i 's/^#\?RuntimeMaxUse=.*/RuntimeMaxUse=32M/' /etc/systemd/journald.conf
sudo systemctl restart systemd-journald
```

Et éviter que la carte SD se remplisse de vieux paquets :

```bash
sudo apt-get autoremove -y
sudo apt-get clean
```

## En cas de problème

| Symptôme | Cause probable | Solution |
|---|---|---|
| Le Pi ne démarre plus après modification de fstab | erreur de frappe dans l'UUID | `nofail` aide, sinon démarrage en mode secours et `sudo nano /etc/fstab` |
| `mount: wrong fs type` | disque non formaté | revenir à l'étape 2 |
| `Permission denied` en écrivant dans `/mnt/ssd` | droits | refaire l'étape 6 |
| Le disque est monté en lecture seule | SSD fatigué ou erreur ext4 | `sudo dmesg \| tail -30`, sauvegarder puis `fsck` |
