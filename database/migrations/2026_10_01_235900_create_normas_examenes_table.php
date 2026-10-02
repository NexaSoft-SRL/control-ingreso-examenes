<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('normas_examenes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete();
            $table->string('alcance', 20);
            $table->text('texto');
            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('students')
                ->restrictOnDelete();
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->index(['examen_id', 'alcance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('normas_examenes');
    }
};
