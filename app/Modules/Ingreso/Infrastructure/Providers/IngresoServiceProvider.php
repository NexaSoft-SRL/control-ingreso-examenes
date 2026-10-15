<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Providers;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Infrastructure\Persistence\AlcancePuntoControlSinAsignaciones;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentEstadoExpulsionGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIngresosRecientesGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\IngresoAuditadoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\VentanaExamen;
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
            VentanaPuntoControl::class,
            VentanaExamen::class,
        );
        $this->app->bind(
            EstadoExpulsionGateway::class,
            EloquentEstadoExpulsionGateway::class,
        );

        $this->app->bind(
            AlcancePuntoControl::class,
            AlcancePuntoControlSinAsignaciones::class,
        );

        $this->app->bind(
            IngresosRecientesGateway::class,
            EloquentIngresosRecientesGateway::class,
        );

    }
}
