#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
find n4s-apps -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
mkdir -p dist && rm -f dist/n4s-apps-plugin.zip
zip -rq dist/n4s-apps-plugin.zip n4s-apps -x '*/.*'
echo "dist/n4s-apps-plugin.zip ($(du -h dist/n4s-apps-plugin.zip | cut -f1))"
