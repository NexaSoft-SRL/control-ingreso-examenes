<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El docente agrega un estudiante suelto a su grupo, sin archivo y aunque el
 * grupo todavia no tenga lista: las mismas reglas que una fila de la lista.
 */
final class AgregarInscritoTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const ALUMNO = [
        'codigo_universitario' => '202104821',
        'documento_identidad' => '7928194',
        'nombres' => 'Kevin René',
        'apellidos' => 'Alvarado Claros',
    ];

    public function test_a_teacher_adds_a_student_to_a_group_without_a_list(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos", self::ALUMNO)
            ->assertOk()
            ->assertJsonPath('archivo', 'Agregado a mano')
            ->assertJsonPath('resumen.nuevos', 1);

        $this->assertSame(1, Inscripcion::query()->where('grupo_id', $grupo->id)->count());

        // Otra vez: ya esta en la lista.
        $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos", self::ALUMNO)
            ->assertOk()
            ->assertJsonPath('resumen.ya_inscritos', 1);

        $this->assertSame(1, Estudiante::query()->count());
    }

    public function test_the_same_rules_as_a_row_of_the_list_apply(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos", ['codigo_universitario' => 'abc'] + self::ALUMNO)
            ->assertOk()
            ->assertJsonPath('resumen.rechazados', 1)
            ->assertJsonPath('rechazos.0.motivo', 'Código universitario no válido');

        $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos']);

        $this->assertSame(0, Inscripcion::query()->count());
    }

    public function test_only_the_teacher_of_the_group_can_add(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->actingAs($this->cuentaDe($this->docente()))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos", self::ALUMNO)
            ->assertForbidden();
    }
}
