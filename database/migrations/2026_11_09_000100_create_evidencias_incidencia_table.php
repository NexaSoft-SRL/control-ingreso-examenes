<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una incidencia lleva hasta tres fotos como evidencia (antes, una sola en
 * `incidencias.evidencia`). Las que ya habia pasan como la primera de su
 * incidencia. Las fotos no se reemplazan ni se borran: se agregan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidencias_incidencia', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('incidencia_id')
                ->constrained('incidencias')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('ruta', 200);

            // 1, 2 o 3, en el orden en que se adjuntaron.
            $table->unsignedSmallInteger('orden');

            $table->foreignId('subida_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('created_at')
                ->useCurrent();

            $table->unique(['incidencia_id', 'orden'], 'uq_evidencia_por_orden');
        });

        DB::statement(
            'insert into evidencias_incidencia (incidencia_id, ruta, orden, subida_por, created_at)'
            .' select id, evidencia, 1, coalesce(corregida_por, reportada_por), coalesce(corregida_en, ocurrida_en)'
            ." from incidencias where evidencia is not null and evidencia <> ''"
        );

        Schema::table('incidencias', function (Blueprint $table): void {
            $table->dropColumn('evidencia');
        });
    }

    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->string('evidencia', 200)
                ->nullable();
        });

        DB::statement(
            'update incidencias set evidencia = evidencia_incidencia.ruta'
            .' from evidencias_incidencia as evidencia_incidencia'
            .' where evidencia_incidencia.incidencia_id = incidencias.id and evidencia_incidencia.orden = 1'
        );

        Schema::dropIfExists('evidencias_incidencia');
    }
};
