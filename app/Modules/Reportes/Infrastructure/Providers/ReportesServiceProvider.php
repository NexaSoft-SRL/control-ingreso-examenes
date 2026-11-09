<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Providers;

use App\Modules\Reportes\Application\Contracts\BitacoraExamenGateway;
use App\Modules\Reportes\Application\Contracts\ExportadorReporte;
use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\Queries\ArmarListaDeGrupo;
use App\Modules\Reportes\Infrastructure\Export\ExportadorReporteArchivo;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentBitacoraExamenGateway;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentReporteGateway;
use Illuminate\Support\ServiceProvider;

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

        $this->app->when(ArmarListaDeGrupo::class)
            ->needs('$dominioCorreo')
            ->giveConfig('umss.dominio_correo_estudiantes', 'est.umss.edu');
    }
}
