#!/bin/bash

# ==============================================================================
# MATOMO/PIWIK LOG IMPORT SCRIPT - CRON OR MANUAL MODE (FIXED DAILY FILTER)
# ------------------------------------------------------------------------------
# Usage Cron:    ./matomoParseLogs.sh <PATH_TO_CONFIG_FILE>
# Usage Manual:  ./matomoParseLogs.sh <PATH_TO_CONFIG_FILE> [OPTIONS]
# ------------------------------------------------------------------------------

# --- Usage Documentation ---
usage() {
    echo "Usage: $0 <PATH_TO_CONFIG_FILE> [OPTIONS]"
    echo ""
    echo "This script processes Matomo/Piwik logs either for the day before (default/cron mode)"
    echo "or for a specific site/period (manual mode)."
    echo ""
    echo "Arguments:"
    echo "  <PATH_TO_CONFIG_FILE>   Absolute path to the configuration file (e.g., /etc/conf/daily_config.conf)."
    echo ""
    echo "Manual Options (Overrides):"
    echo "  --year=YYYY             Year to process (e.g., 2024)."
    echo "  --month=MM              Month to process (e.g., 01). Note: Use two digits."
    echo "  --idsite=ID             Single IDSite to process (e.g., 42)."
    echo "  --subdomain=SUBDOMAIN   Subdomain prefix associated with the site (e.g., oai). Must be used with --idsite."
    echo "  --help, -h              Display this help message and exit."
    echo ""
    echo "NOTE: PARAMETER_FILE supports 3 fields (e.g., IDSITE;SUBDOMAIN;--enable-bots  --enable-http-errors)"
    echo "----------------"
    exit 0
}

# --- 0. ARGUMENT PARSING AND OVERRIDES ---
MANUAL_YEAR=""
MANUAL_MONTH=""
MANUAL_IDSITE=""
MANUAL_SUBDOMAIN=""
CONFIG_FILE=""

# Check for help or missing config file
if [ -z "$1" ] || [ "$1" == "--help" ] || [ "$1" == "-h" ]; then usage; fi

# The first valid argument is the configuration file
CONFIG_FILE="$1"
shift

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: Configuration file '$CONFIG_FILE' not found. Exiting."
    exit 1
fi

# Parse remaining named arguments for overrides
for i in "$@"; do
    case $i in
        --year=*) MANUAL_YEAR="${i#*=}"; shift ;;
        --month=*) MANUAL_MONTH="${i#*=}"; shift ;;
        --idsite=*) MANUAL_IDSITE="${i#*=}"; shift ;;
        --subdomain=*) MANUAL_SUBDOMAIN="${i#*=}"; shift ;;
        --help|-h) usage ;;
        *) echo "Error: Unknown option '$i'. Use --help for usage information."; exit 1 ;;
    esac
done

# Load variables from the configuration file
source "$CONFIG_FILE"

# ------------------------------------------------------------------------------
# 1. CORE PARAMETER VALIDATION
# ------------------------------------------------------------------------------
if [ -z "$BASE_LOG_PATH" ] || [ -z "$URL_PIWIK" ] || [ -z "$TOKEN_AUTH" ] || [ -z "$PYTHON_SCRIPT" ] || [ -z "$PYTHON_VERSION" ] || [ -z "$DOMAIN_NAME" ]; then
    echo "Error: Missing critical configuration parameters. Check $CONFIG_FILE."
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. DATE LOGIC (CRON or MANUAL)
# ------------------------------------------------------------------------------
if [ -n "$MANUAL_YEAR" ] && [ -n "$MANUAL_MONTH" ]; then
    # Manual mode: Process the full specified month
    if ! [[ "$MANUAL_YEAR" =~ ^[0-9]{4}$ ]] || ! [[ "$MANUAL_MONTH" =~ ^[0-9]{2}$ ]]; then
        echo "Error: Year (YYYY) and Month (MM) parameters must be in the correct format (e.g., --year=2025 --month=01)."; exit 1
    fi
    TARGET_YEAR="$MANUAL_YEAR"
    TARGET_MONTH="$MANUAL_MONTH"
    TARGET_DAY="*" # Wildcard for full month processing
    echo "Manual Mode (Date) : Processing ${TARGET_YEAR}/${TARGET_MONTH} (full month)."
elif [ -n "$MANUAL_YEAR" ] || [ -n "$MANUAL_MONTH" ]; then
    echo "Error: Both --year and --month must be provided to override the default period."; exit 1
else
    # Cron/Default mode: Calculate yesterday's date (robust for year/leap change)
    TARGET_YEAR=$(date -d "yesterday" +%Y)
    TARGET_MONTH=$(date -d "yesterday" +%m)
    TARGET_DAY=$(date -d "yesterday" +%d) # PRECISE DAY (DD)
    echo "Cron Mode (Default) : Processing yesterday's logs (${TARGET_YEAR}/${TARGET_MONTH}/${TARGET_DAY} ONLY)."
fi

