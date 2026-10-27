<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Quien controla cada aula de un examen y lo que el docente reacomoda con
 * el examen en marcha (HU-22).
 */
interface ControlPuertaGateway
{
    /**
     * Las aulas del examen con su personal asignado (a cargo y apoyo) y si
     * tienen codigo de acceso.
     *
     * @return list<array{aula_id: int, nombre: string, usa_codigo: bool, personal: list<array{usuario_id: int, nombre: string, rol: string}>}>
     */
    public function aulas(int $examenId): array;

    /**
     * Las cuentas que usaron la puerta de un aula del examen en los ultimos
     * minutos.
     *
     * @return list<array{aula_id: int, usuario_id: int, nombre: string, visto_en: string|null}>
     */
    public function presentes(int $examenId, int $minutos): array;

    /**
     * Una cuenta uso la puerta del aula: queda la hora.
     */
    public function marcarPresencia(int $aulaId, int $usuarioId): void;

    /**
     * Pasa a una persona del personal de un aula a otra del examen. Si
     * estaba a cargo, el aula queda a cargo de quien la mueve; en la de
     * destino entra como apoyo. Devuelve `OK`, `NO_ESTA` o `AULA_AJENA`.
     */
    public function trasladarPersona(int $examenId, int $usuarioId, int $deAulaId, int $aAulaId, int $autorId): string;

    /**
     * Pasa a los habilitados de un aula que todavia no entraron a otra del
     * examen. Devuelve cuantos, o nulo si alguna aula no es del examen.
     */
    public function trasladarEstudiantes(int $examenId, int $deAulaId, int $aAulaId, int $autorId): ?int;
}
