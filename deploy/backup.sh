#!/usr/bin/env bash
# ---------------------------------------------------------------------------
#  Histoire de famille — sauvegarde (ancien point d'entrée, conservé)
# ---------------------------------------------------------------------------
#  La sauvegarde est désormais une commande PHP, plus simple à maintenir et
#  capable de vérifier l'intégrité de la copie de la base :
#
#      php /var/www/histoiredefamille/app/cli/sauvegarde.php
#
#  Elle est lancée automatiquement tous les jours à 3 h 30 par le minuteur
#  systemd « histoiredefamille-sauvegarde.timer » (installé par
#  deploy/install.sh).
#
#  Ce fichier reste présent pour les installations anciennes : il se contente
#  d'appeler la nouvelle commande. Vous pouvez aussi l'utiliser à la main :
#
#      sudo -u www-data bash /var/www/histoiredefamille/deploy/backup.sh
#
#  Ce qu'elle fait : copie cohérente de la base (VACUUM INTO + integrity_check),
#  copie incrémentale des fichiers, rotation 7 quotidiennes / 4 hebdomadaires /
#  6 mensuelles, état lisible dans Administration → État du site.
#  Rien n'est écrit si la clé USB n'est pas montée.
# ---------------------------------------------------------------------------

set -o errexit -o nounset -o pipefail

APP_DIR="${APP_DIR:-/var/www/histoiredefamille}"

if [ ! -f "${APP_DIR}/app/cli/sauvegarde.php" ]; then
    echo "Sauvegarde : ${APP_DIR}/app/cli/sauvegarde.php est introuvable." >&2
    exit 1
fi

exec /usr/bin/php "${APP_DIR}/app/cli/sauvegarde.php"
