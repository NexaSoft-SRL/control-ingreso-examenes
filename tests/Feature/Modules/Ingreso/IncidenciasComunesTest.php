<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La gravedad de una incidencia la elige quien registra: el tipo, o la lista
 * de incidencias comunes del docente, solo la sugiere. Una incidencia no
 * prevista se registra con su propio nombre y se puede guardar en la lista.
 */
final class IncidenciasComunesTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private Aula $otra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();

        $this->otra = $this->aula('617');
        $this->sumarAula($this->otra);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<mixed>
     */
    private function registrar(array $datos, ?User $cuenta = null): array
    {
        $respuesta = $this->actingAs($cuenta ?? $this->cuentaDocente)->postJson("/api/examenes/{$this->examen->id}/incidencias", array_merge([
            'aula_id' => $this->aula->id,
            'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
            'detalle' => 'Celular encendido sobre la mesa',
        ], $datos))->assertCreated();

        $fila = $respuesta->json('data');
        self::assertIsArray($fila);

        return $fila;
    }

    /**
     * @return array<string, string>
     */
    private function catalogo(): array
    {
        $lista = $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias/catalogo")
            ->assertOk()
            ->assertJsonPath('puede_guardar', true)
            ->json('data');
        self::assertIsArray($lista);

        $porNombre = [];

        foreach ($lista as $item) {
            self::assertIsArray($item);
            self::assertIsString($item['nombre']);
            self::assertIsString($item['gravedad']);
            $porNombre[$item['nombre']] = $item['gravedad'];
        }

        return $porNombre;
    }

    public function test_the_catalog_offers_the_system_types_with_their_suggested_severity(): void
    {
        $catalogo = $this->catalogo();

        $this->assertSame('MUY_GRAVE', $catalogo['Suplantación de identidad']);
        $this->assertSame('GRAVE', $catalogo['Dispositivo no permitido']);
        $this->assertSame('LEVE', $catalogo['Cambio de aula']);
        // «Otra» no va en la lista: es la puerta a una no prevista.
        $this->assertArrayNotHasKey('Otra', $catalogo);
    }

    public function test_the_severity_is_the_one_chosen_and_not_the_one_of_the_type(): void
    {
        $fila = $this->registrar(['gravedad' => 'LEVE']);

        $this->assertSame('LEVE', $fila['gravedad']);
        $this->assertDatabaseHas('incidencias', ['id' => $fila['id'], 'tipo' => 'DISPOSITIVO_NO_PERMITIDO', 'gravedad' => 'LEVE']);
    }

    public function test_without_a_choice_the_teacher_preference_for_the_type_is_used(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->putJson('/api/incidencias-comunes', ['tipo' => 'DISPOSITIVO_NO_PERMITIDO', 'gravedad' => 'MUY_GRAVE'])
            ->assertOk();

        $this->assertSame('MUY_GRAVE', $this->catalogo()['Dispositivo no permitido']);
        $this->assertSame('MUY_GRAVE', $this->registrar([])['gravedad']);

        // Volver a la del tipo quita la preferencia.
        $this->actingAs($this->cuentaDocente)
            ->putJson('/api/incidencias-comunes', ['tipo' => 'DISPOSITIVO_NO_PERMITIDO', 'gravedad' => 'GRAVE'])
            ->assertOk();
        $this->assertDatabaseCount('incidencias_comunes', 0);
    }

    public function test_an_unforeseen_incident_is_registered_with_its_name_and_saved_in_the_list(): void
    {
        $fila = $this->registrar([
            'tipo' => 'OTRA',
            'tipo_texto' => 'Se comunicó por señas',
            'gravedad' => 'GRAVE',
            'guardar_comun' => true,
        ]);

        $this->assertSame('Se comunicó por señas', $fila['tipo_texto']);
        $this->assertSame('GRAVE', $fila['gravedad']);
        $this->assertSame('GRAVE', $this->catalogo()['Se comunicó por señas']);

        // La segunda vez, sin elegir, se sugiere la guardada (sin distinguir mayúsculas).
        $this->assertSame('GRAVE', $this->registrar(['tipo' => 'OTRA', 'tipo_texto' => 'se comunicó por SEÑAS'])['gravedad']);
        $this->assertDatabaseCount('incidencias_comunes', 1);

        // En la bitácora del examen va con su nombre y su gravedad.
        // (el rol de prueba es uno solo: así suma el permiso de reportes)
        $this->usuarioConPermisos(['reportes_examenes']);
        $sucesos = $this->actingAs($this->cuentaDocente)
            ->getJson("/api/reportes/examenes/{$this->examen->id}/bitacora")
            ->assertOk()
            ->json('data');
        self::assertIsArray($sucesos);
        $propios = array_values(array_filter(
            $sucesos,
            static fn (mixed $s): bool => is_array($s) && is_string($s['titulo']) && mb_strtolower($s['titulo']) === 'se comunicó por señas',
        ));
        $this->assertCount(2, $propios);
        $this->assertSame(['GRAVE', 'GRAVE'], array_column($propios, 'gravedad'));
    }

    public function test_an_assistant_is_offered_the_list_of_the_teacher_of_the_exam(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->putJson('/api/incidencias-comunes', ['nombre' => 'Uso de corrector', 'gravedad' => 'LEVE'])
            ->assertOk();

        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerACargo($this->examen, $this->otra, $auxiliar);

        $lista = $this->actingAs($auxiliar)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias/catalogo")
            ->assertOk()
            ->json('data');
        self::assertIsArray($lista);
        $this->assertContains('Uso de corrector', array_map(static fn (mixed $i): mixed => is_array($i) ? $i['nombre'] : null, $lista));

        $fila = $this->registrar(['aula_id' => $this->otra->id, 'tipo' => 'OTRA', 'tipo_texto' => 'Uso de corrector'], $auxiliar);
        $this->assertSame('LEVE', $fila['gravedad']);
    }

    public function test_the_teacher_corrects_the_severity_and_the_name(): void
    {
        $id = $this->registrar([])['id'];
        self::assertIsInt($id);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/correccion", [
                'tipo' => 'OTRA',
                'tipo_texto' => 'Hojas de otro examen',
                'gravedad' => 'MUY_GRAVE',
                'detalle' => 'Traía las hojas del examen de la mañana',
            ])
            ->assertOk()
            ->assertJsonPath('data.gravedad', 'MUY_GRAVE')
            ->assertJsonPath('data.tipo_texto', 'Hojas de otro examen');

        // Vuelto a un tipo del sistema, pierde el nombre propio.
        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/correccion", [
                'tipo' => 'MATERIAL_NO_PERMITIDO',
                'detalle' => 'Traía las hojas del examen de la mañana',
            ])
            ->assertOk()
            ->assertJsonPath('data.gravedad', 'MUY_GRAVE')
            ->assertJsonPath('data.tipo_texto', 'Material no permitido');
    }

    public function test_each_account_manages_only_its_own_list(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->putJson('/api/incidencias-comunes', ['nombre' => 'Llegó sin carnet', 'gravedad' => 'LEVE'])
            ->assertOk();
        $id = DB::table('incidencias_comunes')->value('id');
        self::assertIsInt($id);

        $this->actingAs($this->docenteAjeno())->deleteJson("/api/incidencias-comunes/{$id}")->assertNotFound();
        $this->actingAs($this->cuentaDocente)->deleteJson("/api/incidencias-comunes/{$id}")->assertOk()->assertJsonPath('message', 'Quitada.');
        $this->assertDatabaseCount('incidencias_comunes', 0);

        $this->actingAs($this->cuentaDocente)
            ->putJson('/api/incidencias-comunes', ['nombre' => 'ab', 'gravedad' => 'ENORME'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'gravedad']);
    }
}
