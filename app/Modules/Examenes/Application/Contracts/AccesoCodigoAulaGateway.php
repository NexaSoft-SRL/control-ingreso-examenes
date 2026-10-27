<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

/**
 * Los accesos con codigo de aula (HU-18). Nacen PENDIENTES y solo abren la
 * puerta cuando el docente los AUTORIZA en persona; el docente puede
 * RECHAZARLOS, CORTARLOS o pasarlos a otra aula del examen.
 */
interface AccesoCodigoAulaGateway
{
    /**
     * Abre un acceso pendiente con el codigo de un aula cuyo examen esta en
     * su ventana. Nulo si el codigo no abre nada ahora.
     *
     * @return array{token: string, examen_id: int, aula_id: int, aula_nombre: string, nombre_colaborador: string, expira_en: string, estado: string}|null
     */
    public function ingresar(string $codigo, string $nombreColaborador): ?array;

    /**
     * Cuando abre o cerro la puerta de un codigo que existe: la proxima
     * ventana o, si no hay, la ultima. Nulo si el codigo no es de ningun
     * aula.
     *
     * @return array{abre: string, cierra: string, cerrada: bool}|null
     */
    public function ventanaDelCodigo(string $codigo): ?array;

    public function salir(string $token): void;

    /**
     * Como va el acceso del token: `ok` (pendiente o autorizado, con sus
     * datos), `rechazado`, `cortado`, `finalizado` o `no_valido`.
     *
     * @return array{estado: 'ok'|'no_valido'|'finalizado'|'rechazado'|'cortado', data?: array{token: string, examen_id: int, aula_id: int, aula_nombre: string, nombre_colaborador: string, expira_en: string, estado: string}}
     */
    public function sesion(string $token): array;

    /**
     * Los accesos vigentes (pendientes y autorizados) del examen.
     *
     * @return list<array{id: int, nombre: string, estado: string, aula_id: int, aula: string, pedido_en: string|null, visto_en: string|null}>
     */
    public function delExamen(int $examenId): array;

    /**
     * Autoriza, rechaza, corta o traslada un acceso del examen. Devuelve el
     * estado que quedo, `AULA_AJENA` si el aula no es del examen, o nulo si
     * el acceso no es del examen.
     */
    public function resolver(int $accesoId, int $examenId, string $estado, int $usuarioId, ?int $aulaId = null): ?string;
}
