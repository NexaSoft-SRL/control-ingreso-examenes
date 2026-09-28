<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\StudentFactory;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HU-07 pide auditar "quien hizo que y cuando". Estas pruebas fijan que
 * cada operacion del sprint 1 deje su rastro: la bitacora solo anotaba las
 * asignaturas.
 */
final class BitacoraCoberturaOperacionesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function operaciones(): array
    {
        /** @var list<string> $operaciones */
        $operaciones = DB::table('bitacora_operaciones')
            ->pluck('operacion')
            ->all();

        return $operaciones;
    }

    public function test_a_successful_login_is_recorded(): void
    {
        $usuario = UserFactory::new()->createOne([
            'correo' => 'auditoria@umss.edu.bo',
            'password' => Hash::make('Secreta12345'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'auditoria@umss.edu.bo',
            'password' => 'Secreta12345',
        ])->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $usuario->getKey(),
            'operacion' => 'sesion.iniciar',
        ]);
    }

    public function test_a_failed_login_is_recorded_without_a_user(): void
    {
        UserFactory::new()->createOne([
            'correo' => 'auditoria@umss.edu.bo',
            'password' => Hash::make('Secreta12345'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'auditoria@umss.edu.bo',
            'password' => 'clave-equivocada',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => null,
            'operacion' => 'sesion.fallida',
        ]);
    }

    public function test_the_logout_is_recorded_for_the_user_that_closed_it(): void
    {
        $usuario = UserFactory::new()->createOne();

        $this->actingAs($usuario)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $usuario->getKey(),
            'operacion' => 'sesion.cerrar',
        ]);
    }

    public function test_the_operations_over_the_student_roll_are_recorded(): void
    {
        $usuario = UserFactory::new()->createOne();

        $creado = $this->actingAs($usuario)->postJson('/api/students', [
            'codigo_universitario' => '201900123',
            'nombre' => 'Lucía',
            'apellido' => 'Fernández',
            'ci' => '7788990',
            'carrera' => 'Ingeniería de Sistemas',
        ])->assertCreated();

        $id = $creado->json('id');

        self::assertIsInt($id);

        $this->actingAs($usuario)->putJson("/api/students/{$id}", [
            'nombre' => 'Lucía',
            'apellido' => 'Fernández Rojas',
            'ci' => '7788990',
        ])->assertOk();

        $this->assertContains('estudiante.registrar', $this->operaciones());
        $this->assertContains('estudiante.actualizar', $this->operaciones());
    }

    public function test_the_bulk_upload_is_recorded_as_one_operation_with_its_summary(): void
    {
        $usuario = UserFactory::new()->createOne();

        $csv = implode("\n", [
            'codigo_universitario,documento_identidad,nombres,apellidos,carrera',
            '201900555,9988776,Marco,Antezana,Ingeniería Civil',
            '201900555,9988777,Código,Repetido,Ingeniería Civil',
        ]);

        $this->actingAs($usuario)->postJson('/api/students/import', [
            'archivo' => UploadedFile::fake()->createWithContent('padron.csv', $csv),
        ])->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'padron.importar',
            'tabla_afectada' => 'students',
            'descripcion' => 'Carga masiva: 1 nuevos, 0 actualizados, 1 rechazados.',
        ]);
    }

    public function test_the_operations_over_the_rooms_are_recorded(): void
    {
        $usuario = UserFactory::new()->createOne();

        $creado = $this->actingAs($usuario)->postJson('/api/admin/ambientes', [
            'nombre' => 'Aula de auditoría',
            'ubicacion' => 'Edificio nuevo',
            'capacidad' => 40,
            'estado' => 'DISPONIBLE',
        ])->assertCreated();

        $id = $creado->json('id');

        self::assertIsInt($id);

        $this->actingAs($usuario)->putJson("/api/admin/ambientes/{$id}", [
            'nombre' => 'Aula de auditoría',
            'capacidad' => 40,
            'estado' => 'MANTENIMIENTO',
        ])->assertOk();

        $this->actingAs($usuario)->deleteJson("/api/admin/ambientes/{$id}")->assertNoContent();

        $operaciones = $this->operaciones();

        $this->assertContains('ambiente.registrar', $operaciones);
        $this->assertContains('ambiente.actualizar', $operaciones);
        $this->assertContains('ambiente.eliminar', $operaciones);
    }

    public function test_creating_an_account_is_recorded_with_its_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = UserFactory::new()->createOne();

        $this->actingAs($administrador)->postJson('/api/auth/admin/users', [
            'nombre' => 'Nuevo Docente',
            'correo' => 'nuevo.docente@umss.edu.bo',
            'rol' => 'Docente',
        ])->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.registrar',
            'descripcion' => 'Cuenta creada con el rol Docente.',
        ]);
    }

    public function test_the_log_keeps_recording_the_student_deregistration(): void
    {
        $usuario = UserFactory::new()->createOne();
        $estudiante = StudentFactory::new()->create(['activo' => true]);

        $id = $estudiante->getKey();

        self::assertIsInt($id);

        $this->actingAs($usuario)->deleteJson("/api/students/{$id}")->assertOk();

        $this->assertContains('estudiante.baja', $this->operaciones());
    }
}
