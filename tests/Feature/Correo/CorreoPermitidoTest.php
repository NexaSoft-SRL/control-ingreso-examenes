<?php

declare(strict_types=1);

namespace Tests\Feature\Correo;

use Illuminate\Mail\Message;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Tests\TestCase;

/**
 * `mail.permitidos` (MAIL_PERMITIDOS) deja pasar solo a las direcciones de
 * la lista y `mail.to` (MAIL_TO_ADDRESS) desvia todo correo a una sola: con
 * datos ficticios, ningun estudiante real recibe nada.
 */
final class CorreoPermitidoTest extends TestCase
{
    public function test_only_the_allowed_addresses_receive_mail(): void
    {
        config(['mail.default' => 'array', 'mail.permitidos' => ['equipo@nexasoft.test']]);
        Mail::purge();

        Mail::raw('Hola', static fn (Message $m) => $m->to(['equipo@nexasoft.test', '202100001@est.umss.edu']));

        $this->assertSame(['equipo@nexasoft.test'], $this->destinatarios());
    }

    public function test_without_any_allowed_recipient_nothing_is_sent(): void
    {
        config(['mail.default' => 'array', 'mail.permitidos' => ['equipo@nexasoft.test']]);
        Mail::purge();

        Mail::raw('Hola', static fn (Message $m) => $m->to('202100001@est.umss.edu'));

        $this->assertSame([], $this->destinatarios());
    }

    public function test_without_a_list_everyone_receives_mail(): void
    {
        config(['mail.default' => 'array', 'mail.permitidos' => []]);
        Mail::purge();

        Mail::raw('Hola', static fn (Message $m) => $m->to('202100001@est.umss.edu'));

        $this->assertSame(['202100001@est.umss.edu'], $this->destinatarios());
    }

    public function test_the_config_reads_the_lists_from_the_environment(): void
    {
        $_SERVER['MAIL_PERMITIDOS'] = $_ENV['MAIL_PERMITIDOS'] = ' Equipo@NexaSoft.test , otro@nexasoft.test ';
        $_SERVER['MAIL_TO_ADDRESS'] = $_ENV['MAIL_TO_ADDRESS'] = 'qa@nexasoft.test';

        try {
            /** @var array<string, mixed> $config */
            $config = require base_path('config/mail.php');
        } finally {
            unset($_SERVER['MAIL_PERMITIDOS'], $_ENV['MAIL_PERMITIDOS'], $_SERVER['MAIL_TO_ADDRESS'], $_ENV['MAIL_TO_ADDRESS']);
        }

        $this->assertSame(['equipo@nexasoft.test', 'otro@nexasoft.test'], $config['permitidos']);
        $this->assertSame(['address' => 'qa@nexasoft.test', 'name' => 'Pruebas'], $config['to']);
    }

    /**
     * @return list<string>
     */
    private function destinatarios(): array
    {
        $transporte = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transporte);

        $direcciones = [];

        foreach ($transporte->messages() as $enviado) {
            self::assertInstanceOf(SentMessage::class, $enviado);

            foreach ($enviado->getEnvelope()->getRecipients() as $direccion) {
                $direcciones[] = $direccion->getAddress();
            }
        }

        return $direcciones;
    }
}
