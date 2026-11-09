<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un codigo reenviado deja al anterior marcado, no borrado: asi se puede
 * decir «use el ultimo» a quien escribe el del primer correo, sin gastarle
 * un intento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codigos_acceso_estudiante', function (Blueprint $table): void {
            $table->timestamp('reemplazado_en')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('codigos_acceso_estudiante', function (Blueprint $table): void {
            $table->dropColumn('reemplazado_en');
        });
    }
};
