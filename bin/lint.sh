#!/usr/bin/env bash
#
# PHP-Syntax-Check (php -l) aller .php-Dateien via Docker.
# Auf dem Arbeitsrechner ist kein PHP installiert; dieser Lauf nutzt
# php:8.4-cli und entspricht damit der Strato-Server-Version.
#
# Aufruf (aus dem Repo oder überall):  bash website/bin/lint.sh
#
set -euo pipefail

# Ins Website-Wurzelverzeichnis wechseln (eine Ebene über bin/)
cd "$(dirname "$0")/.."

if ! command -v docker >/dev/null 2>&1; then
  echo "FEHLER: docker nicht gefunden. Docker Desktop starten oder php lokal installieren." >&2
  exit 127
fi

docker run --rm -v "$PWD":/app -w /app php:8.4-cli bash -c '
  status=0
  while IFS= read -r -d "" f; do
    php -l "$f" >/dev/null 2>err.tmp || { cat err.tmp; status=1; }
  done < <(find . -type d \( -name vendor -o -name venv -o -name node_modules -o -name .git \) -prune -o -name "*.php" -print0)
  rm -f err.tmp
  exit $status
'

echo "PHP-Lint OK (php:8.4-cli)"
