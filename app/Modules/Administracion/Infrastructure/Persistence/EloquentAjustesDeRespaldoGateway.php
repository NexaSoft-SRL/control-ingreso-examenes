<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Actions\GenerarRespaldoAutomatico;
use App\Modules\Administracion\Application\Contracts\AjustesDeRespaldoGateway;
use App\Modules\Administracion\Application\DTOs\AjustesDeRespaldoData;
use Illuminate\Support\Facades\DB;

final class EloquentAjustesDeRespaldoGateway implements AjustesDeRespaldoGateway
{
    private const HABILITADO = 'respaldo.habilitado';

    private const HORA = 'respaldo.hora';

    private const REINTENTO = 'respaldo.reintento_minutos';

    public function leer(): AjustesDeRespaldoData
    {
        /** @var array<string, string> $guardados */
        $guardados = DB::table('ajustes')
            ->whereIn('clave', [self::HABILITADO, self::HORA, self::REINTENTO])
            ->pluck('valor', 'clave')
            ->all();

        $hora = $guardados[self::HORA] ?? config('umss.respaldo_automatico.hora');
        $reintento = $guardados[self::REINTENTO] ?? config('umss.respaldo_automatico.reintento_minutos');

        return new AjustesDeRespaldoData(
            habilitado: isset($guardados[self::HABILITADO])
                ? $guardados[self::HABILITADO] === '1'
                : config('umss.respaldo_automatico.habilitado') === true,
            hora: is_string($hora) && $hora !== '' ? $hora : GenerarRespaldoAutomatico::HORA_POR_DEFECTO,
            reintentoMinutos: is_numeric($reintento) ? max(1, (int) $reintento) : 30,
        );
    }

    public function guardar(AjustesDeRespaldoData $ajustes): void
    {
        $ahora = now();

        foreach ([
            self::HABILITADO => $ajustes->habilitado ? '1' : '0',
            self::HORA => $ajustes->hora,
            self::REINTENTO => (string) $ajustes->reintentoMinutos,
        ] as $clave => $valor) {
            DB::table('ajustes')->updateOrInsert(['clave' => $clave], ['valor' => $valor, 'updated_at' => $ahora]);
        }
    }
}
