<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Providers;

use App\Modules\Estudiantes\Application\Contracts\ConflictoGateway;
use App\Modules\Estudiantes\Application\Contracts\GeneradorListaDeGrupo;
use App\Modules\Estudiantes\Application\Contracts\GeneradorPlantilla;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\Contracts\InscritosDeExamenGateway;
use App\Modules\Estudiantes\Application\Contracts\LectorListaGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\Queries\ArmarListaDeGrupo;
use App\Modules\Estudiantes\Application\Queries\ConsultarFichaDeEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Infrastructure\Export\ListaDeGrupoXlsx;
use App\Modules\Estudiantes\Infrastructure\Export\PlantillaInscritosXlsx;
use App\Modules\Estudiantes\Infrastructure\Import\LectorListaPhpSpreadsheet;
use App\Modules\Estudiantes\Infrastructure\Persistence\EloquentConflictoGateway;
use App\Modules\Estudiantes\Infrastructure\Persistence\EloquentInscripcionGateway;
use App\Modules\Estudiantes\Infrastructure\Persistence\EloquentInscritosDeExamenGateway;
use App\Modules\Estudiantes\Infrastructure\Persistence\EloquentPadronGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces contrato -> implementacion del modulo Estudiantes.
 */
final class EstudiantesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ConflictoGateway::class,
            EloquentConflictoGateway::class,
        );

        $this->app->bind(
            GeneradorListaDeGrupo::class,
            ListaDeGrupoXlsx::class,
        );

        $this->app->bind(
            GeneradorPlantilla::class,
            PlantillaInscritosXlsx::class,
        );

        $this->app->bind(
            InscripcionGateway::class,
            EloquentInscripcionGateway::class,
        );

        $this->app->bind(
            InscritosDeExamenGateway::class,
            EloquentInscritosDeExamenGateway::class,
        );

        $this->app->bind(
            LectorListaGateway::class,
            LectorListaPhpSpreadsheet::class,
        );

        $this->app->bind(
            PadronGateway::class,
            EloquentPadronGateway::class,
        );

        // El correo institucional de un estudiante es su codigo con el
        // dominio configurado.
        $this->app->when([ArmarListaDeGrupo::class, ConsultarFichaDeEstudiante::class])
            ->needs('$dominioCorreo')
            ->give(static function (): string {
                $dominio = config('umss.dominio_correo_estudiantes');

                return is_string($dominio) && $dominio !== '' ? $dominio : Estudiante::DOMINIO_CORREO;
            });
    }
}
