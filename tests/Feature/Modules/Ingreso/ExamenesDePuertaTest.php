<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class ExamenesDePuertaTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_encuentra_examenes_del_aula(): void
    {
        $cuenta = $this->usuarioConPermisos(['examenes']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-101');

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
        );

        $resultado = app(ExamenesDePuerta::class)
            ->examenesDelAula(
                $aula->id,
                $examen->fecha->format('Y-m-d'),
            );

        self::assertContains($examen->id, $resultado);
    }

    public function test_no_devuelve_examenes_de_otra_aula(): void
    {
        $cuenta = $this->usuarioConPermisos(['examenes']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-101');
        $otraAula = $this->aula('P-102');

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
        );

        $resultado = app(ExamenesDePuerta::class)
            ->examenesDelAula(
                $otraAula->id,
                $examen->fecha->format('Y-m-d'),
            );

        self::assertNotContains($examen->id, $resultado);
    }

    public function test_no_devuelve_examenes_fuera_del_periodo(): void
    {
        $cuenta = $this->usuarioConPermisos(['examenes']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-101');

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
        );

        $fechaLejana = (new \DateTimeImmutable($examen->fecha->format('Y-m-d')))
            ->modify('+10 days')
            ->format('Y-m-d');

        $resultado = app(ExamenesDePuerta::class)
            ->examenesDelAula(
                $aula->id,
                $fechaLejana,
            );

        self::assertNotContains($examen->id, $resultado);
    }

    public function test_incluye_examen_del_dia_siguiente(): void
    {
        $cuenta = $this->usuarioConPermisos(['examenes']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-103');

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
        );

        $fechaAnterior = $examen->fecha
            ->copy()
            ->subDay()
            ->format('Y-m-d');

        $resultado = app(ExamenesDePuerta::class)
            ->examenesDelAula($aula->id, $fechaAnterior);

        self::assertContains($examen->id, $resultado);
    }
}
