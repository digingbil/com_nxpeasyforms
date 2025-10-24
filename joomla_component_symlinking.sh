#!/usr/bin/env bash
#
# Joomla Component Dev Symlinker (robust)
# - Links admin, site, media and language files from repo into a Joomla install
# - Verifies each link; non-critical failures won't abort the script
# - Supports configurable owner/group and dev perms (0775/0664)
#

set -uo pipefail

# ----- colors and logging -----
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log_i(){ echo -e "${GREEN}[INFO]${NC} $*"; }
log_w(){ echo -e "${YELLOW}[WARN]${NC} $*"; }
log_e(){ echo -e "${RED}[ERROR]${NC} $*"; }

# ----- helpers -----
resolve_abs() {
  if command -v realpath >/dev/null 2>&1; then realpath "$1"; else readlink -f "$1"; fi
}

ensure_dir() { sudo mkdir -p "$1"; }

ask_use_default() {
  # $1=question with [default] inside; $2=default; $3=out var
  local label="$1" def="$2" outvar="$3" ans val
  while true; do
    read -rp "$label Y/n: " ans
    case "$ans" in
      ""|"Y"|"y")
        printf -v "$outvar" "%s" "$def"
        return 0
        ;;
      "N"|"n")
        while true; do
          read -rp "Enter value: " val
          [ -n "$val" ] && { printf -v "$outvar" "%s" "$val"; return 0; }
          log_w "Value cannot be empty."
        done
        ;;
      *)
        log_w "Please answer Y or n."
        ;;
    esac
  done
}

create_symlink() {
  # $1=src $2=dest $3=kind(file|dir) $4=owner:group
  local src="$1" dest="$2" kind="$3" own="$4"
  local ok=0

  if [ "$kind" = "dir" ] && [ ! -d "$src" ]; then log_w "Source dir missing: $src"; return 1; fi
  if [ "$kind" = "file" ] && [ ! -f "$src" ]; then log_w "Source file missing: $src"; return 1; fi

  ensure_dir "$(dirname "$dest")"

  # Backup existing non-symlink
  if [ -e "$dest" ] && [ ! -L "$dest" ]; then
    local backup="${dest}.bak.$(date +%s)"
    if sudo mv "$dest" "$backup"; then
      log_i "Backed up existing $(basename "$dest") -> $(basename "$backup")"
    else
      log_e "Failed to backup existing path: $dest"
      return 1
    fi
  fi

  # Create/refresh symlink to absolute source
  local abs_src; abs_src="$(resolve_abs "$src")"
  if sudo ln -sfn "$abs_src" "$dest"; then
    # chown symlink (may be unsupported on some FS; ignore errors)
    sudo chown -h "$own" "$dest" 2>/dev/null || true
    ok=1
  else
    log_e "Failed to create symlink: $dest"
    return 1
  fi

  if [ "$ok" -eq 1 ] && [ -L "$dest" ]; then
    local target; target="$(readlink "$dest")"
    log_i "Linked $kind: $dest -> $target"
    return 0
  fi

  log_e "Symlink not created: $dest"
  return 1
}

list_link() {
  local p="$1"
  if [ -L "$p" ]; then
    ls -ld "$p"
  elif [ -e "$p" ]; then
    log_w "Exists but not a symlink: $p"
    ls -ld "$p"
  else
    log_w "Missing path: $p"
  fi
}

# ----- gather inputs with exact Y/n style -----
COM_REPO="$(pwd)"
DETECTED_COMP="$(basename "$COM_REPO")"

ask_use_default "Use the detected component name [$DETECTED_COMP]?" "$DETECTED_COMP" COMPONENT_NAME
ask_use_default "Use default Joomla root [/var/www/html/j5.loc]?" "/var/www/html/j5.loc" JROOT
ask_use_default "Use default webserver user [www-data]?" "www-data" WEBUSER
ask_use_default "Use default webserver group [www-data]?" "www-data" WEBGROUP
OWN="$WEBUSER:$WEBGROUP"

echo
log_i "Configuration:"
echo "  Component: $COMPONENT_NAME"
echo "  Repo:      $COM_REPO"
echo "  Joomla:    $JROOT"
echo "  Owner:     $OWN"
echo

# Validate Joomla root
if [ ! -f "$JROOT/configuration.php" ]; then
  log_e "Joomla installation not found at $JROOT (configuration.php missing)."
  exit 1
fi

read -rp "Proceed with linking? Y/n: " PROCEED
[[ ! "$PROCEED" =~ ^([Yy]|)$ ]] && { log_w "Aborted."; exit 0; }

# ----- ensure target trees exist -----
ensure_dir "$JROOT/administrator/components"
ensure_dir "$JROOT/components"
ensure_dir "$JROOT/media"
ensure_dir "$JROOT/administrator/language/en-GB"
ensure_dir "$JROOT/language/en-GB"

