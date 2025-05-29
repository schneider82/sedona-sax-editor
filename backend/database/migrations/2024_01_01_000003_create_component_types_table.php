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
        Schema::create('component_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type_name'); // e.g. "Folder", "DO", "UI", etc.
            $table->text('description')->nullable();
            $table->json('slots')->nullable(); // Define available slots
            $table->json('properties')->nullable(); // Default properties
            $table->string('icon')->nullable();
            $table->string('category')->nullable(); // For palette organization
            $table->boolean('is_folder')->default(false);
            $table->timestamps();
            
            $table->index(['kit_id', 'type_name']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_types');
    }
};
