<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-25: registro de cada respaldo generado (programado diario o puntual a demanda)
 * y su estado para el historial de restauraciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respaldos', function (Blueprint $table): void {
            $table->id();
            $table->string('archivo')->unique();
            $table->string('tipo');
            $table->unsignedBigInteger('tamano_bytes')->default(0);
            $table->string('estado')->default('correcto');
            $table->text('error')->nullable();
            $table->string('version_esquema')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index('tipo');
            $table->index('estado');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respaldos');
    }
};
