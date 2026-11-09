<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Actions\CrearCuentaUsuario;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\GeneradorContrasenaTemporal;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La contrasena temporal: su formato, su vencimiento a 72 horas y el
 * cambio obligatorio en el primer ingreso, sin pedir la actual.
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
        $this->assertNull($usuario->email);
        $this->assertNull($usuario->password_changed_at);
        $this->assertSame('Docente', $usuario->role?->name);
        $this->assertTrue(Hash::check($cuenta->contrasenaTemporal, $usuario->getAuthPassword()));
        $this->assertDatabaseHas('users', [
            'id' => $cuenta->id,
            'password_temporal_expira_en' => '2026-10-15 08:21:00',
        ]);

        Mail::assertNothingSent();
    }

    public function test_the_temporary_password_also_travels_to_the_contact_email(): void
    {
        $cuenta = $this->crear('Mamani Torrez Diego', 'diego.mamani', 'diego@est.umss.edu', 'Auxiliar');

        $this->assertSame('diego@est.umss.edu', $cuenta->enviadaA);

        Mail::assertSent(
            CredencialesInicialesMail::class,
            static fn (CredencialesInicialesMail $correo): bool => $correo->hasTo('diego@est.umss.edu')
                && $correo->usuario === 'diego.mamani'
                && $correo->contrasenaTemporal === $cuenta->contrasenaTemporal
                && $correo->horasVigencia === 72,
        );
    }

    public function test_the_email_shows_the_user_name_the_password_and_the_expiry(): void
    {
        $html = (new CredencialesInicialesMail(
            'Mamani Torrez Diego',
            'diego.mamani',
            'Fa7-Kmq4-Ru9',
            'https://ejemplo.test/login',
            72,
        ))->render();

        $this->assertStringContainsString('diego.mamani', $html);
        $this->assertStringContainsString('Fa7-Kmq4-Ru9', $html);
        $this->assertStringContainsString('72 horas', $html);
    }

    public function test_the_creation_is_recorded_without_the_password(): void
    {
        $administrador = $this->usuarioConRol('Administrador');

        $cuenta = $this->app->make(CrearCuentaUsuario::class)->execute(
            new NuevaCuentaData('Flores Villarroel Corina', 'corina.flores', null, 'Docente'),
            $this->entero($administrador->getKey()),
        );

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.crear',
            'tabla_afectada' => 'users',
            'registro_id' => $cuenta->id,
        ]);

        $this->assertStringNotContainsString(
            $cuenta->contrasenaTemporal,
            DB::table('bitacora_operaciones')->pluck('descripcion')->implode(' ')
        );
    }

    public function test_the_first_login_enters_and_demands_the_change(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => $cuenta->contrasenaTemporal,
        ])
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true)
            ->assertJsonPath('user.rol', 'Docente');

        $this->getJson('/api/bitacora')
            ->assertStatus(409)
            ->assertJsonPath('debe_cambiar_contrasena', true);
    }

    public function test_with_a_temporary_password_the_current_one_is_not_asked(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');
        $usuario = User::findOrFail($cuenta->id);

        $this->actingAs($usuario)
            ->postJson('/api/auth/contrasena', [
                'contrasena_nueva' => 'Clave-Propia-2',
                'contrasena_nueva_confirmation' => 'Clave-Propia-2',
            ])
            ->assertOk()
            ->assertExactJson(['message' => 'Contraseña actualizada.']);

        $usuario->refresh();

        $this->assertTrue(Hash::check('Clave-Propia-2', $usuario->getAuthPassword()));
        $this->assertNotNull($usuario->password_changed_at);
        $this->assertNull($usuario->getAttribute('password_temporal_expira_en'));

        $this->actingAs($usuario)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', false);
    }

    public function test_the_own_password_cannot_repeat_the_temporary_one(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');
        $usuario = User::findOrFail($cuenta->id);

        $this->actingAs($usuario)
            ->postJson('/api/auth/contrasena', [
                'contrasena_nueva' => $cuenta->contrasenaTemporal,
                'contrasena_nueva_confirmation' => $cuenta->contrasenaTemporal,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La contraseña nueva debe ser distinta de la actual.')
            ->assertJsonValidationErrors('contrasena_nueva');

        $this->assertNull($usuario->refresh()->password_changed_at);
    }

    public function test_the_temporary_password_still_works_just_before_72_hours(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');

        $this->travel(71)->hours();
        $this->travel(59)->minutes();

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => $cuenta->contrasenaTemporal,
        ])->assertOk();
    }

    public function test_an_expired_temporary_password_does_not_enter(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');

        $this->travel(72)->hours();
        $this->travel(1)->minutes();

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => $cuenta->contrasenaTemporal,
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'La contraseña temporal venció. Pida una nueva a la administración.',
                'codigo' => 'TEMPORAL_VENCIDA',
            ]);

        $this->assertGuest();

        // Es la contrasena correcta: no suma para el bloqueo por intentos.
        $this->assertSame(0, User::findOrFail($cuenta->id)->failed_login_attempts);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'sesion.intento_fallido',
            'registro_id' => $cuenta->id,
        ]);
    }

    public function test_an_expired_temporary_password_with_a_wrong_password_is_just_a_failed_attempt(): void
    {
        $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');

        $this->travel(80)->hours();

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => 'No-es-la-temporal-1',
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Usuario o contraseña incorrectos.',
                'intentos_restantes' => 4,
            ]);
    }

    public function test_the_own_password_never_expires(): void
    {
        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');

        $this->actingAs(User::findOrFail($cuenta->id))
            ->postJson('/api/auth/contrasena', [
                'contrasena_nueva' => 'Clave-Propia-2',
                'contrasena_nueva_confirmation' => 'Clave-Propia-2',
            ])
            ->assertOk();

        $this->postJson('/api/auth/logout')->assertNoContent();

        $this->travel(30)->days();

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => 'Clave-Propia-2',
        ])->assertOk();
    }

    public function test_a_new_temporary_password_replaces_the_expired_one(): void
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
        $this->assertNull($usuario->locked_until);
        $this->assertSame(0, $usuario->failed_login_attempts);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.restablecer_temporal',
            'registro_id' => $primera->id,
        ]);

        Mail::assertSent(CredencialesInicialesMail::class, 2);

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => $primera->contrasenaTemporal,
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => $segunda->contrasenaTemporal,
        ])
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true);
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
        config()->set('auth_security.horas_temporal', 1);

        $cuenta = $this->crear('Flores Villarroel Corina', 'corina.flores', null, 'Docente');

        $this->travel(61)->minutes();

        $this->postJson('/api/auth/login', [
            'usuario' => 'corina.flores',
            'password' => $cuenta->contrasenaTemporal,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('codigo', 'TEMPORAL_VENCIDA');
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
