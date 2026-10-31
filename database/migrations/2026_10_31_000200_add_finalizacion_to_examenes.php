<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El docente finaliza el examen: la duracion es la prevista, pero un examen
 * puede pasarse. Sin finalizar, el examen se cierra solo al terminar su dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examenes', function (Blueprint $table): void {
            $table->timestamp('finalizado_en')->nullable();
            $table->foreignId('finalizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('examenes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('finalizado_por');
            $table->dropColumn('finalizado_en');
        });
    }
};
