<?php

namespace App\Providers;

use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentAuthenticationSecurityGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudentRepository;
use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\Contracts\DocenteGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentAsignaturaGateway;
use App\Modules\Examenes\Infrastructure\Persistence\EloquentDocenteGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
       
        $this->app->bind(
            AuthenticationSecurityGateway::class,
            EloquentAuthenticationSecurityGateway::class,
        );

        $this->app->bind(
            StudentRepository::class,
            EloquentStudentRepository::class,
        );
        
        $this->app->bind(
            AsignaturaGateway::class,
            EloquentAsignaturaGateway::class,
        );

        $this->app->bind(
            DocenteGateway::class,
            EloquentDocenteGateway::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
