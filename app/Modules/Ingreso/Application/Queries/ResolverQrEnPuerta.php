<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Exceptions\CodigoQrNoValido;
use InvalidArgumentException;

final readonly class ResolverQrEnPuerta
{
    public function __construct(
        private ExamenGateway $examenes,
        private AlcancePuntoControl $alcance,
        private VentanaPuntoControl $ventana,
        private CodigoQrGateway $qr,
    ) {}

    /**
     * @param  list<int>  $candidatos
     * @return array{int, string}
     */
    public function ejecutar(
        int $usuarioId,
        int $aulaId,
        array $candidatos,
        string $contenido,
    ): array {
        if (
            $usuarioId <= 0
            || $aulaId <= 0
            || strlen($contenido) > 2048
        ) {
            throw new InvalidArgumentException('Código QR inválido.');
        }

        // El alcance y la ventana se comprueban antes que el contenido: un
        // QR ajeno solo es un intento en la puerta de quien la atiende.
        $conFormato = str_starts_with($contenido, 'SCIQR1:');

        $tieneAlcance = false;
        $tieneVentana = false;
        $coincidencias = [];

        foreach (array_unique($candidatos) as $examenId) {
            if ($examenId <= 0) {
                throw new InvalidArgumentException(
                    'Identificador de examen inválido.'
                );
            }

            $examen = $this->examenes->actual($examenId);

            if (
                $examen === null
                || ! in_array($aulaId, $examen->aulas, true)
                || ! $this->alcance->puedeOperar(
                    $usuarioId,
                    $examenId,
                    $aulaId,
                )
            ) {
                continue;
            }

            $tieneAlcance = true;

            if (! $this->ventana->estaAbierta($examenId)) {
                continue;
            }

            $tieneVentana = true;

            if (! $conFormato) {
                continue;
            }

            try {
                $codigo = $this->qr->resolver(
                    $contenido,
                    $examenId,
                );

                $coincidencias[] = [$examenId, $codigo];
            } catch (InvalidArgumentException) {
                // QR válido para otro examen o contenido inválido.
                // No se concede acceso ni se revela ese examen.
                continue;
            }
        }

        if (! $tieneAlcance) {
            throw new AccesoPuntoDenegado(
                'Sin asignación en esta aula.'
            );
        }

        if (! $tieneVentana) {
            throw new InvalidArgumentException(
                'Sin examen a esta hora.'
            );
        }

        if (count($coincidencias) !== 1) {
            throw new CodigoQrNoValido(
                'Código QR no válido.'
            );
        }

        return $coincidencias[0];
    }
}
