<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use Database\Factories\UserFactory;
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

    public function test_room_is_attended_by_its_keeper_and_by_its_support_only(): void
    {
        $docente = $this->docenteConCuenta();
        $propia = $this->aula();
        $delegada = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$propia, $delegada]);

        $encargado = $this->auxiliar();
        $apoyo = $this->auxiliar();
        $ajeno = $this->auxiliar();

        $this->ponerACargo($examen, $delegada, $encargado);
        $this->ponerDeApoyo($examen, $delegada, $apoyo);

        $alcance = $this->alcance();

        $this->assertTrue($alcance->atiendeAula((int) $docente->user_id, $examen->id, $propia->id));
        $this->assertFalse($alcance->atiendeAula((int) $docente->user_id, $examen->id, $delegada->id));

        $this->assertTrue($alcance->atiendeAula($this->id($encargado->getKey()), $examen->id, $delegada->id));
        $this->assertTrue($alcance->atiendeAula($this->id($apoyo->getKey()), $examen->id, $delegada->id));
        $this->assertFalse($alcance->atiendeAula($this->id($apoyo->getKey()), $examen->id, $propia->id));
        $this->assertFalse($alcance->atiendeAula($this->id($ajeno->getKey()), $examen->id, $delegada->id));
    }

    public function test_rooms_attended_are_those_of_the_exams_of_that_day(): void
    {
        $docente = $this->docenteConCuenta();
        $grupo = $this->grupo($docente);
        $deHoy = $this->aula();
        $deApoyo = $this->aula();
        $deManana = $this->aula();

        $hoy = now()->toDateString();
        $manana = now()->addDay()->toDateString();

        $examenHoy = $this->examen($docente, [$grupo], [$deHoy, $deApoyo]);
        $examenManana = $this->examen($docente, [$grupo], [$deManana], [
            'tipo' => 'SEGUNDO_PARCIAL',
            'fecha' => $manana,
        ]);

        $auxiliar = $this->auxiliar(UserFactory::new()->createOne());
        $auxiliarId = $this->id($auxiliar->getKey());

        $this->ponerACargo($examenHoy, $deHoy, $auxiliar);
        $this->ponerDeApoyo($examenHoy, $deApoyo, $auxiliar);
        $this->ponerACargo($examenManana, $deManana, $auxiliar);

        $aulas = $this->alcance()->aulasQueAtiende($auxiliarId, $hoy);

        $this->assertCount(2, $aulas);
        $this->assertSame(
            [[$examenHoy->id, $deHoy->id, true], [$examenHoy->id, $deApoyo->id, false]],
            array_map(
                static fn ($aula): array => [$aula->examenId, $aula->aulaId, $aula->esEncargado],
                $aulas,
            ),
        );

        $this->assertCount(1, $this->alcance()->aulasQueAtiende($auxiliarId, $manana));
        $this->assertSame([], $this->alcance()->aulasQueAtiende($auxiliarId, now()->subDay()->toDateString()));
    }

    private function alcance(): AlcanceExamenGateway
    {
        return $this->app->make(AlcanceExamenGateway::class);
    }

    private function id(mixed $clave): int
    {
        if (! is_int($clave)) {
            $this->fail('El identificador de la cuenta debía ser entero.');
        }

        return $clave;
    }
}
