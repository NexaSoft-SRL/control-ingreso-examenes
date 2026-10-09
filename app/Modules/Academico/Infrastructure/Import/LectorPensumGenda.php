<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Import;

use App\Modules\Academico\Application\Contracts\FuentePensumGateway;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Academico\Domain\Rules\NombresDeOferta;

/**
 * Lee `pensum.json`: la lista de facultades de la universidad con sus
 * carreras. Solo se usa para saber el codigo de una carrera por su nombre.
 */
final class LectorPensumGenda implements FuentePensumGateway
{
    /**
     * @return array<string, string>
     */
    public function carrerasDe(string $codigoFacultad): array
    {
        try {
            $datos = LectorJson::leer(LectorJson::rutaGenda('pensum.json'));
        } catch (FuenteNoDisponibleException) {
            // Sin pensum la importacion sigue: la carrera queda sin enlazar.
            return [];
        }

        $carreras = [];

        foreach ($datos as $facultad) {
            if (! is_array($facultad) || (string) ($this->escalar($facultad['code'] ?? null)) !== $codigoFacultad) {
                continue;
            }

            $lista = $facultad['careers'] ?? null;

            foreach (is_array($lista) ? $lista : [] as $carrera) {
                if (! is_array($carrera)) {
                    continue;
                }

                $codigo = $this->escalar($carrera['code'] ?? null);
                $nombre = $this->escalar($carrera['name'] ?? null);

                if ($codigo === null || $nombre === null) {
                    continue;
                }

                // Si un nombre se repite (dos planes), vale el primero.
                $carreras[NombresDeOferta::normalizar($nombre)] ??= $codigo;
            }
        }

        return $carreras;
    }

    private function escalar(mixed $valor): ?string
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
