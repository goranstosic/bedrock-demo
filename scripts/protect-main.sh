#!/usr/bin/env bash
#
# Protects the main branch on GitHub: changes arrive through pull requests
# with passing checks, and history cannot be rewritten.
#
# Usage: scripts/protect-main.sh [owner/repo] [branch]
# Needs: the GitHub CLI (gh), logged in as a repository admin.
#
# The required checks are the job names in .github/workflows/ci.yml and
# a11y.yml. Keep this list in sync when jobs are renamed.

set -euo pipefail

REPO="${1:-$(gh repo view --json nameWithOwner --jq .nameWithOwner)}"
BRANCH="${2:-main}"

echo "Protecting ${BRANCH} on ${REPO}"

gh api \
	--method PUT \
	-H "Accept: application/vnd.github+json" \
	"repos/${REPO}/branches/${BRANCH}/protection" \
	--input - <<JSON
{
  "required_status_checks": {
    "strict": true,
    "checks": [
      { "context": "Lint" },
      { "context": "WP Engine build" },
      { "context": "Accessibility" }
    ]
  },
  "enforce_admins": false,
  "required_pull_request_reviews": {
    "required_approving_review_count": 0,
    "dismiss_stale_reviews": true,
    "require_code_owner_reviews": false
  },
  "restrictions": null,
  "required_linear_history": false,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "required_conversation_resolution": true
}
JSON

echo
gh api "repos/${REPO}/branches/${BRANCH}/protection" \
	--jq '{checks: [.required_status_checks.checks[].context], strict: .required_status_checks.strict, pull_requests_required: (.required_pull_request_reviews != null), force_pushes: .allow_force_pushes.enabled, deletions: .allow_deletions.enabled}'
