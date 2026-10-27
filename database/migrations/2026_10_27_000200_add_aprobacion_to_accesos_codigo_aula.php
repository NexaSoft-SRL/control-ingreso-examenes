<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-18: un acceso con codigo de aula espera a que el docente lo autorice
 * en persona (PENDIENTE); el docente lo AUTORIZA, lo RECHAZA, lo CORTA o lo
 * pasa a otra aula del examen. Los accesos que ya existian abrian la puerta
 * al instante: quedan AUTORIZADOS, y los revocados, CORTADOS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accesos_codigo_aula', function (Blueprint $table): void {
            // PENDIENTE, AUTORIZADO, RECHAZADO o CORTADO.
            $table->string('estado', 12)->default('PENDIENTE');
            $table->foreignId('resuelto_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('resuelto_en')->nullable();
            $table->index(['examen_id', 'estado'], 'idx_accesos_codigo_aula_estado');
        });

        DB::table('accesos_codigo_aula')->whereNotNull('revocado_en')->update(['estado' => 'CORTADO']);
        DB::table('accesos_codigo_aula')->whereNull('revocado_en')->update(['estado' => 'AUTORIZADO']);
    }

    public function down(): void
    {
        // Lo que no quedo autorizado deja de abrir la puerta, como antes.
        DB::table('accesos_codigo_aula')
            ->whereIn('estado', ['PENDIENTE', 'RECHAZADO', 'CORTADO'])
            ->whereNull('revocado_en')
            ->update(['revocado_en' => now()]);

        Schema::table('accesos_codigo_aula', function (Blueprint $table): void {
            $table->dropIndex('idx_accesos_codigo_aula_estado');
            $table->dropConstrainedForeignId('resuelto_por');
            $table->dropColumn(['estado', 'resuelto_en']);
        });
    }
};
