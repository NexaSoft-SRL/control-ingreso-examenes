<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El padron nacio con nombre, apellido, ci y correo, pero HU-03 y HU-04
 * piden cinco datos: codigo universitario, documento de identidad, nombres,
 * apellidos y carrera. Se agregan los dos que faltaban.
 *
 * Quedan opcionales para no invalidar los estudiantes ya cargados; la carga
 * masiva si los exige. El correo pasa a ser opcional porque el pliego no lo
 * pide para identificar al estudiante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('codigo_universitario', 20)
                ->nullable()
                ->unique()
                ->after('id');

            $table->string('carrera', 120)
                ->nullable()
                ->after('apellido');

            $table->string('correo', 150)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['codigo_universitario']);
            $table->dropColumn(['codigo_universitario', 'carrera']);
        });
    }
};
