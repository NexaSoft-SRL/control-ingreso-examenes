<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La puerta: codigos QR, ingresos e incidencias. El ingreso es unico por
 * estudiante y examen e inmutable; una correccion se asienta como
 * incidencia. Expulsiones y situaciones excepcionales son incidencias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_qr', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Opaco: no lleva datos del estudiante ni del examen.
            $table->string('token', 64)
                ->unique('uq_codigo_qr_token');

            $table->timestamp('emitido_en');

            $table->foreignId('emitido_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('enviado_en')
                ->nullable();

            $table->timestamps();

            $table->unique(
                ['examen_id', 'estudiante_id'],
                'uq_codigo_qr_examen_estudiante'
            );
        });

        // Ninguna clave foranea de esta tabla anula ni borra en cascada:
        // lo impediria el disparador de inmutabilidad.
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

            // La puerta por la que entro.
            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('medio', 12);

            // Entro por un cambio de aula autorizado.
            $table->boolean('es_excepcion')
                ->default(false);

            $table->timestamp('registrado_en');

            // Nulo solo si lo registro una sesion con codigo de aula.
            $table->foreignId('registrado_por')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('acceso_aula_id')
                ->nullable()
                ->constrained('accesos_aula')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('created_at')
                ->useCurrent();

            // Total, no parcial: un ingreso por estudiante y examen,
            // tambien cuando dos puestos lo registran a la vez.
            $table->unique(
                ['examen_id', 'estudiante_id'],
                'uq_ingreso_examen_estudiante'
            );

            $table->index(['examen_id', 'aula_id'], 'idx_ingreso_examen_aula');

            $table->index(['aula_id', 'registrado_en'], 'idx_ingreso_aula_hora');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                CREATE OR REPLACE FUNCTION fn_ingresos_inmutables()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'El ingreso es inmutable: no admite % (trg_ingresos_inmutables).', TG_OP;
                END;
                $$ LANGUAGE plpgsql
                SQL
            );

            // De fila: el TRUNCATE de una restauracion no lo dispara.
            DB::statement(
                'CREATE TRIGGER trg_ingresos_inmutables
                 BEFORE UPDATE OR DELETE ON ingresos
                 FOR EACH ROW EXECUTE FUNCTION fn_ingresos_inmutables()'
            );
        }

        Schema::create('incidencias', function (Blueprint $table): void {
            $table->id();

            // Nulo solo en intentos que el sistema no pudo atribuir a un
            // examen del aula.
            $table->foreignId('examen_id')
                ->nullable()
                ->constrained('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('estudiantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Lo que tecleo quien reporta.
            $table->string('estudiante_texto', 150)
                ->nullable();

            $table->foreignId('ingreso_id')
                ->nullable()
                ->constrained('ingresos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('tipo', 30);

            $table->string('origen', 8);

            // Solo en las del sistema.
            $table->string('causa', 20)
                ->nullable();

            $table->foreignId('examen_correspondiente_id')
                ->nullable()
                ->constrained('examenes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->text('detalle');

            $table->timestamp('ocurrida_en');

            $table->foreignId('reportada_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('acceso_aula_id')
                ->nullable()
                ->constrained('accesos_aula')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('atendida_en')
                ->nullable();

            $table->foreignId('atendida_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->index(
                ['examen_id', 'atendida_en'],
                'idx_incidencia_examen_atendida'
            );

            $table->index(
                ['examen_id', 'aula_id'],
                'idx_incidencia_examen_aula'
            );

            $table->index(
                ['aula_id', 'ocurrida_en'],
                'idx_incidencia_aula_hora'
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Un ingreso se expulsa una sola vez.
            DB::statement(
                "CREATE UNIQUE INDEX uq_expulsion_por_ingreso
                 ON incidencias (ingreso_id)
                 WHERE tipo = 'EXPULSION'"
            );

            // Expulsar exige haber entrado.
            DB::statement(
                "ALTER TABLE incidencias
                 ADD CONSTRAINT chk_incidencia_expulsion_con_ingreso
                 CHECK (tipo <> 'EXPULSION' OR ingreso_id IS NOT NULL)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
        Schema::dropIfExists('ingresos');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS fn_ingresos_inmutables()');
        }

        Schema::dropIfExists('codigos_qr');
    }
};
