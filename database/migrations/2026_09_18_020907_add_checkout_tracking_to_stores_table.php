<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('checkout_platform')->nullable()->after('stripe_customer_id');
            $table->string('checkout_session_id')->nullable()->after('checkout_platform');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['checkout_platform', 'checkout_session_id']);
        });
    }
};
