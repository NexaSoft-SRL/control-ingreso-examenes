<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Models\Asignatura;
use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;

/**
 * Lo que repiten las pruebas del modulo: un docente con el permiso de la
 * pantalla y el cuerpo del asistente de registro.
 */
trait ArmaExamenes
{
    use DatosAcademicos;
    use DatosDeExamen;
    use UsuarioConPermisos;

    protected function docente(?string $nombre = null): Docente
    {
        return $this->docenteConCuenta($this->usuarioConPermisos(['examenes']), $nombre);
    }

    protected function cuenta(Docente $docente): User
    {
        return User::findOrFail($docente->user_id);
    }

    protected function auxiliarConRol(): User
    {
        return $this->usuarioConRol('Auxiliar');
    }

    /**
     * La fecha de dentro de `$dias` dias, pasada al lunes si cae domingo:
     * no se registran examenes los domingos, y la prueba no debe depender de
     * que dia de la semana se corre.
     */
    protected function diaHabil(int $dias): string
    {
        $fecha = now()->addDays($dias);

        return ($fecha->isSunday() ? $fecha->addDay() : $fecha)->toDateString();
    }

    /**
     * @param  list<Grupo>  $grupos
     * @param  list<Aula>  $aulas
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    protected function cuerpo(Asignatura $asignatura, array $grupos, array $aulas = [], array $cambios = []): array
    {
        return array_merge([
            'asignatura_id' => $asignatura->id,
            'tipo' => 'PRIMER_PARCIAL',
            'fecha' => $this->diaHabil(3),
            'hora_inicio' => '08:15',
            'duracion_minutos' => 90,
            'normas' => 'Sin celular.',
            'grupos' => array_map(static fn (Grupo $grupo): int => $grupo->id, $grupos),
            'aulas' => array_map(
                static fn (Aula $aula): array => ['aula_id' => $aula->id],
                $aulas,
            ),
        ], $cambios);
    }
}
