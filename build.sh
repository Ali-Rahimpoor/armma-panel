#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# Arma Panel — distribution build script
#
# Produces a clean, install-ready zip of the plugin with all development
# and demo artifacts removed:
#   • demo-server.php   (mocks the whole WP environment incl. nonces — must
#                        never ship on a live site)
#   • tests/            (unit test suite)
#   • composer.phar     (build tooling)
#   • .git*, zip archives, local guides
#
# Usage:  bash build.sh            → arma-panel-{VERSION}.zip in repo root
set -euo pipefail
cd "$(dirname "$0")"

VERSION=$(grep -oP "define\('ARMA_PANEL_VERSION', '\K[^']+" arma-panel.php)
if [ -z "$VERSION" ]; then echo "✘ could not read ARMA_PANEL_VERSION"; exit 1; fi

STAGE=$(mktemp -d)
OUT="arma-panel-${VERSION}.zip"

echo "▸ Building Arma Panel v${VERSION}…"

# 1) Copy everything except the distribution-ignored paths
mkdir -p "${STAGE}/arma-panel"
rsync -a \
    --exclude='.git*' \
    --exclude='.distignore' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='composer.phar' \
    --exclude='demo-server.php' \
    --exclude='tests/' \
    --exclude='build/' \
    --exclude='dist/' \
    --exclude='node_modules/' \
    --exclude='*.zip' \
    --exclude='USAGE-AND-SECURITY-GUIDE.md' \
    --exclude='CHANGES.md' \
    --exclude='.DS_Store' \
    --exclude='.demo-state.json' \
    ./ "${STAGE}/arma-panel/"

# 2) Hard guarantees — the build fails if any dev file sneaked in
for forbidden in demo-server.php composer.phar; do
    if [ -e "${STAGE}/arma-panel/${forbidden}" ]; then
        echo "✘ SECURITY: ${forbidden} must not ship!"; rm -rf "$STAGE"; exit 1;
    fi
done
if [ -d "${STAGE}/arma-panel/tests" ]; then
    echo "✘ SECURITY: tests/ must not ship!"; rm -rf "$STAGE"; exit 1
fi

# 3) The autoloader must be present or the plugin fatals on install
if [ ! -f "${STAGE}/arma-panel/vendor/autoload.php" ]; then
    echo "✘ vendor/autoload.php missing — run: php composer.phar dump-autoload -o"; rm -rf "$STAGE"; exit 1
fi

# 4) Zip it (python3 zipfile — no dependency on the zip binary)
rm -f "$OUT"
python3 - "$STAGE" "$OUT" << 'PY'
import sys, zipfile, os
stage, out = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(stage):
        for f in sorted(files):
            full = os.path.join(root, f)
            z.write(full, os.path.relpath(full, stage))
PY
rm -rf "$STAGE"

echo "✔ Built ${OUT}:"
python3 - "$OUT" << 'PY'
import sys, zipfile
names = zipfile.ZipFile(sys.argv[1]).namelist()
print(f"  {len(names)} files")
print("  demo-server.php shipped? ", any(n.endswith('demo-server.php') for n in names))
print("  composer.phar shipped?   ", any(n.endswith('composer.phar') for n in names))
print("  tests/ shipped?          ", any('/tests/' in n for n in names))
print("  vendor/autoload shipped? ", any(n.endswith('vendor/autoload.php') for n in names))
PY
