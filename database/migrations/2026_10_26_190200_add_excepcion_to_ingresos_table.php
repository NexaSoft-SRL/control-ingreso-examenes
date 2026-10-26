<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-19 (situacion excepcional): el ingreso admitido como excepcion queda
 * marcado y guarda su motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table): void {
            $table->boolean('es_excepcion')->default(false);
            $table->text('motivo_excepcion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table): void {
            $table->dropColumn(['es_excepcion', 'motivo_excepcion']);
        });
    }
};
