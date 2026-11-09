<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\AuxiliarGateway;
use App\Modules\Examenes\Application\DTOs\AuxiliarData;
use App\Modules\Examenes\Domain\Models\Auxiliar;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * El auxiliar es una cuenta (`users`, de Administracion) con rol Auxiliar:
 * la cuenta y el rol se leen por nombre de tabla.
 */
final class EloquentAuxiliarGateway implements AuxiliarGateway
{
    use ConvierteFilas;

    private const ROL = 'Auxiliar';

    private const MAXIMO_AL_BUSCAR = 20;

    /**
     * La cuenta atiende, a cargo o de apoyo, un aula de un examen que
     * registro el docente y que todavia no termino. Parametros: el docente
     * y el instante actual.
     */
    private const ATIENDE_PENDIENTE = <<<'SQL'
        exists (
            select 1
            from examen_aula as asignada
            join examenes as examen on examen.id = asignada.examen_id
            where examen.creado_por = ?
              and coalesce(examen.finalizado_en, examen.fecha + interval '1 day') > ?
              and (
                  asignada.encargado_id = cuenta.id
                  or exists (
                      select 1
                      from examen_aula_apoyo as apoyo
                      where apoyo.examen_aula_id = asignada.id
                        and apoyo.user_id = cuenta.id
                  )
              )
        )
        SQL;

    /**
     * Es «mio» si lo registro este docente o lo tiene, a cargo o de apoyo,
     * en un aula de un examen suyo que todavia no termino. Parametros: el
     * docente, el docente y el instante actual.
     */
    private const ES_MIO = '(coalesce(auxiliar.registrado_por = ?, false) or '.self::ATIENDE_PENDIENTE.')';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function listar(int $usuarioId, ?string $buscar): array
    {
        $consulta = $this->base($usuarioId)
            ->where('cuenta.is_active', true)
            ->orderByRaw('mio desc')
            ->orderBy('cuenta.name')
            ->orderBy('cuenta.id');

        if ($buscar === null) {
            $consulta->whereRaw(self::ES_MIO, $this->deMio($usuarioId));
        } else {
            $patron = '%'.$this->sinTildes($buscar).'%';

            $consulta
                ->where(function (Builder $coincide) use ($patron): void {
                    $coincide
                        ->whereRaw($this->normalizada('cuenta.name').' like ?', [$patron])
                        ->orWhereRaw($this->normalizada('cuenta.usuario').' like ?', [$patron])
                        ->orWhere('auxiliar.codigo_universitario', 'like', $patron);
                })
                ->limit(self::MAXIMO_AL_BUSCAR);
        }

        $auxiliares = [];

        foreach ($consulta->get() as $fila) {
            $auxiliares[] = $this->dato($fila);
        }

        return $auxiliares;
    }

    public function porCodigo(string $codigoUniversitario, int $usuarioId): ?AuxiliarData
    {
        $fila = $this->base($usuarioId)
            ->where('auxiliar.codigo_universitario', $codigoUniversitario)
            ->orderBy('auxiliar.id')
            ->first();

        return $fila === null ? null : $this->dato($fila);
    }

    public function registrar(int $cuentaId, string $codigoUniversitario, int $registradoPor): void
    {
        DB::transaction(function () use ($cuentaId, $codigoUniversitario, $registradoPor): void {
            $auxiliar = Auxiliar::create([
                'user_id' => $cuentaId,
                'codigo_universitario' => $codigoUniversitario,
                'registrado_por' => $registradoPor,
            ]);

            $this->bitacora->registrar(
                $registradoPor,
                'auxiliar.registrar',
                'auxiliares',
                $auxiliar->id,
                "Auxiliar con código universitario {$codigoUniversitario} registrado.",
            );
        }, 3);
    }

    public function atiendeExamenPendiente(int $cuentaId, int $usuarioId): bool
    {
        return DB::table('users as cuenta')
            ->where('cuenta.id', $cuentaId)
            ->whereRaw(self::ATIENDE_PENDIENTE, [$usuarioId, $this->ahora()])
            ->exists();
    }

    public function quitar(int $cuentaId, int $usuarioId): bool
    {
        return DB::transaction(function () use ($cuentaId, $usuarioId): bool {
            $auxiliar = Auxiliar::where('user_id', $cuentaId)
                ->where('registrado_por', $usuarioId)
                ->lockForUpdate()
                ->first();

            if (! $auxiliar instanceof Auxiliar) {
                return false;
            }

            $auxiliar->registrado_por = null;
            $auxiliar->save();

            $this->bitacora->registrar(
                $usuarioId,
                'auxiliar.quitar',
                'auxiliares',
                $auxiliar->id,
                'Auxiliar'
                    .($auxiliar->codigo_universitario === null ? '' : " con código universitario {$auxiliar->codigo_universitario}")
                    .' quitado de la lista de quien lo registró.',
            );

            return true;
        }, 3);
    }

    /**
     * Cuentas de auxiliar: las de rol Auxiliar y las que tienen fila en
     * `auxiliares`.
     */
    private function base(int $usuarioId): Builder
    {
        return DB::table('users as cuenta')
            ->leftJoin('roles as rol', 'rol.id', '=', 'cuenta.role_id')
            ->leftJoin('auxiliares as auxiliar', 'auxiliar.user_id', '=', 'cuenta.id')
            ->where(function (Builder $esAuxiliar): void {
                $esAuxiliar
                    ->where('rol.name', self::ROL)
                    ->orWhereNotNull('auxiliar.id');
            })
            ->select(['cuenta.id', 'cuenta.name', 'cuenta.usuario'])
            ->selectRaw(self::ES_MIO.' as mio', $this->deMio($usuarioId));
    }

    /**
     * @return array{int, int, string}
     */
    private function deMio(int $usuarioId): array
    {
        return [$usuarioId, $usuarioId, $this->ahora()];
    }

    /**
     * El instante actual en la zona de la aplicacion, la misma con la que
     * el modulo decide si un examen ya se rindio.
     */
    private function ahora(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function dato(object $fila): AuxiliarData
    {
        $columnas = $this->columnas($fila);

        return new AuxiliarData(
            id: $this->entero($columnas['id'] ?? null),
            nombre: $this->cadena($columnas['name'] ?? null),
            usuario: $this->cadena($columnas['usuario'] ?? null),
            mio: $this->booleano($columnas['mio'] ?? null),
        );
    }

    private function normalizada(string $columna): string
    {
        return "translate(lower({$columna}), 'áéíóúñ', 'aeioun')";
    }

    private function sinTildes(string $texto): string
    {
        return strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            '%' => '\%', '_' => '\_',
        ]);
    }
}
