<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El docente corrige una incidencia (tipo y detalle) y le adjunta una
 * foto como evidencia. La correccion deja quien y cuando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->string('evidencia', 200)
                ->nullable();

            $table->timestamp('corregida_en')
                ->nullable();

            $table->foreignId('corregida_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('corregida_por');
            $table->dropColumn(['evidencia', 'corregida_en']);
        });
    }
};
