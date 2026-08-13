#!/usr/bin/env bash
#
# Optik-/Regressions-Check öffentlicher Seiten via Playwright im Docker-Container (A-3).
# Braucht einen laufenden Docker-Daemon (Docker Desktop starten).
#
# Erster Lauf legt Baselines an (tests/visual/__snapshots__/) — diese committen.
# Spätere Läufe vergleichen gegen die Baselines und melden Abweichungen.
#
#   bash website/bin/visual-check.sh            # gegen Live-Seite prüfen
#   bash website/bin/visual-check.sh --update   # Baselines neu erzeugen
#   VISUAL_BASE_URL=http://localhost:8080 bash website/bin/visual-check.sh
#
set -euo pipefail
cd "$(dirname "$0")/.."   # -> website/

# Muss zur @playwright/test-Version in tests/visual/package.json passen
PW_IMAGE="mcr.microsoft.com/playwright:v1.48.2-jammy"

UPDATE=""
[ "${1:-}" = "--update" ] && UPDATE="--update-snapshots"

if ! command -v docker >/dev/null 2>&1; then
  echo "FEHLER: docker nicht gefunden. Docker Desktop starten." >&2
  exit 127
fi

docker run --rm \
  -v "$PWD/tests/visual":/work -w /work \
  -e VISUAL_BASE_URL="${VISUAL_BASE_URL:-https://atsv-kirchseeon-marktlauf.de}" \
  "$PW_IMAGE" \
  bash -c "npm install --no-audit --no-fund && npx playwright test ${UPDATE}"

echo "Visual-Check fertig. Report/Diffs unter tests/visual/test-results/"
