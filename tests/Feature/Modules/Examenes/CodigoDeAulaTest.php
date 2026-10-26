<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\CodigoAulaGateway;
use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use App\Modules\Examenes\Domain\Models\ExamenAulaCodigo;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentCodigoAulaGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CodigoDeAulaTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_consultar_codigo_no_lo_genera_si_no_existe(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertOk()
            ->assertJsonPath('data.codigo', null)
            ->assertJsonPath('data.generado_en', null);

        $this->assertDatabaseMissing('examen_aula_codigos', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
        ]);
    }

    public function test_docente_puede_generar_codigo_de_seis_digitos(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $respuesta = $this->actingAs($cuenta)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertCreated()
            ->assertJsonPath('message', 'Código generado');

        $codigo = $respuesta->json('data.codigo');
        self::assertIsString($codigo);
        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $codigo);

        $this->assertDatabaseHas('examen_aula_codigos', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => $codigo,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen_aula_codigo.generar',
            'usuario_id' => $cuenta->id,
        ]);
    }

    public function test_regenerar_codigo_revoca_sesiones_abiertas_anteriores(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$aula]);

        // Código inicial
        ExamenAulaCodigo::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '123456',
            'generado_en' => now()->toDateTimeString(),
        ]);

        // Sesión activa abierta con el código anterior
        $acceso = AccesoCodigoAula::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '123456',
            'nombre_colaborador' => 'Colaborador Juan',
            'token' => 'token-prueba-123456',
            'ultimo_acceso_en' => now(),
            'revocado_en' => null,
        ]);

        // Regeneramos el código
        $respuesta = $this->actingAs($cuenta)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertOk()
            ->assertJsonPath('message', 'Código regenerado');

        $nuevoCodigo = $respuesta->json('data.codigo');
        self::assertIsString($nuevoCodigo);
        self::assertNotSame('123456', $nuevoCodigo);
        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $nuevoCodigo);

        // La sesión previa debe haber sido revocada
        $accesoActualizado = AccesoCodigoAula::find($acceso->id);
        self::assertNotNull($accesoActualizado?->revocado_en);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen_aula_codigo.regenerar',
            'usuario_id' => $cuenta->id,
        ]);
    }

    public function test_docente_ajeno_no_puede_consultar_ni_generar_codigo(): void
    {
        $docenteA = $this->docente('Docente A');
        $docenteB = $this->docente('Docente B');
        $cuentaB = $this->cuenta($docenteB);

        $grupo = $this->grupo($docenteA);
        $aula = $this->aula('691A');
        $examen = $this->examen($docenteA, [$grupo], [$aula]);

        $this->actingAs($cuentaB)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertForbidden();

        $this->actingAs($cuentaB)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertForbidden();
    }

    public function test_la_bitacora_describe_el_codigo_con_el_examen_y_el_aula(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $asignatura = DB::table('asignaturas')->where('id', $examen->asignatura_id)->value('nombre');
        self::assertIsString($asignatura);

        $this->actingAs($cuenta)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertCreated();
        $this->actingAs($cuenta)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen_aula_codigo.generar',
            'descripcion' => "Código generado: Primer parcial · {$asignatura}, aula 691A.",
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen_aula_codigo.regenerar',
            'descripcion' => "Código regenerado: Primer parcial · {$asignatura}, aula 691A.",
        ]);
    }

    public function test_el_codigo_no_se_repite_entre_examenes_de_la_misma_fecha(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $aulaA = $this->aula('691A');
        $aulaB = $this->aula('691B');
        $aulaC = $this->aula('691C');
        $dentroDeTresDias = now()->addDays(3)->toDateString();

        // Otro examen del mismo día (no de hoy) ya usa 111111.
        $otro = $this->examen($docente, [$this->grupo($docente)], [$aulaA], ['fecha' => $dentroDeTresDias]);
        ExamenAulaCodigo::create([
            'examen_id' => $otro->id,
            'aula_id' => $aulaA->id,
            'codigo' => '111111',
            'generado_en' => now()->toDateTimeString(),
        ]);
        // Uno de otro día usa 333333: ese sí puede repetirse.
        $ayer = $this->examen($docente, [$this->grupo($docente)], [$aulaC], ['fecha' => now()->subDay()->toDateString()]);
        ExamenAulaCodigo::create([
            'examen_id' => $ayer->id,
            'aula_id' => $aulaC->id,
            'codigo' => '333333',
            'generado_en' => now()->toDateTimeString(),
        ]);

        $examen = $this->examen($docente, [$this->grupo($docente)], [$aulaB], ['fecha' => $dentroDeTresDias]);
        $sorteos = ['111111', '222222'];
        $this->sortearEn($sorteos);

        $this->actingAs($cuenta)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aulaB->id}/codigo")
            ->assertCreated()
            ->assertJsonPath('data.codigo', '222222');

        $this->sortearEn(['333333']);

        $this->actingAs($cuenta)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aulaB->id}/codigo")
            ->assertOk()
            ->assertJsonPath('data.codigo', '333333');
    }

    /**
     * Fija los códigos que saldrán sorteados, en orden.
     *
     * @param  list<string>  $codigos
     */
    private function sortearEn(array $codigos): void
    {
        $bitacora = $this->app->make(BitacoraGateway::class);

        $this->app->bind(CodigoAulaGateway::class, static function () use ($bitacora, $codigos): EloquentCodigoAulaGateway {
            return new EloquentCodigoAulaGateway(
                $bitacora,
                static function () use (&$codigos): string {
                    return array_shift($codigos) ?? '999999';
                },
            );
        });
    }
}
