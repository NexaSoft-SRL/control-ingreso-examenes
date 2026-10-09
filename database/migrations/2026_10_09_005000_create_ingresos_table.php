<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El ingreso de un estudiante a un examen, en su forma minima. Todavia nadie
 * escribe aqui (lo hara el punto de control): existe para que las reglas
 * «con ingresos no se modifica el examen» y «quien ya ingreso no se
 * inhabilita» sean reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // El aula por la que entro.
            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('registrado_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('registrado_en')
                ->useCurrent();

            $table->unique(
                ['examen_id', 'estudiante_id'],
                'uq_ingreso_examen_estudiante'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos');
    }
};
