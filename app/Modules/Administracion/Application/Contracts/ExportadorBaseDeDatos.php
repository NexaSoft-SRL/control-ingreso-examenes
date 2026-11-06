<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

/**
 * HU-25: Exporta los datos de las tablas no transitorias en formato SQL.
 */
interface ExportadorBaseDeDatos
{
    /**
     * Genera el volcado SQL completo de la base de datos excluyendo tablas transitorias.
     */
    public function exportar(string $versionEsquema, string $tipo): string;
}
