<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;

/**
 * Propone el usuario de una cuenta nueva: primer nombre de pila, punto,
 * apellido paterno; en minusculas y sin tildes. Es una propuesta: quien
 * crea la cuenta puede cambiarla.
 */
final readonly class ProponerUsuario implements ProponedorUsuario
{
    private const LARGO_MAXIMO = 60;

    private const SIN_TILDES = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    public function __construct(
        private CuentaUsuarioGateway $cuentas,
    ) {}

    /**
     * El nombre llega como en la oferta, «Paterno Materno Nombres»: el de
     * pila es la tercera palabra si hay mas de dos; si no, la ultima.
     */
    public function execute(string $nombreCompleto): string
    {
        $base = $this->base($nombreCompleto);
        $propuesta = $base;
        $sufijo = 2;

        while ($this->cuentas->usuarioRegistrado($propuesta)) {
            $numero = (string) $sufijo;
            $propuesta = substr($base, 0, self::LARGO_MAXIMO - strlen($numero)).$numero;
            $sufijo++;
        }

        return $propuesta;
    }

    public function proponer(string $nombreCompleto): string
    {
        return $this->execute($nombreCompleto);
    }

    private function base(string $nombreCompleto): string
    {
        $palabras = [];

        foreach (preg_split('/\s+/u', trim($nombreCompleto)) ?: [] as $palabra) {
            $limpia = $this->limpiar($palabra);

            if ($limpia !== '') {
                $palabras[] = $limpia;
            }
        }

        $cantidad = count($palabras);

        if ($cantidad === 0) {
            return 'usuario';
        }

        if ($cantidad === 1) {
            return substr($palabras[0], 0, self::LARGO_MAXIMO);
        }

        $paterno = $palabras[0];
        $pila = $cantidad > 2 ? $palabras[2] : $palabras[$cantidad - 1];

        return substr($pila.'.'.$paterno, 0, self::LARGO_MAXIMO);
    }

    private function limpiar(string $palabra): string
    {
        $minusculas = strtr(mb_strtolower($palabra), self::SIN_TILDES);

        return preg_replace('/[^a-z0-9]/', '', $minusculas) ?? '';
    }
}
