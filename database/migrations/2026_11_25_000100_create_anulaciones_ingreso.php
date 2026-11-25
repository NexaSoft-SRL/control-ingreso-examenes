<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La anulación de un ingreso (requisito 29): el ingreso sigue siendo
 * inmutable y una corrección se asienta aparte, con su motivo, sin tocar la
 * fila original.
 *
 * - `anulaciones_ingreso`: una por ingreso, también inmutable.
 * - `ingresos.intento`: un estudiante anulado puede volver a pasar por la
 *   puerta y su nuevo ingreso es el intento siguiente; el único pasa de
 *   (examen, estudiante) a (examen, estudiante, intento).
 * - `ingresos_vigentes`: los ingresos sin anulación. Todo lo que cuenta
 *   quién entró (puerta, lista, reportes, seguimiento) lee esta vista; el
 *   historial completo sigue en `ingresos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anulaciones_ingreso', function (Blueprint $table): void {
            $table->id();

            // Sin cascada: el ingreso es inmutable y su anulación también.
            $table->foreignId('ingreso_id')
                ->constrained('ingresos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('motivo', 500);

            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('anulado_en');

            $table->unique('ingreso_id', 'uq_anulacion_ingreso');
        });

        Schema::table('ingresos', function (Blueprint $table): void {
            $table->unsignedSmallInteger('intento')
                ->default(1);
        });

        Schema::table('ingresos', function (Blueprint $table): void {
            $table->dropUnique('uq_ingreso_examen_estudiante');
        });

        Schema::table('ingresos', function (Blueprint $table): void {
            $table->unique(
                ['examen_id', 'estudiante_id', 'intento'],
                'uq_ingreso_examen_estudiante_intento'
            );
        });

        DB::statement(
            'CREATE VIEW ingresos_vigentes AS
             SELECT ingreso.*
             FROM ingresos AS ingreso
             WHERE NOT EXISTS (
                 SELECT 1 FROM anulaciones_ingreso AS anulacion
                 WHERE anulacion.ingreso_id = ingreso.id
             )'
        );

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                CREATE OR REPLACE FUNCTION fn_anulaciones_ingreso_inmutables()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'La anulación de un ingreso es inmutable: no admite % (trg_anulaciones_ingreso_inmutables).', TG_OP;
                END;
                $$ LANGUAGE plpgsql
                SQL
            );

            // De fila: el TRUNCATE de una restauracion no lo dispara.
            DB::statement(
                'CREATE TRIGGER trg_anulaciones_ingreso_inmutables
                 BEFORE UPDATE OR DELETE ON anulaciones_ingreso
                 FOR EACH ROW EXECUTE FUNCTION fn_anulaciones_ingreso_inmutables()'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ingresos_vigentes');
        DB::statement('DROP TRIGGER IF EXISTS trg_anulaciones_ingreso_inmutables ON anulaciones_ingreso');
        DB::statement('DROP FUNCTION IF EXISTS fn_anulaciones_ingreso_inmutables()');

        Schema::dropIfExists('anulaciones_ingreso');

        Schema::table('ingresos', function (Blueprint $table): void {
            $table->dropUnique('uq_ingreso_examen_estudiante_intento');
        });

        Schema::table('ingresos', function (Blueprint $table): void {
            $table->dropColumn('intento');
        });

        Schema::table('ingresos', function (Blueprint $table): void {
            $table->unique(['examen_id', 'estudiante_id'], 'uq_ingreso_examen_estudiante');
        });
    }
};
