<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
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
 * deja el periodo vigente, las cuentas, un padron ficticio con
 * inscripciones, las plantillas de normas y examenes en todos los estados
 * del sprint. **Todo va fechado respecto del momento en que se ejecuta**
 * (zona `umss.zona_horaria`).
 *
 * Cuentas (el acceso es por correo):
 *
 *   admin@umss.edu.bo            / Admin12345        Administrador (preparación y sistema).
 *   leticia.blanco@umss.edu.bo   / Docente12345      Docente, carga normal: Blanco Coca Leticia.
 *                                                    Sus datos de ejemplo estan en 2 asignaturas
 *                                                    (Introduccion a la Programacion y Taller de
 *                                                    Ingenieria de Software); sus otros grupos
 *                                                    reales quedan sin lista.
 *   carga.alta@umss.edu.bo       / Docente12345      Docente, carga alta: el docente real de FCyT
 *                                                    con mas asignaturas; 18 examenes en 6 de ellas.
 *   carla.salazar@umss.edu.bo    / Docente12345      Docente de los grupos 1 y 6 de Introduccion.
 *   erika.rodriguez@umss.edu.bo  / Docente12345      Docente que registro el parcial conjunto de Taller.
 *   americo.fiorilo@umss.edu.bo  / Docente12345      Docente del parcial de Calculo I que comparte
 *                                                    el aula 691A con el examen de hoy.
 *   corina.flores@umss.edu.bo    / Fa7-Kmq4-Ru9      Docente con contrasena temporal («Temporal»
 *                                                    en Docentes; entra igual, el cambio obligatorio
 *                                                    llega con HU-16).
 *   auxiliar@umss.edu.bo         / Auxiliar12345     Auxiliar: entra y no tiene pantallas todavia.
 *   coordinacion@umss.edu.bo     / Coordinador12345  Rol creado «Coordinador» (Periodo, Aulas y
 *                                                    Docentes).
 *   victor.montano@umss.edu.bo   (sin acceso)        Cuenta bloqueada.
 *
 * Examenes de leticia.blanco: (a) primer parcial de Introduccion, hoy, 4
 * grupos y 5 aulas (la 691A compartida con Calculo I), habilitado con 9 no
 * habilitados y repartido; (b) parcial conjunto de Taller en dos dias,
 * registrado por otra docente, a medio habilitar; (c) segundo parcial de
 * Introduccion, sin aulas; (d) segundo parcial de Taller, con aula y sin
 * habilitar.
 *
 * Ademas, uno de cada cuatro docentes tiene cuenta (contrasena al azar que
 * nadie conoce), para que los filtros de Docentes tengan que mostrar.
 *
 * Se puede volver a ejecutar: no duplica. La segunda vez solo vuelve a
 * fechar los examenes. No corre en el despliegue.
 */
class DemoSeeder extends Seeder
{
    public const CLAVE_ADMINISTRADOR = 'Admin12345';

    public const CLAVE_DOCENTE = 'Docente12345';

    public const CLAVE_AUXILIAR = 'Auxiliar12345';

    public const CLAVE_COORDINADOR = 'Coordinador12345';

    public const TEMPORAL = 'Fa7-Kmq4-Ru9';

    public const ROL_CREADO = 'Coordinador';

    /**
     * Las dos plantillas de normas propias de la docente de ejemplo.
     *
     * @var list<string>
     */
    public const PLANTILLAS_DE_LETICIA = [
        'Solo se permite el formulario oficial',
        'Práctica impresa y firmada',
    ];

    private const DOMINIO = 'umss.edu.bo';

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

