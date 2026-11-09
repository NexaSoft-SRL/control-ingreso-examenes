<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Administracion\Application\Actions\CrearCuentaUsuario;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Una escritura y su asiento en la bitacora van en la misma transaccion:
 * si el asiento falla, la escritura no queda. El vehiculo es el alta de
 * una cuenta (`usuario.crear`).
 */
final class BitacoraAtomicityTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    protected function setUp(): void
    {
        parent::setUp();

        // Una cuenta nace con uno de los tres roles del sistema.
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_account_creation_writes_the_account_and_its_audit_entry_together(): void
    {
        Mail::fake();

        $administrador = $this->usuarioConPermisos(['usuarios_roles']);

        $cuenta = $this->app->make(CrearCuentaUsuario::class)->execute(
            new NuevaCuentaData('Blanco Coca Leticia', 'leticia.blanco', null, 'Docente'),
            $this->identificador($administrador->getKey()),
        );

        $usuarioId = $cuenta->id;

        $this->assertDatabaseHas('users', [
            'id' => $usuarioId,
            'usuario' => 'leticia.blanco',
            'email' => null,
            'password_changed_at' => null,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.crear',
            'tabla_afectada' => 'users',
            'registro_id' => $usuarioId,
        ]);

        Mail::assertNothingSent();
    }

    public function test_account_creation_rolls_back_when_audit_insert_fails(): void
    {
        Mail::fake();

        $administrador = $this->usuarioConPermisos(['usuarios_roles']);

        DB::statement(
            "ALTER TABLE bitacora_operaciones
             ADD CONSTRAINT replanteo_force_create_audit_failure
             CHECK (operacion <> 'usuario.crear')"
        );

        try {
            try {
                $this->app->make(CrearCuentaUsuario::class)->execute(
                    new NuevaCuentaData(
                        'Flores Villarroel Corina',
                        'corina.flores',
                        'corina.flores@example.test',
                        'Docente',
                    ),
                    $this->identificador($administrador->getKey()),
                );

                $this->fail(
                    'La escritura de bitácora debía provocar una excepción PostgreSQL.'
                );
            } catch (QueryException $exception) {
                self::assertStringContainsString(
                    'replanteo_force_create_audit_failure',
                    $exception->getMessage()
                );
            }

            $this->assertDatabaseMissing('users', [
                'usuario' => 'corina.flores',
            ]);

            $this->assertDatabaseCount('bitacora_operaciones', 0);

            // Una cuenta que no llego a existir no recibe credenciales.
            Mail::assertNothingSent();
        } finally {
            DB::statement(
                'ALTER TABLE bitacora_operaciones
                 DROP CONSTRAINT IF EXISTS replanteo_force_create_audit_failure'
            );
        }
    }

    public function test_audit_user_foreign_key_failure_rolls_back_the_account(): void
    {
        Mail::fake();

        try {
            $this->app->make(CrearCuentaUsuario::class)->execute(
                new NuevaCuentaData('Mamani Quispe Diego', 'diego.mamani', null, 'Auxiliar'),
                999999999,
            );

            $this->fail(
                'PostgreSQL debía rechazar el usuario inexistente de la bitácora.'
            );
        } catch (QueryException $exception) {
            self::assertStringContainsString(
                'bitacora_operaciones',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('users', [
            'usuario' => 'diego.mamani',
        ]);

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    private function identificador(mixed $clave): int
    {
        if (! is_int($clave)) {
            $this->fail('El identificador PostgreSQL de la cuenta debía ser entero.');
        }

        return $clave;
    }
}
