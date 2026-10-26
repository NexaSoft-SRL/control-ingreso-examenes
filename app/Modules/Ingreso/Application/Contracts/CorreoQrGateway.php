<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\AvisoData;
use App\Modules\Ingreso\Application\DTOs\CredencialData;

/**
 * Los correos de los codigos QR y de los avisos: cada uno deja su fila en `envios_correo`
 * y la bitacora registra el lote.
 */
interface CorreoQrGateway
{
    /**
     * Cuantos correos se envian por peticion. Nulo = sin tope (cola real).
     * Con la cola sincronica el envio ocurre dentro de la peticion y se
     * limita a tandas.
     */
    public function tamanoDeLote(): ?int;

    /**
     * El instante del servidor en ISO 8601: marca el inicio de una tanda.
     */
    public function ahora(): string;

    /**
     * Encola un correo con el codigo QR por credencial, al correo
     * institucional del estudiante. Devuelve cuantos encolo.
     *
     * @param  list<CredencialData>  $credenciales
     */
    public function encolar(int $examenId, array $credenciales, int $usuarioId): int;

    /**
     * Encola un aviso por estudiante: fecha, hora, aula, normas y
     * recomendaciones. Devuelve cuantos encolo.
     *
     * @param  list<CredencialData>  $destinatarios
     */
    public function avisar(int $examenId, array $destinatarios, AvisoData $aviso, int $usuarioId): int;
}
