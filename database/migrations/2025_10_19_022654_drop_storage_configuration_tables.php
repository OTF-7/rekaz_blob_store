<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop storage configuration tables as we now use .env configuration
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('storage_backends');
        Schema::dropIfExists('storage_configurations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate basic table structures if needed for rollback
        Schema::create('storage_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('backend_type');
            $table->boolean('is_active')->default(false);
            $table->json('configuration')->nullable();
            $table->timestamps();
        });
        
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
};
