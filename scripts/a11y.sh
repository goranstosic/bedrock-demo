#!/usr/bin/env bash
#
# Runs the accessibility tests against the local DDEV site.
#
# Usage: scripts/a11y.sh [extra Playwright arguments]
# Needs: Node.js 20 or later on the host, and a running, seeded DDEV project
#        (ddev start && ddev setup).

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/../tests/a11y"

npm ci --no-audit --no-fund
npx playwright install chromium
BASE_URL="${BASE_URL:-http://northwind-demo.ddev.site}" npx playwright test "$@"
