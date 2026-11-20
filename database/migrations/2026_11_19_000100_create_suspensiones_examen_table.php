<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una suspensión registrada por el docente durante el examen.
 * Mientras exista una suspensión sin reanudar, la puerta deja
 * de admitir estudiantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspensiones_examen', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('motivo', 100);

            $table->text('descripcion')
                ->nullable();

            $table->timestamp('suspendido_en');

            $table->timestamp('reanudado_en')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['examen_id', 'reanudado_en'],
                'idx_suspension_examen_abierta'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspensiones_examen');
    }
};
