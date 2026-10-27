<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La huella de los archivos fuente con que se importo: si la fuente no
 * cambio, volver a importar no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_oferta', function (Blueprint $table): void {
            $table->string('huella', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('importaciones_oferta', function (Blueprint $table): void {
            $table->dropColumn('huella');
        });
    }
};
