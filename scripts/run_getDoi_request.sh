#!/bin/bash

# Usage: [ENV VARS] run_getDoi_request.sh /path/to/journals.json
# Required environment variables: GETDOI_PHP, LOG_REQUEST, SCRIPT_DIR, PHP

set -euo

JOURNAL_JSON="$1"
JQ_BINARY="${JQ_BINARY:-/usr/bin/jq}"

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" >&2
    if [[ -n "${LOG_REQUEST:-}" ]]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" >> "$LOG_REQUEST"
    fi
}

log_info() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $*"
    if [[ -n "${LOG_REQUEST:-}" ]]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $*" >> "$LOG_REQUEST"
    fi
}

if [[ -z "${JOURNAL_JSON:-}" ]]; then
    log_error "Missing required argument: path to journals.json"
    exit 1
fi

if [[ -z "$GETDOI_PHP" || -z "$LOG_REQUEST" || -z "$SCRIPT_DIR" || -z "$PHP" ]]; then
    log_error "One or more required environment variables are not set."
    log_error "Required: GETDOI_PHP, LOG_REQUEST, SCRIPT_DIR, PHP"
    exit 1
fi

if [[ ! -f "$JOURNAL_JSON" ]]; then
    log_error "Journal JSON file not found: $JOURNAL_JSON"
    exit 1
fi

if [[ ! -f "$GETDOI_PHP" ]]; then
    log_error "getDoi.php script not found: $GETDOI_PHP"
    exit 1
fi

if [[ ! -x "$PHP" ]]; then
    log_error "PHP binary not found or not executable: $PHP"
    exit 1
fi

if [[ ! -x "$JQ_BINARY" ]]; then
    log_error "jq binary not found or not executable: $JQ_BINARY"
    log_error "Please install jq or set JQ_BINARY environment variable to the correct path"
    exit 1
fi

if [[ ! -d "$SCRIPT_DIR" ]]; then
    log_error "Script directory not found: $SCRIPT_DIR"
    exit 1
fi

log_info "Starting getDoi request processing"
log_info "Journal JSON: $JOURNAL_JSON"
log_info "Log file: $LOG_REQUEST"
log_info "Script directory: $SCRIPT_DIR"

cd "$SCRIPT_DIR" || {
    log_error "Failed to change to script directory: $SCRIPT_DIR"
    exit 1
}

# Disable exit on error for the entire processing section
set +e

journal_count=$("$JQ_BINARY" '. | length' "$JOURNAL_JSON")
if [[ $? -ne 0 ]]; then
    log_error "Failed to parse journal count from JSON file"
    exit 1
fi

log_info "Found $journal_count journals to process"

processed=0
errors=0

# Get all RVIDs first and store in an array to avoid issues with command substitution
readarray -t rvids < <("$JQ_BINARY" -r '.[].rvid' "$JOURNAL_JSON")

if [[ ${#rvids[@]} -eq 0 ]]; then
    log_error "No journal RVIDs found in JSON file"
    exit 1
fi

for rvid in "${rvids[@]}"; do
    log_info "Processing journal rvid: $rvid"
    
    "$PHP" "$GETDOI_PHP" --rvid "$rvid" --request >> "$LOG_REQUEST" 2>&1
    exit_code=$?
    
    if [[ $exit_code -eq 0 ]]; then
        ((processed++))
        log_info "Successfully processed journal rvid: $rvid"
    else
        ((errors++))
        log_error "Failed to process journal rvid: $rvid (exit code: $exit_code)"
    fi
done

# Re-enable exit on error for final checks
set -e

log_info "Processing complete. Processed: $processed, Errors: $errors"

# Exit with error only if no journals were processed successfully
if [[ $processed -eq 0 && $errors -gt 0 ]]; then
    log_error "All journal processing failed"
    exit 1
elif [[ $errors -gt 0 ]]; then
    log_info "Some journals failed but $processed were processed successfully"
    exit 0
fi