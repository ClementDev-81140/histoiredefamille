# Dossier de stockage (développement uniquement)

En production, la base SQLite, les photos, les vidéos et les miniatures ne sont
**pas** ici : ils sont sur le SSD, dans `/mnt/ssd/histoiredefamille`
(chemin défini par `storage.root` dans `config.local.php`).

Ce dossier ne sert qu'à faire tourner le site sur un ordinateur de test : les
valeurs par défaut de `app/config.php` y écrivent quand aucun
`config.local.php` n'est présent.

Contenu créé automatiquement par `php app/cli/migrate.php` :

| Sous-dossier | Rôle |
|---|---|
| `histoire.sqlite` | base de données (mode WAL : `-wal` et `-shm` à côté) |
| `medias/` | fichiers envoyés (documents, photos, vidéos), rangés par année/mois |
| `miniatures/` | miniatures JPEG générées à l'envoi (pour les listes) |
| `arrivee/` | dossier où déposer les gros fichiers (SFTP/Samba) avant import |
| `tmp/` | fichiers temporaires (aperçu GEDCOM, etc.) |
| `verrous/` | verrous des lectures vidéo simultanées |

**Tout ce dossier est exclu de Git** (voir `.gitignore`) : aucun document
familial réel ne doit se retrouver dans un dépôt public.

Pour repartir de zéro en développement :

```bash
rm -rf storage/histoire.sqlite* storage/medias storage/miniatures storage/arrivee storage/tmp storage/verrous
php app/cli/migrate.php
php app/cli/create-admin.php
```
