<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;

/**
 * Propone el usuario de una cuenta nueva: el primer nombre de pila y el
 * apellido paterno, separados por un espacio («jose soruco»), en minusculas
 * y sin tildes, aunque el nombre completo sea mas largo. Es una propuesta:
 * quien crea la cuenta puede cambiarla.
 */
final readonly class ProponerUsuario implements ProponedorUsuario
{
    /**
     * Las que van delante de un apellido compuesto, ya en minusculas.
     */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y'];

    public function __construct(
        private CuentaUsuarioGateway $cuentas,
    ) {}

    /**
     * Con un numero al final si ya hay otra cuenta igual («jose soruco2»).
     */
    public function execute(string $nombreCompleto): string
    {
        $base = self::formar($nombreCompleto);
        $propuesta = $base;
        $sufijo = 2;

        while ($this->cuentas->usuarioRegistrado($propuesta)) {
            $numero = (string) $sufijo;
            $propuesta = substr($base, 0, NombreDeUsuario::LARGO_MAXIMO - strlen($numero)).$numero;
            $sufijo++;
        }

        return $propuesta;
    }

    public function proponer(string $nombreCompleto): string
    {
        return $this->execute($nombreCompleto);
    }

    public function normalizar(string $usuario): string
    {
        return NombreDeUsuario::normalizar($usuario);
    }

    /**
     * El usuario que le toca a un nombre, sin mirar si ya existe. El nombre
     * llega como en la oferta, «Paterno Materno Nombres»: el de pila es el
     * primero despues de los dos apellidos; con solo dos grupos, el
     * segundo. Los apellidos compuestos («Del Castillo», «De La Barra»)
     * cuentan como un solo apellido y van juntos: «nelly delabarra».
     */
    public static function formar(string $nombreCompleto): string
    {
        $palabras = [];

        foreach (preg_split('/\s+/u', trim($nombreCompleto)) ?: [] as $palabra) {
            $limpia = NombreDeUsuario::limpiar($palabra);

            if ($limpia !== '') {
                $palabras[] = $limpia;
            }
        }

        if ($palabras === []) {
            return 'usuario';
        }

        // «Paterno Materno Nombres»: hasta dos apellidos y lo que sigue.
        $grupos = [];
        $resto = $palabras;

        while ($resto !== [] && count($grupos) < 2) {
            $grupos[] = self::apellido($resto);
        }

        // Sin nombres despues de dos apellidos («Mamani Diego»): el segundo
        // es el nombre. Con un solo grupo, queda ese.
        $pila = $resto[0] ?? $grupos[1] ?? '';

        return substr(trim($pila.' '.$grupos[0]), 0, NombreDeUsuario::LARGO_MAXIMO);
    }

    /**
     * Saca del principio de `$palabras` un apellido: las particulas que
     * tenga delante y la palabra que las sigue, juntas.
     *
     * @param  list<string>  $palabras
     */
    private static function apellido(array &$palabras): string
    {
        $apellido = '';

        while ($palabras !== []) {
            $palabra = array_shift($palabras);
            $apellido .= $palabra;

            // La particula sola al final («Jorge De») no se queda esperando.
            if (! in_array($palabra, self::PARTICULAS, true) || $palabras === []) {
                break;
            }
        }

        return $apellido;
    }
}
