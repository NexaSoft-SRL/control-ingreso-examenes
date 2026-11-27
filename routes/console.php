<?php

use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Administracion\Application\Actions\GenerarRespaldo;
use App\Modules\Administracion\Application\Actions\ProcesarRebotesDeCorreo;
use App\Modules\Ingreso\Application\Actions\EnviarRecordatoriosDeExamen;
use App\Modules\Reportes\Application\Actions\ProcesarAlertasDeExamen;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * HU-25: el respaldo se genera a demanda desde la pantalla y, ademas, solo
 * cada noche. Sin usuario: lo hace el sistema, y asi queda en la bitacora.
 */
Artisan::command('respaldo:generar', function (GenerarRespaldo $generar) {
    $respaldo = $generar->execute();

    $this->info(sprintf(
        'Respaldo %s: %d tablas, %d filas.',
        $respaldo->archivo,
        $respaldo->tablas,
        $respaldo->filas,
    ));
})->purpose('Genera un respaldo de los datos del sistema');

/*
 * Los periodos salen de los archivos de oferta y de los calendarios de
 * `database/umss/fuentes.json`. El estado no se guarda: se calcula con la
 * fecha de hoy en la zona de la universidad.
 */
Artisan::command('periodos:detectar', function (DetectarPeriodos $detectar) {
    $resultado = $detectar->execute();

    $zona = config('umss.zona_horaria');
    $hoy = now(is_string($zona) ? $zona : 'America/La_Paz')->toDateString();
    $filas = [];

    foreach ($resultado->periodos as $periodo) {
        $estado = match (true) {
            $periodo->fechaInicio === null || $periodo->fechaFin === null => 'Sin fechas',
            $hoy < $periodo->fechaInicio => 'Próximo',
            $hoy > $periodo->fechaFin => 'Cerrado',
            default => 'Vigente',
        };

        $filas[] = [
            $periodo->codigo,
            $periodo->tipoEtiqueta,
            $periodo->fechaInicio ?? '—',
            $periodo->fechaFin ?? '—',
            $estado,
        ];
    }

    $this->table(['Período', 'Tipo', 'Inicio', 'Fin', 'Estado'], $filas);

    $this->info(sprintf(
        '%d creados, %d actualizados.',
        $resultado->creados,
        $resultado->actualizados,
    ));
})->purpose('Detecta los periodos academicos en las fuentes');

/*
 * Importa la oferta de una facultad (fcyt, fce, fhce, fach) o de las
 * cuatro. Sale con codigo 1 si alguna falla.
 */
Artisan::command('oferta:importar {facultad? : fcyt, fce, fhce o fach; sin ella, las cuatro}', function (
    ImportarOferta $importar,
    FuenteOfertaGateway $fuente,
) {
    $pedida = $this->argument('facultad');
    $facultades = is_string($pedida) && $pedida !== '' ? [$pedida] : $fuente->facultades();
    $fallo = false;

    foreach ($facultades as $facultad) {
        try {
            $resumen = $importar->execute($facultad);
        } catch (FuenteNoDisponibleException $error) {
            $this->error($error->getMessage());
            $fallo = true;

            continue;
        }

        if (! $resumen->importada) {
            $this->error(sprintf('%s: %s', $resumen->sigla, (string) $resumen->error));
            $fallo = true;

            continue;
        }

        $this->info(sprintf(
            '%s: %d carreras, %d asignaturas, %d grupos (%d sin docente), %d docentes, %d aulas, %d sesiones descartadas.',
            $resumen->sigla,
            $resumen->carreras,
            $resumen->asignaturas,
            $resumen->grupos,
            $resumen->gruposSinDocente,
            $resumen->docentes,
            $resumen->aulas,
            $resumen->sesionesDescartadas,
        ));
    }

    return $fallo ? 1 : 0;
})->purpose('Importa la oferta academica de GENDA');

/*
 * Importa la oferta GUARDADA de un periodo cerrado (por ahora, 1/2026) desde
 * `database/umss/genda/historial/`. No toca la oferta vigente ni el estado de
 * su importacion; lo anual no entra. Sale con codigo 1 si alguna falla.
 */
Artisan::command('oferta:importar-historial {periodo : 1/2026} {facultad? : fcyt, fce, fhce o fach; sin ella, las cuatro}', function (
    ImportarOferta $importar,
    FuenteOfertaGateway $fuente,
) {
    $periodoPedido = $this->argument('periodo');
    $periodo = is_string($periodoPedido) ? $periodoPedido : '';
    $pedida = $this->argument('facultad');
    $facultades = is_string($pedida) && $pedida !== '' ? [$pedida] : $fuente->facultades();
    $fallo = false;

    foreach ($facultades as $facultad) {
        try {
            $resumen = $importar->historial($facultad, $periodo);
        } catch (FuenteNoDisponibleException $error) {
            $this->error($error->getMessage());
            $fallo = true;

            continue;
        }

        if (! $resumen->importada) {
            $this->error(sprintf('%s: %s', $resumen->sigla, (string) $resumen->error));
            $fallo = true;

            continue;
        }

        $this->info(sprintf(
            '%s %s: %d asignaturas, %d grupos (%d sin docente), %d docentes, %d aulas. Pico de memoria: %.1f MB.',
            $resumen->sigla,
            $periodo,
            $resumen->asignaturas,
            $resumen->grupos,
            $resumen->gruposSinDocente,
            $resumen->docentes,
            $resumen->aulas,
            memory_get_peak_usage(true) / 1048576,
        ));
    }

    return $fallo ? 1 : 0;
})->purpose('Importa la oferta guardada de un periodo cerrado');

Schedule::command('respaldo:generar')
    ->dailyAt('03:00')
    ->withoutOverlapping();

/*
 * El servidor de destino no tiene procesos permanentes: la cola de correos
 * se vacia cada minuto desde el planificador.
 */
Schedule::command('queue:work --stop-when-empty --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

Artisan::command('examenes:recordatorios', function (EnviarRecordatoriosDeExamen $enviar) {
    $cantidad = $enviar->execute();

    $this->info(sprintf('%d recordatorio(s) de examen enviados.', $cantidad));
})->purpose('Envia recordatorios por correo de examenes del dia siguiente');

Schedule::command('examenes:recordatorios')
    ->dailyAt('08:00')
    ->timezone(config('umss.zona_horaria', 'America/La_Paz'))
    ->withoutOverlapping();

Artisan::command('reportes:alertas-cierre', function (ProcesarAlertasDeExamen $procesar) {
    $cantidad = $procesar->execute();

    $this->info(sprintf('%d examenes cerrados procesados para alertas.', $cantidad));
})->purpose('Procesa las alertas de los examenes cerrados');

Schedule::command('reportes:alertas-cierre')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Los correos que rebotaron (direccion inexistente) pasan a fallidos. Sin
 * `UMSS_REVISAR_REBOTES` no se lee el buzon.
 */
Artisan::command('correo:rebotes', function (ProcesarRebotesDeCorreo $procesar) {
    $cantidad = $procesar->execute();

    $this->info(sprintf('%d envios marcados como no entregados.', $cantidad));
})->purpose('Lee los correos rebotados y marca esos envios como fallidos');

Schedule::command('correo:rebotes')
    ->everyTenMinutes()
    ->withoutOverlapping();
