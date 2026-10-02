<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_ambiente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('ambiente_id')
                ->constrained('ambientes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('estudiante_id')
                ->constrained('students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->timestamps();
            $table->unique(['examen_id', 'estudiante_id'], 'uq_asignacion_ambiente_examen_estudiante');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_ambiente');
    }
};
