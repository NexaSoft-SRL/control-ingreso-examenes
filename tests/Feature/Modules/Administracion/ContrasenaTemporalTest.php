<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\GeneradorContrasenaTemporal;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Administracion\Domain\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La contrasena temporal: su formato y su emision, al crear la cuenta y al
 * restablecerla. El vencimiento se guarda pero todavia no se aplica, y el
 * cambio obligatorio llega con el primer ingreso (HU-16).
 */
final class ContrasenaTemporalTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const FORMATO = '/^[A-HJKMNP-Z][a-hjkmnp-z][2-9]-[A-HJKMNP-Z][a-hjkmnp-z]{2}[2-9]-[A-HJKMNP-Z][a-hjkmnp-z][2-9]$/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    public function test_the_generator_follows_the_format_without_confusing_characters(): void
    {
        $generador = $this->app->make(GeneradorContrasenaTemporal::class);
        $generadas = [];

        for ($i = 0; $i < 300; $i++) {
            $contrasena = $generador->generar();

            $this->assertMatchesRegularExpression(self::FORMATO, $contrasena);
            $this->assertDoesNotMatchRegularExpression('/[ilo01ILO]/', $contrasena);

            $generadas[$contrasena] = true;
        }

        $this->assertGreaterThan(295, count($generadas));
    }

    public function test_a_new_account_gets_a_temporary_password_that_expires_in_72_hours(): void
    {
        Carbon::setTestNow('2026-10-12 08:21:00');

        $cuenta = $this->crear('Blanco Coca Leticia', 'Leticia.Blanco', null, 'Docente');

        $this->assertMatchesRegularExpression(self::FORMATO, $cuenta->contrasenaTemporal);
        $this->assertSame('leticia.blanco', $cuenta->usuario);
        $this->assertNull($cuenta->enviadaA);
        $this->assertSame('2026-10-15T08:21:00-04:00', $cuenta->caducaEn);

        $usuario = User::findOrFail($cuenta->id);

        $this->assertSame('leticia.blanco', $usuario->usuario);
        $this->assertNull($usuario->correo);
        $this->assertNull($usuario->password_changed_at);
        $this->assertSame('Docente', $usuario->role?->name);
        $this->assertTrue(Hash::check($cuenta->contrasenaTemporal, $usuario->getAuthPassword()));
        $this->assertDatabaseHas('usuarios', [
            'id' => $cuenta->id,
            'password_temporal_expira_en' => '2026-10-15 08:21:00',
        ]);

        Mail::assertNothingSent();
    }

    public function test_the_role_can_be_one_created_after_the_installation(): void
    {
        $this->usuarioConPermisos(['periodo_oferta']);

        $cuenta = $this->crear('Rojas Vargas Ana', 'ana.rojas', 'ana@umss.edu.bo', 'Rol de prueba');

        $this->assertSame('Rol de prueba', User::findOrFail($cuenta->id)->role?->name);
    }

    public function test_the_creation_is_recorded_without_the_password(): void
    {
        $administrador = $this->usuarioConRol('Administrador');

        $cuenta = $this->app->make(CuentaUsuarioGateway::class)->crear(
            new NuevaCuentaData('Flores Villarroel Corina', 'corina.flores', null, 'Docente'),
            $this->entero($administrador->getKey()),
        );

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.registrar',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $cuenta->id,
        ]);

        $this->assertStringNotContainsString(
            $cuenta->contrasenaTemporal,
            DB::table('bitacora_operaciones')->pluck('descripcion')->implode(' ')
        );
    }

    public function test_the_account_enters_by_email_with_the_temporary_password(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', 'corina.flores@umss.edu.bo', 'Docente');

        $this->postJson('/api/auth/login', [
            'email' => 'corina.flores@umss.edu.bo',
            'password' => $cuenta->contrasenaTemporal,
        ])
            ->assertOk()
            ->assertJsonPath('user.usuario', 'corina.flores')
            ->assertJsonPath('user.rol', 'Docente')
            ->assertJsonPath('user.debe_cambiar_contrasena', false);
    }

    public function test_the_expiry_is_stored_but_not_enforced_yet(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', 'corina.flores@umss.edu.bo', 'Docente');

        $this->travel(80)->hours();

        $this->postJson('/api/auth/login', [
            'email' => 'corina.flores@umss.edu.bo',
            'password' => $cuenta->contrasenaTemporal,
        ])->assertOk();
    }

    public function test_a_new_temporary_password_replaces_the_previous_one(): void
    {
        $cuentas = $this->app->make(CuentaUsuarioGateway::class);
        $administrador = $this->usuarioConRol('Administrador');

        $primera = $this->crear('Flores Villarroel Corina', 'corina.flores', 'corina@umss.edu.bo', 'Docente');

        User::findOrFail($primera->id)->forceFill([
            'password_changed_at' => now(),
            'password_temporal_expira_en' => null,
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(10),
        ])->save();

        $this->travel(5)->days();

        $segunda = $cuentas->emitirTemporal(
            $primera->id,
            $this->entero($administrador->getKey()),
        );

        $this->assertInstanceOf(CuentaCreadaData::class, $segunda);
        $this->assertMatchesRegularExpression(self::FORMATO, $segunda->contrasenaTemporal);
        $this->assertNotSame($primera->contrasenaTemporal, $segunda->contrasenaTemporal);
        $this->assertSame('corina@umss.edu.bo', $segunda->enviadaA);

        $usuario = User::findOrFail($primera->id);

        $this->assertNull($usuario->password_changed_at);
        $this->assertNotNull($usuario->password_temporal_expira_en);
        $this->assertNull($usuario->locked_until);
        $this->assertSame(0, $usuario->failed_login_attempts);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.restablecer_temporal',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $primera->id,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'corina@umss.edu.bo',
            'password' => $primera->contrasenaTemporal,
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'corina@umss.edu.bo',
            'password' => $segunda->contrasenaTemporal,
        ])->assertOk();
    }

    public function test_a_temporary_password_for_an_unknown_account_is_not_issued(): void
    {
        $this->assertNull(
            $this->app->make(CuentaUsuarioGateway::class)->emitirTemporal(999999, null)
        );

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_the_validity_comes_from_the_configuration(): void
    {
        Carbon::setTestNow('2026-10-12 08:21:00');
        config()->set('auth_security.horas_temporal', 1);

        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');

        $this->assertSame('2026-10-12T09:21:00-04:00', $cuenta->caducaEn);
    }

    private function crear(
        string $nombre,
        string $usuario,
        ?string $correo,
        string $rol,
    ): CuentaCreadaData {
        return $this->app->make(CuentaUsuarioGateway::class)->crear(
            new NuevaCuentaData($nombre, $usuario, $correo, $rol),
            null,
        );
    }

    private function entero(mixed $valor): int
    {
        if (! is_int($valor)) {
            $this->fail('Se esperaba un identificador entero.');
        }

        return $valor;
    }
}
