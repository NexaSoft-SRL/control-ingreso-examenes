<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Asignatura;
use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Carrera;
use App\Modules\Academico\Domain\Models\Facultad;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Academico\Domain\Models\Horario;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Academico\Domain\Models\PlanEstudio;

/**
 * Lo que `DatosAcademicos` no trae y las pruebas de la oferta necesitan:
 * carreras, plan de estudios, horarios y periodos con fechas a medida.
 */
trait OfertaDePrueba
{
    private int $secuenciaOferta = 0;

    protected function carrera(Facultad $facultad, string $nombre, RegimenCarrera $regimen = RegimenCarrera::Semestral): Carrera
    {
        return Carrera::create([
            'facultad_id' => $facultad->id,
            'codigo' => 'C'.(++$this->secuenciaOferta),
            'nombre' => $nombre,
            'regimen' => $regimen,
        ]);
    }

    protected function enPlan(Carrera $carrera, Asignatura $asignatura, string $nivel): void
    {
        PlanEstudio::create([
            'carrera_id' => $carrera->id,
            'asignatura_id' => $asignatura->id,
            'nivel' => $nivel,
        ]);
    }

    protected function horario(Grupo $grupo, string $dia, string $inicio, string $fin, ?Aula $aula = null): void
    {
        Horario::create([
            'grupo_id' => $grupo->id,
            'aula_id' => $aula?->id,
            'dia' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);
    }

    /**
     * Un periodo cuyas fechas van de `$desde` a `$hasta` dias respecto de
     * hoy; con null queda sin fechas.
     */
    protected function periodo(string $codigo, ?int $desde, ?int $hasta, TipoPeriodo $tipo = TipoPeriodo::Semestre2): Periodo
    {
        [$numero, $anio] = array_map('intval', explode('/', $codigo));

        return Periodo::create([
            'codigo' => $codigo,
            'anio' => $anio,
            'numero' => $numero,
            'tipo' => $tipo,
            'fecha_inicio' => $desde === null ? null : now()->addDays($desde)->toDateString(),
            'fecha_fin' => $hasta === null ? null : now()->addDays($hasta)->toDateString(),
        ]);
    }
}
