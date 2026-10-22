<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

interface AuxiliaresDocenteGateway
{
    /**
     * @return list<array{id: int, nombre: string, usuario: string, codigo: string, correo: string}>
     */
    public function buscar(string $termino): array;

    /**
     * @return list<array{id: int, nombre: string, usuario: string, codigo: string, correo: string}>
     */
    public function listarDelDocente(int $docenteId): array;

    /**
     * @return array{id: int, nombre: string, rol_nombre: string}|null
     */
    public function buscarPorCodigoOCorreo(string $codigo, string $correo): ?array;

    public function agregar(int $docenteId, int $auxiliarId): void;

    public function estaEnLista(int $docenteId, int $auxiliarId): bool;

    public function existeUsuario(int $auxiliarId): bool;

    /**
     * @return array{id: int, nombre: string, usuario: string, correo: string}|null
     */
    public function obtenerPorId(int $auxiliarId): ?array;

    /**
     * @return array{id: int, nombre: string, usuario: string}|null
     */
    public function obtenerUsuario(int $usuarioId): ?array;

    public function tieneExamenPendiente(int $docenteId, int $auxiliarId): bool;

    public function quitar(int $docenteId, int $auxiliarId): void;
}
