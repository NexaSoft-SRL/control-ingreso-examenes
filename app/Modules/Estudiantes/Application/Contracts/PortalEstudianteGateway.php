<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Application\DTOs\EstudiantePortalData;
use App\Modules\Estudiantes\Application\DTOs\PlazoFotoData;
use App\Modules\Estudiantes\Domain\Exceptions\CarnetYaRegistradoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;

/**
 * Lo que el estudiante hace por si mismo: entrar con un codigo, ver sus
 * examenes y subir su foto.
 */
interface PortalEstudianteGateway
{
    /**
     * Un estudiante activo del padron, por su codigo universitario.
     */
    public function porCodigo(string $codigoUniversitario): ?EstudiantePortalData;

    public function estudiante(int $estudianteId): ?EstudiantePortalData;

    /**
     * Las carreras para elegir al empadronarse, con su facultad.
     *
     * @return list<array{id: int, nombre: string, facultad_id: int, facultad: string}>
     */
    public function carreras(): array;

    /**
     * Guarda el carnet del estudiante, una sola vez.
     *
     * @throws CarnetYaRegistradoException si ya tiene uno
     * @throws DocumentoEnUsoException si es de otro estudiante
     */
    public function registrarCarnet(int $estudianteId, string $documento): void;

    /**
     * Crea al estudiante con origen «Estudiante», sin verificar.
     *
     * @throws DocumentoEnUsoException si el documento es de otro estudiante
     */
    public function empadronar(
        string $codigoUniversitario,
        string $documentoIdentidad,
        string $nombres,
        string $apellidos,
        int $carreraId,
    ): EstudiantePortalData;

    /**
     * Deja vigente un solo codigo: el nuevo anula los anteriores.
     */
    public function guardarCodigo(int $estudianteId, string $hash, int $minutos): void;

    /**
     * Verdadero si el codigo vigente coincide; lo consume. Cada intento
     * fallido cuenta y, pasado el maximo, el codigo deja de valer.
     */
    public function consumirCodigo(int $estudianteId, string $hash, int $maximoIntentos): bool;

    /**
     * Si ese codigo era uno vigente que un reenvio reemplazo.
     */
    public function fueReemplazado(int $estudianteId, string $hash): bool;

    /**
     * Cuantos intentos le quedan al codigo vigente del estudiante: nulo si
     * no tiene ninguno vigente (vencido, usado o inexistente).
     */
    public function intentosRestantes(string $codigoUniversitario, int $maximoIntentos): ?int;

    /**
     * Los examenes de sus grupos, del mas proximo al mas lejano; los ya
     * rendidos, al final.
     *
     * @return list<array<string, mixed>>
     */
    public function examenes(int $estudianteId): array;

    public function plazo(): ?PlazoFotoData;

    public function fijarPlazo(PlazoFotoData $plazo, int $usuarioId): void;

    /**
     * Las fechas que cubren los periodos vigentes hoy (`AAAA-MM-DD`): el
     * plazo de fotos cae dentro. Nulo si no hay ninguno.
     *
     * @return array{inicio: string, fin: string}|null
     */
    public function fechasVigentes(): ?array;

    /**
     * Cuando subio su ultima foto (`AAAA-MM-DD HH:MM:SS`) y, si un
     * docente le habilito un cambio, hasta que dia y quien.
     *
     * @return array{subida_en: string|null, habilitada_hasta: string|null, habilitada_por: string|null}
     */
    public function estadoFoto(int $estudianteId): array;

    /**
     * Habilita al estudiante a subir o cambiar su foto hasta ese dia.
     */
    public function habilitarFoto(int $estudianteId, string $hasta, int $usuarioId): void;

    /**
     * Pone la foto y devuelve la ruta de la que reemplaza, si habia. Una
     * habilitacion de cambio se usa con ella.
     */
    public function ponerFoto(int $estudianteId, string $ruta): ?string;

    public function rutaFoto(int $estudianteId): ?string;
}
