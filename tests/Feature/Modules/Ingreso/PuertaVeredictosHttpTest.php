<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-19, ronda 3 de QA: veredictos con sus datos, intentos distinguidos,
 * la puerta del aula, excepciones, expulsion y bitacora.
 */
final class PuertaVeredictosHttpTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $usuario;

    private Examen $examen;

    private Aula $aula;

    private Aula $otraAula;

    /** @var list<Estudiante> */
    private array $estudiantes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($this->usuario);
        $grupo = $this->grupo($docente);
        $edificio = $this->edificio(null, 'Edificio Académico 2');
        $this->aula = $this->aula('691A', $edificio, '1.er piso');
        $this->otraAula = $this->aula('691B', $edificio, '2.º piso');
        $this->estudiantes = $this->estudiantesInscritos($grupo, 4);

        $this->examen = $this->examen($docente, [$grupo], [$this->aula, $this->otraAula], [
            'normas' => 'Sin celulares.',
        ]);

        DB::table('examen_norma')->insert([
            'examen_id' => $this->examen->id,
            'texto' => 'Llevar carnet',
            'orden' => 1,
        ]);

        $this->habilitar($this->examen, [$this->estudiantes[0], $this->estudiantes[3]], $this->aula);
        $this->habilitar($this->examen, [$this->estudiantes[1]], $this->otraAula);
        $this->inhabilitar($this->examen, [$this->estudiantes[2]], 'No entregó las prácticas 1 y 2.');
    }

    public function test_bug19_admitido_trae_estudiante_examen_y_hora(): void
    {
        $respuesta = $this->ingresar_por_codigo($this->estudiantes[0])
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'ADMITIDO')
            ->assertJsonPath('data.estudiante.codigo', $this->estudiantes[0]->codigo_universitario)
            ->assertJsonPath('data.examen.tipo', 'Primer parcial');

        $hora = substr(self::cadena(DB::table('ingresos')->value('registrado_en')), 11, 5);

        $respuesta->assertJsonPath('data.dato', 'Registrado a las '.$hora)
            ->assertJsonPath('data.hora', $hora);

        self::assertNotNull($respuesta->json('data.examen.asignatura'));
        self::assertNotNull($respuesta->json('data.registrado_en'));
    }

    public function test_bug11_ya_ingreso_dice_hora_y_aula(): void
    {
        $this->ingresar_por_codigo($this->estudiantes[0])->assertCreated();
        $hora = substr(self::cadena(DB::table('ingresos')->value('registrado_en')), 11, 5);

        // Releerlo enseguida en la misma puerta no deja incidencia.
        $this->ingresar_por_codigo($this->estudiantes[0])
            ->assertConflict()
            ->assertJsonPath('data.veredicto', 'YA_INGRESO')
            ->assertJsonPath('data.dato', "Ingresó a las {$hora} en esta aula")
            ->assertJsonPath('data.pie', null)
            ->assertJsonPath('data.registrado_en', DB::table('ingresos')->value('registrado_en'));
        $this->assertDatabaseCount('incidencias', 0);

        // Desde otra puerta del examen se nombra el aula del ingreso y queda
        // la incidencia, una sola aunque lo vuelvan a leer.
        foreach ([1, 2] as $vez) {
            $this->actingAs($this->usuario)->postJson(
                "/api/aulas/{$this->otraAula->id}/punto-control/ingresos",
                ['tipo' => 'codigo', 'identificador' => $this->estudiantes[0]->codigo_universitario],
            )->assertConflict()
                ->assertJsonPath('data.dato', "Ingresó a las {$hora} en el aula 691A")
                ->assertJsonPath('data.pie', $vez === 1 ? 'Incidencia registrada' : null);
        }

        $this->assertDatabaseCount('incidencias', 1);
        $this->assertDatabaseHas('incidencias', [
            'aula_id' => $this->otraAula->id,
            'estudiante_id' => $this->estudiantes[0]->id,
            'detalle' => "Código o C.I. ya usado. Ingresó a las {$hora} en el aula 691A.",
        ]);
    }

    public function test_bug08_el_aula_asignada_es_una_sugerencia(): void
    {
        $respuesta = $this->ingresar_por_codigo($this->estudiantes[1])
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'ADMITIDO')
            ->assertJsonPath('data.aula_sugerida.nombre', '691B')
            ->assertJsonPath('data.aula_sugerida.edificio', 'Edificio Académico 2')
            ->assertJsonPath('data.aula_sugerida.piso', '2.º piso');

        $hora = substr(self::cadena(DB::table('ingresos')->value('registrado_en')), 11, 5);
        $respuesta->assertJsonPath('data.dato', "Registrado a las {$hora} · su aula sugerida era la 691B");

        $this->assertDatabaseHas('ingresos', [
            'estudiante_id' => $this->estudiantes[1]->id,
            'aula_id' => $this->aula->id,
        ]);
    }

    public function test_un_solo_campo_acepta_el_ci_con_complemento(): void
    {
        DB::table('estudiantes')
            ->where('id', $this->estudiantes[0]->id)
            ->update(['documento_identidad' => '5003748-1A']);

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['identificador' => '5003748 1a'],
        )->assertCreated()
            ->assertJsonPath('data.veredicto', 'ADMITIDO')
            ->assertJsonPath('data.estudiante.codigo', $this->estudiantes[0]->codigo_universitario);

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['identificador' => $this->estudiantes[3]->codigo_universitario],
        )->assertCreated();

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['identificador' => 'abc'],
        )->assertUnprocessable()
            ->assertJsonPath('errors.identificador.0', 'Código SIS o C.I. no válido');

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['identificador' => '7777777-2B'],
        )->assertConflict()
            ->assertJsonPath('data.causa', 'NO_REGISTRADO')
            ->assertJsonPath('data.dato', 'Sin registro en el padrón');
    }

    public function test_llegada_tarde_pasados_quince_minutos(): void
    {
        $inicio = now()->subMinutes(40)->format('H:i:s');
        DB::table('examenes')->where('id', $this->examen->id)->update([
            'fecha' => now()->toDateString(),
            'hora_inicio' => $inicio,
        ]);

        $this->ingresar_por_codigo($this->estudiantes[0])
            ->assertCreated()
            ->assertJsonPath('data.tarde', 40)
            ->assertJsonPath('data.pie', 'Tarde · 40 min después del inicio');

        $this->actingAs($this->usuario)
            ->getJson("/api/aulas/{$this->aula->id}/punto-control/ingresos")
            ->assertOk()
            ->assertJsonPath('data.0.tarde', 40);
    }

    public function test_bug10_no_habilitado_trae_motivo_y_quien_lo_registro(): void
    {
        $this->ingresar_por_codigo($this->estudiantes[2])
            ->assertConflict()
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO')
            ->assertJsonPath('data.dato', 'No entregó las prácticas 1 y 2.');
    }

    public function test_bug04_y_bug14_no_inscrito_y_sin_registro_se_distinguen_y_se_listan(): void
    {
        $ajeno = $this->estudiantesInscritos($this->grupo($this->docenteConCuenta()), 1)[0];

        $this->ingresar_por_codigo($ajeno)
            ->assertConflict()
            ->assertJsonPath('data.causa', 'NO_INSCRITO')
            ->assertJsonPath('data.estudiante.codigo', $ajeno->codigo_universitario)
            ->assertJsonPath('data.dato', 'No inscrito en los exámenes del aula 691A')
            ->assertJsonPath('data.pie', 'Intento registrado');

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => '99999999'],
        )->assertConflict()
            ->assertJsonPath('data.causa', 'NO_REGISTRADO')
            ->assertJsonPath('data.estudiante', null);

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['tipo' => 'qr', 'identificador' => 'SCIQR1:falso'],
        )->assertConflict()
            ->assertJsonPath('data.causa', 'QR_NO_VALIDO');

        $this->ingresar_por_codigo($this->estudiantes[2])->assertConflict();

        // Quedan como intentos rechazados del sistema, cada uno con su causa.
        $causas = DB::table('incidencias')
            ->where('origen', 'SISTEMA')
            ->where('tipo', 'INTENTO_NO_AUTORIZADO')
            ->where('aula_id', $this->aula->id)
            ->pluck('causa')
            ->all();

        self::assertEqualsCanonicalizing(
            ['NO_INSCRITO', 'NO_REGISTRADO', 'QR_NO_VALIDO', 'NO_HABILITADO'],
            $causas,
        );
    }

    public function test_bug04_identificar_no_inscrito_registra_un_solo_intento(): void
    {
        $ajeno = $this->estudiantesInscritos($this->grupo($this->docenteConCuenta()), 1)[0];

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/identificar",
            ['tipo' => 'codigo', 'identificador' => $ajeno->codigo_universitario],
        )->assertOk()->assertJsonPath('data.causa', 'NO_INSCRITO');

        $this->ingresar_por_codigo($ajeno)->assertConflict();

        $this->assertDatabaseHas('incidencias', [
            'examen_id' => null,
            'estudiante_id' => $ajeno->id,
            'causa' => 'NO_INSCRITO',
        ]);
        $this->assertDatabaseCount('incidencias', 1);
    }

    public function test_bug16_validacion_en_espanol(): void
    {
        $ruta = "/api/aulas/{$this->aula->id}/punto-control/ingresos";

        $this->actingAs($this->usuario)->postJson($ruta, ['tipo' => 'otro'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.tipo.0', 'Tipo de identificación no válido')
            ->assertJsonPath('errors.identificador.0', 'Obligatorio');

        $this->actingAs($this->usuario)->postJson($ruta, [
            'tipo' => 'qr',
            'identificador' => str_repeat('a', 2049),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.identificador.0', 'Código QR no válido');
    }

    public function test_bug07_puerta_del_aula_con_examenes_cifras_y_normas(): void
    {
        $this->ingresar_por_codigo($this->estudiantes[0])->assertCreated();

        $this->actingAs($this->usuario)
            ->getJson("/api/aulas/{$this->aula->id}/punto-control")
            ->assertOk()
            ->assertJsonPath('data.aula.nombre', '691A')
            ->assertJsonPath('data.aula.edificio', 'Edificio Académico 2')
            ->assertJsonPath('data.aula.piso', '1.er piso')
            ->assertJsonPath('data.abierta', true)
            ->assertJsonPath('data.examenes.0.tipo', 'Primer parcial')
            ->assertJsonPath('data.examenes.0.ingresados', 1)
            ->assertJsonPath('data.examenes.0.asignados', 2)
            ->assertJsonPath('data.examenes.0.faltan', 1)
            ->assertJsonPath('data.examenes.0.normas', 'Sin celulares.')
            ->assertJsonPath('data.examenes.0.normas_marcadas', ['Llevar carnet']);

        $ajeno = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($ajeno)
            ->getJson("/api/aulas/{$this->aula->id}/punto-control")
            ->assertForbidden()
            ->assertJsonPath('message', 'Sin asignación en esta aula.');
    }

    public function test_bug23_ver_todos_con_busqueda_y_total(): void
    {
        foreach ([0, 3] as $indice) {
            $this->ingresar_por_codigo($this->estudiantes[$indice])->assertCreated();
        }

        $ruta = "/api/aulas/{$this->aula->id}/punto-control/ingresos";

        $this->actingAs($this->usuario)->getJson($ruta)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->usuario)
            ->getJson($ruta.'?todos=1&buscar='.$this->estudiantes[3]->codigo_universitario)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', $this->estudiantes[3]->codigo_universitario)
            ->assertJsonPath('data.0.hora', substr(self::cadena(DB::table('ingresos')
                ->where('estudiante_id', $this->estudiantes[3]->id)
                ->value('registrado_en')), 11, 5));
    }

    public function test_n1_cambio_de_aula_admite_como_excepcion(): void
    {
        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/excepciones",
            [
                'tipo' => 'EXCEPCION_CAMBIO_AULA',
                'motivo' => 'El aula 691B está cerrada.',
                'estudiante_id' => $this->estudiantes[1]->id,
                'examen_id' => $this->examen->id,
            ],
        )->assertCreated()
            ->assertJsonPath('data.admitido', true)
            ->assertJsonPath('message', 'Admitido como excepción');

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $this->examen->id,
            'estudiante_id' => $this->estudiantes[1]->id,
            'aula_id' => $this->aula->id,
            'es_excepcion' => true,
            'motivo_excepcion' => 'El aula 691B está cerrada.',
        ]);
        $this->assertDatabaseHas('incidencias', ['tipo' => 'CAMBIO_AULA', 'origen' => 'PERSONA']);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->usuario->id,
            'operacion' => 'puerta.excepcion',
        ]);

        // Con ingreso, la siguiente excepcion ya no admite.
        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/excepciones",
            [
                'tipo' => 'EXCEPCION_CAMBIO_AULA',
                'motivo' => 'Otra vez el mismo caso.',
                'estudiante_id' => $this->estudiantes[1]->id,
            ],
        )->assertConflict();
    }

    public function test_n2_y_n7_expulsion_en_bitacora_y_segunda_vez(): void
    {
        $this->ingresar_por_codigo($this->estudiantes[0])->assertCreated();
        $ingreso = self::entero(DB::table('ingresos')->value('id'));
        $ruta = "/api/aulas/{$this->aula->id}/punto-control/ingresos/{$ingreso}/expulsion";

        $hora = $this->actingAs($this->usuario)
            ->postJson($ruta, ['motivo' => 'Uso de celular.'])
            ->assertCreated()
            ->json('data.hora');

        $hora = self::cadena($hora);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $hora);

        $this->actingAs($this->usuario)
            ->postJson($ruta, ['motivo' => 'Uso de celular.'])
            ->assertConflict()
            ->assertJsonPath('message', 'Ya estaba expulsado.')
            ->assertJsonPath('data.hora', $hora);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->usuario->id,
            'operacion' => 'puerta.expulsion',
            'tabla_afectada' => 'ingresos',
            'registro_id' => $ingreso,
        ]);

        $this->ingresar_por_codigo($this->estudiantes[0])
            ->assertConflict()
            ->assertJsonPath('data.veredicto', 'EXPULSADO')
            ->assertJsonPath('data.dato', 'Expulsado a las '.$hora);
    }

    public function test_n2_incidencia_en_bitacora_y_n5_catalogo_y_maximo(): void
    {
        $ruta = "/api/examenes/{$this->examen->id}/incidencias";

        $this->actingAs($this->usuario)->postJson($ruta, [
            'aula_id' => $this->aula->id,
            'tipo' => 'CUALQUIER_TEXTO',
            'detalle' => str_repeat('x', 2001),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.tipo.0', 'Elija el tipo de incidencia.')
            ->assertJsonPath('errors.detalle.0', 'Máximo 2000 caracteres');

        $this->actingAs($this->usuario)->postJson($ruta, [
            'aula_id' => $this->aula->id,
            'tipo' => 'MATERIAL_NO_PERMITIDO',
            'detalle' => 'Apuntes sobre la mesa.',
        ])->assertCreated()
            ->assertJsonPath('data.tipo_texto', 'Material no permitido')
            ->assertJsonPath('data.gravedad', 'MEDIA');

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->usuario->id,
            'operacion' => 'incidencia.registrar',
            'tabla_afectada' => 'incidencias',
        ]);
    }

    public function test_n3_fuera_de_la_ventana_no_registra_incidencia_ni_expulsion(): void
    {
        $this->ingresar_por_codigo($this->estudiantes[0])->assertCreated();
        $ingreso = self::entero(DB::table('ingresos')->value('id'));

        // El examen pasa a otro dia: la puerta queda cerrada.
        $this->examen->update(['fecha' => now()->addDays(40)->toDateString()]);

        $this->actingAs($this->usuario)->postJson(
            "/api/examenes/{$this->examen->id}/incidencias",
            [
                'aula_id' => $this->aula->id,
                'tipo' => 'OTRA',
                'detalle' => 'Fuera de la ventana.',
            ],
        )->assertConflict()->assertJsonPath('message', 'Solo el día del examen.');

        $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos/{$ingreso}/expulsion",
            ['motivo' => 'Fuera de la ventana.'],
        )->assertConflict()->assertJsonPath('message', 'Fuera del horario del examen.');

        $this->assertDatabaseCount('incidencias', 0);
        $this->assertSame(0, DB::table('incidencias')->where('tipo', 'EXPULSION')->count());
    }

    public function test_bug15_ingreso_en_bitacora_legible(): void
    {
        $this->ingresar_por_codigo($this->estudiantes[0])->assertCreated();

        $descripcion = self::cadena(DB::table('bitacora_operaciones')
            ->where('operacion', 'INGRESO_ADMITIDO')
            ->value('descripcion'));

        self::assertStringContainsString($this->estudiantes[0]->codigo_universitario, $descripcion);
        self::assertStringContainsString('Aula 691A', $descripcion);
        self::assertStringNotContainsString('Examen '.$this->examen->id, $descripcion);
    }

    public function test_alcance_ignora_la_tabla_vieja_de_seguimiento(): void
    {
        $auxiliar = $this->usuarioConPermisos(['punto_control']);

        DB::table('asignaciones_seguimiento_aula')->insert([
            'usuario_id' => $auxiliar->id,
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'asignado_por' => $this->usuario->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $alcance = app(AlcancePuntoControl::class);

        self::assertFalse($alcance->puedeOperar($auxiliar->id, $this->examen->id, $this->aula->id));
        self::assertSame([], $alcance->aulasOperables($auxiliar->id, $this->examen->id));

        DB::table('examen_aula_personal')->insert([
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'usuario_id' => $auxiliar->id,
            'rol' => 'apoyo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertTrue($alcance->puedeOperar($auxiliar->id, $this->examen->id, $this->aula->id));
        self::assertSame([$this->aula->id], $alcance->aulasOperables($auxiliar->id, $this->examen->id));
        self::assertEqualsCanonicalizing(
            [$this->aula->id, $this->otraAula->id],
            $alcance->aulasOperables($this->usuario->id, $this->examen->id),
        );
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function ingresar_por_codigo(Estudiante $estudiante): TestResponse
    {
        return $this->actingAs($this->usuario)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => $estudiante->codigo_universitario],
        );
    }

    private static function cadena(mixed $valor): string
    {
        self::assertTrue(is_string($valor) || is_int($valor), 'Se esperaba un texto.');

        return (string) $valor;
    }

    private static function entero(mixed $valor): int
    {
        self::assertIsInt($valor);

        return $valor;
    }
}
