<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class ExpulsionYAuditoriaPuertaHttpTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_expulsion_requiere_autenticacion(): void
    {
        $this->postJson('/api/aulas/1/punto-control/ingresos/1/expulsion', [
            'motivo' => 'Incumplimiento de normas',
        ])->assertUnauthorized();
    }

    public function test_expulsion_no_permite_ajeno_al_aula(): void
    {
        $operador = $this->usuarioConPermisos(['punto_control']);
        $this->actingAs($operador)->postJson(
            '/api/aulas/1/punto-control/ingresos/999/expulsion',
            ['motivo' => 'Incumplimiento de normas'],
        )->assertNotFound();
        self::assertSame(0, DB::table('expulsiones')->count());
    }

    public function test_motivo_corto_no_es_aceptado(): void
    {
        $operador = $this->usuarioConPermisos(['punto_control']);
        $this->actingAs($operador)->postJson(
            '/api/aulas/1/punto-control/ingresos/1/expulsion',
            ['motivo' => 'abc'],
        )->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
    }
}
