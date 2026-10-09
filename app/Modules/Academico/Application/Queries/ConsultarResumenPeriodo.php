<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\Contracts\PeriodoGateway;

/**
 * La cabecera de la pantalla Periodo: periodo principal, ventana de
 * examenes, pendientes y oferta por facultad.
 */
final readonly class ConsultarResumenPeriodo
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
        private PeriodoGateway $periodos,
        private ResolverPeriodos $resolverPeriodos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $hoy = $this->oferta->hoy();
        $principal = $this->periodos->principal();
        $periodoIds = $this->resolverPeriodos->execute(null);
        $pendientes = $this->oferta->pendientes($periodoIds);

        return [
            'hoy' => $hoy,
            'periodo' => $principal === null
                ? null
                : ['codigo' => $principal->codigo, 'estado' => 'Vigente'],
            'ventana' => $principal === null
                ? null
                : $this->ventana($principal->ventanas, $hoy),
            'pendientes' => [
                'docentes_sin_cuenta' => [
                    'valor' => $pendientes['docentes_sin_cuenta'],
                    'de' => $pendientes['docentes'],
                ],
                'grupos_sin_lista' => [
                    'valor' => $pendientes['grupos_sin_lista'],
                    'de' => $pendientes['grupos'],
                ],
            ],
            'facultades' => $this->oferta->ofertaPorFacultad($periodoIds),
        ];
    }

    /**
     * La ventana de examenes en curso o, si no hay ninguna abierta, la
     * proxima.
     *
     * @param  array<string, mixed>  $ventanas
     * @return array{nombre: string, desde: string, hasta: string}|null
     */
    private function ventana(array $ventanas, string $hoy): ?array
    {
        $proxima = null;

        foreach ($ventanas as $clave => $rango) {
            if (! is_array($rango) || ! is_string($rango[0] ?? null) || ! is_string($rango[1] ?? null)) {
                continue;
            }

            $ventana = [
                'nombre' => $this->nombre((string) $clave),
                'desde' => $rango[0],
                'hasta' => $rango[1],
            ];

            if ($ventana['desde'] <= $hoy && $hoy <= $ventana['hasta']) {
                return $ventana;
            }

            if ($ventana['desde'] > $hoy && ($proxima === null || $ventana['desde'] < $proxima['desde'])) {
                $proxima = $ventana;
            }
        }

        return $proxima;
    }

    private function nombre(string $clave): string
    {
        $texto = str_replace('_', ' ', $clave);

        return mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }
}
