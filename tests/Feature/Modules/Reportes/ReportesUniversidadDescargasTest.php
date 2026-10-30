<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

final class ReportesUniversidadDescargasTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_usuario_sin_permiso_no_descarga(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);

        $this->actingAs($usuario)
            ->get('/api/reportes/universidad/descargas/horarios/pdf')
            ->assertForbidden();
    }

    public function test_descarga_individual_exige_examen(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad/descargas/asistencia/pdf')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Debe seleccionar un examen.');
    }

    public function test_pdf_y_xlsx_descargan_asistencia_real(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente, $this->asignatura('Física General', '0002070'));
        $aula = $this->aula('942');
        $estudiantes = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);

        $base = '/api/reportes/universidad/descargas/asistencia/';

        $pdf = $this->actingAs($usuario)
            ->get($base.'pdf?examen='.$examen->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $contenidoPdf = $pdf->getContent();
        $this->assertNotFalse($contenidoPdf);
        $this->assertStringStartsWith('%PDF-', $contenidoPdf);

        $disposicion = $pdf->headers->get('Content-Disposition');
        $this->assertNotNull($disposicion);
        $this->assertStringContainsString('attachment;', $disposicion);

        $xlsx = $this->actingAs($usuario)
            ->get($base.'xlsx?examen='.$examen->id)
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $contenido = $xlsx->getContent();
        $this->assertNotFalse($contenido);
        $this->assertStringStartsWith('PK', $contenido);

        $ruta = tempnam(sys_get_temp_dir(), 'tis-http-xlsx-');
        $this->assertNotFalse($ruta);

        try {
            $this->assertNotFalse(file_put_contents($ruta, $contenido));
            $libro = IOFactory::load($ruta);

            try {
                $hoja = $libro->getActiveSheet();
                $this->assertSame('Código SIS', $hoja->getCell('A6')->getValue());
                $this->assertSame(
                    $estudiantes[0]->codigo_universitario,
                    $hoja->getCell('A7')->getValue(),
                );
                $this->assertSame(
                    DataType::TYPE_STRING,
                    $hoja->getCell('A7')->getDataType(),
                );
            } finally {
                $libro->disconnectWorksheets();
            }
        } finally {
            unlink($ruta);
        }
    }

    public function test_invitado_no_puede_descargar_reportes(): void
    {
        $this->getJson('/api/reportes/universidad/descargas/horarios/pdf')
            ->assertUnauthorized();
    }

    public function test_descarga_rechaza_filtros_invalidos(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad/descargas/horarios/pdf?por_pagina=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['por_pagina']);
    }

    public function test_las_seis_descargas_restantes_generan_archivos_reales(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo(
            $docente,
            $this->asignatura('Física General', '0002070')
        );
        $aula = $this->aula('943');
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
            'detalle' => 'Ingreso rechazado.',
            'ocurrida_en' => $fecha,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);

        $esperados = [
            'horarios' => ['Tramo horario', null],
            'registrados' => ['Habilitados', null],
            'intentos' => ['Código SIS', $estudiantes[1]->codigo_universitario],
            'estudiantes' => ['Código SIS', $estudiantes[0]->codigo_universitario],
            'incidencias' => ['Código SIS', $estudiantes[1]->codigo_universitario],
            'ausentes' => ['Código SIS', $estudiantes[1]->codigo_universitario],
        ];

        foreach ($esperados as $tipo => [$encabezado, $codigo]) {
            $base = '/api/reportes/universidad/descargas/'.$tipo.'/';

            $pdf = $this->actingAs($usuario)
                ->get($base.'pdf?examen='.$examen->id)
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $contenidoPdf = $pdf->getContent();
            $this->assertNotFalse($contenidoPdf);
            $this->assertStringStartsWith('%PDF-', $contenidoPdf, $tipo);

            $xlsx = $this->actingAs($usuario)
                ->get($base.'xlsx?examen='.$examen->id)
                ->assertOk()
                ->assertHeader(
                    'Content-Type',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                );

            $contenido = $xlsx->getContent();
            $this->assertNotFalse($contenido);
            $this->assertStringStartsWith('PK', $contenido, $tipo);

            $rutaTemporal = tempnam(sys_get_temp_dir(), 'tis-reporte-');
            $this->assertNotFalse($rutaTemporal);

            try {
                $this->assertNotFalse(
                    file_put_contents($rutaTemporal, $contenido)
                );

                $libro = IOFactory::load($rutaTemporal);

                try {
                    $hoja = $libro->getActiveSheet();
                    $valores = [];

                    foreach ($hoja->toArray() as $fila) {
                        foreach ($fila as $valor) {
                            if (is_string($valor)) {
                                $valores[] = $valor;
                            }
                        }
                    }

                    $this->assertContains(
                        $encabezado,
                        $valores,
                        'Falta encabezado en '.$tipo
                    );

                    if ($codigo !== null) {
                        $this->assertContains(
                            $codigo,
                            $valores,
                            'Falta código SIS real en '.$tipo
                        );
                    }
                } finally {
                    $libro->disconnectWorksheets();
                }
            } finally {
                unlink($rutaTemporal);
            }
        }
    }
}
