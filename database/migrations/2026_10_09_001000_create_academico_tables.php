<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La oferta academica se importa, no se teclea: periodos, facultades,
 * carreras, asignaturas, docentes, edificios, aulas, grupos y horarios.
 * Las aulas no tienen capacidad ni estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos', function (Blueprint $table): void {
            $table->id();

            $table->string('codigo', 10)
                ->unique('uq_periodo_codigo');

            $table->smallInteger('anio');

            // 0 anual, 1, 2, 3 verano, 4 invierno.
            $table->smallInteger('numero');

            $table->string('tipo', 12);

            // Sin fechas, el periodo queda a la espera de un ajuste a mano.
            $table->date('fecha_inicio')
                ->nullable();

            $table->date('fecha_fin')
                ->nullable();

            $table->json('ventanas')
                ->nullable();

            $table->string('fuente', 120)
                ->nullable();

            $table->foreignId('ajustado_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique(['anio', 'numero'], 'uq_periodo_anio_numero');
        });

        Schema::create('facultades', function (Blueprint $table): void {
            $table->id();

            $table->string('clave', 10)
                ->unique('uq_facultad_clave');

            $table->string('sigla', 10)
                ->unique('uq_facultad_sigla');

            $table->string('nombre', 120);

            $table->string('codigo_umss', 4);

            $table->char('color', 7);

            $table->smallInteger('orden');
        });

        Schema::create('carreras', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('facultad_id')
                ->constrained('facultades')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo', 10)
                ->unique('uq_carrera_codigo');

            $table->string('nombre', 150);

            $table->string('regimen', 10);

            $table->timestamps();
        });

        Schema::create('asignaturas', function (Blueprint $table): void {
            $table->id();

            $table->string('codigo', 30)
                ->unique('uq_asignatura_codigo');

            $table->string('nombre', 150);

            $table->timestamps();
        });

        // El nivel de una asignatura depende de la carrera que la dicta.
        Schema::create('plan_estudios', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('carrera_id')
                ->constrained('carreras')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('asignatura_id')
                ->constrained('asignaturas')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('nivel', 30);

            $table->unique(
                ['carrera_id', 'asignatura_id'],
                'uq_plan_carrera_asignatura'
            );
        });

        Schema::create('docentes', function (Blueprint $table): void {
            $table->id();

            // Nulo = el docente todavia no tiene cuenta.
            $table->foreignId('user_id')
                ->nullable()
                ->unique('uq_docente_user')
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('nombre_completo', 150);

            // La identidad al reimportar: la fuente no trae otra.
            $table->string('nombre_normalizado', 150)
                ->unique('uq_docente_nombre_normalizado');

            $table->timestamps();
        });

        Schema::create('edificios', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('facultad_id')
                ->constrained('facultades')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('clave', 40)
                ->unique('uq_edificio_clave');

            $table->string('nombre', 150);

            $table->json('poligono');

            $table->decimal('centro_lon', 10, 7);

            $table->decimal('centro_lat', 10, 7);

            $table->timestamps();
        });

        Schema::create('aulas', function (Blueprint $table): void {
            $table->id();

            $table->string('nombre', 30)
                ->unique('uq_aula_nombre');

            // Nulo = aula de la oferta que no se pudo ubicar en el mapa.
            $table->foreignId('edificio_id')
                ->nullable()
                ->constrained('edificios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('facultad_id')
                ->nullable()
                ->constrained('facultades')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('piso', 40)
                ->nullable();

            $table->timestamps();
        });

        Schema::create('grupos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('asignatura_id')
                ->constrained('asignaturas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('facultad_id')
                ->constrained('facultades')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Nulo = «Por designar».
            $table->foreignId('docente_id')
                ->nullable()
                ->constrained('docentes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo', 20);

            $table->timestamps();

            $table->unique(
                ['periodo_id', 'asignatura_id', 'facultad_id', 'codigo'],
                'uq_grupo_periodo_asignatura_facultad_codigo'
            );

            $table->index(
                ['docente_id', 'periodo_id'],
                'idx_grupo_docente_periodo'
            );

            $table->index(
                ['facultad_id', 'periodo_id'],
                'idx_grupo_facultad_periodo'
            );
        });

        // Sin marcas de tiempo: se borran y recrean en cada importacion.
        Schema::create('horarios', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->char('dia', 2);

            $table->time('hora_inicio');

            $table->time('hora_fin');

            $table->boolean('es_auxiliatura')
                ->default(false);

            $table->index('grupo_id', 'idx_horario_grupo');

            $table->index(['aula_id', 'dia'], 'idx_horario_aula_dia');
        });

        // El estado de una facultad es el de su ultima fila.
        Schema::create('importaciones_oferta', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('facultad_id')
                ->constrained('facultades')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('estado', 12);

            $table->string('periodo_codigo', 10)
                ->nullable();

            $table->date('fecha_fuente')
                ->nullable();

            $table->json('resumen')
                ->nullable();

            $table->text('error')
                ->nullable();

            $table->foreignId('ejecutada_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('iniciada_en')
                ->useCurrent();

            $table->timestamp('terminada_en')
                ->nullable();

            $table->index(['facultad_id', 'id'], 'idx_importacion_facultad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_oferta');
        Schema::dropIfExists('horarios');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('aulas');
        Schema::dropIfExists('edificios');
        Schema::dropIfExists('docentes');
        Schema::dropIfExists('plan_estudios');
        Schema::dropIfExists('asignaturas');
        Schema::dropIfExists('carreras');
        Schema::dropIfExists('facultades');
        Schema::dropIfExists('periodos');
    }
};
