<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('expert_verified')->default(false);
            $table->text('expert_notes')->nullable();
            $table->foreignId('expert_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['plant_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
