<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Reportes\Application\DTOs\ArchivoReporte;
use Illuminate\Http\Response;

/**
 * La respuesta de una descarga, igual para todos los reportes.
 */
trait EntregaArchivo
{
    private function descarga(ArchivoReporte $archivo): Response
    {
        return response($archivo->contenido, Response::HTTP_OK, [
            'Content-Type' => $archivo->tipo,
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombre.'"',
            'Content-Length' => (string) strlen($archivo->contenido),
        ]);
    }
}
