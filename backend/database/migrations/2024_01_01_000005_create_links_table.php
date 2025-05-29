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
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_component_id')->constrained('components')->onDelete('cascade');
            $table->string('from_slot');
            $table->foreignId('to_component_id')->constrained('components')->onDelete('cascade');
            $table->string('to_slot');
            $table->json('path_data')->nullable(); // Store visual path information
            $table->timestamps();
            
            $table->index('project_id');
            $table->index(['from_component_id', 'from_slot']);
            $table->index(['to_component_id', 'to_slot']);
            
            // Ensure unique links
            $table->unique(['from_component_id', 'from_slot', 'to_component_id', 'to_slot'], 'unique_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
