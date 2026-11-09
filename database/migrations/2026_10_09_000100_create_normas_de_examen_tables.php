<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las normas del examen que se marcan de plantillas. El texto libre sigue
 * en `examenes.normas`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Normas reutilizables. Sin cuenta = norma predefinida del sistema;
        // con cuenta = plantilla propia de quien registra examenes.
        Schema::create('plantillas_norma', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('texto', 300);

            $table->timestamps();

            $table->unique(
                ['user_id', 'texto'],
                'uq_plantilla_usuario_texto'
            );
        });

        // El unico anterior no alcanza a las predefinidas: en PostgreSQL
        // dos nulos no chocan.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX uq_plantilla_predefinida_texto
                 ON plantillas_norma (texto)
                 WHERE user_id IS NULL'
            );
        }

        // Las normas marcadas en un examen. El texto es una copia: el examen
        // conserva la norma aunque la plantilla se edite o se quite.
        Schema::create('examen_norma', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('plantilla_id')
                ->nullable()
                ->constrained('plantillas_norma')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('texto', 300);

            $table->smallInteger('orden');

            $table->unique(
                ['examen_id', 'plantilla_id'],
                'uq_examen_norma'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_norma');
        Schema::dropIfExists('plantillas_norma');
    }
};
