#!/bin/bash

# Production Server Storage Backend Fix Script
# Run this script on your production server to diagnose and fix storage issues

echo "=== Production Server Storage Backend Troubleshooting ==="
echo "Starting diagnostic and fix process..."
echo ""

# Step 1: Check .env file
echo "1. Checking .env configuration..."
if [ -f ".env" ]; then
    echo "✓ .env file exists"
    echo "Current STORAGE_BACKEND setting:"
    grep "STORAGE_BACKEND" .env || echo "⚠ STORAGE_BACKEND not found in .env"
    echo "FTP Configuration:"
    grep -E "FTP_HOST|FTP_USERNAME|FTP_PORT|FTP_ROOT" .env || echo "⚠ FTP settings not found"
else
    echo "✗ .env file not found!"
    exit 1
fi
echo ""

# Step 2: Clear Laravel caches (CRITICAL)
echo "2. Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo "✓ All caches cleared"
echo ""

# Step 3: Re-cache configuration
echo "3. Re-caching configuration with current .env values..."
php artisan config:cache
echo "✓ Configuration cached"
echo ""

# Step 4: Run storage diagnostics
echo "4. Running storage diagnostics..."
php artisan storage:debug
echo ""

# Step 5: Test FTP connectivity
echo "5. Testing FTP connectivity..."
FTP_HOST=$(grep "FTP_HOST=" .env | cut -d '=' -f2)
FTP_USER=$(grep "FTP_USERNAME=" .env | cut -d '=' -f2)
FTP_PASS=$(grep "FTP_PASSWORD=" .env | cut -d '=' -f2)
FTP_PORT=$(grep "FTP_PORT=" .env | cut -d '=' -f2)

if [ -n "$FTP_HOST" ] && [ -n "$FTP_USER" ]; then
    echo "Testing FTP connection to $FTP_HOST..."
    
    # Create temporary PHP script to test FTP
    cat > /tmp/test_ftp.php << EOF
<?php
\$host = '$FTP_HOST';
\$user = '$FTP_USER';
\$pass = '$FTP_PASS';
\$port = ${FTP_PORT:-21};

echo "Connecting to \$host:\$port...\n";
\$conn = ftp_connect(\$host, \$port, 10);
if (\$conn) {
    echo "✓ FTP connection successful\n";
    if (ftp_login(\$conn, \$user, \$pass)) {
        echo "✓ FTP login successful\n";
        echo "✓ FTP is working correctly\n";
    } else {
        echo "✗ FTP login failed - check username/password\n";
    }
    ftp_close(\$conn);
} else {
    echo "✗ FTP connection failed - check host/port/firewall\n";
}
EOF
    
    php /tmp/test_ftp.php
    rm /tmp/test_ftp.php
else
    echo "⚠ FTP credentials not found in .env"
fi
echo ""

# Step 6: Check file permissions
echo "6. Checking file permissions..."
echo "Storage directory permissions:"
ls -la storage/ | head -5
echo "Bootstrap cache permissions:"
ls -la bootstrap/cache/ 2>/dev/null || echo "Bootstrap cache directory not found"
echo ""

# Step 7: Check recent logs
echo "7. Checking recent Laravel logs for errors..."
if [ -f "storage/logs/laravel.log" ]; then
    echo "Recent storage/FTP related errors:"
    tail -50 storage/logs/laravel.log | grep -i "ftp\|storage\|connection" | tail -5 || echo "No recent FTP/storage errors found"
else
    echo "No Laravel log file found"
fi
echo ""

# Step 8: Final verification
echo "8. Final verification..."
echo "Running storage diagnostics again to verify fixes..."
php artisan storage:debug
echo ""

echo "=== Troubleshooting Complete ==="
echo ""
echo "If the issue persists:"
echo "1. Check the full SERVER_TROUBLESHOOTING.md guide"
echo "2. Verify network connectivity to FTP server"
echo "3. Contact your hosting provider about FTP access"
echo "4. Consider temporarily switching to local storage"
echo ""
echo "To test blob upload:"
echo "1. Upload a file through your application"
echo "2. Check if it appears on FTP server (not in database)"
echo "3. Monitor 'tail -f storage/logs/laravel.log' for errors"