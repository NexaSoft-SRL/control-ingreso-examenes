<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Estudiantes\Application\Contracts\InscritosDeExamenGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La lista de inscritos de un grupo del docente (ruta 40), la plantilla
 * del archivo (ruta 39) y el contrato publico de inscritos.
 */
final class InscritosDeGrupoTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_the_teacher_sees_the_enrolled_students_of_its_group_with_their_origin(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $deAdministracion = $this->estudiantesInscritos($grupo, 1)[0];
        $this->estudiantesInscritos($this->grupo($docente), 2);

        $this->cargarLista($docente, $grupo, ['201901349,6492819,Mariana,Aguilar Cossío'])->assertOk();

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.codigo', '201901349')
            ->assertJsonPath('data.0.nombre', 'Aguilar Cossío, Mariana')
            ->assertJsonPath('data.0.documento', '6492819')
            ->assertJsonPath('data.0.origen', 'Docente')
            ->assertJsonPath('data.1', [
                'id' => $deAdministracion->id,
                'codigo' => $deAdministracion->codigo_universitario,
                'nombre' => 'Apellido 1, Nombre 1',
                'documento' => $deAdministracion->documento_identidad,
                'origen' => 'Administración',
            ])
            ->assertJsonPath('meta', ['total' => 2, 'pagina' => 1, 'por_pagina' => 25]);
    }

    public function test_the_list_is_searched_and_paginated_by_the_server(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $this->estudiantesInscritos($grupo, 30);
        $cuenta = $this->cuentaDe($docente);

        $this->actingAs($cuenta)
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos?por_pagina=10&pagina=3")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta', ['total' => 30, 'pagina' => 3, 'por_pagina' => 10]);

        $this->actingAs($cuenta)
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos?buscar=".urlencode('APELLIDO 17'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Apellido 17, Nombre 17');

        $this->actingAs($cuenta)
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos?buscar=202100017")
            ->assertJsonCount(1, 'data');

        $this->actingAs($cuenta)
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos?buscar=7000017")
            ->assertJsonCount(1, 'data');
    }

    public function test_a_group_without_list_returns_an_empty_page(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos")
            ->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['total' => 0, 'pagina' => 1, 'por_pagina' => 25]]);
    }

    public function test_the_list_validates_its_parameters(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos?por_pagina=500")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['por_pagina']);
    }

    public function test_a_teacher_cannot_see_the_list_of_a_group_of_another_teacher(): void
    {
        $docente = $this->docente();
        $ajeno = $this->grupo($this->docente());
        $porDesignar = $this->grupo();
        $this->estudiantesInscritos($ajeno, 2);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$ajeno->id}/inscritos")
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este grupo no es tuyo.', 'alcance' => true]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$porDesignar->id}/inscritos")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
    }

    public function test_a_group_that_does_not_exist_is_not_found(): void
    {
        $this->actingAs($this->cuentaDe($this->docente()))
            ->getJson('/api/docente/grupos/999999/inscritos')
            ->assertNotFound()
            ->assertJsonPath('message', 'Grupo no encontrado.');
    }

    public function test_a_guest_cannot_see_the_list(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->getJson("/api/docente/grupos/{$grupo->id}/inscritos")->assertUnauthorized();
    }

    public function test_a_role_without_the_permission_cannot_see_the_list(): void
    {
        $grupo = $this->grupo($this->docente());

        // El Administrador tiene todos los permisos (D-8): se prueba con el Auxiliar.
        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }

    public function test_the_administrator_does_not_have_the_permission_of_the_teacher_groups(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->actingAs($this->administrador())
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }

    // --- Plantilla ---

    public function test_the_group_template_is_an_xlsx_with_the_four_columns(): void
    {
        $respuesta = $this->actingAs($this->cuentaDe($this->docente()))
            ->get('/api/inscritos/plantilla?alcance=grupo')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="plantilla_inscritos.xlsx"');

        $this->assertSame(
            [['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos']],
            $this->filasDe((string) $respuesta->getContent()),
        );
    }

    public function test_the_faculty_template_adds_subject_group_and_career(): void
    {
        $respuesta = $this->actingAs($this->administrador())
            ->get('/api/inscritos/plantilla?alcance=facultad')
            ->assertOk();

        $this->assertSame(
            [[
                'codigo_universitario',
                'documento_identidad',
                'nombres',
                'apellidos',
                'codigo_asignatura',
                'grupo',
                'carrera',
            ]],
            $this->filasDe((string) $respuesta->getContent()),
        );
    }

    public function test_the_template_defaults_to_the_group_one_and_validates_the_scope(): void
    {
        $administrador = $this->administrador();

        $respuesta = $this->actingAs($administrador)->get('/api/inscritos/plantilla')->assertOk();

        $this->assertCount(4, $this->filasDe((string) $respuesta->getContent())[0]);

        $this->actingAs($administrador)
            ->getJson('/api/inscritos/plantilla?alcance=universidad')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alcance' => 'El alcance debe ser grupo o facultad.']);
    }

    public function test_the_template_can_be_loaded_back_once_filled(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $plantilla = $this->actingAs($this->cuentaDe($docente))->get('/api/inscritos/plantilla')->getContent();

        $ruta = (string) tempnam(sys_get_temp_dir(), 'plantilla');
        file_put_contents($ruta, $plantilla);

        $libro = IOFactory::createReader('Xlsx')->load($ruta);
        $libro->getActiveSheet()->fromArray([['0012345', '0792819', 'Kevin René', 'Alvarado Claros']], null, 'A2');
        IOFactory::createWriter($libro, 'Xlsx')->save($ruta);

        $this->cargarLista(
            $docente,
            $grupo,
            new UploadedFile($ruta, 'plantilla_inscritos.xlsx', null, null, true),
        )->assertOk()->assertJsonPath('resumen.nuevos', 1);

        // Las columnas son de texto: no se pierden los ceros a la izquierda.
        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '0012345',
            'documento_identidad' => '0792819',
        ]);
    }

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->getJson('/api/inscritos/plantilla')->assertUnauthorized();
    }

    public function test_a_role_without_any_of_the_two_permissions_cannot_download_the_template(): void
    {
        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson('/api/inscritos/plantilla')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'padron_estudiantes|mis_grupos');
    }

    // --- Contrato publico ---

    public function test_the_public_contract_returns_the_students_of_the_groups_once_each(): void
    {
        $uno = $this->grupo();
        $dos = $this->grupo();
        $otro = $this->grupo();

        $deUno = $this->estudiantesInscritos($uno, 2);
        $deDos = $this->estudiantesInscritos($dos, 1);
        $this->estudiantesInscritos($otro, 3);

        // El mismo estudiante en los dos grupos sale una sola vez.
        Inscripcion::create([
            'estudiante_id' => $deUno[0]->id,
            'grupo_id' => $dos->id,
            'via' => OrigenEstudiante::Docente,
        ]);

        $deUno[1]->update(['activo' => false]);

        $inscritos = $this->app->make(InscritosDeExamenGateway::class);
        $estudiantes = $inscritos->estudiantesDe([$uno->id, $dos->id, $dos->id]);

        $this->assertSame(
            [$deUno[0]->id, $deDos[0]->id],
            array_map(static fn (EstudianteData $estudiante): int => $estudiante->id, $estudiantes),
        );
        $this->assertSame($deUno[0]->codigo_universitario, $estudiantes[0]->codigoUniversitario);
        $this->assertSame('Apellido 1, Nombre 1', $estudiantes[0]->nombreCompleto());
        $this->assertSame([], $inscritos->estudiantesDe([]));
    }

    /**
     * @return list<list<string>>
     */
    private function filasDe(string $contenido): array
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'plantilla');
        file_put_contents($ruta, $contenido);

        $filas = [];

        foreach (IOFactory::createReader('Xlsx')->load($ruta)->getActiveSheet()->toArray() as $fila) {
            $valores = array_values(array_map(
                static fn (mixed $celda): string => is_scalar($celda) ? (string) $celda : '',
                $fila,
            ));

            // Las filas en blanco son las que la plantilla deja con
            // formato de texto para llenar.
            if (implode('', $valores) !== '') {
                $filas[] = $valores;
            }
        }

        return $filas;
    }
}
