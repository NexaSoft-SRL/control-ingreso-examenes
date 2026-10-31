<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plazos_foto_grupo', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('grupo_id')
                ->unique()
                ->constrained('grupos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->date('desde');
            $table->date('hasta');

            $table->foreignId('fijado_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plazos_foto_grupo');
    }
};
