<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BitacoraSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bitacora_schema_matches_reference_model(): void
    {
        self::assertTrue(
            Schema::hasTable('bitacora_operaciones'),
            'Debe existir la tabla bitacora_operaciones.'
        );

        $expectedColumns = [
            'id',
            'usuario_id',
            'operacion',
            'tabla_afectada',
            'registro_id',
            'descripcion',
            'fecha_operacion',
        ];

        foreach ($expectedColumns as $column) {
            self::assertTrue(
                Schema::hasColumn(
                    'bitacora_operaciones',
                    $column,
                ),
                "Falta la columna {$column} en bitacora_operaciones."
            );
        }

        self::assertFalse(
            Schema::hasColumn(
                'bitacora_operaciones',
                'created_at',
            ),
            'La bitácora no debe utilizar created_at.'
        );

        self::assertFalse(
            Schema::hasColumn(
                'bitacora_operaciones',
                'updated_at',
            ),
            'La bitácora no debe utilizar updated_at.'
        );
    }

    public function test_deleting_user_preserves_audit_record_and_nulls_actor(): void
    {
        $user = UserFactory::new()->createOne();

        $userId = $user->getKey();

        if (! is_int($userId)) {
            $this->fail(
                'El identificador PostgreSQL del usuario debía ser entero.'
            );
        }

        $bitacoraId = DB::table('bitacora_operaciones')
            ->insertGetId([
                'usuario_id' => $userId,
                'operacion' => 'prueba.operacion',
                'tabla_afectada' => 'prueba',
                'registro_id' => 100,
                'descripcion' => 'Registro de prueba de integridad.',
                'fecha_operacion' => now(),
            ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'id' => $bitacoraId,
            'usuario_id' => $userId,
            'operacion' => 'prueba.operacion',
        ]);

        $user->delete();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'id' => $bitacoraId,
            'usuario_id' => null,
            'operacion' => 'prueba.operacion',
        ]);
    }

    public function test_bitacora_rejects_nonexistent_user_reference(): void
    {
        $this->expectException(QueryException::class);

        DB::table('bitacora_operaciones')->insert([
            'usuario_id' => 999999999,
            'operacion' => 'prueba.fk_invalida',
            'tabla_afectada' => 'prueba',
            'registro_id' => null,
            'descripcion' => null,
            'fecha_operacion' => now(),
        ]);
    }
}
