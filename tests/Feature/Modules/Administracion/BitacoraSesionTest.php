<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BitacoraSesionTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_in_the_audit_log(): void
    {
        $user = User::create([
            'name' => 'Administrador',
            'usuario' => 'administrador',
            'email' => 'admin@umss.edu.bo',
            'password' => 'Clave-valida-1',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => 'administrador',
            'password' => 'Clave-valida-1',
        ])->assertOk();

        $operacion = $this->operacion('sesion.iniciar');

        $this->assertSame($user->getKey(), $operacion['usuario_id']);
        $this->assertSame('users', $operacion['tabla_afectada']);
    }

    public function test_failed_login_is_recorded_without_user_reference(): void
    {
        User::create([
            'name' => 'Administrador',
            'usuario' => 'administrador',
            'email' => 'admin@umss.edu.bo',
            'password' => 'Clave-valida-1',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => 'administrador',
            'password' => 'clave-incorrecta',
        ])->assertUnauthorized();

        $operacion = $this->operacion('sesion.intento_fallido');

        $this->assertNull($operacion['usuario_id']);
        $this->assertIsString($operacion['descripcion']);
        $this->assertStringContainsString(
            'administrador',
            $operacion['descripcion']
        );
    }

    public function test_the_audit_log_never_stores_the_submitted_password(): void
    {
        User::create([
            'name' => 'Administrador',
            'usuario' => 'administrador',
            'email' => 'admin@umss.edu.bo',
            'password' => 'Clave-valida-1',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => 'administrador',
            'password' => 'clave-secreta-del-intento',
        ])->assertUnauthorized();

        $descripciones = DB::table('bitacora_operaciones')
            ->pluck('descripcion')
            ->implode(' ');

        $this->assertStringNotContainsString(
            'clave-secreta-del-intento',
            $descripciones
        );
    }

    public function test_logout_is_recorded_in_the_audit_log(): void
    {
        $user = User::create([
            'name' => 'Administrador',
            'usuario' => 'administrador',
            'email' => 'admin@umss.edu.bo',
            'password' => 'Clave-valida-1',
        ]);

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $operacion = $this->operacion('sesion.cerrar');

        $this->assertSame($user->getKey(), $operacion['usuario_id']);
    }

    /**
     * Devuelve la operacion registrada como arreglo: la consulta cruda
     * entrega un objeto sin tipos declarados.
     *
     * @return array<string, mixed>
     */
    private function operacion(string $nombre): array
    {
        $registro = DB::table('bitacora_operaciones')
            ->where('operacion', $nombre)
            ->first();

        $this->assertNotNull(
            $registro,
            "No se registro la operacion {$nombre} en la bitacora."
        );

        /** @var array<string, mixed> $datos */
        $datos = get_object_vars($registro);

        return $datos;
    }
}
