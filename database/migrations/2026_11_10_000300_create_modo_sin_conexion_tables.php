<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo sin conexion de la puerta: cada puesto que descarga una instantanea
 * del aula abre una sesion; lo que decide sin conexion llega despues como
 * eventos, y lo que parece irregular queda para que el docente lo revise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_sin_conexion', function (Blueprint $table): void {
            $table->id();

            // Lo genera el navegador del puesto y se guarda en el.
            $table->string('dispositivo', 64);

            $table->foreignId('aula_id')
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Nulo si el puesto es una cuenta; con codigo de aula, quien lo abrio.
            $table->foreignId('acceso_aula_id')
                ->nullable()
                ->constrained('accesos_aula')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('operador', 150)
                ->nullable();

            $table->timestamp('instantanea_en');

            // Los examenes del dia que cubria la instantanea.
            $table->json('examenes');

            $table->timestamp('sincronizada_en')
                ->nullable();

            $table->unsignedInteger('eventos')
                ->default(0);

            // DESCARGADA | SINCRONIZADA
            $table->string('estado', 12)
                ->default('DESCARGADA');

            $table->timestamps();

            $table->unique(['dispositivo', 'aula_id', 'instantanea_en'], 'uq_sesion_sin_conexion');

            $table->index(['aula_id', 'estado'], 'idx_sesion_sin_conexion_aula');
        });

        Schema::create('eventos_sin_conexion', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('sesion_id')
                ->constrained('sesiones_sin_conexion')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Lo genera el puesto: reenviar el lote no repite nada.
            $table->string('uid', 64)
                ->unique('uq_evento_sin_conexion_uid');

            $table->foreignId('examen_id')
                ->nullable()
                ->constrained('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('estudiantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // La hora en que ocurrio, segun el puesto ya corregida.
            $table->timestamp('hora');

            $table->string('medio', 12);

            // Lo que el puesto decidio: ADMITIDO, NO_HABILITADO, YA_INGRESO...
            $table->string('resultado', 20);

            $table->foreignId('ingreso_id')
                ->nullable()
                ->constrained('ingresos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('created_at')
                ->useCurrent();

            $table->index(['examen_id', 'estudiante_id'], 'idx_evento_sin_conexion_estudiante');
        });

        Schema::create('irregularidades_sin_conexion', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('sesion_id')
                ->nullable()
                ->constrained('sesiones_sin_conexion')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('tipo', 32);

            // ALTA | MEDIA | BAJA
            $table->string('gravedad', 6);

            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('estudiantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->text('detalle');

            $table->json('datos')
                ->nullable();

            // PENDIENTE | CONFIRMADA | DESCARTADA
            $table->string('estado', 10)
                ->default('PENDIENTE');

            $table->text('nota')
                ->nullable();

            $table->foreignId('resuelta_por')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('resuelta_en')
                ->nullable();

            $table->timestamps();

            $table->index(['examen_id', 'estado'], 'idx_irregularidad_examen_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irregularidades_sin_conexion');
        Schema::dropIfExists('eventos_sin_conexion');
        Schema::dropIfExists('sesiones_sin_conexion');
    }
};
