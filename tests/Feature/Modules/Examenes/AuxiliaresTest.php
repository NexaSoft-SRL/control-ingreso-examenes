<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Examenes\Domain\Models\DocenteAuxiliar;
use App\Modules\Examenes\Domain\Models\ExamenAulaPersonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuxiliaresTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_docente_puede_listar_sus_auxiliares(): void
    {
        $docenteA = $this->docente('Docente Uno');
        $cuentaA = $this->cuenta($docenteA);

        $docenteB = $this->docente('Docente Dos');
        $cuentaB = $this->cuenta($docenteB);

        $aux1 = $this->usuarioConRol('Auxiliar', ['nombre' => 'Carlos Perez']);
        $aux2 = $this->usuarioConRol('Auxiliar', ['nombre' => 'Ana Morales']);
        $aux3 = $this->usuarioConRol('Auxiliar', ['nombre' => 'Zulma Quispe']);

        DocenteAuxiliar::create(['docente_id' => $cuentaA->id, 'auxiliar_id' => $aux1->id]);
        DocenteAuxiliar::create(['docente_id' => $cuentaA->id, 'auxiliar_id' => $aux2->id]);
        DocenteAuxiliar::create(['docente_id' => $cuentaB->id, 'auxiliar_id' => $aux3->id]);

        $this->actingAs($cuentaA)
            ->getJson('/api/auxiliares')
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 2)
            ->assertJsonPath('data.0.nombre', 'Ana Morales')
            ->assertJsonPath('data.1.nombre', 'Carlos Perez');
    }

    public function test_docente_puede_buscar_auxiliares_por_nombre_o_codigo(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);

        $this->usuarioConRol('Auxiliar', ['nombre' => 'Rodrigo Albarracin', 'usuario' => '202100100']);
        $this->usuarioConRol('Auxiliar', ['nombre' => 'Bernardo Mendez', 'usuario' => '202100200']);

        // Búsqueda con menos de 3 caracteres no activa búsqueda global
        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=ro')
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 0);

        // Búsqueda con >= 3 caracteres
        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=rod')
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 1)
            ->assertJsonPath('data.0.nombre', 'Rodrigo Albarracin');

        // Búsqueda por código
        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=2021002')
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 1)
            ->assertJsonPath('data.0.nombre', 'Bernardo Mendez');
    }

    public function test_docente_puede_agregar_auxiliar_existente_a_su_lista(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);

        $auxiliar = $this->usuarioConRol('Auxiliar', [
            'nombre' => 'Maria Lopez',
            'usuario' => '20219988',
            'correo' => '20219988@est.umss.edu',
        ]);

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', [
                'nombre_completo' => 'Maria Lopez',
                'codigo_estudiante' => '20219988',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Auxiliar ya registrado')
            ->assertJsonPath('data.id', $auxiliar->id);

        $this->assertDatabaseHas('docente_auxiliares', [
            'docente_id' => $cuenta->id,
            'auxiliar_id' => $auxiliar->id,
        ]);
    }

    public function test_docente_puede_crear_cuenta_de_auxiliar_si_no_existe(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', [
                'nombre_completo' => 'Javier Camacho',
                'codigo_estudiante' => '202211223',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Auxiliar agregado')
            ->assertJsonPath('data.nombre', 'Javier Camacho')
            ->assertJsonPath('data.codigo', '202211223');

        $this->assertDatabaseHas('usuarios', [
            'usuario' => '202211223',
            'correo' => '202211223@est.umss.edu',
            'nombre' => 'Javier Camacho',
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'auxiliar.agregar',
            'usuario_id' => $cuenta->id,
        ]);
    }

    public function test_no_permite_crear_auxiliar_si_codigo_pertenece_a_otro_rol(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);

        // Cuenta existente con rol Docente o Administrador
        $this->usuarioConRol('Docente', [
            'usuario' => '202055443',
            'correo' => '202055443@est.umss.edu',
        ]);

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', [
                'nombre_completo' => 'Otro Nombre',
                'codigo_estudiante' => '202055443',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_estudiante' => 'Ya existe']);
    }

    public function test_docente_puede_quitar_auxiliar_de_su_lista(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);

        $auxiliar = $this->usuarioConRol('Auxiliar', ['nombre' => 'Auxiliar Libre']);
        DocenteAuxiliar::create(['docente_id' => $cuenta->id, 'auxiliar_id' => $auxiliar->id]);

        $this->actingAs($cuenta)
            ->deleteJson("/api/auxiliares/{$auxiliar->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Auxiliar quitado');

        $this->assertDatabaseMissing('docente_auxiliares', [
            'docente_id' => $cuenta->id,
            'auxiliar_id' => $auxiliar->id,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'auxiliar.quitar',
            'usuario_id' => $cuenta->id,
        ]);
    }

    public function test_no_puede_quitar_auxiliar_con_examen_pendiente(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('A-101');

        $auxiliar = $this->usuarioConRol('Auxiliar', ['nombre' => 'Auxiliar Ocupado']);
        DocenteAuxiliar::create(['docente_id' => $cuenta->id, 'auxiliar_id' => $auxiliar->id]);

        // Examen para mañana
        $examen = $this->examen($docente, [$grupo], [$aula], [
            'fecha' => now()->addDays(2)->toDateString(),
        ]);

        ExamenAulaPersonal::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'usuario_id' => $auxiliar->id,
            'rol' => ExamenAulaPersonal::ROL_A_CARGO,
        ]);

        $this->actingAs($cuenta)
            ->deleteJson("/api/auxiliares/{$auxiliar->id}")
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'AUXILIAR_EN_EXAMEN_PENDIENTE');

        $this->assertDatabaseHas('docente_auxiliares', [
            'docente_id' => $cuenta->id,
            'auxiliar_id' => $auxiliar->id,
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_gestionar_auxiliares(): void
    {
        $cuentaSinPermiso = $this->usuarioConPermisos([]);

        $this->actingAs($cuentaSinPermiso)
            ->getJson('/api/auxiliares')
            ->assertForbidden();

        $this->actingAs($cuentaSinPermiso)
            ->postJson('/api/auxiliares', [
                'nombre_completo' => 'Prueba',
                'codigo_estudiante' => '123456',
            ])
            ->assertForbidden();
    }
}
