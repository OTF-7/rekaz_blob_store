<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StorageManager;
use Exception;

class DiagnoseStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:diagnose {--backend= : Specific backend to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose storage backend configuration and connectivity';

    /**
     * Execute the console command.
     */
    public function handle(StorageManager $storageManager): int
    {
        $this->info('=== Storage Backend Diagnostics ===');
        $this->newLine();

        // Check environment configuration
        $this->info('Environment Configuration:');
        $this->line('STORAGE_BACKEND: ' . (env('STORAGE_BACKEND') ?: 'not set'));
        $this->line('Config cached: ' . (app()->configurationIsCached() ? 'YES' : 'NO'));
        $this->line('Environment: ' . app()->environment());
        $this->newLine();

        // Check current backend
        $currentBackend = $storageManager->getCurrentBackend();
        $this->info("Current Backend: {$currentBackend}");
        $this->newLine();

        // Check configured backends
        $configuredBackends = $storageManager->getConfiguredBackends();
        $this->info('Configured Backends: ' . implode(', ', $configuredBackends));
        $this->newLine();

        // Test specific backend or all backends
        $backendToTest = $this->option('backend');
        if ($backendToTest) {
            $this->testBackend($storageManager, $backendToTest);
        } else {
            $availableBackends = $storageManager->getAvailableBackends();
            foreach ($availableBackends as $backend) {
                $this->testBackend($storageManager, $backend);
            }
        }

        // Check environment variables for each backend
        $this->newLine();
        $this->info('=== Environment Variables Check ===');
        $this->checkEnvironmentVariables();

        return 0;
    }

    private function testBackend(StorageManager $storageManager, string $backend): void
    {
        $this->info("Testing {$backend} backend:");
        
        try {
            $result = $storageManager->testDriver($backend);
            
            if ($result['success']) {
                $this->line("  ✅ {$result['message']}");
                $this->line("  - Configured: " . ($result['configured'] ? 'YES' : 'NO'));
                $this->line("  - Data integrity: " . ($result['data_integrity'] ? 'PASS' : 'FAIL'));
                $this->line("  - Exists check: " . ($result['exists_check'] ? 'PASS' : 'FAIL'));
                $this->line("  - Size check: " . ($result['size_check'] ? 'PASS' : 'FAIL'));
            } else {
                $this->error("  ❌ {$result['message']}");
                $this->line("  - Configured: " . ($result['configured'] ? 'YES' : 'NO'));
                if (isset($result['error'])) {
                    $this->line("  - Error: {$result['error']}");
                }
            }
        } catch (Exception $e) {
            $this->error("  ❌ Exception: {$e->getMessage()}");
        }
        
        $this->newLine();
    }

    private function checkEnvironmentVariables(): void
    {
        $envVars = [
            'Database' => [],
            'Local' => ['LOCAL_STORAGE_PATH'],
            'S3' => ['S3_ENDPOINT', 'S3_BUCKET', 'S3_ACCESS_KEY', 'S3_SECRET_KEY', 'S3_REGION'],
            'FTP' => ['FTP_HOST', 'FTP_USERNAME', 'FTP_PASSWORD', 'FTP_PORT', 'FTP_ROOT', 'FTP_PASSIVE', 'FTP_SSL']
        ];

        foreach ($envVars as $backend => $vars) {
            $this->line("{$backend} Backend:");
            if (empty($vars)) {
                $this->line('  - No additional configuration required');
            } else {
                foreach ($vars as $var) {
                    $value = env($var);
                    $status = $value !== null ? '✅' : '❌';
                    $displayValue = $value !== null ? (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value) : 'not set';
                    $this->line("  {$status} {$var}: {$displayValue}");
                }
            }
            $this->newLine();
        }
    }
}