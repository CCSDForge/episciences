#!/bin/bash

# Cron wrapper for the zbjats:zip console command.
#
# Runs one PHP process PER journal (--rvcode) instead of letting the console
# command loop over the whole journals.ini in a single process. This works
# around a bug where the RVID constant is defined once for the first journal
# and then stays stuck for every following journal in the same PHP process,
# silently zipping nothing for them.
#
# Usage: [ENV VARS] run_zbjats_zip.sh [/path/to/journals.ini]
# Required environment variables: PHP, SCRIPT_DIR, LOG_ZBJATS
# Optional environment variables: ZBJATS_REMOVE_CACHE (default: 1), ZBJATS_ZIP_PREFIX

set -euo pipefail

JOURNALS_INI="${1:-}"

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" >&2
    if [[ -n "${LOG_ZBJATS:-}" ]]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" >> "$LOG_ZBJATS"
    fi
}

log_info() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $*"
    if [[ -n "${LOG_ZBJATS:-}" ]]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $*" >> "$LOG_ZBJATS"
    fi
}

if [[ -z "${PHP:-}" || -z "${SCRIPT_DIR:-}" || -z "${LOG_ZBJATS:-}" ]]; then
    log_error "One or more required environment variables are not set."
    log_error "Required: PHP, SCRIPT_DIR, LOG_ZBJATS"
    exit 1
fi

CONSOLE_PHP="$SCRIPT_DIR/console.php"
JOURNALS_INI="${JOURNALS_INI:-$SCRIPT_DIR/zbjats/journals.ini}"
ZBJATS_REMOVE_CACHE="${ZBJATS_REMOVE_CACHE:-1}"
ZBJATS_ZIP_PREFIX="${ZBJATS_ZIP_PREFIX:-}"

if [[ ! -x "$PHP" ]]; then
    log_error "PHP binary not found or not executable: $PHP"
    exit 1
fi

if [[ ! -f "$CONSOLE_PHP" ]]; then
    log_error "console.php not found: $CONSOLE_PHP"
    exit 1
fi

if [[ ! -f "$JOURNALS_INI" ]]; then
    log_error "Journals INI file not found: $JOURNALS_INI"
    exit 1
fi

if [[ ! -d "$SCRIPT_DIR" ]]; then
    log_error "Script directory not found: $SCRIPT_DIR"
    exit 1
fi

log_info "Starting zbjats:zip processing"
log_info "Journals INI: $JOURNALS_INI"
log_info "Log file: $LOG_ZBJATS"
log_info "Script directory: $SCRIPT_DIR"

cd "$SCRIPT_DIR" || {
    log_error "Failed to change to script directory: $SCRIPT_DIR"
    exit 1
}

readarray -t journals < <(grep -oP 'journals\[\]\s*=\s*"\K[^"]+' "$JOURNALS_INI")

if [[ ${#journals[@]} -eq 0 ]]; then
    log_error "No journals found in $JOURNALS_INI"
    exit 1
fi

log_info "Found ${#journals[@]} journal(s) to process"

extra_opts=()
[[ "$ZBJATS_REMOVE_CACHE" == "1" ]] && extra_opts+=(--remove-cache)
[[ -n "$ZBJATS_ZIP_PREFIX" ]] && extra_opts+=(--zip-prefix="$ZBJATS_ZIP_PREFIX")

# Disable exit on error for the per-journal loop: one failing journal must not
# stop the others.
set +e

processed=0
errors=0

for rvcode in "${journals[@]}"; do
    log_info "Processing journal: $rvcode"

    "$PHP" "$CONSOLE_PHP" zbjats:zip --rvcode="$rvcode" "${extra_opts[@]}" >> "$LOG_ZBJATS" 2>&1
    exit_code=$?

    if [[ $exit_code -eq 0 ]]; then
        ((processed++))
        log_info "Successfully processed journal: $rvcode"
    else
        ((errors++))
        log_error "Failed to process journal: $rvcode (exit code: $exit_code)"
    fi
done

set -e

log_info "Processing complete. Processed: $processed, Errors: $errors"

if [[ $processed -eq 0 && $errors -gt 0 ]]; then
    log_error "All journal processing failed"
    exit 1
elif [[ $errors -gt 0 ]]; then
    log_info "Some journals failed but $processed were processed successfully"
    exit 0
fi