#!/usr/bin/env bash
#
# HydroLink - installation / update / uninstall on an existing FreeScout (Linux).
#
#   sudo bash install.sh                         install, or update when already installed (latest release)
#   sudo bash install.sh --update                update only (fails when HydroLink is not installed)
#   sudo bash install.sh --path /var/www/html    FreeScout folder (otherwise detected, or asked)
#   sudo bash install.sh --source ./HydroLink.zip  local archive or folder instead of GitHub
#   sudo bash install.sh --dry-run               show what would be done, change nothing
#   sudo bash install.sh --uninstall             stop hydroxide, remove the module (FreeScout mailboxes are kept)
#   sudo bash install.sh --uninstall --keep-accounts   same, but keep the hydroxide sessions and the accounts
#   sudo bash install.sh --yes                   no question
#   sudo bash install.sh --lang fr|en            language of the messages (default: from $LANG)
#
# sudo is only used by this script to act as the web server user; HydroLink itself never needs root.
# Steps: checks -> backup -> copy -> activation -> migrations -> health -> hydroxide binary. On error, the previous
# version of the module is put back. No password is shown or logged.
#
set -Eeuo pipefail

SCRIPT_VERSION="1.0.0"   # same as HydroLink/module.json (checked by tools/build-release.sh)
REPO_URL="${HL_REPO_URL:-https://github.com/00MY00/HydroLink}"
MODULE="HydroLink"
ALIAS="hydrolink"
MIN_PHP="7.1.0"
DEFAULT_PATHS="/var/www/html /var/www/freescout /var/www/html/freescout /srv/freescout /var/www/helpdesk"
PORTS="1143 1025"

FS_PATH=""
SOURCE=""
DRY_RUN=0
YES=0
UNINSTALL=0
UPDATE_ONLY=0
KEEP_ACCOUNTS=0
WEB_USER=""
WORK=""
BACKUP=""
STEP=0
STEPS=7
LOG="/tmp/hydrolink-install-$(date +%Y%m%d-%H%M%S).log"
case "${LANG:-}${LC_ALL:-}" in fr*|*fr_*) L="fr" ;; *) L="en" ;; esac

if [ -t 1 ]; then C_RED=$'\e[31m'; C_GREEN=$'\e[32m'; C_YELLOW=$'\e[33m'; C_BLUE=$'\e[34m'; C_RESET=$'\e[0m'; else C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_RESET=""; fi

# t "texte français" "English text": message in the chosen language
t() { if [ "$L" = "fr" ]; then printf '%s' "$1"; else printf '%s' "$2"; fi; }

log() { printf '%s %s\n' "$(date +%H:%M:%S)" "$*" >>"$LOG"; }
info() { printf '%s\n' "${C_BLUE}i${C_RESET} $*"; log "INFO $*"; }
warn() { printf '%s\n' "${C_YELLOW}! $*${C_RESET}" >&2; log "WARN $*"; }
ok() { printf '%s\n' "${C_GREEN}OK $*${C_RESET}"; log "OK $*"; }
step() { STEP=$((STEP + 1)); printf '\n%s\n' "${C_BLUE}[${STEP}/${STEPS}]${C_RESET} $*"; log "STEP ${STEP} $*"; }
die() { printf '%s\n' "${C_RED}X $*${C_RESET}" >&2; log "ERROR $*"; exit 1; }

run() {
    if [ "$DRY_RUN" -eq 1 ]; then printf '  (dry-run) %s\n' "$*"; return 0; fi
    log "RUN $*"
    "$@" >>"$LOG" 2>&1
}

artisan() {
    if [ "$DRY_RUN" -eq 1 ]; then printf '  (dry-run) php artisan %s\n' "$*"; return 0; fi
    log "ARTISAN $*"
    (cd "$FS_PATH" && sudo -u "$WEB_USER" php artisan "$@") >>"$LOG" 2>&1
}

as_web() { (cd "$FS_PATH" && sudo -u "$WEB_USER" "$@"); }

ask() {
    [ "$YES" -eq 1 ] && return 0
    local answer
    read -r -p "$1 $(t '[o/N]' '[y/N]') " answer || true
    [[ "$answer" =~ ^([oOyY]|oui|yes)$ ]]
}

