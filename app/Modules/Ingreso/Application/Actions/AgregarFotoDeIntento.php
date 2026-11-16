<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;

/**
 * Quien controla la puerta fotografia a quien intenta entrar sin
 * corresponderle (un intento rechazado, un ingreso repetido o una
 * suplantacion) y la foto queda como evidencia de esa incidencia, hasta tres.
 */
final readonly class AgregarFotoDeIntento
{
    public const LADO = 1280;

    public const MAXIMO = 3;

    private const TIPOS = ['INTENTO_NO_AUTORIZADO', 'INGRESO_REPETIDO', 'SUPLANTACION'];

    public function __construct(
        private PuertaGateway $puerta,
        private IncidenciaGateway $incidencias,
        private AlcancePuesto $alcance,
        private AlmacenImagenes $imagenes,
    ) {}

    /**
     * @return int cuantas fotos tiene ahora la incidencia
     *
     * @throws FueraDeAlcanceException
     * @throws DatoNoValidoException
     */
    public function execute(AulaData $aula, OperadorData $operador, int $incidenciaId, string $imagen): int
    {
        if (! $this->alcance->atiendeAula($operador, $aula->id, $this->puerta->examenesDelDia($aula->id))) {
            throw FueraDeAlcanceException::aula();
        }

        $incidencia = $this->incidencias->ubicacion($incidenciaId);

        // Solo las de esta puerta y de estos tipos: la foto de un intruso no
        // se cuelga de cualquier incidencia.
        if (
            $incidencia === null
            || $incidencia['aula_id'] !== $aula->id
            || ! in_array($incidencia['tipo'], self::TIPOS, true)
        ) {
            throw new DatoNoValidoException('incidencia', 'La incidencia no es de esta puerta.');
        }

        $hay = $this->incidencias->cuantasEvidencias($incidenciaId);

        if ($hay >= self::MAXIMO) {
            throw new DatoNoValidoException('foto', sprintf('Ya tiene %d fotos', self::MAXIMO));
        }

        $ruta = $this->imagenes->guardar('incidencias', bin2hex(random_bytes(16)), $imagen, self::LADO);

        if ($ruta === null) {
            throw new DatoNoValidoException('foto', 'La imagen no se pudo leer.');
        }

        $this->incidencias->agregarEvidencia($incidenciaId, $ruta, $operador->usuarioId);

        return $hay + 1;
    }
}
