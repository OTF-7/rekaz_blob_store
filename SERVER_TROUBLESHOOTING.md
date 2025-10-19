# Production Server Storage Backend Troubleshooting Guide

This guide helps diagnose and fix storage backend issues on your production server where blobs are being saved to the database instead of the configured FTP backend.

## Quick Diagnosis Commands

Run these commands on your production server to quickly identify the issue:

```bash
# 1. Check current storage configuration
php artisan storage:debug

# 2. Clear all Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 3. Re-cache configuration
php artisan config:cache

# 4. Check storage again
php artisan storage:debug
```

## Step-by-Step Troubleshooting

### Step 1: Verify .env File Configuration

```bash
# Check if .env file exists
ls -la .env

# Verify STORAGE_BACKEND setting
grep "STORAGE_BACKEND" .env

# Check FTP configuration
grep -E "FTP_HOST|FTP_USERNAME|FTP_PASSWORD|FTP_PORT|FTP_ROOT" .env

# Ensure no extra spaces or quotes
cat .env | grep -E "STORAGE_BACKEND|FTP_"
```

**Expected output:**
```
STORAGE_BACKEND=ftp
FTP_HOST=your-ftp-host.com
FTP_USERNAME=your-username
FTP_PASSWORD=your-password
FTP_PORT=21
FTP_ROOT=/path/to/ftp/root
```

### Step 2: Clear Laravel Configuration Cache

```bash
# Clear all caches (CRITICAL - cached config overrides .env)
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Verify caches are cleared
ls -la bootstrap/cache/
# Should show no config.php file

# Re-cache configuration with current .env values
php artisan config:cache
```

### Step 3: Run Storage Diagnostics

```bash
# Run the storage diagnostic command
php artisan storage:debug

# Check what backend is being used
php artisan tinker
# In tinker, run:
app('storage.manager')->getBestAvailableDriver()->getBackendType();
exit
```

### Step 4: Test FTP Connectivity

```bash
# Test FTP connection manually
ftp your-ftp-host.com
# Enter username and password when prompted
# If successful, type 'quit' to exit

# Test with curl (if available)
curl -u username:password ftp://your-ftp-host.com/

# Test PHP FTP functions
php -r "
\$conn = ftp_connect('your-ftp-host.com', 21);
if (\$conn) {
    echo 'FTP connection successful\n';
    if (ftp_login(\$conn, 'username', 'password')) {
        echo 'FTP login successful\n';
    } else {
        echo 'FTP login failed\n';
    }
    ftp_close(\$conn);
} else {
    echo 'FTP connection failed\n';
}"
```

### Step 5: Check File Permissions

```bash
# Check Laravel storage permissions
ls -la storage/
ls -la storage/logs/

# Ensure web server can write to storage
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/

# Check bootstrap/cache permissions
ls -la bootstrap/cache/
sudo chown -R www-data:www-data bootstrap/cache/
sudo chmod -R 775 bootstrap/cache/
```

### Step 6: Inspect Laravel Logs

```bash
# Check recent Laravel logs for FTP errors
tail -f storage/logs/laravel.log

# Search for FTP-related errors
grep -i "ftp\|storage\|connection" storage/logs/laravel.log | tail -20

# Check for configuration errors
grep -i "config\|cache" storage/logs/laravel.log | tail -10
```

### Step 7: Manual FTP Configuration Test

