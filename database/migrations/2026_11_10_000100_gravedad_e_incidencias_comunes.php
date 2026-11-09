<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La gravedad deja de salir del tipo: se guarda en cada incidencia y la
 * elige quien registra (el tipo solo la sugiere). Una incidencia no prevista
 * lleva su propio nombre (`tipo_texto`, con tipo OTRA).
 *
 * `incidencias_comunes` es la lista de cada cuenta: las que no estan entre
 * los tipos del sistema (con `nombre`) y la gravedad que prefiere para los
 * del sistema (con `tipo`). Las ya registradas toman la gravedad de su tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->string('gravedad', 10)->nullable()->after('tipo');
            $table->string('tipo_texto', 80)->nullable()->after('gravedad');
        });

        DB::statement("update incidencias set gravedad = case tipo
            when 'SUPLANTACION' then 'MUY_GRAVE'
            when 'EXPULSION' then 'MUY_GRAVE'
            when 'INGRESO_REPETIDO' then 'GRAVE'
            when 'INTENTO_NO_AUTORIZADO' then 'GRAVE'
            when 'MATERIAL_NO_PERMITIDO' then 'GRAVE'
            when 'DISPOSITIVO_NO_PERMITIDO' then 'GRAVE'
            else 'LEVE' end");

        DB::statement("alter table incidencias add constraint ck_incidencias_gravedad check (gravedad in ('LEVE', 'GRAVE', 'MUY_GRAVE'))");

        Schema::create('incidencias_comunes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Un tipo del sistema con la gravedad que prefiere la cuenta, o
            // una incidencia propia por su nombre: uno de los dos.
            $table->string('tipo', 40)->nullable();
            $table->string('nombre', 80)->nullable();
            $table->string('gravedad', 10);

            $table->timestamps();
        });

        DB::statement("alter table incidencias_comunes add constraint ck_incidencia_comun_gravedad check (gravedad in ('LEVE', 'GRAVE', 'MUY_GRAVE'))");
        DB::statement('alter table incidencias_comunes add constraint ck_incidencia_comun_tipo_o_nombre check ((tipo is null) <> (nombre is null))');
        DB::statement('create unique index uq_incidencia_comun_tipo on incidencias_comunes (usuario_id, tipo) where tipo is not null');
        DB::statement('create unique index uq_incidencia_comun_nombre on incidencias_comunes (usuario_id, lower(nombre)) where nombre is not null');
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_comunes');

        Schema::table('incidencias', function (Blueprint $table): void {
            $table->dropColumn(['gravedad', 'tipo_texto']);
        });
    }
};
