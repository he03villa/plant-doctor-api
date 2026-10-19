<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->foreignId('plant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // plant_id es nullable a propósito: los diagnósticos sin planta son
        // válidos (StoreDiagnosisRequest acepta plant_id => nullable).
        // No se revierte a NOT NULL para no destruir datos válidos ni
        // romper migrate:refresh / rollback cuando existen esos registros.
    }
};
