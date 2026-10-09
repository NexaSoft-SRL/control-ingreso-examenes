<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\TestCase;

final class AlcanceExamenGatewayTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;

    public function test_exam_belongs_to_who_registered_it_and_to_the_teachers_of_its_groups(): void
    {
        $registra = $this->docenteConCuenta();
        $sumado = $this->docenteConCuenta();
        $ajeno = $this->docenteConCuenta();
        $asignatura = $this->asignatura();

        $examen = $this->examen($registra, [
            $this->grupo($registra, $asignatura),
            $this->grupo($sumado, $asignatura),
            $this->grupo(null, $asignatura),
        ]);

        $alcance = $this->alcance();

        $this->assertTrue($alcance->esDocenteDelExamen((int) $registra->user_id, $examen->id));
        $this->assertTrue($alcance->esDocenteDelExamen((int) $sumado->user_id, $examen->id));
        $this->assertFalse($alcance->esDocenteDelExamen((int) $ajeno->user_id, $examen->id));

        $this->assertTrue($alcance->loRegistro((int) $registra->user_id, $examen->id));
        $this->assertFalse($alcance->loRegistro((int) $sumado->user_id, $examen->id));
    }

    public function test_exam_stays_with_who_registered_it_even_without_teaching_any_group(): void
    {
        $registra = $this->docenteConCuenta();
        $otro = $this->docenteConCuenta();

        $examen = $this->examen($registra, [$this->grupo($otro)]);

        $this->assertTrue($this->alcance()->esDocenteDelExamen((int) $registra->user_id, $examen->id));
        $this->assertFalse($this->alcance()->esDocenteDelExamen((int) $registra->user_id, $examen->id + 1));
    }

    private function alcance(): AlcanceExamenGateway
    {
        return $this->app->make(AlcanceExamenGateway::class);
    }
}
