<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Infrastructure\Providers;

use App\Modules\Monitoreo\Application\Contracts\EnCursoGateway;
use App\Modules\Monitoreo\Infrastructure\Persistence\EloquentEnCursoGateway;
use Illuminate\Support\ServiceProvider;

final class MonitoreoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            EnCursoGateway::class,
            EloquentEnCursoGateway::class,
        );
    }
}
