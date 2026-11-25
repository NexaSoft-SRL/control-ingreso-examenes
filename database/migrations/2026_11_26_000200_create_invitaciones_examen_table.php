<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un docente invita a otro a sumar su grupo a un examen: el grupo no entra
 * en `examen_grupo` hasta que el docente acepta. Mientras tanto la
 * invitacion queda `pendiente`; si la rechaza, `rechazada` (el creador la ve
 * y puede quitarla). Al aceptar, la fila se borra y el grupo pasa al examen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitaciones_examen', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('invitado_por')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // pendiente | rechazada
            $table->string('estado', 12)->default('pendiente');
            $table->timestampTz('respondida_en')->nullable();
            $table->timestampsTz();

            $table->unique(['examen_id', 'grupo_id'], 'uq_invitacion_examen_grupo');
            $table->index(['grupo_id', 'estado'], 'idx_invitacion_grupo_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitaciones_examen');
    }
};
