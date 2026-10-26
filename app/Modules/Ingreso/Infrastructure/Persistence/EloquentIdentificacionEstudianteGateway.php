<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\IdentificacionEstudianteGateway;
use Illuminate\Support\Facades\DB;

final class EloquentIdentificacionEstudianteGateway implements IdentificacionEstudianteGateway
{
    public function codigoPorIdentificador(string $identificador): ?array
    {
        $valor = trim($identificador);

        if ($valor === '') {
            return null;
        }

        $codigo = DB::table('estudiantes')
            ->where('codigo_universitario', $valor)
            ->value('codigo_universitario');

        if (is_string($codigo)) {
            return ['codigo' => $codigo, 'medio' => 'codigo'];
        }

        $normalizado = mb_strtoupper(str_replace([' ', '-'], '', $valor));

        $codigo = DB::table('estudiantes')
            ->whereRaw("upper(replace(replace(documento_identidad, ' ', ''), '-', '')) = ?", [$normalizado])
            ->orderBy('id')
            ->value('codigo_universitario');

        return is_string($codigo) ? ['codigo' => $codigo, 'medio' => 'documento'] : null;
    }
}
