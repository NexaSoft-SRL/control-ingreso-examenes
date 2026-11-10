<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Ademas de hasta tres fotos, una incidencia lleva un video corto de
 * evidencia: uno solo, de hasta 8 MB, que se agrega al reportarla o al
 * corregirla y no se reemplaza.
 */
final class VideoEvidenciaTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->armarExamen();
    }

    private function video(string $nombre = 'evidencia.webm', string $tipo = 'video/webm', int $kb = 600): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, str_repeat('v', $kb * 1024))->mimeType($tipo);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return TestResponse<Response>
     */
    private function reportar(array $datos): TestResponse
    {
        return $this->actingAs($this->cuentaDocente)->post("/api/examenes/{$this->examen->id}/incidencias", array_merge([
            'aula_id' => $this->aula->id,
            'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
            'detalle' => 'Grabó la pantalla del celular',
        ], $datos), ['Accept' => 'application/json']);
    }

    public function test_the_video_is_saved_with_the_incident_and_can_be_played(): void
    {
        $id = $this->reportar(['video' => $this->video()])
            ->assertCreated()
            ->assertJsonPath('data.video', true)
            ->json('data.id');
        self::assertIsInt($id);

        $ruta = DB::table('incidencias')->where('id', $id)->value('video');
        self::assertIsString($ruta);
        Storage::disk('local')->assertExists($ruta);
        $this->assertSame('video/webm', DB::table('incidencias')->where('id', $id)->value('video_tipo'));

        $respuesta = $this->actingAs($this->cuentaDocente)->get("/api/incidencias/{$id}/video")->assertOk();
        $this->assertSame('video/webm', $respuesta->headers->get('Content-Type'));
        $this->assertSame('bytes', $respuesta->headers->get('Accept-Ranges'));
    }

    public function test_without_video_the_incident_says_so_and_there_is_nothing_to_play(): void
    {
        $id = $this->reportar([])->assertCreated()->assertJsonPath('data.video', false)->json('data.id');
        self::assertIsInt($id);

        $this->actingAs($this->cuentaDocente)->getJson("/api/incidencias/{$id}/video")
            ->assertNotFound()
            ->assertJsonPath('message', 'Sin video.');
    }

    public function test_only_videos_up_to_8_mb_are_accepted(): void
    {
        $this->reportar(['video' => UploadedFile::fake()->create('hoja.pdf', 100, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.video.0', 'Solo MP4, WebM o MOV');

        $this->reportar(['video' => $this->video('largo.mp4', 'video/mp4', 9000)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.video.0', 'Máximo 8 MB');

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_the_correction_adds_the_video_once(): void
    {
        $id = $this->reportar([])->json('data.id');
        self::assertIsInt($id);

        $corregir = fn (): TestResponse => $this->actingAs($this->cuentaDocente)->post("/api/incidencias/{$id}/correccion", [
            'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
            'detalle' => 'Grabó la pantalla del celular',
            'video' => $this->video('desde-el-celular.mov', 'video/quicktime'),
        ], ['Accept' => 'application/json']);

        $corregir()->assertOk()->assertJsonPath('data.video', true);
        $this->assertSame('video/quicktime', DB::table('incidencias')->where('id', $id)->value('video_tipo'));

        $corregir()->assertUnprocessable()->assertJsonPath('errors.video.0', 'Ya tiene un video');
        $this->assertCount(1, Storage::disk('local')->allFiles('incidencias/videos'));
    }

    public function test_the_video_is_only_for_who_can_see_the_incident(): void
    {
        $id = $this->reportar(['video' => $this->video()])->json('data.id');
        self::assertIsInt($id);

        $this->actingAs($this->docenteAjeno())->getJson("/api/incidencias/{$id}/video")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/incidencias/{$id}/video")->assertUnauthorized();
    }
}