version_ge() { [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n 1)" = "$2" ]; }

json_version() { { sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$1" 2>/dev/null | head -n 1; } || true; }

usage() { sed -n '3,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0; }

parse_args() {
    while [ $# -gt 0 ]; do
        case "$1" in
            --path) FS_PATH="${2:-}"; shift 2 ;;
            --source) SOURCE="${2:-}"; shift 2 ;;
            --lang) L="${2:-en}"; [ "$L" = "fr" ] || L="en"; shift 2 ;;
            --dry-run) DRY_RUN=1; shift ;;
            --update) UPDATE_ONLY=1; shift ;;
            --uninstall) UNINSTALL=1; shift ;;
            --keep-accounts) KEEP_ACCOUNTS=1; shift ;;
            --yes|-y) YES=1; shift ;;
            -h|--help) usage ;;
            *) die "$(t "Option inconnue : $1 (voir --help)" "Unknown option: $1 (see --help)")" ;;
        esac
    done
}

on_error() {
    local code=$?
    printf '%s\n' "${C_RED}X $(t "Erreur à l'étape ${STEP} (code ${code}). Journal : ${LOG}" "Error at step ${STEP} (code ${code}). Log: ${LOG}")${C_RESET}" >&2
    if [ -n "$BACKUP" ] && [ -d "$BACKUP/module" ] && [ "$DRY_RUN" -eq 0 ]; then
        warn "$(t "Remise en place de la version précédente du module..." "Putting the previous version of the module back...")"
        rm -rf "${FS_PATH}/Modules/${MODULE}"
        cp -a "$BACKUP/module" "${FS_PATH}/Modules/${MODULE}"
        (cd "$FS_PATH" && sudo -u "$WEB_USER" php artisan freescout:clear-cache) >>"$LOG" 2>&1 || true
        warn "$(t "Version précédente restaurée (sauvegarde : $BACKUP)." "Previous version restored (backup: $BACKUP).")"
    fi
    exit "$code"
}
trap on_error ERR
trap '[ -n "$WORK" ] && rm -rf "$WORK"' EXIT

is_freescout() { [ -f "$1/artisan" ] && grep -q "freescout" "$1/config/app.php" 2>/dev/null; }

detect_freescout() {
    if [ -z "$FS_PATH" ]; then
        for candidate in $DEFAULT_PATHS; do
            if is_freescout "$candidate"; then FS_PATH="$candidate"; break; fi
        done
    fi
    local tries=0
    while [ -z "$FS_PATH" ] || ! is_freescout "$FS_PATH"; do
        [ -n "$FS_PATH" ] && warn "$(t "\"$FS_PATH\" n'est pas un dossier FreeScout." "\"$FS_PATH\" is not a FreeScout folder.")"
        if [ "$YES" -eq 1 ] || [ ! -t 0 ] || [ "$tries" -ge 3 ]; then
            die "$(t "FreeScout introuvable : indiquez son dossier avec --path /chemin/vers/freescout" "FreeScout not found: give its folder with --path /path/to/freescout")"
        fi
        tries=$((tries + 1))
        read -r -p "$(t "Dossier de FreeScout : " "FreeScout folder: ")" FS_PATH || true
        FS_PATH="${FS_PATH%/}"
    done
    FS_PATH="$(cd "$FS_PATH" && pwd)"
    WEB_USER="$(stat -c '%U' "$FS_PATH/storage" 2>/dev/null || echo www-data)"
    [ "$WEB_USER" = "root" ] && WEB_USER="www-data"
    id "$WEB_USER" >/dev/null 2>&1 || die "$(t "Utilisateur web \"$WEB_USER\" introuvable." "Web server user \"$WEB_USER\" not found.")"
}

