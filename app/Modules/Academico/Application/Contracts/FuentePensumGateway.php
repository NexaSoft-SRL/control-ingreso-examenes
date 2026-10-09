<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

interface FuentePensumGateway
{
    /**
     * Las carreras de una facultad segun el pensum: nombre normalizado =>
     * codigo. Vacio si el pensum no esta o no trae esa facultad: sirve solo
     * para resolver el codigo de una carrera que la oferta trae sin el.
     *
     * @param  string  $codigoFacultad  el de la universidad (`20` = FCyT)
     * @return array<string, string>
     */
    public function carrerasDe(string $codigoFacultad): array;
}
