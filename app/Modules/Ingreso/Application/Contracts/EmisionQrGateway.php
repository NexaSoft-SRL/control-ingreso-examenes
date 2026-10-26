<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\CredencialData;
use App\Modules\Ingreso\Application\DTOs\ExamenQrData;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;

/**
 * Los codigos QR emitidos (`codigos_qr`) y los habilitados a los que les
 * corresponden. El contenido de cada codigo lo genera `CodigoQrGateway`
 * (el formato que lee la puerta); este contrato solo lo guarda y lo lee.
 */
interface EmisionQrGateway
{
    /**
     * Nulo si el examen no existe.
     */
    public function examen(int $examenId): ?ExamenQrData;

    /**
     * Cuantos habilitados tiene el examen, cuantos con codigo, cuantos con
     * el codigo ya recibido por correo, cuantos con el ultimo envio fallido
     * y cuantos siguen sin aula.
     *
     * @return array{habilitados: int, emitidos: int, enviados: int, fallidos: int, sin_aula: int}
     */
    public function resumen(int $examenId): array;

    /**
     * Los habilitados que todavia no tienen codigo, con su codigo
     * universitario.
     *
     * @return list<array{estudiante_id: int, codigo: string}>
     */
    public function pendientesDeEmitir(int $examenId): array;

    /**
     * Guarda los codigos (estudiante => contenido) sin tocar los que ya
     * existen y asienta la bitacora. Devuelve cuantos guardo.
     *
     * @param  array<int, string>  $contenidos
     */
    public function guardarLote(int $examenId, array $contenidos, int $usuarioId): int;

    /**
     * Una pagina de los habilitados del examen, por apellidos y nombres,
     * con el estado de su codigo.
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
     * Las credenciales con codigo emitido que pasan el filtro, por
     * apellidos. Con `$soloSinEnviar`, solo las que aun no se recibieron.
     * Con `$desde` (el instante en que empezo una tanda de envios), sin las
     * que ya tienen un envio registrado desde entonces.
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
     * Cuantas credenciales emitidas del filtro siguen sin recibirse.
     */
    public function sinEnviar(int $examenId, FiltroCodigosData $filtro): int;

    /**
     * Cuantas credenciales emitidas del filtro no tienen un envio
     * registrado desde `$desde`.
     */
    public function porReenviar(int $examenId, FiltroCodigosData $filtro, string $desde): int;
}