# environment checks of HydroLink (same as the panel): errors stop, warnings are shown
env_checks() {
    local arch
    arch="$(uname -m)"
    case "$arch" in
        x86_64|amd64|aarch64|arm64) ok "$(t "Architecture $arch prise en charge." "Architecture $arch supported.")" ;;
        *) die "HL-ENV-04 $(t "Architecture $arch non prise en charge (amd64 ou arm64)." "Architecture $arch not supported (amd64 or arm64).")" ;;
    esac
    if as_web php -r 'exit(function_exists("proc_open") && !in_array("proc_open", array_map("trim", explode(",", (string) ini_get("disable_functions"))), true) ? 0 : 1);'; then
        ok "$(t "proc_open disponible en CLI." "proc_open available in the CLI.")"
    else
        die "HL-ENV-01 $(t "proc_open est désactivé pour PHP en ligne de commande (disable_functions)." "proc_open is disabled for command-line PHP (disable_functions).")"
    fi
    local probe="$FS_PATH/storage/app/.hydrolink-exec-test"
    if [ "$DRY_RUN" -eq 0 ]; then
        printf '#!/bin/sh\necho ok\n' >"$probe" && chmod 700 "$probe" && chown "$WEB_USER" "$probe"
        if [ "$(sudo -u "$WEB_USER" "$probe" 2>/dev/null)" = "ok" ]; then
            ok "$(t "storage/ autorise l'exécution." "storage/ allows execution.")"
        else
            rm -f "$probe"
            die "HL-ENV-02 $(t "storage/ est monté en noexec : hydroxide ne pourrait pas démarrer." "storage/ is mounted noexec: hydroxide could not start.")"
        fi
        rm -f "$probe"
    fi
    local port
    for port in $PORTS; do
        if (exec 3<>"/dev/tcp/127.0.0.1/$port") 2>/dev/null; then
            if pgrep -u "$WEB_USER" -f 'hydroxide.* serve' >/dev/null 2>&1; then
                info "$(t "Port $port utilisé par hydroxide (HydroLink déjà actif)." "Port $port used by hydroxide (HydroLink already running).")"
            else
                warn "HL-ENV-03 $(t "Port $port déjà utilisé sur 127.0.0.1 : hydroxide ne pourra pas démarrer tant qu'il est occupé." "Port $port already used on 127.0.0.1: hydroxide cannot start while it is taken.")"
            fi
        fi
    done
    if crontab -u "$WEB_USER" -l 2>/dev/null | grep -q 'schedule:run' || grep -rqs 'schedule:run' /etc/crontab /etc/cron.d; then
        ok "$(t "Cron FreeScout trouvé." "FreeScout cron found.")"
    else
        warn "HL-ENV-05 $(t "Cron FreeScout non trouvé. Ajoutez pour $WEB_USER : * * * * * php $FS_PATH/artisan schedule:run >> /dev/null 2>&1" "FreeScout cron not found. Add for $WEB_USER: * * * * * php $FS_PATH/artisan schedule:run >> /dev/null 2>&1")"
    fi
}

checks() {
    step "$(t "Vérifications" "Checks")"
    [ "$(id -u)" -eq 0 ] || die "$(t "Lancez le script avec sudo (il agit au nom de l'utilisateur web)." "Run the script with sudo (it acts as the web server user).")"
    command -v php >/dev/null || die "$(t "php introuvable." "php not found.")"
    local php_version fs_version
    php_version="$(php -r 'echo PHP_VERSION;')"
    version_ge "$php_version" "$MIN_PHP" || die "PHP $php_version < $MIN_PHP"
    detect_freescout
    fs_version="$(sed -n "s/.*'version' => '\([^']*\)'.*/\1/p" "$FS_PATH/config/app.php" | head -n 1)"
    info "FreeScout ${fs_version:-?} ($FS_PATH, $(t "utilisateur web" "web user"): $WEB_USER), PHP $php_version"
    if [ "$UPDATE_ONLY" -eq 1 ] && [ ! -d "${FS_PATH}/Modules/${MODULE}" ]; then
        die "$(t "--update : HydroLink n'est pas installé." "--update: HydroLink is not installed.")"
    fi
    if [ -z "$SOURCE" ] || [ ! -d "$SOURCE" ]; then
        command -v unzip >/dev/null || die "$(t "unzip introuvable (apt install unzip)." "unzip not found (apt install unzip).")"
        [ -n "$SOURCE" ] || command -v curl >/dev/null || die "$(t "curl introuvable (apt install curl)." "curl not found (apt install curl).")"
    fi
    env_checks
}

