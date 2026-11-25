<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Domain\Models\Ingreso;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Requisito 29: el ingreso es inmutable y una corrección se asienta como una
 * anulación que conserva el registro original. El estudiante anulado deja de
 * contar como ingresado y puede volver a pasar por la puerta.
 */
final class AnulacionDeIngresoTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private Ingreso $ingreso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
        // El rol de prueba es compartido: el docente también ve sus reportes.
        $this->usuarioConPermisos(['reportes_examenes']);

        [$estudiante] = $this->habilitados(2);
        $this->ingreso = $this->ingresar($this->examen, $estudiante, $this->aula);
    }

    private function ruta(?int $ingreso = null): string
    {
        return '/api/puerta/ingresos/'.($ingreso ?? $this->ingreso->id).'/anulacion';
    }

    public function test_an_entry_is_cancelled_with_its_reason_and_the_original_stays(): void
    {
        $antes = $this->ingreso->fresh()?->getAttributes();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Se leyó el QR de otro estudiante'])
            ->assertOk()
            ->assertJsonPath('message', 'Ingreso anulado.');

        $this->assertDatabaseHas('anulaciones_ingreso', [
            'ingreso_id' => $this->ingreso->id,
            'motivo' => 'Se leyó el QR de otro estudiante',
            'anulado_por' => $this->cuentaDocente->id,
        ]);
        // El registro original no se toca ni se borra.
        $this->assertSame($antes, $this->ingreso->fresh()?->getAttributes());
        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_the_cancellation_is_written_to_the_audit_log(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Se leyó el QR de otro estudiante'])
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'ingreso.anular',
            'tabla_afectada' => 'anulaciones_ingreso',
        ]);
    }

    public function test_the_reason_is_required_and_has_three_to_five_hundred_characters(): void
    {
        foreach (['', 'no', str_repeat('a', 501)] as $motivo) {
            $this->actingAs($this->cuentaDocente)
                ->postJson($this->ruta(), ['motivo' => $motivo])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['motivo']);
        }

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => ''])
            ->assertJsonPath('errors.motivo.0', 'Obligatorio');
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'no'])
            ->assertJsonPath('errors.motivo.0', 'Mínimo 3 caracteres');

        $this->assertDatabaseCount('anulaciones_ingreso', 0);
    }

    public function test_an_entry_is_cancelled_only_once(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertOk();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Otra vez'])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'YA_ANULADO');

        $this->assertDatabaseCount('anulaciones_ingreso', 1);
    }

    public function test_an_unknown_entry_is_not_found(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(999999), ['motivo' => 'Entró por error'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Ingreso no encontrado.');
    }

    public function test_only_the_teacher_of_the_exam_cancels_not_an_assistant_nor_another_teacher(): void
    {
        $this->postJson($this->ruta(), ['motivo' => 'Entró por error'])->assertUnauthorized();

        $this->actingAs(UserFactory::new()->createOne())
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertForbidden();

        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerDeApoyo($this->examen, $this->aula, $auxiliar);

        $this->actingAs($auxiliar)
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->actingAs($this->docenteAjeno())
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseCount('anulaciones_ingreso', 0);
    }

    public function test_it_cannot_be_cancelled_after_the_day_of_the_exam(): void
    {
        $this->travel(1)->days();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'FUERA_DE_DIA');

        $this->assertDatabaseCount('anulaciones_ingreso', 0);
    }

    public function test_the_cancelled_student_no_longer_counts_as_entered(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/en-curso")
            ->assertJsonPath('cifras.ingresaron', 1);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertOk();

        // El seguimiento en vivo, la lista de la puerta y los reportes.
        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/en-curso")
            ->assertJsonPath('cifras.ingresaron', 0);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertOk()
            ->assertJsonPath('total_ingresos', 0)
            // La lista conserva la fila, marcada como anulada.
            ->assertJsonCount(1, 'ingresos')
            ->assertJsonPath('ingresos.0.anulado', true)
            ->assertJsonPath('ingresos.0.motivo_anulacion', 'Entró por error');

        $this->actingAs($this->cuentaDocente)
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonPath('data.0.presentes', 0)
            ->assertJsonPath('data.0.ausentes', 2)
            // Los anulados se cuentan aparte, sin inflar los presentes.
            ->assertJsonPath('data.0.anulados', 1);
    }

    public function test_the_cancelled_student_can_pass_the_door_again(): void
    {
        $codigo = DB::table('estudiantes')->where('id', $this->ingreso->estudiante_id)->value('codigo_universitario');
        self::assertIsString($codigo);

        $this->verificar($this->cuentaDocente, $codigo)
            ->assertJsonPath('veredicto', 'ya_ingreso');

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertOk();

        $this->verificar($this->cuentaDocente, $codigo)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido');

        // El historial guarda los dos ingresos; solo el nuevo es vigente.
        $this->assertDatabaseCount('ingresos', 2);
        $this->assertDatabaseHas('ingresos', [
            'estudiante_id' => $this->ingreso->estudiante_id,
            'intento' => 2,
        ]);
        $this->assertSame(1, DB::table('ingresos_vigentes')->where('estudiante_id', $this->ingreso->estudiante_id)->count());

        // Y ahora sí cuenta como ingresado.
        $this->verificar($this->cuentaDocente, $codigo)
            ->assertJsonPath('veredicto', 'ya_ingreso');
    }

    public function test_the_exam_log_keeps_the_entry_and_adds_the_cancellation(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertOk();

        $sucesos = $this->actingAs($this->cuentaDocente)
            ->getJson("/api/reportes/examenes/{$this->examen->id}/bitacora")
            ->assertOk()
            ->json('data');
        self::assertIsArray($sucesos);

        $titulos = collect($sucesos)
            ->filter(static fn (mixed $s): bool => is_array($s) && $s['categoria'] === 'ingreso')
            ->pluck('titulo')
            ->all();

        $this->assertSame(['Ingresó', 'Ingreso anulado'], array_values($titulos));
    }

    public function test_the_original_entry_and_its_cancellation_cannot_be_changed_or_deleted(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Entró por error'])
            ->assertOk();

        foreach ([
            fn () => DB::table('ingresos')->where('id', $this->ingreso->id)->update(['medio' => 'QR']),
            fn () => DB::table('ingresos')->where('id', $this->ingreso->id)->delete(),
            fn () => DB::table('anulaciones_ingreso')->update(['motivo' => 'Otro motivo']),
            fn () => DB::table('anulaciones_ingreso')->delete(),
        ] as $operacion) {
            try {
                DB::transaction($operacion);
                $this->fail('La base de datos aceptó la operación.');
            } catch (QueryException $error) {
                $this->assertStringContainsString('inmutable', $error->getMessage());
            }
        }

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('anulaciones_ingreso', 1);
    }
}
