<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Administracion\Domain\Models\Ambiente;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AmbientesDelExamenTest extends TestCase
{
    use RefreshDatabase;

    private function id(Model $modelo): int
    {
        $id = $modelo->getKey();

        if (! is_int($id)) {
            throw new \LogicException('El modelo no tiene un ID entero.');
        }

        return $id;
    }

    private function crearExamen(string $hora = '08:30', int $duracion = 90): int
    {
        $docente = Docente::query()->firstOrCreate(
            ['codigo_docente' => 'DOC-AMB-HU10'],
            ['nombres' => 'Marcela', 'apellidos' => 'Quiroga', 'estado' => true],
        );

        $asignatura = Asignatura::query()->firstOrCreate(
            ['codigo' => 'INF-HU10'],
            ['nombre' => 'Redes de Computadoras', 'semestre' => '8', 'estado' => true],
        );

        $grupo = $asignatura->grupos()->firstOrCreate(
            ['codigo_grupo' => 'A'],
            ['docente_id' => $docente->getKey(), 'cupo' => 40],
        );

        return (int) DB::table('examenes')->insertGetId([
            'grupo_id' => $grupo->getKey(),
            'nombre' => 'Primer parcial',
            'fecha' => '2026-10-15',
            'hora_inicio' => $hora,
            'duracion_minutos' => $duracion,
        ]);
    }

    private function crearAmbiente(string $nombre, string $estado = 'DISPONIBLE', int $capacidad = 60): Ambiente
    {
        return Ambiente::query()->create([
            'nombre' => $nombre,
            'ubicacion' => 'Edificio Central',
            'capacidad' => $capacidad,
            'estado' => $estado,
        ]);
    }

    public function test_un_examen_admite_varios_ambientes(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $aula = $this->crearAmbiente('Aula Magna', 'DISPONIBLE', 120);
        $laboratorio = $this->crearAmbiente('Laboratorio 1', 'DISPONIBLE', 40);

        foreach ([$aula, $laboratorio] as $ambiente) {
            $this->actingAs($usuario)
                ->postJson("/api/examenes/{$examen}/ambientes", [
                    'ambiente_id' => $ambiente->getKey(),
                ])
                ->assertCreated();
        }

        $this->actingAs($usuario)
            ->getJson("/api/examenes/{$examen}/ambientes")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('ocupacion.capacidad_asignada', 160);
    }

    public function test_la_capacidad_asignada_se_compara_con_los_habilitados(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $aula = $this->crearAmbiente('Aula 691B', 'DISPONIBLE', 88);

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertCreated();

        // Las habilitaciones llegan en HU-11: hasta entonces no hay
        // habilitados y la capacidad alcanza.
        $this->actingAs($usuario)
            ->getJson("/api/examenes/{$examen}/ambientes")
            ->assertOk()
            ->assertJsonPath('ocupacion.capacidad_asignada', 88)
            ->assertJsonPath('ocupacion.habilitados', 0)
            ->assertJsonPath('ocupacion.alcanza', true);
    }

    public function test_un_ambiente_en_mantenimiento_no_se_puede_asignar(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $taller = $this->crearAmbiente('Laboratorio 2', 'MANTENIMIENTO');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/ambientes", [
                'ambiente_id' => $taller->getKey(),
            ])
            ->assertStatus(409);

        $this->assertSame(0, DB::table('examen_ambiente')->count());
    }

    public function test_un_ambiente_ocupado_tampoco_se_puede_asignar(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $ocupado = $this->crearAmbiente('Auditorio', 'OCUPADO');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/ambientes", [
                'ambiente_id' => $ocupado->getKey(),
            ])
            ->assertStatus(409);

        $this->assertSame(0, DB::table('examen_ambiente')->count());
    }

    public function test_un_ambiente_no_se_comparte_entre_examenes_solapados(): void
    {
        $usuario = UserFactory::new()->createOne();
        $manana = $this->crearExamen('08:00', 120);
        $solapado = $this->crearExamen('09:00', 60);
        $aula = $this->crearAmbiente('Aula Magna');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$manana}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertCreated();

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$solapado}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertStatus(409);
    }

    public function test_un_ambiente_si_se_comparte_entre_examenes_que_no_se_solapan(): void
    {
        $usuario = UserFactory::new()->createOne();
        $manana = $this->crearExamen('08:00', 60);
        $tarde = $this->crearExamen('14:00', 60);
        $aula = $this->crearAmbiente('Aula Magna');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$manana}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertCreated();

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$tarde}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertCreated();
    }

    public function test_el_mismo_ambiente_no_se_asigna_dos_veces_al_mismo_examen(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $aula = $this->crearAmbiente('Aula Magna');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertCreated();

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ambiente_id');
    }

    public function test_un_ambiente_se_puede_quitar_del_examen(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $aula = $this->crearAmbiente('Aula Magna');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertCreated();

        $this->actingAs($usuario)
            ->deleteJson("/api/examenes/{$examen}/ambientes/{$this->id($aula)}")
            ->assertNoContent();

        $this->assertSame(0, DB::table('examen_ambiente')->count());
    }

    public function test_quitar_un_ambiente_no_asignado_reporta_error(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $aula = $this->crearAmbiente('Aula Magna');

        $this->actingAs($usuario)
            ->deleteJson("/api/examenes/{$examen}/ambientes/{$this->id($aula)}")
            ->assertNotFound();
    }

    public function test_cada_movimiento_queda_en_la_bitacora(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $aula = $this->crearAmbiente('Aula Magna');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/ambientes", [
                'ambiente_id' => $aula->getKey(),
            ])
            ->assertCreated();

        $this->actingAs($usuario)
            ->deleteJson("/api/examenes/{$examen}/ambientes/{$this->id($aula)}")
            ->assertNoContent();

        foreach (['examen.asignar_ambiente', 'examen.quitar_ambiente'] as $operacion) {
            $this->assertTrue(
                DB::table('bitacora_operaciones')
                    ->where('operacion', $operacion)
                    ->where('usuario_id', $usuario->getKey())
                    ->exists(),
                "Falta el asiento {$operacion}."
            );
        }
    }

    public function test_un_rol_sin_permiso_no_puede_asignar_ambientes(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();

        $this->actingAs($usuario)
            ->getJson("/api/examenes/{$examen}/ambientes")
            ->assertOk();
    }

    public function test_un_invitado_no_puede_asignar_ambientes(): void
    {
        $examen = $this->crearExamen();

        $this->getJson("/api/examenes/{$examen}/ambientes")->assertUnauthorized();
    }
}
