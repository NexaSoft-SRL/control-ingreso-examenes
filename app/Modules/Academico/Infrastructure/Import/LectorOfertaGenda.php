<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Import;

use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Application\DTOs\AsignaturaOfertaData;
use App\Modules\Academico\Application\DTOs\CarreraOfertaData;
use App\Modules\Academico\Application\DTOs\GrupoOfertaData;
use App\Modules\Academico\Application\DTOs\OfertaFacultadData;
use App\Modules\Academico\Application\DTOs\SesionOfertaData;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;

/**
 * Lee `<facultad>.json` de GENDA: un objeto con una clave por carrera mas
 * `INFO`; dentro, niveles, asignaturas y grupos. Las claves que empiezan
 * con `_` son datos del nivel en que estan, no hijos.
 */
final class LectorOfertaGenda implements FuenteOfertaGateway
{
    private const DIAS = ['LU', 'MA', 'MI', 'JU', 'VI', 'SA'];

    /**
     * @return list<string>
     */
    public function facultades(): array
    {
        $claves = config('umss.facultades');
        $facultades = [];

        foreach (is_array($claves) ? $claves : [] as $clave) {
            if (is_string($clave)) {
                $facultades[] = $clave;
            }
        }

        return $facultades;
    }

    public function leer(string $facultad): OfertaFacultadData
    {
        if (preg_match('/^[a-z]+$/', $facultad) !== 1) {
            throw new FuenteNoDisponibleException("La facultad «{$facultad}» no tiene archivo de oferta.");
        }

        $datos = LectorJson::leer(LectorJson::rutaGenda($facultad.'.json'));

        $info = $datos['INFO'] ?? null;
        $info = is_array($info) ? $info : [];

        $carreras = [];

        foreach ($datos as $nombre => $contenido) {
            if ($nombre === 'INFO' || ! is_string($nombre) || ! is_array($contenido)) {
                continue;
            }

            $carreras[] = $this->carrera($nombre, $contenido);
        }

        if ($carreras === []) {
            throw new FuenteNoDisponibleException("El archivo {$facultad}.json no trae ninguna carrera.");
        }

        return new OfertaFacultadData(
            facultad: $facultad,
            periodoCodigo: $this->periodo($info['SEMESTER'] ?? null),
            fechaFuente: $this->fecha($info['DATE'] ?? null),
            carreras: $carreras,
        );
    }

    /**
     * @param  array<mixed>  $contenido
     */
    private function carrera(string $nombre, array $contenido): CarreraOfertaData
    {
        $asignaturas = [];
        $anual = false;

        foreach ($contenido as $nivel => $materias) {
            if (! is_string($nivel) || str_starts_with($nivel, '_') || ! is_array($materias)) {
                continue;
            }

            if (str_contains(mb_strtoupper($nivel, 'UTF-8'), 'AÑO')) {
                $anual = true;
            }

            foreach ($materias as $materia => $grupos) {
                $materia = (string) $materia;

                if (str_starts_with($materia, '_') || ! is_array($grupos)) {
                    continue;
                }

                $codigo = $this->texto($grupos['_CODE'] ?? null);

                if ($codigo === null) {
                    continue;
                }

                $asignaturas[] = new AsignaturaOfertaData(
                    codigo: $codigo,
                    nombre: trim($materia),
                    nivel: trim($nivel),
                    grupos: $this->grupos($grupos),
                );
            }
        }

        return new CarreraOfertaData(
            codigo: $this->texto($contenido['_CAREER_CODE'] ?? null),
            nombre: trim($nombre),
            anual: $anual,
            asignaturas: $asignaturas,
        );
    }

    /**
     * @param  array<mixed>  $contenido
     * @return list<GrupoOfertaData>
     */
    private function grupos(array $contenido): array
    {
        $grupos = [];

        foreach ($contenido as $codigo => $datos) {
            // PHP convierte en entero las claves numericas («3»).
            $codigo = trim((string) $codigo);

            if ($codigo === '' || str_starts_with($codigo, '_') || ! is_array($datos)) {
                continue;
            }

            $grupos[] = $this->grupo($codigo, $datos);
        }

        return $grupos;
    }

    /**
     * @param  array<mixed>  $datos
     */
    private function grupo(string $codigo, array $datos): GrupoOfertaData
    {
        $dias = $this->lista($datos['DAY'] ?? null);
        $horas = $this->lista($datos['HOUR'] ?? null);
        $aulas = $this->lista($datos['CLASS'] ?? null);
        $auxiliaturas = $this->auxiliaturas($datos['AUX_INDEX'] ?? null);

        $sesiones = [];
        $descartadas = 0;
        $posiciones = max(count($dias), count($horas));

        for ($i = 0; $i < $posiciones; $i++) {
            $dia = mb_strtoupper(trim($dias[$i] ?? ''), 'UTF-8');
            $hora = trim($horas[$i] ?? '');

            // Una fila mal leida trae la hora en el dia, o nada: se descarta.
            if (
                ! in_array($dia, self::DIAS, true)
                || preg_match('/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/', $hora, $partes) !== 1
            ) {
                $descartadas++;

                continue;
            }

            $aula = trim($aulas[$i] ?? '');

            $sesiones[] = new SesionOfertaData(
                dia: $dia,
                horaInicio: sprintf('%02d:%s', (int) $partes[1], $partes[2]),
                horaFin: sprintf('%02d:%s', (int) $partes[3], $partes[4]),
                aula: $aula === '' ? null : $aula,
                esAuxiliatura: in_array($i, $auxiliaturas, true),
            );
        }

        $docente = $datos['PROFESSOR'] ?? null;

        return new GrupoOfertaData(
            codigo: $codigo,
            docente: is_string($docente) ? $docente : null,
            sesiones: $sesiones,
            sesionesDescartadas: $descartadas,
        );
    }

    /**
     * `-1` = ninguna; un entero = esa posicion; una lista = esas posiciones.
     *
     * @return list<int>
     */
    private function auxiliaturas(mixed $indice): array
    {
        $posiciones = [];

        foreach (is_array($indice) ? $indice : [$indice] as $valor) {
            if (is_int($valor) && $valor >= 0) {
                $posiciones[] = $valor;
            }
        }

        return $posiciones;
    }

    /**
     * @return list<string>
     */
    private function lista(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $lista = [];

        foreach ($valor as $elemento) {
            $lista[] = is_scalar($elemento) ? (string) $elemento : '';
        }

        return $lista;
    }

    /**
     * `Semestre 2/2026` o `Gestión 2/2026` -> `2/2026`.
     */
    private function periodo(mixed $semestre): ?string
    {
        if (! is_string($semestre) || preg_match('/(\d)\/(\d{4})/', $semestre, $partes) !== 1) {
            return null;
        }

        return $partes[1].'/'.$partes[2];
    }

    /**
     * `M/D/AAAA` -> `AAAA-MM-DD`.
     */
    private function fecha(mixed $fecha): ?string
    {
        if (! is_string($fecha) || preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', trim($fecha), $partes) !== 1) {
            return null;
        }

        if (! checkdate((int) $partes[1], (int) $partes[2], (int) $partes[3])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $partes[3], (int) $partes[1], (int) $partes[2]);
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
