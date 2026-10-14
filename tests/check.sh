#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for source in lib.php config.php installation.php bin/*.php public/*.php public/api/*.php tests/*.php; do
  php -l "$source" >/dev/null
done
node --check public/counter.js
node --check public/slide-cookie.js
node -e 'const fs=require("fs"),vm=require("vm");for(const m of fs.readFileSync("public/index.php","utf8").matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(m[1]);'
php tests/dashboard_tabs_test.php
php tests/source_filter_contract_test.php
php tests/installation_test.php
node tests/migration_test.mjs
node tests/audit_repro.mjs
node tests/new_features_test.mjs
