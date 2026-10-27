<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Los accesos con codigo que ya existian abrian la puerta al instante:
 * quedan AUTORIZADOS, y los revocados, CORTADOS.
 */
final class AprobacionDeAccesosMigracionTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_los_accesos_previos_quedan_autorizados_o_cortados(): void
    {
        /** @var Migration $migracion */
        $migracion = require database_path('migrations/2026_10_27_000200_add_aprobacion_to_accesos_codigo_aula.php');
        $docente = $this->docente();
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);

        $this->correr($migracion, 'down');
        self::assertFalse(Schema::hasColumn('accesos_codigo_aula', 'estado'));

        foreach (['vigente' => null, 'revocado' => now()] as $token => $revocado) {
            DB::table('accesos_codigo_aula')->insert([
                'examen_id' => $examen->id,
                'aula_id' => $aula->id,
                'codigo' => '123456',
                'nombre_colaborador' => 'Rocha Ana',
                'token' => $token,
                'ultimo_acceso_en' => now(),
                'revocado_en' => $revocado,
            ]);
        }

        $this->correr($migracion, 'up');

        $this->assertDatabaseHas('accesos_codigo_aula', ['token' => 'vigente', 'estado' => 'AUTORIZADO']);
        $this->assertDatabaseHas('accesos_codigo_aula', ['token' => 'revocado', 'estado' => 'CORTADO']);
    }

    private function correr(Migration $migracion, string $sentido): void
    {
        $paso = [$migracion, $sentido];
        self::assertIsCallable($paso);
        $paso();
    }
}