backup() {
    step "$(t "Sauvegarde" "Backup")"
    BACKUP="${FS_PATH}/storage/app/hydrolink-backups/install-$(date +%Y%m%d-%H%M%S)"
    if [ "$DRY_RUN" -eq 1 ]; then printf '  (dry-run) %s\n' "$BACKUP"; return 0; fi
    mkdir -p "$BACKUP"
    chmod 700 "${FS_PATH}/storage/app/hydrolink-backups"
    if [ -d "${FS_PATH}/Modules/${MODULE}" ]; then
        cp -a "${FS_PATH}/Modules/${MODULE}" "$BACKUP/module"
        ok "$(t "Module sauvegardé (version $(json_version "$BACKUP/module/module.json"))." "Module saved (version $(json_version "$BACKUP/module/module.json")).")"
    else
        info "$(t "Première installation : rien à sauvegarder." "First installation: nothing to save.")"
    fi
    chown -R "$WEB_USER":"$WEB_USER" "${FS_PATH}/storage/app/hydrolink-backups"
}

fetch_module() {
    step "$(t "Récupération du module" "Getting the module")"
    WORK="$(mktemp -d)"
    local src="$SOURCE"
    if [ -z "$src" ]; then
        if [ "$DRY_RUN" -eq 1 ]; then printf '  (dry-run) download %s\n' "${REPO_URL}/releases/latest"; return 0; fi
        src="$WORK/HydroLink.zip"
        curl -fsSL "${REPO_URL}/releases/latest/download/HydroLink.zip" -o "$src" 2>>"$LOG" \
            && curl -fsSL "${REPO_URL}/releases/latest/download/SHA256SUMS" -o "$WORK/SHA256SUMS" 2>>"$LOG" \
            || die "$(t "Téléchargement de la dernière release impossible." "Cannot download the latest release.")"
        local expected actual
        expected="$(sed -n 's/^\([a-f0-9]\{64\}\)[[:space:]]\{1,\}\*\{0,1\}HydroLink\.zip[[:space:]]*$/\1/p' "$WORK/SHA256SUMS" | head -n 1)"
        actual="$(sha256sum "$src" | cut -d' ' -f1)"
        if [ -z "$expected" ] || [ "$expected" != "$actual" ]; then
            die "$(t "SHA-256 de l'archive incorrect : rien n'est installé." "Wrong SHA-256 of the archive: nothing is installed.")"
        fi
        ok "$(t "Dernière release téléchargée, SHA-256 vérifié." "Latest release downloaded, SHA-256 checked.")"
    fi
    if [ -d "$src" ]; then
        if [ -f "$src/module.json" ]; then cp -a "$src" "$WORK/$MODULE"; else cp -a "$src/$MODULE" "$WORK/$MODULE"; fi
    else
        [ -f "$src" ] || die "$(t "Source introuvable : $src" "Source not found: $src")"
        unzip -q "$src" -d "$WORK/x"
        local found
        found="$(find "$WORK/x" -maxdepth 3 -type f -name module.json -path "*/${MODULE}/module.json" | head -n 1)"
        [ -n "$found" ] || die "$(t "L'archive ne contient pas ${MODULE}/module.json." "The archive does not contain ${MODULE}/module.json.")"
        cp -a "$(dirname "$found")" "$WORK/$MODULE"
    fi
    grep -q "\"alias\": \"${ALIAS}\"" "$WORK/$MODULE/module.json" || die "$(t "module.json inattendu." "Unexpected module.json.")"
    rm -rf "$WORK/$MODULE/Tests"
    ok "HydroLink $(json_version "$WORK/$MODULE/module.json")"
}

install_module() {
    step "$(t "Copie des fichiers" "Copying the files")"
    local current new
    current="$(json_version "${FS_PATH}/Modules/${MODULE}/module.json")"
    new="$(json_version "$WORK/$MODULE/module.json")"
    [ -n "$current" ] && info "$(t "Mise à jour ${current} -> ${new:-?}" "Update ${current} -> ${new:-?}")"
    run rm -rf "${FS_PATH}/Modules/${MODULE}"
    run cp -a "$WORK/$MODULE" "${FS_PATH}/Modules/${MODULE}"
    run chown -R "$WEB_USER":"$WEB_USER" "${FS_PATH}/Modules/${MODULE}"

    step "$(t "Activation" "Activation")"
    artisan cache:clear
    artisan module:enable "$MODULE"
    # creates the link public/modules/hydrolink, runs the migrations, clears the caches (like Manage > Modules)
    artisan freescout:module-install "$ALIAS"

    step "$(t "Migrations" "Migrations")"
    artisan migrate --force
    artisan freescout:clear-cache
    ok "$(t "Module activé, base à jour." "Module enabled, database up to date.")"
}

