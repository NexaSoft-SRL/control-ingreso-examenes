<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expulsiones', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('ingreso_id')
                ->constrained('ingresos')
                ->restrictOnDelete();

            $table->foreignId('registrada_por')
                ->constrained('usuarios')
                ->restrictOnDelete();

            $table->text('motivo');

            $table->timestamp('registrada_en')
                ->useCurrent();

            $table->unique('ingreso_id', 'uq_expulsion_ingreso');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE expulsiones
                 ADD CONSTRAINT chk_expulsion_motivo
                 CHECK (length(btrim(motivo)) > 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expulsiones');
    }
};
