<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\DTOs\BitacoraOperacionData;
use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;
use App\Modules\Administracion\Application\DTOs\PaginaBitacoraData;
use App\Modules\Administracion\Domain\ValueObjects\InstanteLocal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaBitacoraGateway implements ConsultaBitacoraGateway
{
    /**
     * Para buscar sin distinguir mayusculas ni tildes.
     */
    private const CON_TILDE = 'áéíóúüñ';

    private const SIN_TILDE = 'aeiouun';

    public function consultar(ConsultarBitacoraData $filtros): PaginaBitacoraData
    {
        $consulta = DB::table('bitacora_operaciones as bitacora')
            ->leftJoin('users as usuario', 'usuario.id', '=', 'bitacora.usuario_id')
            ->leftJoin('roles as rol', 'rol.id', '=', 'usuario.role_id');

        if ($filtros->buscar !== null && trim($filtros->buscar) !== '') {
            $patron = '%'.$this->normalizar($filtros->buscar).'%';

            $consulta->where(function (Builder $donde) use ($patron): void {
                foreach (['usuario.name', 'bitacora.actor', 'bitacora.descripcion'] as $columna) {
                    $donde->orWhereRaw(
                        sprintf(
                            "translate(lower(coalesce(%s, '')), '%s', '%s') like ?",
                            $columna,
                            self::CON_TILDE,
                            self::SIN_TILDE,
                        ),
                        [$patron],
                    );
                }
            });
        }

        if ($filtros->rol !== null) {
            $consulta->whereRaw('coalesce(bitacora.rol, rol.name) = ?', [$filtros->rol]);
        }

        if ($filtros->operacion !== null) {
            $consulta->where('bitacora.operacion', $filtros->operacion);
        }

        // Los dias del filtro y la columna estan en la zona de la aplicacion.
        if ($filtros->desde !== null) {
            $consulta->where(
                'bitacora.fecha_operacion',
                '>=',
                InstanteLocal::inicioDelDia($filtros->desde),
            );
        }

        if ($filtros->hasta !== null) {
            // Inclusivo: «hasta el 18» incluye todo el 18.
            $consulta->where(
                'bitacora.fecha_operacion',
                '<',
                InstanteLocal::inicioDelDia($filtros->hasta, 1),
            );
        }

        $total = $consulta->count();

        $filas = $consulta
            ->orderByDesc('bitacora.fecha_operacion')
            ->orderByDesc('bitacora.id')
            ->forPage($filtros->pagina, $filtros->porPagina)
            ->select([
                'bitacora.id',
                DB::raw('coalesce(usuario.name, bitacora.actor) as usuario'),
                // El rol guardado al operar; los asientos viejos, el actual.
                DB::raw('coalesce(bitacora.rol, rol.name) as rol'),
                'bitacora.operacion',
                'bitacora.descripcion',
                'bitacora.fecha_operacion',
            ])
            ->get();

        $asientos = [];

        foreach ($filas as $fila) {
            $datos = get_object_vars($fila);
            $fecha = $this->texto($datos['fecha_operacion'] ?? null);

            $asientos[] = new BitacoraOperacionData(
                id: $this->entero($datos['id'] ?? null),
                usuario: $this->texto($datos['usuario'] ?? null),
                rol: $this->texto($datos['rol'] ?? null),
                operacion: $this->texto($datos['operacion'] ?? null) ?? '',
                descripcion: $this->texto($datos['descripcion'] ?? null),
                fecha: InstanteLocal::de($fecha ?? 'now')->iso(),
            );
        }

        return new PaginaBitacoraData(
            filas: $asientos,
            total: $total,
            pagina: $filtros->pagina,
            porPagina: $filtros->porPagina,
            operaciones: $this->operaciones(),
            fechaMinima: $this->fechaMinima(),
        );
    }

    /**
     * Las claves que existen, para el desplegable: lo resuelve el indice
     * `idx_bitacora_operacion`.
     *
     * @return list<string>
     */
    private function operaciones(): array
    {
        $claves = [];

        foreach (DB::table('bitacora_operaciones')->distinct()->orderBy('operacion')->pluck('operacion') as $clave) {
            if (is_string($clave)) {
                $claves[] = $clave;
            }
        }

        return $claves;
    }

    private function fechaMinima(): ?string
    {
        $minima = $this->texto(DB::table('bitacora_operaciones')->min('fecha_operacion'));

        return $minima === null ? null : InstanteLocal::de($minima)->fecha();
    }

    private function normalizar(string $texto): string
    {
        $texto = strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return addcslashes($texto, '%_\\');
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }
}
