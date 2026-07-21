<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            $table->string('sku')->nullable()->after('category');
            $table->decimal('purchase_price', 10, 2)->nullable()->after('sku');
            $table->integer('min_stock')->default(0)->after('purchase_price');
            $table->string('unit')->default('unidad')->after('min_stock');
            $table->string('barcode')->nullable()->after('unit');
            $table->text('description')->nullable()->after('barcode');
            $table->text('image_url')->nullable()->after('description');
            $table->boolean('is_visible_on_map')->default(false)->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'sku',
                'purchase_price',
                'min_stock',
                'unit',
                'barcode',
                'description',
                'image_url',
                'is_visible_on_map',
            ]);
        });
    }
};
