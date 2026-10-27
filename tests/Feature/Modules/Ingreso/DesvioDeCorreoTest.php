<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * `mail.to` (MAIL_TO_ADDRESS) desvia todo correo a una direccion de
 * pruebas: los estudiantes reales no reciben nada. `mail.permitidos`
 * (MAIL_PERMITIDOS) deja pasar solo a las direcciones de la lista.
 */
final class DesvioDeCorreoTest extends TestCase
{
    use EscenarioDeCodigosQr;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    public function test_the_config_reads_the_redirect_from_the_environment(): void
    {
        $_SERVER['MAIL_TO_ADDRESS'] = 'qa@example.com';
        $_ENV['MAIL_TO_ADDRESS'] = 'qa@example.com';

        try {
            /** @var array<string, mixed> $conDesvio */
            $conDesvio = require base_path('config/mail.php');
        } finally {
            unset($_SERVER['MAIL_TO_ADDRESS'], $_ENV['MAIL_TO_ADDRESS']);
        }

        /** @var array<string, mixed> $sinDesvio */
        $sinDesvio = require base_path('config/mail.php');

        $this->assertSame(['address' => 'qa@example.com', 'name' => 'Pruebas'], $conDesvio['to']);
        $this->assertNull($sinDesvio['to']);
    }

    public function test_with_the_redirect_the_real_recipient_is_replaced(): void
    {
        config([
            'mail.default' => 'array',
            'mail.to' => ['address' => 'qa@example.com', 'name' => 'Pruebas'],
        ]);
        Mail::purge();

        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta('/'.$estudiante->id.'/envio'))
            ->assertStatus(202);

        $transporte = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transporte);
        $this->assertCount(1, $transporte->messages());

        $enviado = $transporte->messages()->first();
        self::assertInstanceOf(SentMessage::class, $enviado);
        $correo = $enviado->getOriginalMessage();
        self::assertInstanceOf(Email::class, $correo);

        $destinatarios = array_map(
            static fn ($direccion): string => $direccion->getAddress(),
            $enviado->getEnvelope()->getRecipients(),
        );

        $this->assertSame(['qa@example.com'], $destinatarios);
        $this->assertSame('qa@example.com', $correo->getTo()[0]->getAddress());
        $this->assertNotContains($estudiante->codigo_universitario.'@est.umss.edu', $destinatarios);
    }

    public function test_without_the_redirect_the_mail_goes_to_the_student(): void
    {
        config(['mail.default' => 'array', 'mail.to' => null]);
        Mail::purge();

        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta('/'.$estudiante->id.'/envio'))
            ->assertStatus(202);

        $transporte = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transporte);
        $enviado = $transporte->messages()->first();
        self::assertInstanceOf(SentMessage::class, $enviado);

        $this->assertSame(
            $estudiante->codigo_universitario.'@est.umss.edu',
            $enviado->getEnvelope()->getRecipients()[0]->getAddress(),
        );
    }

    public function test_the_config_reads_the_allowed_addresses_from_the_environment(): void
    {
        $_SERVER['MAIL_PERMITIDOS'] = ' 202100001@est.umss.edu, QA@Example.com ,';
        $_ENV['MAIL_PERMITIDOS'] = $_SERVER['MAIL_PERMITIDOS'];

        try {
            /** @var array<string, mixed> $conLista */
            $conLista = require base_path('config/mail.php');
        } finally {
            unset($_SERVER['MAIL_PERMITIDOS'], $_ENV['MAIL_PERMITIDOS']);
        }

        /** @var array<string, mixed> $sinLista */
        $sinLista = require base_path('config/mail.php');

        $this->assertSame(['202100001@est.umss.edu', 'qa@example.com'], $conLista['permitidos']);
        $this->assertSame([], $sinLista['permitidos']);
    }

    public function test_an_address_outside_the_allowed_list_is_not_sent(): void
    {
        config(['mail.default' => 'array', 'mail.to' => null, 'mail.permitidos' => ['otra@est.umss.edu']]);
        Mail::purge();

        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta('/'.$estudiante->id.'/envio'))
            ->assertStatus(202);

        $transporte = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transporte);
        $this->assertCount(0, $transporte->messages());
        $this->assertDatabaseHas('envios_correo', [
            'estudiante_id' => $estudiante->id,
            'estado' => 'FALLIDO',
            'error' => 'Dirección no permitida en esta instalación.',
        ]);
    }

    public function test_an_allowed_address_is_sent(): void
    {
        [$estudiante] = $this->habilitados(1);
        $direccion = $estudiante->codigo_universitario.'@est.umss.edu';

        config(['mail.default' => 'array', 'mail.to' => null, 'mail.permitidos' => [$direccion]]);
        Mail::purge();

        $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta('/'.$estudiante->id.'/envio'))
            ->assertStatus(202);

        $transporte = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transporte);
        $enviado = $transporte->messages()->first();
        self::assertInstanceOf(SentMessage::class, $enviado);

        $this->assertSame($direccion, $enviado->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertDatabaseHas('envios_correo', ['estudiante_id' => $estudiante->id, 'estado' => 'ENVIADO']);
    }
}
