<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quien opero sin cuenta: el estudiante en su portal o la persona que
 * entro con el codigo de un aula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora_operaciones', function (Blueprint $table): void {
            $table->string('actor', 150)->nullable()->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_operaciones', function (Blueprint $table): void {
            $table->dropColumn('actor');
        });
    }
};