echo "Configuration loaded from: $CONFIG_FILE"
echo "Python Version used: $PYTHON_VERSION"
echo "--------------------------------------------------------"

# ------------------------------------------------------------------------------
# 3. SINGLE SITE PROCESSING FUNCTION (Updated to accept OPTIONS_FLAGS)
# ------------------------------------------------------------------------------
process_site() {
    local IDSITE="$1"
    local SUBDOMAIN="$2"
    local YEAR="$3"
    local MONTH="$4"
    local DAY="${5:-*}"
    local OPTIONS_FLAGS="$6" # NEW: Extra flags for this site (e.g., --enable-bots)
    local LOGS_FOUND=0

    # 1. Construct the base directory path
    SITE_LOG_ROOT="${BASE_LOG_PATH}/${SUBDOMAIN}.${DOMAIN_NAME}"

    # 2. Construct the PRECISE file pattern
    LOG_BASE_PATTERN="${SITE_LOG_ROOT}/${YEAR}/${MONTH}/${DAY}-${SUBDOMAIN}.${DOMAIN_NAME}.access_log"

    echo "-> Processing IDSite: ${IDSITE} (SUBDOMAIN: ${SUBDOMAIN})"
    echo "   Extra Flags: ${OPTIONS_FLAGS:-None}" # Display flags, 'None' if empty

    # 3. Test both suffixes (.gz and no suffix)
    local LOG_SUFFIXES=(".gz" "")

    for SUFFIX in "${LOG_SUFFIXES[@]}"; do
        LOG_FILE_PATTERN="${LOG_BASE_PATTERN}${SUFFIX}"

        if ls "${LOG_FILE_PATTERN}" 1> /dev/null 2>&1; then
            echo "   Target log path: ${LOG_FILE_PATTERN}"
            LOGS_FOUND=1

            # Execute the Python command, inserting OPTIONS_FLAGS before the log file path
            /usr/bin/${PYTHON_VERSION} "${PYTHON_SCRIPT}" \
                --log-format-regex="${LOG_REGEX}" \
                --token-auth "${TOKEN_AUTH}" \
                --url "${URL_PIWIK}" \
                --idsite "${IDSITE}" \
                --recorders "${RECORDERS}" \
                ${COMMON_FLAGS} \
                ${OPTIONS_FLAGS} \
                "${LOG_FILE_PATTERN}"
        fi
    done

    if [ "$LOGS_FOUND" -eq 0 ]; then
        echo "   Warning: No log files found matching pattern ${DAY}-${SUBDOMAIN}.${DOMAIN_NAME}.access_log(.gz) in ${YEAR}/${MONTH}."
    fi
}

# ------------------------------------------------------------------------------
# 4. SITE LOGIC (MANUAL or FILE) - Updated to read 3 fields
# ------------------------------------------------------------------------------

if [ -n "$MANUAL_IDSITE" ] && [ -n "$MANUAL_SUBDOMAIN" ]; then
    # Manual single site mode: OPTIONS_FLAGS must be empty here, as they aren't passed via command line
    echo "Manual Mode (Site) : Processing a single specified site."
    process_site "$MANUAL_IDSITE" "$MANUAL_SUBDOMAIN" "$TARGET_YEAR" "$TARGET_MONTH" "$TARGET_DAY" ""
elif [ -n "$MANUAL_IDSITE" ] || [ -n "$MANUAL_SUBDOMAIN" ]; then
    echo "Error: Both --idsite and --subdomain must be provided together for manual site mode."; exit 1
else
    # Default/Cron mode: loop through the parameter file (Reading 3 fields)
    echo "File Mode (Default) : Looping through sites in '$PARAMETER_FILE'."
    if [ -z "$PARAMETER_FILE" ] || [ ! -f "$PARAMETER_FILE" ]; then echo "Error: Site parameter file not defined or not found: '$PARAMETER_FILE'."; exit 1; fi

    IFS=';' # Internal Field Separator for site file: IDSITE;SUBDOMAIN;OPTIONS

    # Reading 3 fields: IDSITE, SUBDOMAIN_VAR, OPTIONS_FLAGS
    while read -r IDSITE SUBDOMAIN_VAR OPTIONS_FLAGS || [ -n "$IDSITE" ]; do

        # Trim whitespace and skip empty/commented lines
        IDSITE=$(echo $IDSITE | tr -d '[:space:]')
        SUBDOMAIN=$(echo $SUBDOMAIN_VAR | tr -d '[:space:]')
        # Note: We keep OPTIONS_FLAGS as is, allowing spaces if they separate multiple flags.

        if [ -z "$IDSITE" ] || [ -z "$SUBDOMAIN" ] || [[ "$IDSITE" =~ ^# ]]; then continue; fi

        process_site "$IDSITE" "$SUBDOMAIN" "$TARGET_YEAR" "$TARGET_MONTH" "$TARGET_DAY" "$OPTIONS_FLAGS"
    done < "$PARAMETER_FILE"
fi

echo "--------------------------------------------------------"
echo "Processing complete."