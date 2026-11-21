#!/usr/bin/env bash
#
# Builds the hydroxide binaries published with HydroLink (CI job "binaries", needs Go and git):
#   dist/hydroxide-linux-amd64, dist/hydroxide-linux-arm64, dist/hydroxide-version.txt, dist/LICENSE.hydroxide
#
# Source: the pinned commit of build/hydroxide.lock + build/patches/*.patch. Static (CGO_ENABLED=0), reproducible
# flags (-trimpath, no build id), so the same commit and patches give the same SHA-256.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="${ROOT}/dist"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

lock() { sed -n "s/^$1=\(.*\)$/\1/p" "$ROOT/build/hydroxide.lock" | head -n 1; }
REPO="$(lock HYDROXIDE_REPO)"
COMMIT="$(lock HYDROXIDE_COMMIT)"
VERSION="$(lock HYDROXIDE_VERSION)"
if [ -z "$REPO" ] || [ -z "$COMMIT" ] || [ -z "$VERSION" ]; then
    echo "build/hydroxide.lock is incomplete" >&2
    exit 1
fi

git clone -q "$REPO" "$WORK/src"
git -C "$WORK/src" -c advice.detachedHead=false checkout -q "$COMMIT"
test "$(git -C "$WORK/src" rev-parse HEAD)" = "$COMMIT"
for p in "$ROOT"/build/patches/*.patch; do
    git -C "$WORK/src" apply --whitespace=nowarn "$p"
done

(cd "$WORK/src" && go test ./cmd/hydroxide/ ./auth/ ./protonmail/)

mkdir -p "$DIST"
for arch in amd64 arm64; do
    (cd "$WORK/src" && CGO_ENABLED=0 GOOS=linux GOARCH="$arch" go build -trimpath -buildvcs=false \
        -ldflags="-s -w -buildid=" -o "$DIST/hydroxide-linux-$arch" ./cmd/hydroxide)
done
printf '%s\n' "$VERSION" >"$DIST/hydroxide-version.txt"
cp "$WORK/src/LICENSE" "$DIST/LICENSE.hydroxide"
ls -l "$DIST"/hydroxide-*
