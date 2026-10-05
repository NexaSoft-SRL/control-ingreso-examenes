<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ambientes DROP CONSTRAINT IF EXISTS ambientes_estado_valid');
            DB::statement(
                "ALTER TABLE ambientes
                 ADD CONSTRAINT ambientes_estado_valid
                 CHECK (estado IN ('DISPONIBLE', 'MANTENIMIENTO', 'OCUPADO', 'INACTIVO'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ambientes DROP CONSTRAINT IF EXISTS ambientes_estado_valid');
            DB::statement(
                "ALTER TABLE ambientes
                 ADD CONSTRAINT ambientes_estado_valid
                 CHECK (estado IN ('DISPONIBLE', 'MANTENIMIENTO', 'OCUPADO'))"
            );
        }
    }
};