CREATED=0; FAILED=0

# ----- link admin component -----
ADMIN_SRC="$COM_REPO/administrator/components/${COMPONENT_NAME}"
ADMIN_DEST="$JROOT/administrator/components/${COMPONENT_NAME}"
if create_symlink "$ADMIN_SRC" "$ADMIN_DEST" "dir" "$OWN"; then
  ((CREATED++)); list_link "$ADMIN_DEST"
else
  ((FAILED++))
fi

# ----- link site component (prefer ./components, fallback to ./site/components) -----
if [ -d "$COM_REPO/components/${COMPONENT_NAME}" ]; then
  SITE_SRC="$COM_REPO/components/${COMPONENT_NAME}"
elif [ -d "$COM_REPO/site/components/${COMPONENT_NAME}" ]; then
  SITE_SRC="$COM_REPO/site/components/${COMPONENT_NAME}"
else
  SITE_SRC=""
fi
SITE_DEST="$JROOT/components/${COMPONENT_NAME}"
if [ -n "$SITE_SRC" ]; then
  if create_symlink "$SITE_SRC" "$SITE_DEST" "dir" "$OWN"; then
    ((CREATED++)); list_link "$SITE_DEST"
  else
    ((FAILED++))
  fi
else
  log_w "Site component folder not found in repo (checked components/ and site/components/)."
fi

# ----- link media -----
MEDIA_SRC="$COM_REPO/media/${COMPONENT_NAME}"
MEDIA_DEST="$JROOT/media/${COMPONENT_NAME}"
if [ -d "$MEDIA_SRC" ]; then
  if create_symlink "$MEDIA_SRC" "$MEDIA_DEST" "dir" "$OWN"; then
    ((CREATED++)); list_link "$MEDIA_DEST"
  else
    ((FAILED++))
  fi
else
  log_w "Media folder not found in repo: $MEDIA_SRC"
fi

# ----- link language files (file-by-file) -----
declare -a LANG_SRC=(
  "$COM_REPO/administrator/language/en-GB/${COMPONENT_NAME}.ini"
  "$COM_REPO/administrator/language/en-GB/${COMPONENT_NAME}.sys.ini"
  "$COM_REPO/language/en-GB/${COMPONENT_NAME}.ini"
)
declare -a LANG_DEST=(
  "$JROOT/administrator/language/en-GB/${COMPONENT_NAME}.ini"
  "$JROOT/administrator/language/en-GB/${COMPONENT_NAME}.sys.ini"
  "$JROOT/language/en-GB/${COMPONENT_NAME}.ini"
)

for i in "${!LANG_SRC[@]}"; do
  SRC="${LANG_SRC[$i]}"; DEST="${LANG_DEST[$i]}"
  if create_symlink "$SRC" "$DEST" "file" "$OWN"; then
    ((CREATED++)); list_link "$DEST"
  else
    ((FAILED++))
  fi
done

echo
log_i "Summary: created=$CREATED, failed=$FAILED"

# ----- optional: make repo group-readable for web access -----
read -rp "Make repo group-readable for $WEBGROUP (chgrp -R; dirs 0775, files 0664)? Y/n: " REPOPERMS
if [[ "$REPOPERMS" =~ ^([Yy]|)$ ]]; then
  log_i "Updating repo group/perms at $COM_REPO ..."
  sudo chgrp -R "$WEBGROUP" "$COM_REPO" || true
  sudo find "$COM_REPO" -type d -exec chmod 0775 {} +
  sudo find "$COM_REPO" -type f -exec chmod 0664 {} +
fi

# ----- optional: apply dev perms across Joomla root (0775/0664) -----
read -rp "Apply dev perms (dirs 0775, files 0664) across Joomla root? Y/n: " APPLYDEV
if [[ "$APPLYDEV" =~ ^([Yy]|)$ ]]; then
  log_i "Setting directory perms to 0775 and file perms to 0664 under $JROOT ..."
  sudo find "$JROOT" -type d -exec chmod 0775 {} +
  sudo find "$JROOT" -type f -exec chmod 0664 {} +
fi

echo
log_i "Done. Verify links:"
echo "  - Admin:     $JROOT/administrator/components/${COMPONENT_NAME}"
echo "  - Site:      $JROOT/components/${COMPONENT_NAME}"
echo "  - Media:     $JROOT/media/${COMPONENT_NAME}"
echo "  - Languages: admin/site en-GB files"
echo
echo "Troubleshooting:"
echo "  - Show symlinks: sudo find \"$JROOT\" -maxdepth 4 -type l -ls | grep ${COMPONENT_NAME}"
echo "  - Debug run:     bash -x ./link-to-joomla.sh"
