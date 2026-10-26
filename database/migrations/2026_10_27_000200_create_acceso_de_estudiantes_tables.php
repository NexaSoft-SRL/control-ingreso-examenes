<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El estudiante entra sin cuenta: pide un codigo de seis digitos que le
 * llega a su correo institucional. Ve sus examenes y sube su foto una sola
 * vez, dentro del plazo que fija la administracion. Un codigo reenviado
 * deja al anterior marcado (`reemplazado_en`), no borrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table): void {
            $table->string('foto', 200)
                ->nullable();

            $table->timestamp('foto_subida_en')
                ->nullable();
        });

        Schema::create('codigos_acceso_estudiante', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // SHA-256 del codigo: el codigo en claro solo viaja en el correo.
            $table->string('codigo_hash', 64);

            $table->timestamp('expira_en');

            $table->timestamp('usado_en')
                ->nullable();

            $table->timestamp('reemplazado_en')
                ->nullable();

            $table->unsignedSmallInteger('intentos')
                ->default(0);

            $table->timestamp('created_at');

            $table->index(['estudiante_id', 'expira_en'], 'idx_codigo_estudiante_vigencia');
        });

        Schema::create('plazos_foto', function (Blueprint $table): void {
            $table->id();

            $table->date('desde');

            $table->date('hasta');

            $table->foreignId('fijado_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plazos_foto');
        Schema::dropIfExists('codigos_acceso_estudiante');

        Schema::table('estudiantes', function (Blueprint $table): void {
            $table->dropColumn(['foto', 'foto_subida_en']);
        });
    }
};
