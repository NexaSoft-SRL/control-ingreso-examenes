<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las suspensiones son parte del examen: al eliminarlo se van con el, como
 * sus grupos, aulas y habilitaciones. Antes la clave lo impedia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suspensiones_examen', function (Blueprint $table): void {
            $table->dropForeign(['examen_id']);

            $table->foreign('examen_id')
                ->references('id')
                ->on('examenes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('suspensiones_examen', function (Blueprint $table): void {
            $table->dropForeign(['examen_id']);

            $table->foreign('examen_id')
                ->references('id')
                ->on('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
