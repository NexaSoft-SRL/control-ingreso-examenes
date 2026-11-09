<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Infrastructure\Providers;

use App\Modules\Monitoreo\Application\Contracts\SeguimientoGateway;
use App\Modules\Monitoreo\Infrastructure\Persistence\EloquentSeguimientoGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces contrato -> implementacion del modulo Monitoreo.
 */
final class MonitoreoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SeguimientoGateway::class,
            EloquentSeguimientoGateway::class,
        );
    }
}
