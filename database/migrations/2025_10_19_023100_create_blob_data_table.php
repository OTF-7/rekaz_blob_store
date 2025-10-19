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
        Schema::create('blob_data', function (Blueprint $table) {
            $table->id();
            $table->string('blob_id')->index();
            $table->longText('data'); // Base64 encoded blob data
            $table->timestamps();
            
            // Foreign key constraint to blobs table
            $table->foreign('blob_id')->references('id')->on('blobs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blob_data');
    }
};
