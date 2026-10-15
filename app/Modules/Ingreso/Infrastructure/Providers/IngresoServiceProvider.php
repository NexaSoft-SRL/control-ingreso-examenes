<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Providers;

use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIngresoGateway;
use Illuminate\Support\ServiceProvider;

final class IngresoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            IngresoGateway::class,
            EloquentIngresoGateway::class,
        );
    }
}
