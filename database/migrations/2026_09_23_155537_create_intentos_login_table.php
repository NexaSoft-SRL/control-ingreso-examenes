<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // La tabla login_attempts ya existe en:
        // 2026_09_16_140731_add_authentication_security_fields_and_login_attempts
    }

    public function down(): void
    {
        // No borrar login_attempts porque pertenece a la migración principal
    }
};
