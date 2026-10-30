<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

final class ReportesUniversidadHttpTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_usuario_sin_permiso_universitario_no_puede_acceder(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad')
            ->assertForbidden();

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad/horarios')
            ->assertForbidden();
    }

    public function test_el_listado_universitario_devuelve_examen_y_cifras_reales(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente, $this->asignatura('Cálculo I'));
        $aula = $this->aula('901');
        $estudiantes = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad?examen='.$examen->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('cifras.examenes', 1)
            ->assertJsonPath('cifras.ingresos', 1)
            ->assertJsonPath('cifras.intentos', 0)
            ->assertJsonPath('cifras.estudiantes', 1);
    }

    public function test_consulta_de_horarios_utiliza_los_ingresos_reales(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente, $this->asignatura('Álgebra I'));
        $aula = $this->aula('902');
        $estudiantes = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad/horarios?examen='.$examen->id)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.tramos');
    }

    public function test_reportes_individuales_exigen_examen(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);

        foreach (['incidencias', 'asistencia', 'ausentes'] as $tipo) {
            $this->actingAs($usuario)
                ->getJson('/api/reportes/universidad/'.$tipo)
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Debe seleccionar un examen.');
        }
    }

    public function test_los_filtros_invalidos_son_rechazados(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_universidad']);

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad?por_pagina=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['por_pagina']);

        $this->actingAs($usuario)
            ->getJson('/api/reportes/universidad?desde=2026-10-30&hasta=2026-10-29')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hasta']);
    }
}
