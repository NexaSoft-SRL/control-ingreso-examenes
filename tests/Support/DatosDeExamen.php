<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Models\Auxiliar;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use App\Modules\Examenes\Domain\Models\ExamenAulaApoyo;
use App\Modules\Examenes\Domain\Models\ExamenGrupo;
use App\Modules\Habilitacion\Domain\Models\Habilitacion;
use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;
use App\Modules\Ingreso\Domain\Models\Ingreso;
use Database\Factories\UserFactory;
use LogicException;

/**
 * Apoyo para las pruebas: un examen con sus inscritos, habilitados,
 * auxiliares e ingresos, armado directamente sobre los modelos. Se combina
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
     * diga otra cosa. La asignatura y el periodo son los del primer grupo;
     * cada aula queda a cargo de quien lo registro.
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
                'encargado_id' => $examen->creado_por,
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
     * Una cuenta de auxiliar con su fila en `auxiliares`. Para que tenga
     * permisos se le pasa la que devuelve `usuarioConPermisos()`.
     */
    protected function auxiliar(?User $cuenta = null, ?User $registradoPor = null): User
    {
        $cuenta ??= UserFactory::new()->createOne();
        $numero = ++$this->secuenciaDeExamen;

        Auxiliar::create([
            'user_id' => $cuenta->getKey(),
            'codigo_universitario' => (string) (202000000 + $numero),
            'registrado_por' => $registradoPor?->getKey(),
        ]);

        return $cuenta;
    }

    /**
     * Deja el aula del examen a cargo de esa cuenta.
     */
    protected function ponerACargo(Examen $examen, Aula $aula, User $cuenta): void
    {
        ExamenAula::where('examen_id', $examen->id)
            ->where('aula_id', $aula->id)
            ->update(['encargado_id' => $cuenta->getKey()]);
    }

    /**
     * Suma a la cuenta como apoyo del aula del examen.
     */
    protected function ponerDeApoyo(Examen $examen, Aula $aula, User $cuenta): void
    {
        $asignada = ExamenAula::where('examen_id', $examen->id)
            ->where('aula_id', $aula->id)
            ->first();

        if (! $asignada instanceof ExamenAula) {
            throw new LogicException('Esa aula no es del examen.');
        }

        ExamenAulaApoyo::create([
            'examen_aula_id' => $asignada->id,
            'user_id' => $cuenta->getKey(),
        ]);
    }

    /**
     * Asienta el ingreso del estudiante por esa aula, registrado por quien
     * creo el examen salvo que se indique otra cuenta.
     */
    protected function ingresar(
        Examen $examen,
        Estudiante $estudiante,
        Aula $aula,
        ?User $registradoPor = null,
    ): Ingreso {
        return Ingreso::create([
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
            'medio' => MedioIdentificacion::Codigo,
            'es_excepcion' => false,
            'registrado_en' => now(),
            'registrado_por' => $registradoPor?->getKey() ?? $examen->creado_por,
        ]);
    }
}
