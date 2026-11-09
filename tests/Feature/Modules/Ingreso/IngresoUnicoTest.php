<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\DTOs\IngresoData;
use App\Modules\Ingreso\Application\DTOs\NuevoIngresoData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El ingreso es unico por estudiante y examen, tambien cuando dos puestos
 * lo registran a la vez, y no se modifica ni se borra.
 */
final class IngresoUnicoTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    public function test_the_gateway_turns_the_second_simultaneous_insert_into_null(): void
    {
        [$estudiante] = $this->habilitados(1);
        $ingresos = $this->app->make(IngresoGateway::class);

        $datos = new NuevoIngresoData(
            examenId: $this->examen->id,
            estudianteId: $estudiante->id,
            aulaId: $this->aula->id,
            medio: MedioIdentificacion::Codigo,
            esExcepcion: false,
            operador: new OperadorData($this->cuentaDocente->id),
        );

        $primero = $ingresos->registrar($datos);
        $segundo = $ingresos->registrar($datos);

        $this->assertInstanceOf(IngresoData::class, $primero);
        $this->assertNull($segundo);
        $this->assertDatabaseCount('ingresos', 1);
        // El intento perdedor tampoco deja su fila de bitacora.
        $this->assertSame(1, DB::table('bitacora_operaciones')->where('operacion', 'ingreso.registrar')->count());
    }

    public function test_a_race_between_two_posts_ends_in_one_entry_and_an_already_entered_verdict(): void
    {
        [$estudiante] = $this->habilitados(1);
        $otra = $this->aula('617');
        $this->sumarAula($otra);

        // El otro puesto gana la carrera: su ingreso ya esta asentado,
        // pero este puesto hizo la comprobacion previa un instante antes
        // y no lo vio.
        $this->ingresar($this->examen, $estudiante, $otra);
        $this->cegarLaPrimeraComprobacion();

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'ya_ingreso')
            ->assertJsonPath('dato', 'Ingresó a las 08:20 en el aula 617')
            ->assertJsonMissingPath('ingreso');

        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_a_lost_race_in_a_room_change_is_a_conflict_and_leaves_nothing_behind(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->ingresar($this->examen, $estudiante, $this->aula);
        $this->cegarLaPrimeraComprobacion();

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/excepciones", [
                'tipo' => 'CAMBIO_AULA',
                'motivo' => 'Su aula está llena',
                'estudiante_id' => $estudiante->id,
                'examen_id' => $this->examen->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'YA_INGRESO');

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_the_table_refuses_a_second_entry_for_the_same_student_and_exam(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->ingresar($this->examen, $estudiante, $this->aula);

        $this->expectException(QueryException::class);

        $this->ingresar($this->examen, $estudiante, $this->aula);
    }

    public function test_an_entry_cannot_be_updated(): void
    {
        [$estudiante] = $this->habilitados(1);
        $ingreso = $this->ingresar($this->examen, $estudiante, $this->aula);

        $this->assertRechaza(
            fn (): int => DB::table('ingresos')->where('id', $ingreso->id)->update(['es_excepcion' => true]),
        );

        $this->assertDatabaseHas('ingresos', ['id' => $ingreso->id, 'es_excepcion' => false]);
    }

    public function test_an_entry_cannot_be_deleted(): void
    {
        [$estudiante] = $this->habilitados(1);
        $ingreso = $this->ingresar($this->examen, $estudiante, $this->aula);

        $this->assertRechaza(
            fn (): int => DB::table('ingresos')->where('id', $ingreso->id)->delete(),
        );

        $this->assertDatabaseHas('ingresos', ['id' => $ingreso->id]);
    }

    public function test_no_route_modifies_or_deletes_an_entry(): void
    {
        [$estudiante] = $this->habilitados(1);
        $ingreso = $this->ingresar($this->examen, $estudiante, $this->aula);

        foreach (['putJson', 'patchJson', 'deleteJson'] as $verbo) {
            $this->actingAs($this->cuentaDocente)
                ->{$verbo}("/api/puerta/ingresos/{$ingreso->id}")
                ->assertNotFound();
        }
    }

    /**
     * Sustituye el gateway por uno que no ve el ingreso previo en su
     * primera comprobacion: asi la accion llega a insertar, como cuando
     * dos puestos verifican al mismo estudiante a la vez.
     */
    private function cegarLaPrimeraComprobacion(): void
    {
        $real = $this->app->make(IngresoGateway::class);

        $this->app->instance(IngresoGateway::class, new class($real) implements IngresoGateway
        {
            private bool $ciego = true;

            public function __construct(private readonly IngresoGateway $real) {}

            public function deEstudiante(int $examenId, int $estudianteId): ?IngresoData
            {
                if ($this->ciego) {
                    $this->ciego = false;

                    return null;
                }

                return $this->real->deEstudiante($examenId, $estudianteId);
            }

            public function porId(int $ingresoId): ?IngresoData
            {
                return $this->real->porId($ingresoId);
            }

            public function registrar(NuevoIngresoData $datos): ?IngresoData
            {
                return $this->real->registrar($datos);
            }
        });
    }

    /**
     * La sentencia rechazada deja inservible la transaccion que la
     * contiene: se corre en una propia para poder seguir afirmando.
     *
     * @param  Closure(): int  $operacion
     */
    private function assertRechaza(Closure $operacion): void
    {
        try {
            DB::transaction($operacion);
            $this->fail('La base de datos aceptó la operación.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('trg_ingresos_inmutables', $error->getMessage());
        }
    }
}
