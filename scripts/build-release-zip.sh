#!/usr/bin/env bash
# Build a Moodle Marketplace-safe ZIP for local_learningprism.
# Usage:
#   ./scripts/build-release-zip.sh [output.zip]
#
# The archive contains a single top-level folder `learningprism/` so it can be
# unzipped into moodle/local/. Excludes .git, vendor, macOS junk, and nested zips.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

RELEASE="$(sed -n "s/.*\\\$plugin->release[[:space:]]*=[[:space:]]*'\\([^']*\\)'.*/\\1/p" version.php | head -1)"
VERSION="$(sed -n "s/.*\\\$plugin->version[[:space:]]*=[[:space:]]*\\([0-9][0-9]*\\).*/\\1/p" version.php | head -1)"
COMPONENT="$(sed -n "s/.*\\\$plugin->component[[:space:]]*=[[:space:]]*'\\([^']*\\)'.*/\\1/p" version.php | head -1)"

if [[ -z "${RELEASE}" ]]; then
  RELEASE="dev"
fi
if [[ -z "${VERSION}" ]]; then
  VERSION="$(date +%Y%m%d)"
fi
if [[ "${COMPONENT}" != "local_learningprism" ]]; then
  echo "Error: unexpected component '${COMPONENT}' in version.php" >&2
  exit 1
fi

DEFAULT_OUT="$(dirname "$ROOT")/local_learningprism-${RELEASE}-${VERSION}.zip"
OUT="${1:-$DEFAULT_OUT}"

OUT_DIR="$(cd "$(dirname "$OUT")" 2>/dev/null && pwd || true)"
if [[ -n "$OUT_DIR" && ( "$OUT_DIR" == "$ROOT" || "$OUT_DIR"/ == "$ROOT"/* ) ]]; then
  echo "Error: output ZIP must not be written inside the plugin directory." >&2
  echo "Suggested path: $DEFAULT_OUT" >&2
  exit 1
fi

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"

STAGE="$(mktemp -d "${TMPDIR:-/tmp}/lp-release.XXXXXX")"
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

mkdir -p "$STAGE/learningprism"
rsync -a \
  --exclude '.git/' \
  --exclude '.gitignore' \
  --exclude '.DS_Store' \
  --exclude '**/.DS_Store' \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude 'scripts/' \
  --exclude '*.zip' \
  --exclude '__MACOSX/' \
  --exclude '.idea/' \
  --exclude '.vscode/' \
  --exclude 'css/' \
  "$ROOT/" "$STAGE/learningprism/"

# Drop empty leftover dirs that rsync may still create.
find "$STAGE/learningprism" -type d -empty -delete 2>/dev/null || true

# Require the essentials Moodle Marketplace validators look for.
for required in version.php lang/en/local_learningprism.php README.md LICENSE.md; do
  if [[ ! -e "$STAGE/learningprism/$required" ]]; then
    echo "Error: missing required file in package: $required" >&2
    exit 1
  fi
done

(
  cd "$STAGE"
  # -X omit extra file attributes; -x exclude patterns (belt and braces).
  zip -r -X "$OUT" learningprism \
    -x '*/.DS_Store' -x '*__MACOSX*' -x '*/.git/*' -x '*/vendor/*'
)

echo "Created: $OUT"
echo "Size: $(du -h "$OUT" | awk '{print $1}')"
echo "Entries: $(zipinfo -1 "$OUT" | wc -l | tr -d ' ')"
echo "Top-level paths:"
zipinfo -1 "$OUT" | awk -F/ '{print $1}' | sort -u
echo
echo "Sanity checks:"
FAIL=0
LISTFILE="$(mktemp "${TMPDIR:-/tmp}/lp-ziplist.XXXXXX")"
zipinfo -1 "$OUT" >"$LISTFILE"
if grep -E '(^|/)(__MACOSX/|\.git/|\.DS_Store$|\.zip$|vendor/)' "$LISTFILE" >/dev/null; then
  echo "FAIL: ZIP contains .git, __MACOSX, .DS_Store, vendor, or nested .zip entries." >&2
  FAIL=1
fi
if ! grep -Fx 'learningprism/version.php' "$LISTFILE" >/dev/null; then
  echo "FAIL: learningprism/version.php missing at expected path." >&2
  FAIL=1
fi
for required in \
  learningprism/lang/en/local_learningprism.php \
  learningprism/README.md \
  learningprism/LICENSE.md \
  learningprism/classes/privacy/provider.php \
  learningprism/amd/build/course.min.js \
  learningprism/amd/build/course.min.js.map
do
  if ! grep -Fx "$required" "$LISTFILE" >/dev/null; then
    echo "FAIL: missing $required in ZIP." >&2
    FAIL=1
  fi
done
TOP="$(awk -F/ '{print $1}' "$LISTFILE" | sort -u)"
rm -f "$LISTFILE"
if [[ "$TOP" != "learningprism" ]]; then
  echo "FAIL: expected single top-level folder learningprism, got:" >&2
  echo "$TOP" >&2
  FAIL=1
fi
if [[ "$FAIL" -ne 0 ]]; then
  exit 1
fi
echo "OK: Marketplace layout looks clean."
echo
echo "Install check: unzip into moodle/local/ so you get moodle/local/learningprism/version.php"
