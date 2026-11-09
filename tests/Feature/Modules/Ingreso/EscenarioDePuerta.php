<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;

/**
 * El escenario comun de las pruebas de Ingreso: un docente con cuenta, un
 * examen de hoy que empezo hace cinco minutos, un aula y sus inscritos.
 * La hora queda fija para que las ventanas no dependan del reloj.
 */
trait EscenarioDePuerta
{
    use DatosAcademicos;
    use DatosDeExamen;
    use UsuarioConPermisos;

    protected User $cuentaDocente;

    protected Docente $docente;

    protected Grupo $grupo;

    protected Aula $aula;

    protected Examen $examen;

    protected function fijarHora(string $instante = '2026-10-12 08:20:00'): void
    {
        $this->travelTo(Carbon::parse($instante));
    }

    /**
     * Un instante en ISO 8601, en la zona de la aplicacion: asi lo
     * devuelve la API.
     */
    protected function iso(string $instante): string
    {
        return Carbon::parse($instante)->format('Y-m-d\TH:i:sP');
    }

    /**
     * Docente con los permisos de su rol que usa este modulo, un grupo
     * suyo, un aula ubicada y un examen abierto en ella.
     */
    protected function armarExamen(): void
    {
        $this->fijarHora();

        $this->cuentaDocente = $this->usuarioConPermisos(['codigos_qr', 'punto_control', 'seguimiento_vivo', 'examenes', 'mis_grupos']);
        $this->docente = $this->docenteConCuenta($this->cuentaDocente, 'Blanco Coca Leticia');
        $this->grupo = $this->grupo($this->docente, $this->asignatura('Introducción a la Programación'), '2');
        $this->aula = $this->aula('691A', $this->edificio(null, 'Edificio Académico 2'), '1° Piso');
        $this->examen = $this->examen($this->docente, [$this->grupo], [$this->aula]);
    }

    /**
     * Una cuenta de auxiliar con los permisos de la puerta y del
     * seguimiento, sin aula asignada todavia.
     */
    protected function cuentaAuxiliar(): User
    {
        return $this->auxiliar($this->usuarioConPermisos(['punto_control', 'seguimiento_vivo']));
    }

    /**
     * Otro docente, con sus permisos, que no tiene que ver con el examen.
     */
    protected function docenteAjeno(): User
    {
        $cuenta = $this->usuarioConPermisos(['codigos_qr', 'punto_control', 'seguimiento_vivo']);
        $this->docenteConCuenta($cuenta);

        return $cuenta;
    }

    /**
     * `$cantidad` inscritos en el grupo, habilitados y con el aula del
     * examen.
     *
     * @return list<Estudiante>
     */
    protected function habilitados(int $cantidad, ?Aula $aula = null, ?Examen $examen = null, ?Grupo $grupo = null): array
    {
        $estudiantes = $this->estudiantesInscritos($grupo ?? $this->grupo, $cantidad);
        $this->habilitar($examen ?? $this->examen, $estudiantes, $aula ?? $this->aula);

        return $estudiantes;
    }

    /**
     * Suma otra aula al examen, a cargo de quien lo registro.
     */
    protected function sumarAula(Aula $aula, ?Examen $examen = null): void
    {
        $examen ??= $this->examen;

        ExamenAula::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'encargado_id' => $examen->creado_por,
        ]);
    }

    protected function emitirCodigo(Estudiante $estudiante, ?Examen $examen = null): string
    {
        $token = Str::random(48);

        DB::table('codigos_qr')->insert([
            'examen_id' => ($examen ?? $this->examen)->id,
            'estudiante_id' => $estudiante->id,
            'token' => $token,
            'emitido_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /**
     * Genera el codigo de acceso del aula del examen.
     */
    protected function ponerCodigoDeAula(string $codigo = '482913', ?Examen $examen = null, ?Aula $aula = null): void
    {
        ExamenAula::where('examen_id', ($examen ?? $this->examen)->id)
            ->where('aula_id', ($aula ?? $this->aula)->id)
            ->update(['codigo_acceso' => $codigo, 'codigo_generado_en' => now()]);
    }

    /**
     * @return TestResponse<Response>
     */
    protected function verificar(User $cuenta, string $identificador, ?Aula $aula = null): TestResponse
    {
        return $this->actingAs($cuenta)->postJson(
            '/api/puerta/aulas/'.($aula ?? $this->aula)->id.'/verificaciones',
            ['identificador' => $identificador],
        );
    }
}
