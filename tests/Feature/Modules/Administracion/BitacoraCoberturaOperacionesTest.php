<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HU-07 pide auditar "quien hizo que y cuando". Estas pruebas fijan que
 * las operaciones de sesion y de cuentas dejen su rastro. Las de cada
 * modulo de dominio se prueban en su carpeta.
 */
final class BitacoraCoberturaOperacionesTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_creating_an_account_is_recorded_with_its_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = UserFactory::new()->createOne();

        $this->actingAs($administrador)->postJson('/api/usuarios', [
            'nombre' => 'Nuevo Docente',
            'usuario' => 'nuevo.docente',
            'correo' => 'nuevo.docente@umss.edu.bo',
            'rol' => 'Docente',
        ])->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.registrar',
            'descripcion' => 'Cuenta nuevo.docente creada con el rol Docente.',
        ]);
    }
}
