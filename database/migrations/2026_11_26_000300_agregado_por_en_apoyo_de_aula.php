<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quien sumo a cada persona de apoyo: cada docente del examen suma y quita a
 * sus propios auxiliares. Nulo en lo que se cargo antes de esta columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examen_aula_apoyo', function (Blueprint $table): void {
            $table->foreignId('agregado_por')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('examen_aula_apoyo', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agregado_por');
        });
    }
};
