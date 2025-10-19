# Quick Server Fix - Storage Backend Issue

## 🚨 IMMEDIATE ACTIONS (Run on Production Server)

### Option 1: Run the Automated Script
```bash
# Upload server_fix.sh to your production server, then:
chmod +x server_fix.sh
./server_fix.sh
```

### Option 2: Manual Commands (if script fails)

#### 1. Clear Laravel Caches (MOST IMPORTANT)
```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

#### 2. Check Current Storage Status
```bash
php artisan storage:debug
```

#### 3. Verify .env Configuration
```bash
grep "STORAGE_BACKEND" .env
grep -E "FTP_HOST|FTP_USERNAME|FTP_PASSWORD" .env
```

#### 4. Test FTP Connection
```bash
# Replace with your actual FTP details
ftp your-ftp-host.com
# Enter username/password when prompted
```

#### 5. Check Laravel Logs
```bash
tail -20 storage/logs/laravel.log
```

## 🔍 EXPECTED RESULTS

After running the fix:
- `php artisan storage:debug` should show:
  - ✅ **Active Backend:** FTP
  - ✅ **Configuration Valid:** Yes
  - ✅ **Connection Test:** Passed

## ⚠️ IF STILL NOT WORKING

### Emergency Fallback to Local Storage
```bash
# Edit .env file:
STORAGE_BACKEND=local
LOCAL_STORAGE_PATH=/var/www/html/storage/app/blobs

# Clear and recache:
php artisan config:clear
php artisan config:cache

# Verify:
php artisan storage:debug
```

### Common Issues
1. **Config Cache Override** → Always run `config:clear` first
2. **FTP Firewall** → Check if port 21 is open
3. **Wrong FTP Path** → Verify FTP_ROOT directory exists
4. **File Permissions** → Ensure web server can write to storage/

## 📞 NEED HELP?
Share the output of:
```bash
php artisan storage:debug
tail -10 storage/logs/laravel.log
```