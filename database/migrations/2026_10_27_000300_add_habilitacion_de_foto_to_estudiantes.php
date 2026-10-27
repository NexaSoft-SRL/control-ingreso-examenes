<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un docente puede habilitar a un estudiante de su grupo para que suba o
 * cambie su foto fuera del plazo general, hasta cierto dia. La foto la
 * sigue subiendo el estudiante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table): void {
            $table->date('foto_habilitada_hasta')
                ->nullable();

            $table->foreignId('foto_habilitada_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('foto_habilitada_por');
            $table->dropColumn('foto_habilitada_hasta');
        });
    }
};
