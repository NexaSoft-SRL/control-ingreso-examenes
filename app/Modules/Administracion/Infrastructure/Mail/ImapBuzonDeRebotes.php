<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Mail;

use App\Modules\Administracion\Application\Contracts\BuzonDeRebotes;
use App\Modules\Administracion\Application\DTOs\ReboteData;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * El buzon del remitente por IMAP sobre TLS. El servidor de despliegue no
 * tiene la extension `imap` de PHP, asi que se habla el protocolo a mano:
 * solo las ordenes que hacen falta (LOGIN, EXAMINE, UID SEARCH, UID FETCH,
 * LOGOUT).
 *
 * Se leen los avisos de mailer-daemon de los ultimos dias, aunque alguien ya
 * los haya abierto, y el buzon se abre en solo lectura: no se marca ni se
 * mueve nada. Cualquier fallo de red o de acceso queda en el registro y
 * devuelve una lista vacia.
 */
final class ImapBuzonDeRebotes implements BuzonDeRebotes
{
    // Como mucho, avisos por lectura: el resto queda para la siguiente.
    private const MAXIMO = 30;

    private const ESPERA = 10;

    /** @var resource|null */
    private $conexion = null;

    private int $orden = 0;

    public function __construct(
        private readonly string $host,
        private readonly int $puerto,
        private readonly string $usuario,
        private readonly string $clave,
        private readonly LectorDeRebote $lector,
    ) {}

    public function rebotes(int $dias): array
    {
        if ($this->usuario === '' || $this->clave === '') {
            return [];
        }

        try {
            $this->abrir();
            $uids = $this->buscar($dias);
            $rebotes = [];

            // Los mas nuevos primero, por si hay mas que el maximo.
            foreach (array_slice(array_reverse($uids), 0, self::MAXIMO) as $uid) {
                [$fecha, $mensaje] = $this->traer($uid);

                $destinatarios = $this->lector->destinatarios($mensaje);

                if ($destinatarios === []) {
                    // No es un rebote permanente (una demora, por ejemplo).
                    continue;
                }

                foreach ($destinatarios as $destinatario) {
                    $rebotes[] = new ReboteData($destinatario, $fecha, $uid);
                }
            }

            return $rebotes;
        } catch (Throwable $error) {
            report($error);

            return [];
        } finally {
            $this->cerrar();
        }
    }

    private function abrir(): void
    {
        $contexto = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $this->host,
        ]]);

        $conexion = @stream_socket_client(
            "ssl://{$this->host}:{$this->puerto}",
            $numero,
            $mensaje,
            self::ESPERA,
            STREAM_CLIENT_CONNECT,
            $contexto,
        );

        if ($conexion === false) {
            throw new RuntimeException("Sin conexión al buzón: {$mensaje} ({$numero}).");
        }

        stream_set_timeout($conexion, self::ESPERA);
        $this->conexion = $conexion;
        $this->orden = 0;

        // El saludo del servidor.
        $this->linea();

        $this->ordenar(sprintf('LOGIN %s %s', $this->cadena($this->usuario), $this->cadena($this->clave)));
        $this->ordenar('EXAMINE INBOX');
    }

    /**
     * @return list<string>
     */
    private function buscar(int $dias): array
    {
        $desde = (new DateTimeImmutable("-{$dias} days"))->format('d-M-Y');
        $respuesta = $this->ordenar("UID SEARCH SINCE {$desde} FROM \"mailer-daemon\"");

        if (preg_match('/^\* SEARCH([ \d]*)$/m', $respuesta, $encontrado) !== 1) {
            return [];
        }

        return array_values(array_filter(
            explode(' ', trim($encontrado[1])),
            static fn (string $uid): bool => ctype_digit($uid),
        ));
    }

    /**
     * La fecha de llegada y el mensaje completo, sin marcarlo como leido.
     *
     * @return array{0: DateTimeImmutable, 1: string}
     */
    private function traer(string $uid): array
    {
        $respuesta = $this->ordenar("UID FETCH {$uid} (INTERNALDATE BODY.PEEK[])");

        $fecha = preg_match('/INTERNALDATE "([^"]+)"/', $respuesta, $interna) === 1
            ? DateTimeImmutable::createFromFormat('d-M-Y H:i:s O', trim($interna[1]))
            : false;

        return [$fecha ?: new DateTimeImmutable, $respuesta];
    }

    /**
     * Envia una orden y devuelve todo lo que respondio, con los literales
     * (`{n}` y n bytes) incluidos. Falla si el servidor no dice OK.
     */
    private function ordenar(string $orden): string
    {
        $etiqueta = 'R'.(++$this->orden);
        $this->escribir("{$etiqueta} {$orden}\r\n");

        $respuesta = '';

        while (true) {
            $linea = $this->linea();

            if (str_starts_with($linea, "{$etiqueta} ")) {
                if (! str_starts_with(substr($linea, strlen($etiqueta) + 1), 'OK')) {
                    // Sin repetir la orden: el LOGIN lleva la clave.
                    throw new RuntimeException('El buzón rechazó la orden: '.explode(' ', $orden)[0]);
                }

                return $respuesta;
            }

            $respuesta .= $linea;

            if (preg_match('/\{(\d+)\}\r\n$/', $linea, $literal) === 1) {
                $respuesta .= $this->bytes((int) $literal[1]);
            }
        }
    }

    private function linea(): string
    {
        $linea = $this->conexion === null ? false : fgets($this->conexion);

        if ($linea === false) {
            throw new RuntimeException('El buzón cortó la conexión.');
        }

        return $linea;
    }

    private function bytes(int $cuantos): string
    {
        $leido = '';

        while (strlen($leido) < $cuantos) {
            $parte = $this->conexion === null ? false : fread($this->conexion, max(1, $cuantos - strlen($leido)));

            if ($parte === false || $parte === '') {
                throw new RuntimeException('El buzón cortó la conexión.');
            }

            $leido .= $parte;
        }

        return $leido;
    }

    private function escribir(string $texto): void
    {
        if ($this->conexion === null || fwrite($this->conexion, $texto) === false) {
            throw new RuntimeException('Sin conexión al buzón.');
        }
    }

    private function cadena(string $valor): string
    {
        return '"'.addcslashes($valor, '"\\').'"';
    }

    private function cerrar(): void
    {
        $conexion = $this->conexion;

        if ($conexion === null) {
            return;
        }

        // Si ya estaba cerrada, la despedida no llega y no importa.
        @fwrite($conexion, "R0 LOGOUT\r\n");
        fclose($conexion);
        $this->conexion = null;
    }
}
