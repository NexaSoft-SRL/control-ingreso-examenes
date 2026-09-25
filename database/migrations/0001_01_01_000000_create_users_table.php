<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Tabla principal del sistema
        |--------------------------------------------------------------------------
        */
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();

            // Compatibilidad con Laravel/tests
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();

            // Campos del dominio del sistema
            $table->string('nombre')->nullable();
            $table->string('correo')->unique()->nullable();

            $table->timestamp('email_verified_at')->nullable();

            $table->string('password');

            $table->boolean('is_active')
                ->default(true);

            $table->integer('failed_login_attempts')
                ->default(0);

            $table->timestamp('locked_until')
                ->nullable();

            $table->timestamp('last_login_at')
                ->nullable();

            $table->rememberToken();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Tabla de compatibilidad para tests que usan users
        |--------------------------------------------------------------------------
        */
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();

            $table->string('email')
                ->unique()
                ->nullable();

            $table->timestamp('email_verified_at')
                ->nullable();

            $table->string('password');

            $table->boolean('is_active')
                ->default(true);

            $table->integer('failed_login_attempts')
                ->default(0);

            $table->timestamp('locked_until')
                ->nullable();

            $table->timestamp('last_login_at')
                ->nullable();

            $table->rememberToken();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Recuperación de contraseña
        |--------------------------------------------------------------------------
        */
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();

            $table->string('token');

            $table->timestamp('created_at')
                ->nullable();
        });

        /*
        |--------------------------------------------------------------------------
        | Sesiones
        |--------------------------------------------------------------------------
        */
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')
                ->primary();

            $table->string('user_id')
                ->nullable()
                ->index();

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->longText('payload');

            $table->integer('last_activity')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');

        Schema::dropIfExists('users');

        Schema::dropIfExists('password_reset_tokens');

        Schema::dropIfExists('sessions');
    }
};
