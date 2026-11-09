<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;

/**
 * La lista de incidencias comunes de cada cuenta: los tipos del sistema con
 * la gravedad que prefiere (o la del tipo, si no eligio otra) y las
 * incidencias propias que fue guardando, cada una con su gravedad.
 *
 * @phpstan-type IncidenciaComun array{id: int|null, tipo: string, nombre: string, gravedad: string, propia: bool, predeterminada: string|null}
 */
interface IncidenciasComunesGateway
{
    /**
     * Primero los tipos del sistema que se pueden reportar, despues las
     * propias por nombre. `predeterminada` es la gravedad del tipo (null en
     * las propias): si difiere de `gravedad`, la cuenta la cambio.
     *
     * @return list<IncidenciaComun>
     */
    public function deUsuario(int $usuarioId): array;

    /**
     * De quien es la lista que se ofrece al registrar en un examen: de quien
     * registra si es docente del examen; si no (un auxiliar, un codigo de
     * aula), del docente que registro el examen.
     */
    public function duenoParaExamen(int $examenId, ?int $usuarioId): ?int;

    /**
     * La gravedad con que se propone un tipo del sistema para esa cuenta.
     */
    public function gravedadDeTipo(?int $usuarioId, TipoIncidencia $tipo): Gravedad;

    /**
     * La gravedad de una incidencia propia por su nombre, o null si no esta.
     */
    public function gravedadDeNombre(?int $usuarioId, string $nombre): ?Gravedad;

    /**
     * Guarda la gravedad preferida de un tipo del sistema (si es la del
     * tipo, la preferencia se quita).
     */
    public function guardarTipo(int $usuarioId, TipoIncidencia $tipo, Gravedad $gravedad): void;

    /**
     * Agrega una incidencia propia o cambia su gravedad, por nombre (sin
     * distinguir mayusculas).
     */
    public function guardarPropia(int $usuarioId, string $nombre, Gravedad $gravedad): void;

    /**
     * Quita una incidencia propia. Falso si no es de esa cuenta.
     */
    public function quitarPropia(int $usuarioId, int $id): bool;
}
