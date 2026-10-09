<?php

use App\Modules\Academico\Infrastructure\Providers\AcademicoServiceProvider;
use App\Modules\Administracion\Infrastructure\Providers\AdministracionServiceProvider;
use App\Modules\Estudiantes\Infrastructure\Providers\EstudiantesServiceProvider;
use App\Modules\Examenes\Infrastructure\Providers\ExamenesServiceProvider;
use App\Modules\Habilitacion\Infrastructure\Providers\HabilitacionServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AdministracionServiceProvider::class,
    AcademicoServiceProvider::class,
    EstudiantesServiceProvider::class,
    ExamenesServiceProvider::class,
    HabilitacionServiceProvider::class,
];
