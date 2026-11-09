<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\GeneradorContrasenaTemporal;
use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Administracion\Application\DTOs\RegistroCorreoData;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class EloquentCuentaUsuarioGateway implements CuentaUsuarioGateway
{
    /**
     * Zona en que se informan los instantes al cliente.
     */
    public function __construct(
        private readonly BitacoraGateway $bitacora,
        private readonly GeneradorContrasenaTemporal $generador,
        private readonly RegistroCorreoGateway $correos,
    ) {}

    public function crear(NuevaCuentaData $datos, ?int $autorId): CuentaCreadaData
    {
        $nombreUsuario = mb_strtolower(trim($datos->usuario));
        $rolId = $this->rolId($datos->rol);
        $temporal = $this->generador->generar();
        $expira = $this->vencimiento();

        // password_changed_at queda nulo: el sistema le exigira definir la
        // suya en el primer ingreso.
        $id = DB::transaction(function () use (
            $datos,
            $autorId,
            $nombreUsuario,
            $rolId,
            $temporal,
            $expira,
        ): int {
            $usuario = new User;

            $usuario->forceFill([
                'name' => $datos->nombre,
                'usuario' => $nombreUsuario,
                'email' => $datos->correo,
                'password' => $temporal,
                'password_changed_at' => null,
                'password_temporal_expira_en' => $expira,
                'role_id' => $rolId,
            ])->save();

            $id = $usuario->getKey();

            if (! is_int($id)) {
                throw new RuntimeException('La cuenta creada no devolvio un identificador.');
            }

            $this->bitacora->registrar(
                $autorId,
                'usuario.crear',
                'users',
                $id,
                "Cuenta {$nombreUsuario} creada con el rol {$datos->rol}.",
            );

            return $id;
        }, 3);

        // El correo sale con la cuenta ya confirmada: una cuenta que no
        // llego a existir no debe recibir credenciales.
        $this->enviar($id, $autorId, $datos->nombre, $nombreUsuario, $datos->correo, $temporal);

        return new CuentaCreadaData(
            id: $id,
            usuario: $nombreUsuario,
            contrasenaTemporal: $temporal,
            enviadaA: $datos->correo,
            caducaEn: $this->instante($expira),
        );
    }

    public function emitirTemporal(int $usuarioId, ?int $autorId): ?CuentaCreadaData
    {
        $temporal = $this->generador->generar();
        $expira = $this->vencimiento();

        $usuario = DB::transaction(function () use (
            $usuarioId,
            $autorId,
            $temporal,
            $expira,
        ): ?User {
            $usuario = User::query()
                ->whereKey($usuarioId)
                ->lockForUpdate()
                ->first();

            if (! $usuario instanceof User) {
                return null;
            }

            $usuario->forceFill([
                'password' => $temporal,
                'password_changed_at' => null,
                'password_temporal_expira_en' => $expira,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            $this->bitacora->registrar(
                $autorId,
                'usuario.restablecer_temporal',
                'users',
                $usuarioId,
                "Contraseña temporal nueva para la cuenta {$usuario->usuario}.",
            );

            return $usuario;
        }, 3);

        if (! $usuario instanceof User) {
            return null;
        }

        $correo = $this->texto($usuario->getAttribute('email'));

        $this->enviar($usuarioId, $autorId, $usuario->name, $usuario->usuario, $correo, $temporal);

        return new CuentaCreadaData(
            id: $usuarioId,
            usuario: $usuario->usuario,
            contrasenaTemporal: $temporal,
            enviadaA: $correo,
            caducaEn: $this->instante($expira),
        );
    }

    public function usuarioRegistrado(string $usuario, ?int $exceptoId = null): bool
    {
        $consulta = User::query()
            // Dos cuentas no pueden diferir solo en el espacio.
            ->whereRaw("replace(usuario, ' ', '') = ?", [NombreDeUsuario::compacto($usuario)]);

        if ($exceptoId !== null) {
            $consulta->whereKeyNot($exceptoId);
        }

        return $consulta->exists();
    }

    public function correoRegistrado(string $correo, ?int $exceptoId = null): bool
    {
        $consulta = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($correo))]);

        if ($exceptoId !== null) {
            $consulta->whereKeyNot($exceptoId);
        }

        return $consulta->exists();
    }

    public function actualizar(
        int $usuarioId,
        string $nombre,
        string $usuario,
        ?string $correo,
        string $rol,
    ): bool {
        $cuenta = User::find($usuarioId);

        if (! $cuenta instanceof User) {
            return false;
        }

        $cuenta->forceFill([
            'name' => $nombre,
            'usuario' => mb_strtolower(trim($usuario)),
            'email' => $correo,
            'role_id' => $this->rolId($rol),
        ])->save();

        return true;
    }

    public function cambiarEstado(int $usuarioId, bool $activo): bool
    {
        $usuario = User::find($usuarioId);

        if (! $usuario instanceof User) {
            return false;
        }

        // is_active no es asignable en masa a proposito: se toca aqui.
        $usuario->forceFill(['is_active' => $activo])->save();

        return true;
    }

    /**
     * Los tres roles los deja `RolePermissionSeeder`: sin ellos el sistema
     * no esta instalado y no se crea una cuenta sin permisos.
     */
    private function rolId(string $rol): int
    {
        $id = Role::query()->where('name', $rol)->value('id');

        if (! is_int($id)) {
            throw new RuntimeException(
                "El rol {$rol} no existe. Falta ejecutar RolePermissionSeeder."
            );
        }

        return $id;
    }

    private function vencimiento(): DateTimeInterface
    {
        $horas = config('auth_security.horas_temporal', 72);

        return now()->addHours(is_int($horas) && $horas > 0 ? $horas : 72);
    }

    private function instante(DateTimeInterface $momento): string
    {
        return DateTimeImmutable::createFromInterface($momento)->format(DATE_ATOM);
    }

    /**
     * El correo de credenciales queda anotado en `envios_correo` antes de
     * salir y se cierra con su resultado. Si el envio falla, el fallo se
     * anota y sigue su curso como antes.
     */
    private function enviar(
        int $usuarioId,
        ?int $autorId,
        string $nombre,
        string $usuario,
        ?string $correo,
        string $temporal,
    ): void {
        if ($correo === null) {
            return;
        }

        $horas = config('auth_security.horas_temporal', 72);

        $envioId = $this->correos->registrar(new RegistroCorreoData(
            tipo: RegistroCorreoData::TIPO_CREDENCIALES,
            destinatario: $correo,
            usuarioId: $usuarioId,
            solicitadoPor: $autorId,
        ));

        try {
            Mail::to($correo)->send(new CredencialesInicialesMail(
                $nombre,
                $usuario,
                $temporal,
                url('/login'),
                is_int($horas) && $horas > 0 ? $horas : 72,
            ));
        } catch (Throwable $fallo) {
            $this->correos->marcarFallido($envioId, $fallo->getMessage());

            throw $fallo;
        }

        $this->correos->marcarEnviado($envioId);
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
