<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Asignatura;
use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Edificio;
use App\Modules\Academico\Domain\Models\Facultad;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Administracion\Domain\Models\User;
use Database\Factories\UserFactory;

/**
 * Apoyo para las pruebas: la oferta academica minima sobre la que se arma
 * cualquier caso (periodo vigente, facultad, asignatura, docente con
 * cuenta, grupo y aula). Cada llamada crea datos nuevos salvo el periodo
 * y la facultad, que se reutilizan.
 */
trait DatosAcademicos
{
    private int $secuenciaAcademica = 0;

    protected function periodoVigente(): Periodo
    {
        $hoy = now();

        return Periodo::firstOrCreate(
            ['codigo' => '2/'.$hoy->year],
            [
                'anio' => $hoy->year,
                'numero' => 2,
                'tipo' => TipoPeriodo::Semestre2,
                'fecha_inicio' => $hoy->copy()->subDays(60)->toDateString(),
                'fecha_fin' => $hoy->copy()->addDays(60)->toDateString(),
            ],
        );
    }

    protected function facultad(string $clave = 'fcyt'): Facultad
    {
        $catalogo = [
            'fcyt' => ['FCyT', 'Ciencias y Tecnología', '20', '#B90813', 1],
            'fce' => ['FCE', 'Ciencias Económicas', '13', '#107C41', 2],
            'fhce' => ['FHCE', 'Humanidades y Ciencias de la Educación', '18', '#ea580c', 3],
            'fach' => ['FACH', 'Arquitectura y Ciencias del Hábitat', '17', '#154075', 4],
        ];

        [$sigla, $nombre, $codigo, $color, $orden] = $catalogo[$clave]
            ?? [strtoupper($clave), 'Facultad '.$clave, '00', '#000000', 9];

        return Facultad::firstOrCreate(
            ['clave' => $clave],
            [
                'sigla' => $sigla,
                'nombre' => $nombre,
                'codigo_umss' => $codigo,
                'color' => $color,
                'orden' => $orden,
            ],
        );
    }

    protected function asignatura(?string $nombre = null, ?string $codigo = null): Asignatura
    {
        $numero = $this->siguienteAcademico();

        return Asignatura::create([
            'codigo' => $codigo ?? 'ASG'.$numero,
            'nombre' => $nombre ?? 'Asignatura '.$numero,
        ]);
    }

    /**
     * Un docente unido a una cuenta. Para que la cuenta tenga permisos se
     * le pasa la que devuelve `usuarioConPermisos()`.
     */
    protected function docenteConCuenta(?User $cuenta = null, ?string $nombre = null): Docente
    {
        $cuenta ??= UserFactory::new()->createOne();
        $numero = $this->siguienteAcademico();
        $nombre ??= 'Docente De Prueba '.$numero;

        return Docente::create([
            'user_id' => $cuenta->getKey(),
            'nombre_completo' => $nombre,
            'nombre_normalizado' => mb_strtoupper($nombre),
        ]);
    }

    protected function docenteSinCuenta(?string $nombre = null): Docente
    {
        $numero = $this->siguienteAcademico();
        $nombre ??= 'Docente Sin Cuenta '.$numero;

        return Docente::create([
            'nombre_completo' => $nombre,
            'nombre_normalizado' => mb_strtoupper($nombre),
        ]);
    }

    /**
     * Sin docente, el grupo queda «Por designar».
     */
    protected function grupo(
        ?Docente $docente = null,
        ?Asignatura $asignatura = null,
        ?string $codigo = null,
        ?Periodo $periodo = null,
        ?Facultad $facultad = null,
    ): Grupo {
        $numero = $this->siguienteAcademico();

        return Grupo::create([
            'periodo_id' => ($periodo ?? $this->periodoVigente())->id,
            'asignatura_id' => ($asignatura ?? $this->asignatura())->id,
            'facultad_id' => ($facultad ?? $this->facultad())->id,
            'docente_id' => $docente?->id,
            'codigo' => $codigo ?? 'G'.$numero,
        ]);
    }

    protected function edificio(?Facultad $facultad = null, ?string $nombre = null): Edificio
    {
        $facultad ??= $this->facultad();
        $numero = $this->siguienteAcademico();

        return Edificio::create([
            'facultad_id' => $facultad->id,
            'clave' => $facultad->clave.'_blk_'.$numero,
            'nombre' => $nombre ?? 'Edificio '.$numero,
            'poligono' => [
                [-66.14435, -17.39445],
                [-66.14425, -17.39445],
                [-66.14425, -17.39455],
                [-66.14435, -17.39445],
            ],
            'centro_lon' => -66.1443167,
            'centro_lat' => -17.3944833,
        ]);
    }

    /**
     * Sin edificio es un aula sin ubicar, como las que trae la oferta y no
     * estan en el mapa.
     */
    protected function aula(?string $nombre = null, ?Edificio $edificio = null, ?string $piso = null): Aula
    {
        $numero = $this->siguienteAcademico();

        return Aula::create([
            'nombre' => $nombre ?? 'A'.$numero,
            'edificio_id' => $edificio?->id,
            'facultad_id' => $edificio !== null ? $edificio->facultad_id : $this->facultad()->id,
            'piso' => $piso,
        ]);
    }

    private function siguienteAcademico(): int
    {
        return ++$this->secuenciaAcademica;
    }
}
