<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Providers;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentAlcanceExamenGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentConsultaExamenGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentExamenGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentPlantillaNormaGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces contrato -> implementacion del modulo Examenes.
 */
final class ExamenesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AlcanceExamenGateway::class,
            EloquentAlcanceExamenGateway::class,
        );

        $this->app->bind(ExamenGateway::class, EloquentExamenGateway::class);
        $this->app->bind(ConsultaExamenGateway::class, EloquentConsultaExamenGateway::class);
        $this->app->bind(PlantillaNormaGateway::class, EloquentPlantillaNormaGateway::class);
    }
}
