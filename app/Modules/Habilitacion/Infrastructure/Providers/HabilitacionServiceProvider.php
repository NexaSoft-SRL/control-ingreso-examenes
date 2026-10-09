<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Infrastructure\Providers;

use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\Contracts\RepartoGateway;
use App\Modules\Habilitacion\Infrastructure\Persistence\EloquentHabilitacionGateway;
use App\Modules\Habilitacion\Infrastructure\Persistence\EloquentRepartoGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces contrato -> implementacion del modulo Habilitacion.
 */
final class HabilitacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            HabilitacionGateway::class,
            EloquentHabilitacionGateway::class,
        );

        $this->app->bind(
            RepartoGateway::class,
            EloquentRepartoGateway::class,
        );
    }
}
