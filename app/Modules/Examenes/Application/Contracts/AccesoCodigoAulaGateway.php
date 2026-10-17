<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

interface AccesoCodigoAulaGateway
{
    /**
     * @return array{token: string, examen_id: int, aula_id: int, aula_nombre: string, nombre_colaborador: string, expira_en: string}|null
     */
    public function ingresar(string $codigo, string $nombreColaborador): ?array;

    public function salir(string $token): void;

    /**
     * @return array{estado: 'ok'|'no_valido'|'finalizado', data?: array{token: string, examen_id: int, aula_id: int, aula_nombre: string, nombre_colaborador: string, expira_en: string}}
     */
    public function sesion(string $token): array;
}
