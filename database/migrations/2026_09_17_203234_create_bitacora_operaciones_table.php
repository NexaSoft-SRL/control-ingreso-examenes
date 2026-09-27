<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bitacora_operaciones', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('operacion', 100);

            $table->string('tabla_afectada', 100)
                ->nullable();

            $table->bigInteger('registro_id')
                ->nullable();

            $table->text('descripcion')
                ->nullable();

            $table->timestamp('fecha_operacion')
                ->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacora_operaciones');
    }
};