        $this->call([
            RolePermissionSeeder::class,
            FacultadSeeder::class,
            NormasPredefinidasSeeder::class,
        ]);

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
        $this->plantillas();
        $this->examenesDeLeticia();
        $this->examenesDeCargaAlta();
        $this->bitacora();
    }

    /**
     * La hora de la universidad, que es la de la aplicacion
     * (`config('umss.zona_horaria')`): en ella se guardan la fecha y la hora
     * de un examen, como las teclea el docente, y tambien los instantes.
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

        foreach (Periodo::where('numero', 0)->get() as $anual) {
            $this->contenerHoy($anual, $hoy, $anual->anio.'-02-02', $anual->anio.'-12-19');
        }
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
        $this->cuenta('administracion.academica', 'admin', 'Administración Académica', 'Administrador', self::CLAVE_ADMINISTRADOR);

        $docentes = [
            'leticia.blanco' => 'BLANCO COCA LETICIA',
            'carla.salazar' => 'SALAZAR SERRUDO CARLA',
            'erika.rodriguez' => 'RODRIGUEZ BILBAO ERIKA PATRICIA',
            'americo.fiorilo' => 'FIORILO LOZADA AMERICO',
        ];

        foreach ($docentes as $usuario => $normalizado) {
            $this->cuentaDeDocente($usuario, $normalizado, self::CLAVE_DOCENTE);
        }

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

        $this->cuentaDeDocente('carga.alta', $cargaAlta, self::CLAVE_DOCENTE);

        // Contrasena temporal vigente: la pantalla Docentes la muestra
        // como «Temporal».
        $corina = $this->cuentaDeDocente('corina.flores', 'FLORES VILLARROEL CORINA', self::TEMPORAL);

        User::whereKey($corina)->firstOrFail()->forceFill([
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->addHours(72),
        ])->save();

        // Bloqueada: la interfaz la muestra como «Bloqueado».
        $victor = $this->cuentaDeDocente('victor.montano', 'MONTANO QUIROGA VICTOR HUGO', Str::random(24));

        User::whereKey($victor)->firstOrFail()->forceFill(['is_active' => false])->save();

        // Entra y ve «Sin pantallas asignadas»: sus dos permisos todavia no
        // tienen vista.
        $this->cuenta('diego.mamani', 'auxiliar', 'Diego Mamani Quispe', 'Auxiliar', self::CLAVE_AUXILIAR);

        // Un rol creado desde la pantalla, para verlo en la matriz.
        $this->rolCreado(self::ROL_CREADO, ['periodo_oferta', 'aulas_docentes']);
        $this->cuenta('coordinacion', 'coordinacion', 'Coordinación de Carrera', self::ROL_CREADO, self::CLAVE_COORDINADOR);
    }

    /**
     * Cuenta con contrasena propia ya definida. No pasa por el contrato de
     * cuentas, que siempre emite una temporal al azar. `$buzon` es la parte
     * local del correo, que es con lo que se entra.
     */
    protected function cuenta(string $usuario, string $buzon, string $nombre, string $rol, string $clave): int
    {
        $cuenta = User::firstOrNew(['usuario' => $usuario]);

        $cuenta->forceFill([
            'nombre' => $nombre,
            'correo' => $buzon.'@'.self::DOMINIO,
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

    private function cuentaDeDocente(string $usuario, string $normalizado, string $clave): int
    {
        $docente = DB::table('docentes')->where('nombre_normalizado', $normalizado)->first();

        if (! $docente instanceof stdClass || ! is_string($docente->nombre_completo)) {
            throw new RuntimeException("La oferta importada no trae al docente {$normalizado}.");
        }

        $id = $this->cuenta($usuario, $usuario, $docente->nombre_completo, 'Docente', $clave);

        DB::table('docentes')->where('id', $docente->id)->update(['user_id' => $id]);

        return $id;
    }

    /**
     * @param  list<string>  $permisos
     */
    private function rolCreado(string $nombre, array $permisos): void
    {
        $rol = Role::firstOrCreate(['name' => $nombre], ['es_sistema' => false]);

        foreach (DB::table('permissions')->whereIn('name', $permisos)->pluck('id') as $permisoId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $rol->id,
                'permission_id' => $permisoId,
            ]);
        }
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
            $usuario = $proponedor->proponer($nombre);
            $temporal = ++$numero % 5 === 0;

            $id = DB::table('usuarios')->insertGetId([
                'nombre' => $nombre,
                'usuario' => $usuario,
                'correo' => $usuario.'@'.self::DOMINIO,
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
        $administrador = $this->cuentas['administracion.academica'];

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
     * Tres conflictos pendientes: la lista de un docente trajo a tres
     * estudiantes del padron con otro documento u otro nombre. Su
     * inscripcion al grupo queda en espera.
     */
    private function conflictos(): void
    {
        if (DB::table('conflictos_padron')->exists()) {
            return;
        }

        $leticia = $this->cuentas['leticia.blanco'];
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
    // Normas
    // ------------------------------------------------------------------

    /**
     * Dos plantillas propias de la docente de ejemplo, ademas de las
     * predefinidas del sistema.
     */
    private function plantillas(): void
    {
        foreach (self::PLANTILLAS_DE_LETICIA as $texto) {
            DB::table('plantillas_norma')->updateOrInsert(
                ['usuario_id' => $this->cuentas['leticia.blanco'], 'texto' => $texto],
                ['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)],
            );
        }
    }

    /**
     * Marca en el examen las plantillas de esos textos (predefinidas o de
     * `$usuarioId`), guardando la copia del texto.
     *
     * @param  list<string>  $textos
     */
    private function marcarNormas(int $examenId, int $usuarioId, array $textos): void
    {
        foreach ($textos as $orden => $texto) {
            $plantilla = DB::table('plantillas_norma')
                ->where('texto', $texto)
                ->where(function (Builder $consulta) use ($usuarioId): void {
                    $consulta->whereNull('usuario_id')->orWhere('usuario_id', $usuarioId);
                })
                ->value('id');

            if ($plantilla === null) {
                throw new RuntimeException("Falta la plantilla de norma «{$texto}».");
            }

            DB::table('examen_norma')->insertOrIgnore([
                'examen_id' => $examenId,
                'plantilla_id' => $plantilla,
                'texto' => $texto,
                'orden' => $orden + 1,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Examenes
    // ------------------------------------------------------------------

    private function examenesDeLeticia(): void
    {
        $leticia = $this->cuentas['leticia.blanco'];
        $carla = $this->cuentas['carla.salazar'];
        $erika = $this->cuentas['erika.rodriguez'];
        $americo = $this->cuentas['americo.fiorilo'];

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

        // (a) Primer parcial de Introduccion a la Programacion: hoy, cuatro
        // grupos (uno propio y tres de otros docentes) y cinco aulas.
        $examenHoy = $this->examen(
            $introduccion,
            'PRIMER_PARCIAL',
            $leticia,
            $inicio->toDateString(),
            $inicio->format('H:i:s'),
            90,
            $gruposHoy,
            $this->aulas(['691B', '691A', '691E', '617', '624']),
            "Traer la hoja de respuestas sellada por la jefatura.\nLas mochilas quedan al frente del aula.",
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
            $this->aulas(['691A', '692A']),
            null,
        );

        if ($examenCalculo['nuevo']) {
            $this->habilitar($examenCalculo['id'], $inscritosCalculo, $examenCalculo['aulas'], $americo);
        }

        if ($examenHoy['nuevo']) {
            $this->marcarNormas($examenHoy['id'], $leticia, [
                NormasPredefinidasSeeder::NORMAS[0],
                NormasPredefinidasSeeder::NORMAS[1],
                self::PLANTILLAS_DE_LETICIA[0],
            ]);

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

            // Habilitacion completa y reparto hecho.
            $this->habilitar($examenHoy['id'], $inscritosHoy, $examenHoy['aulas'], $leticia, $noHabilitados);
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
            $this->aulas(['690E', 'INFLAB']),
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
            $this->ahoraLocal->copy()->addDays(45)->toDateString(),
            '14:15:00',
            90,
            [$gruposHoy[1]],
            [],
            null,
        );

        // (d) Segundo parcial de Taller: con aula, sin habilitar.
        $segundoTaller = $this->examen(
            $taller,
            'SEGUNDO_PARCIAL',
            $leticia,
            $this->ahoraLocal->copy()->addDays(47)->toDateString(),
            '08:15:00',
            120,
            [$grupoTaller],
            $this->aulas(['INFLAB']),
            null,
        );

        if ($segundoTaller['nuevo']) {
            $this->marcarNormas($segundoTaller['id'], $leticia, [self::PLANTILLAS_DE_LETICIA[1]]);
        }
    }

    /**
     * 18 examenes en seis asignaturas del docente de carga alta: los
     * primeros parciales (uno hoy en doce aulas, habilitado y repartido;
     * tres mañana a la misma hora en la misma aula; uno ayer; uno a medio
     * habilitar) y los segundos parciales y finales, sin aulas.
     */
    private function examenesDeCargaAlta(): void
    {
        $docente = $this->cuentas['carga.alta'];

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

        $dia = fn (int $dias): string => $this->ahoraLocal->copy()->addDays($dias)->toDateString();

        // Hoy, dentro de 45 minutos.
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
                0 => [$pronto->toDateString(), $pronto->format('H:i:s'), $doce],
                1, 2, 3 => [$dia(1), '08:15:00', $this->aulas(['692B'])],
                4 => [$dia(-1), '14:15:00', $this->aulas(['693A', '693B'])],
                default => [$dia(4), '10:45:00', $this->aulas(['692B'])],
            };

            $parcial = $this->examen($asignaturaId, 'PRIMER_PARCIAL', $docente, $fecha, $hora, 90, $gruposDeAsignatura, $aulas, null);

            if ($parcial['nuevo'] && $numero === 0) {
                // Todos revisados y repartidos.
                $this->habilitar($parcial['id'], $inscritos, $aulas, $docente, [7 => 'No entregó la práctica 3.']);
            }

            if ($parcial['nuevo'] && $numero === 4) {
                // Ayer: quedo habilitado y repartido.
                $this->habilitar($parcial['id'], $inscritos, $aulas, $docente, [5 => 'No entregó la práctica 3.']);
            }

            if ($parcial['nuevo'] && $numero === 5) {
                $this->habilitar($parcial['id'], array_slice($inscritos, 0, 30), [], $docente);
            }

            $this->examen($asignaturaId, 'SEGUNDO_PARCIAL', $docente, $dia(44 + $numero), '08:15:00', 90, $gruposDeAsignatura, [], null);
            $this->examen($asignaturaId, 'FINAL', $docente, $dia(58 + $numero), '08:15:00', 120, $gruposDeAsignatura, [], null);

            $numero++;
        }
    }

    /**
     * Crea el examen o, si ya existe (misma asignatura, tipo y autor),
     * solo lo vuelve a fechar. Devuelve su id, si es nuevo y sus aulas.
     *
     * @param  list<int>  $grupos
     * @param  list<int>  $aulas
     * @return array{id: int, nuevo: bool, aulas: list<int>}
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

        foreach ($aulas as $aulaId) {
            DB::table('examen_aula')->insert([
                'examen_id' => $id,
                'aula_id' => $aulaId,
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
     * Unos asientos de bitacora con las cuentas de ejemplo, ademas de los
     * que deja la importacion.
     */
    private function bitacora(): void
    {
        if (DB::table('bitacora_operaciones')->where('operacion', 'examen.registrar')->exists()) {
            return;
        }

        $administrador = $this->cuentas['administracion.academica'];
        $leticia = $this->cuentas['leticia.blanco'];

        $asientos = [
            [$administrador, 'sesion.iniciar', 'usuarios', $administrador, null, 9 * 1440],
            [$administrador, 'usuario.registrar', 'usuarios', $leticia, 'Cuenta leticia.blanco creada con el rol Docente.', 9 * 1440 - 20],
            [$administrador, 'docente.activar_cuenta', 'docentes', null, 'Cuenta «leticia.blanco» activada para Blanco Coca Leticia', 9 * 1440 - 20],
            [$administrador, 'rol.crear', 'roles', null, 'Rol Coordinador creado con 2 permisos.', 8 * 1440],
            [$leticia, 'sesion.iniciar', 'usuarios', $leticia, null, 6 * 1440 + 30],
            [$leticia, 'inscritos.cargar', 'cargas_inscritos', null, 'Lista de Introducción a la Programación, grupo 2: 65 filas.', 6 * 1440 + 20],
            [$leticia, 'norma.plantilla_crear', 'plantillas_norma', null, 'Plantilla «Solo se permite el formulario oficial» creada.', 6 * 1440 + 5],
            [$leticia, 'examen.registrar', 'examenes', null, 'Primer parcial de Introducción a la Programación registrado con 4 grupos y 5 aulas.', 6 * 1440],
            [$leticia, 'habilitacion.registrar', 'habilitaciones', null, 'Habilitación del primer parcial de Introducción a la Programación: 9 no habilitados.', 3 * 1440],
            [$leticia, 'habilitacion.repartir', 'habilitaciones', null, 'Reparto en 5 aulas.', 3 * 1440 - 3],
            [$leticia, 'sesion.iniciar', 'usuarios', $leticia, null, 70],
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
     * @param  list<string>  $nombres
     * @return list<int>
     */
    protected function aulas(array $nombres): array
    {
        $aulas = [];

        foreach ($nombres as $nombre) {
            $aulas[] = $this->id('aulas', ['nombre' => $nombre]);
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
