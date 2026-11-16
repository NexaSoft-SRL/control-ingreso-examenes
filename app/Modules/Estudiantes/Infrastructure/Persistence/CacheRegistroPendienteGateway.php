<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Estudiantes\Application\Contracts\RegistroPendienteGateway;
use Illuminate\Support\Facades\Cache;

/**
 * Los codigos de quien aun no esta en el padron, en la cache: asi un
 * codigo universitario cualquiera no deja filas en el padron.
 */
final class CacheRegistroPendienteGateway implements RegistroPendienteGateway
{
    private const PREFIJO = 'registro-estudiante:';

    public function guardarCodigo(string $codigoUniversitario, string $hash, int $minutos): void
    {
        $this->bajoCandado($codigoUniversitario, function () use ($codigoUniversitario, $hash, $minutos): void {
            $anterior = Cache::get($this->clave($codigoUniversitario));
            $reemplazados = is_array($anterior) && is_array($anterior['reemplazados'] ?? null)
                ? $anterior['reemplazados']
                : [];

            if (is_array($anterior) && is_string($anterior['hash'] ?? null)) {
                $reemplazados[] = $anterior['hash'];
            }

            $expira = now()->addMinutes($minutos);

            Cache::put($this->clave($codigoUniversitario), [
                'hash' => $hash,
                'intentos' => 0,
                'expira' => $expira->getTimestamp(),
                'reemplazados' => array_slice($reemplazados, -5),
            ], $expira);
        });
    }

    public function consumirCodigo(string $codigoUniversitario, string $hash, int $maximoIntentos): bool
    {
        /** @var bool $valido */
        $valido = $this->bajoCandado($codigoUniversitario, function () use ($codigoUniversitario, $hash, $maximoIntentos): bool {
            $clave = $this->clave($codigoUniversitario);
            $vigente = Cache::get($clave);

            if (! is_array($vigente) || ! is_string($vigente['hash'] ?? null) || ! is_int($vigente['expira'] ?? null)) {
                return false;
            }

            $intentos = is_int($vigente['intentos'] ?? null) ? $vigente['intentos'] : 0;

            if ($intentos >= $maximoIntentos) {
                return false;
            }

            if (! hash_equals($vigente['hash'], $hash)) {
                $vigente['intentos'] = $intentos + 1;
                // El vencimiento no se alarga: conserva su hora original.
                Cache::put($clave, $vigente, now()->setTimestamp($vigente['expira']));

                return false;
            }

            Cache::forget($clave);

            return true;
        });

        return $valido;
    }

    public function intentosRestantes(string $codigoUniversitario, int $maximoIntentos): ?int
    {
        $vigente = Cache::get($this->clave($codigoUniversitario));

        if (! is_array($vigente)) {
            return null;
        }

        $intentos = is_int($vigente['intentos'] ?? null) ? $vigente['intentos'] : 0;

        return max(0, $maximoIntentos - $intentos);
    }

    public function fueReemplazado(string $codigoUniversitario, string $hash): bool
    {
        $vigente = Cache::get($this->clave($codigoUniversitario));

        return is_array($vigente)
            && is_array($vigente['reemplazados'] ?? null)
            && in_array($hash, $vigente['reemplazados'], true);
    }

    private function clave(string $codigoUniversitario): string
    {
        return self::PREFIJO.trim($codigoUniversitario);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    private function bajoCandado(string $codigoUniversitario, callable $accion): mixed
    {
        return Cache::lock($this->clave($codigoUniversitario).':candado', 5)->block(3, $accion);
    }
}
