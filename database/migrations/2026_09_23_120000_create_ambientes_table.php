<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('ubicacion', 200)->nullable();
            $table->integer('capacidad');
            $table->string('estado', 20)->default('DISPONIBLE');
            $table->timestamps();

            $table->index('estado');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE ambientes
                 ADD CONSTRAINT ambientes_capacidad_positiva
                 CHECK (capacidad > 0)'
            );

            DB::statement(
                "ALTER TABLE ambientes
                 ADD CONSTRAINT ambientes_estado_valid
                 CHECK (estado IN ('DISPONIBLE', 'MANTENIMIENTO', 'OCUPADO'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ambientes DROP CONSTRAINT IF EXISTS ambientes_capacidad_positiva');
            DB::statement('ALTER TABLE ambientes DROP CONSTRAINT IF EXISTS ambientes_estado_valid');
        }

        Schema::dropIfExists('ambientes');
    }
};
