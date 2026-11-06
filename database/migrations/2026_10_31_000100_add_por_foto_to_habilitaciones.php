<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vencido el plazo de fotos del grupo (`plazos_foto_grupo`), quien no subio
 * su foto queda inhabilitado en los examenes del grupo. Esa inhabilitacion
 * la pone el sistema (`por_foto`) y la quita cuando el estudiante sube la
 * foto o el docente mueve el plazo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habilitaciones', function (Blueprint $table): void {
            $table->boolean('por_foto')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('habilitaciones', function (Blueprint $table): void {
            $table->dropColumn('por_foto');
        });
    }
};
