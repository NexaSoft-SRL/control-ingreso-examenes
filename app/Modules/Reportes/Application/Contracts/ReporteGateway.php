<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\DTOs\ExamenReporteData;
use App\Modules\Reportes\Application\DTOs\FilaAsistencia;
use App\Modules\Reportes\Application\DTOs\FilaAusente;
use App\Modules\Reportes\Application\DTOs\FilaEstudianteGrupo;
use App\Modules\Reportes\Application\DTOs\FilaExamenReporte;
use App\Modules\Reportes\Application\DTOs\FilaIncidencia;
use App\Modules\Reportes\Application\DTOs\FilaIntento;
use App\Modules\Reportes\Application\DTOs\FilaMiExamen;
use App\Modules\Reportes\Application\DTOs\FilaRegistrados;
use App\Modules\Reportes\Application\DTOs\FilaResumenEstudiante;
use App\Modules\Reportes\Application\DTOs\GrupoReporteData;
use App\Modules\Reportes\Application\DTOs\ReporteHorarios;

/**
 * Las lecturas de los reportes. Ninguna escribe.
 */
interface ReporteGateway
{
    /**
     * Cuantos examenes abarca el alcance.
     */
    public function contarExamenes(AlcanceReporte $alcance): int;

    /**
     * Una pagina de los examenes del alcance, por fecha y hora.
     *
     * @return list<FilaExamenReporte>
     */
    public function examenes(AlcanceReporte $alcance, int $pagina, int $porPagina): array;

    /**
     * Examenes del alcance por sigla de facultad; trae las facultades sin
     * examenes en cero.
     *
     * @return array<string, int>
     */
    public function examenesPorFacultad(AlcanceReporte $alcance): array;

    public function contarIngresos(AlcanceReporte $alcance): int;

    public function contarIntentos(AlcanceReporte $alcance): int;

    /**
     * Como se repartieron los ingresos en el tiempo.
     */
    public function horarios(AlcanceReporte $alcance): ReporteHorarios;

    /**
     * Habilitados y registrados, una fila por examen y aula.
     *
     * @return list<FilaRegistrados>
     */
    public function registrados(AlcanceReporte $alcance): array;

    /**
     * Lo que la puerta rechazo: incidencias del sistema de los examenes
     * del alcance, mas las que no se pudieron atribuir a un examen y
     * ocurrieron en un aula y en la ventana de uno del alcance.
     *
     * @return list<FilaIntento>
     */
    public function intentos(AlcanceReporte $alcance): array;

    /**
     * Cuantos estudiantes figuran en el resumen por estudiante.
     */
    public function contarEstudiantes(AlcanceReporte $alcance): int;

    /**
     * Una fila por estudiante con lo que tuvo en los examenes del alcance:
     * examenes que le tocaban o a los que ingreso, ingresos, rechazos de
     * la puerta, expulsiones e incidencias reportadas. Por apellidos y
     * nombres.
     *
     * @return list<FilaResumenEstudiante>
     */
    public function resumenPorEstudiante(AlcanceReporte $alcance): array;

    /**
     * Todo lo asentado en el examen, por hora: lo que reportaron las
     * personas (expulsiones y situaciones excepcionales incluidas) y lo
     * que rechazo la puerta, tambien lo que quedo sin examen pero ocurrio
     * en una de sus aulas dentro de su ventana.
     *
     * @return list<FilaIncidencia>
     */
    public function incidenciasDeExamen(int $examenId): array;

    /**
     * Quienes ingresaron al examen, con su hora.
     *
     * @return list<FilaAsistencia>
     */
    public function asistencia(int $examenId): array;

    /**
     * Habilitados sin ingreso. El no habilitado no es un ausente.
     *
     * @return list<FilaAusente>
     */
    public function ausentes(int $examenId): array;

    /**
     * Nombres legibles de lo filtrado, para el encabezado y el nombre del
     * archivo: `periodo` (codigo), `facultad` (sigla), `examen`.
     *
     * @return array<string, string>
     */
    public function nombresDelAlcance(AlcanceReporte $alcance): array;

    public function examen(int $examenId): ?ExamenReporteData;

    /**
     * Los examenes de los que la cuenta es docente (los registro o es
     * docente de alguno de sus grupos), por fecha y hora. Sin periodo, los
     * de los periodos vigentes.
     *
     * @return list<FilaMiExamen>
     */
    public function examenesDelDocente(int $usuarioId, ?int $periodoId): array;

    public function grupo(int $grupoId): ?GrupoReporteData;

    /**
     * Los inscritos del grupo, por apellidos y nombres.
     *
     * @return list<FilaEstudianteGrupo>
     */
    public function estudiantesDeGrupo(int $grupoId): array;
}
