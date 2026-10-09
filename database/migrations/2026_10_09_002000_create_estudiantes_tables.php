<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El padron deja de editarse a mano: se llena con cargas de inscritos y se
 * corrige resolviendo conflictos. El correo del estudiante no se guarda:
 * es su codigo universitario mas el dominio institucional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiantes', function (Blueprint $table): void {
            $table->id();

            $table->string('codigo_universitario', 20)
                ->unique('uq_estudiante_codigo');

            $table->string('documento_identidad', 30)
                ->nullable();

            $table->string('nombres', 100);

            $table->string('apellidos', 100);

            $table->foreignId('carrera_id')
                ->nullable()
                ->constrained('carreras')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('facultad_id')
                ->nullable()
                ->constrained('facultades')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Quien lo creo: una carga de administracion o la de un docente.
            $table->string('origen', 15);

            $table->boolean('verificado')
                ->default(false);

            $table->boolean('activo')
                ->default(true);

            $table->timestamps();

            $table->index(['apellidos', 'nombres'], 'idx_estudiante_apellidos');
        });

        // Las cargas de docentes pueden no traer documento: el unico solo
        // alcanza a quienes lo tienen.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX uq_estudiante_documento
                 ON estudiantes (documento_identidad)
                 WHERE documento_identidad IS NOT NULL'
            );
        }

        // Una fila por archivo subido.
        Schema::create('cargas_inscritos', function (Blueprint $table): void {
            $table->id();

            $table->string('alcance', 10);

            $table->foreignId('grupo_id')
                ->nullable()
                ->constrained('grupos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('facultad_id')
                ->nullable()
                ->constrained('facultades')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('archivo', 200);

            $table->integer('filas')
                ->default(0);

            $table->integer('nuevos')
                ->default(0);

            $table->integer('reutilizados')
                ->default(0);

            $table->integer('ya_inscritos')
                ->default(0);

            $table->integer('rechazados')
                ->default(0);

            $table->integer('conflictos')
                ->default(0);

            $table->json('rechazos')
                ->nullable();

            $table->foreignId('cargada_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('created_at')
                ->useCurrent();
        });

        Schema::create('inscripciones', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('via', 15);

            $table->foreignId('cargada_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('carga_id')
                ->nullable()
                ->constrained('cargas_inscritos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique(
                ['estudiante_id', 'grupo_id'],
                'uq_inscripcion_estudiante_grupo'
            );

            $table->index('grupo_id', 'idx_inscripcion_grupo');
        });

        // La carga trajo a un estudiante que ya existe con otro documento
        // u otro nombre: la inscripcion queda en espera hasta resolverlo.
        Schema::create('conflictos_padron', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('grupo_id')
                ->nullable()
                ->constrained('grupos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('carga_id')
                ->nullable()
                ->constrained('cargas_inscritos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->integer('fila')
                ->nullable();

            $table->string('tipo', 20);

            $table->string('documento_nuevo', 30)
                ->nullable();

            $table->string('nombres_nuevos', 100);

            $table->string('apellidos_nuevos', 100);

            $table->string('via', 15);

            $table->string('estado', 10);

            $table->string('resolucion', 16)
                ->nullable();

            $table->foreignId('reportado_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('resuelto_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('resuelto_en')
                ->nullable();

            $table->timestamps();

            $table->index('estado', 'idx_conflicto_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflictos_padron');
        Schema::dropIfExists('inscripciones');
        Schema::dropIfExists('cargas_inscritos');
        Schema::dropIfExists('estudiantes');
    }
};
