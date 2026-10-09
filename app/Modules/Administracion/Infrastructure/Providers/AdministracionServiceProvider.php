<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Providers;

use App\Modules\Administracion\Application\Actions\ProponerUsuario;
use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\GeneradorContrasenaTemporal;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\Contracts\RolGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentAuthenticationSecurityGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentBitacoraGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentConsultaBitacoraGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentConsultaUsuariosGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentCuentaUsuarioGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentRolesGateway;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentRolGateway;
use App\Modules\Administracion\Infrastructure\Persistence\GeneradorContrasenaTemporalAleatorio;
use App\Modules\Administracion\Infrastructure\Persistence\TransaccionBaseDeDatos;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces contrato -> implementacion del modulo Administracion.
 */
final class AdministracionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
            RolGateway::class,
            EloquentRolGateway::class,
        );

        $this->app->bind(
            ConsultaUsuariosGateway::class,
            EloquentConsultaUsuariosGateway::class,
        );

        $this->app->bind(
            RolesGateway::class,
            EloquentRolesGateway::class,
        );

        $this->app->bind(
            Transaccion::class,
            TransaccionBaseDeDatos::class,
        );
    }
}
