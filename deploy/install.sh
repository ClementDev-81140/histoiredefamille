#!/usr/bin/env bash
# ---------------------------------------------------------------------------
#  Histoire de famille — installation sur le Raspberry Pi
# ---------------------------------------------------------------------------
#  À lancer UNE FOIS, depuis le dossier du site, sur le Raspberry Pi :
#
#      cd /home/votre-utilisateur/histoiredefamille
#      sudo bash deploy/install.sh
#
#  Le script installe Apache, PHP et ses extensions, active les modules
#  nécessaires, copie le site dans /var/www/histoiredefamille, crée les
#  dossiers de stockage avec les bons droits, puis prépare la base.
#
#  Il ne crée PAS le compte administrateur : à la fin, il affiche la commande
#  à lancer vous-même (le mot de passe est saisi au clavier et n'apparaît
#  nulle part dans un fichier).
#
#  Le script peut être relancé sans risque (il ne supprime jamais de données).
# ---------------------------------------------------------------------------

set -o errexit -o nounset -o pipefail

SITE_DIR="/var/www/histoiredefamille"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

bleu()  { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }
vert()  { printf '\033[1;32m    %s\033[0m\n' "$1"; }
rouge() { printf '\033[1;31m    %s\033[0m\n' "$1" >&2; }

if [ "$(id -u)" -ne 0 ]; then
    rouge "Ce script doit être lancé avec sudo :"
    rouge "  sudo bash deploy/install.sh"
    exit 1
fi

# --- 1. Paquets ------------------------------------------------------------
bleu "Installation des paquets (Apache, PHP, SQLite…)"
export DEBIAN_FRONTEND=noninteractive
apt-get update
# « libapache2-mod-php » (mod_php) plutôt que PHP-FPM : plus simple à
# administrer pour un site familial à faible trafic.
apt-get install -y \
    apache2 \
    libapache2-mod-php \
    php-sqlite3 \
    php-gd \
    php-mbstring \
    php-curl \
    php-xml \
    sqlite3 \
    rsync \
    curl \
    openssh-server
# Version de PHP réellement installée (sert à placer le fichier .ini).
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
vert "Paquets installés (PHP ${PHP_VERSION})."

# --- 2. Modules Apache -----------------------------------------------------
bleu "Activation des modules Apache"
a2enmod rewrite headers expires deflate >/dev/null
vert "rewrite, headers, expires, deflate activés."

# --- 3. Copie du site ------------------------------------------------------
bleu "Copie du site dans ${SITE_DIR}"
if [ "${SOURCE_DIR}" != "${SITE_DIR}" ]; then
    mkdir -p "${SITE_DIR}"
    # --delete : le dossier cible reflète exactement la source.
    # Les dossiers de données (storage, var) et config.local.php sont exclus.
    rsync -a --delete \
        --exclude 'config.local.php' \
        --exclude 'storage/' \
        --exclude 'var/' \
        --exclude '.git/' \
        "${SOURCE_DIR}/" "${SITE_DIR}/"
    vert "Fichiers copiés."
else
    vert "Le site est déjà à cet emplacement."
fi

chown -R root:root "${SITE_DIR}"
find "${SITE_DIR}" -type d -exec chmod 755 {} \;
find "${SITE_DIR}" -type f -exec chmod 644 {} \;
chmod 750 "${SITE_DIR}/deploy" 2>/dev/null || true
chmod 750 "${SITE_DIR}"/deploy/*.sh 2>/dev/null || true

# --- 4. Configuration locale ----------------------------------------------
bleu "Fichier de configuration"
if [ ! -f "${SITE_DIR}/config.local.php" ]; then
    cp "${SITE_DIR}/config.example.php" "${SITE_DIR}/config.local.php"
    chown root:www-data "${SITE_DIR}/config.local.php"
    chmod 640 "${SITE_DIR}/config.local.php"
    vert "config.local.php créé à partir du modèle."
    rouge "À VÉRIFIER : adaptez les chemins et les réglages dans"
    rouge "  sudo nano ${SITE_DIR}/config.local.php"
else
    vert "config.local.php existe déjà : inchangé."
fi

# --- 5. Dossiers de stockage ----------------------------------------------
# Ils sont sur le SSD (/mnt/ssd), donc HORS de la racine web.
bleu "Dossiers de stockage sur le SSD"
mkdir -p /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous}
chown -R www-data:www-data /mnt/ssd/histoiredefamille
chmod 750 /mnt/ssd/histoiredefamille
chmod 770 /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous}
vert "Créés dans /mnt/ssd/histoiredefamille (propriétaire : www-data)."

mkdir -p /mnt/sauvegarde
chmod 750 /mnt/sauvegarde

# --- 6. Configuration PHP et Apache ---------------------------------------
bleu "Configuration PHP adaptée au Raspberry Pi"
cp "${SITE_DIR}/deploy/php/99-histoiredefamille.ini" "/etc/php/${PHP_VERSION}/apache2/conf.d/"
cp "${SITE_DIR}/deploy/php/99-histoiredefamille.ini" "/etc/php/${PHP_VERSION}/cli/conf.d/"
vert "Limites mémoire, envois et sessions réglées."

bleu "Configuration Apache"
cp "${SITE_DIR}/deploy/apache/histoiredefamille.conf" /etc/apache2/sites-available/
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite histoiredefamille >/dev/null
apache2ctl configtest
systemctl reload apache2
vert "Site activé sur le port 80 (Cloudflare s'occupe du HTTPS)."

# --- 7. Base de données ---------------------------------------------------
bleu "Initialisation de la base SQLite"
if sudo -u www-data php "${SITE_DIR}/app/cli/migrate.php"; then
    vert "Base prête (migrations appliquées)."
else
    rouge "La migration a échoué. Vérifiez les chemins de config.local.php."
    exit 1
fi

# --- 8. Sauvegarde quotidienne --------------------------------------------
bleu "Sauvegarde quotidienne (cron)"
chmod 750 "${SITE_DIR}/deploy/backup.sh"
cat >/etc/cron.d/histoiredefamille <<CRON
# Sauvegarde quotidienne du site à 3 h 15.
# Voir la sortie dans /mnt/sauvegarde/histoiredefamille/journal-sauvegarde.log
15 3 * * * www-data /var/www/histoiredefamille/deploy/backup.sh >/dev/null 2>&1
# Maintenance hebdomadaire (nettoyage des verrous et fichiers temporaires).
45 4 * * 0 www-data /usr/bin/php /var/www/histoiredefamille/app/cli/maintenance.php >/dev/null 2>&1
CRON
chmod 644 /etc/cron.d/histoiredefamille
vert "Tâches planifiées installées dans /etc/cron.d/histoiredefamille."

# --- 9. Fin ---------------------------------------------------------------
bleu "Installation terminée"
cat <<'FIN'

Il reste deux choses à faire à la main :

1) Créer le premier compte administrateur (le mot de passe sera demandé,
   il n'est écrit nulle part) :

       sudo -u www-data php /var/www/histoiredefamille/app/cli/create-admin.php

2) Ouvrir le site depuis le Raspberry Pi pour vérifier :

       curl -I http://localhost/

   Puis mettre en place le tunnel Cloudflare (voir README.md, section
   « Mise en ligne » et le dossier deploy/cloudflared/).

FIN
