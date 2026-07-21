<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disease_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['disease_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disease_products');
    }
};
