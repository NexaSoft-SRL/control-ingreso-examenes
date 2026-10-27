<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-22: quien esta en cada puerta y cuando la uso por ultima vez. La puerta
 * la marca al usarla una cuenta; el docente lo ve en «Quién controla».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presencias_puerta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aula_id')->constrained('aulas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamp('visto_en');
            $table->unique(['aula_id', 'usuario_id'], 'uq_presencia_aula_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presencias_puerta');
    }
};
