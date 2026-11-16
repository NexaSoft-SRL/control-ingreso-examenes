<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Providers;

use App\Modules\Reportes\Application\Actions\ProcesarAlertasDeExamen;
use App\Modules\Reportes\Application\Contracts\AlertaReporteGateway;
use App\Modules\Reportes\Application\Contracts\BitacoraExamenGateway;
use App\Modules\Reportes\Application\Contracts\ExportadorReporte;
use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\Queries\ArmarListaDeGrupo;
use App\Modules\Reportes\Infrastructure\Export\ExportadorReporteArchivo;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentAlertaReporteGateway;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentBitacoraExamenGateway;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentReporteGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Enlaces contrato -> implementacion del modulo Reportes.
 */
final class ReportesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ExportadorReporte::class,
            ExportadorReporteArchivo::class,
        );

        $this->app->bind(
            ReporteGateway::class,
            EloquentReporteGateway::class,
        );

        $this->app->bind(
            BitacoraExamenGateway::class,
            EloquentBitacoraExamenGateway::class,
        );

        $this->app->bind(
            AlertaReporteGateway::class,
            EloquentAlertaReporteGateway::class,
        );

        $this->app->when(ArmarListaDeGrupo::class)
            ->needs('$dominioCorreo')
            ->giveConfig('umss.dominio_correo_estudiantes', 'est.umss.edu');
    }

    /**
     * Sin tareas programadas en el servidor, las alertas de los examenes
     * cerrados se procesan al terminar una peticion web, como mucho una vez
     * por minuto (el candado de la cache). Un fallo no afecta a la respuesta,
     * que ya salio: queda en el registro y se reintenta en el minuto siguiente.
     */
    public function boot(): void
    {
        $this->app->terminating(function (): void {
            if (config('umss.alertas_al_responder') !== true) {
                return;
            }

            if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                return;
            }

            if (! Cache::add('reportes:alertas:procesadas', true, 60)) {
                return;
            }

            try {
                $this->app->make(ProcesarAlertasDeExamen::class)->execute();
            } catch (Throwable $error) {
                report($error);
            }
        });
    }
}
