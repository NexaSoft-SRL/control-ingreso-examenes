<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BitacoraConsultaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_audit_operations(): void
    {
        $actor = UserFactory::new()->createOne();
        $authenticatedUser = UserFactory::new()->createOne();

        $olderId = $this->insertAuditOperation(
            usuarioId: $this->modelId($actor),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-20 10:00:00',
        );

        $newerId = $this->insertAuditOperation(
            usuarioId: $this->modelId($actor),
            operacion: 'asignatura.eliminar',
            fecha: '2026-09-21 10:00:00',
        );

        $response = $this
            ->actingAs($authenticatedUser)
            ->getJson('/api/bitacora');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerId)
            ->assertJsonPath('data.0.operacion', 'asignatura.eliminar')
            ->assertJsonPath('data.0.usuario.id', $this->modelId($actor))
            ->assertJsonPath('data.0.usuario.name', $actor->name)
            ->assertJsonPath('data.0.usuario.email', $actor->email)
            ->assertJsonPath('data.1.id', $olderId);
    }

    public function test_guest_cannot_list_audit_operations(): void
    {
        $this->getJson('/api/bitacora')
            ->assertUnauthorized();
    }

    public function test_can_filter_audit_operations_by_user(): void
    {
        $authenticatedUser = UserFactory::new()->createOne();
        $actorA = UserFactory::new()->createOne();
        $actorB = UserFactory::new()->createOne();

        $expectedId = $this->insertAuditOperation(
            usuarioId: $this->modelId($actorA),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-21 09:00:00',
        );

        $this->insertAuditOperation(
            usuarioId: $this->modelId($actorB),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-21 10:00:00',
        );

        $response = $this
            ->actingAs($authenticatedUser)
            ->getJson(
                '/api/bitacora?usuario_id='.$this->modelId($actorA)
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expectedId)
            ->assertJsonPath(
                'data.0.usuario.id',
                $this->modelId($actorA)
            );
    }

    public function test_can_filter_audit_operations_by_date(): void
    {
        $authenticatedUser = UserFactory::new()->createOne();

        $this->insertAuditOperation(
            usuarioId: $this->modelId($authenticatedUser),
            operacion: 'antes',
            fecha: '2026-09-20 23:59:59',
        );

        $firstExpectedId = $this->insertAuditOperation(
            usuarioId: $this->modelId($authenticatedUser),
            operacion: 'inicio',
            fecha: '2026-09-21 00:00:00',
        );

        $lastExpectedId = $this->insertAuditOperation(
            usuarioId: $this->modelId($authenticatedUser),
            operacion: 'fin',
            fecha: '2026-09-21 23:59:59',
        );

        $this->insertAuditOperation(
            usuarioId: $this->modelId($authenticatedUser),
            operacion: 'despues',
            fecha: '2026-09-22 00:00:00',
        );

        $response = $this
            ->actingAs($authenticatedUser)
            ->getJson('/api/bitacora?fecha=2026-09-21');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $lastExpectedId)
            ->assertJsonPath('data.1.id', $firstExpectedId);
    }

    public function test_can_filter_audit_operations_by_operation(): void
    {
        $authenticatedUser = UserFactory::new()->createOne();

        $expectedId = $this->insertAuditOperation(
            usuarioId: $this->modelId($authenticatedUser),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-21 09:00:00',
        );

        $this->insertAuditOperation(
            usuarioId: $this->modelId($authenticatedUser),
            operacion: 'asignatura.eliminar',
            fecha: '2026-09-21 10:00:00',
        );

        $response = $this
            ->actingAs($authenticatedUser)
            ->getJson(
                '/api/bitacora?operacion=asignatura.registrar'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expectedId)
            ->assertJsonPath(
                'data.0.operacion',
                'asignatura.registrar'
            );
    }

    public function test_can_combine_all_audit_filters(): void
    {
        $authenticatedUser = UserFactory::new()->createOne();
        $actorA = UserFactory::new()->createOne();
        $actorB = UserFactory::new()->createOne();

        $expectedId = $this->insertAuditOperation(
            usuarioId: $this->modelId($actorA),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-21 12:00:00',
        );

        $this->insertAuditOperation(
            usuarioId: $this->modelId($actorA),
            operacion: 'asignatura.eliminar',
            fecha: '2026-09-21 12:10:00',
        );

        $this->insertAuditOperation(
            usuarioId: $this->modelId($actorB),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-21 12:20:00',
        );

        $this->insertAuditOperation(
            usuarioId: $this->modelId($actorA),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-22 12:00:00',
        );

        $query = http_build_query([
            'usuario_id' => $this->modelId($actorA),
            'fecha' => '2026-09-21',
            'operacion' => 'asignatura.registrar',
        ]);

        $response = $this
            ->actingAs($authenticatedUser)
            ->getJson('/api/bitacora?'.$query);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expectedId);
    }

    public function test_deleted_user_does_not_hide_audit_record(): void
    {
        $authenticatedUser = UserFactory::new()->createOne();
        $actor = UserFactory::new()->createOne();

        $auditId = $this->insertAuditOperation(
            usuarioId: $this->modelId($actor),
            operacion: 'asignatura.registrar',
            fecha: '2026-09-21 12:00:00',
        );

        $actor->delete();

        $response = $this
            ->actingAs($authenticatedUser)
            ->getJson('/api/bitacora');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $auditId)
            ->assertJsonPath('data.0.usuario', null);
    }

    public function test_audit_filters_are_validated(): void
    {
        $user = UserFactory::new()->createOne();

        $this
            ->actingAs($user)
            ->getJson(
                '/api/bitacora?usuario_id=no-es-entero'
                .'&fecha=21-09-2026'
                .'&operacion='.str_repeat('x', 101)
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'usuario_id',
                'fecha',
                'operacion',
            ]);
    }

    private function modelId(Model $model): int
    {
        $id = $model->getKey();

        if (! is_int($id)) {
            throw new \LogicException(
                'El modelo no tiene un identificador entero válido.'
            );
        }

        return $id;
    }

    private function insertAuditOperation(
        ?int $usuarioId,
        string $operacion,
        string $fecha,
    ): int {
        return (int) DB::table('bitacora_operaciones')
            ->insertGetId([
                'usuario_id' => $usuarioId,
                'operacion' => $operacion,
                'tabla_afectada' => 'asignaturas',
                'registro_id' => 1,
                'descripcion' => null,
                'fecha_operacion' => $fecha,
            ]);
    }

    public function test_the_log_can_be_filtered_by_a_date_range(): void
    {
        $usuario = UserFactory::new()->createOne();

        DB::table('bitacora_operaciones')->insert([
            [
                'usuario_id' => $usuario->getKey(),
                'operacion' => 'sesion.iniciar',
                'tabla_afectada' => 'usuarios',
                'registro_id' => null,
                'descripcion' => null,
                'fecha_operacion' => '2026-09-14 08:00:00',
            ],
            [
                'usuario_id' => $usuario->getKey(),
                'operacion' => 'estudiante.registrar',
                'tabla_afectada' => 'students',
                'registro_id' => null,
                'descripcion' => null,
                'fecha_operacion' => '2026-09-17 10:00:00',
            ],
            [
                'usuario_id' => $usuario->getKey(),
                'operacion' => 'ambiente.registrar',
                'tabla_afectada' => 'ambientes',
                'registro_id' => null,
                'descripcion' => null,
                'fecha_operacion' => '2026-09-25 16:00:00',
            ],
        ]);

        // El rango incluye los dos extremos.
        $response = $this->actingAs($usuario)
            ->getJson('/api/bitacora?desde=2026-09-14&hasta=2026-09-17')
            ->assertOk();

        $operaciones = $response->json('data');

        self::assertIsArray($operaciones);
        self::assertCount(2, $operaciones);
        $this->assertSame(
            ['estudiante.registrar', 'sesion.iniciar'],
            array_column($operaciones, 'operacion'),
        );
    }

    public function test_the_range_rejects_an_end_before_its_start(): void
    {
        $usuario = UserFactory::new()->createOne();

        $this->actingAs($usuario)
            ->getJson('/api/bitacora?desde=2026-09-20&hasta=2026-09-14')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hasta');
    }
}
