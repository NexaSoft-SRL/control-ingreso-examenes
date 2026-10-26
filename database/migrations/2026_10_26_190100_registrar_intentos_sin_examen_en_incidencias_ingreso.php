<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-19 (BUG-04): el intento de quien no esta inscrito en los examenes del
 * aula, de un codigo que no esta en el padron o de un QR no valido tambien
 * queda en las incidencias de ingreso. Esos intentos no tienen un examen
 * propio: no se inventa uno.
 */
return new class extends Migration
{
    private const TIPOS = [
        'NO_HABILITADO',
        'OTRA_AULA',
        'YA_INGRESO',
        'NO_CORRESPONDE',
        'EXPULSADO',
    ];

    private const TIPOS_NUEVOS = [
        'NO_INSCRITO',
        'NO_REGISTRADO',
        'QR_NO_VALIDO',
    ];

    public function up(): void
    {
        Schema::table('incidencias_ingreso', function (Blueprint $table): void {
            $table->unsignedBigInteger('examen_id')->nullable()->change();
            $table->index(['aula_id', 'registrado_en'], 'idx_incidencias_ingreso_aula_fecha');
        });

        $this->restringirTipos([...self::TIPOS, ...self::TIPOS_NUEVOS]);
    }

    public function down(): void
    {
        DB::table('incidencias_ingreso')
            ->whereNull('examen_id')
            ->orWhereIn('tipo_rechazo', self::TIPOS_NUEVOS)
            ->delete();

        $this->restringirTipos(self::TIPOS);

        Schema::table('incidencias_ingreso', function (Blueprint $table): void {
            $table->dropIndex('idx_incidencias_ingreso_aula_fecha');
            $table->unsignedBigInteger('examen_id')->nullable(false)->change();
        });
    }

    /**
     * @param  list<string>  $tipos
     */
    private function restringirTipos(array $tipos): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE incidencias_ingreso DROP CONSTRAINT IF EXISTS chk_incidencia_tipo_rechazo'
        );

        DB::statement(sprintf(
            'ALTER TABLE incidencias_ingreso
             ADD CONSTRAINT chk_incidencia_tipo_rechazo
             CHECK (tipo_rechazo IN (%s))',
            implode(', ', array_map(
                static fn (string $tipo): string => "'{$tipo}'",
                $tipos,
            )),
        ));
    }
};
