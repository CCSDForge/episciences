#!/bin/bash

# ==============================================================================
# ANNUAL LOG ANALYZER SCRIPT - PROCESS BY YEAR/SITE
# ------------------------------------------------------------------------------
# Purpose: Calls the main import script (matomoParseLogs.sh) for every month
#          of a specified year for a single IDSite and Subdomain, passing
#          optional custom flags (e.g., --enable-bots).
#
# Usage: ./annual_analyzer.sh <CONF_FILE> <YEAR> <IDSITE> <SUBDOMAIN> [OPTIONS_FLAGS]
# Example: ./annual_analyzer.sh /etc/conf/daily_config.conf 2024 48 oai "--enable-bots"
# ==============================================================================

# ------------------------------------------------------------------------------
# 1. ARGUMENT VALIDATION AND ASSIGNMENT
# ------------------------------------------------------------------------------

# Check if required arguments (1 to 4) are provided
if [ "$#" -lt 4 ]; then
    echo "Error: Missing required arguments."
    echo "Usage: $0 <PATH_TO_CONFIG_FILE> <YEAR> <IDSITE> <SUBDOMAIN> [OPTIONS_FLAGS]"
    echo "Example: $0 /etc/conf/daily_config.conf 2024 48 oai \"--enable-bots\""
    exit 1
fi

CONFIG_FILE="$1"
TARGET_YEAR="$2"
TARGET_IDSITE="$3"
TARGET_SUBDOMAIN="$4"
# The 5th argument is optional. If not provided, it remains empty.
OPTIONS_FLAGS="${5:-}"

# Define the path to the main import script
MAIN_SCRIPT="./matomoParseLogs.sh"

if [ ! -f "$MAIN_SCRIPT" ]; then
    echo "Error: Main script '$MAIN_SCRIPT' not found. Please check the path."
    exit 1
fi

echo "Starting annual analysis for site ${TARGET_IDSITE} (${TARGET_SUBDOMAIN}) in ${TARGET_YEAR}."
echo "Custom Flags: ${OPTIONS_FLAGS:-None}"
echo "--------------------------------------------------------"

# ------------------------------------------------------------------------------
# 2. LOOP THROUGH 12 MONTHS
# ------------------------------------------------------------------------------

# seq -w 1 12 generates months from 01 to 12 (two-digit format required by the main script)
for MONTH in $(seq -w 1 12); do

    echo "-> Processing month ${MONTH}/${TARGET_YEAR}..."

    # Call the main script (matomoParseLogs.sh) in manual override mode.
    # Note: We pass the OPTIONS_FLAGS as a separate argument at the end.
    "${MAIN_SCRIPT}" "${CONFIG_FILE}" \
        --idsite="${TARGET_IDSITE}" \
        --subdomain="${TARGET_SUBDOMAIN}" \
        --year="${TARGET_YEAR}" \
        --month="${MONTH}" \
        ${OPTIONS_FLAGS} # Insert the optional flags here.

    # Optional: Uncomment the sleep command if you need a pause between months
    # sleep 5

done

echo "--------------------------------------------------------"
echo "Annual analysis complete for site ${TARGET_IDSITE} in ${TARGET_YEAR}."