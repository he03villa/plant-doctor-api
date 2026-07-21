<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 10)->default('COP');
            $table->text('invoice_image_url')->nullable();
            $table->longText('ocr_raw_text')->nullable();
            $table->decimal('ocr_confidence', 5, 2)->nullable();
            $table->enum('status', ['pending', 'processed', 'verified', 'error'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
