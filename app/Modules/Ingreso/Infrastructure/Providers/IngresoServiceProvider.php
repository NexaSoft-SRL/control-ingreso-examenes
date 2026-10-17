<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Providers;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciasIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Contracts\VerificacionPuntoControlGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentAlcancePuntoControl;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentContextoOperativoPuntoControl;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentEstadoExpulsionGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIncidenciaIngresoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIncidenciasIngresoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIngresosRecientesGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentSeguimientoAulaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentVerificacionPuntoControlGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\IngresoAuditadoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\VentanaExamen;
use App\Modules\Ingreso\Infrastructure\QR\CodigoQrCifrado;
use Illuminate\Support\ServiceProvider;

final class IngresoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            IngresoGateway::class,
            IngresoAuditadoGateway::class,
        );

        $this->app->bind(
            IncidenciaIngresoGateway::class,
            EloquentIncidenciaIngresoGateway::class,
        );

        $this->app->bind(
            IncidenciasIngresoGateway::class,
            EloquentIncidenciasIngresoGateway::class,
        );

        $this->app->bind(
            VentanaPuntoControl::class,
            VentanaExamen::class,
        );
        $this->app->bind(
            EstadoExpulsionGateway::class,
            EloquentEstadoExpulsionGateway::class,
        );

        $this->app->bind(
            AlcancePuntoControl::class,
            EloquentAlcancePuntoControl::class,
        );

        $this->app->bind(
            ContextoOperativoPuntoControl::class,
            EloquentContextoOperativoPuntoControl::class,
        );

        $this->app->bind(
            IngresosRecientesGateway::class,
            EloquentIngresosRecientesGateway::class,
        );

        $this->app->bind(
            SeguimientoAulaGateway::class,
            EloquentSeguimientoAulaGateway::class,
        );

        $this->app->bind(
            CodigoQrGateway::class,
            CodigoQrCifrado::class,
        );

        $this->app->bind(
            VerificacionPuntoControlGateway::class,
            EloquentVerificacionPuntoControlGateway::class,
        );
    }
}
