<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Providers;

use App\Modules\Academico\Application\Contracts\AjustePeriodoGateway;
use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Academico\Application\Contracts\ConsultaAulasGateway;
use App\Modules\Academico\Application\Contracts\ConsultaDocentesGateway;
use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\Contracts\CuentaDocenteGateway;
use App\Modules\Academico\Application\Contracts\DeteccionPeriodoGateway;
use App\Modules\Academico\Application\Contracts\FuenteCalendarioGateway;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Application\Contracts\FuentePensumGateway;
use App\Modules\Academico\Application\Contracts\FuenteUbicacionesGateway;
use App\Modules\Academico\Application\Contracts\ImportacionGateway;
use App\Modules\Academico\Application\Contracts\OfertaGateway;
use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Academico\Application\Contracts\UbicacionGateway;
use App\Modules\Academico\Infrastructure\Import\LectorFuentesUmss;
use App\Modules\Academico\Infrastructure\Import\LectorOfertaGenda;
use App\Modules\Academico\Infrastructure\Import\LectorPensumGenda;
use App\Modules\Academico\Infrastructure\Import\LectorUbicacionesGenda;
use App\Modules\Academico\Infrastructure\Persistence\EloquentAjustePeriodoGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentAlcanceDocenteGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentConsultaAulasGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentConsultaDocentesGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentConsultaOfertaGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentCuentaDocenteGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentDeteccionPeriodoGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentImportacionGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentOfertaGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentPeriodoGateway;
use App\Modules\Academico\Infrastructure\Persistence\EloquentUbicacionGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces contrato -> implementacion del modulo Academico.
 */
final class AcademicoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AlcanceDocenteGateway::class,
            EloquentAlcanceDocenteGateway::class,
        );

        $this->app->bind(
            PeriodoGateway::class,
            EloquentPeriodoGateway::class,
        );

        // Importacion de la oferta y deteccion de periodos (F0-C).
        $this->app->bind(DeteccionPeriodoGateway::class, EloquentDeteccionPeriodoGateway::class);
        $this->app->bind(FuenteCalendarioGateway::class, LectorFuentesUmss::class);
        $this->app->bind(FuenteOfertaGateway::class, LectorOfertaGenda::class);
        $this->app->bind(FuentePensumGateway::class, LectorPensumGenda::class);
        $this->app->bind(FuenteUbicacionesGateway::class, LectorUbicacionesGenda::class);
        $this->app->bind(ImportacionGateway::class, EloquentImportacionGateway::class);
        $this->app->bind(OfertaGateway::class, EloquentOfertaGateway::class);
        $this->app->bind(UbicacionGateway::class, EloquentUbicacionGateway::class);

        // Consultas de oferta, aulas y docentes, ajuste de periodo y cuenta del docente (B2).
        $this->app->bind(AjustePeriodoGateway::class, EloquentAjustePeriodoGateway::class);
        $this->app->bind(ConsultaAulasGateway::class, EloquentConsultaAulasGateway::class);
        $this->app->bind(ConsultaDocentesGateway::class, EloquentConsultaDocentesGateway::class);
        $this->app->bind(ConsultaOfertaGateway::class, EloquentConsultaOfertaGateway::class);
        $this->app->bind(CuentaDocenteGateway::class, EloquentCuentaDocenteGateway::class);
    }
}
