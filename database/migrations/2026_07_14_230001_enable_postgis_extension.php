<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis_topology');
    }

    public function down(): void
    {
        // Las extensiones PostGIS son infraestructura compartida
        // (postgis_tiger_geocoder y otros objetos dependen de postgis).
        // No se eliminan en rollback; up() las recrea idempotente
        // con IF NOT EXISTS.
    }
};
