<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use App\Modules\Examenes\Domain\Models\ExamenAulaPersonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

final class PuertaTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_colaborador_con_token_opera_punto_de_control_de_su_aula(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula('Aula 101');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $inicio = now()->subMinutes(10);
        $examen = $this->examen($docente, [$grupo], [$aula], [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
        ]);

        $this->habilitar($examen, [$estudiante], $aula);

        $token = 'colaborador-token-valido-101';
        $acceso = AccesoCodigoAula::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '123456',
            'nombre_colaborador' => 'Colaborador Puerta',
            'token' => $token,
            'ultimo_acceso_en' => now(),
            'revocado_en' => null,
        ]);

        $headers = ['X-Codigo-Aula-Token' => $token];

        // 1. Consultar candidato
        $this->withHeaders($headers)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/punto-control/candidato?tipo=codigo&identificador={$estudiante->codigo_universitario}")
            ->assertOk()
            ->assertJsonPath('data.codigo', $estudiante->codigo_universitario)
            ->assertJsonPath('data.habilitado', true);

        // 2. Registrar ingreso
        $this->withHeaders($headers)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/punto-control/ingresos", [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'ADMITIDO')
            ->assertJsonPath('data.admitido', true);

        // Verifica que el ingreso guarde acceso_codigo_aula_id y registrado_por null
        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
            'acceso_codigo_aula_id' => $acceso->id,
            'registrado_por' => null,
        ]);

        // Verifica auditoría en bitácora con usuario_id null
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'INGRESO_ADMITIDO',
            'usuario_id' => null,
        ]);

        // 3. Consultar recientes
        $this->withHeaders($headers)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/punto-control/ingresos")
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 1)
            ->assertJsonPath('data.0.estudiante_id', $estudiante->id);

        // 4. Consultar incidencias
        $this->withHeaders($headers)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/punto-control/incidencias")
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 0);
    }

    public function test_colaborador_no_puede_operar_otra_aula_responde_403(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aulaA = $this->aula('Aula 101');
        $aulaB = $this->aula('Aula 102');

        $inicio = now()->subMinutes(10);
        $examen = $this->examen($docente, [$grupo], [$aulaA, $aulaB], [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
        ]);

        $token = 'token-solo-para-aula-101';
        AccesoCodigoAula::create([
            'examen_id' => $examen->id,
            'aula_id' => $aulaA->id,
            'codigo' => '123456',
            'nombre_colaborador' => 'Colaborador Puerta A',
            'token' => $token,
            'ultimo_acceso_en' => now(),
            'revocado_en' => null,
        ]);

        $headers = ['X-Codigo-Aula-Token' => $token];

        // Intento de operar Aula B con token de Aula A
        $this->withHeaders($headers)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$aulaB->id}/punto-control/candidato?tipo=codigo&identificador=202100001")
            ->assertForbidden()
            ->assertJsonPath('message', 'Punto de control no autorizado.');

        $this->withHeaders($headers)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aulaB->id}/punto-control/ingresos", [
                'tipo' => 'codigo',
                'identificador' => '202100001',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Punto de control no autorizado.');
    }

    public function test_docente_creador_opera_aula_aunque_este_a_cargo_de_otra_persona(): void
    {
        $usuarioCreador = $this->usuarioConPermisos(['examenes', 'punto_control']);
        $docenteCreador = $this->docenteConCuenta($usuarioCreador, 'Docente Creadora');
        $cuentaCreador = $this->cuenta($docenteCreador);

        $usuarioApoyo = $this->usuarioConPermisos(['examenes', 'punto_control']);
        $docenteApoyo = $this->docenteConCuenta($usuarioApoyo, 'Docente Apoyo');
        $cuentaApoyo = $this->cuenta($docenteApoyo);

        $grupo = $this->grupo($docenteCreador);
        $aula = $this->aula('617');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $inicio = now()->subMinutes(10);
        $examen = $this->examen($docenteCreador, [$grupo], [$aula], [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
        ]);

        $this->habilitar($examen, [$estudiante], $aula);

        // Aula 617 puesta a cargo de Docente Apoyo
        ExamenAulaPersonal::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'usuario_id' => $cuentaApoyo->id,
            'rol' => ExamenAulaPersonal::ROL_A_CARGO,
        ]);

        // Docente creador opera el punto de control sin ser rechazado con 403
        $this->actingAs($cuentaCreador)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/punto-control/candidato?tipo=codigo&identificador={$estudiante->codigo_universitario}")
            ->assertOk()
            ->assertJsonPath('data.codigo', $estudiante->codigo_universitario);
    }
}
