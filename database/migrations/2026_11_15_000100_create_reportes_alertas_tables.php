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
        Schema::create('preferencias_alertas_reportes', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('asistencia_minima')->default(70);
            $table->unsignedSmallInteger('incidencias_minimas')->default(5);
            $table->timestamps();
        });

        Schema::create('alertas_reportes_procesadas', function (Blueprint $table): void {
            $table->foreignId('examen_id')->primary()->constrained('examenes')->cascadeOnDelete();
            $table->timestamp('procesado_en');
        });

        Schema::create('alertas_reportes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->string('titulo', 160);
            $table->text('mensaje');
            $table->unsignedInteger('valor');
            $table->unsignedInteger('umbral');
            $table->timestamp('leida_en')->nullable();
            $table->string('correo_estado', 16)->default('PENDIENTE');
            $table->text('correo_error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'examen_id', 'tipo'], 'uq_alerta_reporte_docente_examen_tipo');
            $table->index(['user_id', 'created_at']);
        });

        $cerrados = DB::table('examenes')
            ->whereNotNull('finalizado_en')
            ->orWhereDate('fecha', '<', now()->toDateString())
            ->get(['id']);

        foreach ($cerrados as $examen) {
            DB::table('alertas_reportes_procesadas')->insert([
                'examen_id' => $examen->id,
                'procesado_en' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_reportes');
        Schema::dropIfExists('alertas_reportes_procesadas');
        Schema::dropIfExists('preferencias_alertas_reportes');
    }
};
