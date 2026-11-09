<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Ingreso\Domain\Models\Ingreso;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;

/**
 * Lo que comparten las pruebas de reportes: las cuentas con el reparto
 * real de permisos, las incidencias que asienta la puerta y la lectura de
 * la hoja de calculo descargada. El reloj queda fijo en un lunes a las
 * 08:20 para que las horas de los ingresos sean previsibles.
 */
trait EscenarioDeReportes
{
    use DatosAcademicos;
    use DatosDeExamen;
    use UsuarioConPermisos;

    private const AHORA = '2026-10-12 08:20:00';

    private const HOY = '2026-10-12';

    protected function administrador(): User
    {
        return $this->usuarioConRol('Administrador');
    }

    /**
     * Un docente con cuenta y el reparto real de su rol.
     */
    protected function docente(?string $nombre = null): Docente
    {
        return $this->docenteConCuenta($this->usuarioConRol('Docente'), $nombre);
    }

    protected function cuentaDe(Docente $docente): User
    {
        return User::query()->findOrFail($docente->user_id);
    }

    /**
     * Un rechazo de la puerta. Sin examen es un intento que el sistema no
     * pudo atribuir.
     */
    protected function intento(
        ?Examen $examen,
        Aula $aula,
        ?Estudiante $estudiante,
        string $causa = 'NO_INSCRITO',
        ?Examen $correspondiente = null,
        ?string $ocurridaEn = null,
        ?string $texto = null,
    ): void {
        DB::table('incidencias')->insert([
            'examen_id' => $examen?->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante?->id,
            'estudiante_texto' => $texto,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => $causa,
            'examen_correspondiente_id' => $correspondiente?->id,
            'detalle' => 'La puerta rechazó el ingreso.',
            'ocurrida_en' => $ocurridaEn ?? now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function expulsar(Ingreso $ingreso): void
    {
        DB::table('incidencias')->insert([
            'examen_id' => $ingreso->examen_id,
            'aula_id' => $ingreso->aula_id,
            'estudiante_id' => $ingreso->estudiante_id,
            'ingreso_id' => $ingreso->id,
            'tipo' => 'EXPULSION',
            'origen' => 'PERSONA',
            'detalle' => 'Copiaba con el teléfono.',
            'ocurrida_en' => now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Algo que reporto una persona en el examen: quien lo registro o, si
     * no se indica cuenta ni acceso, quien registro el examen. Con
     * `$acceso`, alguien que entro con el codigo del aula.
     */
    protected function reportar(
        Examen $examen,
        Aula $aula,
        string $tipo,
        string $detalle,
        ?Estudiante $estudiante = null,
        ?User $por = null,
        ?string $texto = null,
        ?string $acceso = null,
    ): void {
        $accesoId = $acceso === null ? null : DB::table('accesos_aula')->insertGetId([
            'examen_aula_id' => DB::table('examen_aula')
                ->where('examen_id', $examen->id)
                ->where('aula_id', $aula->id)
                ->value('id'),
            'nombre' => $acceso,
        ]);

        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante?->id,
            'estudiante_texto' => $texto,
            'tipo' => $tipo,
            'origen' => 'PERSONA',
            'detalle' => $detalle,
            'ocurrida_en' => now()->toDateTimeString(),
            'reportada_por' => $acceso === null ? ($por?->getKey() ?? $examen->creado_por) : null,
            'acceso_aula_id' => $accesoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  TestResponse<Response>  $respuesta
     */
    protected function contenido(TestResponse $respuesta): string
    {
        $contenido = $respuesta->getContent();

        self::assertIsString($contenido);

        return $contenido;
    }

    /**
     * Las filas de la hoja descargada, cada celda como texto.
     *
     * @param  TestResponse<Response>  $respuesta
     * @return list<list<string>>
     */
    protected function hoja(TestResponse $respuesta): array
    {
        $respuesta->assertOk();

        $archivo = tempnam(sys_get_temp_dir(), 'reporte').'.xlsx';
        file_put_contents($archivo, $this->contenido($respuesta));

        $celdas = IOFactory::load($archivo)->getActiveSheet()->toArray();

        unlink($archivo);

        $filas = [];

        foreach ($celdas as $fila) {
            $textos = [];

            foreach ($fila as $celda) {
                $textos[] = is_scalar($celda) ? (string) $celda : '';
            }

            $filas[] = $textos;
        }

        return $filas;
    }

    /**
     * Las filas de datos de la hoja: las que siguen al encabezado, hasta
     * la primera linea en blanco.
     *
     * @param  list<list<string>>  $hoja
     * @return list<list<string>>
     */
    protected function datos(array $hoja, string $primerEncabezado): array
    {
        $datos = [];
        $dentro = false;

        foreach ($hoja as $fila) {
            if (! $dentro) {
                $dentro = ($fila[0] ?? '') === $primerEncabezado;

                continue;
            }

            if (implode('', $fila) === '') {
                break;
            }

            $datos[] = $fila;
        }

        return $datos;
    }

    /**
     * Todo el texto de la hoja, para buscar titulos, filtros y totales.
     *
     * @param  list<list<string>>  $hoja
     */
    protected function texto(array $hoja): string
    {
        return implode("\n", array_map(
            static fn (array $fila): string => trim(implode(' | ', $fila), ' |'),
            $hoja,
        ));
    }
}
