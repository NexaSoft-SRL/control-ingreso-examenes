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
        Schema::create('incidencias_ingreso', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->restrictOnDelete();

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete();

            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('estudiantes')
                ->nullOnDelete();

            $table->foreignId('registrado_por')
                ->constrained('usuarios')
                ->restrictOnDelete();

            $table->string('tipo_rechazo', 40);

            $table->text('detalle');

            $table->timestamp('registrado_en')
                ->useCurrent();

            $table->index(
                ['examen_id', 'registrado_en'],
                'idx_incidencias_ingreso_examen_fecha'
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE incidencias_ingreso
                 ADD CONSTRAINT chk_incidencia_tipo_rechazo
                 CHECK (
                     tipo_rechazo IN (
                         'NO_HABILITADO',
                         'OTRA_AULA',
                         'YA_INGRESO',
                         'NO_CORRESPONDE',
                         'EXPULSADO'
                     )
                 )"
            );

            DB::statement(
                'ALTER TABLE incidencias_ingreso
                 ADD CONSTRAINT chk_incidencia_detalle
                 CHECK (length(btrim(detalle)) > 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_ingreso');
    }
};
