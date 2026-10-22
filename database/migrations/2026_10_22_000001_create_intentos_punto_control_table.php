<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Auditoria independiente del examen: no inventar un examen para
        // un identificador desconocido ni para un caso ambiguo.
        Schema::create('intentos_punto_control', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aula_id')->constrained('aulas')->restrictOnDelete();
            $table->foreignId('registrado_por')->constrained('usuarios')->restrictOnDelete();
            $table->string('tipo_identificador', 16);
            $table->char('identificador_sha256', 64);
            $table->string('causa', 24);
            $table->timestamp('registrado_en')->useCurrent();
            $table->index(['aula_id', 'registrado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_punto_control');
    }
};
