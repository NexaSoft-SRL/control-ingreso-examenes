<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Las columnas de seguridad ya existen en la migración
        // add_authentication_security_fields_and_login_attempts.php
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hace nada porque no se agregaron columnas aquí
    }
};
