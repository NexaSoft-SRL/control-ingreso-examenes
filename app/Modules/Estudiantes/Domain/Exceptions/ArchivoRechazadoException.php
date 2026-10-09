<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El archivo subido se rechaza entero, antes de guardar nada: no es una
 * lista de inscritos, no trae filas, le faltan columnas o las trae en otro
 * orden.
 */
final class ArchivoRechazadoException extends RuntimeException
{
    public const NO_CORRESPONDE = 'ARCHIVO_NO_CORRESPONDE';

    public const VACIO = 'ARCHIVO_VACIO';

    public const FALTAN_COLUMNAS = 'FALTAN_COLUMNAS';

    public const ORDEN_DE_COLUMNAS = 'ORDEN_DE_COLUMNAS';

    /**
     * @param  list<string>  $columnas  las que faltan (solo en `FALTAN_COLUMNAS`)
     * @param  list<string>  $ordenEsperado  las columnas que espera la carga, en orden
     */
    private function __construct(
        string $mensaje,
        public readonly string $codigo,
        public readonly array $columnas = [],
        public readonly array $ordenEsperado = [],
    ) {
        parent::__construct($mensaje);
    }

    public static function noCorresponde(): self
    {
        return new self('El archivo no es una lista de inscritos.', self::NO_CORRESPONDE);
    }

    public static function vacio(): self
    {
        return new self('El archivo no tiene filas.', self::VACIO);
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<string>  $ordenEsperado
     */
    public static function faltanColumnas(array $columnas, array $ordenEsperado): self
    {
        return new self(
            'Faltan columnas: '.implode(', ', $columnas).'.',
            self::FALTAN_COLUMNAS,
            $columnas,
            $ordenEsperado,
        );
    }

    /**
     * @param  list<string>  $ordenEsperado
     */
    public static function ordenDeColumnas(array $ordenEsperado): self
    {
        return new self(
            'Las columnas están en otro orden. Orden esperado: '.implode(', ', $ordenEsperado).'.',
            self::ORDEN_DE_COLUMNAS,
            [],
            $ordenEsperado,
        );
    }

    /**
     * La misma negativa, con las columnas que espera la carga.
     *
     * @param  list<string>  $ordenEsperado
     */
    public function conOrdenEsperado(array $ordenEsperado): self
    {
        return new self($this->getMessage(), $this->codigo, $this->columnas, $ordenEsperado);
    }
}
