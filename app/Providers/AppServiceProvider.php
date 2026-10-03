<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentAmbienteRepository;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentAuthenticationSecurityGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentBitacoraGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentConsultaBitacoraGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudentRepository;
use App\Modules\Examenes\Application\Contracts\AmbienteExamenGateway;
use App\Modules\Examenes\Application\Contracts\AsignacionAmbienteGateway;
use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\Contracts\DocenteGateway;
use App\Modules\Examenes\Application\Contracts\EstudianteExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\Contracts\NormaExamenGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentAmbienteExamenGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentAsignacionAmbienteGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentAsignaturaGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentDocenteGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentEstudianteExamenGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentExamenGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentNormaExamenGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Infrastructure\Persistence\EloquentHabilitacionGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AuthenticationSecurityGateway::class,
            EloquentAuthenticationSecurityGateway::class,
        );

        $this->app->bind(
            BitacoraGateway::class,
            EloquentBitacoraGateway::class,
        );

        $this->app->bind(
            ConsultaBitacoraGateway::class,
            EloquentConsultaBitacoraGateway::class,
        );

        $this->app->bind(
            AsignaturaGateway::class,
            EloquentAsignaturaGateway::class,
        );

        $this->app->bind(
            DocenteGateway::class,
            EloquentDocenteGateway::class,
        );

        $this->app->bind(
            ExamenGateway::class,
            EloquentExamenGateway::class,
        );

        $this->app->bind(
            EstudianteExamenGateway::class,
            EloquentEstudianteExamenGateway::class,
        );

        $this->app->bind(
            NormaExamenGateway::class,
            EloquentNormaExamenGateway::class,
        );

        $this->app->bind(
            StudentRepository::class,
            EloquentStudentRepository::class,
        );

        $this->app->bind(
            AmbienteRepository::class,
            EloquentAmbienteRepository::class,
        );

        $this->app->bind(
            AmbienteExamenGateway::class,
            EloquentAmbienteExamenGateway::class,
        );

        $this->app->bind(
            HabilitacionGateway::class,
            EloquentHabilitacionGateway::class,
        );

        $this->app->bind(
            AsignacionAmbienteGateway::class,
            EloquentAsignacionAmbienteGateway::class,
        );
    }

    public function boot(): void
    {
        //
    }
}
