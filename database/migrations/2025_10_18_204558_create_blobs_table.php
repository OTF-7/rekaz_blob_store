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
        Schema::create('blobs', function (Blueprint $table) {
            $table->string('id')->primary(); // Custom string ID as primary key
            $table->string('original_filename')->nullable();
            $table->bigInteger('size_bytes')->unsigned();
            $table->string('mime_type', 100)->nullable();
            $table->enum('storage_backend', ['s3', 'database', 'local', 'ftp']);
            $table->text('storage_path')->nullable();
            $table->string('checksum_md5', 32)->nullable();
            $table->timestamps();
            
            // Create indexes for performance
            $table->index('storage_backend', 'idx_blobs_storage_backend');
            $table->index('created_at', 'idx_blobs_created_at');
            $table->index('size_bytes', 'idx_blobs_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blobs');
    }
};