```bash
# Create a test script to verify FTP configuration
cat > test_ftp.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "Testing FTP Configuration:\n";
echo "Host: " . $_ENV['FTP_HOST'] . "\n";
echo "Username: " . $_ENV['FTP_USERNAME'] . "\n";
echo "Port: " . ($_ENV['FTP_PORT'] ?? 21) . "\n";
echo "Root: " . ($_ENV['FTP_ROOT'] ?? '/') . "\n";

// Test connection
$connection = ftp_connect($_ENV['FTP_HOST'], $_ENV['FTP_PORT'] ?? 21);
if ($connection) {
    echo "✓ FTP connection successful\n";
    
    if (ftp_login($connection, $_ENV['FTP_USERNAME'], $_ENV['FTP_PASSWORD'])) {
        echo "✓ FTP login successful\n";
        
        // Test directory change
        $root = $_ENV['FTP_ROOT'] ?? '/';
        if (ftp_chdir($connection, $root)) {
            echo "✓ FTP root directory accessible: $root\n";
        } else {
            echo "✗ FTP root directory not accessible: $root\n";
        }
        
        // Test file creation
        $testFile = tempnam(sys_get_temp_dir(), 'ftp_test');
        file_put_contents($testFile, 'test content');
        
        if (ftp_put($connection, 'test_connection.txt', $testFile, FTP_ASCII)) {
            echo "✓ FTP file upload successful\n";
            ftp_delete($connection, 'test_connection.txt');
            echo "✓ FTP file deletion successful\n";
        } else {
            echo "✗ FTP file upload failed\n";
        }
        
        unlink($testFile);
    } else {
        echo "✗ FTP login failed\n";
    }
    
    ftp_close($connection);
} else {
    echo "✗ FTP connection failed\n";
}
EOF

# Run the test
php test_ftp.php

# Clean up
rm test_ftp.php
```

### Step 8: Force Storage Backend Test

```bash
# Create a test to force use specific storage backend
php artisan tinker
# In tinker, run:
$manager = app('storage.manager');
$driver = $manager->driver('ftp');
echo "FTP Driver configured: " . ($driver->isConfigured() ? 'YES' : 'NO') . "\n";
if ($driver->isConfigured()) {
    echo "Backend type: " . $driver->getBackendType() . "\n";
    // Test store operation
    try {
        $result = $driver->store('test content', 'test-file.txt');
        echo "Test file stored successfully\n";
        $driver->delete('test-file.txt');
        echo "Test file deleted successfully\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
exit
```

## Common Issues and Solutions

### Issue 1: Configuration Cache Override
**Symptoms:** `.env` changes don't take effect
**Solution:**
```bash
php artisan config:clear
php artisan config:cache
```

### Issue 2: FTP Connection Timeout
**Symptoms:** FTP connection fails or times out
**Solutions:**
- Check firewall settings
- Verify FTP server is accessible from production server
- Try passive mode FTP
- Check if FTP port (21) is open

### Issue 3: FTP Authentication Failure
**Symptoms:** Connection succeeds but login fails
**Solutions:**
- Verify username/password in `.env`
- Check for special characters that need escaping
- Ensure FTP user has proper permissions

### Issue 4: FTP Directory Permissions
**Symptoms:** Connection and login succeed but file operations fail
**Solutions:**
- Verify FTP_ROOT directory exists
- Check FTP user has write permissions
- Test with different directory paths

### Issue 5: Laravel Storage Permissions
**Symptoms:** General storage errors
**Solutions:**
```bash
sudo chown -R www-data:www-data storage/ bootstrap/cache/
sudo chmod -R 775 storage/ bootstrap/cache/
```

## Verification Steps

After applying fixes:

1. **Clear all caches:**
   ```bash
   php artisan config:clear && php artisan cache:clear && php artisan config:cache
   ```

2. **Run diagnostics:**
   ```bash
   php artisan storage:debug
   ```

3. **Test blob upload:**
   - Upload a file through your application
   - Check if it appears on FTP server instead of database
   - Verify `blobs` table in database remains unchanged

4. **Monitor logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Emergency Fallback

If FTP continues to fail, temporarily switch to local storage:

```bash
# In .env file, change:
STORAGE_BACKEND=local
LOCAL_STORAGE_PATH=/path/to/local/storage

# Clear and recache
php artisan config:clear
php artisan config:cache

# Verify
php artisan storage:debug
```

## Need Help?

If issues persist:
1. Run `php artisan storage:debug` and share the output
2. Check `storage/logs/laravel.log` for specific error messages
3. Verify network connectivity between server and FTP host
4. Consider contacting your hosting provider about FTP connectivity