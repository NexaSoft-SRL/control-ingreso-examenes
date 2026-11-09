<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un acceso con codigo de aula espera a que el docente lo autorice en
 * persona; el docente puede moverlo de aula o cortarlo. Ademas, quien
 * esta en cada puerta y cuando la uso por ultima vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accesos_aula', function (Blueprint $table): void {
            // PENDIENTE, AUTORIZADO, RECHAZADO o CORTADO.
            $table->string('estado', 12)->default('PENDIENTE');
            $table->foreignId('resuelto_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('resuelto_en')->nullable();
            $table->timestamp('ultima_actividad_en')->nullable();
        });

        Schema::create('presencias_puerta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aula_id')->constrained('aulas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamp('visto_en');
            $table->unique(['aula_id', 'user_id'], 'uq_presencia_aula_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presencias_puerta');

        Schema::table('accesos_aula', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resuelto_por');
            $table->dropColumn(['estado', 'resuelto_en', 'ultima_actividad_en']);
        });
    }
};
