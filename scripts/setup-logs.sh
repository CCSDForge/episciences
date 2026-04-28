#!/bin/bash

# Directory where logs are stored
LOG_DIR="logs"

# Ensure log directory exists
if [ ! -d "$LOG_DIR" ]; then
    echo "Creating $LOG_DIR directory..."
    mkdir -p "$LOG_DIR"
fi

# Set directory permissions to be writable by everyone (including web server)
# Use 2>/dev/null to ignore "Operation not permitted" if we don't own the file but it's already writable
chmod 777 "$LOG_DIR" 2>/dev/null || true

# List of common log files to initialize if they don't exist
LOG_FILES=(
    "dev.exceptions.log"
    "portal.exceptions.log"
    "dev.monolog.log"
)

for LOG_FILE in "${LOG_FILES[@]}"; do
    FILE_PATH="$LOG_DIR/$LOG_FILE"
    if [ ! -f "$FILE_PATH" ]; then
        echo "Initializing $FILE_PATH..."
        touch "$FILE_PATH"
    fi
    # Set file permissions to be writable by everyone
    chmod 666 "$FILE_PATH" 2>/dev/null || true
done

echo "Log directory and files setup complete with correct permissions."
