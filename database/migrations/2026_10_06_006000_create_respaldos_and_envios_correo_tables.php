<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de respaldos (antes eran solo archivos y un fallo no dejaba
 * rastro) y el registro de los correos que envia el sistema. Va al final
 * porque el registro de correos apunta a examenes y estudiantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respaldos', function (Blueprint $table): void {
            $table->id();

            // Nulo si la generacion fallo antes de escribir el archivo.
            $table->string('archivo', 120)
                ->nullable()
                ->unique('uq_respaldo_archivo');

            $table->string('tipo', 12);

            $table->string('estado', 10);

            $table->bigInteger('tamano_bytes')
                ->nullable();

            $table->integer('tablas')
                ->nullable();

            $table->integer('filas')
                ->nullable();

            $table->text('error')
                ->nullable();

            // Nulo = lo genero el planificador.
            $table->foreignId('generado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('restaurado_en')
                ->nullable();

            $table->foreignId('restaurado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('created_at')
                ->useCurrent();
        });

        Schema::create('envios_correo', function (Blueprint $table): void {
            $table->id();

            $table->string('tipo', 20);

            $table->string('destinatario', 150);

            // La cuenta destinataria, en credenciales y restablecimientos.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('examen_id')
                ->nullable()
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('estudiantes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('estado', 10);

            $table->text('error')
                ->nullable();

            $table->foreignId('solicitado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('enviado_en')
                ->nullable();

            $table->timestamps();

            $table->index(['examen_id', 'estado'], 'idx_envio_examen_estado');
        });

        // La pantalla de bitacora filtra por fecha y por operacion sobre
        // miles de filas.
        Schema::table('bitacora_operaciones', function (Blueprint $table): void {
            if (! Schema::hasIndex('bitacora_operaciones', 'idx_bitacora_fecha')) {
                $table->index('fecha_operacion', 'idx_bitacora_fecha');
            }

            if (! Schema::hasIndex('bitacora_operaciones', 'idx_bitacora_operacion')) {
                $table->index('operacion', 'idx_bitacora_operacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_operaciones', function (Blueprint $table): void {
            if (Schema::hasIndex('bitacora_operaciones', 'idx_bitacora_operacion')) {
                $table->dropIndex('idx_bitacora_operacion');
            }

            if (Schema::hasIndex('bitacora_operaciones', 'idx_bitacora_fecha')) {
                $table->dropIndex('idx_bitacora_fecha');
            }
        });

        Schema::dropIfExists('envios_correo');
        Schema::dropIfExists('respaldos');
    }
};
