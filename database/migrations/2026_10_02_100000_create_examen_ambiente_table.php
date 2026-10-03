<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examen_ambiente', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('examen_id')
                ->constrained('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('ambiente_id')
                ->constrained('ambientes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique(['examen_id', 'ambiente_id'], 'uq_examen_ambiente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_ambiente');
    }
};
