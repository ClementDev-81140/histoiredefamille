# Chiffrer la clé USB de sauvegarde (facultatif)

> Cette page explique une **option**, pas une obligation. Le site fonctionne
> très bien sans chiffrement. À lire avant de décider.

## De quoi parle-t-on ?

La clé USB contient **toute** l'histoire de la famille : la base (comptes,
arbre, articles, tchat) et les fichiers (photos, vidéos, témoignages). Si la
clé est perdue ou volée, ces données sont lisibles par n'importe qui avec un
ordinateur, sans mot de passe.

Le chiffrement (LUKS, le standard sous Linux) rend la clé **illisible** sans un
mot de passe. C'est le même principe qu'un coffre : sans la combinaison, le
contenu est du bruit.

## Avantages

- **Vol ou perte de la clé** : personne ne peut lire les photos ni la base.
- **Réparation** : une clé chiffrée peut être prêtée ou envoyée sans crainte.
- **Copie hors du domicile** (chez un proche, au bureau) : sans risque de fuite.

## Risques — à connaître avant de commencer

1. **Le mot de passe devient la clé de tout.** S'il est oublié, les sauvegardes
   sont **définitivement perdues**. Il n'existe aucun « mot de passe de secours ».
2. **Il faut le mot de passe à chaque démarrage du Raspberry Pi**, sinon la clé
   ne se monte pas, la sauvegarde est annulée (c'est voulu) et l'administration
   affiche une alerte rouge.
3. **Le mot de passe doit être noté ailleurs que sur la clé** : sur papier, dans
   un tiroir, ou dans un gestionnaire de mots de passe. Jamais dans
   `config.local.php` (le code du site est public sur GitHub).
4. **La restauration devient une opération en deux temps** : ouvrir la clé
   (mot de passe), puis restaurer. Ce n'est pas plus compliqué, mais il faut y
   penser le jour où tout va mal.
5. **Un disque chiffré se répare moins facilement** en cas de corruption : le
   contrôle d'intégrité de SQLite reste possible une fois la clé ouverte.

## Ce qu'il faut décider

| Situation | Recommandation |
|---|---|
| La clé reste à la maison, dans un tiroir | Le chiffrement est **utile** (vol, visite) |
| La clé voyage (prêt, envoi, copie hors domicile) | Le chiffrement est **fortement conseillé** |
| Le mot de passe risque d'être perdu | **Ne pas chiffrer** : une sauvegarde lisible vaut mieux qu'une sauvegarde perdue |
| Plusieurs personnes doivent pouvoir restaurer | Chiffrer, mais **partager le mot de passe** avec au moins deux personnes de confiance |

## Mise en place (résumé)

Ces commandes se lancent **sur le Raspberry Pi**, avec la clé branchée mais
**pas encore montée**. Remplacez `/dev/sdX1` par le nom réel de la clé (vu avec
`lsblk`) : **une erreur de nom efface un disque**.

```bash
# 1. Vérifier le nom de la clé (taille, modèle) — NE PAS SE TROMPER
lsblk -o NAME,SIZE,MODEL,MOUNTPOINT

# 2. Chiffrer la partition (efface tout ce qui est dessus)
sudo cryptsetup luksFormat /dev/sdX1
sudo cryptsetup open /dev/sdX1 sauvegarde
sudo mkfs.ext4 /dev/mapper/sauvegarde
sudo cryptsetup close sauvegarde
```

Puis, pour que la clé se monte **automatiquement** au démarrage, il faut une
clé de déchiffrement dans un fichier lisible par root uniquement
(`/etc/luks/sauvegarde.key`, droits `600`) :

```bash
sudo mkdir -p /etc/luks
sudo dd if=/dev/urandom of=/etc/luks/sauvegarde.key bs=512 count=8
sudo chmod 600 /etc/luks/sauvegarde.key
sudo cryptsetup luksAddKey /dev/sdX1 /etc/luks/sauvegarde.key
```

L'entrée dans `/etc/crypttab` :

```
sauvegarde  UUID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx  /etc/luks/sauvegarde.key  luks,nofail
```

et dans `/etc/fstab` :

```
/dev/mapper/sauvegarde  /mnt/sauvegarde  ext4  defaults,nofail,noatime  0  2
```

`nofail` est important : si la clé est absente, le Pi démarre quand même (et la
sauvegarde s'annule proprement au lieu de bloquer le démarrage).

> **Ici, le mot de passe est dans un fichier root.** Cela protège contre le vol
> de la clé seule, pas contre quelqu'un qui a déjà la main sur le Raspberry Pi.
> C'est le compromis habituel pour une sauvegarde automatique : sans ce fichier,
> il faudrait taper le mot de passe chaque nuit.

## Vérifier que tout fonctionne

```bash
df -h /mnt/sauvegarde                       # la clé est montée
sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php
```

Résultat attendu : « Sauvegarde réussie ». Si la clé n'est pas montée, la
commande s'arrête sans rien écrire et l'administration affiche une alerte rouge.

## Revenir en arrière (déchiffrer)

Il faut recopier les données ailleurs (elles sont lisibles une fois la clé
ouverte), puis reformater la clé en clair :

```bash
sudo cryptsetup open /dev/sdX1 sauvegarde
sudo rsync -a /mnt/sauvegarde/ /chemin/vers/un/disque/temporaire/
sudo umount /mnt/sauvegarde && sudo cryptsetup close sauvegarde
sudo mkfs.ext4 /dev/sdX1
```

Puis remonter la clé (voir `deploy/montage-disques.md`) et refaire une
sauvegarde complète.
