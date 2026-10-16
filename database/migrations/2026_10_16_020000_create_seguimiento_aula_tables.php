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
        Schema::create('asignaciones_seguimiento_aula', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('asignado_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique(
                ['usuario_id', 'examen_id', 'aula_id'],
                'uq_seguimiento_usuario_examen_aula',
            );

            $table->index(
                ['examen_id', 'aula_id'],
                'idx_seguimiento_examen_aula',
            );
        });

        Schema::create('incidencias_aula', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('estudiantes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('reportada_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('tipo', 60);
            $table->text('detalle');
            $table->timestamp('reportada_en')->useCurrent();
            $table->foreignId('atendida_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('atendida_en')->nullable();

            $table->index(
                ['examen_id', 'aula_id', 'reportada_en'],
                'idx_incidencia_aula_examen_aula_fecha',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE incidencias_aula
                 ADD CONSTRAINT chk_incidencia_aula_detalle
                 CHECK (length(btrim(tipo)) > 0 AND length(btrim(detalle)) > 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_aula');
        Schema::dropIfExists('asignaciones_seguimiento_aula');
    }
};
