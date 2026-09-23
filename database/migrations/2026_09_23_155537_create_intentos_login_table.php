<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intentos_login', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('usuario_id')
                ->nullable();

            $table->timestamp('fecha_intento')
                ->useCurrent();

            $table->boolean('exitoso')
                ->default(false);

            $table->string('ip_origen', 45)
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_login');
    }
};