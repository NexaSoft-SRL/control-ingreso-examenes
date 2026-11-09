<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\AvisoData;
use App\Modules\Ingreso\Application\DTOs\CredencialData;

interface CorreoQrGateway
{
    /**
     * Cuantos correos se encolan por peticion. Nulo = sin tope. Cuando la
     * cola es sincronica el envio ocurre dentro de la peticion y se limita.
     */
    public function tamanoDeLote(): ?int;

    /**
     * Encola un correo por credencial, deja su fila en `envios_correo` y
     * asienta la bitacora. Devuelve cuantos encolo.
     *
     * @param  list<CredencialData>  $credenciales
     */
    public function encolar(int $examenId, array $credenciales, ?int $usuarioId): int;

    /**
     * Encola un aviso por estudiante (fecha, aula, normas y
     * recomendaciones), deja su fila en `envios_correo` con el tipo
     * `AVISO` y asienta la bitacora. Devuelve cuantos encolo.
     *
     * @param  list<CredencialData>  $destinatarios
     */
    public function avisar(int $examenId, array $destinatarios, AvisoData $aviso, ?int $usuarioId): int;
}
