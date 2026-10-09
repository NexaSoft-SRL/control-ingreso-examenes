<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Estudiantes\Domain\Enums\EstadoConflicto;
use App\Modules\Estudiantes\Domain\Enums\ResolucionConflicto;
use App\Modules\Estudiantes\Domain\Models\ConflictoPadron;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Los conflictos entre una carga y el padron, y sus dos salidas (rutas 37
 * y 38).
 */
final class ConflictosTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private Docente $docenteDelGrupo;

    private Grupo $grupoDeLaCarga;

    public function test_the_list_shows_what_is_stored_and_what_the_load_brought(): void
    {
        $guardado = $this->estudiante(['nombres' => 'Carla', 'apellidos' => 'Rojas Lima']);

        DB::table('estudiantes')->where('id', $guardado->id)->update(['created_at' => '2026-08-05 22:00:00']);

        $conflicto = $this->conflicto('202104821,7928149,Carla,Rojas Lima');

        $this->actingAs($this->administrador())
            ->getJson('/api/estudiantes/conflictos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', [
                'id' => $conflicto->id,
                'codigo' => '202104821',
                'tipo' => 'Documento distinto',
                'estado' => 'pendiente',
                'guardado' => [
                    'nombre' => 'Rojas Lima, Carla',
                    'documento' => '7928194',
                    'por' => 'Administración · 5 ago 2026',
                ],
                'nuevo' => [
                    'nombre' => 'Rojas Lima, Carla',
                    'documento' => '7928149',
                    'por' => 'Blanco Coca Leticia · Introducción a la Programación, grupo 2',
                ],
                'inscripcion_en_espera' => true,
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.por_pagina', 5)
            ->assertJsonPath('meta.conteos', ['pendientes' => 1, 'resueltos' => 0]);

        $this->assertSame($guardado->id, $conflicto->estudiante_id);
    }

    public function test_a_student_brought_by_a_teacher_shows_who_loaded_it_first(): void
    {
        $this->prepararGrupo();

        $otro = $this->docenteConCuenta($this->usuarioConRol('Docente'), 'Salazar Serrudo Carla');
        $primero = $this->grupo($otro, $this->asignatura('Álgebra I'), '8');

        $this->cargarLista($otro, $primero, ['202104821,7712045,José,Mamani Flores'])->assertOk();
        $this->cargarLista($this->docenteDelGrupo, $this->grupoDeLaCarga, ['202104821,7712045,José Luis,Mamani Flores'])
            ->assertOk()
            ->assertJsonPath('resumen.conflictos', 1);

        $this->actingAs($this->administrador())
            ->getJson('/api/estudiantes/conflictos')
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'Nombre distinto')
            ->assertJsonPath('data.0.guardado.por', 'Salazar Serrudo Carla · Álgebra I, grupo 8')
            ->assertJsonPath('data.0.nuevo.nombre', 'Mamani Flores, José Luis');
    }

    public function test_the_list_filters_by_state_and_is_paginated(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->estudiante([
                'codigo_universitario' => (string) (202100000 + $i),
                'documento_identidad' => (string) (7000000 + $i),
            ]);
        }

        $lineas = [];

        for ($i = 1; $i <= 7; $i++) {
            $lineas[] = sprintf('%d,%d,Kevin René,Alvarado Claros', 202100000 + $i, 8000000 + $i);
        }

        $this->prepararGrupo();
        $this->cargarLista($this->docenteDelGrupo, $this->grupoDeLaCarga, $lineas)->assertOk();

        $administrador = $this->administrador();
        $primero = ConflictoPadron::query()->orderBy('id')->firstOrFail();

        $this->actingAs($administrador)
            ->postJson("/api/estudiantes/conflictos/{$primero->id}/resolucion", ['resolucion' => 'MANTENER_PADRON'])
            ->assertOk();

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes/conflictos')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.conteos', ['pendientes' => 6, 'resueltos' => 1]);

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes/conflictos?estado=pendiente&pagina=2')
            ->assertJsonCount(1, 'data');

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes/conflictos?estado=resuelto')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $primero->id)
            ->assertJsonPath('data.0.estado', 'resuelto')
            ->assertJsonPath('data.0.inscripcion_en_espera', false);

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes/conflictos?estado=todos&por_pagina=50')
            ->assertJsonCount(7, 'data');
    }

    public function test_the_list_validates_its_parameters(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/estudiantes/conflictos?estado=otro')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado' => 'El estado debe ser pendiente, resuelto o todos.']);
    }

    public function test_keeping_the_padron_leaves_the_student_and_creates_the_enrollment(): void
    {
        $guardado = $this->estudiante();
        $conflicto = $this->conflicto('202104821,7928149,Kevin,Alvarado');
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->postJson("/api/estudiantes/conflictos/{$conflicto->id}/resolucion", ['resolucion' => 'MANTENER_PADRON'])
            ->assertOk()
            ->assertExactJson(['message' => '202104821 sin cambios.']);

        $this->assertDatabaseHas('estudiantes', [
            'id' => $guardado->id,
            'documento_identidad' => '7928194',
            'nombres' => 'Kevin René',
            'apellidos' => 'Alvarado Claros',
        ]);

        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => $guardado->id,
            'grupo_id' => $this->grupoDeLaCarga->id,
            'via' => 'DOCENTE',
            'cargada_por' => $this->docenteDelGrupo->user_id,
            'carga_id' => $conflicto->carga_id,
        ]);

        $resuelto = $conflicto->fresh();

        $this->assertNotNull($resuelto);
        $this->assertSame(EstadoConflicto::Resuelto, $resuelto->estado);
        $this->assertSame(ResolucionConflicto::MantenerPadron, $resuelto->resolucion);
        $this->assertSame($administrador->id, $resuelto->resuelto_por);
        $this->assertNotNull($resuelto->resuelto_en);
    }

    public function test_using_the_load_replaces_document_and_name_verifies_and_creates_the_enrollment(): void
    {
        $guardado = $this->estudiante(['verificado' => false]);
        $conflicto = $this->conflicto('202104821,7928149,Kevin,Alvarado C.');

        $this->actingAs($this->administrador())
            ->postJson("/api/estudiantes/conflictos/{$conflicto->id}/resolucion", ['resolucion' => 'USAR_CARGA'])
            ->assertOk()
            ->assertExactJson(['message' => '202104821 actualizado.']);

        $this->assertDatabaseHas('estudiantes', [
            'id' => $guardado->id,
            'codigo_universitario' => '202104821',
            'documento_identidad' => '7928149',
            'nombres' => 'Kevin',
            'apellidos' => 'Alvarado C.',
            'verificado' => true,
        ]);

        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => $guardado->id,
            'grupo_id' => $this->grupoDeLaCarga->id,
        ]);

        $this->assertDatabaseHas('conflictos_padron', [
            'id' => $conflicto->id,
            'estado' => 'RESUELTO',
            'resolucion' => 'USAR_CARGA',
        ]);
    }

    public function test_a_conflict_is_resolved_only_once(): void
    {
        $this->estudiante();
        $conflicto = $this->conflicto('202104821,7928149,Kevin,Alvarado');
        $administrador = $this->administrador();
        $ruta = "/api/estudiantes/conflictos/{$conflicto->id}/resolucion";

        $this->actingAs($administrador)->postJson($ruta, ['resolucion' => 'MANTENER_PADRON'])->assertOk();

        $this->actingAs($administrador)
            ->postJson($ruta, ['resolucion' => 'USAR_CARGA'])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'El conflicto ya fue resuelto.',
                'codigo' => 'CONFLICTO_YA_RESUELTO',
            ]);

        $this->assertDatabaseHas('estudiantes', ['documento_identidad' => '7928194']);
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_the_load_cannot_be_used_if_its_document_belongs_to_another_student(): void
    {
        $this->estudiante();
        $conflicto = $this->conflicto('202104821,7928149,Kevin René,Alvarado Claros');

        // Despues de la carga otro estudiante entra con ese documento.
        $this->estudiante(['codigo_universitario' => '201901349', 'documento_identidad' => '7928149']);

        $this->actingAs($this->administrador())
            ->postJson("/api/estudiantes/conflictos/{$conflicto->id}/resolucion", ['resolucion' => 'USAR_CARGA'])
            ->assertConflict()
            ->assertJsonPath('codigo', 'DOCUMENTO_EN_USO');

        $this->assertDatabaseHas('conflictos_padron', ['id' => $conflicto->id, 'estado' => 'PENDIENTE']);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_the_resolution_is_validated(): void
    {
        $this->estudiante();
        $conflicto = $this->conflicto('202104821,7928149,Kevin,Alvarado');
        $administrador = $this->administrador();
        $ruta = "/api/estudiantes/conflictos/{$conflicto->id}/resolucion";

        $this->actingAs($administrador)
            ->postJson($ruta, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resolucion' => 'La resolución es obligatoria.']);

        $this->actingAs($administrador)
            ->postJson($ruta, ['resolucion' => 'BORRAR'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resolucion']);

        $this->assertDatabaseHas('conflictos_padron', ['id' => $conflicto->id, 'estado' => 'PENDIENTE']);
    }

    public function test_a_conflict_that_does_not_exist_is_not_found(): void
    {
        $this->actingAs($this->administrador())
            ->postJson('/api/estudiantes/conflictos/999999/resolucion', ['resolucion' => 'USAR_CARGA'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Conflicto no encontrado.');
    }

    public function test_a_guest_cannot_see_nor_resolve_conflicts(): void
    {
        $this->getJson('/api/estudiantes/conflictos')->assertUnauthorized();
        $this->postJson('/api/estudiantes/conflictos/1/resolucion', ['resolucion' => 'USAR_CARGA'])
            ->assertUnauthorized();
    }

    public function test_a_teacher_cannot_see_nor_resolve_conflicts(): void
    {
        $this->estudiante();
        $conflicto = $this->conflicto('202104821,7928149,Kevin,Alvarado');
        $cuenta = $this->cuentaDe($this->docenteDelGrupo);

        $this->actingAs($cuenta)
            ->getJson('/api/estudiantes/conflictos')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'padron_estudiantes');

        $this->actingAs($cuenta)
            ->postJson("/api/estudiantes/conflictos/{$conflicto->id}/resolucion", ['resolucion' => 'USAR_CARGA'])
            ->assertForbidden();

        $this->assertDatabaseHas('conflictos_padron', ['id' => $conflicto->id, 'estado' => 'PENDIENTE']);
    }

    public function test_the_resolution_is_recorded_in_the_audit_log(): void
    {
        $this->estudiante();
        $conflicto = $this->conflicto('202104821,7928149,Kevin,Alvarado');
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->postJson("/api/estudiantes/conflictos/{$conflicto->id}/resolucion", ['resolucion' => 'USAR_CARGA'])
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'padron.resolver_conflicto',
            'tabla_afectada' => 'conflictos_padron',
            'registro_id' => $conflicto->id,
            'descripcion' => 'Conflicto de 202104821 resuelto: se usa la carga nueva.',
        ]);
    }

    public function test_resolving_does_not_duplicate_an_enrollment_that_already_exists(): void
    {
        $guardado = $this->estudiante();
        $conflicto = $this->conflicto('202104821,7928149,Kevin René,Alvarado Claros');

        DB::table('inscripciones')->insert([
            'estudiante_id' => $guardado->id,
            'grupo_id' => $this->grupoDeLaCarga->id,
            'via' => 'ADMINISTRACION',
        ]);

        $this->actingAs($this->administrador())
            ->postJson("/api/estudiantes/conflictos/{$conflicto->id}/resolucion", ['resolucion' => 'MANTENER_PADRON'])
            ->assertOk();

        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertSame(1, Estudiante::query()->count());
    }

    private function prepararGrupo(): void
    {
        $this->docenteDelGrupo = $this->docenteConCuenta($this->usuarioConRol('Docente'), 'Blanco Coca Leticia');
        $this->grupoDeLaCarga = $this->grupo(
            $this->docenteDelGrupo,
            $this->asignatura('Introducción a la Programación'),
            '2',
        );
    }

    /**
     * Abre un conflicto como se abren de verdad: con la carga de un
     * docente que no coincide con el estudiante guardado.
     */
    private function conflicto(string $linea): ConflictoPadron
    {
        $this->prepararGrupo();

        $this->cargarLista($this->docenteDelGrupo, $this->grupoDeLaCarga, [$linea])
            ->assertOk()
            ->assertJsonPath('resumen.conflictos', 1);

        return ConflictoPadron::query()->latest('id')->firstOrFail();
    }
}
