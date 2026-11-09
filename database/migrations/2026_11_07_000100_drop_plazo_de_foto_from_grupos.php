<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El plazo de fotos lo fija solo la administracion (`plazos_foto`): el
 * docente ya no fija el de su grupo, para que no haya plazos que choquen.
 * `habilitaciones.por_foto` se queda: la inhabilitacion por no subir la foto
 * ahora depende del plazo general.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('foto_hasta_por');
            $table->dropColumn('foto_hasta');
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table): void {
            $table->date('foto_hasta')->nullable();
            $table->foreignId('foto_hasta_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
