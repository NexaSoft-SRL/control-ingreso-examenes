<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Administracion\Application\Actions\ProponerUsuario;
use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use GdImage;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

/**
 * Datos de demostracion para recorrer el sistema de punta a punta:
 *
 *   composer datos:demo        (migrate:fresh + este seeder)
 *
 * Importa la oferta real de las cuatro facultades (con edificios y aulas),
 * deja el periodo vigente, las cuentas de los tres roles, un padron ficticio
 * con inscripciones y examenes en todos los estados. **Todo va fechado
 * respecto del momento en que se ejecuta** (zona `umss.zona_horaria`): el
 * examen «de hoy» empezo seis minutos antes de sembrar, asi que siempre hay
 * uno en curso.
 *
 * Cuentas (usuario / contrasena):
 *
 *   admin / Admin12345      Administrador
 *   leticia blanco          / Docente12345    Docente, carga normal: Blanco Coca Leticia.
 *                                              Sus datos de ejemplo estan en 2 asignaturas
 *                                              (Introduccion a la Programacion y Taller de
 *                                              Ingenieria de Software); sus otros grupos
 *                                              reales quedan sin lista.
 *   (nombre + apellido)     / Docente12345    Docente, carga alta: el docente real de FCyT
 *                                              con mas asignaturas; 18 examenes en 6 de ellas.
 *                                              Su usuario sale de su nombre como el de
 *                                              cualquier cuenta (jose soruco con la oferta
 *                                              2/2026).
 *   carla salazar           / Docente12345    Docente a cargo del aula 691A en el examen de hoy.
 *   erika rodriguez         / Docente12345    Docente que registro el parcial conjunto de Taller.
 *   americo fiorilo         / Docente12345    Docente del parcial de Calculo I que comparte
 *                                              el aula 691A con el examen de hoy.
 *   corina flores           / Fa7-Kmq4-Ru9    Docente con contrasena temporal: cae en el
 *                                              cambio obligatorio.
 *   diego mamani            / Auxiliar12345   Auxiliar a cargo del aula 691E hoy.
 *   andrea rojas            / Auxiliar12345   Auxiliar a cargo del aula 617 hoy.
 *   lucia peredo, sergio vargas / Auxiliar12345  Auxiliares de apoyo en el aula 691B.
 *   victor montano          (sin acceso)      Cuenta bloqueada.
 *
 * Codigo de acceso del aula 624 en el examen de hoy: 482913.
 *
 * Ademas, uno de cada cuatro docentes tiene cuenta (contrasena al azar que
 * nadie conoce), para que los filtros de Docentes tengan que mostrar.
 *
 * Se puede volver a ejecutar: no duplica. La segunda vez solo vuelve a
 * fechar los examenes; los ingresos ya asentados no se tocan (son
 * inmutables). No corre en el despliegue.
 */
class DemoSeeder extends Seeder
{
    public const CLAVE_ADMINISTRADOR = 'Admin12345';

    public const CLAVE_DOCENTE = 'Docente12345';

    public const CLAVE_AUXILIAR = 'Auxiliar12345';

    public const TEMPORAL_PRIMER_INGRESO = 'Fa7-Kmq4-Ru9';

    public const CODIGO_AULA_624 = '482913';

    /**
     * Estudiantes del padron por facultad: 4.218 en total.
     *
     * @var array<string, int>
     */
    private const PADRON = ['fcyt' => 1874, 'fce' => 1102, 'fhce' => 768, 'fach' => 474];

    private const INTRODUCCION = '2010010';

    private const TALLER = '2010024';

    private const CALCULO = '2008054';

    private const ARQUITECTURA = '2010013';

    private const LOTE = 500;

    private const NOMBRES = [
        'Adriana', 'Alejandro', 'Álvaro', 'Ana María', 'Andrés', 'Ariel', 'Beatriz', 'Boris', 'Camila', 'Carlos',
        'Carolina', 'César', 'Claudia', 'Cristian', 'Daniela', 'David', 'Diana', 'Edson', 'Eduardo', 'Elena',
        'Erick', 'Fabiola', 'Fernando', 'Gabriela', 'Gonzalo', 'Grover', 'Helen', 'Hugo', 'Iván', 'Jhonny',
        'Jimena', 'Jorge Luis', 'José Manuel', 'Juan Pablo', 'Karen', 'Kevin', 'Laura', 'Limbert', 'Luis Fernando', 'Marcela',
        'Marco Antonio', 'María José', 'Mariana', 'Mauricio', 'Melisa', 'Miguel Ángel', 'Nataly', 'Nelson', 'Noelia', 'Óscar',
        'Pamela', 'Paola', 'Pedro', 'Ramiro', 'Rocío', 'Rodrigo', 'Rosa', 'Rubén', 'Sandra', 'Sebastián',
        'Silvia', 'Sofía', 'Tatiana', 'Valeria', 'Verónica', 'Víctor', 'Wilson', 'Ximena', 'Yesenia', 'Zulma',
    ];

    private const APELLIDOS = [
        'Aguilar', 'Alvarado', 'Arce', 'Ayala', 'Balderrama', 'Bustamante', 'Cabrera', 'Calle', 'Camacho', 'Cardozo',
        'Castro', 'Céspedes', 'Choque', 'Claros', 'Colque', 'Condori', 'Cossío', 'Crespo', 'Delgadillo', 'Escóbar',
        'Espinoza', 'Fernández', 'Flores', 'Fuentes', 'Gamboa', 'García', 'Guzmán', 'Herbas', 'Heredia', 'Jiménez',
        'Lazarte', 'Ledezma', 'López', 'Mamani', 'Maldonado', 'Medrano', 'Mejía', 'Mendoza', 'Montaño', 'Morales',
        'Navia', 'Orellana', 'Ortuño', 'Pardo', 'Paredes', 'Peredo', 'Pérez', 'Quiroga', 'Quispe', 'Rocha',
        'Rodríguez', 'Rojas', 'Romero', 'Saavedra', 'Salazar', 'Sánchez', 'Siles', 'Soliz', 'Soto', 'Terrazas',
        'Torrico', 'Ugarte', 'Vargas', 'Vásquez', 'Vega', 'Veizaga', 'Villarroel', 'Zabala', 'Zambrana', 'Zurita',
    ];

    /**
     * Momento de la siembra en la zona de la universidad.
     */
    protected Carbon $ahoraLocal;

    protected int $periodoId = 0;

    /**
     * Las ventanas del calendario del semestre, como `[inicio, fin]`.
     *
     * @var array<string, array{string, string}>
     */
    protected array $ventanas = [];

    protected int $fcytId = 0;

    /**
     * Estudiantes por facultad (clave => ids) y cuantos ya se repartieron.
     *
     * @var array<string, list<int>>
     */
    protected array $padron = [];

    /**
     * @var array<string, int>
     */
    protected array $puntero = [];

    /**
     * Cuentas por usuario.
     *
     * @var array<string, int>
     */
    protected array $cuentas = [];

    public function run(DetectarPeriodos $detectar, ImportarOferta $importar, FuenteOfertaGateway $fuente): void
    {
        $this->ahoraLocal = self::ahoraLocal();

        $this->call([RolePermissionSeeder::class, FacultadSeeder::class, NormasPredefinidasSeeder::class]);

        $detectar->execute();

        foreach ($fuente->facultades() as $facultad) {
            $resumen = $importar->execute($facultad);

            if (! $resumen->importada) {
                throw new RuntimeException("No se pudo importar {$facultad}: {$resumen->error}");
            }
        }

        $this->periodos();
        $this->fcytId = $this->id('facultades', ['clave' => 'fcyt']);

        $this->cuentas();
        $this->cuentasDeOtrosDocentes();
        $this->padron();
        $this->inscripcionesDeOtrasFacultades();
        $this->conflictos();
        $this->examenesDeLeticia();
        $this->examenesDeCargaAlta();
        $this->listasDeDocentesConCuenta();
        $this->completarPadron();
        $this->fotos();
        $this->bitacora();
    }

    /**
     * La hora de la universidad, que es la de la aplicacion
     * (`config('umss.zona_horaria')`): en ella se guardan la fecha y la hora
     * de un examen, como las teclea el docente, y tambien los instantes
     * (ingresos, incidencias, bitacora).
     */
    public static function ahoraLocal(): Carbon
    {
        return Carbon::now();
    }

    // ------------------------------------------------------------------
    // Periodos
    // ------------------------------------------------------------------

