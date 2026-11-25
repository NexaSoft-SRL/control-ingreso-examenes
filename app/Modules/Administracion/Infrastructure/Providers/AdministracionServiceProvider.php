<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Providers;

use App\Modules\Administracion\Application\Actions\GenerarRespaldoAutomatico;
use App\Modules\Administracion\Application\Actions\ProcesarRebotesDeCorreo;
use App\Modules\Administracion\Application\Actions\ProponerUsuario;
use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\BuzonDeRebotes;
use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\Contracts\CredencialesGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\GeneradorContrasenaTemporal;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Administracion\Application\Contracts\RespaldoGateway;
use App\Modules\Administracion\Application\Contracts\RestablecimientoGateway;
use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\Contracts\RolGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Infrastructure\Files\AlmacenImagenesEnDisco;
use App\Modules\Administracion\Infrastructure\Files\RespaldoSqlEnDisco;
use App\Modules\Administracion\Infrastructure\Mail\ImapBuzonDeRebotes;
use App\Modules\Administracion\Infrastructure\Mail\LectorDeRebote;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentAuthenticationSecurityGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentBitacoraGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentConsultaBitacoraGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentConsultaUsuariosGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentCredencialesGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentCuentaUsuarioGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentRegistroCorreoGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentRestablecimientoGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentRolesGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentRolGateway;
use App\Modules\Administracion\Infrastructure\Persistence\GeneradorContrasenaTemporalAleatorio;
use App\Modules\Administracion\Infrastructure\Persistence\TransaccionBaseDeDatos;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Enlaces contrato -> implementacion del modulo Administracion.
 */
final class AdministracionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AlmacenImagenes::class,
            AlmacenImagenesEnDisco::class,
        );

        $this->app->bind(
            AuthenticationSecurityGateway::class,
            EloquentAuthenticationSecurityGateway::class,
        );

        $this->app->bind(
            BitacoraGateway::class,
            EloquentBitacoraGateway::class,
        );

        $this->app->bind(
            ConsultaBitacoraGateway::class,
            EloquentConsultaBitacoraGateway::class,
        );

        $this->app->bind(
            ConsultaUsuariosGateway::class,
            EloquentConsultaUsuariosGateway::class,
        );

        $this->app->bind(
            CredencialesGateway::class,
            EloquentCredencialesGateway::class,
        );

        $this->app->bind(
            CuentaUsuarioGateway::class,
            EloquentCuentaUsuarioGateway::class,
        );

        $this->app->bind(
            GeneradorContrasenaTemporal::class,
            GeneradorContrasenaTemporalAleatorio::class,
        );

        // La propuesta de usuario es una regla de aplicacion: su cara
        // publica para los demas modulos es la propia accion.
        $this->app->bind(
            ProponedorUsuario::class,
            ProponerUsuario::class,
        );

        $this->app->bind(
            RegistroCorreoGateway::class,
            EloquentRegistroCorreoGateway::class,
        );

        $this->app->bind(
            RespaldoGateway::class,
            RespaldoSqlEnDisco::class,
        );

        $this->app->bind(
            RestablecimientoGateway::class,
            EloquentRestablecimientoGateway::class,
        );

        $this->app->bind(
            RolesGateway::class,
            EloquentRolesGateway::class,
        );

        $this->app->bind(
            RolGateway::class,
            EloquentRolGateway::class,
        );

        $this->app->bind(
            Transaccion::class,
            TransaccionBaseDeDatos::class,
        );

        $this->app->bind(BuzonDeRebotes::class, fn (): ImapBuzonDeRebotes => new ImapBuzonDeRebotes(
            host: $this->texto('umss.rebotes.host', 'imap.gmail.com'),
            puerto: $this->entero('umss.rebotes.puerto', 993),
            usuario: $this->texto('umss.rebotes.usuario', ''),
            clave: $this->texto('umss.rebotes.clave', ''),
            lector: new LectorDeRebote,
        ));

        $this->app->when(GenerarRespaldoAutomatico::class)
            ->needs('$hora')
            ->give(fn (): string => $this->texto('umss.respaldo_automatico.hora', GenerarRespaldoAutomatico::HORA_POR_DEFECTO));

        $this->app->when(GenerarRespaldoAutomatico::class)
            ->needs('$zona')
            ->give(fn (): string => $this->texto('umss.zona_horaria', 'America/La_Paz'));

        $this->app->when(ProcesarRebotesDeCorreo::class)
            ->needs('$habilitado')
            ->give(fn (): bool => config('umss.rebotes.habilitado') === true);
    }

    /**
     * Sin tareas programadas en el servidor, los rebotes se leen al terminar
     * una peticion web, como mucho una vez cada 5 minutos (el candado de la
     * cache). La lectura va despues de la respuesta y un fallo solo queda en
     * el registro.
     */
    public function boot(): void
    {
        $this->app->terminating(function (): void {
            if (config('umss.rebotes.habilitado') !== true) {
                return;
            }

            if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                return;
            }

            if (! Cache::add('correo:rebotes:revisados', true, 300)) {
                return;
            }

            try {
                $this->app->make(ProcesarRebotesDeCorreo::class)->execute();
            } catch (Throwable $error) {
                report($error);
            }
        });

        $this->respaldarAlResponder();
    }

    /**
     * Sin tareas programadas en el servidor, el respaldo de cada noche se
     * genera al terminar una peticion web pasada la hora de corte. Se mira a
     * lo mas cada 5 minutos, y tras un intento, con exito o sin el, no se
     * repite antes de `reintento_minutos`: un fallo no se reintenta en bucle.
     * Va despues de la respuesta y un fallo solo queda en el registro.
     */
    private function respaldarAlResponder(): void
    {
        $this->app->terminating(function (): void {
            if (config('umss.respaldo_automatico.habilitado') !== true) {
                return;
            }

            if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                return;
            }

            try {
                if (! Cache::add('respaldo:automatico:revisado', true, 300)) {
                    return;
                }

                $respaldo = $this->app->make(GenerarRespaldoAutomatico::class);

                if (! $respaldo->corresponde(DateTimeImmutable::createFromInterface(now()))) {
                    return;
                }

                $reintento = max(1, $this->entero('umss.respaldo_automatico.reintento_minutos', 30));

                if (! Cache::add('respaldo:automatico:intento', true, $reintento * 60)) {
                    return;
                }

                $respaldo->execute();
            } catch (Throwable $error) {
                report($error);
            }
        });
    }

    private function texto(string $clave, string $porDefecto): string
    {
        $valor = config($clave);

        return is_string($valor) && $valor !== '' ? $valor : $porDefecto;
    }

    private function entero(string $clave, int $porDefecto): int
    {
        $valor = config($clave);

        return is_int($valor) ? $valor : $porDefecto;
    }
}
