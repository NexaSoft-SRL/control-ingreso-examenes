<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes que la administracion cambia desde la pantalla (hoy, el respaldo
 * automatico). Mandan sobre lo que diga el .env: sin fila, vale el .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table): void {
            $table->string('clave', 60)->primary();
            $table->string('valor', 255);
            $table->timestampTz('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