    /**
     * El semestre de la oferta tiene que contener el dia de hoy; el anual
     * no trae fechas en ninguna fuente y aqui se le ponen.
     */
    private function periodos(): void
    {
        $hoy = $this->ahoraLocal->toDateString();

        $semestre = Periodo::where('numero', '>', 0)
            ->withCount('grupos')
            ->orderByDesc('grupos_count')
            ->first();

        if (! $semestre instanceof Periodo) {
            throw new RuntimeException('La oferta importada no dejó ningún período.');
        }

        $this->contenerHoy($semestre, $hoy, null, null);
        $this->periodoId = $semestre->id;
        $this->ventanasDelDemo($semestre);

        foreach (Periodo::where('numero', 0)->get() as $anual) {
            $this->contenerHoy($anual, $hoy, $anual->anio.'-02-02', $anual->anio.'-12-19');
        }
    }

    /**
     * Cada examen del demo cae en la ventana del calendario de su tipo,
     * como lo exige el asistente. Se conservan las ventanas importadas que
     * ya los contienen; las que faltan o ya pasaron se fechan desde hoy.
     */
    private function ventanasDelDemo(Periodo $semestre): void
    {
        $dia = fn (int $dias): string => $this->ahoraLocal->copy()->addDays($dias)->toDateString();
        $importadas = is_array($semestre->ventanas) ? $semestre->ventanas : [];
        $ventanas = [];

        foreach ($importadas as $clave => $ventana) {
            $leida = $this->ventana($ventana);

            if (is_string($clave) && $leida !== null) {
                $ventanas[$clave] = $leida;
            }
        }

        // Primeros parciales: de ayer a dentro de cuatro dias, como minimo.
        $primeros = $ventanas['primeros_parciales'] ?? null;
        $ventanas['primeros_parciales'] = $primeros !== null
            ? [min($primeros[0], $dia(-1)), max($primeros[1], $dia(4))]
            : [$dia(-1), $dia(10)];

        foreach (['segundos_parciales' => [44, 54], 'finales_y_segunda_instancia' => [58, 68]] as $clave => [$desde, $hasta]) {
            // Tiene que dejar al menos ocho dias por delante.
            if (! isset($ventanas[$clave]) || $ventanas[$clave][1] < $dia(8)) {
                $ventanas[$clave] = [$dia($desde), $dia($hasta)];
            }
        }

        $ultimo = max(array_map(static fn (array $v): string => $v[1], array_values($ventanas)));

        if (($semestre->fecha_fin?->toDateString() ?? '') < $ultimo) {
            $semestre->setAttribute('fecha_fin', $ultimo);
        }

        $semestre->setAttribute('ventanas', $ventanas);
        $semestre->save();
        $this->ventanas = $ventanas;
    }

    /**
     * @return array{string, string}|null
     */
    private function ventana(mixed $valor): ?array
    {
        if (! is_array($valor) || ! isset($valor[0], $valor[1]) || ! is_string($valor[0]) || ! is_string($valor[1])) {
            return null;
        }

        return [$valor[0], $valor[1]];
    }

    /**
     * Un dia futuro de la ventana: `$orden` dias despues de su inicio (o de
     * pasado manana, si ya empezo), sin pasar de su fin.
     */
    private function enVentana(string $clave, int $orden): string
    {
        [$inicio, $fin] = $this->ventanas[$clave];
        $desde = max($inicio, $this->ahoraLocal->copy()->addDays(2)->toDateString());

        return min(Carbon::parse($desde)->addDays($orden)->toDateString(), $fin);
    }

    private function contenerHoy(Periodo $periodo, string $hoy, ?string $inicio, ?string $fin): void
    {
        $inicio = $periodo->fecha_inicio?->toDateString() ?? $inicio;
        $fin = $periodo->fecha_fin?->toDateString() ?? $fin;

        if ($inicio === null || $inicio > $hoy) {
            $inicio = $this->ahoraLocal->copy()->subDays(60)->toDateString();
        }

        if ($fin === null || $fin < $hoy) {
            $fin = $this->ahoraLocal->copy()->addDays(75)->toDateString();
        }

        if ($periodo->fecha_inicio?->toDateString() === $inicio && $periodo->fecha_fin?->toDateString() === $fin) {
            return;
        }

        $periodo->setAttribute('fecha_inicio', $inicio);
        $periodo->setAttribute('fecha_fin', $fin);
        $periodo->fuente ??= 'Datos de demostración';
        $periodo->save();
    }

    // ------------------------------------------------------------------
    // Cuentas
    // ------------------------------------------------------------------

