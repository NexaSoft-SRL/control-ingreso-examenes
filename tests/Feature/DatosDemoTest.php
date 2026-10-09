<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
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
        $this->assertSame(847, $antes['docentes']);
        $this->assertSame(281, $antes['aulas']);
        $this->assertSame(8, $antes['plantillas_norma']);
        $this->assertSame(0, $antes['ingresos']);

        $this->seed(DemoSeeder::class);

        $this->assertSame($antes, $this->conteos());
        $this->assertSame(8, DB::table('importaciones_oferta')->where('estado', 'IMPORTADA')->count());
    }

    public function test_the_demo_seeder_leaves_accounts_and_exams_in_every_state(): void
    {
        // Sembrar tarda segundos: sin congelar el reloj, un cambio de minuto
        // a mitad de la prueba corre la cuenta de «hace seis minutos».
        $this->freezeTime();

        $this->seed(DemoSeeder::class);

        // Cuentas documentadas: se entra con el correo.
        $cuentas = [
            'admin@umss.edu.bo' => ['administracion.academica', 'Administrador', DemoSeeder::CLAVE_ADMINISTRADOR],
            'leticia.blanco@umss.edu.bo' => ['leticia.blanco', 'Docente', DemoSeeder::CLAVE_DOCENTE],
            'carga.alta@umss.edu.bo' => ['carga.alta', 'Docente', DemoSeeder::CLAVE_DOCENTE],
            'auxiliar@umss.edu.bo' => ['diego.mamani', 'Auxiliar', DemoSeeder::CLAVE_AUXILIAR],
            'coordinacion@umss.edu.bo' => ['coordinacion', DemoSeeder::ROL_CREADO, DemoSeeder::CLAVE_COORDINADOR],
            'corina.flores@umss.edu.bo' => ['corina.flores', 'Docente', DemoSeeder::TEMPORAL],
        ];

        foreach ($cuentas as $correo => [$usuario, $rol, $clave]) {
            $cuenta = $this->fila(
                DB::table('usuarios')
                    ->join('roles', 'roles.id', '=', 'usuarios.role_id')
                    ->where('usuarios.correo', $correo)
                    ->first(['usuarios.usuario', 'usuarios.password', 'usuarios.password_changed_at', 'usuarios.password_temporal_expira_en', 'roles.name'])
            );

            $this->assertSame($usuario, $cuenta->usuario);
            $this->assertSame($rol, $cuenta->name);
            $this->assertTrue(Hash::check($clave, $this->texto($cuenta->password)), $correo);

            if ($usuario === 'corina.flores') {
                $this->assertNull($cuenta->password_changed_at);
                $this->assertGreaterThan(now(), Carbon::parse($this->texto($cuenta->password_temporal_expira_en)));
            } else {
                $this->assertNotNull($cuenta->password_changed_at);
            }
        }

        $this->assertDatabaseHas('usuarios', ['usuario' => 'victor.montano', 'is_active' => false]);

        // La cuenta de demostracion entra por HTTP y recibe su sesion.
        $this->postJson('/api/auth/login', [
            'email' => 'leticia.blanco@umss.edu.bo',
            'password' => DemoSeeder::CLAVE_DOCENTE,
        ])
            ->assertOk()
            ->assertJsonPath('user.nombre', 'Blanco Coca Leticia')
            ->assertJsonPath('user.rol', 'Docente');

        // El rol creado tiene solo sus dos pantallas.
        $this->assertSame(
            ['aulas_docentes', 'periodo_oferta'],
            DB::table('permission_role')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('roles.name', DemoSeeder::ROL_CREADO)
                ->where('roles.es_sistema', false)
                ->orderBy('permissions.name')
                ->pluck('permissions.name')
                ->all()
        );

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
                ->join('usuarios', 'usuarios.id', '=', 'examenes.creado_por')
                ->join('asignaturas', 'asignaturas.id', '=', 'examenes.asignatura_id')
                ->where('usuarios.usuario', 'leticia.blanco')
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

        // Cuatro grupos (tres de otros docentes), cinco aulas y sus normas:
        // tres plantillas marcadas y texto libre.
        $this->assertSame(4, DB::table('examen_grupo')->where('examen_id', $examenId)->count());
        $this->assertSame(5, DB::table('examen_aula')->where('examen_id', $examenId)->count());
        $this->assertSame(3, DB::table('examen_norma')->where('examen_id', $examenId)->whereNotNull('plantilla_id')->count());
        $this->assertNotNull(DB::table('examenes')->where('id', $examenId)->value('normas'));
        $this->assertSame(
            count(DemoSeeder::PLANTILLAS_DE_LETICIA),
            DB::table('plantillas_norma')
                ->join('usuarios', 'usuarios.id', '=', 'plantillas_norma.usuario_id')
                ->where('usuarios.usuario', 'leticia.blanco')
                ->count()
        );

        // Habilitacion completa y repartida, con nueve no habilitados y su
        // motivo.
        $inscritos = DB::table('inscripciones')
            ->whereIn('grupo_id', DB::table('examen_grupo')->where('examen_id', $examenId)->select('grupo_id'))
            ->count();
        $habilitados = DB::table('habilitaciones')->where('examen_id', $examenId)->where('habilitado', true)->count();

        $this->assertSame($inscritos, DB::table('habilitaciones')->where('examen_id', $examenId)->count());
        $this->assertSame(9, $inscritos - $habilitados);
        $this->assertSame(9, DB::table('habilitaciones')->where('examen_id', $examenId)->where('habilitado', false)->whereNotNull('motivo')->count());
        $this->assertSame(0, DB::table('habilitaciones')->where('examen_id', $examenId)->where('habilitado', true)->whereNull('aula_id')->count());

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
        $this->assertSame(
            18,
            DB::table('examenes')->join('usuarios', 'usuarios.id', '=', 'examenes.creado_por')->where('usuarios.usuario', 'carga.alta')->count()
        );

        // A medio habilitar: con estudiantes revisados y otros sin revisar.
        $conjunto = (int) $this->texto(
            DB::table('examenes')
                ->join('usuarios', 'usuarios.id', '=', 'examenes.creado_por')
                ->where('usuarios.usuario', 'erika.rodriguez')
                ->value('examenes.id')
        );
        $revisados = DB::table('habilitaciones')->where('examen_id', $conjunto)->count();

        $this->assertGreaterThan(0, $revisados);
        $this->assertLessThan(
            DB::table('inscripciones')
                ->whereIn('grupo_id', DB::table('examen_grupo')->where('examen_id', $conjunto)->select('grupo_id'))
                ->count(),
            $revisados,
        );
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
