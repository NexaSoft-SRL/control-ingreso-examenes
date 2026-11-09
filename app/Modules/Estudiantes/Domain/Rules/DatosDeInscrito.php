<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Rules;

use App\Modules\Estudiantes\Domain\Enums\TipoConflicto;

/**
 * Las reglas de los datos de un inscrito, las mismas para una fila del
 * archivo de carga que para el estudiante que el docente agrega suelto:
 * el formato de cada dato y cuando difiere del que ya esta en el padron.
 */
final class DatosDeInscrito
{
    /**
     * El codigo universitario: de 5 a 10 digitos.
     */
    public const PATRON_CODIGO = '/^\d{5,10}$/';

    /**
     * El C.I.: digitos con, opcionalmente, su complemento o extension
     * («5003748», «5003748-1A», «5003748 LP»).
     */
    public const PATRON_DOCUMENTO = '/^\d{4,10}([\s-][0-9A-Za-z]{1,3}){0,2}$/';

    /**
     * Nombres y apellidos: palabras de letras separadas por un espacio, un
     * punto, un apostrofo o un guion, sin dos signos seguidos y con al
     * menos dos letras.
     */
    public const PATRON_NOMBRE = "/^(?=(?:.*\\p{L}){2})\\p{L}+(?:(?:\\.\\s*|\\s+|['-])\\p{L}+)*\\.?$/u";

    public const MAXIMO_DOCUMENTO = 30;

    public const MAXIMO_NOMBRE = 100;

    public static function codigoValido(string $codigo): bool
    {
        return preg_match(self::PATRON_CODIGO, $codigo) === 1;
    }

    public static function documentoValido(string $documento): bool
    {
        return mb_strlen($documento) <= self::MAXIMO_DOCUMENTO
            && preg_match(self::PATRON_DOCUMENTO, trim($documento)) === 1;
    }

    public static function nombreValido(string $nombre): bool
    {
        return preg_match(self::PATRON_NOMBRE, trim($nombre)) === 1;
    }

    /**
     * La clave con que se busca un texto sin importar mayusculas, tildes
     * ni espacios repetidos (el nombre de una carrera, por ejemplo).
     */
    public static function clave(string $valor): string
    {
        return self::normalizar($valor);
    }

    /**
     * En que difieren los datos traidos de los del estudiante guardado;
     * null si coinciden. Manda el documento: si difiere, el conflicto es
     * de documento.
     */
    public static function diferencia(
        ?string $documentoGuardado,
        string $nombresGuardados,
        string $apellidosGuardados,
        string $documento,
        string $nombres,
        string $apellidos,
    ): ?TipoConflicto {
        if (self::documento((string) $documentoGuardado) !== self::documento($documento)) {
            return TipoConflicto::DocumentoDistinto;
        }

        if (
            self::normalizar($nombresGuardados) !== self::normalizar($nombres)
            || self::normalizar($apellidosGuardados) !== self::normalizar($apellidos)
        ) {
            return TipoConflicto::NombreDistinto;
        }

        return null;
    }

    private static function documento(string $valor): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $valor));
    }

    /**
     * Mayusculas, sin tildes y con espacios simples: «José  Luis» y
     * «JOSE LUIS» son el mismo nombre.
     */
    private static function normalizar(string $valor): string
    {
        $valor = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $valor)));

        return strtr($valor, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        ]);
    }
}
