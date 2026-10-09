<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Domain\Models\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Una escritura y su asiento en la bitacora van en la misma transaccion: si
 * el asiento falla, la escritura no queda. El vehiculo es el alta de un rol
 * seguida de su asiento, tal como lo hace una accion de la aplicacion: la
 * pasarela de bitacora escribe en la conexion de quien la llama y no se
 * traga el error.
 */
final class BitacoraAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_write_and_its_audit_entry_are_stored_together(): void
    {
        $autor = $this->identificador(UserFactory::new()->createOne()->getKey());

        $rolId = $this->crearRolConAsiento('Coordinador', $autor);

        $this->assertDatabaseHas('roles', [
            'id' => $rolId,
            'name' => 'Coordinador',
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $autor,
            'operacion' => 'rol.crear',
            'tabla_afectada' => 'roles',
            'registro_id' => $rolId,
        ]);
    }

    public function test_the_write_rolls_back_when_audit_insert_fails(): void
    {
        $autor = $this->identificador(UserFactory::new()->createOne()->getKey());

        DB::statement(
            "ALTER TABLE bitacora_operaciones
             ADD CONSTRAINT hu07_force_audit_failure
             CHECK (operacion <> 'rol.crear')"
        );

        try {
            try {
                $this->crearRolConAsiento('Coordinador', $autor);

                $this->fail(
                    'La escritura de bitácora debía provocar una excepción PostgreSQL.'
                );
            } catch (QueryException $exception) {
                self::assertStringContainsString(
                    'hu07_force_audit_failure',
                    $exception->getMessage()
                );
            }

            $this->assertDatabaseMissing('roles', [
                'name' => 'Coordinador',
            ]);

            $this->assertDatabaseCount('bitacora_operaciones', 0);
        } finally {
            DB::statement(
                'ALTER TABLE bitacora_operaciones
                 DROP CONSTRAINT IF EXISTS hu07_force_audit_failure'
            );
        }
    }

    public function test_audit_user_foreign_key_failure_rolls_back_the_write(): void
    {
        try {
            $this->crearRolConAsiento('Coordinador', 999999999);

            $this->fail(
                'PostgreSQL debía rechazar el usuario inexistente de la bitácora.'
            );
        } catch (QueryException $exception) {
            self::assertStringContainsString(
                'bitacora_operaciones',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('roles', [
            'name' => 'Coordinador',
        ]);

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    private function crearRolConAsiento(string $nombre, int $autor): int
    {
        $bitacora = $this->app->make(BitacoraGateway::class);

        return DB::transaction(function () use ($bitacora, $nombre, $autor): int {
            $rolId = $this->identificador(
                Role::create(['name' => $nombre, 'es_sistema' => false])->getKey()
            );

            $bitacora->registrar($autor, 'rol.crear', 'roles', $rolId, null);

            return $rolId;
        });
    }

    private function identificador(mixed $clave): int
    {
        if (! is_int($clave)) {
            $this->fail('El identificador PostgreSQL debía ser entero.');
        }

        return $clave;
    }
}
