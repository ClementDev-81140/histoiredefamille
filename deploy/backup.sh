#!/usr/bin/env bash
# ---------------------------------------------------------------------------
#  Histoire de famille — sauvegarde quotidienne
# ---------------------------------------------------------------------------
#  Ce script est prévu pour tourner la nuit par cron (voir deploy/crontab.example)
#  et peut aussi être lancé à la main :
#
#      sudo /var/www/histoiredefamille/deploy/backup.sh
#      sudo /var/www/histoiredefamille/deploy/backup.sh --verifier
#
#  Ce qu'il fait :
#    1. copie COHÉRENTE de la base SQLite avec « sqlite3 .backup »
#       (jamais « cp » d'une base en cours d'utilisation : on obtiendrait un
#       fichier corrompu) ;
#    2. conserve les 14 dernières copies datées de la base ;
#    3. synchronisation INCRÉMENTALE des fichiers (photos, vidéos, PDF) vers
#       la clé USB avec rsync (seuls les nouveaux fichiers sont copiés) ;
#    4. écrit un journal et un petit fichier d'état JSON que la page
#       « État du site » du site sait lire ;
#    5. prévient (journal + fichier + éventuellement webhook) en cas d'échec.
#
#  Prérequis : sqlite3, rsync, gzip, flock, php  (installés par deploy/install.sh)
#
#  IMPORTANT : la clé USB doit avoir une capacité supérieure au volume des
#  données à sauvegarder (base + fichiers), avec de la marge. Si les vidéos
#  représentent 50 Go, prenez une clé de 64 Go minimum.
# ---------------------------------------------------------------------------

set -o errexit -o nounset -o pipefail

# --- Réglages (adaptables par variables d'environnement) -------------------
APP_DIR="${APP_DIR:-/var/www/histoiredefamille}"           # code du site
CONFIG_FILE="${CONFIG_FILE:-${APP_DIR}/config.local.php}"  # configuration du site
BACKUP_DIR="${BACKUP_DIR:-/mnt/sauvegarde/histoiredefamille}"
KEEP_DATABASE="${KEEP_DATABASE:-14}"                       # copies de base conservées
BACKUP_USER="${BACKUP_USER:-www-data}"                     # propriétaire des fichiers copiés
WEBHOOK_URL="${WEBHOOK_URL:-}"                             # facultatif : alerte HTTP (voir README)
MIN_FREE_MB="${MIN_FREE_MB:-2048}"                         # marge libre exigée sur la clé (Mo)

LOG_FILE="${BACKUP_DIR}/journal-sauvegarde.log"
STATE_FILE="${BACKUP_DIR}/dernier-etat.json"
LOCK_FILE="${LOCK_FILE:-/run/histoiredefamille-sauvegarde.lock}"
DATABASE_DIR="${BACKUP_DIR}/base"
FILES_DIR="${BACKUP_DIR}/fichiers"

mkdir -p "${BACKUP_DIR}" 2>/dev/null || true

# --- Petites fonctions d'affichage ----------------------------------------
log() {
    local ligne
    ligne="$(date '+%Y-%m-%d %H:%M:%S')  $1"
    printf '%s\n' "${ligne}"
    printf '%s\n' "${ligne}" >>"${LOG_FILE}" 2>/dev/null || true
}

