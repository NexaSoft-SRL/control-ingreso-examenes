<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sin enlaces: cada modulo registra los suyos en su
 * Infrastructure/Providers (ver bootstrap/providers.php). Aqui solo va lo
 * que vale para todo correo del sistema.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, static fn (MessageSending $evento): ?bool => self::soloPermitidos($evento->message));
    }

    /**
     * Con `mail.permitidos`, quita del mensaje a quien no este en la lista;
     * si no queda nadie, el falso cancela el envio.
     */
    private static function soloPermitidos(Email $correo): ?bool
    {
        /** @var list<string> $permitidos */
        $permitidos = config('mail.permitidos', []);

        if ($permitidos === []) {
            return null;
        }

        $para = self::filtrar($correo->getTo(), $permitidos);
        $copia = self::filtrar($correo->getCc(), $permitidos);
        $oculta = self::filtrar($correo->getBcc(), $permitidos);

        if ($para === [] && $copia === [] && $oculta === []) {
            return false;
        }

        $correo->to(...$para)->cc(...$copia)->bcc(...$oculta);

        return null;
    }

    /**
     * @param  array<Address>  $direcciones
     * @param  list<string>  $permitidos
     * @return list<Address>
     */
    private static function filtrar(array $direcciones, array $permitidos): array
    {
        $quedan = [];

        foreach ($direcciones as $direccion) {
            if (in_array(mb_strtolower($direccion->getAddress()), $permitidos, true)) {
                $quedan[] = $direccion;
            }
        }

        return $quedan;
    }
}
