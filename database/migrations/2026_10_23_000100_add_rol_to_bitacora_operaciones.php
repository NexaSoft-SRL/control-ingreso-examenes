<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El rol de quien opero, como era en ese momento: si despues le cambian el
 * rol, la bitacora sigue diciendo con que rol hizo cada cosa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora_operaciones', function (Blueprint $table): void {
            $table->string('rol', 60)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_operaciones', function (Blueprint $table): void {
            $table->dropColumn('rol');
        });
    }
};
