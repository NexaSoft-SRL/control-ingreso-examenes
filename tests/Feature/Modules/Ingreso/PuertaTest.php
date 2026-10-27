<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAulaCodigo;
use App\Modules\Examenes\Domain\Models\ExamenAulaPersonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

/**
 * Con codigo de aula se ve y se opera la misma puerta que con cuenta, solo
 * en el aula del acceso y solo con la autorizacion del docente; la
 * excepcion y la expulsion las hace una cuenta.
 */
final class PuertaTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_con_codigo_autorizado_opera_la_misma_puerta_que_con_cuenta(): void
    {
        [$examen, $aula, $estudiante] = $this->escenario();
        $acceso = $this->acceso($examen, $aula, 'AUTORIZADO', 'token-101');
        $headers = ['X-Codigo-Aula-Token' => 'token-101'];

        // El contexto dice el aula del acceso y su nombre.
        $this->withHeaders($headers)
            ->getJson('/api/punto-control/contexto')
            ->assertOk()
            ->assertJsonPath('data.0.id', $examen->id)
            ->assertJsonPath('data.0.aulas.0.id', $aula->id)
            ->assertJsonPath('acceso.nombre', 'Rocha Ana')
            ->assertJsonPath('acceso.aula', 'Aula 101');

        // La puerta del aula con sus examenes, cifras y normas.
        $this->withHeaders($headers)
            ->getJson("/api/aulas/{$aula->id}/punto-control")
            ->assertOk()
            ->assertJsonPath('data.examenes.0.id', $examen->id);

        $this->withHeaders($headers)
            ->postJson("/api/aulas/{$aula->id}/punto-control/ingresos", [
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'ADMITIDO');

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
            'acceso_codigo_aula_id' => $acceso->id,
            'registrado_por' => null,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'INGRESO_ADMITIDO',
            'usuario_id' => null,
        ]);

        $this->withHeaders($headers)
            ->getJson("/api/aulas/{$aula->id}/punto-control/ingresos")
            ->assertOk()
            ->assertJsonPath('data.0.estudiante_id', $estudiante->id)
            ->assertJsonPath('data.0.operador', 'Rocha Ana (código de aula)');

        // El mapa de la puerta no exige cuenta.
        $this->withHeaders($headers)
            ->getJson("/api/aulas/{$aula->id}/punto-control/edificios")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['caja']]);

        // Con codigo de aula no hay excepcion ni expulsion.
        $ingreso = DB::table('ingresos')->value('id');
        self::assertIsInt($ingreso);
        $this->withHeaders($headers)
            ->postJson("/api/aulas/{$aula->id}/punto-control/ingresos/{$ingreso}/expulsion", ['motivo' => 'Uso de celular.'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Con código de aula solo se verifica y se reportan incidencias.');
        $this->withHeaders($headers)
            ->postJson("/api/aulas/{$aula->id}/punto-control/excepciones", [
                'tipo' => 'EXCEPCION_IDENTIFICACION',
                'motivo' => 'Documento dudoso.',
            ])
            ->assertForbidden();
    }

    public function test_el_codigo_solo_abre_la_puerta_de_su_aula(): void
    {
        [$examen, $aula] = $this->escenario();
        $otra = $this->aula('Aula 102');
        DB::table('examen_aula')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $otra->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->acceso($examen, $aula, 'AUTORIZADO', 'token-101');

        $this->withHeaders(['X-Codigo-Aula-Token' => 'token-101'])
            ->postJson("/api/aulas/{$otra->id}/punto-control/ingresos", ['identificador' => '202100001'])
            ->assertForbidden()
            ->assertJsonPath('message', 'El código solo abre la puerta de su aula.');
    }

    public function test_sin_autorizar_rechazado_o_cortado_no_opera(): void
    {
        [$examen, $aula] = $this->escenario();

        foreach ([
            'PENDIENTE' => 'Falta que el docente autorice el acceso.',
            'RECHAZADO' => 'El docente no autorizó el acceso.',
            'CORTADO' => 'El docente cortó el acceso.',
        ] as $estado => $mensaje) {
            $token = 'token-'.$estado;
            $this->acceso($examen, $aula, $estado, $token);

            $this->withHeaders(['X-Codigo-Aula-Token' => $token])
                ->getJson("/api/aulas/{$aula->id}/punto-control")
                ->assertUnauthorized()
                ->assertJsonPath('message', $mensaje);
        }
    }

    public function test_el_docente_autoriza_mueve_y_corta_el_acceso(): void
    {
        $cuenta = $this->usuarioConPermisos(['examenes', 'punto_control']);
        $docente = $this->docenteConCuenta($cuenta, 'Blanco Coca Leticia');
        $grupo = $this->grupo($docente);
        $aula = $this->aula('Aula 101');
        $otra = $this->aula('Aula 102');
        $inicio = now()->subMinutes(10);
        $examen = $this->examen($docente, [$grupo], [$aula, $otra], [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
        ]);
        $acceso = $this->acceso($examen, $aula, 'PENDIENTE', 'token-espera');
        $base = "/api/examenes/{$examen->id}/accesos/{$acceso->id}";

        // Solo el docente del examen decide.
        $this->actingAs($this->usuarioConPermisos(['examenes']))
            ->postJson("{$base}/autorizar")
            ->assertForbidden();

        $this->actingAs($cuenta)
            ->postJson("{$base}/autorizar")
            ->assertOk()
            ->assertJsonPath('estado', 'autorizado')
            ->assertJsonPath('message', 'Acceso autorizado.');
        $this->assertDatabaseHas('accesos_codigo_aula', ['id' => $acceso->id, 'estado' => 'AUTORIZADO', 'resuelto_por' => $cuenta->id]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'acceso_aula.autorizar', 'registro_id' => $acceso->id]);

        // El aula ajena no se acepta; la del examen mueve el acceso.
        $ajena = $this->aula('Aula 900');
        $this->postJson("{$base}/trasladar", ['aula_id' => $ajena->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.aula_id.0', 'El aula no es de este examen.');
        $this->postJson("{$base}/trasladar", ['aula_id' => $otra->id])
            ->assertOk()
            ->assertJsonPath('message', 'Acceso movido de aula.');
        $this->assertDatabaseHas('accesos_codigo_aula', ['id' => $acceso->id, 'aula_id' => $otra->id]);

        // La puerta toma el aula del acceso.
        $this->withHeaders(['X-Codigo-Aula-Token' => 'token-espera'])
            ->getJson("/api/aulas/{$otra->id}/punto-control")
            ->assertOk();

        $this->actingAs($cuenta)->postJson("{$base}/cortar")->assertOk()->assertJsonPath('estado', 'cortado');
        // Lo cortado queda asi.
        $this->postJson("{$base}/autorizar")->assertOk()->assertJsonPath('estado', 'cortado');

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['X-Codigo-Aula-Token' => 'token-espera'])
            ->getJson('/api/auth/codigo-aula/sesion')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'El docente cortó el acceso.');
    }

    public function test_regenerar_el_codigo_corta_los_accesos_del_aula(): void
    {
        [$examen, $aula] = $this->escenario();
        $creador = User::query()->findOrFail($examen->creado_por);
        $autorizado = $this->acceso($examen, $aula, 'AUTORIZADO', 'token-a');
        $pendiente = $this->acceso($examen, $aula, 'PENDIENTE', 'token-b');
        ExamenAulaCodigo::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '123456',
            'generado_en' => now()->toDateTimeString(),
        ]);

        $this->actingAs($creador)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
            ->assertOk();

        $this->assertDatabaseHas('accesos_codigo_aula', ['id' => $autorizado->id, 'estado' => 'CORTADO']);
        $this->assertDatabaseHas('accesos_codigo_aula', ['id' => $pendiente->id, 'estado' => 'CORTADO']);
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

        ExamenAulaPersonal::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'usuario_id' => $cuentaApoyo->id,
            'rol' => ExamenAulaPersonal::ROL_A_CARGO,
        ]);

        $this->actingAs($cuentaCreador)
            ->postJson("/api/aulas/{$aula->id}/punto-control/identificar", [
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertOk()
            ->assertJsonPath('data.codigo', $estudiante->codigo_universitario);
    }

    /**
     * @return array{Examen, Aula, Estudiante}
     */
    private function escenario(): array
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

        return [$examen, $aula, $estudiante];
    }

    private function acceso(Examen $examen, Aula $aula, string $estado, string $token): AccesoCodigoAula
    {
        return AccesoCodigoAula::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '123456',
            'nombre_colaborador' => 'Rocha Ana',
            'token' => $token,
            'ultimo_acceso_en' => now(),
            'estado' => $estado,
        ]);
    }
}
