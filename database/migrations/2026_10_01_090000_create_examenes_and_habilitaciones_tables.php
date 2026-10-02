<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        Schema::create('habilitaciones_examen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('estudiante_id')
                ->constrained('students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('estado', 20)->default('NO_HABILITADO');
            $table->text('motivo')->nullable();
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('fecha_habilitacion')->useCurrent();
            $table->timestamps();
            $table->unique(['examen_id', 'estudiante_id'], 'uq_habilitacion_examen_estudiante');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitaciones_examen');
        Schema::dropIfExists('examenes');
    }
};
