<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-22: el panel en vivo del docente. Quien controla cada aula (con su
 * presencia en la puerta), los accesos con codigo y los movimientos.
 */
final class ControlDelExamenTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $docente;

    private User $auxiliar;

    private Examen $examen;

    private Aula $aula;

    private Aula $otra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docente = $this->usuarioConRol('Docente', ['nombre' => 'Blanco Coca Leticia']);
        $docente = $this->docenteConCuenta($this->docente);
        $grupo = $this->grupo($docente);
        $this->aula = $this->aula('691A');
        $this->otra = $this->aula('691B');
        $this->examen = $this->examen($docente, [$grupo], [$this->aula, $this->otra]);
        $this->auxiliar = $this->usuarioConRol('Auxiliar', ['nombre' => 'Mamani Quispe Diego']);
        $this->personal($this->aula, $this->auxiliar, 'a_cargo');
    }

    public function test_dice_quien_controla_cada_aula_y_si_esta_en_la_puerta(): void
    {
        // El auxiliar usa la puerta: queda su presencia.
        $this->actingAs($this->auxiliar)
            ->getJson("/api/aulas/{$this->aula->id}/punto-control")
            ->assertOk();
        $this->assertDatabaseHas('presencias_puerta', [
            'aula_id' => $this->aula->id,
            'usuario_id' => $this->auxiliar->id,
        ]);

        AccesoCodigoAula::create([
            'examen_id' => $this->examen->id,
            'aula_id' => $this->otra->id,
            'codigo' => '123456',
            'nombre_colaborador' => 'Rocha Ana',
            'token' => 'token-pendiente',
            'ultimo_acceso_en' => now(),
            'estado' => 'PENDIENTE',
        ]);

        $respuesta = $this->actingAs($this->docente)
            ->getJson("/api/examenes/{$this->examen->id}/control")
            ->assertOk()
            ->assertJsonPath('meta.pendientes', 1)
            ->assertJsonPath('meta.minutos_en_linea', 2)
            ->assertJsonPath('data.0.nombre', '691A')
            ->assertJsonPath('data.0.personal.0.nombre', 'Mamani Quispe Diego')
            ->assertJsonPath('data.0.personal.0.rol', 'a_cargo')
            ->assertJsonPath('data.1.accesos.0.nombre', 'Rocha Ana')
            ->assertJsonPath('data.1.accesos.0.estado', 'pendiente');

        self::assertNotNull($respuesta->json('data.0.personal.0.visto_en'));

        // Solo el docente del examen.
        $this->actingAs($this->auxiliar)
            ->getJson("/api/examenes/{$this->examen->id}/control")
            ->assertForbidden();
    }

    public function test_mueve_a_una_persona_y_el_aula_queda_a_cargo_de_quien_mueve(): void
    {
        $this->actingAs($this->docente)
            ->postJson("/api/examenes/{$this->examen->id}/movimientos", [
                'de_aula_id' => $this->aula->id,
                'a_aula_id' => $this->otra->id,
                'usuario_id' => $this->auxiliar->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Cambio de aula registrado.');

        $this->assertDatabaseHas('examen_aula_personal', [
            'aula_id' => $this->aula->id,
            'usuario_id' => $this->docente->id,
            'rol' => 'a_cargo',
        ]);
        $this->assertDatabaseHas('examen_aula_personal', [
            'aula_id' => $this->otra->id,
            'usuario_id' => $this->auxiliar->id,
            'rol' => 'apoyo',
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.mover_personal']);

        $this->postJson("/api/examenes/{$this->examen->id}/movimientos", [
            'de_aula_id' => $this->aula->id,
            'a_aula_id' => $this->otra->id,
            'usuario_id' => $this->auxiliar->id,
        ])->assertUnprocessable()->assertJsonPath('errors.usuario_id.0', 'No atiende esa aula.');
    }

    public function test_mueve_a_los_estudiantes_que_no_entraron(): void
    {
        $grupo = $this->grupo($this->docenteConCuenta());
        DB::table('examen_grupo')->insert(['examen_id' => $this->examen->id, 'grupo_id' => $grupo->id]);
        $estudiantes = $this->estudiantesInscritos($grupo, 3);
        $this->habilitar($this->examen, $estudiantes, $this->aula);
        $this->ingresar($this->examen, $estudiantes[0], $this->aula);

        $this->actingAs($this->docente)
            ->postJson("/api/examenes/{$this->examen->id}/movimientos", [
                'de_aula_id' => $this->aula->id,
                'a_aula_id' => $this->otra->id,
            ])
            ->assertOk()
            ->assertJsonPath('movidos', 2)
            ->assertJsonPath('message', '2 estudiantes cambiaron de aula.');

        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $estudiantes[0]->id, 'aula_id' => $this->aula->id]);
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $estudiantes[1]->id, 'aula_id' => $this->otra->id]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'habilitacion.mover_aula']);

        $ajena = $this->aula('900');
        $this->postJson("/api/examenes/{$this->examen->id}/movimientos", [
            'de_aula_id' => $this->aula->id,
            'a_aula_id' => $ajena->id,
        ])->assertUnprocessable()->assertJsonPath('errors.a_aula_id.0', 'El aula no es de este examen.');
    }

    public function test_en_curso_da_los_cruces_de_aula_y_el_ritmo_de_llegada(): void
    {
        $grupo = $this->grupo($this->docenteConCuenta());
        DB::table('examen_grupo')->insert(['examen_id' => $this->examen->id, 'grupo_id' => $grupo->id]);
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $this->habilitar($this->examen, $estudiantes, $this->aula);
        // Uno de 691A entra por 691B.
        $this->ingresar($this->examen, $estudiantes[0], $this->otra);
        $this->ingresar($this->examen, $estudiantes[1], $this->aula);

        $respuesta = $this->actingAs($this->docente)
            ->getJson("/api/en-curso/{$this->examen->id}")
            ->assertOk();

        $respuesta->assertJsonPath('data.aulas.0.nombre', '691A')
            ->assertJsonPath('data.aulas.0.en_otra', 1)
            ->assertJsonPath('data.aulas.0.de_otra', 0)
            ->assertJsonPath('data.aulas.1.nombre', '691B')
            ->assertJsonPath('data.aulas.1.de_otra', 1);

        // La lista del aula dice lo mismo.
        $this->getJson("/api/seguimiento/examenes/{$this->examen->id}/aulas/{$this->aula->id}")
            ->assertOk()
            ->assertJsonPath('data.cifras.en_otra_aula', 1)
            ->assertJsonPath('data.cifras.de_otra_aula', 0);

        $llegadas = (array) $respuesta->json('data.llegadas');
        self::assertSame(2, array_sum(array_column($llegadas, 'ingresos')));
    }

    private function personal(Aula $aula, User $usuario, string $rol): void
    {
        DB::table('examen_aula_personal')->insert([
            'examen_id' => $this->examen->id,
            'aula_id' => $aula->id,
            'usuario_id' => $usuario->id,
            'rol' => $rol,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
