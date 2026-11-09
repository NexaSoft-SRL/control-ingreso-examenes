<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El docente fija hasta que dia los estudiantes de su grupo suben su foto.
 * Vencido ese dia, quien no la subio queda inhabilitado en los examenes del
 * grupo: esa inhabilitacion la pone el sistema (`por_foto`) y la quita
 * cuando el estudiante sube la foto o el docente mueve el plazo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table): void {
            $table->date('foto_hasta')->nullable();
            $table->foreignId('foto_hasta_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('habilitaciones', function (Blueprint $table): void {
            $table->boolean('por_foto')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('habilitaciones', function (Blueprint $table): void {
            $table->dropColumn('por_foto');
        });

        Schema::table('grupos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('foto_hasta_por');
            $table->dropColumn('foto_hasta');
        });
    }
};
