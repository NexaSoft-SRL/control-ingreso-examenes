<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use App\Modules\Examenes\Domain\Models\ExamenAulaCodigo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class AccesoConCodigoDeAulaTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('intento-codigo-aula:127.0.0.1');
    }

    public function test_colaborador_puede_ingresar_con_codigo_valido_en_ventana_permitida(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');

        // Examen que inicia en 30 minutos (dentro de los 60 min previos)
        $inicio = now()->addMinutes(30);
        $examen = $this->examen($docente, [$grupo], [$aula], [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
        ]);

        ExamenAulaCodigo::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '654321',
            'generado_en' => now()->toDateTimeString(),
        ]);

        $respuesta = $this->postJson('/api/auth/codigo-aula', [
            'codigo' => '654321',
            'nombre_colaborador' => 'Marta Colaboradora',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Acceso concedido')
            ->assertJsonPath('data.examen_id', $examen->id)
            ->assertJsonPath('data.aula_id', $aula->id)
            ->assertJsonPath('data.aula_nombre', '691A')
            ->assertJsonPath('data.nombre_colaborador', 'Marta Colaboradora');

        $token = $respuesta->json('data.token');
        self::assertIsString($token);

        $this->assertDatabaseHas('accesos_codigo_aula', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '654321',
            'nombre_colaborador' => 'Marta Colaboradora',
            'token' => $token,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'acceso_codigo_aula.ingreso',
            'usuario_id' => null,
        ]);
    }

    public function test_rechaza_ingreso_fuera_de_ventana_con_422(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');

        // Examen para dentro de 3 horas (más de 60 minutos previos)
        $inicio = now()->addHours(3);
        $examen = $this->examen($docente, [$grupo], [$aula], [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
        ]);

        ExamenAulaCodigo::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '112233',
            'generado_en' => now()->toDateTimeString(),
        ]);

        $this->postJson('/api/auth/codigo-aula', [
            'codigo' => '112233',
            'nombre_colaborador' => 'Pedro Aux',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código no válido');
    }

    public function test_rate_limiting_responde_429_al_cuarto_intento_fallido_en_5_minutos(): void
    {
        // 3 intentos fallidos con código inexistente
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/auth/codigo-aula', [
                'codigo' => '000000',
                'nombre_colaborador' => 'Intento '.$i,
            ])->assertUnprocessable();
        }

        // El cuarto intento debe responder 429 Demasiados intentos
        $this->postJson('/api/auth/codigo-aula', [
            'codigo' => '000000',
            'nombre_colaborador' => 'Intento 4',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Demasiados intentos');
    }

    public function test_consulta_de_sesion_activa_y_cierre_de_sesion(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');

        $inicio = now()->addMinutes(10);
        $examen = $this->examen($docente, [$grupo], [$aula], [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:00'),
            'duracion_minutos' => 90,
        ]);

        $acceso = AccesoCodigoAula::create([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'codigo' => '998877',
            'nombre_colaborador' => 'Laura Gomez',
            'token' => 'token-test-sesion-998877',
            'ultimo_acceso_en' => now(),
            'revocado_en' => null,
        ]);

        // Consulta de sesión activa
        $this->withHeader('X-Codigo-Aula-Token', 'token-test-sesion-998877')
            ->getJson('/api/auth/codigo-aula/sesion')
            ->assertOk()
            ->assertJsonPath('data.nombre_colaborador', 'Laura Gomez')
            ->assertJsonPath('data.aula_nombre', '691A');

        // Salir / cerrar sesión
        $this->withHeader('X-Codigo-Aula-Token', 'token-test-sesion-998877')
            ->postJson('/api/auth/codigo-aula/salir')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada');

        $this->assertNotNull(AccesoCodigoAula::find($acceso->id)?->revocado_en);

        // Ya no puede consultar la sesión
        $this->withHeader('X-Codigo-Aula-Token', 'token-test-sesion-998877')
            ->getJson('/api/auth/codigo-aula/sesion')
            ->assertUnauthorized();
    }
}
