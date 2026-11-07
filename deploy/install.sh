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
    ffmpeg \
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
# config.local.php est traité à part (étape 4) : il ne doit jamais être élargi
# à 644, même une seconde, car il contient la configuration du site.
find "${SITE_DIR}" -type f ! -name 'config.local.php' -exec chmod 644 {} \;
chmod 750 "${SITE_DIR}/deploy" 2>/dev/null || true
chmod 750 "${SITE_DIR}"/deploy/*.sh 2>/dev/null || true

# --- 4. Configuration locale ----------------------------------------------
bleu "Fichier de configuration"
CONFIG_LOCALE="${SITE_DIR}/config.local.php"

# Droits de config.local.php : 640 root:www-data.
#
# Le fichier peut contenir l'identifiant et le mot de passe du serveur
# d'envoi : il ne doit être lisible que par root et par le site (www-data).
# Relancer l'installation ne doit donc PAS l'élargir. On CONSERVE les droits
# trouvés s'ils sont au moins aussi stricts que 640 ; on les remet à
# 640 root:www-data s'ils sont plus larges (644, 664, 777…).
#
# « Plus large » = bits d'écriture ou d'exécution pour le groupe (0030) et
# tout ce qui concerne les autres (0007), soit le masque 0037.
droits_config_local() {
    local fichier="$1"
    local mode=""
    mode="$(stat -c '%a' "${fichier}" 2>/dev/null || true)"
    if [ -z "${mode}" ]; then
        return 0
    fi
    # stat donne p. ex. « 640 » ou « 2755 » : on ne garde que les droits.
    mode="${mode: -3}"
    if [ $(( 8#${mode} & 8#037 )) -ne 0 ]; then
        chown root:www-data "${fichier}"
        chmod 640 "${fichier}"
        vert "config.local.php était lisible trop largement (${mode}) : droits remis à 640 root:www-data."
    else
        # Droits conservés ; seuls le propriétaire et le groupe changent, pour
        # que root l'administre et que le site puisse le lire.
        chown root:www-data "${fichier}"
        vert "config.local.php : droits conservés (${mode})."
        if [ $(( 8#${mode} & 8#040 )) -eq 0 ]; then
            rouge "  Attention : avec ces droits, www-data ne peut pas lire le fichier."
            rouge "  Le site ne démarrerait pas. Corrigez avec : sudo chmod 640 ${fichier}"
        fi
    fi
}

if [ ! -f "${CONFIG_LOCALE}" ]; then
    cp "${SITE_DIR}/config.example.php" "${CONFIG_LOCALE}"
    chown root:www-data "${CONFIG_LOCALE}"
    chmod 640 "${CONFIG_LOCALE}"
    vert "config.local.php créé à partir du modèle (640 root:www-data)."
    rouge "À VÉRIFIER : adaptez les chemins et les réglages dans"
    rouge "  sudo nano ${SITE_DIR}/config.local.php"
else
    vert "config.local.php existe déjà : contenu inchangé."
    droits_config_local "${CONFIG_LOCALE}"
fi

# --- 5. Dossiers de stockage ----------------------------------------------
# Ils sont sur le SSD (/mnt/ssd), donc HORS de la racine web.
bleu "Dossiers de stockage sur le SSD"
mkdir -p /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous,envois}
chown -R www-data:www-data /mnt/ssd/histoiredefamille
chmod 750 /mnt/ssd/histoiredefamille
chmod 770 /mnt/ssd/histoiredefamille/{medias,miniatures,arrivee,tmp,verrous,envois}
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

# --- 7 bis. État de l'installation ----------------------------------------
# Deux réponses viennent du SITE, pas du shell : « les courriels sont-ils
# activés ? » (un réglage en base) et « le mode sans disque externe est-il
# assumé ? » (config.local.php). Seul PHP sait les lire.
ETAT_INSTALLATION="$(sudo -u www-data php "${SITE_DIR}/app/cli/etat-installation.php" 2>/dev/null || true)"
COURRIELS_ACTIFS="$(printf '%s\n' "${ETAT_INSTALLATION}" | sed -n 's/^courriels_actifs=//p' | head -n 1)"
SAUVEGARDE_SANS_DISQUE="$(printf '%s\n' "${ETAT_INSTALLATION}" | sed -n 's/^sauvegarde_sans_disque=//p' | head -n 1)"

# --- 8. Sauvegarde quotidienne (minuteur systemd) --------------------------
bleu "Sauvegarde quotidienne à 3 h 30 (minuteur systemd)"

# La clé USB doit déjà être montée (voir deploy/montage-disques.md). Sans
# elle, la sauvegarde refusera de s'exécuter pour ne pas remplir la carte SD —
# SAUF si le mode « sans disque externe » a été assumé dans config.local.php
# (backup_allow_same_disk) : dans ce cas, ce n'est pas une erreur à signaler.
if mountpoint -q /mnt/sauvegarde; then
    vert "La clé USB est montée sur /mnt/sauvegarde."
elif [ "${SAUVEGARDE_SANS_DISQUE}" = "1" ]; then
    vert "Mode sans disque externe actif (backup_allow_same_disk) : la sauvegarde écrit sur la carte SD,"
    vert "  dans /mnt/sauvegarde/histoiredefamille. C'est un choix assumé pour les premiers jours."
    vert "  Branchez la clé (deploy/montage-disques.md) et retirez ce réglage dès que possible."
else
    rouge "ATTENTION : /mnt/sauvegarde n'est pas un point de montage."
    rouge "  La sauvegarde refusera de s'exécuter tant que la clé ne sera pas montée."
    rouge "  Suivez deploy/montage-disques.md, puis relancez ce script."
fi

mkdir -p /mnt/sauvegarde/histoiredefamille
chown -R www-data:www-data /mnt/sauvegarde/histoiredefamille 2>/dev/null || true
chmod 750 /mnt/sauvegarde/histoiredefamille 2>/dev/null || true

chmod 750 "${SITE_DIR}/deploy/backup.sh" 2>/dev/null || true

install -m 644 "${SITE_DIR}/deploy/systemd/histoiredefamille-sauvegarde.service" /etc/systemd/system/
install -m 644 "${SITE_DIR}/deploy/systemd/histoiredefamille-sauvegarde.timer" /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now histoiredefamille-sauvegarde.timer >/dev/null 2>&1
vert "Minuteur installé et activé : tous les jours à 3 h 30."
vert "Vérification : systemctl list-timers histoiredefamille-sauvegarde.timer"

# --- 8 bis. Envoi des courriels (minuteur systemd, toutes les 5 minutes) ---
bleu "Envoi des courriels en attente (minuteur systemd, toutes les 5 minutes)"

install -m 644 "${SITE_DIR}/deploy/systemd/histoiredefamille-courriels.service" /etc/systemd/system/
install -m 644 "${SITE_DIR}/deploy/systemd/histoiredefamille-courriels.timer" /etc/systemd/system/
systemctl daemon-reload

# Le site n'envoie aucun courriel par défaut (réglage « courriels_actifs »,
# faux depuis la 1.28.0). Dans ce cas, l'unité est posée mais le minuteur reste
# ARRÊTÉ : le réactiver ferait tourner une tâche toutes les cinq minutes pour
# ne rien faire, et donnerait l'impression que le site envoie des messages.
# Relancer ce script ne doit pas non plus rallumer un minuteur éteint exprès :
# il est donc explicitement arrêté.
if [ "${COURRIELS_ACTIFS}" = "1" ]; then
    systemctl enable --now histoiredefamille-courriels.timer >/dev/null 2>&1
    vert "Minuteur installé et activé : toutes les 5 minutes."
    vert "Vérification : systemctl list-timers histoiredefamille-courriels.timer"
    vert "Sans configuration d'envoi (config.local.php), la commande ne fait rien : les messages attendent."
else
    systemctl disable --now histoiredefamille-courriels.timer >/dev/null 2>&1 || true
    vert "Courriels désactivés : minuteur laissé arrêté."
    vert "  Les unités sont installées, prêtes à servir. Pour les courriels : cochez"
    vert "  « Le site envoie des courriels » dans Administration → Réglages, remplissez"
    vert "  le bloc « mail » de config.local.php, puis relancez ce script."
fi

# --- 8 ter. Conversion des vidéos (minuteur systemd, toutes les 10 minutes) --
bleu "Conversion des vidéos en MP4 (minuteur systemd, toutes les 10 minutes)"

install -m 644 "${SITE_DIR}/deploy/systemd/histoiredefamille-conversion.service" /etc/systemd/system/
install -m 644 "${SITE_DIR}/deploy/systemd/histoiredefamille-conversion.timer" /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now histoiredefamille-conversion.timer >/dev/null 2>&1

if command -v ffmpeg >/dev/null 2>&1; then
    vert "Minuteur installé et activé : un fichier à la fois, en priorité basse."
    vert "ffmpeg est présent : les vidéos envoyées seront converties en arrière-plan."
else
    rouge "ffmpeg est absent : les vidéos envoyées resteront dans leur format d’origine."
    rouge "Pour l'installer : sudo apt-get install -y ffmpeg"
fi
vert "Vérification : systemctl list-timers histoiredefamille-conversion.timer"

# Ancien cron : la ligne de sauvegarde est retirée (le minuteur la remplace).
# La maintenance hebdomadaire reste dans /etc/cron.d.
cat >/etc/cron.d/histoiredefamille <<CRON
# Maintenance hebdomadaire (nettoyage des verrous et fichiers temporaires).
45 4 * * 0 www-data /usr/bin/php /var/www/histoiredefamille/app/cli/maintenance.php >/dev/null 2>&1
CRON
chmod 644 /etc/cron.d/histoiredefamille
vert "Maintenance hebdomadaire conservée (dimanche à 4 h 45)."

# --- 9. Fin ---------------------------------------------------------------
bleu "Installation terminée"
cat <<'FIN'

Il reste deux choses à faire à la main :

1) Créer le premier compte administrateur (le mot de passe sera demandé,
   il n'est écrit nulle part) :

       sudo -u www-data php /var/www/histoiredefamille/app/cli/create-admin.php

2) Ouvrir le site depuis le Raspberry Pi pour vérifier :

       curl -I http://localhost/

3) Faire une première sauvegarde et vérifier qu'elle fonctionne :

       sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php
       systemctl list-timers histoiredefamille-sauvegarde.timer

4) Mettre en place le tunnel Cloudflare (voir README.md, section
   « Mise en ligne » et le dossier deploy/cloudflared/), puis lancer la
   vérification avant ouverture :

       sudo -u www-data php /var/www/histoiredefamille/app/cli/verifier-mise-en-ligne.php

FIN
