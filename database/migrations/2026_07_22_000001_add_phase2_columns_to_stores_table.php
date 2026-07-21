<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('name');
            $table->string('tax_id')->nullable()->after('business_name');
            $table->string('business_phone')->nullable()->after('phone');
            $table->string('business_email')->nullable()->after('business_phone');
            $table->boolean('is_premium')->default(false);
            $table->boolean('onboarding_completed')->default(false);
            $table->boolean('sync_to_map')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'business_name',
                'tax_id',
                'business_phone',
                'business_email',
                'is_premium',
                'onboarding_completed',
                'sync_to_map',
            ]);
        });
    }
};
