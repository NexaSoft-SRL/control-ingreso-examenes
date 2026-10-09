<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Role;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La bitacora se escribe sola: quien opera no la pide. Cada operacion deja
 * quien la hizo, que hizo, sobre que registro y cuando; una operacion
 * rechazada no deja asiento.
 */
final class BitacoraRegistroAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creating_an_account_records_audit_operation(): void
    {
        $administrador = UserFactory::new()->createOne();

        $this
            ->actingAs($administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Blanco Coca Leticia',
                'usuario' => 'leticia.blanco',
                'correo' => 'leticia.blanco@umss.edu.bo',
                'rol' => 'Docente',
            ])
            ->assertCreated();

        $cuentaId = DB::table('usuarios')
            ->where('correo', 'leticia.blanco@umss.edu.bo')
            ->value('id');

        self::assertIsInt($cuentaId);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.registrar',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $cuentaId,
        ]);

        $fechaOperacion = DB::table('bitacora_operaciones')
            ->where('operacion', 'usuario.registrar')
            ->where('registro_id', $cuentaId)
            ->value('fecha_operacion');

        self::assertNotNull($fechaOperacion);
    }

    public function test_changing_the_permissions_of_a_role_records_audit_operation(): void
    {
        $administrador = UserFactory::new()->createOne();
        $rolId = Role::where('name', 'Auxiliar')->firstOrFail()->id;

        $this
            ->actingAs($administrador)
            ->putJson("/api/roles/{$rolId}", ['permisos' => ['punto_control']])
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'rol.modificar',
            'tabla_afectada' => 'roles',
            'registro_id' => $rolId,
            'descripcion' => 'El rol Auxiliar quedó con 1 permisos.',
        ]);
    }

    public function test_rejected_account_creation_does_not_record_audit_operation(): void
    {
        $administrador = UserFactory::new()->createOne();

        $this
            ->actingAs($administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Sin usuario',
                'correo' => 'sin.usuario@umss.edu.bo',
                'rol' => 'Docente',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }
}
