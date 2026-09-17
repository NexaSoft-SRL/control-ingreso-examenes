<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_asignatura', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('asignatura_id')
                ->constrained('asignaturas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('docente_id')
                ->constrained('docentes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo_grupo', 20);

            $table->integer('cupo')
                ->default(0);

            $table->timestamps();

            $table->unique(
                ['asignatura_id', 'codigo_grupo'],
                'uq_grupo_asignatura_codigo'
            );
        });

        DB::statement(
            <<<'SQL'
            ALTER TABLE grupos_asignatura
            ADD CONSTRAINT chk_grupos_asignatura_cupo
            CHECK (cupo >= 0)
            SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_asignatura');
    }
};
