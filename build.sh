#!/bin/bash

# Get the current directory name
CURRENT_DIR="$(basename "$(pwd)")"

# Create a temporary directory with the same name
TEMP_DIR="./$CURRENT_DIR-temp"
mkdir -p "$TEMP_DIR"

# Copy files excluding .gitignore, media folder, and git-related files
rsync -av --progress ./ "$TEMP_DIR/" \
    --exclude .gitignore \
    --exclude media \
    --exclude "$CURRENT_DIR-temp" \
    --exclude "*.zip" \
    --exclude "build.sh" \
    --exclude .git \
    --exclude .gitattributes

# Create zip file (without the -temp suffix in the archive)
cd "$TEMP_DIR"
zip -r "../$CURRENT_DIR.zip" .

# Clean up temporary directory
cd ..
rm -rf "$TEMP_DIR"

echo "Build complete: $CURRENT_DIR.zip created successfully."