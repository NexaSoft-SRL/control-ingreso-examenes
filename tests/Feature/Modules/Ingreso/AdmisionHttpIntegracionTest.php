<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class AdmisionHttpIntegracionTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_admite_y_persiste_un_estudiante_habilitado(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $this->habilitar($examen, [$estudiante], $aula);
        $this->autorizarPunto($usuario, $examen, $aula);

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'ADMITIDO')
            ->assertJsonPath('data.admitido', true);

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
            'registrado_por' => $usuario->id,
        ]);

        self::assertSame(1, DB::table('ingresos')->count());
    }

    public function test_segundo_intento_retorna_conflicto_sin_duplicar(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $this->habilitar($examen, [$estudiante], $aula);
        $this->autorizarPunto($usuario, $examen, $aula);

        $url = $this->url($examen, $aula);

        $datos = [
            'tipo' => 'codigo',
            'identificador' => $estudiante->codigo_universitario,
        ];

        $this->actingAs($usuario)
            ->postJson($url, $datos)
            ->assertCreated();

        $this->postJson($url, $datos)
            ->assertStatus(409)
            ->assertJsonPath('data.veredicto', 'YA_INGRESO')
            ->assertJsonPath('data.admitido', false);

        self::assertSame(1, DB::table('ingresos')->count());

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'registrado_por' => $usuario->id,
            'tipo_rechazo' => 'YA_INGRESO',
            'detalle' => 'El estudiante ya tiene un ingreso registrado.',
        ]);

        $this->assertDatabaseCount('incidencias_ingreso', 1);
    }

    public function test_consulta_candidato_muestra_motivo_real_de_inhabilitacion(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $this->inhabilitar(
            $examen,
            [$estudiante],
            'Documentación académica pendiente.',
        );
        $this->autorizarPunto($usuario, $examen, $aula);

        $this->actingAs($usuario)
            ->getJson(sprintf(
                '/api/examenes/%d/aulas/%d/punto-control/candidato?tipo=codigo&identificador=%s',
                $examen->id,
                $aula->id,
                $estudiante->codigo_universitario,
            ))
            ->assertOk()
            ->assertJsonPath('data.habilitado', false)
            ->assertJsonPath(
                'data.motivo_inhabilitacion',
                'Documentación académica pendiente.',
            );

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias_ingreso', 0);
    }

    public function test_no_habilitado_no_genera_ingreso(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $this->inhabilitar($examen, [$estudiante]);
        $this->autorizarPunto($usuario, $examen, $aula);

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'documento',
                'identificador' => $estudiante->documento_identidad,
            ])
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO')
            ->assertJsonPath('data.admitido', false);

        $this->assertDatabaseCount('ingresos', 0);

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'registrado_por' => $usuario->id,
            'tipo_rechazo' => 'NO_HABILITADO',
            'detalle' => 'No cumple el requisito.',
        ]);

        $this->assertDatabaseCount('incidencias_ingreso', 1);
    }

    public function test_estudiante_de_otra_aula_no_ingresa(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $otraAula = $this->aula('P-302');

        DB::table('examen_aula')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $otraAula->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->habilitar($examen, [$estudiante], $otraAula);
        $this->autorizarPunto($usuario, $examen, $aula);

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'OTRA_AULA')
            ->assertJsonPath('data.admitido', false);

        $this->assertDatabaseCount('ingresos', 0);

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'registrado_por' => $usuario->id,
            'tipo_rechazo' => 'OTRA_AULA',
            'detalle' => sprintf('Asignado al aula %s.', $otraAula->nombre),
        ]);

        $this->assertDatabaseCount('incidencias_ingreso', 1);
    }

    public function test_aula_ajena_al_examen_es_rechazada(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $aulaAjena = $this->aula('P-399');

        $this->habilitar($examen, [$estudiante], $aula);
        $this->autorizarPunto($usuario, $examen, $aula);

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aulaAjena), [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ingresos', 0);
    }

    /**
     * @return array{User, Examen, Aula, Estudiante}
     */
    private function escenario(): array
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-301');

        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        // Mismo huso horario que la regla de ventana de HU-19.
        $inicio = now('America/La_Paz')->subMinutes(5);

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
            [
                'fecha' => $inicio->toDateString(),
                'hora_inicio' => $inicio->format('H:i:00'),
                'duracion_minutos' => 90,
            ],
        );

        return [$usuario, $examen, $aula, $estudiante];
    }

    public function test_sin_condicion_registrada_crea_incidencia_no_habilitado(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $this->autorizarPunto($usuario, $examen, $aula);

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO')
            ->assertJsonPath('data.admitido', false);

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo_rechazo' => 'NO_HABILITADO',
            'detalle' => 'Sin revisar: sin condición de habilitación registrada.',
        ]);

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias_ingreso', 1);
    }

    public function test_estudiante_inexistente_crea_incidencia_sin_estudiante(): void
    {
        [$usuario, $examen, $aula] = $this->escenario();

        $this->autorizarPunto($usuario, $examen, $aula);

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'codigo',
                'identificador' => 'CODIGO-INEXISTENTE-999',
            ])
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_CORRESPONDE')
            ->assertJsonPath('data.admitido', false);

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => null,
            'registrado_por' => $usuario->id,
            'tipo_rechazo' => 'NO_CORRESPONDE',
            'detalle' => 'Estudiante no encontrado.',
        ]);

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias_ingreso', 1);
    }

    public function test_operador_no_autorizado_no_crea_incidencia(): void
    {
        [, $examen, $aula, $estudiante] = $this->escenario();
        $noAutorizado = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($noAutorizado)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias_ingreso', 0);
    }

    private function autorizarPunto(
        User $usuario,
        Examen $examen,
        Aula $aula,
    ): void {
        $alcance = $this->createMock(AlcancePuntoControl::class);

        $alcance->method('puedeOperar')
            ->willReturnCallback(
                static fn (
                    int $usuarioId,
                    int $examenId,
                    int $aulaId,
                ): bool => $usuarioId === $usuario->id
                    && $examenId === $examen->id
                    && $aulaId === $aula->id,
            );

        $this->app->instance(
            AlcancePuntoControl::class,
            $alcance,
        );
    }

    private function url(Examen $examen, Aula $aula): string
    {
        return sprintf(
            '/api/examenes/%d/aulas/%d/punto-control/ingresos',
            $examen->id,
            $aula->id,
        );
    }

    public function test_admite_estudiante_identificado_por_qr(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $this->habilitar($examen, [$estudiante], $aula);
        $this->autorizarPunto($usuario, $examen, $aula);

        $contenido = app(CodigoQrGateway::class)->generar(
            $examen->id,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'qr',
                'identificador' => $contenido,
            ])
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'ADMITIDO');

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
        ]);
    }

    public function test_qr_de_otro_examen_no_registra_ingreso(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenario();

        $this->habilitar($examen, [$estudiante], $aula);
        $this->autorizarPunto($usuario, $examen, $aula);

        $contenido = app(CodigoQrGateway::class)->generar(
            $examen->id + 1,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson($this->url($examen, $aula), [
                'tipo' => 'qr',
                'identificador' => $contenido,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('ingresos', 0);
    }
}
