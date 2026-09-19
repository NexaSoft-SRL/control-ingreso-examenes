<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('carrera_id')
                ->constrained('carreras')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo_sis', 30)->unique();
            $table->string('ci', 30)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('correo', 150)->nullable()->unique();
            $table->string('telefono', 30)->nullable();
            $table->string('estado', 20)->default('ACTIVO');

            $table->timestamps();

            $table->index('codigo_sis');
            $table->index('ci');
            $table->index('estado');
            $table->index(['nombres', 'apellidos']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE estudiantes
                 ADD CONSTRAINT estudiantes_estado_valid
                 CHECK (estado IN ('ACTIVO', 'INACTIVO'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE estudiantes
                 DROP CONSTRAINT IF EXISTS estudiantes_estado_valid'
            );
        }

        Schema::dropIfExists('estudiantes');
    }
};
