#!/usr/bin/env bash
#
# Build the release: <out-dir>/Require-Email-2FA-main/ and <out-dir>/Require-Email-2FA-main.zip.
# The folder name matches the one used on the live sites; never rename it.
#
# Usage: bin/build.sh <out-dir>

set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$1"
NAME="Require-Email-2FA-main"
PUC="vendor/yahnis-elsts/plugin-update-checker"

rm -rf "${OUT:?}/$NAME" "$OUT/$NAME.zip"
mkdir -p "$OUT/$NAME/$(dirname "$PUC")"

cp "$REPO"/force-email-two-factor.php "$REPO"/uninstall.php "$REPO"/readme.txt "$REPO"/LICENSE "$OUT/$NAME/"
cp -R "$REPO/includes" "$OUT/$NAME/"
cp -R "$REPO/$PUC" "$OUT/$NAME/$PUC"
rm -rf "$OUT/$NAME/$PUC/languages" "$OUT/$NAME/$PUC/css" "$OUT/$NAME/$PUC/js" "$OUT/$NAME/$PUC/README.md"

(cd "$OUT" && zip -qr "$NAME.zip" "$NAME")
