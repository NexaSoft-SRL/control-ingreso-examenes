<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Las incidencias en un solo modelo: catalogo con gravedad, evidencia con
 * foto, correccion del docente y reporte tambien con codigo de aula.
 */
final class IncidenciasTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $docente;

    private User $auxiliar;

    private Examen $examen;

    private Aula $aula;

    private Aula $otraAula;

    /** @var list<Estudiante> */
    private array $estudiantes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->docente = $this->usuarioConRol('Docente', ['nombre' => 'Blanco Coca Leticia']);
        $docente = $this->docenteConCuenta($this->docente);
        $grupo = $this->grupo($docente);
        $this->aula = $this->aula('691A');
        $this->otraAula = $this->aula('691B');
        $this->estudiantes = $this->estudiantesInscritos($grupo, 2);
        $this->examen = $this->examen($docente, [$grupo], [$this->aula, $this->otraAula]);

        $this->auxiliar = $this->usuarioConRol('Auxiliar', ['nombre' => 'Mamani Quispe Diego']);
        DB::table('examen_aula_personal')->insert([
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'usuario_id' => $this->auxiliar->id,
            'rol' => 'apoyo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_el_auxiliar_reporta_con_gravedad_estudiante_y_evidencia(): void
    {
        $respuesta = $this->actingAs($this->auxiliar)
            ->post("/api/examenes/{$this->examen->id}/incidencias", [
                'aula_id' => $this->aula->id,
                'tipo' => 'SUPLANTACION',
                'estudiante' => $this->estudiantes[0]->codigo_universitario,
                'detalle' => 'La foto no coincide.',
                'evidencia' => UploadedFile::fake()->image('foto.png', 2000, 1500),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.tipo_texto', 'Suplantación de identidad')
            ->assertJsonPath('data.gravedad', 'GRAVE')
            ->assertJsonPath('data.aula', '691A')
            ->assertJsonPath('data.evidencia', true)
            ->assertJsonPath('data.por', 'Mamani Quispe Diego')
            ->assertJsonPath('data.rol', 'Auxiliar')
            ->assertJsonPath('data.atendida', false);

        $estudiante = $this->estudiantes[0];
        $respuesta->assertJsonPath(
            'data.estudiante',
            $estudiante->apellidos.' '.$estudiante->nombres.' · '.$estudiante->codigo_universitario,
        );

        $ruta = DB::table('incidencias')->value('evidencia');
        self::assertIsString($ruta);
        Storage::disk('local')->assertExists($ruta);
        [$ancho] = (array) getimagesizefromstring((string) Storage::disk('local')->get($ruta));
        self::assertSame(1280, $ancho);

        // Solo en su aula, y el estudiante debe estar inscrito.
        $this->post("/api/examenes/{$this->examen->id}/incidencias", [
            'aula_id' => $this->otraAula->id,
            'tipo' => 'OTRA',
            'detalle' => 'En otra aula.',
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->postJson("/api/examenes/{$this->examen->id}/incidencias", [
            'aula_id' => $this->aula->id,
            'tipo' => 'OTRA',
            'estudiante' => '99999999',
            'detalle' => 'Código de nadie.',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.estudiante.0', 'No inscrito en el examen.');

        // La expulsion y el ingreso repetido no se eligen.
        $this->postJson("/api/examenes/{$this->examen->id}/incidencias", [
            'aula_id' => $this->aula->id,
            'tipo' => 'EXPULSION',
            'detalle' => 'No por aquí.',
        ])->assertUnprocessable();
    }

    public function test_un_nombre_escrito_a_mano_queda_como_texto(): void
    {
        $this->actingAs($this->auxiliar)
            ->postJson("/api/examenes/{$this->examen->id}/incidencias", [
                'aula_id' => $this->aula->id,
                'tipo' => 'MATERIAL_NO_PERMITIDO',
                'estudiante' => 'Juan Pérez',
                'detalle' => 'Calculadora programable.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.estudiante', 'Juan Pérez');

        $this->assertDatabaseHas('incidencias', [
            'estudiante_id' => null,
            'estudiante_texto' => 'Juan Pérez',
            'origen' => 'PERSONA',
        ]);
    }

    public function test_con_codigo_de_aula_se_reporta_solo_en_su_aula(): void
    {
        $token = str_repeat('a', 64);
        $accesoId = DB::table('accesos_codigo_aula')->insertGetId([
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'codigo' => '123456',
            'nombre_colaborador' => 'Rocha Ana',
            'token' => $token,
            'ultimo_acceso_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders(['X-Codigo-Aula-Token' => $token])
            ->postJson("/api/examenes/{$this->examen->id}/incidencias", [
                'aula_id' => $this->aula->id,
                'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
                'detalle' => 'Reloj inteligente.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.por', 'Rocha Ana')
            ->assertJsonPath('data.rol', 'Código de aula');

        $this->assertDatabaseHas('incidencias', [
            'reportada_por' => null,
            'acceso_codigo_aula_id' => $accesoId,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'incidencia.registrar',
            'tabla_afectada' => 'incidencias',
            'usuario_id' => null,
        ]);

        $this->withHeaders(['X-Codigo-Aula-Token' => $token])
            ->postJson("/api/examenes/{$this->examen->id}/incidencias", [
                'aula_id' => $this->otraAula->id,
                'tipo' => 'OTRA',
                'detalle' => 'Fuera de su aula.',
            ])
            ->assertForbidden();

        // Sin sesion ni codigo no se reporta.
        $this->withHeaders(['X-Codigo-Aula-Token' => ''])
            ->postJson("/api/examenes/{$this->examen->id}/incidencias", [
                'aula_id' => $this->aula->id,
                'tipo' => 'OTRA',
                'detalle' => 'Sin nadie.',
            ])
            ->assertUnauthorized();
    }

    public function test_el_docente_corrige_tipo_detalle_estudiante_y_evidencia(): void
    {
        $id = $this->reportada('OTRA', 'Algo raro en la fila.');

        $this->actingAs($this->auxiliar)
            ->postJson("/api/incidencias/{$id}/correccion", ['tipo' => 'OTRA', 'detalle' => 'Cambio no autorizado.'])
            ->assertForbidden();

        $this->actingAs($this->docente)
            ->post("/api/incidencias/{$id}/correccion", [
                'tipo' => 'MATERIAL_NO_PERMITIDO',
                'detalle' => 'Hojas con fórmulas.',
                'estudiante' => $this->estudiantes[1]->codigo_universitario,
                'evidencia' => UploadedFile::fake()->image('hojas.jpg', 400, 300),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.tipo', 'MATERIAL_NO_PERMITIDO')
            ->assertJsonPath('data.corregida', true)
            ->assertJsonPath('data.corregida_por', 'Blanco Coca Leticia')
            ->assertJsonPath('data.evidencia', true);

        $this->assertDatabaseHas('incidencias', [
            'id' => $id,
            'estudiante_id' => $this->estudiantes[1]->id,
            'corregida_por' => $this->docente->id,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'incidencia.corregir', 'registro_id' => $id]);

        // La foto la ven el docente y quien atiende el aula.
        $this->get("/api/incidencias/{$id}/evidencia")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->auxiliar)->get("/api/incidencias/{$id}/evidencia")->assertOk();

        $ajeno = $this->usuarioConRol('Auxiliar');
        $this->actingAs($ajeno)->getJson("/api/incidencias/{$id}/evidencia")->assertForbidden();
    }

    public function test_el_ingreso_repetido_y_la_expulsion_conservan_su_tipo(): void
    {
        $ingreso = $this->ingresar($this->examen, $this->estudiantes[0], $this->aula);
        $id = DB::table('incidencias')->insertGetId([
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'estudiante_id' => $this->estudiantes[0]->id,
            'ingreso_id' => $ingreso,
            'tipo' => 'INGRESO_REPETIDO',
            'origen' => 'SISTEMA',
            'detalle' => 'Código QR ya usado. Ingresó a las 08:05 en esta aula.',
            'ocurrida_en' => now(),
        ]);

        $this->actingAs($this->docente)
            ->postJson("/api/incidencias/{$id}/correccion", ['tipo' => 'OTRA', 'detalle' => 'Otra cosa.'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.tipo.0', 'Este tipo no se puede cambiar.');

        // El texto que escribio la puerta no se reescribe.
        $this->postJson("/api/incidencias/{$id}/correccion", ['tipo' => 'INGRESO_REPETIDO', 'detalle' => 'Texto nuevo.'])
            ->assertOk()
            ->assertJsonPath('data.detalle', 'Código QR ya usado. Ingresó a las 08:05 en esta aula.');

        // Se lista y se atiende aunque la haya asentado la puerta.
        $this->getJson("/api/examenes/{$this->examen->id}/incidencias?estado=sin_atender")
            ->assertOk()
            ->assertJsonPath('meta.sin_atender', 1)
            ->assertJsonPath('data.0.tipo_texto', 'Ingreso repetido')
            ->assertJsonPath('data.0.gravedad', 'GRAVE');
    }

    public function test_los_intentos_rechazados_por_la_puerta_no_se_listan(): void
    {
        DB::table('incidencias')->insert([
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_HABILITADO',
            'detalle' => 'No habilitado.',
            'ocurrida_en' => now(),
        ]);
        $this->reportada('OTRA', 'Reportada por una persona.');

        $this->actingAs($this->docente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.todas', 1);

        // Quien atiende un aula indica cual.
        $this->actingAs($this->auxiliar)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertUnprocessable();
    }

    private function reportada(string $tipo, string $detalle): int
    {
        return DB::table('incidencias')->insertGetId([
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'reportada_por' => $this->auxiliar->id,
            'tipo' => $tipo,
            'origen' => 'PERSONA',
            'detalle' => $detalle,
            'ocurrida_en' => now(),
        ]);
    }
}
