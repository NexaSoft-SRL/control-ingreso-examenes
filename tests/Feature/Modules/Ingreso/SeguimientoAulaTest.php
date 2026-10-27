<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Administracion\Domain\Models\User;
use Database\Seeders\DemoSeeder;
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

        $ingresos = [];
        foreach (array_slice($estudiantes, 0, 26) as $estudiante) {
            $ingresos[] = $this->ingresar($examen, $estudiante, $aulaA);
        }

        foreach ($ingresos as $indice => $ingresoId) {
            DB::table('ingresos')
                ->where('id', $ingresoId)
                ->update([
                    'registrado_en' => now()
                        ->subMinutes(count($ingresos) - $indice)
                        ->format('Y-m-d H:i:s'),
                ]);
        }

        $this->ingresar($examen, $estudiantes[26], $aulaB);

        $this->actingAs($docenteCuenta)
            ->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?estado=faltan&buscar=alvaro%%20alvarez',
            $examen->id,
            $aulaA->id,
        ))
            ->assertOk()
            ->assertJsonPath('data.cifras.ingresaron', 26)
            ->assertJsonPath('data.cifras.faltan', 1)
            ->assertJsonPath('data.cifras.asignados', 28)
            ->assertJsonPath('data.pagina.total', 1)
            ->assertJsonPath('data.estudiantes.0.nombres', 'Álvaro')
            ->assertJsonPath('data.estudiantes.0.apellidos', 'Álvarez')
            ->assertJsonPath('data.estudiantes.0.grupos.0', 'Grupo '.$grupo->codigo);

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?estado=ingresaron&pagina=1',
            $examen->id,
            $aulaA->id,
        ))
            ->assertOk()
            ->assertJsonPath('data.estudiantes.0.estudiante_id', $estudiantes[25]->id);

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
            ->assertJsonPath('errors.buscar.0', 'La búsqueda debe ser un texto.');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?pagina=abc',
            $examen->id,
            $aulaA->id,
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.pagina.0', 'La página debe ser un número entero.');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?estado%%5B%%5D=x',
            $examen->id,
            $aulaA->id,
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'El estado no es válido.');

        // El nombre tal como lo muestra la lista: «Apellidos, Nombres».
        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?estado=faltan&buscar=%s',
            $examen->id,
            $aulaA->id,
            rawurlencode('  Álvarez,  Álvaro '),
        ))
            ->assertOk()
            ->assertJsonPath('data.pagina.total', 1)
            ->assertJsonPath('data.estudiantes.0.apellidos', 'Álvarez');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d?estado=ingresaron&pagina=2',
            $examen->id,
            $aulaA->id,
        ))
            ->assertOk()
            ->assertJsonPath('data.pagina.por_pagina', 25)
            ->assertJsonPath('data.pagina.total', 26)
            ->assertJsonPath('data.pagina.actual', 2)
            ->assertJsonCount(1, 'data.estudiantes')
            ->assertJsonPath('data.estudiantes.0.estudiante_id', $estudiantes[0]->id);
    }

    public function test_auxiliar_solo_ve_aulas_asignadas_y_las_incidencias_de_su_aula(): void
    {
        $docenteCuenta = $this->usuarioConPermisos(['seguimiento_vivo']);
        $docente = $this->docenteConCuenta($docenteCuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('HU21-ASIGNADA');
        $otraAula = $this->aula('HU21-OTRA');
        $examen = $this->examen($docente, [$grupo], [$aula, $otraAula]);
        $auxiliar = $this->usuarioConRol('Auxiliar');

        DB::table('examen_aula_personal')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'usuario_id' => $auxiliar->id,
            'rol' => 'apoyo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $incidenciaId = DB::table('incidencias')->insertGetId([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'reportada_por' => $auxiliar->id,
            'tipo' => 'OTRA',
            'origen' => 'PERSONA',
            'detalle' => 'Incidencia para atender',
            'ocurrida_en' => now(),
        ]);

        $otraIncidenciaId = DB::table('incidencias')->insertGetId([
            'examen_id' => $examen->id,
            'aula_id' => $otraAula->id,
            'reportada_por' => $docenteCuenta->id,
            'tipo' => 'MATERIAL_NO_PERMITIDO',
            'origen' => 'PERSONA',
            'detalle' => 'Incidencia de otra aula',
            'ocurrida_en' => now(),
        ]);

        // El personal del aula ve las de su aula, no las de otra.
        $this->actingAs($auxiliar)
            ->getJson("/api/examenes/{$examen->id}/incidencias?aula={$aula->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $incidenciaId)
            ->assertJsonPath('meta.sin_atender', 1);

        $this->getJson("/api/examenes/{$examen->id}/incidencias?aula={$otraAula->id}")
            ->assertForbidden();

        // Solo el docente del examen las atiende.
        $this->postJson("/api/incidencias/{$incidenciaId}/atencion")
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente del examen.');

        $this->actingAs($docenteCuenta)
            ->postJson("/api/incidencias/{$incidenciaId}/atencion")
            ->assertOk()
            ->assertJsonPath('data.atendida', true);

        $this->postJson("/api/incidencias/{$incidenciaId}/atencion")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La incidencia ya estaba atendida.');

        $this->assertDatabaseHas('incidencias', [
            'id' => $incidenciaId,
            'atendida_por' => $docenteCuenta->id,
        ]);
        $this->assertDatabaseHas('incidencias', [
            'id' => $otraIncidenciaId,
            'atendida_por' => null,
        ]);

        $this->assertDatabaseHas('examen_aula_personal', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'usuario_id' => $auxiliar->id,
            'rol' => 'apoyo',
        ]);
    }

    public function test_solo_muestra_las_aulas_de_los_examenes_de_hoy(): void
    {
        $docenteCuenta = $this->usuarioConPermisos(['seguimiento_vivo', 'punto_control']);
        $docente = $this->docenteConCuenta($docenteCuenta);
        $grupo = $this->grupo($docente);
        $aulaHoy = $this->aula('HU21-HOY');
        $aulaFutura = $this->aula('HU21-FUTURA');
        $hoy = $this->examen($docente, [$grupo], [$aulaHoy]);
        $futuro = $this->examen($docente, [$grupo], [$aulaFutura], [
            'fecha' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($docenteCuenta)
            ->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.examen_id', $hoy->id)
            ->assertJsonPath('data.0.aula_id', $aulaHoy->id);

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d',
            $futuro->id,
            $aulaFutura->id,
        ))
            ->assertForbidden()
            ->assertJsonPath('message', 'Disponible solo el día del examen.');

        // El docente del examen opera la puerta del aula y el ingreso está abierto.
        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d',
            $hoy->id,
            $aulaHoy->id,
        ))
            ->assertOk()
            ->assertJsonPath('data.puede_operar', true)
            ->assertJsonPath('data.abierto', true)
            ->assertJsonPath('data.puede_atender', true);

        $ajeno = $this->usuarioConRol('Auxiliar');
        $this->actingAs($ajeno)
            ->getJson(sprintf(
                '/api/seguimiento/examenes/%d/aulas/%d',
                $hoy->id,
                $aulaHoy->id,
            ))
            ->assertForbidden()
            ->assertJsonPath('message', 'Aula fuera de su asignación.');
    }

    public function test_el_auxiliar_de_la_demo_ve_el_aula_que_tiene_a_cargo(): void
    {
        $this->freezeTime();
        $this->seed(DemoSeeder::class);

        $auxiliar = User::query()->where('usuario', 'diego.mamani')->firstOrFail();
        $asignacion = DB::table('examen_aula_personal')
            ->where('usuario_id', $auxiliar->id);
        $examenId = (clone $asignacion)->value('examen_id');
        $aulaId = (clone $asignacion)->value('aula_id');

        $this->assertIsInt($examenId);
        $this->assertIsInt($aulaId);
        $this->assertSame('691E', DB::table('aulas')->where('id', $aulaId)->value('nombre'));

        $this->actingAs($auxiliar)
            ->getJson('/api/seguimiento/aulas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.aula', '691E');

        $this->getJson(sprintf(
            '/api/seguimiento/examenes/%d/aulas/%d',
            $examenId,
            $aulaId,
        ))
            ->assertOk()
            ->assertJsonPath('data.aula.nombre', '691E')
            ->assertJsonPath('data.puede_operar', true);
    }
}
