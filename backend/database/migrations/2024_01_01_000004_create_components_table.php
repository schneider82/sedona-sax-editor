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
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('component_type_id')->constrained()->onDelete('restrict');
            $table->foreignId('parent_id')->nullable()->constrained('components')->onDelete('cascade');
            $table->string('name');
            $table->integer('component_id'); // ID within the SAX file
            $table->json('properties')->nullable(); // Component-specific properties
            $table->json('meta')->nullable(); // Meta value from SAX
            $table->integer('x')->default(0); // Canvas position
            $table->integer('y')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
            
            $table->index('project_id');
            $table->index('parent_id');
            $table->unique(['project_id', 'component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
