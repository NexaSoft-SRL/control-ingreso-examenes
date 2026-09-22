<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaturas', function (Blueprint $table): void {
            $table->id();

            $table->string('codigo', 30)
                ->unique();

            $table->string('nombre', 150);

            $table->string('semestre', 20)
                ->nullable();

            $table->text('descripcion')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaturas');
    }
};
