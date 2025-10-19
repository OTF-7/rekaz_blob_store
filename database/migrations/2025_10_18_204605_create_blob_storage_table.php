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
        Schema::create('blob_storage', function (Blueprint $table) {
            $table->id();
            $table->string('blob_id');
            $table->longText('data'); // Store base64 encoded data
            $table->timestamps();
            
            // Foreign key constraint to blobs table
            $table->foreign('blob_id')->references('id')->on('blobs')->onDelete('cascade');
            
            // Unique index for blob_id lookups
            $table->unique('blob_id', 'idx_blob_storage_blob_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blob_storage');
    }
};
