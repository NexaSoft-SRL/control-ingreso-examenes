<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Providers;

use App\Modules\Estudiantes\Application\Contracts\CodigosQrDelEstudiante;
use App\Modules\Ingreso\Application\Contracts\AccesoAulaGateway;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\CondicionHabilitacionGateway;
use App\Modules\Ingreso\Application\Contracts\ControlPuertaGateway;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciasComunesGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\Contracts\Transaccion;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentAccesoAulaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentCodigoQrGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentCondicionHabilitacionGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentControlPuertaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentCorreoQrGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIncidenciaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIncidenciasComunesGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIngresoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentPuertaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\TransaccionDeBaseDeDatos;
use App\Modules\Ingreso\Infrastructure\QR\CodigosQrDelEstudianteEmitidos;
use App\Modules\Ingreso\Infrastructure\QR\GeneradorImagenQrEndroid;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces contrato -> implementacion del modulo Ingreso.
 */
final class IngresoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccesoAulaGateway::class, EloquentAccesoAulaGateway::class);
        $this->app->bind(ControlPuertaGateway::class, EloquentControlPuertaGateway::class);
        $this->app->bind(CodigoQrGateway::class, EloquentCodigoQrGateway::class);
        $this->app->bind(CondicionHabilitacionGateway::class, EloquentCondicionHabilitacionGateway::class);
        $this->app->bind(CorreoQrGateway::class, EloquentCorreoQrGateway::class);
        $this->app->bind(GeneradorImagenQr::class, GeneradorImagenQrEndroid::class);
        $this->app->bind(CodigosQrDelEstudiante::class, CodigosQrDelEstudianteEmitidos::class);
        $this->app->bind(IncidenciaGateway::class, EloquentIncidenciaGateway::class);
        $this->app->bind(IncidenciasComunesGateway::class, EloquentIncidenciasComunesGateway::class);
        $this->app->bind(IngresoGateway::class, EloquentIngresoGateway::class);
        $this->app->bind(PuertaGateway::class, EloquentPuertaGateway::class);
        $this->app->bind(Transaccion::class, TransaccionDeBaseDeDatos::class);
    }
}
