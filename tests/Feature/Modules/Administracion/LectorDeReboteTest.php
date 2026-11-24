<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Infrastructure\Mail\ImapBuzonDeRebotes;
use App\Modules\Administracion\Infrastructure\Mail\LectorDeRebote;
use PHPUnit\Framework\TestCase;

/**
 * El aviso de un correo no entregado: de el sale la direccion que no
 * existe, solo si el rechazo es definitivo.
 */
final class LectorDeReboteTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function leer(string $archivo): array
    {
        $mensaje = file_get_contents(__DIR__.'/../../../Fixtures/Rebotes/'.$archivo);
        self::assertIsString($mensaje);

        return (new LectorDeRebote)->destinatarios($mensaje);
    }

    public function test_the_gmail_report_in_spanish_gives_the_rejected_address(): void
    {
        self::assertSame(['20012030@est.umss.edu'], $this->leer('gmail_es_dsn.eml'));
    }

    public function test_without_a_report_the_english_sentence_is_read_in_lowercase(): void
    {
        self::assertSame(['ana.quispe@est.umss.edu'], $this->leer('gmail_en_sin_dsn.eml'));
    }

    public function test_a_delay_is_not_a_bounce(): void
    {
        self::assertSame([], $this->leer('gmail_demora.eml'));
    }

    public function test_the_spanish_sentence_alone_needs_a_permanent_code(): void
    {
        $lector = new LectorDeRebote;

        self::assertSame(
            ['202112345@est.umss.edu'],
            $lector->destinatarios("Tu mensaje no se entregó a 202112345@est.umss.edu.\n\n550 5.1.1 does not exist"),
        );
        self::assertSame(
            [],
            $lector->destinatarios("Tu mensaje no se entregó a 202112345@est.umss.edu.\n\n421 4.7.0 try again later"),
        );
    }

    public function test_an_ordinary_message_gives_nothing(): void
    {
        self::assertSame([], (new LectorDeRebote)->destinatarios("Subject: Hola\n\nNos vemos el lunes."));
    }

    public function test_the_search_answer_of_gmail_ends_in_crlf(): void
    {
        self::assertSame(['38', '39'], ImapBuzonDeRebotes::uids("* SEARCH 38 39\r\n"));
        self::assertSame([], ImapBuzonDeRebotes::uids("* SEARCH\r\n"));
    }
}
