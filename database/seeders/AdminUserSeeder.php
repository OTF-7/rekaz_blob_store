<?php

namespace Database\Seeders;

use App\Models\StorageConfiguration;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@rekaz.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        if ($adminUser->wasRecentlyCreated) {
            Log::info('Default admin user created', [
                'email' => $adminUser->email,
                'name' => $adminUser->name,
            ]);
            $this->command->info('✓ Default admin user created: admin@rekaz.com / admin123');
        } else {
            $this->command->info('✓ Default admin user already exists: admin@rekaz.com');
        }

        // Create default storage configurations
        $this->createDefaultStorageConfigurations();
    }

    /**
     * Create default storage configurations.
     */
    private function createDefaultStorageConfigurations(): void
    {
        // Database storage configuration (active by default)
        $databaseConfig = StorageConfiguration::firstOrCreate(
            ['backend_type' => 'database'],
            [
                'is_active' => true,
                'configuration' => [
                    'compression' => false,
                    'chunk_size' => 1048576, // 1MB
                ],
            ]
        );

        if ($databaseConfig->wasRecentlyCreated) {
            $this->command->info('✓ Database storage configuration created (active)');
        }

        // Local storage configuration (inactive by default)
        $localConfig = StorageConfiguration::firstOrCreate(
            ['backend_type' => 'local'],
            [
                'is_active' => false,
                'configuration' => [
                    'path' => storage_path('app/blobs'),
                    'create_directories' => true,
                    'permissions' => '755',
                ],
            ]
        );

        if ($localConfig->wasRecentlyCreated) {
            $this->command->info('✓ Local storage configuration created (inactive)');
        }

        // S3 storage configuration template (inactive by default)
        $s3Config = StorageConfiguration::firstOrCreate(
            ['backend_type' => 's3'],
            [
                'is_active' => false,
                'configuration' => [
                    'access_key_id' => 'YOUR_ACCESS_KEY_ID',
                    'secret_access_key' => 'YOUR_SECRET_ACCESS_KEY',
                    'region' => 'us-east-1',
                    'bucket' => 'your-bucket-name',
                    'endpoint' => null,
                    'use_path_style_endpoint' => false,
                    'prefix' => 'blobs/',
                ],
            ]
        );

        if ($s3Config->wasRecentlyCreated) {
            $this->command->info('✓ S3 storage configuration template created (inactive)');
        }

        // FTP storage configuration template (inactive by default)
        $ftpConfig = StorageConfiguration::firstOrCreate(
            ['backend_type' => 'ftp'],
            [
                'is_active' => false,
                'configuration' => [
                    'host' => 'ftp.example.com',
                    'username' => 'your_username',
                    'password' => 'your_password',
                    'port' => 21,
                    'root' => '/blobs',
                    'passive' => true,
                    'ssl' => false,
                    'timeout' => 30,
                ],
            ]
        );

        if ($ftpConfig->wasRecentlyCreated) {
            $this->command->info('✓ FTP storage configuration template created (inactive)');
        }

        $this->command->info('');
        $this->command->info('Storage configurations summary:');
        $this->command->info('- Database: Active (ready to use)');
        $this->command->info('- Local: Inactive (configure path if needed)');
        $this->command->info('- S3: Inactive (update credentials to activate)');
        $this->command->info('- FTP: Inactive (update credentials to activate)');
    }
}