verify() {
    step "$(t "Vérification et binaire hydroxide" "Verification and hydroxide binary")"
    if [ "$DRY_RUN" -eq 1 ]; then printf '  (dry-run) php artisan hydrolink:health; php artisan hydrolink:update-binary\n'; return 0; fi
    mkdir -p "${FS_PATH}/storage/app/hydrolink"
    chown -R "$WEB_USER":"$WEB_USER" "${FS_PATH}/storage/app/hydrolink"
    chmod 700 "${FS_PATH}/storage/app/hydrolink"
    as_web php artisan hydrolink:health >>"$LOG" 2>&1 || die "$(t "hydrolink:health a échoué (voir $LOG)." "hydrolink:health failed (see $LOG).")"
    ok "$(t "Contrôle de santé du module : OK." "Module health check: OK.")"
    if as_web php artisan hydrolink:update-binary; then
        ok "$(t "Binaire hydroxide prêt." "hydroxide binary ready.")"
    else
        warn "$(t "Binaire non installé maintenant : le cron réessaiera, ou bouton Installer dans le panneau." "Binary not installed now: the cron will try again, or use Install in the panel.")"
    fi
}

uninstall() {
    STEPS=3
    step "$(t "Vérifications" "Checks")"
    [ "$(id -u)" -eq 0 ] || die "$(t "Lancez le script avec sudo." "Run the script with sudo.")"
    detect_freescout
    [ -d "${FS_PATH}/Modules/${MODULE}" ] || die "$(t "HydroLink n'est pas installé dans $FS_PATH." "HydroLink is not installed in $FS_PATH.")"
    if [ "$KEEP_ACCOUNTS" -eq 1 ]; then
        info "$(t "Les sessions hydroxide et la table des comptes sont conservées (--keep-accounts)." "The hydroxide sessions and the accounts table are kept (--keep-accounts).")"
    else
        warn "$(t "Les boîtes FreeScout et leurs tickets sont conservés, mais n'utiliseront plus hydroxide." "The FreeScout mailboxes and their tickets are kept, but no longer use hydroxide.")"
    fi
    ask "$(t "Désinstaller HydroLink ?" "Uninstall HydroLink?")" || die "$(t "Annulé." "Cancelled.")"
    backup
    step "$(t "Arrêt et nettoyage" "Stop and clean-up")"
    local flags=()
    [ "$KEEP_ACCOUNTS" -eq 1 ] && flags+=(--keep-accounts)
    [ "$DRY_RUN" -eq 1 ] && flags+=(--dry-run)
    (cd "$FS_PATH" && sudo -u "$WEB_USER" php artisan hydrolink:uninstall ${flags[@]+"${flags[@]}"}) | tee -a "$LOG"
    step "$(t "Retrait" "Removal")"
    if [ "$KEEP_ACCOUNTS" -eq 0 ]; then
        artisan migrate:rollback --path="Modules/${MODULE}/Database/Migrations" --force || warn "$(t "Tables non supprimées (voir le journal)." "Tables not removed (see the log).")"
    fi
    artisan module:disable "$MODULE" || true
    run rm -rf "${FS_PATH}/Modules/${MODULE}" "${FS_PATH}/public/modules/${ALIAS}"
    run rm -f "${FS_PATH}/bootstrap/cache/config.php"
    artisan freescout:clear-cache || true
    ok "$(t "HydroLink retiré (sauvegarde : $BACKUP)." "HydroLink removed (backup: $BACKUP).")"
}

main() {
    parse_args "$@"
    : >"$LOG"
    chmod 600 "$LOG"
    printf '%s\n' "HydroLink - $(t "script" "script") ${SCRIPT_VERSION} ($(t "journal" "log"): $LOG)"
    [ "$DRY_RUN" -eq 1 ] && info "$(t "Mode --dry-run : aucune modification." "--dry-run mode: no change.")"
    if [ "$UNINSTALL" -eq 1 ]; then uninstall; exit 0; fi
    checks
    backup
    fetch_module
    install_module
    verify
    printf '\n%s\n' "$(t "Terminé : menu Gérer > HydroLink. Mises à jour du module : Gérer > Modules, UpdateGuard, ou ce script avec --update." "Done: menu Manage > HydroLink. Module updates: Manage > Modules, UpdateGuard, or this script with --update.")"
}

main "$@"
