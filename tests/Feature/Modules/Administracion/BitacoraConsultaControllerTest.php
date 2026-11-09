<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Ruta 13: la consulta de la bitacora, paginada, con busqueda, filtros y
 * el texto legible de cada operacion.
 */
final class BitacoraConsultaControllerTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    private User $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrador = $this->usuarioConRol('Administrador', ['name' => 'Rojas Vargas Ana']);
        $this->docente = $this->usuarioConRol('Docente', ['name' => 'Blanco Coca Leticia']);
    }

    /**
     * `fecha` va en la hora de la aplicacion (Cochabamba), como la guarda el sistema.
     */
    private function asiento(
        ?User $autor,
        string $operacion,
        string $fecha,
        ?string $descripcion = null,
    ): int {
        return (int) DB::table('bitacora_operaciones')->insertGetId([
            'usuario_id' => $autor?->getKey(),
            'operacion' => $operacion,
            'tabla_afectada' => 'examenes',
            'registro_id' => 1,
            'descripcion' => $descripcion,
            'fecha_operacion' => $fecha,
        ]);
    }

    public function test_the_operations_are_listed_newest_first_with_a_readable_text(): void
    {
        $anterior = $this->asiento($this->docente, 'examen.registrar', '2026-10-09 08:58:00', 'Examen #1 · Introducción a la Programación');
        $reciente = $this->asiento($this->docente, 'habilitacion.inhabilitar', '2026-10-11 16:40:00', 'Habilitación #19 · Introducción a la Programación');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $reciente,
                        'usuario' => 'Blanco Coca Leticia',
                        'rol' => 'Docente',
                        'operacion' => 'habilitacion.inhabilitar',
                        'operacion_texto' => 'Inhabilitó al estudiante',
                        'entidad' => 'Habilitación #19 · Introducción a la Programación',
                        'fecha' => '2026-10-11T16:40:00-04:00',
                    ],
                    [
                        'id' => $anterior,
                        'usuario' => 'Blanco Coca Leticia',
                        'rol' => 'Docente',
                        'operacion' => 'examen.registrar',
                        'operacion_texto' => 'Registró el examen',
                        'entidad' => 'Examen #1 · Introducción a la Programación',
                        'fecha' => '2026-10-09T08:58:00-04:00',
                    ],
                ],
                'meta' => [
                    'total' => 2,
                    'pagina' => 1,
                    'por_pagina' => 25,
                    'operaciones' => [
                        ['clave' => 'habilitacion.inhabilitar', 'texto' => 'Inhabilitó al estudiante'],
                        ['clave' => 'examen.registrar', 'texto' => 'Registró el examen'],
                    ],
                    'fecha_minima' => '2026-10-09',
                ],
            ]);
    }

    public function test_an_operation_without_user_belongs_to_the_system(): void
    {
        $this->asiento(null, 'respaldo.generar', '2026-10-12 07:00:00', 'Respaldo programado');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.usuario', 'Sistema')
            ->assertJsonPath('data.0.rol', null)
            ->assertJsonPath('data.0.operacion_texto', 'Generó el respaldo');
    }

    public function test_an_operation_without_account_names_who_did_it(): void
    {
        app(BitacoraGateway::class)->registrar(null, 'ingreso.registrar', 'ingresos', 1, 'Ingreso de 202100001.', 'Rojas Vargas Andrea');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora')
            ->assertJsonPath('data.0.usuario', 'Rojas Vargas Andrea');
        $this->getJson('/api/bitacora?buscar=rojas')->assertJsonCount(1, 'data');
    }

    public function test_a_deleted_user_does_not_hide_the_record(): void
    {
        $borrada = $this->usuarioConRol('Auxiliar');
        $id = $this->asiento($borrada, 'ingreso.registrar', '2026-10-12 13:00:00');

        $borrada->delete();

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.usuario', 'Sistema');
    }

    public function test_an_unknown_operation_shows_its_own_key(): void
    {
        $this->asiento($this->docente, 'algo.nuevo', '2026-10-12 13:00:00');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.operacion_texto', 'algo.nuevo')
            ->assertJsonPath('meta.operaciones.0', ['clave' => 'algo.nuevo', 'texto' => 'algo.nuevo']);
    }

    public function test_the_search_looks_at_the_user_name_and_the_description(): void
    {
        $porNombre = $this->asiento($this->docente, 'examen.registrar', '2026-10-12 13:00:00', 'Examen #1 · Cálculo I');
        $porDescripcion = $this->asiento($this->administrador, 'usuario.crear', '2026-10-12 14:00:00', 'Usuario #9 · Núñez Peña Óscar');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?buscar=LETICIA')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $porNombre);

        // Sin tildes ni mayusculas.
        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?buscar='.urlencode('nunez pena'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $porDescripcion)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_the_operations_are_filtered_by_role(): void
    {
        $this->asiento($this->docente, 'examen.registrar', '2026-10-12 13:00:00');
        $delAdministrador = $this->asiento($this->administrador, 'usuario.crear', '2026-10-12 14:00:00');
        $this->asiento(null, 'respaldo.generar', '2026-10-12 15:00:00');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?rol=Administrador')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $delAdministrador)
            ->assertJsonPath('data.0.rol', 'Administrador');
    }

    public function test_the_role_shown_is_the_one_held_when_operating(): void
    {
        app(BitacoraGateway::class)->registrar((int) $this->docente->id, 'examen.registrar', 'examenes', 1, 'Examen #1');
        $this->docente->forceFill([
            'role_id' => DB::table('roles')->where('name', 'Administrador')->value('id'),
        ])->save();

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.rol', 'Docente');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?rol=Docente')
            ->assertJsonCount(1, 'data');
    }

    public function test_the_operations_are_filtered_by_operation(): void
    {
        $esperado = $this->asiento($this->docente, 'examen.registrar', '2026-10-12 13:00:00');
        $this->asiento($this->docente, 'examen.eliminar', '2026-10-12 14:00:00');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?operacion=examen.registrar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $esperado)
            // El desplegable sigue ofreciendo todas las que existen.
            ->assertJsonCount(2, 'meta.operaciones');
    }

    public function test_the_date_range_is_inclusive_and_uses_local_days(): void
    {
        // El dia 21 va de las 00:00 del 21 a las 00:00 del 22, hora local.
        $this->asiento($this->docente, 'antes', '2026-09-20 23:59:59');
        $primero = $this->asiento($this->docente, 'inicio', '2026-09-21 00:00:00');
        $ultimo = $this->asiento($this->docente, 'fin', '2026-09-21 23:59:59');
        $this->asiento($this->docente, 'despues', '2026-09-22 00:00:00');

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?desde=2026-09-21&hasta=2026-09-21')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $ultimo)
            ->assertJsonPath('data.0.fecha', '2026-09-21T23:59:59-04:00')
            ->assertJsonPath('data.1.id', $primero)
            // La fecha minima es la de toda la bitacora, no la del filtro.
            ->assertJsonPath('meta.fecha_minima', '2026-09-20');
    }

    public function test_all_the_filters_can_be_combined(): void
    {
        $esperado = $this->asiento($this->docente, 'examen.registrar', '2026-09-21 16:00:00', 'Examen #1 · Cálculo I');
        $this->asiento($this->docente, 'examen.eliminar', '2026-09-21 16:10:00', 'Examen #1 · Cálculo I');
        $this->asiento($this->administrador, 'examen.registrar', '2026-09-21 16:20:00', 'Examen #2 · Cálculo I');
        $this->asiento($this->docente, 'examen.registrar', '2026-09-22 16:00:00', 'Examen #3 · Cálculo I');
        $this->asiento($this->docente, 'examen.registrar', '2026-09-21 16:30:00', 'Examen #4 · Física');

        $consulta = http_build_query([
            'buscar' => 'calculo',
            'rol' => 'Docente',
            'operacion' => 'examen.registrar',
            'desde' => '2026-09-21',
            'hasta' => '2026-09-21',
        ]);

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?'.$consulta)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $esperado);
    }

    public function test_the_log_is_paginated_on_the_server(): void
    {
        $ids = [];

        foreach (range(1, 5) as $minuto) {
            $ids[] = $this->asiento($this->docente, 'examen.registrar', sprintf('2026-10-12 13:0%d:00', $minuto));
        }

        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?pagina=2&por_pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $ids[2])
            ->assertJsonPath('data.1.id', $ids[1])
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 2);
    }

    public function test_an_empty_log_has_no_minimum_date(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.operaciones', [])
            ->assertJsonPath('meta.fecha_minima', null);
    }

    public function test_the_filters_are_validated(): void
    {
        $this->actingAs($this->administrador)
            ->getJson(
                '/api/bitacora?rol=Responsable&desde=21-09-2026&por_pagina=500'
                .'&operacion='.str_repeat('x', 101)
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rol', 'desde', 'por_pagina', 'operacion'])
            ->assertJsonPath('errors.desde.0', 'La fecha inicial no es válida.');
    }

    public function test_the_end_date_cannot_be_before_the_start_date(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/bitacora?desde=2026-10-12&hasta=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonPath('errors.hasta.0', 'La fecha final no puede ser anterior a la inicial.');
    }

    public function test_a_guest_cannot_read_the_log(): void
    {
        $this->getJson('/api/bitacora')->assertUnauthorized();
    }

    public function test_the_permission_is_required(): void
    {
        $this->actingAs($this->docente)
            ->getJson('/api/bitacora')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'bitacora');
    }
}
