#!/bin/bash

set -e
# Navigate to the application directory
cd /var/www/gee/hrappdf

# Temporary file to capture the output
OUTPUT_FILE="/tmp/deploy_output.txt"

# Run the deployment process and capture the output
{
    echo "Starting deployment..."

    # Ensure the working directory is clean by removing untracked files and directories
    echo "Removing untrack directories and files..."
    git clean -fd

    # Pull the latest code from the production branch
    echo "Pulling latest code from production branch..."
    git fetch origin main
    git reset --hard origin/main

    echo "Installing/updating PHP dependencies..."
    # Install/update PHP dependencies
    #composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --ignore-platform-req=ext-gd

    # Install/update Node.js dependencies and build assets
    echo "Building frontend assets..."
    npm ci
    npm run build

    # Run database migrations if required
    # php artisan migrate --force

    echo "Clearing Laravel caches..."
    # Clear Laravel caches
    php artisan optimize:clear
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear

    # Restart necessary services
    # sudo systemctl restart php-fpm
    # sudo systemctl restart nginx

    # Notify that the deployment is completed
    echo "Deployment completed successfully."

} &> "$OUTPUT_FILE"

# Check if deployment was successful
if [ $? -eq 0 ]; then
    STATUS="success"
else
    STATUS="failed"
fi

# Send the email notification using Laravel Artisan command
#php artisan deploy:notify $STATUS --output="$(cat $OUTPUT_FILE)"

# Clean up
# rm "$OUTPUT_FILE"!!