    private function cuentas(): void
    {
        $this->cuenta('admin', 'Administración Académica', 'Administrador', self::CLAVE_ADMINISTRADOR);

        $docentes = [
            'leticiablanco' => 'BLANCO COCA LETICIA',
            'carlasalazar' => 'SALAZAR SERRUDO CARLA',
            'erikarodriguez' => 'RODRIGUEZ BILBAO ERIKA PATRICIA',
            'americofiorilo' => 'FIORILO LOZADA AMERICO',
        ];

        foreach ($docentes as $usuario => $normalizado) {
            $this->cuentaDeDocente($usuario, $normalizado, self::CLAVE_DOCENTE);
        }

        User::whereKey($this->cuentas['leticiablanco'])->update(['email' => 'leticia.blanco@fcyt.umss.edu.bo']);

        // El docente real de FCyT con mas asignaturas en el periodo.
        $cargaAlta = DB::table('grupos')
            ->join('docentes', 'docentes.id', '=', 'grupos.docente_id')
            ->where('grupos.facultad_id', $this->fcytId)
            ->where('grupos.periodo_id', $this->periodoId)
            ->whereNotIn('docentes.nombre_normalizado', array_values($docentes))
            ->groupBy('docentes.id', 'docentes.nombre_normalizado')
            ->orderByRaw('count(distinct grupos.asignatura_id) desc')
            ->orderBy('docentes.nombre_normalizado')
            ->value('docentes.nombre_normalizado');

        if (! is_string($cargaAlta)) {
            throw new RuntimeException('No hay docentes de FCyT en la oferta importada.');
        }

        // Su usuario, nombre y apellido como el de cualquier cuenta; si ya
        // tiene cuenta (segunda vez), la misma.
        $usuarioCargaAlta = DB::table('docentes')
            ->join('users', 'users.id', '=', 'docentes.user_id')
            ->where('docentes.nombre_normalizado', $cargaAlta)
            ->value('users.usuario');

        if (! is_string($usuarioCargaAlta)) {
            $usuarioCargaAlta = app(ProponedorUsuario::class)->proponer(
                $this->texto(DB::table('docentes')->where('nombre_normalizado', $cargaAlta)->value('nombre_completo')),
            );
        }

        $this->cuentas['cargaalta'] = $this->cuentaDeDocente($usuarioCargaAlta, $cargaAlta, self::CLAVE_DOCENTE);

        // Primer ingreso: contrasena temporal vigente, cambio obligatorio.
        $corina = $this->cuentaDeDocente('corinaflores', 'FLORES VILLARROEL CORINA', self::TEMPORAL_PRIMER_INGRESO);

        User::whereKey($corina)->firstOrFail()->forceFill([
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->addHours(72),
        ])->save();

        // Bloqueada: la interfaz la muestra como «Bloqueado».
        $victor = $this->cuentaDeDocente('victormontano', 'MONTANO QUIROGA VICTOR HUGO', Str::random(24));

        User::whereKey($victor)->firstOrFail()->forceFill(['is_active' => false])->save();

        $auxiliares = [
            'diegomamani' => ['Mamani Quispe Diego', '202100417'],
            'andrearojas' => ['Rojas Vargas Andrea', '202000982'],
            'luciaperedo' => ['Peredo Soliz Lucía', '202101256'],
            'sergiovargas' => ['Vargas Camacho Sergio', '201900731'],
        ];

        foreach ($auxiliares as $usuario => [$nombre, $codigo]) {
            $id = $this->cuenta($usuario, $nombre, 'Auxiliar', self::CLAVE_AUXILIAR);

            DB::table('auxiliares')->updateOrInsert(
                ['user_id' => $id],
                [
                    'codigo_universitario' => $codigo,
                    'registrado_por' => $this->cuentas['leticiablanco'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * Cuenta con contrasena propia ya definida. No pasa por el contrato de
     * cuentas, que siempre emite una temporal al azar.
     */
    protected function cuenta(string $usuario, string $nombre, string $rol, string $clave): int
    {
        $cuenta = User::firstOrNew(['usuario' => $this->usuarioDe($usuario, $nombre)]);

        $cuenta->forceFill([
            'name' => $nombre,
            'password' => $clave,
            'password_changed_at' => now(),
            'password_temporal_expira_en' => null,
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'role_id' => Role::where('name', $rol)->firstOrFail()->id,
        ])->save();

        return $this->cuentas[$usuario] = $this->entero($cuenta->getKey());
    }

    /**
     * El usuario con que se entra: nombre y apellido separados («leticia
     * blanco») cuando la clave es justo eso, junto; si no («admin»), la
     * clave tal cual. La clave sigue siendo la de `$this->cuentas`.
     */
    private function usuarioDe(string $clave, string $nombre): string
    {
        $separado = ProponerUsuario::formar($nombre);

        return str_replace(' ', '', $separado) === $clave ? $separado : $clave;
    }

    private function cuentaDeDocente(string $usuario, string $normalizado, string $clave): int
    {
        $docente = DB::table('docentes')->where('nombre_normalizado', $normalizado)->first();

        if (! $docente instanceof stdClass || ! is_string($docente->nombre_completo)) {
            throw new RuntimeException("La oferta importada no trae al docente {$normalizado}.");
        }

        $id = $this->cuenta($usuario, $docente->nombre_completo, 'Docente', $clave);

        DB::table('docentes')->where('id', $docente->id)->update(['user_id' => $id]);

        return $id;
    }

    /**
     * Uno de cada cuatro docentes con cuenta; de esos, uno de cada cinco
     * todavia con la contrasena temporal.
     */
    private function cuentasDeOtrosDocentes(): void
    {
        $proponedor = app(ProponedorUsuario::class);
        $rolId = Role::where('name', 'Docente')->firstOrFail()->id;
        $clave = Hash::make(Str::random(40));
        $numero = 0;

        $docentes = DB::table('docentes')
            ->whereNull('user_id')
            ->whereRaw('id % 4 = 0')
            ->orderBy('id')
            ->get(['id', 'nombre_completo']);

        foreach ($docentes as $docente) {
            $nombre = is_string($docente->nombre_completo) ? $docente->nombre_completo : '';
            $temporal = ++$numero % 5 === 0;

            $id = DB::table('users')->insertGetId([
                'name' => $nombre,
                'usuario' => $proponedor->proponer($nombre),
                'email' => null,
                'password' => $clave,
                'password_changed_at' => $temporal ? null : now(),
                'password_temporal_expira_en' => $temporal ? now()->addHours(72) : null,
                'is_active' => true,
                'role_id' => $rolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('docentes')->where('id', $docente->id)->update(['user_id' => $id]);
        }
    }

    // ------------------------------------------------------------------
    // Padron e inscripciones
    // ------------------------------------------------------------------

    /**
     * 4.218 estudiantes ficticios, siempre los mismos: los codigos y los
     * nombres salen de la posicion, no del azar.
     */
    private function padron(): void
    {
        $numero = 0;

        foreach (self::PADRON as $clave => $cantidad) {
            $facultadId = $this->id('facultades', ['clave' => $clave]);
            $carreras = $this->enteros(DB::table('carreras')->where('facultad_id', $facultadId)->orderBy('id')->pluck('id'));
            $filas = [];

            for ($i = 0; $i < $cantidad; $i++, $numero++) {
                $filas[] = [
                    'codigo_universitario' => (string) ((2021 + $numero % 6) * 100000 + 10007 + intdiv($numero, 6) * 7),
                    'documento_identidad' => (string) (5000000 + $numero * 937),
                    'nombres' => self::NOMBRES[($numero * 37 + intdiv($numero, 11)) % count(self::NOMBRES)],
                    'apellidos' => self::APELLIDOS[($numero * 13 + intdiv($numero, 70) * 17) % count(self::APELLIDOS)]
                        .' '.self::APELLIDOS[($numero * 29 + 5 + intdiv($numero, 7) * 3) % count(self::APELLIDOS)],
                    'carrera_id' => $carreras === [] ? null : $carreras[$i % count($carreras)],
                    'facultad_id' => $facultadId,
                    // Unos pocos los creo la carga de un docente y nadie
                    // de administracion los confirmo todavia.
                    'origen' => $numero % 40 === 0 ? 'DOCENTE' : 'ADMINISTRACION',
                    'verificado' => $numero % 40 !== 0,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($filas, self::LOTE) as $lote) {
                DB::table('estudiantes')->insertOrIgnore($lote);
            }

            $this->padron[$clave] = $this->enteros(
                DB::table('estudiantes')
                    ->where('facultad_id', $facultadId)
                    ->whereIn('codigo_universitario', array_column($filas, 'codigo_universitario'))
                    ->orderBy('id')
                    ->pluck('id')
            );
            $this->puntero[$clave] = 0;
        }
    }

    /**
     * Inscribe en el grupo a los siguientes `$cantidad` estudiantes de la
     * facultad y deja la fila de la carga. Si el grupo ya tiene lista (el
     * seeder ya corrio) devuelve la que tiene.
     *
     * @return list<int> los estudiantes inscritos
     */
    protected function inscribir(int $grupoId, int $cantidad, string $via, int $cargadaPor, string $facultad = 'fcyt'): array
    {
        $total = count($this->padron[$facultad]);
        $desde = $this->puntero[$facultad];
        $this->puntero[$facultad] += $cantidad;

        $inscritos = $this->enteros(
            DB::table('inscripciones')->where('grupo_id', $grupoId)->orderBy('estudiante_id')->pluck('estudiante_id')
        );

        if ($inscritos !== [] || $total === 0) {
            return $inscritos;
        }

        $estudiantes = [];

        for ($i = 0; $i < min($cantidad, $total); $i++) {
            $estudiantes[] = $this->padron[$facultad][($desde + $i) % $total];
        }

        $grupo = DB::table('grupos')
            ->join('asignaturas', 'asignaturas.id', '=', 'grupos.asignatura_id')
            ->where('grupos.id', $grupoId)
            ->first(['grupos.codigo as grupo', 'grupos.periodo_id', 'grupos.facultad_id', 'asignaturas.codigo as asignatura']);

        if (! $grupo instanceof stdClass) {
            throw new RuntimeException("No existe el grupo {$grupoId}.");
        }

        $cargaId = DB::table('cargas_inscritos')->insertGetId([
            'alcance' => 'GRUPO',
            'grupo_id' => $grupoId,
            'facultad_id' => $grupo->facultad_id,
            'periodo_id' => $grupo->periodo_id,
            'archivo' => sprintf('inscritos_%s_grupo_%s.csv', $this->texto($grupo->asignatura), $this->texto($grupo->grupo)),
            'filas' => count($estudiantes),
            'nuevos' => $via === 'DOCENTE' ? 0 : count($estudiantes),
            'reutilizados' => $via === 'DOCENTE' ? count($estudiantes) : 0,
            'cargada_por' => $cargadaPor,
            'created_at' => now()->subDays(12),
        ]);

        $filas = [];

        foreach ($estudiantes as $estudianteId) {
            $filas[] = [
                'estudiante_id' => $estudianteId,
                'grupo_id' => $grupoId,
                'via' => $via,
                'cargada_por' => $cargadaPor,
                'carga_id' => $cargaId,
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(12),
            ];
        }

        foreach (array_chunk($filas, self::LOTE) as $lote) {
            DB::table('inscripciones')->insertOrIgnore($lote);
        }

        return $estudiantes;
    }

    /**
     * Para que el padron del administrador tenga listas fuera de FCyT:
     * seis grupos de cada una de las otras facultades.
     */
    private function inscripcionesDeOtrasFacultades(): void
    {
        $administrador = $this->cuentas['admin'];

        foreach (['fce', 'fhce', 'fach'] as $clave) {
            $grupos = $this->enteros(
                DB::table('grupos')
                    ->where('facultad_id', $this->id('facultades', ['clave' => $clave]))
                    ->whereNotNull('docente_id')
                    ->orderBy('id')
                    ->limit(6)
                    ->pluck('id')
            );

            foreach ($grupos as $grupoId) {
                $this->inscribir($grupoId, 40, 'ADMINISTRACION', $administrador, $clave);
            }
        }
    }

    /**
     * El plazo de fotos abierto y la foto de tres de cada cuatro
     * estudiantes de los examenes de hoy: la puerta muestra la cara de
     * casi todos y el resto queda «Sin foto». Las imagenes son siluetas.
     */
    private function fotos(): void
    {
        DB::table('plazos_foto')->delete();
        DB::table('plazos_foto')->insert([
            'desde' => $this->ahoraLocal->copy()->subDays(10)->toDateString(),
            'hasta' => $this->ahoraLocal->copy()->addDays(20)->toDateString(),
            'fijado_por' => $this->cuentas['admin'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $estudiantes = $this->enteros(
            DB::table('habilitaciones')
                ->join('examenes', 'examenes.id', '=', 'habilitaciones.examen_id')
                ->join('estudiantes', 'estudiantes.id', '=', 'habilitaciones.estudiante_id')
                ->whereDate('examenes.fecha', $this->ahoraLocal->toDateString())
                ->whereNull('estudiantes.foto')
                ->whereRaw('estudiantes.id % 4 <> 0')
                ->distinct()
                ->orderBy('estudiantes.id')
                ->pluck('estudiantes.id')
        );

        $almacen = app(AlmacenImagenes::class);

        foreach ($estudiantes as $estudianteId) {
            $ruta = $almacen->guardar('estudiantes', 'demo-'.$estudianteId, $this->silueta($estudianteId), 480);

            DB::table('estudiantes')->where('id', $estudianteId)->update([
                'foto' => $ruta,
                'foto_subida_en' => now()->subDays(1 + $estudianteId % 9),
            ]);
        }
    }

    /**
     * Una silueta en JPEG con el fondo y la ropa de otro color por
     * estudiante, para que no se vean todas iguales.
     */
    private function silueta(int $semilla): string
    {
        $imagen = imagecreatetruecolor(360, 480);

        if (! $imagen instanceof GdImage) {
            throw new RuntimeException('GD no pudo crear la imagen.');
        }

        $fondos = [[214, 228, 240], [230, 224, 214], [220, 236, 222], [236, 226, 236], [226, 230, 232]];
        $ropas = [[46, 64, 92], [120, 52, 48], [40, 96, 72], [88, 72, 120], [60, 60, 64]];
        $pieles = [[224, 186, 152], [198, 150, 112], [170, 122, 88], [236, 204, 176]];
        $color = fn (array $rgb): int => (int) imagecolorallocate(
            $imagen,
            max(0, min(255, $this->entero($rgb[0]))),
            max(0, min(255, $this->entero($rgb[1]))),
            max(0, min(255, $this->entero($rgb[2]))),
        );

        imagefill($imagen, 0, 0, $color($fondos[$semilla % 5]));
        imagefilledellipse($imagen, 180, 470, 340, 260, $color($ropas[intdiv($semilla, 5) % 5]));
        imagefilledrectangle($imagen, 150, 280, 210, 350, $color($pieles[$semilla % 4]));
        imagefilledellipse($imagen, 180, 205, 170, 210, $color($pieles[$semilla % 4]));
        imagefilledellipse($imagen, 180, 125, 180, 90, $color([40 + $semilla % 30, 30, 24]));

        ob_start();
        imagejpeg($imagen, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Una foto de evidencia de muestra: un reloj sobre la mesa.
     */
    private function imagenDeEvidencia(): string
    {
        $imagen = imagecreatetruecolor(800, 600);

        if (! $imagen instanceof GdImage) {
            throw new RuntimeException('GD no pudo crear la imagen.');
        }

        imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 150, 120, 90));
        imagefilledrectangle($imagen, 370, 80, 430, 520, (int) imagecolorallocate($imagen, 30, 30, 34));
        imagefilledellipse($imagen, 400, 300, 220, 240, (int) imagecolorallocate($imagen, 20, 20, 24));
        imagefilledellipse($imagen, 400, 300, 180, 200, (int) imagecolorallocate($imagen, 40, 90, 140));
        imagestring($imagen, 5, 372, 292, '08:47', (int) imagecolorallocate($imagen, 255, 255, 255));

        ob_start();
        imagejpeg($imagen, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Ningun docente que entra al sistema ve un grupo vacio: los grupos
     * (semestrales y anuales) de los docentes con cuenta que todavia no
     * tienen lista reciben entre 20 y 40 estudiantes de su facultad.
     */
    private function listasDeDocentesConCuenta(): void
    {
        $administrador = $this->cuentas['admin'];
        $claves = DB::table('facultades')->pluck('clave', 'id');

        $grupos = DB::table('grupos')
            ->join('docentes', 'docentes.id', '=', 'grupos.docente_id')
            ->whereNotNull('docentes.user_id')
            ->whereNotExists(static fn (Builder $q) => $q->from('inscripciones')->whereColumn('inscripciones.grupo_id', 'grupos.id'))
            ->orderBy('grupos.id')
            ->get(['grupos.id', 'grupos.facultad_id']);

        foreach ($grupos as $grupo) {
            $clave = $this->texto($claves[$grupo->facultad_id] ?? '');

            if (! isset($this->padron[$clave])) {
                continue;
            }

            $id = $this->entero($grupo->id);
            $this->inscribir($id, 20 + $id % 21, 'ADMINISTRACION', $administrador, $clave);
        }
    }

    /**
     * Ningun estudiante del padron queda sin materias: el que no salio en
     * ninguna lista entra a un grupo con lista de su facultad.
     */
    private function completarPadron(): void
    {
        $administrador = $this->cuentas['admin'];

        foreach ($this->padron as $clave => $estudiantes) {
            $sueltos = $this->enteros(
                DB::table('estudiantes')
                    ->whereIn('id', $estudiantes)
                    ->whereNotExists(static fn (Builder $q) => $q->from('inscripciones')->whereColumn('inscripciones.estudiante_id', 'estudiantes.id'))
                    ->orderBy('id')
                    ->pluck('id')
            );

            $grupos = $this->enteros(
                DB::table('grupos')
                    ->where('facultad_id', $this->id('facultades', ['clave' => $clave]))
                    ->whereExists(static fn (Builder $q) => $q->from('inscripciones')->whereColumn('inscripciones.grupo_id', 'grupos.id'))
                    ->orderBy('id')
                    ->pluck('id')
            );

            if ($sueltos === [] || $grupos === []) {
                continue;
            }

            $filas = [];

            foreach ($sueltos as $i => $estudianteId) {
                $filas[] = [
                    'estudiante_id' => $estudianteId,
                    'grupo_id' => $grupos[$i % count($grupos)],
                    'via' => 'ADMINISTRACION',
                    'cargada_por' => $administrador,
                    'created_at' => now()->subDays(12),
                    'updated_at' => now()->subDays(12),
                ];
            }

            foreach (array_chunk($filas, self::LOTE) as $lote) {
                DB::table('inscripciones')->insertOrIgnore($lote);
            }
        }
    }

    /**
     * Tres conflictos pendientes: la lista de un docente trajo a tres
     * estudiantes del padron con otro documento u otro nombre. Su
     * inscripcion al grupo queda en espera.
     */
    private function conflictos(): void
    {
        if (DB::table('conflictos_padron')->exists()) {
            return;
        }

        $leticia = $this->cuentas['leticiablanco'];
        $grupoId = $this->grupo(self::ARQUITECTURA, '2');

        $cargaId = DB::table('cargas_inscritos')->insertGetId([
            'alcance' => 'GRUPO',
            'grupo_id' => $grupoId,
            'facultad_id' => $this->fcytId,
            'periodo_id' => $this->periodoId,
            'archivo' => 'inscritos_'.self::ARQUITECTURA.'_grupo_2.csv',
            'filas' => 3,
            'conflictos' => 3,
            'cargada_por' => $leticia,
            'created_at' => now()->subDays(2),
        ]);

        $casos = [
            [1700, 'DOCUMENTO_DISTINTO', true, false],
            [1730, 'DOCUMENTO_DISTINTO', true, false],
            [1760, 'NOMBRE_DISTINTO', false, true],
        ];

        foreach ($casos as $fila => [$posicion, $tipo, $otroDocumento, $otroNombre]) {
            $estudiante = DB::table('estudiantes')->where('id', $this->padron['fcyt'][$posicion])->first();

            if (! $estudiante instanceof stdClass) {
                continue;
            }

            DB::table('conflictos_padron')->insert([
                'estudiante_id' => $estudiante->id,
                'grupo_id' => $grupoId,
                'carga_id' => $cargaId,
                'fila' => $fila + 2,
                'tipo' => $tipo,
                'documento_nuevo' => $otroDocumento
                    ? (string) ((int) $this->texto($estudiante->documento_identidad) + 10)
                    : $estudiante->documento_identidad,
                'nombres_nuevos' => $otroNombre ? $this->texto($estudiante->nombres).' A.' : $estudiante->nombres,
                'apellidos_nuevos' => $estudiante->apellidos,
                'via' => 'DOCENTE',
                'estado' => 'PENDIENTE',
                'reportado_por' => $leticia,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Examenes
    // ------------------------------------------------------------------

    private function examenesDeLeticia(): void
    {
        $leticia = $this->cuentas['leticiablanco'];
        $carla = $this->cuentas['carlasalazar'];
        $erika = $this->cuentas['erikarodriguez'];
        $americo = $this->cuentas['americofiorilo'];
        $diego = $this->cuentas['diegomamani'];
        $andrea = $this->cuentas['andrearojas'];

        $introduccion = $this->id('asignaturas', ['codigo' => self::INTRODUCCION]);
        $taller = $this->id('asignaturas', ['codigo' => self::TALLER]);
        $calculo = $this->id('asignaturas', ['codigo' => self::CALCULO]);

        // Listas: las de sus grupos las subio cada docente.
        $inscritosHoy = [];
        $gruposHoy = [];

        foreach (['1' => $carla, '2' => $leticia, '3' => $leticia, '6' => $carla] as $codigo => $quien) {
            $gruposHoy[] = $grupoId = $this->grupo(self::INTRODUCCION, (string) $codigo);
            $inscritosHoy = [...$inscritosHoy, ...$this->inscribir($grupoId, 65, 'DOCENTE', $quien)];
        }

        $grupoTaller = $this->grupo(self::TALLER, '2');
        $grupoTallerErika = $this->grupo(self::TALLER, '4');
        $inscritosTaller = [
            ...$this->inscribir($grupoTaller, 46, 'DOCENTE', $leticia),
            ...$this->inscribir($grupoTallerErika, 44, 'DOCENTE', $erika),
        ];

        $gruposCalculo = [$this->grupo(self::CALCULO, '1'), $this->grupo(self::CALCULO, '14')];
        $inscritosCalculo = [];

        foreach ($gruposCalculo as $grupoId) {
            $inscritosCalculo = [...$inscritosCalculo, ...$this->inscribir($grupoId, 60, 'DOCENTE', $americo)];
        }

        $inicio = $this->ahoraLocal->copy()->subMinutes(6)->startOfMinute();

        // (a) Primer parcial de Introduccion a la Programacion: hoy, en
        // curso, cinco aulas, cada una con quien la atiende.
        $aulas = [
            '691B' => $leticia,
            '691A' => $carla,
            '691E' => $diego,
            '617' => $andrea,
            '624' => $leticia,
        ];

        $examenHoy = $this->examen(
            $introduccion,
            'PRIMER_PARCIAL',
            $leticia,
            $inicio->toDateString(),
            $inicio->format('H:i:s'),
            90,
            $gruposHoy,
            $this->aulasConEncargado($aulas),
            "Se ingresa solo con el código QR o el documento de identidad.\nSin teléfonos ni relojes inteligentes sobre el pupitre.\nNo se admite el ingreso pasados 30 minutos del inicio.",
        );

        // El parcial de Calculo I de otro docente, a la misma hora y
        // tambien en la 691A: aula compartida.
        $examenCalculo = $this->examen(
            $calculo,
            'PRIMER_PARCIAL',
            $americo,
            $inicio->toDateString(),
            $inicio->format('H:i:s'),
            90,
            $gruposCalculo,
            $this->aulasConEncargado(['691A' => $americo, '692A' => $americo]),
            null,
        );

        if ($examenCalculo['nuevo']) {
            $reparto = $this->habilitar($examenCalculo['id'], $inscritosCalculo, array_keys($examenCalculo['aulas']), $americo);
            $this->emitirQr($examenCalculo['id'], array_keys($reparto), $americo, false);
            $this->ingresar($examenCalculo['id'], $reparto, $examenCalculo['aulas'], 30, 18 * 60, 60);
        }

        if ($examenHoy['nuevo']) {
            $this->movimientoDelExamenDeHoy($examenHoy, $inscritosHoy, $examenCalculo['id'], $inscritosCalculo);
        }

        // (b) Parcial conjunto de Taller, en dos dias, registrado por otra
        // docente; a medio habilitar.
        $enDosDias = $this->ahoraLocal->copy()->addDays(2)->toDateString();

        $conjunto = $this->examen(
            $taller,
            'PRIMER_PARCIAL',
            $erika,
            $enDosDias,
            '08:15:00',
            90,
            [$grupoTallerErika, $grupoTaller],
            $this->aulasConEncargado(['690E' => $erika, 'INFLAB' => $leticia]),
            'Proyecto impreso y defensa individual.',
        );

        if ($conjunto['nuevo']) {
            $mitad = array_slice($inscritosTaller, 0, intdiv(count($inscritosTaller), 2));
            $this->habilitar($conjunto['id'], $mitad, [], $erika, [3 => 'No presentó el segundo avance del proyecto.']);
        }

        // (c) Segundo parcial de Introduccion: todavia sin aulas.
        $this->examen(
            $introduccion,
            'SEGUNDO_PARCIAL',
            $leticia,
            $this->enVentana('segundos_parciales', 1),
            '14:15:00',
            90,
            [$gruposHoy[1]],
            [],
            null,
        );

        // (d) Segundo parcial de Taller: con aula, sin habilitar.
        $this->examen(
            $taller,
            'SEGUNDO_PARCIAL',
            $leticia,
            $this->enVentana('segundos_parciales', 3),
            '08:15:00',
            120,
            [$grupoTaller],
            $this->aulasConEncargado(['INFLAB' => $leticia]),
            null,
        );
    }

    /**
     * Lo que ya paso en el examen de hoy cuando se abre la demostracion:
     * habilitacion completa (nueve no habilitados), reparto, codigos QR
     * emitidos y enviados, cuatro de cada diez ya adentro, un acceso con
     * codigo de aula, una expulsion, un cambio de aula, tres incidencias de
     * personas y cuatro intentos que la puerta rechazo.
     *
     * @param  array{id: int, nuevo: bool, aulas: array<int, int>}  $examen
     * @param  list<int>  $inscritos
     * @param  list<int>  $inscritosCalculo
     */
    private function movimientoDelExamenDeHoy(array $examen, array $inscritos, int $examenCalculo, array $inscritosCalculo): void
    {
        $leticia = $this->cuentas['leticiablanco'];
        $id = $examen['id'];

        $motivos = [
            'No entregó las prácticas 1 y 2.',
            'No entregó las prácticas 1 y 2.',
            'No rindió el examen de laboratorio.',
            'Abandonó la materia: sin asistencia desde agosto.',
            'No entregó el proyecto del primer módulo.',
            'No rindió el examen de laboratorio.',
            'No entregó las prácticas 1 y 2.',
            'Inscripción observada por la jefatura de carrera.',
            'No entregó el proyecto del primer módulo.',
        ];

        // Posiciones de la lista que no quedan habilitadas.
        $noHabilitados = [];

        foreach ($motivos as $i => $motivo) {
            $noHabilitados[11 + $i * 27] = $motivo;
        }

        $reparto = $this->habilitar($id, $inscritos, array_keys($examen['aulas']), $leticia, $noHabilitados);
        $this->emitirQr($id, array_keys($reparto), $leticia, true);

        $aula = fn (string $nombre): int => $this->id('aulas', ['nombre' => $nombre]);

        // Apoyo en la 691B.
        $examenAula691B = $this->id('examen_aula', ['examen_id' => $id, 'aula_id' => $aula('691B')]);

        foreach (['luciaperedo', 'sergiovargas'] as $usuario) {
            DB::table('examen_aula_apoyo')->insertOrIgnore([
                'examen_aula_id' => $examenAula691B,
                'user_id' => $this->cuentas[$usuario],
            ]);
        }

        // La 624 la atiende un ayudante ocasional que entro con el codigo.
        $examenAula624 = $this->id('examen_aula', ['examen_id' => $id, 'aula_id' => $aula('624')]);

        DB::table('examen_aula')->where('id', $examenAula624)->update([
            'codigo_acceso' => self::CODIGO_AULA_624,
            'codigo_generado_en' => now()->subHours(3),
        ]);

        $acceso = DB::table('accesos_aula')->insertGetId([
            'examen_aula_id' => $examenAula624,
            'nombre' => 'Quiroga Marcelo',
            'ip' => '10.0.12.34',
            'creado_en' => now()->subMinutes(28),
            // La docente lo vio en persona y lo autorizo.
            'estado' => 'AUTORIZADO',
            'resuelto_por' => $leticia,
            'resuelto_en' => now()->subMinutes(27),
            'ultima_actividad_en' => now()->subMinute(),
        ]);

        // Otro ayudante acaba de pedir entrar con el codigo: espera en la
        // puerta a que la docente lo autorice desde «En curso».
        DB::table('accesos_aula')->insert([
            'examen_aula_id' => $examenAula624,
            'nombre' => 'Choque Mamani Rubén',
            'ip' => '10.0.12.51',
            'creado_en' => now()->subMinutes(2),
            'estado' => 'PENDIENTE',
        ]);

        // Uno de la 617 entra por la 691B con un cambio de aula autorizado.
        $cambiado = null;

        foreach ($reparto as $estudianteId => $aulaId) {
            if ($aulaId === $aula('617')) {
                $cambiado = $estudianteId;
            }
        }

        if ($cambiado !== null) {
            unset($reparto[$cambiado]);
        }

        $this->ingresar($id, $reparto, $examen['aulas'], 40, 20 * 60, 30, [$aula('624') => $acceso]);

        $ocurrida = static fn (int $minutos): Carbon => now()->subMinutes($minutos);
        $incidencias = [];
        // Quien entro por la puerta de esa aula: las incidencias nombran a
        // alguien que de verdad esta ahi.
        $presente = function (string $nombre, int $orden) use ($id, $aula): ?int {
            $estudiante = DB::table('ingresos')
                ->where('examen_id', $id)
                ->where('aula_id', $aula($nombre))
                ->where('es_excepcion', false)
                ->orderBy('id')
                ->skip($orden)
                ->value('estudiante_id');

            return is_numeric($estudiante) ? (int) $estudiante : null;
        };

        if ($cambiado !== null) {
            DB::table('ingresos')->insertOrIgnore([
                'examen_id' => $id,
                'estudiante_id' => $cambiado,
                'aula_id' => $aula('691B'),
                'medio' => 'DOCUMENTO',
                'es_excepcion' => true,
                'registrado_en' => $ocurrida(9),
                'registrado_por' => $leticia,
                'created_at' => $ocurrida(9),
            ]);

            $incidencias[] = [
                'aula_id' => $aula('691B'),
                'estudiante_id' => $cambiado,
                'tipo' => 'CAMBIO_AULA',
                'detalle' => 'Le tocaba la 617; rinde en la 691B por una lesión en la pierna (planta baja).',
                'ocurrida_en' => $ocurrida(9),
                'reportada_por' => $leticia,
                'atendida_en' => $ocurrida(9),
                'atendida_por' => $leticia,
            ];
        }

        // Una expulsion: va unida al ingreso del expulsado.
        $expulsado = DB::table('ingresos')
            ->where('examen_id', $id)
            ->where('aula_id', $aula('691B'))
            ->where('es_excepcion', false)
            ->orderBy('id')
            ->first();

        if ($expulsado instanceof stdClass) {
            $incidencias[] = [
                'aula_id' => $aula('691B'),
                'estudiante_id' => $expulsado->estudiante_id,
                'ingreso_id' => $expulsado->id,
                'tipo' => 'EXPULSION',
                'detalle' => 'Sorprendido consultando el teléfono durante el examen; se le retiró la hoja.',
                'ocurrida_en' => $ocurrida(3),
                'reportada_por' => $leticia,
                'atendida_en' => $ocurrida(3),
                'atendida_por' => $leticia,
            ];
        }

        // Tres incidencias reportadas por quienes atienden las aulas.
        $incidencias[] = [
            'aula_id' => $aula('691E'),
            'estudiante_texto' => 'Joven de chompa azul, dice llamarse Kevin Rojas',
            'tipo' => 'SUPLANTACION',
            'detalle' => 'La foto del documento no coincide con quien lo presenta. Espera en la puerta.',
            'ocurrida_en' => $ocurrida(5),
            'reportada_por' => $this->cuentas['diegomamani'],
        ];
        $incidencias[] = [
            'aula_id' => $aula('617'),
            'estudiante_id' => $presente('617', 2),
            'tipo' => 'MATERIAL_NO_PERMITIDO',
            'detalle' => 'Entró con apuntes impresos; se los retiró y quedaron en el escritorio.',
            'ocurrida_en' => $ocurrida(14),
            'reportada_por' => $this->cuentas['andrearojas'],
            'atendida_en' => $ocurrida(11),
            'atendida_por' => $leticia,
        ];
        $incidencias[] = [
            'aula_id' => $aula('691A'),
            'tipo' => 'OTRA',
            'detalle' => 'Faltan pupitres: el otro examen que usa el aula ocupó dos filas más de lo previsto.',
            'ocurrida_en' => $ocurrida(2),
            'reportada_por' => $this->cuentas['carlasalazar'],
        ];

        // Un dispositivo no permitido, con la foto que tomo el auxiliar.
        $incidencias[] = [
            'aula_id' => $aula('691B'),
            'estudiante_id' => $presente('691B', 3),
            'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
            'detalle' => 'Reloj inteligente en la muñeca; se le pidió guardarlo en la mochila.',
            'ocurrida_en' => $ocurrida(7),
            'reportada_por' => $this->cuentas['luciaperedo'],
            'evidencia' => app(AlmacenImagenes::class)->guardar(
                'incidencias',
                'demo-reloj',
                $this->imagenDeEvidencia(),
                1280,
            ),
        ];

        // Un QR que ya habia entrado vuelve a presentarse en otra puerta:
        // la puerta lo asienta como ingreso repetido.
        $repetido = DB::table('ingresos')
            ->where('examen_id', $id)
            ->where('aula_id', $aula('691A'))
            ->orderBy('id')
            ->first();

        if ($repetido instanceof stdClass) {
            $incidencias[] = [
                'aula_id' => $aula('624'),
                'estudiante_id' => $repetido->estudiante_id,
                'ingreso_id' => $repetido->id,
                'tipo' => 'INGRESO_REPETIDO',
                'origen' => 'SISTEMA',
                'detalle' => sprintf(
                    'Código QR ya usado. Ingresó a las %s en el aula 691A.',
                    substr($this->texto($repetido->registrado_en), 11, 5),
                ),
                'ocurrida_en' => $ocurrida(4),
                'acceso_aula_id' => $acceso,
            ];
        }

        // Cuatro intentos que la puerta rechazo sola.
        $noHabilitado = $inscritos[array_key_first($noHabilitados)];
        $ajeno = $this->padron['fcyt'][count($this->padron['fcyt']) - 1];

        $incidencias[] = [
            'aula_id' => $aula('691B'),
            'estudiante_id' => $noHabilitado,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_HABILITADO',
            'detalle' => 'No habilitado: '.$motivos[0],
            'ocurrida_en' => $ocurrida(16),
            'reportada_por' => $leticia,
        ];
        $incidencias[] = [
            'aula_id' => $aula('691E'),
            'estudiante_id' => $inscritosCalculo[4],
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_INSCRITO',
            'examen_correspondiente_id' => $examenCalculo,
            'detalle' => 'No está inscrito en este examen. A esta hora le corresponde el de '
                .$this->texto(DB::table('asignaturas')->where('codigo', self::CALCULO)->value('nombre')).'.',
            'ocurrida_en' => $ocurrida(12),
            'reportada_por' => $this->cuentas['diegomamani'],
        ];
        $incidencias[] = [
            'aula_id' => $aula('617'),
            'estudiante_id' => $ajeno,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_INSCRITO',
            'detalle' => 'No está inscrito en ninguno de los grupos del examen.',
            'ocurrida_en' => $ocurrida(8),
            'reportada_por' => $this->cuentas['andrearojas'],
        ];
        $incidencias[] = [
            'aula_id' => $aula('624'),
            'estudiante_texto' => 'Código leído: 20219999',
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'CODIGO_INVALIDO',
            'detalle' => 'El código no corresponde a ningún estudiante ni a un QR emitido.',
            'ocurrida_en' => $ocurrida(6),
            'acceso_aula_id' => $acceso,
        ];

        foreach ($incidencias as $incidencia) {
            // La foto va aparte: una incidencia lleva hasta tres.
            $foto = $incidencia['evidencia'] ?? null;
            unset($incidencia['evidencia']);

            $incidenciaId = DB::table('incidencias')->insertGetId($incidencia + [
                'examen_id' => $id,
                'estudiante_id' => null,
                'estudiante_texto' => null,
                'ingreso_id' => null,
                'origen' => 'PERSONA',
                'causa' => null,
                'examen_correspondiente_id' => null,
                'reportada_por' => null,
                'acceso_aula_id' => null,
                'atendida_en' => null,
                'atendida_por' => null,
                'created_at' => $incidencia['ocurrida_en'],
                'updated_at' => $incidencia['atendida_en'] ?? $incidencia['ocurrida_en'],
            ]);

            if (is_string($foto)) {
                DB::table('evidencias_incidencia')->insert([
                    'incidencia_id' => $incidenciaId,
                    'ruta' => $foto,
                    'orden' => 1,
                    'subida_por' => $incidencia['reportada_por'] ?? null,
                    'created_at' => $incidencia['ocurrida_en'],
                ]);
            }
        }
    }

    /**
     * 18 examenes en seis asignaturas del docente de carga alta: los
     * primeros parciales (uno hoy en doce aulas y listo para empezar, tres
     * mañana a la misma hora en la misma aula, uno ayer ya rendido y uno a
     * medio habilitar) y los segundos parciales y finales, sin aulas.
     */
    private function examenesDeCargaAlta(): void
    {
        $docente = $this->cuentas['cargaalta'];

        $grupos = DB::table('grupos')
            ->join('docentes', 'docentes.id', '=', 'grupos.docente_id')
            ->join('asignaturas', 'asignaturas.id', '=', 'grupos.asignatura_id')
            ->where('docentes.user_id', $docente)
            ->where('grupos.periodo_id', $this->periodoId)
            ->where('grupos.facultad_id', $this->fcytId)
            ->orderBy('asignaturas.nombre')
            ->orderBy('grupos.id')
            ->get(['grupos.id', 'grupos.asignatura_id']);

        /** @var array<int, list<int>> $porAsignatura */
        $porAsignatura = [];

        foreach ($grupos as $grupo) {
            $porAsignatura[$this->entero($grupo->asignatura_id)][] = $this->entero($grupo->id);
        }

        $porAsignatura = array_slice($porAsignatura, 0, 6, true);

        // Doce aulas ubicadas de FCyT que no usa ningun otro examen de hoy.
        $reservadas = ['691A', '691B', '691E', '617', '624', '692A', '692B', '690E', 'INFLAB', '693A', '693B'];

        $doce = $this->enteros(
            DB::table('aulas')
                ->where('facultad_id', $this->fcytId)
                ->whereNotNull('edificio_id')
                ->whereNotIn('nombre', $reservadas)
                ->orderBy('nombre')
                ->limit(12)
                ->pluck('id')
        );

        $enLasDoce = [];

        foreach ($doce as $aulaId) {
            $enLasDoce[$aulaId] = $docente;
        }

        $dia = fn (int $dias): string => $this->ahoraLocal->copy()->addDays($dias)->toDateString();

        // Hoy, dentro de 45 minutos: la puerta ya esta abierta.
        $pronto = $this->ahoraLocal->copy()->addMinutes(45)->startOfMinute();

        if ($pronto->toDateString() !== $this->ahoraLocal->toDateString()) {
            $pronto = $this->ahoraLocal->copy()->subMinutes(6)->startOfMinute();
        }

        $numero = 0;

        foreach ($porAsignatura as $asignaturaId => $gruposDeAsignatura) {
            $inscritos = [];

            foreach ($gruposDeAsignatura as $grupoId) {
                $inscritos = [...$inscritos, ...$this->inscribir($grupoId, $numero === 0 ? 300 : 60, 'DOCENTE', $docente)];
            }

            [$fecha, $hora, $aulas] = match ($numero) {
                0 => [$pronto->toDateString(), $pronto->format('H:i:s'), $enLasDoce],
                1, 2, 3 => [$dia(1), '08:15:00', $this->aulasConEncargado(['692B' => $docente])],
                4 => [$dia(-1), '14:15:00', $this->aulasConEncargado(['693A' => $docente, '693B' => $docente])],
                default => [$dia(4), '10:45:00', $this->aulasConEncargado(['692B' => $docente])],
            };

            $parcial = $this->examen($asignaturaId, 'PRIMER_PARCIAL', $docente, $fecha, $hora, 90, $gruposDeAsignatura, $aulas, null);

            if ($parcial['nuevo'] && $numero === 0) {
                // Listo: todos revisados, repartidos y con su QR.
                $reparto = $this->habilitar($parcial['id'], $inscritos, array_keys($aulas), $docente, [7 => 'No entregó la práctica 3.']);
                $this->emitirQr($parcial['id'], array_keys($reparto), $docente, true);
            }

            if ($parcial['nuevo'] && $numero === 4) {
                // Ayer: ya se rindio.
                $reparto = $this->habilitar($parcial['id'], $inscritos, array_keys($aulas), $docente, [5 => 'No entregó la práctica 3.']);
                $this->emitirQr($parcial['id'], array_keys($reparto), $docente, true);
                $this->ingresar($parcial['id'], $reparto, $aulas, 88, 25 * 60, 24 * 3600 + 3600);
            }

            if ($parcial['nuevo'] && $numero === 5) {
                $this->habilitar($parcial['id'], array_slice($inscritos, 0, 30), [], $docente);
            }

            $this->examen($asignaturaId, 'SEGUNDO_PARCIAL', $docente, $this->enVentana('segundos_parciales', $numero), '08:15:00', 90, $gruposDeAsignatura, [], null);
            $this->examen($asignaturaId, 'FINAL', $docente, $this->enVentana('finales_y_segunda_instancia', $numero), '08:15:00', 120, $gruposDeAsignatura, [], null);

            $numero++;
        }
    }

    /**
     * Crea el examen o, si ya existe (misma asignatura, tipo y autor),
     * solo lo vuelve a fechar. Devuelve su id, si es nuevo y quien atiende
     * cada aula.
     *
     * @param  list<int>  $grupos
     * @param  array<int, int>  $aulas  aula => cuenta a cargo
     * @return array{id: int, nuevo: bool, aulas: array<int, int>}
     */
    protected function examen(
        int $asignaturaId,
        string $tipo,
        int $creadoPor,
        string $fecha,
        string $hora,
        int $duracion,
        array $grupos,
        array $aulas,
        ?string $normas,
    ): array {
        $clave = ['asignatura_id' => $asignaturaId, 'tipo' => $tipo, 'creado_por' => $creadoPor, 'periodo_id' => $this->periodoId];
        $existente = DB::table('examenes')->where($clave)->value('id');

        if ($existente !== null) {
            DB::table('examenes')->where('id', $existente)->update([
                'fecha' => $fecha,
                'hora_inicio' => $hora,
                'updated_at' => now(),
            ]);

            return ['id' => $this->entero($existente), 'nuevo' => false, 'aulas' => $aulas];
        }

        $id = DB::table('examenes')->insertGetId($clave + [
            'fecha' => $fecha,
            'hora_inicio' => $hora,
            'duracion_minutos' => $duracion,
            'normas' => $normas,
            'created_at' => now()->subDays(6),
            'updated_at' => now()->subDays(6),
        ]);

        foreach ($grupos as $grupoId) {
            DB::table('examen_grupo')->insert(['examen_id' => $id, 'grupo_id' => $grupoId]);
        }

        foreach ($aulas as $aulaId => $encargado) {
            DB::table('examen_aula')->insert([
                'examen_id' => $id,
                'aula_id' => $aulaId,
                'encargado_id' => $encargado,
                'created_at' => now()->subDays(6),
                'updated_at' => now()->subDays(6),
            ]);
        }

        return ['id' => $id, 'nuevo' => true, 'aulas' => $aulas];
    }

    /**
     * Revisa a los estudiantes: todos habilitados salvo las posiciones de
     * `$noHabilitados` (posicion => motivo). Con aulas, reparte a los
     * habilitados por turno, que es dejar cada vez al aula con menos carga.
     *
     * @param  list<int>  $estudiantes
     * @param  list<int>  $aulas
     * @param  array<int, string>  $noHabilitados
     * @return array<int, int|null> estudiante habilitado => su aula
     */
    protected function habilitar(int $examenId, array $estudiantes, array $aulas, int $por, array $noHabilitados = []): array
    {
        $filas = [];
        $reparto = [];
        $turno = 0;

        foreach ($estudiantes as $posicion => $estudianteId) {
            $motivo = $noHabilitados[$posicion] ?? null;
            $aulaId = null;

            if ($motivo === null) {
                $aulaId = $aulas === [] ? null : $aulas[$turno++ % count($aulas)];
                $reparto[$estudianteId] = $aulaId;
            }

            $filas[] = [
                'examen_id' => $examenId,
                'estudiante_id' => $estudianteId,
                'habilitado' => $motivo === null,
                'aula_id' => $aulaId,
                'motivo' => $motivo,
                'registrada_por' => $por,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ];
        }

        foreach (array_chunk($filas, self::LOTE) as $lote) {
            DB::table('habilitaciones')->insertOrIgnore($lote);
        }

        return $reparto;
    }

    /**
     * @param  list<int>  $estudiantes
     */
    protected function emitirQr(int $examenId, array $estudiantes, int $por, bool $enviados): void
    {
        $codigos = [];
        $envios = [];
        $emitido = now()->subDays(2);

        $universitarios = [];

        foreach (array_chunk($estudiantes, 1000) as $lote) {
            foreach (DB::table('estudiantes')->whereIn('id', $lote)->pluck('codigo_universitario', 'id') as $id => $codigo) {
                $universitarios[(int) $id] = $this->texto($codigo);
            }
        }

        foreach ($estudiantes as $posicion => $estudianteId) {
            // A tres de cada examen el correo les reboto.
            $rebotado = $enviados && $posicion % 97 === 13;
            $enviado = $enviados && ! $rebotado;

            $codigos[] = [
                'examen_id' => $examenId,
                'estudiante_id' => $estudianteId,
                'token' => Str::random(48),
                'emitido_en' => $emitido,
                'emitido_por' => $por,
                'enviado_en' => $enviado ? $emitido->copy()->addMinutes(4) : null,
                'created_at' => $emitido,
                'updated_at' => $emitido,
            ];

            if ($enviados) {
                $envios[] = [
                    'tipo' => 'CODIGO_QR',
                    'destinatario' => ($universitarios[$estudianteId] ?? 'sin-codigo').'@'.$this->dominioDeCorreo(),
                    'examen_id' => $examenId,
                    'estudiante_id' => $estudianteId,
                    'estado' => $rebotado ? 'FALLIDO' : 'ENVIADO',
                    'error' => $rebotado ? 'El buzón del destinatario no existe.' : null,
                    'solicitado_por' => $por,
                    'enviado_en' => $enviado ? $emitido->copy()->addMinutes(4) : null,
                    'created_at' => $emitido,
                    'updated_at' => $emitido->copy()->addMinutes(4),
                ];
            }
        }

        foreach (array_chunk($codigos, self::LOTE) as $lote) {
            DB::table('codigos_qr')->insertOrIgnore($lote);
        }

        foreach (array_chunk($envios, self::LOTE) as $lote) {
            DB::table('envios_correo')->insert($lote);
        }
    }

    /**
     * Asienta el ingreso de `$porcentaje` de cada cien habilitados, cada uno
     * por su aula, repartidos a lo largo de `$ventana` segundos que
     * terminaron hace `$hace` segundos. Lo registra quien atiende el aula o,
     * en las aulas de `$accesos`, quien entro con el codigo de aula.
     *
     * @param  array<int, int|null>  $reparto  estudiante => aula
     * @param  array<int, int>  $encargados  aula => cuenta a cargo
     * @param  array<int, int>  $accesos  aula => acceso con codigo
     */
    protected function ingresar(
        int $examenId,
        array $reparto,
        array $encargados,
        int $porcentaje,
        int $ventana,
        int $hace,
        array $accesos = [],
    ): void {
        $elegidos = [];
        $posicion = 0;

        foreach ($reparto as $estudianteId => $aulaId) {
            // Salteado, para que no entren solo los primeros de la lista.
            if ($aulaId !== null && ($posicion++ * 37) % 100 < $porcentaje) {
                $elegidos[$estudianteId] = $aulaId;
            }
        }

        $filas = [];
        $total = max(1, count($elegidos));
        $orden = 0;

        foreach ($elegidos as $estudianteId => $aulaId) {
            $momento = now()->subSeconds($hace + $ventana - intdiv($ventana * $orden, $total));
            $acceso = $accesos[$aulaId] ?? null;

            $filas[] = [
                'examen_id' => $examenId,
                'estudiante_id' => $estudianteId,
                'aula_id' => $aulaId,
                'medio' => match (true) {
                    $orden % 10 < 7 => 'QR',
                    $orden % 10 < 9 => 'CODIGO',
                    default => 'DOCUMENTO',
                },
                'es_excepcion' => false,
                'registrado_en' => $momento,
                'registrado_por' => $acceso === null ? ($encargados[$aulaId] ?? null) : null,
                'acceso_aula_id' => $acceso,
                'created_at' => $momento,
            ];

            $orden++;
        }

        // Los ingresos son inmutables: solo se insertan.
        foreach (array_chunk($filas, self::LOTE) as $lote) {
            DB::table('ingresos')->insertOrIgnore($lote);
        }
    }

    /**
     * Unos asientos de bitacora con las cuentas de ejemplo, ademas de los
     * que deja la importacion.
     */
    private function bitacora(): void
    {
        if (DB::table('bitacora_operaciones')->where('operacion', 'examen.registrar')->exists()) {
            return;
        }

        $administrador = $this->cuentas['admin'];
        $leticia = $this->cuentas['leticiablanco'];
        // El nombre como lo trae la oferta, igual que en las demas pantallas.
        $introduccion = $this->texto(DB::table('asignaturas')->where('codigo', self::INTRODUCCION)->value('nombre'));

        // Sin asientos de respaldos: la demo no trae respaldos y la bitacora
        // no puede nombrar uno que la pantalla de Respaldo no muestra.
        $asientos = [
            [$administrador, 'sesion.iniciar', 'users', $administrador, 'Inicio de sesión.', 9 * 1440],
            [$administrador, 'usuario.crear', 'users', $leticia, 'Cuenta leticiablanco creada con el rol Docente.', 9 * 1440 - 20],
            [$administrador, 'docente.activar_cuenta', 'docentes', null, 'Cuenta activada para Blanco Coca Leticia.', 9 * 1440 - 20],
            [$leticia, 'sesion.iniciar', 'users', $leticia, 'Inicio de sesión.', 6 * 1440 + 30],
            [$leticia, 'inscritos.cargar', 'cargas_inscritos', null, "Lista de {$introduccion}, grupo 2: 65 filas.", 6 * 1440 + 20],
            [$leticia, 'examen.registrar', 'examenes', null, "Primer parcial de {$introduccion} registrado con 4 grupos y 5 aulas.", 6 * 1440],
            [$leticia, 'auxiliar.registrar', 'users', $this->cuentas['diegomamani'], 'Auxiliar diegomamani registrado.', 6 * 1440 - 5],
            [$leticia, 'habilitacion.habilitar', 'habilitaciones', null, "Habilitación del primer parcial de {$introduccion}: 9 no habilitados.", 3 * 1440],
            [$leticia, 'habilitacion.repartir', 'habilitaciones', null, 'Reparto en 5 aulas.', 3 * 1440 - 3],
            [$leticia, 'qr.emitir', 'codigos_qr', null, 'Códigos QR emitidos y enviados por correo.', 2 * 1440],
            [$leticia, 'sesion.iniciar', 'users', $leticia, 'Inicio de sesión.', 70],
            [$this->cuentas['diegomamani'], 'sesion.iniciar', 'users', $this->cuentas['diegomamani'], 'Inicio de sesión.', 35],
        ];

        foreach ($asientos as [$usuario, $operacion, $tabla, $registro, $descripcion, $haceMinutos]) {
            DB::table('bitacora_operaciones')->insert([
                'usuario_id' => $usuario,
                'operacion' => $operacion,
                'tabla_afectada' => $tabla,
                'registro_id' => $registro,
                'descripcion' => $descripcion,
                'fecha_operacion' => now()->subMinutes($haceMinutos),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Apoyos
    // ------------------------------------------------------------------

    /**
     * @param  array<int|string, int>  $porNombre  nombre del aula => cuenta a cargo
     * @return array<int, int> aula => cuenta a cargo
     */
    protected function aulasConEncargado(array $porNombre): array
    {
        $aulas = [];

        foreach ($porNombre as $nombre => $encargado) {
            $aulas[$this->id('aulas', ['nombre' => (string) $nombre])] = $encargado;
        }

        return $aulas;
    }

    /**
     * El grupo de FCyT de esa asignatura en el periodo de la oferta.
     */
    protected function grupo(string $codigoAsignatura, string $codigo): int
    {
        return $this->id('grupos', [
            'asignatura_id' => $this->id('asignaturas', ['codigo' => $codigoAsignatura]),
            'facultad_id' => $this->fcytId,
            'periodo_id' => $this->periodoId,
            'codigo' => $codigo,
        ]);
    }

    /**
     * @param  array<string, int|string>  $donde
     */
    protected function id(string $tabla, array $donde): int
    {
        $id = DB::table($tabla)->where($donde)->value('id');

        if (! is_numeric($id)) {
            throw new RuntimeException("Falta un dato de la oferta en {$tabla}: ".json_encode($donde));
        }

        return (int) $id;
    }

    protected function dominioDeCorreo(): string
    {
        $dominio = config('umss.dominio_correo_estudiantes');

        return is_string($dominio) ? $dominio : 'est.umss.edu';
    }

    /**
     * @param  iterable<mixed>  $valores
     * @return list<int>
     */
    protected function enteros(iterable $valores): array
    {
        $enteros = [];

        foreach ($valores as $valor) {
            $enteros[] = $this->entero($valor);
        }

        return $enteros;
    }

    protected function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    protected function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }
}
