<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un reporte ya resuelto y listo para mostrarse o exportarse: el titulo,
 * los filtros con que se lo pidio, los encabezados y las filas, todo en
 * texto. El exportador (HU-34) no sabe de que reporte se trata.
 */
final readonly class TablaReporte
{
    public const MAX_FILAS_EXPORTACION = 1000;

    /**
     * @param  list<string>  $filtros
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     * @param  list<string>  $totales
     */
    public function __construct(
        public string $clave,
        public string $titulo,
        public array $filtros,
        public array $encabezados,
        public array $filas,
        public array $totales,
        public string $emitidoEn,
    ) {}
}
