<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\Queries\ConstruirTablaReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

final class ConstruirTablaReporteTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_construye_siete_tablas_con_datos_reales_y_columnas_consistentes(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo(
            $docente,
            $this->asignatura('Física General', '0002070')
        );
        $aula = $this->aula('925');
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);

        $fecha = now();

        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiantes[1]->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_HABILITADO',
            'detalle' => 'Intento rechazado por el punto de control.',
            'ocurrida_en' => $fecha,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);

        $construir = app(ConstruirTablaReporte::class);
        $alcance = AlcanceReporte::deExamen($examen->id);

        $esperados = [
            'horarios' => 1,
            'registrados' => 1,
            'intentos' => 1,
            'estudiantes' => 2,
            'incidencias' => 1,
            'asistencia' => 1,
            'ausentes' => 1,
        ];

        foreach ($esperados as $tipo => $cantidad) {
            $tabla = $construir->execute($tipo, $alcance);

            $this->assertSame($tipo, $tabla->clave);
            $this->assertNotSame('', $tabla->titulo);
            $this->assertNotEmpty($tabla->encabezados);
            $this->assertCount($cantidad, $tabla->filas, $tipo);
            $this->assertContains('Examen ID: '.$examen->id, $tabla->filtros);

            foreach ($tabla->filas as $fila) {
                $this->assertCount(
                    count($tabla->encabezados),
                    $fila,
                    'Columnas inconsistentes: '.$tipo
                );

            }
        }

        $asistencia = $construir->execute('asistencia', $alcance);
        $this->assertSame(
            $estudiantes[0]->codigo_universitario,
            $asistencia->filas[0][0]
        );

        $ausentes = $construir->execute('ausentes', $alcance);
        $this->assertSame(
            $estudiantes[1]->codigo_universitario,
            $ausentes->filas[0][0]
        );

        $intentos = $construir->execute('intentos', $alcance);
        $this->assertSame(
            $estudiantes[1]->codigo_universitario,
            $intentos->filas[0][1]
        );

        $horarios = $construir->execute('horarios', $alcance);
        $this->assertContains('Total de ingresos: 1', $horarios->totales);
    }

    public function test_rechaza_tipo_invalido(): void
    {
        $construir = app(ConstruirTablaReporte::class);

        $this->expectException(InvalidArgumentException::class);

        $construir->execute('desconocido', new AlcanceReporte);
    }

    public function test_reportes_individuales_exigen_examen(): void
    {
        $construir = app(ConstruirTablaReporte::class);

        foreach (['incidencias', 'asistencia', 'ausentes'] as $tipo) {
            try {
                $construir->execute($tipo, new AlcanceReporte);
                $this->fail('Se aceptó un reporte sin examen: '.$tipo);
            } catch (InvalidArgumentException $error) {
                $this->assertSame(
                    'Debe seleccionar un examen.',
                    $error->getMessage()
                );
            }
        }
    }
}
