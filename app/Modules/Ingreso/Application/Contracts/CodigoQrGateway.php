<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\CredencialData;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;

interface CodigoQrGateway
{
    /**
     * Cuantos habilitados tiene el examen, cuantos con codigo, cuantos
     * con el codigo ya enviado, cuantos con el ultimo envio fallido y
     * cuantos habilitados siguen sin aula.
     *
     * @return array{habilitados: int, emitidos: int, enviados: int, fallidos: int, sin_aula: int}
     */
    public function resumen(int $examenId): array;

    /**
     * Emite un codigo a cada habilitado que no lo tenga. Devuelve cuantos
     * emitio.
     */
    public function emitirLote(int $examenId, ?int $usuarioId): int;

    /**
     * @return array{examen_id: int, estudiante_id: int}|null
     */
    public function resolver(string $token): ?array;

    /**
     * Una pagina de los habilitados del examen, por apellidos y nombres.
     *
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function listar(int $examenId, FiltroCodigosData $filtro): array;

    /**
     * Los grupos y las aulas del examen, para los filtros de la lista.
     *
     * @return array{grupos: list<array{id: int, codigo: string}>, aulas: list<array{aula_id: int, nombre: string}>}
     */
    public function opciones(int $examenId): array;

    /**
     * Nulo si el estudiante no esta habilitado en el examen.
     */
    public function credencial(int $examenId, int $estudianteId): ?CredencialData;

    /**
     * Las credenciales con codigo emitido que pasan el filtro. Con
     * `$soloSinEnviar`, solo las que aun no se enviaron. Con `$desde`
     * (el instante en que empezo la tanda), sin las que ya tienen un envio
     * registrado desde entonces.
     *
     * @return list<CredencialData>
     */
    public function emitidas(
        int $examenId,
        FiltroCodigosData $filtro,
        ?int $limite = null,
        bool $soloSinEnviar = false,
        ?string $desde = null,
    ): array;

    /**
     * Cuantas credenciales emitidas del filtro siguen sin enviarse.
     */
    public function sinEnviar(int $examenId, FiltroCodigosData $filtro): int;

    /**
     * Cuantas credenciales emitidas del filtro no tienen un envio
     * registrado desde `$desde`.
     */
    public function porReenviar(int $examenId, FiltroCodigosData $filtro, string $desde): int;

    /**
     * Los habilitados del filtro, tengan o no codigo, para avisarles. Con
     * `$desde`, sin los que ya tienen un aviso registrado desde entonces.
     *
     * @return list<CredencialData>
     */
    public function paraAvisar(
        int $examenId,
        FiltroCodigosData $filtro,
        ?int $limite = null,
        ?string $desde = null,
    ): array;

    /**
     * Cuantos habilitados del filtro no tienen un aviso registrado desde
     * `$desde`.
     */
    public function porAvisar(int $examenId, FiltroCodigosData $filtro, string $desde): int;
}
