<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\RespaldoGateway;
use App\Modules\Administracion\Application\DTOs\PaginaRespaldosData;

final readonly class ListarRespaldos
{
    /**
     * Cuando corre la tarea programada (`routes/console.php`).
     */
    public const PROGRAMACION = 'Diario, 03:00';

    /**
     * @var list<string>
     */
    public const FILTROS = ['todos', 'programados', 'puntuales', 'fallidos'];

    public function __construct(
        private RespaldoGateway $respaldos,
    ) {}

    public function execute(?string $filtro, int $pagina, int $porPagina): PaginaRespaldosData
    {
        return $this->respaldos->listar(
            $filtro === 'todos' ? null : $filtro,
            max(1, $pagina),
            min(100, max(1, $porPagina)),
        );
    }
}
