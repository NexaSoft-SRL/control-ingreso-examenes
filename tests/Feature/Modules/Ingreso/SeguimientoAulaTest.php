<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class SeguimientoAulaTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_protege_la_pantalla_y_solo_muestra_aulas_autorizadas(): void
    {
        $this->getJson('/api/seguimiento/aulas')->assertUnauthorized();

        $sinPermiso = $this->usuarioConPermisos([]);
        $this->actingAs($sinPermiso)
            ->getJson('/api/seguimiento/aulas')
            ->assertForbidden();

        $docenteCuenta = $this->usuarioConPermisos(['seguimiento_vivo']);
        $docente = $this->docenteConCuenta($docenteCuenta);
        $grupo = $this->grupo($docente);
        $aulaA = $this->aula('HU21-A');
        $aulaB = $this->aula('HU21-B');
        $examen = $this->examen($docente, [$grupo], [$aulaA, $aulaB]);
        $estudiantes = $this->estudiantesInscritos($grupo, 28);
        $this->habilitar($examen, $estudiantes, $aulaA);

        DB::table('estudiantes')
            ->where('id', $estudiantes[27]->id)
            ->update(['nombres' => 'Álvaro', 'apellidos' => 'Álvarez']);

        foreach (array_slice($estudiantes, 0, 26) as $estudiante) {
            $this->ingresar($examen, $estudiante, $aulaA);
        }

        $this->ingresar($examen, $estudiantes[26], $aulaB);

        $this->actingAs($docenteCuenta)
            ->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?estado=faltan&buscar=alvarez',
            $examen->id,
            $aulaA->id,
        ))
            ->assertOk()
            ->assertJsonPath('data.cifras.ingresaron', 26)
            ->assertJsonPath('data.cifras.faltan', 1)
            ->assertJsonPath('data.cifras.asignados', 28)
            ->assertJsonPath('data.pagina.total', 1)
            ->assertJsonPath('data.estudiantes.0.nombres', 'Álvaro')
            ->assertJsonPath('data.estudiantes.0.apellidos', 'Álvarez');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?buscar=',
            $examen->id,
            $aulaA->id,
        ))->assertOk();

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?buscar%%5B%%5D=alvarez',
            $examen->id,
            $aulaA->id,
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('buscar');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?estado=ingresaron&pagina=2',
            $examen->id,
            $aulaA->id,
        ))
            ->assertOk()
            ->assertJsonPath('data.pagina.por_pagina', 25)
            ->assertJsonPath('data.pagina.total', 26)
            ->assertJsonPath('data.pagina.actual', 2)
            ->assertJsonCount(1, 'data.estudiantes');
    }

    public function test_docente_asigna_auxiliar_y_es_el_unico_que_atiende_incidencias(): void
    {
        $docenteCuenta = $this->usuarioConPermisos(['seguimiento_vivo']);
        $docente = $this->docenteConCuenta($docenteCuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('HU21-ASIGNADA');
        $otraAula = $this->aula('HU21-OTRA');
        $examen = $this->examen($docente, [$grupo], [$aula, $otraAula]);
        $auxiliar = $this->usuarioConRol('Auxiliar');

        $this->actingAs($auxiliar)
            ->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->actingAs($docenteCuenta)
            ->postJson("/api/seguimiento/examenes/{$examen->id}/asignaciones", [
                'usuario_id' => $auxiliar->id,
                'aula_id' => $aula->id,
            ])
            ->assertCreated();

        $this->actingAs($auxiliar)
            ->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.aula_id', $aula->id);

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d',
            $examen->id,
            $otraAula->id,
        ))->assertForbidden();

        $incidenciaId = DB::table('incidencias_aula')->insertGetId([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'reportada_por' => $auxiliar->id,
            'tipo' => 'OTRA_NOVEDAD',
            'detalle' => 'Incidencia para atender',
            'reportada_en' => now(),
        ]);

        $this->actingAs($auxiliar)
            ->patchJson("/api/seguimiento/incidencias/{$incidenciaId}/atendida")
            ->assertForbidden();

        $this->actingAs($docenteCuenta)
            ->patchJson("/api/seguimiento/incidencias/{$incidenciaId}/atendida")
            ->assertOk();

        $this->patchJson("/api/seguimiento/incidencias/{$incidenciaId}/atendida")
            ->assertStatus(409);

        $this->assertDatabaseHas('incidencias_aula', [
            'id' => $incidenciaId,
            'atendida_por' => $docenteCuenta->id,
        ]);

        $asignacionId = DB::table('asignaciones_seguimiento_aula')
            ->where('usuario_id', $auxiliar->id)
            ->where('examen_id', $examen->id)
            ->value('id');

        if (! is_numeric($asignacionId)) {
            self::fail('La asignación creada debe existir.');
        }

        $this->deleteJson(
            "/api/seguimiento/examenes/{$examen->id}/asignaciones/{$asignacionId}",
        )->assertOk();

        $this->actingAs($auxiliar)
            ->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
