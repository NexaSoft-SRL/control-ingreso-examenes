<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-20: Codigos QR y avisos por correo.
 *
 * - `codigos_qr`: el codigo de un habilitado para un examen. `contenido` es
 *   lo que lleva el QR, en el formato que lee la puerta (HU-19): cifrado,
 *   sin datos legibles. Se guarda para mostrar y reenviar el mismo codigo.
 * - `envios_correo`: cada correo que sale del sistema y como termino.
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

            $table->text('contenido');

            $table->timestamp('emitido_en');

            $table->foreignId('emitido_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // La ultima vez que el estudiante lo recibio por correo.
            $table->timestamp('enviado_en')
                ->nullable();

            $table->timestamps();

            $table->unique(
                ['examen_id', 'estudiante_id'],
                'uq_codigo_qr_examen_estudiante'
            );

            $table->unique('contenido', 'uq_codigo_qr_contenido');

            $table->index('estudiante_id', 'idx_codigo_qr_estudiante');
        });

        Schema::create('envios_correo', function (Blueprint $table): void {
            $table->id();

            $table->string('tipo', 20);

            $table->string('destinatario', 150);

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
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('enviado_en')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['examen_id', 'estudiante_id', 'tipo'],
                'idx_envio_correo_examen_estudiante'
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE envios_correo
                 ADD CONSTRAINT chk_envio_correo_estado
                 CHECK (estado IN ('PENDIENTE', 'ENVIADO', 'FALLIDO'))"
            );

            DB::statement(
                "ALTER TABLE envios_correo
                 ADD CONSTRAINT chk_envio_correo_tipo
                 CHECK (tipo IN ('CODIGO_QR', 'AVISO'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_correo');
        Schema::dropIfExists('codigos_qr');
    }
};
