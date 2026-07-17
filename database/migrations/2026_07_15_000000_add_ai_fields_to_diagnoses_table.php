<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->string('ai_provider')->nullable()->after('image_path');
            $table->text('species_name')->nullable()->after('ai_provider');
            $table->json('species_common_names')->nullable()->after('species_name');
            $table->text('disease_name_detected')->nullable()->after('species_common_names');
            $table->text('disease_name_scientific')->nullable()->after('disease_name_detected');
            $table->string('disease_severity')->nullable()->after('disease_name_scientific');
            $table->json('symptoms_observed')->nullable()->after('disease_severity');
            $table->text('treatment_recommendation')->nullable()->after('symptoms_observed');
            $table->text('prevention_recommendation')->nullable()->after('treatment_recommendation');
            $table->json('ai_raw_response')->nullable()->after('prevention_recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider',
                'species_name',
                'species_common_names',
                'disease_name_detected',
                'disease_name_scientific',
                'disease_severity',
                'symptoms_observed',
                'treatment_recommendation',
                'prevention_recommendation',
                'ai_raw_response',
            ]);
        });
    }
};
