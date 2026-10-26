<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Providers;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;
use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use App\Modules\Ingreso\Application\Contracts\IdentificacionEstudianteGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaManualPuertaGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciasIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Contracts\VerificacionPuntoControlGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentAlcancePuntoControl;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentContextoOperativoPuntoControl;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentCorreoQrGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentDetallePuertaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentEmisionQrGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentEstadoExpulsionGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentExamenesDePuerta;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIdentificacionEstudianteGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIncidenciaIngresoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIncidenciaManualPuertaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIncidenciasIngresoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentIngresosRecientesGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentOperacionesPuertaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentSeguimientoAulaGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\EloquentVerificacionPuntoControlGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\IngresoAuditadoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\VentanaExamen;
use App\Modules\Ingreso\Infrastructure\QR\CodigoQrCifrado;
use App\Modules\Ingreso\Infrastructure\QR\GeneradorImagenQrEndroid;
use Illuminate\Support\ServiceProvider;

final class IngresoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DetallePuertaGateway::class,
            EloquentDetallePuertaGateway::class,
        );

        $this->app->bind(
            IncidenciaManualPuertaGateway::class,
            EloquentIncidenciaManualPuertaGateway::class,
        );

        $this->app->bind(
            OperacionesPuertaGateway::class,
            EloquentOperacionesPuertaGateway::class,
        );

        $this->app->bind(
            IdentificacionEstudianteGateway::class,
            EloquentIdentificacionEstudianteGateway::class,
        );

        $this->app->bind(
            ExamenesDePuerta::class,
            EloquentExamenesDePuerta::class,
        );

        $this->app->bind(
            IngresoGateway::class,
            IngresoAuditadoGateway::class,
        );

        $this->app->bind(
            IncidenciaIngresoGateway::class,
            EloquentIncidenciaIngresoGateway::class,
        );

        $this->app->bind(
            IncidenciasIngresoGateway::class,
            EloquentIncidenciasIngresoGateway::class,
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
            EloquentAlcancePuntoControl::class,
        );

        $this->app->bind(
            ContextoOperativoPuntoControl::class,
            EloquentContextoOperativoPuntoControl::class,
        );

        $this->app->bind(
            IngresosRecientesGateway::class,
            EloquentIngresosRecientesGateway::class,
        );

        $this->app->bind(
            SeguimientoAulaGateway::class,
            EloquentSeguimientoAulaGateway::class,
        );

        $this->app->bind(
            CodigoQrGateway::class,
            CodigoQrCifrado::class,
        );

        $this->app->bind(
            VerificacionPuntoControlGateway::class,
            EloquentVerificacionPuntoControlGateway::class,
        );

        // HU-20: los codigos QR emitidos de cada examen.
        $this->app->bind(
            EmisionQrGateway::class,
            EloquentEmisionQrGateway::class,
        );

        $this->app->bind(
            CorreoQrGateway::class,
            EloquentCorreoQrGateway::class,
        );

        $this->app->bind(
            GeneradorImagenQr::class,
            GeneradorImagenQrEndroid::class,
        );
    }
}
