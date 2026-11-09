<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\AccesoAulaGateway;
use App\Modules\Ingreso\Application\DTOs\AccesoAulaData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Un acceso con codigo de aula nace PENDIENTE y solo abre la puerta cuando
 * el docente lo AUTORIZA en persona; puede RECHAZARLO o CORTARLO, y
 * moverlo a otra aula del examen. Las filas no se borran: los ingresos y
 * las incidencias las nombran.
 */
final class EloquentAccesoAulaGateway implements AccesoAulaGateway
{
    use LeeFilas;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function abrir(string $codigo, string $nombre, ?string $ip): ?AccesoAulaData
    {
        $fila = $this->abiertas()
            ->where('asignada.codigo_acceso', $codigo)
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);
        $examenAulaId = $this->entero($columnas['examen_aula_id'] ?? null);

        /** @var int $id */
        $id = DB::transaction(function () use ($examenAulaId, $nombre, $ip, $columnas): int {
            $id = DB::table('accesos_aula')->insertGetId([
                'examen_aula_id' => $examenAulaId,
                'nombre' => $nombre,
                'ip' => $ip,
                'creado_en' => now(),
                'estado' => 'PENDIENTE',
            ]);

            $this->bitacora->registrar(
                null,
                'acceso_aula.solicitar',
                'accesos_aula',
                $id,
                sprintf(
                    '%s pidió controlar el aula %s con su código; espera la autorización del docente.',
                    $nombre,
                    $this->texto($columnas['aula'] ?? null) ?? '',
                ),
                $nombre,
            );

            return $id;
        }, 3);

