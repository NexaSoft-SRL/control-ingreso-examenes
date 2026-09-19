<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carreras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('facultad_id')
                ->constrained('facultades')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('nombre', 150);
            $table->string('codigo', 20)->unique();

            $table->boolean('estado')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carreras');
    }
};
