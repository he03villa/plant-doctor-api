<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            INSERT INTO sale_payments (sale_id, amount, payment_method, created_at, updated_at)
            SELECT s.id, s.total, s.payment_method, s.created_at, s.updated_at
            FROM sales s
            WHERE NOT EXISTS (
                SELECT 1 FROM sale_payments sp WHERE sp.sale_id = s.id
            )
        ');
    }

    public function down(): void
    {
        DB::statement('
            DELETE FROM sale_payments sp
            USING sales s
            WHERE sp.sale_id = s.id
              AND sp.amount = s.total
              AND sp.payment_method = s.payment_method
        ');
    }
};
