<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * El correo con las credenciales de una cuenta nueva o restablecida. Las
 * rutas que lo disparan (`/api/usuarios`, `/api/docentes/{docente}/cuenta`)
 * tienen sus propias pruebas; aqui se prueba la pasarela.
 */
final class CredencialesInicialesTest extends TestCase
{
    use RefreshDatabase;

    private CuentaUsuarioGateway $cuentas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->cuentas = $this->app->make(CuentaUsuarioGateway::class);
    }

    public function test_the_temporary_password_travels_to_the_account_email(): void
    {
        Mail::fake();

        $cuenta = $this->cuentas->crear(
            new NuevaCuentaData('Mamani Torrez Diego', 'diego.mamani', 'diego@umss.edu.bo', 'Auxiliar'),
            null,
        );

        $this->assertSame('diego@umss.edu.bo', $cuenta->enviadaA);

        Mail::assertSent(
            CredencialesInicialesMail::class,
            static fn (CredencialesInicialesMail $correo): bool => $correo->hasTo('diego@umss.edu.bo')
                && $correo->usuario === 'diego.mamani'
                && $correo->correo === 'diego@umss.edu.bo'
                && $correo->contrasenaTemporal === $cuenta->contrasenaTemporal
                && $correo->horasVigencia === 72,
        );
    }

    public function test_an_account_without_email_is_created_and_nothing_is_sent(): void
    {
        Mail::fake();

        $cuenta = $this->cuentas->crear(
            new NuevaCuentaData('Mamani Torrez Diego', 'diego.mamani', null, 'Auxiliar'),
            null,
        );

        $this->assertNull($cuenta->enviadaA);
        $this->assertDatabaseHas('usuarios', ['id' => $cuenta->id, 'correo' => null]);

        Mail::assertNothingSent();
    }

    public function test_the_username_and_the_email_are_stored_in_lowercase(): void
    {
        Mail::fake();

        $cuenta = $this->cuentas->crear(
            new NuevaCuentaData(
                '  Villarroel Soto Patricia ',
                ' Patricia.Villarroel ',
                'Patricia@UMSS.edu.bo',
                'Docente',
            ),
            null,
        );

        $this->assertSame('patricia.villarroel', $cuenta->usuario);
        $this->assertSame('patricia@umss.edu.bo', $cuenta->enviadaA);

        $this->assertDatabaseHas('usuarios', [
            'nombre' => 'Villarroel Soto Patricia',
            'usuario' => 'patricia.villarroel',
            'correo' => 'patricia@umss.edu.bo',
        ]);
    }

    public function test_taken_usernames_and_emails_are_detected_ignoring_case(): void
    {
        Mail::fake();

        $cuenta = $this->cuentas->crear(
            new NuevaCuentaData('Rojas Vargas Ana', 'ana.rojas', 'ana@umss.edu.bo', 'Administrador'),
            null,
        );

        $this->assertTrue($this->cuentas->usuarioRegistrado(' ANA.Rojas '));
        $this->assertTrue($this->cuentas->correoRegistrado('Ana@UMSS.edu.bo'));
        $this->assertFalse($this->cuentas->usuarioRegistrado('ana.rojas', $cuenta->id));
        $this->assertFalse($this->cuentas->correoRegistrado('ana@umss.edu.bo', $cuenta->id));
        $this->assertFalse($this->cuentas->usuarioRegistrado('otra.cuenta'));
    }

    public function test_a_new_temporary_password_is_sent_again(): void
    {
        Mail::fake();

        $cuenta = $this->cuentas->crear(
            new NuevaCuentaData('Flores Villarroel Corina', 'corina.flores', 'corina@umss.edu.bo', 'Docente'),
            null,
        );

        $nueva = $this->cuentas->emitirTemporal($cuenta->id, null);

        $this->assertNotNull($nueva);
        $this->assertSame('corina@umss.edu.bo', $nueva->enviadaA);

        Mail::assertSent(CredencialesInicialesMail::class, 2);
    }

    public function test_the_email_shows_the_account_the_password_and_the_expiry(): void
    {
        $html = (new CredencialesInicialesMail(
            'Mamani Torrez Diego',
            'diego.mamani',
            'diego@umss.edu.bo',
            'Fa7-Kmq4-Ru9',
            'https://ejemplo.test/login',
            72,
        ))->render();

        $this->assertStringContainsString('diego.mamani', $html);
        $this->assertStringContainsString('diego@umss.edu.bo', $html);
        $this->assertStringContainsString('Fa7-Kmq4-Ru9', $html);
        $this->assertStringContainsString('72 horas', $html);
    }

    public function test_a_mail_failure_keeps_the_account_and_reports_nothing_was_sent(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Servidor de correo caído.'));

        $cuenta = $this->cuentas->crear(
            new NuevaCuentaData('Mamani Torrez Diego', 'diego.mamani', 'diego@umss.edu.bo', 'Auxiliar'),
            null,
        );

        $this->assertNull($cuenta->enviadaA);
        $this->assertTrue(User::whereKey($cuenta->id)->exists());
    }
}
