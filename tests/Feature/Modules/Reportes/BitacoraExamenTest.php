<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La bitacora de un examen: todo lo ocurrido, en orden, para un docente
 * del examen. Solo lectura.
 */
final class BitacoraExamenTest extends TestCase
{
    use EscenarioDeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::AHORA);
    }

    public function test_a_teacher_reads_everything_that_happened_in_the_exam_in_order(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación'));
        [$a, $b] = [$this->aula('691A'), $this->aula('691B')];
        // Empezo a las 08:15.
        $examen = $this->examen($docente, [$grupo], [$a, $b]);
        [$primero, $segundo, $ajeno] = $this->estudiantesInscritos($grupo, 3);
        $this->habilitar($examen, [$primero, $segundo], $a);

        $this->travelTo(self::HOY.' 08:10:00');
        $this->ingresar($examen, $primero, $a);
        $this->travelTo(self::HOY.' 08:18:00');
        // Sugerida la 691A, entro por la 691B y despues del inicio.
        $this->ingresar($examen, $segundo, $b);
        $this->travelTo(self::HOY.' 08:19:00');
        $this->intento($examen, $a, $ajeno, 'NO_HABILITADO');
        $this->travelTo(self::HOY.' 08:30:00');
        $this->reportar($examen, $b, 'DISPOSITIVO_NO_PERMITIDO', 'Celular sobre la mesa.', $segundo);
        DB::table('incidencias')->where('tipo', 'DISPOSITIVO_NO_PERMITIDO')->update([
            'atendida_en' => '2026-10-12 08:35:00',
            'atendida_por' => $docente->user_id,
        ]);
        $examen->forceFill(['finalizado_en' => '2026-10-12 09:50:00', 'finalizado_por' => $docente->user_id])->save();
        DB::table('bitacora_operaciones')->insert([
            'usuario_id' => $docente->user_id,
            'operacion' => 'examen.finalizar',
            'tabla_afectada' => 'examenes',
            'registro_id' => $examen->id,
            'descripcion' => 'Primer parcial finalizado.',
            'fecha_operacion' => '2026-10-12 09:50:00',
        ]);

        $respuesta = $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertOk();

        $this->assertSame(
            [
                ['2026-10-12 08:10:00', 'ingreso', 'Ingresó'],
                ['2026-10-12 08:15:00', 'examen', 'Inicio del examen'],
                ['2026-10-12 08:18:00', 'ingreso', 'Ingresó'],
                ['2026-10-12 08:19:00', 'rechazo', 'Intento rechazado'],
                ['2026-10-12 08:30:00', 'incidencia', 'Dispositivo no permitido'],
                ['2026-10-12 08:35:00', 'incidencia', 'Dispositivo no permitido: atendida'],
                ['2026-10-12 09:50:00', 'examen', 'Examen finalizado'],
                ['2026-10-12 09:50:00', 'examen', 'Cierre del examen'],
            ],
            array_map(
                static fn (mixed $s): array => is_array($s)
                    ? [$s['instante'] ?? null, $s['categoria'] ?? null, $s['titulo'] ?? null]
                    : [],
                (array) $respuesta->json('data'),
            ),
        );
        $respuesta
            ->assertJsonPath('data.0.aula', '691A')
            ->assertJsonPath('data.0.codigo', $primero->codigo_universitario)
            ->assertJsonPath('data.0.detalle', 'por código SIS')
            ->assertJsonPath('data.0.por', 'Blanco Coca Leticia')
            ->assertJsonPath('data.1.detalle', 'Programado 08:15 – 09:45 · 90 min')
            ->assertJsonPath('data.2.detalle', 'por código SIS · su aula sugerida era la 691A · después del inicio')
            ->assertJsonPath('data.3.detalle', 'no habilitado · La puerta rechazó el ingreso.')
            ->assertJsonPath('data.3.codigo', $ajeno->codigo_universitario)
            ->assertJsonPath('data.0.gravedad', null)
            ->assertJsonPath('data.3.gravedad', 'GRAVE')
            ->assertJsonPath('data.4.gravedad', 'GRAVE')
            ->assertJsonPath('data.4.gravedad_texto', 'Grave')
            ->assertJsonPath('data.5.gravedad', 'GRAVE')
            ->assertJsonPath('data.7.detalle', 'Ingresaron 2 de 2 habilitados · 0 no se presentaron · 1 incidencia · 1 intento rechazado');
    }

    public function test_it_says_who_controlled_each_room_and_when_the_day_of_the_exam_ended(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación'));
        [$a, $b] = [$this->aula('691A'), $this->aula('691B')];
        $examen = $this->examen($docente, [$grupo], [$a, $b]);
        [$primero, $segundo] = $this->estudiantesInscritos($grupo, 2);
        $this->habilitar($examen, [$primero, $segundo], $a);
        $auxiliar = $this->auxiliar();
        $auxiliar->forceFill(['name' => 'Rojas Vera Ana'])->save();
        $this->ponerACargo($examen, $b, $auxiliar);
        $this->ponerDeApoyo($examen, $a, $auxiliar);
        $asignadaB = DB::table('examen_aula')->where('examen_id', $examen->id)->where('aula_id', $b->id)->value('id');
        foreach (['Mamani Diego' => 'AUTORIZADO', 'Quispe Luis' => 'RECHAZADO', 'Flores Eva' => 'CORTADO'] as $nombre => $estado) {
            DB::table('accesos_aula')->insert([
                'examen_aula_id' => $asignadaB,
                'nombre' => $nombre,
                'estado' => $estado,
                'creado_en' => self::HOY.' 08:05:00',
            ]);
        }

        $this->travelTo(self::HOY.' 08:10:00');
        $this->ingresar($examen, $primero, $a);
        $this->travelTo(self::HOY.' 08:31:00');
        $this->ingresar($examen, $segundo, $a);

        // Al dia siguiente, sin finalizacion manual.
        $this->travelTo('2026-10-13 10:00:00');
        $respuesta = $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertOk()
            ->assertJsonPath('aulas.0.aula', '691A')
            ->assertJsonPath('aulas.0.controlo', 'Blanco Coca Leticia (docente), Rojas Vera Ana (auxiliar)')
            ->assertJsonPath('aulas.0.ingresos', 2)
            ->assertJsonPath('aulas.0.primer_ingreso', '08:10')
            ->assertJsonPath('aulas.0.ultimo_ingreso', '08:31')
            ->assertJsonPath('aulas.1.aula', '691B')
            ->assertJsonPath('aulas.1.personal', [['nombre' => 'Rojas Vera Ana', 'rol' => 'auxiliar']])
            ->assertJsonPath('aulas.1.con_codigo', ['Mamani Diego', 'Flores Eva'])
            ->assertJsonPath('aulas.1.controlo', 'Rojas Vera Ana (auxiliar), Mamani Diego (código de aula), Flores Eva (código de aula)')
            ->assertJsonPath('aulas.1.ingresos', 0)
            ->assertJsonPath('aulas.1.primer_ingreso', null);

        // Sin finalizacion manual, el dia del examen cierra a las 23:59:59.
        $this->assertSame(
            [
                ['2026-10-12 23:59:59', 'examen', 'Fin del día del examen'],
                ['2026-10-12 23:59:59', 'examen', 'Cierre del examen'],
            ],
            collect((array) $respuesta->json('data'))
                ->take(-2)
                ->map(static fn (mixed $s): array => is_array($s) ? [$s['instante'] ?? null, $s['categoria'] ?? null, $s['titulo'] ?? null] : [])
                ->values()
                ->all(),
        );

        $texto = $this->texto($this->hoja(
            $this->actingAs($this->cuentaDe($docente))->get("/api/reportes/bitacora/exportar?formato=xlsx&examen={$examen->id}")
        ));
        $this->assertStringContainsString('Aula 691A · controló: Blanco Coca Leticia (docente), Rojas Vera Ana (auxiliar) · 2 ingresos (08:10 – 08:31).', $texto);
        $this->assertStringContainsString('Aula 691B · controló: Rojas Vera Ana (auxiliar), Mamani Diego (código de aula), Flores Eva (código de aula) · 0 ingresos.', $texto);
        $this->assertStringContainsString('Fin del día del examen', $texto);
    }

    public function test_the_close_lists_each_enabled_student_that_did_not_show_up(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación'));
        $a = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$a]);
        [$vino, $falto, $inhabilitado] = $this->estudiantesInscritos($grupo, 3);
        $falto->forceFill(['apellidos' => 'Zurita Paz', 'nombres' => 'Omar'])->save();
        $this->habilitar($examen, [$vino, $falto], $a);
        $this->inhabilitar($examen, [$inhabilitado], 'Adeuda la matrícula');
        $this->travelTo(self::HOY.' 08:10:00');
        $this->ingresar($examen, $vino, $a);
        $examen->forceFill(['finalizado_en' => self::HOY.' 09:50:00', 'finalizado_por' => $docente->user_id])->save();

        $sucesos = collect((array) $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertOk()
            ->json('data'));

        $cierre = $sucesos->firstWhere('titulo', 'Cierre del examen');
        self::assertIsArray($cierre);
        $this->assertSame('Ingresaron 1 de 2 habilitados · 1 no se presentó · 0 incidencias · 0 intentos rechazados', $cierre['detalle']);
        $this->assertSame(
            [['ausente', 'No se presentó', 'Zurita Paz Omar', $falto->codigo_universitario, '691A', self::HOY.' 09:50:00']],
            $sucesos->where('categoria', 'ausente')
                ->map(static fn (mixed $s): array => is_array($s) ? [$s['categoria'], $s['titulo'], $s['estudiante'], $s['codigo'], $s['aula'], $s['instante']] : [])
                ->values()
                ->all(),
        );
    }

    public function test_the_room_operations_of_the_exam_are_in_its_log(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación'));
        [$a, $b] = [$this->aula('691A'), $this->aula('691B')];
        $examen = $this->examen($docente, [$grupo], [$a, $b]);
        $otro = $this->examen($docente, [$this->grupo($docente)], [$a]);
        $id = static fn (mixed $valor): int => is_numeric($valor) ? (int) $valor : 0;
        $asignadaA = $id(DB::table('examen_aula')->where('examen_id', $examen->id)->where('aula_id', $a->id)->value('id'));
        $asignadaB = $id(DB::table('examen_aula')->where('examen_id', $examen->id)->where('aula_id', $b->id)->value('id'));
        $deOtro = $id(DB::table('examen_aula')->where('examen_id', $otro->id)->value('id'));
        $acceso = DB::table('accesos_aula')->insertGetId([
            'examen_aula_id' => $asignadaB,
            'nombre' => 'Mamani Diego',
            'estado' => 'AUTORIZADO',
            'creado_en' => self::HOY.' 08:00:00',
        ]);
        $asentar = fn (string $operacion, string $tabla, int $registro, string $cuando, string $texto) => DB::table('bitacora_operaciones')->insert([
            'usuario_id' => $docente->user_id,
            'operacion' => $operacion,
            'tabla_afectada' => $tabla,
            'registro_id' => $registro,
            'descripcion' => $texto,
            'fecha_operacion' => self::HOY.' '.$cuando,
        ]);
        $asentar('codigo_aula.generar', 'examen_aula', $asignadaA, '07:50:00', 'Código de acceso del aula generado.');
        $asentar('codigo_aula.regenerar', 'examen_aula', $deOtro, '07:51:00', 'De otro examen.');
        $asentar('acceso_aula.trasladar', 'accesos_aula', $acceso, '08:20:00', 'Mamani Diego pasó a controlar el aula 691B.');
        $asentar('examen.mover_personal', 'examen_aula', $asignadaB, '08:25:00', 'Rojas Ana pasó del aula 691A al aula 691B.');
        $asentar('habilitacion.mover_aula', 'examenes', $examen->id, '08:30:00', '3 estudiantes pasaron del aula 691A al aula 691B.');

        $this->travelTo(self::HOY.' 08:40:00');
        $sucesos = collect((array) $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertOk()
            ->json('data'))
            ->where('por', 'Blanco Coca Leticia')
            ->map(static fn (mixed $s): array => is_array($s) ? [$s['instante'], $s['categoria'], $s['titulo'], $s['aula']] : [])
            ->values()
            ->all();

        $this->assertSame([
            [self::HOY.' 07:50:00', 'acceso', 'Código de aula generado', '691A'],
            [self::HOY.' 08:20:00', 'acceso', 'Control con código pasó a otra aula', '691B'],
            [self::HOY.' 08:25:00', 'examen', 'Personal cambiado de aula', '691B'],
            [self::HOY.' 08:30:00', 'examen', 'Estudiantes cambiados de aula', null],
        ], $sucesos);
    }

    public function test_a_future_exam_has_no_start_in_its_log_yet(): void
    {
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente, $this->asignatura())], [$this->aula('691A')], [
            'fecha' => '2026-10-20',
        ]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('aulas.0.controlo', $docente->nombre_completo.' (docente)');
    }

    public function test_only_a_teacher_of_the_exam_reads_it(): void
    {
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente, $this->asignatura())], [$this->aula()]);
        $ruta = "/api/reportes/examenes/{$examen->id}/bitacora";

        $this->getJson($ruta)->assertUnauthorized();
        $this->actingAs($this->cuentaDe($this->docente('Otro Docente')))
            ->getJson($ruta)
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/examenes/999999/bitacora')
            ->assertNotFound();
    }

    public function test_the_administrator_reads_and_downloads_the_log_of_any_exam(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura());
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$aula]);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->habilitar($examen, [$estudiante], $aula);
        $this->ingresar($examen, $estudiante, $aula);
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->getJson("/api/reportes/universidad/examenes/{$examen->id}/bitacora")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.titulo', 'Ingresó');

        $this->actingAs($administrador)
            ->get("/api/reportes/universidad/bitacora/exportar?formato=xlsx&examen={$examen->id}")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="bitacora_'.self::HOY.'.xlsx"');

        // El docente no usa la ruta de la universidad.
        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/reportes/universidad/examenes/{$examen->id}/bitacora")
            ->assertForbidden();
    }
}
