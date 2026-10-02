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
        Schema::create('examenes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grupo_id')
                ->constrained('grupos_asignatura')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('nombre', 150);
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->unsignedInteger('duracion_minutos');
            $table->timestamps();
        });

        DB::statement(
            <<<'SQL'
            ALTER TABLE examenes
            ADD CONSTRAINT chk_examen_duracion_positiva
            CHECK (duracion_minutos > 0)
            SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('examenes');
    }
};
