<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\GeneradorContrasenaTemporal;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class EloquentCuentaUsuarioGateway implements CuentaUsuarioGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
        private readonly GeneradorContrasenaTemporal $generador,
    ) {}

    public function crear(NuevaCuentaData $datos, ?int $autorId): CuentaCreadaData
    {
        $nombreUsuario = mb_strtolower(trim($datos->usuario));
        $correo = $this->correo($datos->correo);
        $rolId = $this->rolId($datos->rol);
        $temporal = $this->generador->generar();
        $expira = $this->vencimiento();

        // password_changed_at queda nulo: la contrasena es temporal hasta
        // que la cuenta defina la suya.
        $id = DB::transaction(function () use (
            $datos,
            $autorId,
            $nombreUsuario,
            $correo,
            $rolId,
            $temporal,
            $expira,
        ): int {
            $usuario = new User;

            $usuario->forceFill([
                'nombre' => trim($datos->nombre),
                'usuario' => $nombreUsuario,
                'correo' => $correo,
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
                'usuario.registrar',
                'usuarios',
                $id,
                "Cuenta {$nombreUsuario} creada con el rol {$datos->rol}.",
            );

            return $id;
        }, 3);

        // El correo sale con la cuenta ya confirmada: una cuenta que no
        // llego a existir no debe recibir credenciales.
        $enviadaA = $this->enviar(trim($datos->nombre), $nombreUsuario, $correo, $temporal);

        return new CuentaCreadaData(
            id: $id,
            usuario: $nombreUsuario,
            contrasenaTemporal: $temporal,
            enviadaA: $enviadaA,
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
                'usuarios',
                $usuarioId,
                "Contraseña temporal nueva para la cuenta {$usuario->usuario}.",
            );

            return $usuario;
        }, 3);

        if (! $usuario instanceof User) {
            return null;
        }

        $enviadaA = $this->enviar(
            $usuario->name,
            $usuario->usuario,
            $this->correo($usuario->getAttribute('correo')),
            $temporal,
        );

        return new CuentaCreadaData(
            id: $usuarioId,
            usuario: $usuario->usuario,
            contrasenaTemporal: $temporal,
            enviadaA: $enviadaA,
            caducaEn: $this->instante($expira),
        );
    }

    public function usuarioRegistrado(string $usuario, ?int $exceptoId = null): bool
    {
        $consulta = User::query()
            ->where('usuario', mb_strtolower(trim($usuario)));

        if ($exceptoId !== null) {
            $consulta->whereKeyNot($exceptoId);
        }

        return $consulta->exists();
    }

    public function correoRegistrado(string $correo, ?int $exceptoId = null): bool
    {
        $consulta = User::query()
            ->whereRaw('lower(correo) = ?', [mb_strtolower(trim($correo))]);

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
            'nombre' => trim($nombre),
            'usuario' => mb_strtolower(trim($usuario)),
            'correo' => $this->correo($correo),
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

        $usuario->forceFill(['is_active' => $activo])->save();

        return true;
    }

    /**
     * El rol es el nombre de uno existente (de inicio o creado): no se crea
     * una cuenta sin permisos.
     */
    private function rolId(string $rol): int
    {
        $id = Role::query()->where('name', $rol)->value('id');

        if (! is_int($id)) {
            throw new RuntimeException(
                "El rol {$rol} no existe."
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
     * Envia las credenciales al correo de la cuenta y devuelve la direccion,
     * o null si la cuenta no tiene correo o el envio fallo. Un fallo del
     * servidor de correo no deshace la cuenta ni la contrasena ya emitida
     * (se muestran en pantalla): queda en el registro de errores.
     */
    private function enviar(
        string $nombre,
        string $usuario,
        ?string $correo,
        string $temporal,
    ): ?string {
        if ($correo === null) {
            return null;
        }

        $horas = config('auth_security.horas_temporal', 72);

        try {
            Mail::to($correo)->send(new CredencialesInicialesMail(
                $nombre,
                $usuario,
                $correo,
                $temporal,
                url('/login'),
                is_int($horas) && $horas > 0 ? $horas : 72,
            ));
        } catch (Throwable $fallo) {
            report($fallo);

            return null;
        }

        return $correo;
    }

    private function correo(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $correo = mb_strtolower(trim($valor));

        return $correo !== '' ? $correo : null;
    }
}