# Écrit le fichier d'état JSON lu par la page « État du site ».
# On passe par PHP pour garantir un JSON valide (accents, guillemets, etc.).
write_state() {
    local statut="$1" message="$2" base="$3" fichiers="$4"
    php -r '
        $etat = [
            "date"        => (int) $argv[6],
            "date_humain" => $argv[7],
            "status"      => $argv[2],
            "database"    => $argv[3] === "true",
            "files"       => $argv[4] === "true",
            "message"     => $argv[5],
        ];
        file_put_contents($argv[1], json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    ' "${STATE_FILE}" "${statut}" "${base}" "${fichiers}" "${message}" \
      "$(date '+%s')" "$(date '+%d/%m/%Y à %H:%M')" 2>/dev/null \
      || printf '{"status":"%s","message":"%s","database":%s,"files":%s}' \
            "${statut}" "${message}" "${base}" "${fichiers}" >"${STATE_FILE}"
    chown "${BACKUP_USER}:${BACKUP_USER}" "${STATE_FILE}" 2>/dev/null || true
    chmod 640 "${STATE_FILE}" 2>/dev/null || true
}

# Alerte : journal + (facultatif) appel HTTP vers un service de notification.
alert() {
    log "ERREUR : $1"
    if [ -n "${WEBHOOK_URL}" ] && command -v curl >/dev/null 2>&1; then
        curl -fsS --max-time 10 -X POST "${WEBHOOK_URL}" \
            -H 'Content-Type: text/plain; charset=utf-8' \
            --data-binary "Sauvegarde histoiredefamille : $1" >/dev/null 2>&1 || true
    fi
}

echouer() {
    alert "$1"
    write_state "erreur" "$1" false false
    log "=== Sauvegarde en échec ==="
    exit 1
}

# --- Lecture de la configuration du site ----------------------------------
# On demande les chemins à PHP lui-même plutôt que de les recopier ici :
# une seule source de vérité (config.local.php).
read_config() {
    command -v php >/dev/null 2>&1 \
        || echouer "php est introuvable : impossible de lire la configuration."

    HDF_CONFIG="${CONFIG_FILE}" HDF_FORCE_CLI=1 \
    php -r 'require $argv[1] . "/app/config.php";
            echo config("database"), "\n", config("storage.root"), "\n";' \
        "${APP_DIR}" 2>/dev/null \
        || echouer "Lecture de la configuration impossible (${CONFIG_FILE})."
}

# --- Mode « --verifier » : teste la dernière sauvegarde sans rien écrire ---
verifier() {
    local derniere
    derniere="$(find "${DATABASE_DIR}" -maxdepth 1 -name 'histoire-*.sqlite.gz' -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn | head -n 1 | cut -d' ' -f2-)"
    if [ -z "${derniere}" ]; then
        echo "Aucune sauvegarde de base trouvée dans ${DATABASE_DIR}."
        exit 1
    fi

    local travail
    travail="$(mktemp -d)"
    trap "rm -rf '${travail}'" EXIT

    echo "Test de restauration de : ${derniere}"
    gzip -dc "${derniere}" >"${travail}/test.sqlite"

    local integrite
    integrite="$(sqlite3 "${travail}/test.sqlite" 'PRAGMA integrity_check;')"
    if [ "${integrite}" != "ok" ]; then
        echo "ÉCHEC : la base restaurée est corrompue (${integrite})."
        exit 1
    fi
    echo "  · intégrité SQLite : ok"

    echo "  · comptes : $(sqlite3 "${travail}/test.sqlite" 'SELECT COUNT(*) FROM accounts;')"
    echo "  · documents : $(sqlite3 "${travail}/test.sqlite" 'SELECT COUNT(*) FROM documents;')"
    echo "  · personnes : $(sqlite3 "${travail}/test.sqlite" 'SELECT COUNT(*) FROM persons;')"
    echo "Restauration vérifiée avec succès."
}

if [ "${1:-}" = "--verifier" ] || [ "${1:-}" = "--test-restauration" ]; then
    verifier
    exit 0
fi

# Un seul exemplaire à la fois. Si /run n'est pas accessible en écriture
# (script lancé sans root), on se rabat sur /tmp.
if ! ( : >>"${LOCK_FILE}" ) 2>/dev/null; then
    LOCK_FILE="/tmp/histoiredefamille-sauvegarde.lock"
fi
exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
    log "Sauvegarde déjà en cours, abandon."
    exit 0
fi

log "=== Début de la sauvegarde ==="

# --- 1. Vérifications préalables ------------------------------------------
[ -f "${CONFIG_FILE}" ] || echouer "Configuration absente (${CONFIG_FILE})."

# La clé USB doit être réellement montée : sinon les sauvegardes
# atterriraient sur la carte SD et la rempliraient.
# (HDF_SKIP_MOUNT_CHECK=1 sert aux essais sur un poste de travail.)
if [ "${HDF_SKIP_MOUNT_CHECK:-0}" != "1" ]; then
    if ! mountpoint -q "${BACKUP_DIR}" && ! mountpoint -q "$(dirname "${BACKUP_DIR}")"; then
        echouer "La clé USB n'est pas montée (${BACKUP_DIR}) : sauvegarde annulée."
    fi
fi

CONF_DB="$(read_config | sed -n 1p)"
CONF_STORAGE="$(read_config | sed -n 2p)"

[ -n "${CONF_DB}" ] && [ -f "${CONF_DB}" ] || echouer "Base de données introuvable."
[ -n "${CONF_STORAGE}" ] && [ -d "${CONF_STORAGE}" ] || echouer "Dossier de stockage introuvable."

command -v rsync >/dev/null 2>&1 \
    || echouer "rsync est introuvable (installez-le avec deploy/install.sh)."
command -v sqlite3 >/dev/null 2>&1 \
    || echouer "sqlite3 est introuvable (installez-le avec deploy/install.sh)."

libre_mb="$(df -Pm "${BACKUP_DIR}" | awk 'NR==2 {print $4}')"
if [ "${libre_mb:-0}" -lt "${MIN_FREE_MB}" ]; then
    echouer "Espace insuffisant sur la clé USB (${libre_mb} Mo libres, ${MIN_FREE_MB} Mo exigés)."
fi
log "Espace libre sur la clé : ${libre_mb} Mo"

mkdir -p "${DATABASE_DIR}" "${FILES_DIR}"

# --- 2. Copie cohérente de la base ----------------------------------------
horodatage="$(date '+%Y-%m-%d_%H%M%S')"
base_tmp="${DATABASE_DIR}/histoire-${horodatage}.sqlite"
base_ok=false

if sqlite3 "${CONF_DB}" ".timeout 5000" ".backup '${base_tmp}'" \
   && [ "$(sqlite3 "${base_tmp}" 'PRAGMA integrity_check;')" = "ok" ]; then
    gzip -f "${base_tmp}"
    chown "${BACKUP_USER}:${BACKUP_USER}" "${base_tmp}.gz" 2>/dev/null || true
    chmod 640 "${base_tmp}.gz" 2>/dev/null || true
    base_ok=true
    log "Base sauvegardée : $(basename "${base_tmp}.gz") ($(du -h "${base_tmp}.gz" | cut -f1))"
else
    rm -f "${base_tmp}"
    alert "La copie de la base a échoué."
fi

# Rotation : on ne garde que les N copies les plus récentes.
if [ "${base_ok}" = true ]; then
    anciennes="$(find "${DATABASE_DIR}" -maxdepth 1 -name 'histoire-*.sqlite.gz' -printf '%T@ %p\n' \
        | sort -rn | tail -n "+$((KEEP_DATABASE + 1))" | cut -d' ' -f2-)"
    if [ -n "${anciennes}" ]; then
        while IFS= read -r vieille; do
            [ -n "${vieille}" ] || continue
            rm -f -- "${vieille}"
            log "Ancienne copie supprimée : $(basename "${vieille}")"
        done <<<"${anciennes}"
    fi
fi

# --- 3. Synchronisation incrémentale des fichiers -------------------------
# --delete : un fichier supprimé du site disparaît aussi de la clé.
# tmp/ (fichiers jetables) et verrous/ (lectures vidéo en cours) sont exclus.
fichiers_ok=false
journal_rsync="$(mktemp)"
if rsync -a --delete --stats \
        --exclude 'tmp/' --exclude 'verrous/' \
        "${CONF_STORAGE}/" "${FILES_DIR}/" >"${journal_rsync}" 2>&1; then
    fichiers_ok=true
    log "Fichiers synchronisés : $(grep -E 'Number of regular files transferred' "${journal_rsync}" \
        | awk -F': ' '{print $2}') nouveau(x) fichier(s)."
else
    alert "La synchronisation rsync a échoué (voir ${journal_rsync})."
fi
rm -f "${journal_rsync}"

# --- 4. Bilan -------------------------------------------------------------
if [ "${base_ok}" = true ] && [ "${fichiers_ok}" = true ]; then
    write_state "ok" "Sauvegarde terminée." true true
    log "=== Sauvegarde réussie ==="
    exit 0
fi

details=""
[ "${base_ok}" = false ] && details="base non copiée"
[ "${fichiers_ok}" = false ] && details="${details:+${details} ; }fichiers non synchronisés"
write_state "erreur" "Sauvegarde incomplète : ${details}." "${base_ok}" "${fichiers_ok}"
log "=== Sauvegarde en échec (${details}) ==="
exit 1
