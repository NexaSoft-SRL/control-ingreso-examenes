<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El docente puede quitar a un inscrito de su grupo. La fila no se borra:
 * queda dada de baja con quien, cuando y por que. Una inscripcion con
 * `baja_en` deja de contar en todo el sistema; volver a cargarla o a
 * agregarla la reactiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscripciones', function (Blueprint $table): void {
            $table->timestamp('baja_en')
                ->nullable();

            $table->foreignId('baja_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('motivo_baja', 20)
                ->nullable();

            $table->string('detalle_baja', 200)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscripciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('baja_por');
            $table->dropColumn(['baja_en', 'motivo_baja', 'detalle_baja']);
        });
    }
};