        return $this->accesoDe($id, $nombre, $columnas, 'PENDIENTE');
    }

    public function deSesion(int $accesoId): ?AccesoAulaData
    {
        $fila = $this->abiertas()
            ->join('accesos_aula as acceso', 'acceso.examen_aula_id', '=', 'asignada.id')
            ->where('acceso.id', $accesoId)
            ->addSelect(['acceso.nombre as acceso_nombre', 'acceso.estado'])
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return $this->accesoDe(
            $accesoId,
            $this->texto($columnas['acceso_nombre'] ?? null) ?? '',
            $columnas,
            $this->texto($columnas['estado'] ?? null) ?? 'PENDIENTE',
        );
    }

    public function tocar(int $accesoId): void
    {
        // Una escritura cada medio minuto basta para saber quien sigue ahi.
        DB::table('accesos_aula')
            ->where('id', $accesoId)
            ->where(function (Builder $vieja): void {
                $vieja->whereNull('ultima_actividad_en')
                    ->orWhere('ultima_actividad_en', '<', now()->subSeconds(30));
            })
            ->update(['ultima_actividad_en' => now()]);
    }

    public function marcarPresencia(int $aulaId, int $usuarioId): void
    {
        // Un aula que no existe la rechaza despues la puerta.
        if (! DB::table('aulas')->where('id', $aulaId)->exists()) {
            return;
        }

        DB::table('presencias_puerta')->upsert(
            [['aula_id' => $aulaId, 'user_id' => $usuarioId, 'visto_en' => now()]],
            ['aula_id', 'user_id'],
            ['visto_en'],
        );
    }

    public function delExamen(int $examenId): array
    {
        $filas = DB::table('accesos_aula as acceso')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'acceso.examen_aula_id')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('asignada.examen_id', $examenId)
            ->whereIn('acceso.estado', ['PENDIENTE', 'AUTORIZADO'])
            ->orderBy('acceso.creado_en')
            ->orderBy('acceso.id')
            ->get([
                'acceso.id',
                'acceso.nombre',
                'acceso.estado',
                'acceso.creado_en',
                'acceso.ultima_actividad_en',
                'asignada.aula_id',
                'aula.nombre as aula',
            ]);

        $accesos = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $accesos[] = [
                'id' => $this->entero($columnas['id'] ?? null),
                'nombre' => $this->texto($columnas['nombre'] ?? null) ?? '',
                'estado' => mb_strtolower($this->texto($columnas['estado'] ?? null) ?? ''),
                'aula_id' => $this->entero($columnas['aula_id'] ?? null),
                'aula' => $this->texto($columnas['aula'] ?? null) ?? '',
                'pedido_en' => $this->iso($this->texto($columnas['creado_en'] ?? null)),
                'visto_en' => $this->iso($this->texto($columnas['ultima_actividad_en'] ?? null)),
            ];
        }

        return $accesos;
    }

    public function presentes(int $examenId, int $minutos): array
    {
        $filas = DB::table('presencias_puerta as presencia')
            ->join('examen_aula as asignada', 'asignada.aula_id', '=', 'presencia.aula_id')
            ->join('users as usuario', 'usuario.id', '=', 'presencia.user_id')
            ->where('asignada.examen_id', $examenId)
            ->where('presencia.visto_en', '>=', now()->subMinutes($minutos))
            ->orderBy('usuario.name')
            ->get(['presencia.aula_id', 'presencia.user_id', 'usuario.name', 'presencia.visto_en']);

        $presentes = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $presentes[] = [
                'aula_id' => $this->entero($columnas['aula_id'] ?? null),
                'usuario_id' => $this->entero($columnas['user_id'] ?? null),
                'nombre' => $this->texto($columnas['name'] ?? null) ?? '',
                'visto_en' => $this->iso($this->texto($columnas['visto_en'] ?? null)),
            ];
        }

        return $presentes;
    }

    public function resolver(int $accesoId, int $examenId, string $estado, int $usuarioId, ?int $aulaId = null): ?string
    {
        return DB::transaction(function () use ($accesoId, $examenId, $estado, $usuarioId, $aulaId): ?string {
            $fila = DB::table('accesos_aula as acceso')
                ->join('examen_aula as asignada', 'asignada.id', '=', 'acceso.examen_aula_id')
                ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
                ->where('acceso.id', $accesoId)
                ->where('asignada.examen_id', $examenId)
                ->lockForUpdate()
                ->first(['acceso.nombre', 'acceso.estado', 'asignada.id as examen_aula_id', 'aula.nombre as aula']);

            if ($fila === null) {
                return null;
            }

            $columnas = $this->columnas($fila);
            $anterior = $this->texto($columnas['estado'] ?? null) ?? '';
            $nombre = $this->texto($columnas['nombre'] ?? null) ?? '';
            $aula = $this->texto($columnas['aula'] ?? null) ?? '';
            $destino = $this->entero($columnas['examen_aula_id'] ?? null);

            // Solo lo vigente cambia: lo rechazado o cortado queda asi.
            if (! in_array($anterior, ['PENDIENTE', 'AUTORIZADO'], true)) {
                return $anterior;
            }

            if ($aulaId !== null) {
                $otra = DB::table('examen_aula as asignada')
                    ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
                    ->where('asignada.examen_id', $examenId)
                    ->where('asignada.aula_id', $aulaId)
                    ->first(['asignada.id', 'aula.nombre']);

                if ($otra === null) {
                    return 'AULA_AJENA';
                }

                $columnasOtra = $this->columnas($otra);
                $destino = $this->entero($columnasOtra['id'] ?? null);
                $aula = $this->texto($columnasOtra['nombre'] ?? null) ?? $aula;
            }

            DB::table('accesos_aula')->where('id', $accesoId)->update([
                'estado' => $estado,
                'examen_aula_id' => $destino,
                'resuelto_por' => $usuarioId,
                'resuelto_en' => now(),
            ]);

            [$operacion, $texto] = match (true) {
                $estado === 'RECHAZADO' => ['acceso_aula.rechazar', '%s no fue autorizado a controlar el aula %s.'],
                $estado === 'CORTADO' => ['acceso_aula.cortar', 'Se cortó el acceso de %s al aula %s.'],
                $anterior === 'AUTORIZADO' => ['acceso_aula.trasladar', '%s pasó a controlar el aula %s.'],
                default => ['acceso_aula.autorizar', '%s fue autorizado a controlar el aula %s.'],
            };

            $this->bitacora->registrar($usuarioId, $operacion, 'accesos_aula', $accesoId, sprintf($texto, $nombre, $aula));

            return $estado;
        }, 3);
    }

    public function ventanaDelCodigo(string $codigo): ?array
    {
        $inicio = '(examen.fecha + examen.hora_inicio)';
        $abre = "({$inicio} - (? * interval '1 minute'))";
        $cierra = "coalesce(examen.finalizado_en, examen.fecha + interval '1 day')";

        $fila = DB::table('examen_aula as asignada')
            ->join('examenes as examen', 'examen.id', '=', 'asignada.examen_id')
            ->where('asignada.codigo_acceso', $codigo)
            ->selectRaw("to_char({$abre}, 'YYYY-MM-DD HH24:MI') as abre", [$this->minutosDeApertura()])
            ->selectRaw("to_char({$cierra}, 'YYYY-MM-DD HH24:MI') as cierra")
            ->selectRaw("{$cierra} < ?::timestamp as cerrada", [$this->ahora()])
            // Primero lo que viene, del mas cercano; despues lo ya cerrado,
            // del mas reciente.
            ->orderByRaw("case when {$cierra} >= ?::timestamp then 0 else 1 end", [$this->ahora()])
            ->orderByRaw("abs(extract(epoch from ({$inicio} - ?::timestamp)))", [$this->ahora()])
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return [
            'abre' => $this->texto($columnas['abre'] ?? null) ?? '',
            'cierra' => $this->texto($columnas['cierra'] ?? null) ?? '',
            'cerrada' => (bool) ($columnas['cerrada'] ?? false),
        ];
    }

    public function vigente(int $accesoId): ?AccesoAulaData
    {
        $fila = $this->abiertas()
            ->join('accesos_aula as acceso', 'acceso.examen_aula_id', '=', 'asignada.id')
            ->where('acceso.id', $accesoId)
            ->where('acceso.estado', 'AUTORIZADO')
            ->addSelect('acceso.nombre as acceso_nombre')
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return $this->accesoDe(
            $accesoId,
            $this->texto($columnas['acceso_nombre'] ?? null) ?? '',
            $columnas,
        );
    }

    /**
     * Las aulas de examen cuya ventana contiene la hora del servidor.
     */
    private function abiertas(): Builder
    {
        $inicio = '(examen.fecha + examen.hora_inicio)';
        // Vale hasta que el docente finaliza el examen (o termina su dia);
        // `fin` muestra el termino previsto, o el real si ya finalizo.
        $cierre = "coalesce(examen.finalizado_en, examen.fecha + interval '1 day')";
        $fin = "coalesce(examen.finalizado_en, {$inicio} + (examen.duracion_minutos * interval '1 minute'))";

        return DB::table('examen_aula as asignada')
            ->join('examenes as examen', 'examen.id', '=', 'asignada.examen_id')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->whereRaw(
                "{$inicio} - (? * interval '1 minute') <= ?::timestamp and ?::timestamp < {$cierre}",
                [$this->minutosDeApertura(), $this->ahora(), $this->ahora()],
            )
            ->select([
                'asignada.id as examen_aula_id',
                'asignada.examen_id',
                'asignada.aula_id',
                'aula.nombre as aula',
            ])
            ->selectRaw("to_char({$fin}, 'YYYY-MM-DD HH24:MI:SS') as fin");
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function accesoDe(int $id, string $nombre, array $columnas, string $estado = 'AUTORIZADO'): AccesoAulaData
    {
        return new AccesoAulaData(
            id: $id,
            aulaId: $this->entero($columnas['aula_id'] ?? null),
            aula: $this->texto($columnas['aula'] ?? null) ?? '',
            examenId: $this->entero($columnas['examen_id'] ?? null),
            nombre: $nombre,
            hasta: $this->iso($this->texto($columnas['fin'] ?? null)) ?? '',
            estado: $estado,
        );
    }
}
