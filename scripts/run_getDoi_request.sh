#!/bin/bash

# Usage: [ENV VARS] run_getDoi_request.sh /path/to/journals.json

JOURNAL_JSON="$1"

if [[ -z "$GETDOI_PHP" || -z "$LOG_REQUEST" || -z "$SCRIPT_DIR" || -z "$PHP" ]]; then
    echo "Error: One or more required environment variables are not set."
    echo "Required: GETDOI_PHP, LOG_REQUEST, SCRIPT_DIR, PHP"
    exit 1
fi

cd "$SCRIPT_DIR" || exit 1

for rvid in $(jq -r '.[].rvid' "$JOURNAL_JSON"); do
    $PHP "$GETDOI_PHP" --rvid "$rvid" --request >> "$LOG_REQUEST" 2>&1
done