<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ademas de las fotos, una incidencia puede llevar un video corto de
 * evidencia (hasta 60 segundos, grabado en la aplicacion o elegido del
 * dispositivo). Uno solo: no se reemplaza ni se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->string('video', 200)->nullable();
            $table->string('video_tipo', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->dropColumn(['video', 'video_tipo']);
        });
    }
};
