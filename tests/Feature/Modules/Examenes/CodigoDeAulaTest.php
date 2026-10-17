<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use App\Modules\Examenes\Domain\Models\ExamenAulaCodigo;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
