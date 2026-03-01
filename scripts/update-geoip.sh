#!/usr/bin/env bash
# =============================================================================
# update-geoip.sh — Download or update the GeoLite2-City.mmdb database.
#
# MaxMind requires a free account and license key to access GeoLite2.
# Sign up at: https://www.maxmind.com/en/geolite2/signup
#
# Usage:
#   MAXMIND_LICENSE_KEY=your_key bash scripts/update-geoip.sh
#   bash scripts/update-geoip.sh your_key
#   make update-geoip MAXMIND_LICENSE_KEY=your_key
#
# Environment variables:
#   MAXMIND_LICENSE_KEY   MaxMind license key (required)
#   GEOIP_DIR             Destination directory (default: scripts/geoip/)
# =============================================================================
set -euo pipefail

LICENSE_KEY="${MAXMIND_LICENSE_KEY:-${1:-}}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST_DIR="${GEOIP_DIR:-${SCRIPT_DIR}/geoip}"
DB_FILE="GeoLite2-City.mmdb"
EDITION="GeoLite2-City"
DOWNLOAD_URL="https://download.maxmind.com/app/geoip_download"

# ---------------------------------------------------------------------------
# Validate inputs
# ---------------------------------------------------------------------------
if [ -z "$LICENSE_KEY" ]; then
    echo "Error: MaxMind license key is required." >&2
    echo "" >&2
    echo "Usage:" >&2
    echo "  MAXMIND_LICENSE_KEY=your_key bash scripts/update-geoip.sh" >&2
    echo "  make update-geoip MAXMIND_LICENSE_KEY=your_key" >&2
    echo "" >&2
    echo "Get a free license key at: https://www.maxmind.com/en/geolite2/signup" >&2
    exit 1
fi

if ! command -v curl &>/dev/null; then
    echo "Error: curl is required but not installed." >&2
    exit 1
fi

if ! command -v tar &>/dev/null; then
    echo "Error: tar is required but not installed." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Setup
# ---------------------------------------------------------------------------
TMP_DIR=$(mktemp -d)
cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

mkdir -p "$DEST_DIR"

EXISTING_DATE=""
if [ -f "${DEST_DIR}/${DB_FILE}" ]; then
    EXISTING_DATE=$(date -r "${DEST_DIR}/${DB_FILE}" '+%Y-%m-%d' 2>/dev/null || echo "unknown")
    echo "Current database: ${DEST_DIR}/${DB_FILE} (dated ${EXISTING_DATE})"
fi

# ---------------------------------------------------------------------------
# Download
# ---------------------------------------------------------------------------
echo "Downloading ${EDITION} from MaxMind..."
HTTP_CODE=$(curl -fsSL \
    --write-out "%{http_code}" \
    "${DOWNLOAD_URL}?edition_id=${EDITION}&license_key=${LICENSE_KEY}&suffix=tar.gz" \
    -o "${TMP_DIR}/${EDITION}.tar.gz" \
    2>&1 || echo "000")

if [ "$HTTP_CODE" = "401" ]; then
    echo "Error: Invalid license key (HTTP 401). Check your MAXMIND_LICENSE_KEY." >&2
    exit 1
fi

if [ "$HTTP_CODE" != "200" ]; then
    echo "Error: Download failed (HTTP ${HTTP_CODE})." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Extract
# ---------------------------------------------------------------------------
echo "Extracting archive..."
tar -xzf "${TMP_DIR}/${EDITION}.tar.gz" -C "$TMP_DIR"

MMDB_FILE=$(find "$TMP_DIR" -name "${DB_FILE}" -type f | head -1)
if [ -z "$MMDB_FILE" ]; then
    echo "Error: ${DB_FILE} not found in the downloaded archive." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Install
# ---------------------------------------------------------------------------
cp "$MMDB_FILE" "${DEST_DIR}/${DB_FILE}"
chmod 644 "${DEST_DIR}/${DB_FILE}"

NEW_DATE=$(date -r "${DEST_DIR}/${DB_FILE}" '+%Y-%m-%d' 2>/dev/null || echo "unknown")
echo "Successfully installed: ${DEST_DIR}/${DB_FILE}"
echo "Database date: ${NEW_DATE}"

if [ -n "$EXISTING_DATE" ] && [ "$EXISTING_DATE" = "$NEW_DATE" ]; then
    echo "Note: Database date unchanged (${NEW_DATE}) — already up to date."
fi

echo ""
echo "Next step: make sure GEO_IP.DATABASE_PATH in config/pwd.json points to:"
echo "  ${DEST_DIR}/"
