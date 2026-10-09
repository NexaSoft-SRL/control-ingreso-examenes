<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El examen es de la asignatura y abarca grupos; lleva sus aulas y sus
 * normas (las marcadas de plantillas y un texto libre). Dos examenes pueden
 * compartir aula y hora: es un aviso, no una restriccion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examenes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('asignatura_id')
                ->constrained('asignaturas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('tipo', 20);

            $table->date('fecha');

            $table->time('hora_inicio');

            $table->smallInteger('duracion_minutos');

            // Texto libre; las normas marcadas van en `examen_norma`.
            $table->text('normas')
                ->nullable();

            $table->foreignId('creado_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->index(['fecha', 'hora_inicio'], 'idx_examen_fecha_hora');

            $table->index(
                ['asignatura_id', 'periodo_id'],
                'idx_examen_asignatura_periodo'
            );

            $table->index('creado_por', 'idx_examen_creador');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE examenes
                 ADD CONSTRAINT chk_examen_duracion
                 CHECK (duracion_minutos BETWEEN 15 AND 480)'
            );
        }

        Schema::create('examen_grupo', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->unique(['examen_id', 'grupo_id'], 'uq_examen_grupo');

            $table->index('grupo_id', 'idx_examen_grupo_grupo');
        });

        // Un aula del examen. Sin tope de estudiantes: dos examenes pueden
        // compartirla a la misma hora.
        Schema::create('examen_aula', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique(['examen_id', 'aula_id'], 'uq_examen_aula');

            $table->index('aula_id', 'idx_examen_aula_aula');
        });

        // Normas reutilizables. Sin cuenta = norma predefinida del sistema;
        // con cuenta = plantilla propia de quien registra examenes.
        Schema::create('plantillas_norma', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('texto', 300);

            $table->timestamps();

            $table->unique(
                ['usuario_id', 'texto'],
                'uq_plantilla_usuario_texto'
            );
        });

        // El unico anterior no alcanza a las predefinidas: en PostgreSQL
        // dos nulos no chocan.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX uq_plantilla_predefinida_texto
                 ON plantillas_norma (texto)
                 WHERE usuario_id IS NULL'
            );
        }

        // Las normas marcadas en un examen. El texto es una copia: el examen
        // conserva la norma aunque la plantilla se edite o se quite.
        Schema::create('examen_norma', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('plantilla_id')
                ->nullable()
                ->constrained('plantillas_norma')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('texto', 300);

            $table->smallInteger('orden');

            $table->unique(
                ['examen_id', 'plantilla_id'],
                'uq_examen_norma'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_norma');
        Schema::dropIfExists('plantillas_norma');
        Schema::dropIfExists('examen_aula');
        Schema::dropIfExists('examen_grupo');
        Schema::dropIfExists('examenes');
    }
};
