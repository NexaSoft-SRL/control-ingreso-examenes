<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Monitoreo;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-22: el examen en curso, aula por aula.
 */
final class EnCursoTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $cuenta;

    private Docente $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cuenta = $this->usuarioConRol('Docente', ['nombre' => 'Blanco Coca Leticia']);
        $this->docente = $this->docenteConCuenta($this->cuenta, 'Blanco Coca Leticia');
    }

    public function test_el_selector_ofrece_los_examenes_de_hoy_y_del_proximo_dia_con_examen(): void
    {
        $grupo = $this->grupo($this->docente);
        $hoy = $this->examen($this->docente, [$grupo]);
        $ayer = $this->examen($this->docente, [$this->grupo($this->docente)], [], [
            'fecha' => now()->subDay()->toDateString(),
        ]);
        $proximo = $this->examen($this->docente, [$this->grupo($this->docente)], [], [
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '08:00:00',
            'tipo' => TipoExamen::SegundoParcial,
        ]);
        $masTarde = $this->examen($this->docente, [$this->grupo($this->docente)], [], [
            'fecha' => now()->addDays(5)->toDateString(),
        ]);

        // Examen de otro docente que incluye un grupo suyo.
        $otro = $this->docenteConCuenta();
        $incluido = $this->examen($otro, [$this->grupo($otro), $this->grupo($this->docente)]);

        $respuesta = $this->actingAs($this->cuenta)
            ->getJson('/api/en-curso')
            ->assertOk();

        $ids = array_column($this->lista($respuesta->json('data')), 'id');

        $this->assertEqualsCanonicalizing([$hoy->id, $incluido->id, $proximo->id], $ids);
        $this->assertNotContains($ayer->id, $ids);
        $this->assertNotContains($masTarde->id, $ids);

        $respuesta
            ->assertJsonPath('data.0.abierto', true)
            ->assertJsonPath('data.0.tipo_texto', 'Primer parcial')
            ->assertJsonPath('data.2.id', $proximo->id)
            ->assertJsonPath('data.2.hora', '08:00')
            ->assertJsonPath('data.2.tipo_texto', 'Segundo parcial')
            ->assertJsonPath('data.2.abierto', false);
    }

    public function test_una_cuenta_sin_examenes_recibe_la_lista_vacia(): void
    {
        $this->actingAs($this->cuenta)
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_el_tablero_da_las_cifras_por_aula_quien_atiende_y_el_ultimo_registro(): void
    {
        $grupo = $this->grupo($this->docente);
        $edificio = $this->edificio();
        $aulaA = $this->aula('691A', $edificio);
        $aulaB = $this->aula('691B');
        $examen = $this->examen($this->docente, [$grupo], [$aulaA, $aulaB]);
        $estudiantes = $this->estudiantesInscritos($grupo, 7);
        $this->habilitar($examen, array_slice($estudiantes, 0, 4), $aulaA);
        $this->habilitar($examen, array_slice($estudiantes, 4, 2), $aulaB);
        $this->inhabilitar($examen, [$estudiantes[6]]);

        $primero = CarbonImmutable::now()->subMinutes(9)->startOfSecond();
        $ultimo = CarbonImmutable::now()->subMinutes(2)->startOfSecond();
        $this->ingresarA($examen, $estudiantes[0], $aulaA, $primero);
        $this->ingresarA($examen, $estudiantes[1], $aulaA, $ultimo);

        $auxiliar = $this->usuarioConRol('Auxiliar', ['nombre' => 'Mamani Quispe Diego']);
        $apoyo = $this->usuarioConRol('Auxiliar', ['nombre' => 'Rojas Vargas Ana']);
        $this->personal($examen, $aulaA, $auxiliar, 'a_cargo');
        $this->personal($examen, $aulaA, $apoyo, 'apoyo');
        $this->personal($examen, $aulaB, $this->cuenta, 'a_cargo');
        DB::table('examen_aula_codigos')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aulaA->id,
            'codigo' => '482913',
            'generado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $respuesta = $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.examen.id', $examen->id)
            ->assertJsonPath('data.examen.tipo_texto', 'Primer parcial')
            ->assertJsonPath('data.examen.abierto', true)
            ->assertJsonPath('data.ultimo_registro', $ultimo->toIso8601String())
            ->assertJsonPath('data.cifras.ingresaron', 2)
            ->assertJsonPath('data.cifras.faltan', 4)
            ->assertJsonPath('data.cifras.aulas', 2)
            ->assertJsonPath('data.cifras.incidencias_sin_atender', 0)
            ->assertJsonCount(2, 'data.aulas');

        $this->assertIsString($respuesta->json('data.hora_servidor'));

        $a = $this->aulaDe($respuesta->json('data.aulas'), $aulaA->id);
        $this->assertSame('691A', $a['nombre']);
        $this->assertSame($edificio->id, $a['edificio_id']);
        $this->assertSame(4, $a['asignados']);
        $this->assertSame(2, $a['ingresados']);
        $this->assertSame(2, $a['faltan']);
        $this->assertSame($ultimo->toIso8601String(), $a['ultimo_registro']);
        $this->assertSame(
            ['id' => $auxiliar->id, 'nombre' => 'Mamani Quispe Diego', 'es_docente' => false],
            $a['a_cargo'],
        );
        $this->assertSame(
            [['id' => $apoyo->id, 'nombre' => 'Rojas Vargas Ana', 'es_docente' => false]],
            $a['apoyo'],
        );
        $this->assertTrue($a['codigo_acceso']);
        $this->assertFalse($a['a_mi_cargo']);
        // La docente del examen opera la puerta aunque el aula no este a su cargo.
        $this->assertTrue($a['puede_operar']);
        $this->assertSame([], $a['compartida']);

        $b = $this->aulaDe($respuesta->json('data.aulas'), $aulaB->id);
        $this->assertNull($b['edificio_id']);
        $this->assertSame(2, $b['asignados']);
        $this->assertSame(0, $b['ingresados']);
        $this->assertSame(2, $b['faltan']);
        $this->assertNull($b['ultimo_registro']);
        $this->assertSame(
            ['id' => $this->cuenta->id, 'nombre' => 'Blanco Coca Leticia', 'es_docente' => true],
            $b['a_cargo'],
        );
        $this->assertFalse($b['codigo_acceso']);
        $this->assertTrue($b['a_mi_cargo']);
        $this->assertTrue($b['puede_operar']);
    }

    public function test_puede_operar_sigue_la_regla_de_la_puerta_en_cada_aula(): void
    {
        $propietario = $this->docenteConCuenta();
        $aulaA = $this->aula('691A');
        $aulaB = $this->aula('691B');
        // Leticia es docente de un grupo incluido: opera todas las aulas.
        $examen = $this->examen(
            $propietario,
            [$this->grupo($propietario), $this->grupo($this->docente)],
            [$aulaA, $aulaB],
        );
        $alcance = app(AlcancePuntoControl::class);

        $aulas = $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->json('data.aulas');

        foreach ([$aulaA, $aulaB] as $aula) {
            $fila = $this->aulaDe($aulas, $aula->id);
            $this->assertFalse($fila['a_mi_cargo']);
            $this->assertTrue($fila['puede_operar']);
            $this->assertSame(
                $alcance->puedeOperar($this->cuenta->id, $examen->id, $aula->id),
                $fila['puede_operar'],
            );
        }
    }

    public function test_la_incidencia_reportada_desde_en_curso_llega_al_tablero(): void
    {
        $propietario = $this->docenteConCuenta();
        $aula = $this->aula('691A');
        $examen = $this->examen(
            $propietario,
            [$this->grupo($propietario), $this->grupo($this->docente)],
            [$aula],
        );

        $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.aulas.0.puede_operar', true);

        $this->postJson("/api/examenes/{$examen->id}/incidencias", [
            'aula_id' => $aula->id,
            'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
            'detalle' => 'Celular encendido durante el examen.',
        ])->assertCreated();

        $this->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.cifras.incidencias_sin_atender', 1)
            ->assertJsonPath('data.incidencias.0.aula', '691A')
            ->assertJsonPath('data.incidencias.0.tipo', 'DISPOSITIVO_NO_PERMITIDO')
            ->assertJsonPath('data.aulas.0.incidencias_sin_atender', 1);
    }

    public function test_el_docente_asignado_a_un_aula_de_otro_examen_solo_ve_esa_aula(): void
    {
        $propietario = $this->docenteConCuenta();
        $grupo = $this->grupo($propietario);
        $aulaAjena = $this->aula('691A');
        $aulaAsignada = $this->aula('692A');
        $examen = $this->examen($propietario, [$grupo], [$aulaAjena, $aulaAsignada]);
        $estudiantes = $this->estudiantesInscritos($grupo, 3);
        $this->habilitar($examen, array_slice($estudiantes, 0, 2), $aulaAjena);
        $this->habilitar($examen, [$estudiantes[2]], $aulaAsignada);
        $this->ingresar($examen, $estudiantes[0], $aulaAjena);
        $this->ingresar($examen, $estudiantes[2], $aulaAsignada);
        $this->personal($examen, $aulaAsignada, $this->cuenta, 'a_cargo');

        $this->actingAs($this->cuenta)
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertJsonPath('data.0.id', $examen->id);

        $this->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.cifras.ingresaron', 1)
            ->assertJsonPath('data.cifras.faltan', 0)
            ->assertJsonPath('data.cifras.aulas', 1)
            ->assertJsonCount(1, 'data.aulas')
            ->assertJsonPath('data.aulas.0.aula_id', $aulaAsignada->id)
            ->assertJsonPath('data.aulas.0.a_mi_cargo', true)
            ->assertJsonPath('data.aulas.0.puede_operar', true);

        $this->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.examen_id', $examen->id)
            ->assertJsonPath('data.0.aula_id', $aulaAsignada->id);

        $this->getJson("/api/seguimiento/examenes/{$examen->id}/aulas/{$aulaAsignada->id}")
            ->assertOk()
            ->assertJsonPath('data.aula.id', $aulaAsignada->id);

        $this->getJson("/api/seguimiento/examenes/{$examen->id}/aulas/{$aulaAjena->id}")
            ->assertForbidden();
    }

    public function test_el_docente_asignado_ve_solo_las_incidencias_de_su_aula_y_no_las_atiende(): void
    {
        $propietario = $this->docenteConCuenta();
        $aulaAjena = $this->aula('691A');
        $aulaAsignada = $this->aula('692A');
        $examen = $this->examen($propietario, [$this->grupo($propietario)], [$aulaAjena, $aulaAsignada]);
        $this->personal($examen, $aulaAsignada, $this->cuenta, 'apoyo');
        $auxiliar = $this->usuarioConRol('Auxiliar');
        $propia = $this->incidencia($examen, $aulaAsignada, $auxiliar, 'Otra', now());
        $ajena = $this->incidencia($examen, $aulaAjena, $auxiliar, 'Otra', now());

        $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.incidencias')
            ->assertJsonPath('data.incidencias.0.id', $propia);

        // Atiende el docente del examen, no quien esta de apoyo en un aula.
        $this->postJson("/api/incidencias/{$propia}/atencion")
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente del examen.');
        $this->assertNull(DB::table('incidencias')->where('id', $propia)->value('atendida_en'));
        $this->assertNull(DB::table('incidencias')->where('id', $ajena)->value('atendida_en'));
    }

    public function test_los_faltantes_del_aula_nunca_son_negativos(): void
    {
        $grupo = $this->grupo($this->docente);
        $aulaA = $this->aula('A1');
        $aulaB = $this->aula('B1');
        $examen = $this->examen($this->docente, [$grupo], [$aulaA, $aulaB]);
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $this->habilitar($examen, [$estudiantes[0]], $aulaA);
        $this->habilitar($examen, [$estudiantes[1]], $aulaB);

        // Entro por la puerta del aula A aunque le tocaba la B.
        $this->ingresar($examen, $estudiantes[0], $aulaA);
        $this->ingresar($examen, $estudiantes[1], $aulaA);

        $respuesta = $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.cifras.faltan', 0);

        $a = $this->aulaDe($respuesta->json('data.aulas'), $aulaA->id);
        $this->assertSame([1, 2, 0], [$a['asignados'], $a['ingresados'], $a['faltan']]);
        $b = $this->aulaDe($respuesta->json('data.aulas'), $aulaB->id);
        $this->assertSame([1, 0, 0], [$b['asignados'], $b['ingresados'], $b['faltan']]);
    }

    public function test_examen_sin_abrir_y_sin_registros(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$this->aula()], [
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
        ]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.examen.abierto', false)
            ->assertJsonPath('data.examen.hora', '10:00')
            ->assertJsonPath('data.ultimo_registro', null)
            ->assertJsonPath('data.cifras.ingresaron', 0);
    }

    public function test_pasada_la_hora_prevista_sigue_abierto_y_avisa_hasta_que_se_finaliza(): void
    {
        if (now()->format('H:i') < '00:20') {
            self::markTestSkipped('Necesita que el examen de las 00:00 ya haya pasado su hora prevista.');
        }

        // 00:00 + 15 min: paso su hora prevista y nadie lo finalizo.
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$this->aula()], [
            'hora_inicio' => '00:00:00',
            'duracion_minutos' => 15,
        ]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.examen.abierto', true)
            ->assertJsonPath('data.examen.fin', '00:15')
            ->assertJsonPath('data.examen.excedido', true);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/finalizar")
            ->assertOk();

        $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.examen.abierto', false)
            ->assertJsonPath('data.examen.excedido', false);
    }

    public function test_senala_el_aula_compartida_con_otro_examen_a_la_misma_hora(): void
    {
        $compartida = $this->aula('617');
        $contigua = $this->aula('618');
        $otroDia = $this->aula('619');
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$compartida, $contigua, $otroDia], [
            'hora_inicio' => '09:00:00',
            'duracion_minutos' => 90,
        ]);

        $otro = $this->docenteConCuenta();
        $fisica = $this->asignatura('Fisica General');
        $this->examen($otro, [$this->grupo($otro, $fisica)], [$compartida], [
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 60,
            'tipo' => TipoExamen::Final,
        ]);
        // Empieza justo cuando termina este: no se cruza.
        $this->examen($otro, [$this->grupo($otro)], [$contigua], [
            'hora_inicio' => '10:30:00',
        ]);
        // Mismo horario, otro dia.
        $this->examen($otro, [$this->grupo($otro)], [$otroDia], [
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00:00',
        ]);

        $aulas = $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->json('data.aulas');

        $this->assertSame(['Fisica General · Examen final'], $this->aulaDe($aulas, $compartida->id)['compartida']);
        $this->assertSame([], $this->aulaDe($aulas, $contigua->id)['compartida']);
        $this->assertSame([], $this->aulaDe($aulas, $otroDia->id)['compartida']);
    }

    public function test_lista_las_incidencias_por_aula_de_la_mas_reciente_y_cuenta_las_sin_atender(): void
    {
        $grupo = $this->grupo($this->docente);
        $aulaA = $this->aula('691A');
        $aulaB = $this->aula('691B');
        $examen = $this->examen($this->docente, [$grupo], [$aulaA, $aulaB]);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $auxiliar = $this->usuarioConRol('Auxiliar', ['nombre' => 'Mamani Quispe Diego']);

        $antigua = $this->incidencia($examen, $aulaA, $auxiliar, 'MATERIAL_NO_PERMITIDO', now()->subMinutes(20));
        $reciente = $this->incidencia($examen, $aulaA, $auxiliar, 'SUPLANTACION', now()->subMinutes(2), $estudiante);
        $atendida = $this->incidencia($examen, $aulaB, $auxiliar, 'OTRA', now()->subMinutes(10));
        DB::table('incidencias')->where('id', $atendida)->update([
            'atendida_por' => $this->cuenta->id,
            'atendida_en' => now()->subMinutes(5),
        ]);

        // Los rechazos de la puerta no son incidencias reportadas.
        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aulaB->id,
            'reportada_por' => $auxiliar->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_HABILITADO',
            'detalle' => 'No esta habilitado.',
            'ocurrida_en' => now(),
        ]);

        // Una incidencia de otro examen no se mezcla.
        $otro = $this->examen($this->docente, [$this->grupo($this->docente)], [$aulaA]);
        $this->incidencia($otro, $aulaA, $auxiliar, 'Ajena', now());

        $respuesta = $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.cifras.incidencias_sin_atender', 2)
            ->assertJsonCount(3, 'data.incidencias')
            ->assertJsonPath('data.incidencias.0.id', $reciente)
            ->assertJsonPath('data.incidencias.0.aula', '691A')
            ->assertJsonPath('data.incidencias.0.tipo', 'SUPLANTACION')
            ->assertJsonPath('data.incidencias.0.estudiante', $estudiante->apellidos.' '.$estudiante->nombres)
            ->assertJsonPath('data.incidencias.0.reportante', 'Mamani Quispe Diego')
            ->assertJsonPath('data.incidencias.0.rol_reportante', 'Auxiliar')
            ->assertJsonPath('data.incidencias.0.atendida', false)
            ->assertJsonPath('data.incidencias.1.id', $atendida)
            ->assertJsonPath('data.incidencias.1.atendida', true)
            ->assertJsonPath('data.incidencias.1.atendida_por', 'Blanco Coca Leticia')
            ->assertJsonPath('data.incidencias.2.id', $antigua)
            ->assertJsonPath('data.incidencias.2.estudiante', null);

        $aulas = $respuesta->json('data.aulas');
        $this->assertSame(2, $this->aulaDe($aulas, $aulaA->id)['incidencias_sin_atender']);
        $this->assertSame(0, $this->aulaDe($aulas, $aulaB->id)['incidencias_sin_atender']);
    }

    public function test_el_docente_atiende_una_incidencia_una_sola_vez_y_queda_quien_y_cuando(): void
    {
        $aula = $this->aula('691A');
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$aula]);
        $auxiliar = $this->usuarioConRol('Auxiliar');
        $id = $this->incidencia($examen, $aula, $auxiliar, 'OTRA', now()->subMinutes(3));

        $this->actingAs($this->cuenta)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertOk()
            ->assertJsonPath('message', 'Incidencia atendida.')
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.atendida', true)
            ->assertJsonPath('data.atendida_por', 'Blanco Coca Leticia');

        $this->assertSame($this->cuenta->id, $this->atendidaPor($id));
        $this->assertNotNull(DB::table('incidencias')->where('id', $id)->value('atendida_en'));

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'incidencia.atender',
            'tabla_afectada' => 'incidencias',
            'registro_id' => $id,
        ]);

        // Un segundo intento, aun de otro docente del examen, no la cambia.
        $colega = $this->usuarioConRol('Docente');
        $docenteColega = $this->docenteConCuenta($colega);
        DB::table('examen_grupo')->insert([
            'examen_id' => $examen->id,
            'grupo_id' => $this->grupo($docenteColega)->id,
        ]);

        $this->actingAs($colega)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La incidencia ya estaba atendida.');

        $this->assertSame($this->cuenta->id, $this->atendidaPor($id));
        $this->assertSame(1, DB::table('bitacora_operaciones')->where('registro_id', $id)
            ->where('operacion', 'incidencia.atender')->count());

        // No hay forma de deshacerla.
        $this->deleteJson("/api/incidencias/{$id}/atencion")->assertStatus(405);
    }

    public function test_el_docente_de_un_grupo_incluido_tambien_atiende(): void
    {
        $aula = $this->aula();
        $otro = $this->docenteConCuenta();
        $examen = $this->examen($otro, [$this->grupo($otro), $this->grupo($this->docente)], [$aula]);
        $id = $this->incidencia($examen, $aula, $this->usuarioConRol('Auxiliar'), 'Otra', now());

        $this->actingAs($this->cuenta)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertOk();

        $this->assertSame($this->cuenta->id, $this->atendidaPor($id));
    }

    public function test_atiende_por_el_examen_de_la_incidencia_y_no_una_inexistente(): void
    {
        $aula = $this->aula();
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$aula]);
        $otroExamen = $this->examen($this->docente, [$this->grupo($this->docente)], [$aula]);
        $id = $this->incidencia($otroExamen, $aula, $this->usuarioConRol('Auxiliar'), 'Otra', now());

        $this->actingAs($this->cuenta)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertOk();

        $this->postJson('/api/incidencias/999999/atencion')
            ->assertNotFound()
            ->assertJsonPath('message', 'Incidencia no encontrada.');

        $this->assertNotNull(DB::table('incidencias')->where('id', $id)->value('atendida_en'));
    }

    public function test_sin_sesion_responde_401(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)]);

        $this->getJson('/api/en-curso')->assertUnauthorized();
        $this->getJson("/api/en-curso/{$examen->id}")->assertUnauthorized();
        $this->postJson('/api/incidencias/1/atencion')->assertUnauthorized();
    }

    public function test_administrador_y_auxiliar_no_tienen_la_vista(): void
    {
        $aula = $this->aula();
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$aula]);
        $auxiliar = $this->usuarioConRol('Auxiliar');
        $this->personal($examen, $aula, $auxiliar, 'a_cargo');
        $id = $this->incidencia($examen, $aula, $auxiliar, 'Otra', now());

        foreach ([$this->usuarioConRol('Administrador'), $auxiliar] as $cuenta) {
            $this->actingAs($cuenta)->getJson('/api/en-curso')->assertForbidden();
            $this->getJson("/api/en-curso/{$examen->id}")->assertForbidden();
            $this->postJson("/api/incidencias/{$id}/atencion")
                ->assertForbidden();
        }

        $this->assertNull(DB::table('incidencias')->where('id', $id)->value('atendida_en'));
    }

    public function test_el_examen_de_otro_docente_se_rechaza_y_el_inexistente_no_se_encuentra(): void
    {
        $aula = $this->aula();
        $otro = $this->docenteConCuenta();
        $ajeno = $this->examen($otro, [$this->grupo($otro)], [$aula]);
        $id = $this->incidencia($ajeno, $aula, $this->cuenta, 'Otra', now());

        $this->actingAs($this->cuenta)
            ->getJson("/api/en-curso/{$ajeno->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Este examen no es suyo.');

        $this->postJson("/api/incidencias/{$id}/atencion")
            ->assertForbidden();

        $this->assertNull(DB::table('incidencias')->where('id', $id)->value('atendida_en'));

        $this->getJson('/api/en-curso')->assertOk()->assertExactJson(['data' => []]);

        $this->getJson('/api/en-curso/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen no encontrado.');
    }

    public function test_la_consulta_no_escribe_nada(): void
    {
        $aula = $this->aula();
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$aula]);
        $this->incidencia($examen, $aula, $this->usuarioConRol('Auxiliar'), 'Otra', now());
        $antes = DB::table('bitacora_operaciones')->count();

        $this->actingAs($this->cuenta)->getJson('/api/en-curso')->assertOk();
        $this->getJson("/api/en-curso/{$examen->id}")->assertOk();

        $this->assertSame($antes, DB::table('bitacora_operaciones')->count());
        $this->assertSame(0, DB::table('incidencias')->whereNotNull('atendida_en')->count());

        foreach (['postJson', 'putJson', 'deleteJson'] as $metodo) {
            $this->{$metodo}("/api/en-curso/{$examen->id}")->assertStatus(405);
        }
    }

    private function ingresarA(Examen $examen, Estudiante $estudiante, Aula $aula, CarbonImmutable $cuando): void
    {
        $id = $this->ingresar($examen, $estudiante, $aula);

        DB::table('ingresos')->where('id', $id)->update([
            'registrado_en' => $cuando->format('Y-m-d H:i:s'),
        ]);
    }

    private function personal(Examen $examen, Aula $aula, User $usuario, string $rol): void
    {
        DB::table('examen_aula_personal')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'usuario_id' => $usuario->id,
            'rol' => $rol,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function incidencia(
        Examen $examen,
        Aula $aula,
        User $reporta,
        string $tipo,
        DateTimeInterface $cuando,
        ?Estudiante $estudiante = null,
    ): int {
        return DB::table('incidencias')->insertGetId([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante?->id,
            'reportada_por' => $reporta->id,
            'tipo' => $tipo,
            'origen' => 'PERSONA',
            'detalle' => 'Detalle de '.$tipo,
            'ocurrida_en' => $cuando,
        ]);
    }

    private function atendidaPor(int $incidenciaId): int
    {
        $valor = DB::table('incidencias')->where('id', $incidenciaId)->value('atendida_por');
        $this->assertIsNumeric($valor);

        return (int) $valor;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lista(mixed $valor): array
    {
        $this->assertIsArray($valor);

        /** @var list<array<string, mixed>> $valor */
        return $valor;
    }

    /**
     * @return array<string, mixed>
     */
    private function aulaDe(mixed $aulas, int $aulaId): array
    {
        foreach ($this->lista($aulas) as $aula) {
            if (($aula['aula_id'] ?? null) === $aulaId) {
                return $aula;
            }
        }

        self::fail("El aula {$aulaId} no esta en el tablero.");
    }
}
