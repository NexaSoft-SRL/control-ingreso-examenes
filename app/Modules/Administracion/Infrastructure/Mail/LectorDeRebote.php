<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Mail;

/**
 * Lee el aviso de un correo no entregado y devuelve las direcciones que lo
 * rechazaron para siempre (codigo 5.x.x). Un aviso de demora (4.x.x) no
 * cuenta: el servidor lo sigue intentando.
 *
 * Primero mira el informe estandar (DSN, RFC 3464: `Final-Recipient`,
 * `Action`, `Status`); si el aviso no lo trae, busca la frase de Gmail
 * («no se entregó a …», «wasn't delivered to …»).
 */
final class LectorDeRebote
{
    private const CORREO = '[A-Za-z0-9._%+\'-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}';

    /**
     * @return list<string>
     */
    public function destinatarios(string $mensaje): array
    {
        $texto = $this->legible($mensaje);

        $informe = $this->delInforme($texto);

        if ($informe !== null) {
            return $informe;
        }

        return $this->delTexto($texto);
    }

    /**
     * Con informe DSN: un bloque por destinatario. null si no hay informe.
     *
     * @return list<string>|null
     */
    private function delInforme(string $texto): ?array
    {
        if (preg_match('/^Final-Recipient:/mi', $texto) !== 1) {
            return null;
        }

        $direcciones = [];

        foreach (preg_split('/\R\s*\R/', $texto) ?: [] as $bloque) {
            if (preg_match('/^Final-Recipient:\s*(?:rfc822;)?\s*<?('.self::CORREO.')>?/mi', $bloque, $final) !== 1
                && preg_match('/^Original-Recipient:\s*(?:rfc822;)?\s*<?('.self::CORREO.')>?/mi', $bloque, $final) !== 1) {
                continue;
            }

            $fallido = preg_match('/^Action:\s*failed/mi', $bloque) === 1;
            $permanente = preg_match('/^Status:\s*5\.\d{1,3}\.\d{1,3}/mi', $bloque) === 1;
            $demora = preg_match('/^Status:\s*4\.\d{1,3}\.\d{1,3}/mi', $bloque) === 1;

            if ($permanente || ($fallido && ! $demora)) {
                $direcciones[] = mb_strtolower($final[1]);
            }
        }

        return array_values(array_unique($direcciones));
    }

    /**
     * Sin informe: la frase del aviso, solo si trae un codigo permanente.
     *
     * @return list<string>
     */
    private function delTexto(string $texto): array
    {
        if (preg_match('/\b5\.\d{1,3}\.\d{1,3}\b/', $texto) !== 1) {
            return [];
        }

        $frase = '/(?:no se entreg[óo] a|was(?:n\'t| not) delivered to|couldn\'t be delivered to)\s*<?('.self::CORREO.')/iu';

        if (preg_match_all($frase, $texto, $coincidencias) < 1) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (string $correo): string => mb_strtolower(rtrim($correo, '.')),
            $coincidencias[1],
        )));
    }

    /**
     * Junta las lineas partidas y decodifica el texto de las partes en
     * quoted-printable o base64, que es como llegan los avisos de Gmail.
     */
    private function legible(string $mensaje): string
    {
        $mensaje = str_replace("\r\n", "\n", $mensaje);
        $partes = [$mensaje];

        if (preg_match_all(
            '/Content-Transfer-Encoding:\s*(quoted-printable|base64)\s*\n(?:[A-Za-z-]+:[^\n]*\n)*\n(.*?)(?=\n--|\z)/si',
            $mensaje,
            $codificadas,
            PREG_SET_ORDER,
        ) > 0) {
            foreach ($codificadas as $parte) {
                $partes[] = strtolower($parte[1]) === 'base64'
                    ? (string) base64_decode(preg_replace('/\s+/', '', $parte[2]) ?? '', true)
                    : quoted_printable_decode($parte[2]);
            }
        }

        return implode("\n\n", $partes);
    }
}
