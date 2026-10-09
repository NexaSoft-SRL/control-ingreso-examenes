<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La condicion de un inscrito frente a un examen y el aula que le toca.
 * «Sin revisar» es la ausencia de fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habilitaciones', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->boolean('habilitado');

            // Nulo = sin repartir.
            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->text('motivo')
                ->nullable();

            $table->foreignId('registrada_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique(
                ['examen_id', 'estudiante_id'],
                'uq_habilitacion_examen_estudiante'
            );

            $table->index(
                ['examen_id', 'aula_id'],
                'idx_habilitacion_examen_aula'
            );
        });

        // Un no habilitado no ocupa aula.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE habilitaciones
                 ADD CONSTRAINT chk_habilitacion_aula_solo_habilitado
                 CHECK (habilitado OR aula_id IS NULL)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitaciones');
    }
};
