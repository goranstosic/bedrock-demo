#!/usr/bin/env bash
#
# Posts the result of a deploy to a Slack or Microsoft Teams incoming webhook.
#
# Called by .github/workflows/deploy.yml. Reads everything from the
# environment so commit messages and names are never evaluated as shell:
#
#   WEBHOOK_URL     Slack (hooks.slack.com) or Teams Workflows webhook URL
#   RESULT          success, failure or cancelled
#   ENVIRONMENT     development, staging or production
#   GITHUB_SHA      commit being deployed
#   COMMIT_MESSAGE  commit message (optional)
#   AUTHOR          commit author or the person who started the run
#   COMMIT_URL      link to the commit
#   RUN_URL         link to the workflow run
#
# Usage: scripts/notify-deploy.sh [--dry-run]   (--dry-run prints the payload)

set -euo pipefail

: "${WEBHOOK_URL:?WEBHOOK_URL is not set}"
: "${RESULT:?}" "${ENVIRONMENT:?}" "${GITHUB_SHA:?}" "${AUTHOR:?}" "${COMMIT_URL:?}" "${RUN_URL:?}"

case "${RESULT}" in
	success) LABEL="Deploy succeeded" ;;
	failure) LABEL="Deploy FAILED" ;;
	cancelled) LABEL="Deploy cancelled" ;;
	*) LABEL="Deploy finished: ${RESULT}" ;;
esac

SHORT_SHA="${GITHUB_SHA:0:7}"
# First line of the commit message only.
SUBJECT="${COMMIT_MESSAGE:-}"
SUBJECT="${SUBJECT%%$'\n'*}"
TITLE="${LABEL}: ${ENVIRONMENT}"

if [[ "${WEBHOOK_URL}" == https://hooks.slack.com/* ]]; then
	PAYLOAD="$(jq -n \
		--arg title "${TITLE}" \
		--arg env "${ENVIRONMENT}" \
		--arg sha "${SHORT_SHA}" \
		--arg subject "${SUBJECT}" \
		--arg author "${AUTHOR}" \
		--arg result "${RESULT}" \
		--arg commit_url "${COMMIT_URL}" \
		--arg run_url "${RUN_URL}" \
		'{
			text: "\($title) (\($sha) by \($author))",
			blocks: [
				{ type: "header", text: { type: "plain_text", text: $title } },
				{ type: "section", fields: [
					{ type: "mrkdwn", text: "*Environment*\n\($env)" },
					{ type: "mrkdwn", text: "*Result*\n\($result)" },
					{ type: "mrkdwn", text: "*Commit*\n<\($commit_url)|\($sha)> \($subject)" },
					{ type: "mrkdwn", text: "*Author*\n\($author)" }
				] },
				{ type: "actions", elements: [
					{ type: "button", text: { type: "plain_text", text: "View run" }, url: $run_url }
				] }
			]
		}')"
else
	# Microsoft Teams "Post to a channel when a webhook request is received"
	# workflow, which expects an Adaptive Card.
	PAYLOAD="$(jq -n \
		--arg title "${TITLE}" \
		--arg env "${ENVIRONMENT}" \
		--arg sha "${SHORT_SHA}" \
		--arg subject "${SUBJECT}" \
		--arg author "${AUTHOR}" \
		--arg result "${RESULT}" \
		--arg commit_url "${COMMIT_URL}" \
		--arg run_url "${RUN_URL}" \
		'{
			type: "message",
			attachments: [ {
				contentType: "application/vnd.microsoft.card.adaptive",
				content: {
					"$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
					type: "AdaptiveCard",
					version: "1.4",
					body: [
						{ type: "TextBlock", text: $title, weight: "Bolder", size: "Medium", wrap: true,
						  color: (if $result == "success" then "Good" elif $result == "failure" then "Attention" else "Default" end) },
						{ type: "FactSet", facts: [
							{ title: "Environment", value: $env },
							{ title: "Result", value: $result },
							{ title: "Commit", value: "[\($sha)](\($commit_url)) \($subject)" },
							{ title: "Author", value: $author }
						] }
					],
					actions: [ { type: "Action.OpenUrl", title: "View run", url: $run_url } ]
				}
			} ]
		}')"
fi

if [ "${1:-}" = "--dry-run" ]; then
	echo "${PAYLOAD}"
	exit 0
fi

curl --silent --show-error --fail-with-body --max-time 15 \
	-H "Content-Type: application/json" \
	--data "${PAYLOAD}" \
	"${WEBHOOK_URL}"
echo
echo "Notification sent."
