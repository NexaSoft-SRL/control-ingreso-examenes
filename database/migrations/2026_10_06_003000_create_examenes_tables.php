<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El examen es de la asignatura y abarca grupos; lleva sus aulas y, por
 * aula, quien la atiende. Dos examenes pueden compartir aula y hora: es un
 * aviso, no una restriccion.
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

            // El texto unico que ve la puerta.
            $table->text('normas')
                ->nullable();

            $table->foreignId('creado_por')
                ->constrained('users')
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

            // Quien esta a cargo del aula: un docente o un auxiliar.
            $table->foreignId('encargado_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Seis digitos para el auxiliar ocasional; nulo = sin codigo.
            $table->char('codigo_acceso', 6)
                ->nullable();

            $table->timestamp('codigo_generado_en')
                ->nullable();

            $table->timestamps();

            $table->unique(['examen_id', 'aula_id'], 'uq_examen_aula');

            $table->index('aula_id', 'idx_examen_aula_aula');

            $table->index('encargado_id', 'idx_examen_aula_encargado');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX uq_examen_aula_codigo
                 ON examen_aula (codigo_acceso)
                 WHERE codigo_acceso IS NOT NULL'
            );
        }

        Schema::create('examen_aula_apoyo', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_aula_id')
                ->constrained('examen_aula')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->unique(
                ['examen_aula_id', 'user_id'],
                'uq_apoyo_aula_usuario'
            );
        });

        // El auxiliar es una cuenta con rol Auxiliar: aqui solo se guarda
        // de quien es y su codigo universitario.
        Schema::create('auxiliares', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->unique('uq_auxiliar_user')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo_universitario', 20)
                ->nullable();

            $table->foreignId('registrado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();
        });

        // Quien entro con el codigo de un aula.
        Schema::create('accesos_aula', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_aula_id')
                ->constrained('examen_aula')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('nombre', 120);

            $table->string('ip', 45)
                ->nullable();

            $table->timestamp('creado_en')
                ->useCurrent();

            $table->index('examen_aula_id', 'idx_acceso_examen_aula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_aula');
        Schema::dropIfExists('auxiliares');
        Schema::dropIfExists('examen_aula_apoyo');
        Schema::dropIfExists('examen_aula');
        Schema::dropIfExists('examen_grupo');
        Schema::dropIfExists('examenes');
    }
};
