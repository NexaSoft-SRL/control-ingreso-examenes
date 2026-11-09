<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use stdClass;
use Tests\TestCase;

/**
 * `composer datos:demo` sobre la oferta real de `database/umss/genda`.
 */
final class DatosDemoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Lo unico que crece al volver a sembrar es la historia: cada corrida
     * del importador deja su fila y su asiento de bitacora.
     */
    private const HISTORIA = ['importaciones_oferta', 'bitacora_operaciones'];

    public function test_the_demo_seeder_can_run_twice_without_duplicating(): void
    {
        $this->seed(DemoSeeder::class);

        $antes = $this->conteos();

        $this->assertSame(4218, $antes['estudiantes']);
        $this->assertSame(3169, $antes['grupos']);
        $this->assertSame(56, $antes['edificios']);
        $this->assertSame(23, $antes['examenes']);
        $this->assertSame(3, $antes['conflictos_padron']);
        $this->assertSame(4, $antes['auxiliares']);

        $this->seed(DemoSeeder::class);

        $this->assertSame($antes, $this->conteos());
        // La fuente no cambio: la segunda corrida no vuelve a importar.
        $this->assertSame(4, DB::table('importaciones_oferta')->where('estado', 'IMPORTADA')->count());
    }

    public function test_no_student_without_subjects_and_no_teacher_account_with_an_empty_group(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(0, DB::table('estudiantes')
            ->whereNotExists(static fn (Builder $q) => $q->from('inscripciones')->whereColumn('inscripciones.estudiante_id', 'estudiantes.id'))
            ->count());

        $this->assertSame(0, DB::table('grupos')
            ->join('docentes', 'docentes.id', '=', 'grupos.docente_id')
            ->whereNotNull('docentes.user_id')
            ->whereNotExists(static fn (Builder $q) => $q->from('inscripciones')->whereColumn('inscripciones.grupo_id', 'grupos.id'))
            ->count());
    }

    public function test_the_demo_seeder_leaves_accounts_and_an_exam_open_right_now(): void
    {
        // Sembrar tarda segundos: sin congelar el reloj, un cambio de minuto
        // a mitad de la prueba corre la cuenta de «hace seis minutos».
        $this->freezeTime();

        $this->seed(DemoSeeder::class);

        // Cuentas de los tres roles, con su contrasena documentada.
        $cuentas = [
            'admin' => ['Administrador', DemoSeeder::CLAVE_ADMINISTRADOR],
            'leticia blanco' => ['Docente', DemoSeeder::CLAVE_DOCENTE],
            'diego mamani' => ['Auxiliar', DemoSeeder::CLAVE_AUXILIAR],
            'corina flores' => ['Docente', DemoSeeder::TEMPORAL_PRIMER_INGRESO],
        ];

        foreach ($cuentas as $usuario => [$rol, $clave]) {
            $cuenta = $this->fila(
                DB::table('users')
                    ->join('roles', 'roles.id', '=', 'users.role_id')
                    ->where('usuario', $usuario)
                    ->first(['users.password', 'users.password_changed_at', 'users.password_temporal_expira_en', 'roles.name'])
            );

            $this->assertSame($rol, $cuenta->name);
            $this->assertTrue(Hash::check($clave, $this->texto($cuenta->password)), $usuario);

            if ($usuario === 'corina flores') {
                $this->assertNull($cuenta->password_changed_at);
                $this->assertGreaterThan(now(), Carbon::parse($this->texto($cuenta->password_temporal_expira_en)));
            } else {
                $this->assertNotNull($cuenta->password_changed_at);
            }
        }

        $this->assertDatabaseHas('users', ['usuario' => 'victor montano', 'is_active' => false]);

        // El periodo de la oferta contiene el dia de hoy.
        $ahora = DemoSeeder::ahoraLocal();

        $this->assertTrue(
            DB::table('periodos')
                ->where('codigo', '2/2026')
                ->whereDate('fecha_inicio', '<=', $ahora->toDateString())
                ->whereDate('fecha_fin', '>=', $ahora->toDateString())
                ->exists()
        );

        // El examen de hoy de la docente de ejemplo: empezo hace minutos y
        // no termino.
        $fila = $this->fila(
            DB::table('examenes')
                ->join('users', 'users.id', '=', 'examenes.creado_por')
                ->join('asignaturas', 'asignaturas.id', '=', 'examenes.asignatura_id')
                ->where('users.usuario', 'leticia blanco')
                ->where('asignaturas.codigo', '2010010')
                ->where('examenes.tipo', 'PRIMER_PARCIAL')
                ->first(['examenes.id', 'examenes.fecha', 'examenes.hora_inicio', 'examenes.duracion_minutos'])
        );

        $examenId = (int) $this->texto($fila->id);
        $fecha = $this->texto($fila->fecha);
        $hora = $this->texto($fila->hora_inicio);

        $inicio = Carbon::parse($fecha.' '.$hora, $ahora->getTimezone());
        $fin = $inicio->copy()->addMinutes((int) $this->texto($fila->duracion_minutos));

        $this->assertTrue($inicio->lessThanOrEqualTo($ahora));
        $this->assertTrue($fin->greaterThan($ahora));
        $this->assertSame(6, (int) round($inicio->diffInMinutes($ahora->copy()->startOfMinute())));

        // Cinco aulas con quien las atiende, apoyo y un codigo de aula.
        $this->assertSame(5, DB::table('examen_aula')->where('examen_id', $examenId)->count());
        $this->assertSame(4, DB::table('examen_aula')->where('examen_id', $examenId)->distinct()->count('encargado_id'));
        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examenId, 'codigo_acceso' => DemoSeeder::CODIGO_AULA_624]);
        $this->assertSame(
            2,
            DB::table('examen_aula_apoyo')
                ->join('examen_aula', 'examen_aula.id', '=', 'examen_aula_apoyo.examen_aula_id')
                ->where('examen_aula.examen_id', $examenId)
                ->count()
        );

        // Habilitacion completa y repartida, QR emitidos, parte adentro.
        $inscritos = DB::table('inscripciones')
            ->whereIn('grupo_id', DB::table('examen_grupo')->where('examen_id', $examenId)->select('grupo_id'))
            ->count();
        $habilitados = DB::table('habilitaciones')->where('examen_id', $examenId)->where('habilitado', true)->count();
        $ingresos = DB::table('ingresos')->where('examen_id', $examenId)->count();

        $this->assertSame($inscritos, DB::table('habilitaciones')->where('examen_id', $examenId)->count());
        $this->assertSame(9, $inscritos - $habilitados);
        $this->assertSame(0, DB::table('habilitaciones')->where('examen_id', $examenId)->where('habilitado', true)->whereNull('aula_id')->count());
        $this->assertSame($habilitados, DB::table('codigos_qr')->where('examen_id', $examenId)->count());
        $this->assertGreaterThan($habilitados * 0.3, $ingresos);
        $this->assertLessThan($habilitados * 0.5, $ingresos);

        // Incidencias atendidas y sin atender, una expulsion y los
        // intentos que rechazo la puerta.
        $incidencias = DB::table('incidencias')->where('examen_id', $examenId);

        $this->assertSame(5, (clone $incidencias)->where('origen', 'SISTEMA')->count());
        $this->assertSame(1, (clone $incidencias)->where('tipo', 'INGRESO_REPETIDO')->whereNotNull('ingreso_id')->count());
        $this->assertSame(1, (clone $incidencias)->whereExists(
            static fn (Builder $foto) => $foto->from('evidencias_incidencia')->whereColumn('evidencias_incidencia.incidencia_id', 'incidencias.id')
        )->count());
        $this->assertSame(1, (clone $incidencias)->where('tipo', 'EXPULSION')->whereNotNull('ingreso_id')->count());
        $this->assertSame(3, (clone $incidencias)->where('origen', 'PERSONA')->whereNull('atendida_en')->count());
        $this->assertGreaterThanOrEqual(1, (clone $incidencias)->where('origen', 'PERSONA')->whereNotNull('atendida_en')->count());

        // Un aula compartida por dos examenes a la misma hora.
        $compartidas = DB::table('examen_aula as propia')
            ->join('examen_aula as otra', 'otra.aula_id', '=', 'propia.aula_id')
            ->join('examenes as otro', 'otro.id', '=', 'otra.examen_id')
            ->where('propia.examen_id', $examenId)
            ->where('otra.examen_id', '<>', $examenId)
            ->where('otro.fecha', $fecha)
            ->where('otro.hora_inicio', $hora)
            ->count();

        $this->assertSame(1, $compartidas);

        // Los demas estados: sin aulas, con aula y sin habilitar, a medio
        // habilitar; y la carga alta con sus 18 examenes.
        $cargaAlta = $this->fila(
            DB::table('examenes')
                ->join('users', 'users.id', '=', 'examenes.creado_por')
                ->groupBy('users.id', 'users.usuario', 'users.password')
                ->orderByRaw('count(*) desc')
                ->selectRaw('users.usuario, users.password, count(*) as examenes')
                ->first()
        );
        $this->assertSame(18, (int) $this->texto($cargaAlta->examenes));
        // Su usuario es nombre y apellido, como el de cualquier cuenta.
        $this->assertMatchesRegularExpression('/^[a-z]+ [a-z]+$/', $this->texto($cargaAlta->usuario));
        $this->assertNotSame('cargaalta', $cargaAlta->usuario);
        $this->assertTrue(Hash::check(DemoSeeder::CLAVE_DOCENTE, $this->texto($cargaAlta->password)));
        $this->assertGreaterThan(0, DB::table('examenes')->whereNotIn('id', DB::table('examen_aula')->select('examen_id'))->count());
        $this->assertGreaterThan(
            0,
            DB::table('examenes')
                ->whereIn('id', DB::table('examen_aula')->select('examen_id'))
                ->whereNotIn('id', DB::table('habilitaciones')->select('examen_id'))
                ->count()
        );
    }

    /**
     * @return array<string, int>
     */
    private function conteos(): array
    {
        $conteos = [];

        $tablas = DB::select("select table_name from information_schema.tables where table_schema = current_schema() and table_type = 'BASE TABLE' order by table_name");

        foreach ($tablas as $tabla) {
            $nombre = $this->texto($this->fila($tabla)->table_name);

            if (! in_array($nombre, self::HISTORIA, true)) {
                $conteos[$nombre] = DB::table($nombre)->count();
            }
        }

        return $conteos;
    }

    private function fila(mixed $fila): stdClass
    {
        if (! $fila instanceof stdClass) {
            $this->fail('Falta una fila que el seeder debía dejar.');
        }

        return $fila;
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }
}
