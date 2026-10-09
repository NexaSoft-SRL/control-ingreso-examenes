<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use App\Modules\Examenes\Domain\Models\ExamenGrupo;
use App\Modules\Habilitacion\Domain\Models\Habilitacion;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Apoyo para las pruebas: un examen con sus inscritos, habilitados e
 * ingresos, armado directamente sobre los modelos. Se combina
 * con `DatosAcademicos`, que da los grupos y las aulas.
 */
trait DatosDeExamen
{
    private int $secuenciaDeExamen = 0;

    /**
     * Crea `$cantidad` estudiantes y los inscribe en el grupo.
     *
     * @return list<Estudiante>
     */
    protected function estudiantesInscritos(Grupo $grupo, int $cantidad): array
    {
        $estudiantes = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $numero = ++$this->secuenciaDeExamen;

            $estudiante = Estudiante::create([
                'codigo_universitario' => (string) (202100000 + $numero),
                'documento_identidad' => (string) (7000000 + $numero),
                'nombres' => 'Nombre '.$numero,
                'apellidos' => 'Apellido '.$numero,
                'facultad_id' => $grupo->facultad_id,
                'origen' => OrigenEstudiante::Administracion,
                'verificado' => true,
                'activo' => true,
            ]);

            Inscripcion::create([
                'estudiante_id' => $estudiante->id,
                'grupo_id' => $grupo->id,
                'via' => OrigenEstudiante::Administracion,
            ]);

            $estudiantes[] = $estudiante;
        }

        return $estudiantes;
    }

    /**
     * Un examen registrado por la cuenta del docente, hoy y abierto ahora
     * (empezo hace cinco minutos y dura noventa), salvo que `$atributos`
     * diga otra cosa. La asignatura y el periodo son los del primer grupo.
     *
     * @param  list<Grupo>  $grupos
     * @param  list<Aula>  $aulas
     * @param  array<string, mixed>  $atributos
     */
    protected function examen(
        Docente $docente,
        array $grupos,
        array $aulas = [],
        array $atributos = [],
    ): Examen {
        if ($docente->user_id === null) {
            throw new LogicException('El docente de la prueba necesita una cuenta.');
        }

        if ($grupos === []) {
            throw new LogicException('El examen de la prueba necesita al menos un grupo.');
        }

        $inicio = now()->subMinutes(5);

        $examen = Examen::create(array_merge([
            'periodo_id' => $grupos[0]->periodo_id,
            'asignatura_id' => $grupos[0]->asignatura_id,
            'tipo' => TipoExamen::PrimerParcial,
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
            'normas' => null,
            'creado_por' => $docente->user_id,
        ], $atributos));

        foreach ($grupos as $grupo) {
            ExamenGrupo::create([
                'examen_id' => $examen->id,
                'grupo_id' => $grupo->id,
            ]);
        }

        foreach ($aulas as $aula) {
            ExamenAula::create([
                'examen_id' => $examen->id,
                'aula_id' => $aula->id,
            ]);
        }

        return $examen;
    }

    /**
     * Habilita a los estudiantes y, si se indica un aula, los deja
     * repartidos en ella.
     *
     * @param  list<Estudiante>  $estudiantes
     */
    protected function habilitar(Examen $examen, array $estudiantes, ?Aula $aula = null): void
    {
        foreach ($estudiantes as $estudiante) {
            Habilitacion::updateOrCreate(
                [
                    'examen_id' => $examen->id,
                    'estudiante_id' => $estudiante->id,
                ],
                [
                    'habilitado' => true,
                    'aula_id' => $aula?->id,
                    'motivo' => null,
                    'registrada_por' => $examen->creado_por,
                ],
            );
        }
    }

    /**
     * @param  list<Estudiante>  $estudiantes
     */
    protected function inhabilitar(Examen $examen, array $estudiantes, string $motivo = 'No cumple el requisito.'): void
    {
        foreach ($estudiantes as $estudiante) {
            Habilitacion::updateOrCreate(
                [
                    'examen_id' => $examen->id,
                    'estudiante_id' => $estudiante->id,
                ],
                [
                    'habilitado' => false,
                    'aula_id' => null,
                    'motivo' => $motivo,
                    'registrada_por' => $examen->creado_por,
                ],
            );
        }
    }

    /**
     * Asienta el ingreso del estudiante al examen, registrado por quien lo
     * creo. En este sprint nadie escribe en `ingresos`: la fila se inserta
     * directamente para probar las reglas «con ingresos». Devuelve su id.
     */
    protected function ingresar(Examen $examen, Estudiante $estudiante, ?Aula $aula = null): int
    {
        return DB::table('ingresos')->insertGetId([
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula?->id,
            'registrado_por' => $examen->creado_por,
            'registrado_en' => now(),
        ]);
    }
}
