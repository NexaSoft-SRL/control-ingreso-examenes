<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * «Reporte de mis estudiantes»: la descarga de la lista de inscritos de un
 * grupo del docente como hoja de calculo (HU-14, criterio 13).
 */
final class DescargaDeListaTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_the_teacher_downloads_the_list_of_its_group_as_an_xlsx(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación', '2010010'), '2');
        $deAdministracion = $this->estudiantesInscritos($grupo, 1)[0];

        // Otro grupo del mismo docente: sus inscritos no entran.
        $this->estudiantesInscritos($this->grupo($docente), 2);

        $this->cargarLista($docente, $grupo, ['00901349,06492819,Mariana,Aguilar Cossío'])->assertOk();

        $respuesta = $this->actingAs($this->cuentaDe($docente))
            ->get("/api/docente/grupos/{$grupo->id}/inscritos/descarga")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="estudiantes_2010010_g2.xlsx"');

        $this->assertSame(
            [
                ['Código', 'Estudiante', 'Documento', 'Correo institucional', 'Origen'],
                // Los ceros a la izquierda se conservan.
                ['00901349', 'Aguilar Cossío, Mariana', '06492819', '00901349@est.umss.edu', 'Docente'],
                [
                    $deAdministracion->codigo_universitario,
                    'Apellido 1, Nombre 1',
                    (string) $deAdministracion->documento_identidad,
                    $deAdministracion->codigo_universitario.'@est.umss.edu',
                    'Administración',
                ],
            ],
            $this->filasDe((string) $respuesta->getContent()),
        );
    }

    public function test_the_file_name_only_keeps_safe_characters(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura('Taller', 'TIS/2 "x"'), '1 A');
        $this->estudiantesInscritos($grupo, 1);

        $this->actingAs($this->cuentaDe($docente))
            ->get("/api/docente/grupos/{$grupo->id}/inscritos/descarga")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="estudiantes_TIS-2-x-_g1-A.xlsx"');
    }

    public function test_a_group_without_list_has_nothing_to_download(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos/descarga")
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'El grupo no tiene lista cargada.',
                'codigo' => 'SIN_LISTA',
            ]);
    }

    public function test_the_list_of_a_closed_period_can_still_be_downloaded(): void
    {
        $docente = $this->docente();

        $cerrado = Periodo::create([
            'codigo' => '1/2020',
            'anio' => 2020,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2020-02-01',
            'fecha_fin' => '2020-07-01',
        ]);

        $grupo = $this->grupo($docente, null, null, $cerrado);
        $this->estudiantesInscritos($grupo, 1);

        $this->actingAs($this->cuentaDe($docente))
            ->get("/api/docente/grupos/{$grupo->id}/inscritos/descarga")
            ->assertOk();
    }

    public function test_a_teacher_cannot_download_the_list_of_a_group_of_another_teacher(): void
    {
        $grupo = $this->grupo($this->docente());
        $this->estudiantesInscritos($grupo, 1);

        $this->actingAs($this->cuentaDe($this->docente()))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos/descarga")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
    }

    public function test_a_group_that_does_not_exist_is_not_found(): void
    {
        $this->actingAs($this->cuentaDe($this->docente()))
            ->getJson('/api/docente/grupos/999999/inscritos/descarga')
            ->assertNotFound()
            ->assertJsonPath('message', 'Grupo no encontrado.');
    }

    public function test_a_guest_cannot_download_the_list(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->getJson("/api/docente/grupos/{$grupo->id}/inscritos/descarga")->assertUnauthorized();
    }

    public function test_a_role_without_the_permission_cannot_download_the_list(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos/descarga")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }

    /**
     * @return list<list<string>>
     */
    private function filasDe(string $contenido): array
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'lista');
        file_put_contents($ruta, $contenido);

        $filas = [];

        foreach (IOFactory::createReader('Xlsx')->load($ruta)->getActiveSheet()->toArray() as $fila) {
            $filas[] = array_values(array_map(
                static fn (mixed $celda): string => is_scalar($celda) ? (string) $celda : '',
                $fila,
            ));
        }

        unlink($ruta);

        return $filas;
    }
}
