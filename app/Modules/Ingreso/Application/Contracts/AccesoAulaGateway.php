<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\AccesoAulaData;

interface AccesoAulaGateway
{
    /**
     * Pide un acceso con el codigo de un aula: queda PENDIENTE hasta que
     * el docente lo autorice. Nulo si el codigo no existe o su examen no
     * esta abierto a esta hora.
     */
    public function abrir(string $codigo, string $nombre, ?string $ip): ?AccesoAulaData;

    /**
     * Cuando abre y cierra la puerta del examen de ese codigo, fuera de
     * hora: la proxima ventana si hay una por venir, si no la ultima.
     * Nulo si el codigo no es de ningun aula. Fechas `AAAA-MM-DD HH:MM`;
     * `cerrada` si esa ventana ya paso.
     *
     * @return array{abre: string, cierra: string, cerrada: bool}|null
     */
    public function ventanaDelCodigo(string $codigo): ?array;

    /**
     * El acceso, si esta AUTORIZADO y el examen sigue abierto.
     */
    public function vigente(int $accesoId): ?AccesoAulaData;

    /**
     * El acceso de la sesion en cualquier estado, mientras el examen
     * sigue abierto: lo consulta quien espera la autorizacion.
     */
    public function deSesion(int $accesoId): ?AccesoAulaData;

    /**
     * Anota que el acceso se esta usando.
     */
    public function tocar(int $accesoId): void;

    /**
     * Anota que una cuenta esta en la puerta de esa aula.
     */
    public function marcarPresencia(int $aulaId, int $usuarioId): void;

    /**
     * Los accesos pendientes y autorizados de las aulas del examen.
     *
     * @return list<array{id: int, nombre: string, estado: string, aula_id: int, aula: string, pedido_en: string|null, visto_en: string|null}>
     */
    public function delExamen(int $examenId): array;

    /**
     * Las cuentas que usaron la puerta de un aula del examen en los
     * ultimos `$minutos`.
     *
     * @return list<array{aula_id: int, usuario_id: int, nombre: string, visto_en: string|null}>
     */
    public function presentes(int $examenId, int $minutos): array;

    /**
     * Autoriza, rechaza o corta un acceso del examen (`$estado`), y con
     * `$aulaId` lo pasa a otra aula del examen. Devuelve el estado que
     * quedo, `AULA_AJENA` si el aula no es del examen o nulo si el acceso
     * no es del examen.
     */
    public function resolver(int $accesoId, int $examenId, string $estado, int $usuarioId, ?int $aulaId = null): ?string;
}
