<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Providers;

use App\Modules\Reportes\Application\Contracts\ConsultaBitacoraExamenGateway;
use App\Modules\Reportes\Application\Contracts\ConsultaReportesGateway;
use App\Modules\Reportes\Application\Contracts\GeneradorArchivoBitacora;
use App\Modules\Reportes\Application\Contracts\GeneradorArchivoReporte;
use App\Modules\Reportes\Application\Contracts\GeneradorTablaReporte;
use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Infrastructure\Export\GeneradorArchivoBitacoraDompdfPhpSpreadsheet;
use App\Modules\Reportes\Infrastructure\Export\GeneradorArchivoReporteDompdfPhpSpreadsheet;
use App\Modules\Reportes\Infrastructure\Export\GeneradorTablaReporteDompdfPhpSpreadsheet;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentConsultaBitacoraExamenGateway;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentConsultaReportesGateway;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentReporteGateway;
use Illuminate\Support\ServiceProvider;

final class ReportesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GeneradorTablaReporte::class, GeneradorTablaReporteDompdfPhpSpreadsheet::class);
        $this->app->bind(ReporteGateway::class, EloquentReporteGateway::class);
        $this->app->bind(
            ConsultaBitacoraExamenGateway::class,
            EloquentConsultaBitacoraExamenGateway::class,
        );
        $this->app->bind(ConsultaReportesGateway::class, EloquentConsultaReportesGateway::class);
        $this->app->bind(
            GeneradorArchivoBitacora::class,
            GeneradorArchivoBitacoraDompdfPhpSpreadsheet::class,
        );
        $this->app->bind(
            GeneradorArchivoReporte::class,
            GeneradorArchivoReporteDompdfPhpSpreadsheet::class,
        );
    }
}
