<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Rules;

use App\Modules\Estudiantes\Domain\Exceptions\ArchivoRechazadoException;

/**
 * La estructura que debe tener el archivo de una lista de inscritos. Se
 * revisa el contenido, no la extension: si no trae filas, le faltan
 * columnas, las trae en otro orden o no parece una lista, se rechaza
 * entero.
 *
 * Con encabezado la revision es exacta. Sin encabezado solo se puede
 * inferir por el contenido: el codigo universitario (5 a 10 digitos) tiene
 * que estar en la primera columna.
 */
final class FormatoDeLista
{
    /**
     * Nombres con los que se reconoce cada columna en el encabezado, ya
     * normalizados (minusculas, sin tildes, con guion bajo).
     */
    private const NOMBRES = [
        'codigo_universitario' => ['codigo_universitario', 'codigo', 'codigo_sis', 'cod_sis', 'codigo_estudiante'],
        'documento_identidad' => ['documento_identidad', 'documento_de_identidad', 'documento', 'ci', 'carnet', 'carnet_de_identidad'],
        'nombres' => ['nombres', 'nombre'],
        'apellidos' => ['apellidos', 'apellido'],
        'codigo_asignatura' => ['codigo_asignatura', 'codigo_materia', 'asignatura', 'materia'],
        'grupo' => ['grupo', 'codigo_grupo'],
        'carrera' => ['carrera', 'codigo_carrera'],
    ];

    /**
     * Columnas en las que un numero de 5 a 10 digitos es normal y no
     * delata un codigo universitario fuera de lugar.
     */
    private const NUMERICAS = ['documento_identidad', 'codigo_asignatura', 'grupo', 'carrera'];

    /**
     * Las filas con datos, sin el encabezado, por su numero de fila en el
     * archivo (la primera es la 1): las filas vacias se saltan sin correr
     * la numeracion.
     *
     * @param  list<list<string>>  $filas  el archivo tal como se leyo
     * @param  list<string>  $obligatorias  columnas que espera la carga, en orden
     * @param  list<string>  $opcionales  columnas que pueden seguir a las obligatorias
     * @return array<int, list<string>>
     *
     * @throws ArchivoRechazadoException
     */
    public static function revisar(array $filas, array $obligatorias, array $opcionales = []): array
    {
        $conDatos = [];

        foreach ($filas as $indice => $fila) {
            $valores = array_map(trim(...), $fila);

            if (implode('', $valores) !== '') {
                $conDatos[$indice + 1] = $valores;
            }
        }

        if ($conDatos === []) {
            throw ArchivoRechazadoException::vacio();
        }

        $primera = array_key_first($conDatos);
        $encabezado = self::encabezado($conDatos[$primera]);

        if ($encabezado !== null) {
            unset($conDatos[$primera]);

            // Un encabezado del que no se reconoce ninguna columna se
            // salta y el archivo se revisa por su contenido.
            if ($encabezado !== []) {
                self::revisarEncabezado($encabezado, $obligatorias, $opcionales);

                if ($conDatos === []) {
                    throw ArchivoRechazadoException::vacio();
                }

                return $conDatos;
            }

            if ($conDatos === []) {
                throw ArchivoRechazadoException::vacio();
            }
        }

        self::revisarContenido(array_values($conDatos), $obligatorias, $opcionales);

        return $conDatos;
    }

    /**
     * Las columnas reconocidas en la fila (nombre => posicion) si es un
     * encabezado; null si es una fila de datos.
     *
     * @param  list<string>  $fila
     * @return array<string, int>|null
     */
    private static function encabezado(array $fila): ?array
    {
        $reconocidas = [];

        foreach ($fila as $posicion => $celda) {
            if (self::esCodigo($celda)) {
                return null;
            }

            $nombre = self::columna($celda);

            if ($nombre !== null && ! isset($reconocidas[$nombre])) {
                $reconocidas[$nombre] = $posicion;
            }
        }

        if ($reconocidas !== []) {
            return $reconocidas;
        }

        // Una primera celda que nombra el codigo es un encabezado aunque
        // sus nombres no sean los conocidos.
        return str_contains(self::normalizar($fila[0] ?? ''), 'codigo') ? [] : null;
    }

    /**
     * @param  array<string, int>  $encabezado
     * @param  list<string>  $obligatorias
     * @param  list<string>  $opcionales
     *
     * @throws ArchivoRechazadoException
     */
    private static function revisarEncabezado(array $encabezado, array $obligatorias, array $opcionales): void
    {
        $faltan = array_values(array_filter(
            $obligatorias,
            static fn (string $columna): bool => ! isset($encabezado[$columna]),
        ));

        if ($faltan !== []) {
            throw ArchivoRechazadoException::faltanColumnas($faltan, $obligatorias);
        }

        foreach ([...$obligatorias, ...$opcionales] as $posicion => $columna) {
            if (isset($encabezado[$columna]) && $encabezado[$columna] !== $posicion) {
                throw ArchivoRechazadoException::ordenDeColumnas($obligatorias);
            }
        }
    }

    /**
     * Sin encabezado: lo que se puede saber mirando las filas.
     *
     * @param  list<list<string>>  $filas
     * @param  list<string>  $obligatorias
     * @param  list<string>  $opcionales
     *
     * @throws ArchivoRechazadoException
     */
    private static function revisarContenido(array $filas, array $obligatorias, array $opcionales): void
    {
        $total = count($filas);
        $ancho = 0;
        $codigos = [];
        $soloTexto = 0;

        foreach ($filas as $fila) {
            foreach ($fila as $posicion => $celda) {
                if ($celda === '') {
                    continue;
                }

                $ancho = max($ancho, $posicion + 1);

                if (self::esCodigo($celda)) {
                    $codigos[$posicion] = ($codigos[$posicion] ?? 0) + 1;
                }
            }

            if (preg_match('/\d/', $fila[0] ?? '') !== 1) {
                $soloTexto++;
            }
        }

        // Nada en el archivo parece un codigo universitario.
        if ($codigos === []) {
            throw ArchivoRechazadoException::noCorresponde();
        }

        if ($ancho < count($obligatorias)) {
            throw ArchivoRechazadoException::faltanColumnas(
                array_slice($obligatorias, $ancho),
                $obligatorias,
            );
        }

        if (($codigos[0] ?? 0) * 2 >= $total) {
            return;
        }

        $esperadas = [...$obligatorias, ...$opcionales];

        foreach ($codigos as $posicion => $cuantos) {
            if ($posicion === 0 || $cuantos * 2 <= $total) {
                continue;
            }

            $numerica = in_array($esperadas[$posicion] ?? '', self::NUMERICAS, true);

            // El documento tambien son digitos: en su columna solo delata
            // un cambio de orden si la primera trae texto.
            if (! $numerica || $soloTexto * 2 > $total) {
                throw ArchivoRechazadoException::ordenDeColumnas($obligatorias);
            }
        }
    }

    private static function columna(string $celda): ?string
    {
        $celda = self::normalizar($celda);

        if ($celda === '') {
            return null;
        }

        foreach (self::NOMBRES as $columna => $nombres) {
            if (in_array($celda, $nombres, true)) {
                return $columna;
            }
        }

        return null;
    }

    private static function esCodigo(string $celda): bool
    {
        return preg_match('/^\d{5,10}$/', $celda) === 1;
    }

    /**
     * «Código Universitario», «codigo-universitario» y
     * «CODIGO_UNIVERSITARIO» son el mismo nombre de columna.
     */
    private static function normalizar(string $celda): string
    {
        $celda = strtr(mb_strtolower(trim($celda)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $celda), '_');
    }
}
