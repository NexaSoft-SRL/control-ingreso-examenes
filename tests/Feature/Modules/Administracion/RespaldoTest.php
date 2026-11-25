<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Rutas 15 a 17: el respaldo se genera a demanda y de forma programada,
 * se descarga, la restauracion repone los datos y todo queda asentado.
 */
final class RespaldoTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        // Los respaldos de las pruebas no tienen por qué caer en el disco
        // del proyecto.
        Storage::fake('local');

        $this->administrador = $this->usuarioConRol('Administrador');
    }

    private function estudiante(string $codigo): Estudiante
    {
        return Estudiante::query()->create([
            'codigo_universitario' => $codigo,
            'documento_identidad' => 'D'.$codigo,
            'nombres' => 'Kevin René',
            'apellidos' => "Alvarado O'Connor",
            'origen' => 'ADMINISTRACION',
            'verificado' => true,
            'activo' => true,
        ]);
    }

    /**
     * @return array{id: int, archivo: string}
     */
    private function generar(): array
    {
        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/respaldos')
            ->assertCreated();

        $id = $respuesta->json('data.id');
        $archivo = $respuesta->json('data.archivo');

        self::assertIsInt($id);
        self::assertIsString($archivo);

        return ['id' => $id, 'archivo' => $archivo];
    }

    public function test_the_backup_is_generated_on_demand(): void
    {
        $this->travelTo('2026-10-12 10:30:15');
        $this->estudiante('202100001');

        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/respaldos')
            ->assertCreated()
            ->assertJsonPath('message', 'Respaldo generado.')
            ->assertJsonPath('data.fecha', '2026-10-12T10:30:15-04:00')
            ->assertJsonPath('data.tipo', 'Puntual')
            ->assertJsonPath('data.estado', 'correcto')
            ->assertJsonPath('data.restaurado', false)
            ->assertJsonPath('data.archivo', 'respaldo-20261012-103015.sql');

        Storage::disk('local')->assertExists('respaldos/respaldo-20261012-103015.sql');

        $contenido = Storage::disk('local')->get('respaldos/respaldo-20261012-103015.sql');

        self::assertIsString($contenido);
        $this->assertStringContainsString('TRUNCATE', $contenido);
        $this->assertStringContainsString('202100001', $contenido);
        $this->assertStringContainsString("O''Connor", $contenido);
        $this->assertStringContainsString('COMMIT;', $contenido);
        $this->assertSame(strlen($contenido), $respuesta->json('data.tamano_bytes'));

        $this->assertDatabaseHas('respaldos', [
            'id' => $respuesta->json('data.id'),
            'archivo' => 'respaldo-20261012-103015.sql',
            'tipo' => 'PUNTUAL',
            'estado' => 'CORRECTO',
            'generado_por' => $this->administrador->getKey(),
            'error' => null,
        ]);
    }

    public function test_two_backups_in_the_same_second_do_not_share_a_file(): void
    {
        $this->travelTo('2026-10-12 10:30:15');

        $uno = $this->generar();
        $otro = $this->generar();

        $this->assertSame('respaldo-20261012-103015.sql', $uno['archivo']);
        $this->assertSame('respaldo-20261012-103016.sql', $otro['archivo']);
        $this->assertDatabaseCount('respaldos', 2);
    }

    public function test_the_backup_is_also_generated_on_a_schedule(): void
    {
        $this->estudiante('202100001');

        // La tarea programada llama a la misma orden de consola.
        $this->assertSame(0, Artisan::call('respaldo:generar'));

        $this->assertCount(1, Storage::disk('local')->files('respaldos'));

        // Sin usuario: lo hizo el planificador, no una persona.
        $this->assertDatabaseHas('respaldos', [
            'tipo' => 'PROGRAMADO',
            'estado' => 'CORRECTO',
            'generado_por' => null,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'respaldo.generar',
            'usuario_id' => null,
        ]);
    }

    public function test_the_nightly_task_is_registered(): void
    {
        $this->assertSame(0, Artisan::call('schedule:list'));
        $this->assertStringContainsString('respaldo:generar', Artisan::output());
    }

    public function test_the_history_and_the_working_tables_stay_out_of_the_backup(): void
    {
        $this->generar();
        $segundo = $this->generar();

        $contenido = Storage::disk('local')->get('respaldos/'.$segundo['archivo']);

        self::assertIsString($contenido);

        foreach (['respaldos', 'sessions', 'cache', 'jobs', 'failed_jobs', 'password_reset_tokens', 'migrations'] as $tabla) {
            $this->assertStringNotContainsString('"'.$tabla.'"', $contenido);
        }

        $this->assertStringContainsString('"users"', $contenido);
        $this->assertStringContainsString('"periodos"', $contenido);
    }

    public function test_restoring_brings_the_data_back(): void
    {
        $this->travelTo('2026-10-12 03:00:00');
        $this->estudiante('202100001');
        $respaldo = $this->generar();

        // Se pierde un estudiante y aparece otro que no estaba.
        Estudiante::query()->where('codigo_universitario', '202100001')->delete();
        $this->estudiante('202100002');

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertOk()
            ->assertJsonPath('message', 'Restaurado al 12 oct 2026, 03:00.');

        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '202100001',
            'apellidos' => "Alvarado O'Connor",
        ]);
        $this->assertDatabaseMissing('estudiantes', ['codigo_universitario' => '202100002']);
    }

    public function test_restoring_reports_how_many_tables_were_put_back(): void
    {
        $respaldo = $this->generar();

        $tablas = $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertOk()
            ->json('tablas');

        // Las 58 del esquema menos las diez que no se vuelcan.
        $this->assertSame(48, $tablas);
        $this->assertSame($tablas, DB::table('respaldos')->where('id', $respaldo['id'])->value('tablas'));
    }

    public function test_the_restored_system_keeps_working_with_new_records(): void
    {
        $this->estudiante('202100001');
        $respaldo = $this->generar();

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertOk();

        // Si la secuencia no se repusiera, este registro chocaría con un
        // identificador ya usado.
        $nuevo = $this->estudiante('202100003');

        $this->assertDatabaseHas('estudiantes', ['id' => $nuevo->getKey()]);
    }

    public function test_both_operations_are_written_to_the_audit_log(): void
    {
        $respaldo = $this->generar();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'respaldo.generar',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'respaldos',
            'registro_id' => $respaldo['id'],
        ]);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertOk();

        // La restauración devuelve la bitácora al estado del respaldo ---el
        // asiento de la generación es posterior y se pierde--- y sobre eso
        // deja constancia de lo que se acaba de hacer.
        $this->assertDatabaseMissing('bitacora_operaciones', [
            'operacion' => 'respaldo.generar',
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'respaldo.restaurar',
            'usuario_id' => $this->administrador->getKey(),
            'registro_id' => $respaldo['id'],
            'descripcion' => 'Restauración desde '.$respaldo['archivo'].'.',
        ]);
    }

    public function test_the_backup_can_be_downloaded_to_import_it_by_hand(): void
    {
        $respaldo = $this->generar();

        $respuesta = $this->actingAs($this->administrador)
            ->get("/api/respaldos/{$respaldo['id']}/descargar")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/sql');

        $this->assertStringContainsString(
            'attachment; filename="'.$respaldo['archivo'].'"',
            (string) $respuesta->headers->get('Content-Disposition'),
        );

        $this->assertSame(
            Storage::disk('local')->get('respaldos/'.$respaldo['archivo']),
            $respuesta->getContent(),
        );
    }

    public function test_a_backup_that_does_not_exist_is_reported(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/respaldos/999999/restaurar')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Respaldo no encontrado.']);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos/999999/descargar')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Respaldo no encontrado.']);
    }

    public function test_a_backup_whose_file_is_gone_is_reported(): void
    {
        $respaldo = $this->generar();

        Storage::disk('local')->delete('respaldos/'.$respaldo['archivo']);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertNotFound()
            ->assertJsonPath('message', 'El archivo de ese respaldo ya no está en disco.');

        $this->actingAs($this->administrador)
            ->getJson("/api/respaldos/{$respaldo['id']}/descargar")
            ->assertNotFound();
    }

    public function test_a_name_is_no_longer_a_way_to_reach_a_file(): void
    {
        // El respaldo se identifica por su id: nada que llegue por la
        // dirección se convierte en una ruta de disco.
        $this->actingAs($this->administrador)
            ->postJson('/api/respaldos/'.urlencode('../../.env').'/restaurar')
            ->assertNotFound();

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos/respaldo-20261012-030000.sql/descargar')
            ->assertNotFound();
    }

    public function test_a_guest_cannot_use_the_backups(): void
    {
        $respaldo = $this->generar();

        $this->app->make('auth')->forgetGuards();

        $this->getJson('/api/respaldos')->assertUnauthorized();
        $this->postJson('/api/respaldos')->assertUnauthorized();
        $this->getJson("/api/respaldos/{$respaldo['id']}/descargar")->assertUnauthorized();
        $this->postJson("/api/respaldos/{$respaldo['id']}/restaurar")->assertUnauthorized();

        $this->assertDatabaseCount('respaldos', 1);
    }

    public function test_the_backup_permission_is_required(): void
    {
        $respaldo = $this->generar();
        $docente = $this->usuarioConRol('Docente');

        $this->actingAs($docente)->getJson('/api/respaldos')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'respaldo_restauracion');
        $this->actingAs($docente)->postJson('/api/respaldos')->assertForbidden();
        $this->actingAs($docente)->getJson("/api/respaldos/{$respaldo['id']}/descargar")->assertForbidden();
        $this->actingAs($docente)->postJson("/api/respaldos/{$respaldo['id']}/restaurar")->assertForbidden();

        $this->assertDatabaseCount('respaldos', 1);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'respaldo.restaurar']);
    }

    public function test_cannot_restore_when_an_exam_is_currently_in_progress(): void
    {
        $this->travelTo('2026-10-12 03:00:00');
        $respaldo = $this->generar();
        $this->examenDeLas10();

        // Intentar restaurar a las 10:30 durante el examen activo
        $this->travelTo('2026-10-12 10:30:00');

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'EXAMEN_EN_CURSO')
            ->assertJsonPath('message', 'No se puede restaurar la base de datos mientras haya un examen en curso.');
    }

    /**
     * La puerta registra ingresos desde una hora antes del inicio hasta que
     * el docente finaliza el examen: en todo ese tramo no se restaura.
     *
     * @return array<string, array{string}>
     */
    public static function momentosConLaPuertaAbierta(): array
    {
        return [
            'puerta abierta antes del inicio' => ['2026-10-12 09:15:00'],
            'pasada la hora de fin, sin finalizar' => ['2026-10-12 12:30:00'],
        ];
    }

    #[DataProvider('momentosConLaPuertaAbierta')]
    public function test_cannot_restore_while_the_door_of_an_exam_is_open(string $momento): void
    {
        $this->travelTo('2026-10-12 03:00:00');
        $respaldo = $this->generar();
        $this->examenDeLas10();
        $this->travelTo($momento);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'EXAMEN_EN_CURSO');
    }

    public function test_can_restore_before_the_door_opens_and_after_the_exam_is_finalized(): void
    {
        $this->travelTo('2026-10-12 03:00:00');
        $respaldo = $this->generar();
        $examenId = $this->examenDeLas10();

        $this->travelTo('2026-10-12 08:30:00');
        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertOk();

        $examenId = $this->examenDeLas10();
        $this->travelTo('2026-10-12 11:00:00');
        DB::table('examenes')->where('id', $examenId)->update(['finalizado_en' => now()]);
        $this->travelTo('2026-10-12 11:05:00');

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo['id']}/restaurar")
            ->assertOk();
    }

    /**
     * Un examen el 12 de octubre de 10:00 a 12:00, sin finalizar.
     */
    private function examenDeLas10(): int
    {
        $periodoId = DB::table('periodos')->where('codigo', '2-2026')->value('id')
            ?? DB::table('periodos')->insertGetId([
                'codigo' => '2-2026',
                'anio' => 2026,
                'numero' => 2,
                'tipo' => 'SEMESTRAL',
                'fecha_inicio' => '2026-08-01',
                'fecha_fin' => '2026-12-31',
            ]);

        $asignaturaId = DB::table('asignaturas')->where('codigo', '2010010')->value('id')
            ?? DB::table('asignaturas')->insertGetId([
                'codigo' => '2010010',
                'nombre' => 'Taller de Ingeniería de Software',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('examenes')->insertGetId([
            'periodo_id' => $periodoId,
            'asignatura_id' => $asignaturaId,
            'tipo' => 'PARCIAL_1',
            'fecha' => '2026-10-12',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 120,
            'creado_por' => $this->administrador->id,
            'finalizado_en' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
