<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-18: Atencion de las aulas del examen.
 *
 * Registra quién atiende cada aula de un examen sin modificar las columnas fijas de `examen_aula`:
 * - `examen_aula_personal`: vincula examen, aula y usuario con rol ('a_cargo' o 'apoyo').
 * - `examen_aula_codigos`: código de 6 dígitos propio de esa aula y examen con fecha de generación.
 * - `docente_auxiliares`: auxiliares agregados a la lista del docente.
 * - `accesos_codigo_aula`: sesiones de colaboradores que entran con código de aula sin cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Auxiliares del docente (lista propia de cada docente)
        Schema::create('docente_auxiliares', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('docente_id')
                ->constrained('usuarios')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('auxiliar_id')
                ->constrained('usuarios')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique(['docente_id', 'auxiliar_id'], 'uq_docente_auxiliar');
            $table->index('auxiliar_id', 'idx_docente_auxiliares_auxiliar');
        });

        // 2. Personal por aula del examen ('a_cargo' o 'apoyo')
        Schema::create('examen_aula_personal', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('rol', 20); // 'a_cargo' | 'apoyo'

            $table->timestamps();

            $table->unique(
                ['examen_id', 'aula_id', 'usuario_id'],
                'uq_examen_aula_personal_usuario',
            );

            $table->index(
                ['examen_id', 'aula_id', 'rol'],
                'idx_examen_aula_personal_rol',
            );
        });

        // 3. Códigos de acceso de 6 dígitos por aula y examen
        Schema::create('examen_aula_codigos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo', 6);
            $table->timestamp('generado_en')->useCurrent();

            $table->timestamps();

            $table->unique(
                ['examen_id', 'aula_id'],
                'uq_examen_aula_codigo_aula',
            );

            $table->index(
                ['codigo'],
                'idx_examen_aula_codigos_codigo',
            );
        });

        // 4. Sesiones/accesos abiertos mediante código de aula (sin cuenta)
        Schema::create('accesos_codigo_aula', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo', 6);
            $table->string('nombre_colaborador', 120);
            $table->string('token', 64)->unique('uq_accesos_codigo_token');
            $table->timestamp('ultimo_acceso_en')->useCurrent();
            $table->timestamp('revocado_en')->nullable();
            $table->timestamps();

            $table->index(
                ['examen_id', 'aula_id', 'codigo'],
                'idx_accesos_codigo_aula_lookup',
            );
        });

        // 5. Vincular accesos con código a los ingresos registrados
        Schema::table('ingresos', function (Blueprint $table): void {
            $table->foreignId('acceso_codigo_aula_id')
                ->nullable()
                ->constrained('accesos_codigo_aula')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table): void {
            $table->dropForeign(['acceso_codigo_aula_id']);
            $table->dropColumn('acceso_codigo_aula_id');
        });

        Schema::dropIfExists('accesos_codigo_aula');
        Schema::dropIfExists('examen_aula_codigos');
        Schema::dropIfExists('examen_aula_personal');
        Schema::dropIfExists('docente_auxiliares');
    }
};
