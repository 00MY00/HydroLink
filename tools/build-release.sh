#!/usr/bin/env bash
#
# Builds the module archive: dist/HydroLink.zip (folder HydroLink/ ready to drop into FreeScout's Modules/),
# dist/version.txt, dist/module.json, dist/install.sh, then SHA256SUMS over everything in dist/ (the hydroxide
# binaries of build/build-hydroxide.sh included when they are there). module.json (latestVersionUrl /
# latestVersionZipUrl) points FreeScout's own module update and UpdateGuard to version.txt and HydroLink.zip.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

version="$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$ROOT/HydroLink/module.json" | head -n 1)"
script_version="$(sed -n 's/^SCRIPT_VERSION="\([^"]*\)".*/\1/p' "$ROOT/install.sh")"
if [ "$version" != "$script_version" ]; then
    printf 'module.json (%s) and install.sh (%s) do not have the same version.\n' "$version" "$script_version" >&2
    exit 1
fi
if ! grep -q "^## \[${version}\]" "$ROOT/CHANGELOG.md"; then
    printf 'CHANGELOG.md: entry [%s] missing.\n' "$version" >&2
    exit 1
fi

cp -R "$ROOT/HydroLink" "$STAGE/HydroLink"
rm -rf "$STAGE/HydroLink/Tests"
cp "$ROOT/CHANGELOG.md" "$ROOT/LICENSE" "$STAGE/HydroLink/"
find "$STAGE" -name '.DS_Store' -delete

mkdir -p "$DIST"
rm -f "$DIST/HydroLink.zip" "$DIST/SHA256SUMS" "$DIST/module.json" "$DIST/version.txt" "$DIST/install.sh"
if command -v zip >/dev/null 2>&1; then
    (cd "$STAGE" && zip -qrX "$DIST/HydroLink.zip" HydroLink)
else
    (cd "$STAGE" && python3 -m zipfile -c "$DIST/HydroLink.zip" HydroLink)
fi
cp "$ROOT/install.sh" "$DIST/install.sh"
cp "$ROOT/HydroLink/module.json" "$DIST/module.json"
printf '%s\n' "$version" >"$DIST/version.txt"
(cd "$DIST" && find . -maxdepth 1 -type f ! -name SHA256SUMS -printf '%f\n' | LC_ALL=C sort | xargs sha256sum >SHA256SUMS)
printf 'HydroLink %s: %s\n' "$version" "$DIST/HydroLink.zip"
cat "$DIST/SHA256SUMS"
