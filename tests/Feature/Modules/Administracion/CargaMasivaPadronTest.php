<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Student;
use Database\Factories\StudentFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Carga masiva del padron (HU-04), con las cinco columnas del backlog.
 */
final class CargaMasivaPadronTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_csv_with_a_header_row_loads_the_padron(): void
    {
        $this->cargar([
            'codigo_universitario,documento_identidad,nombres,apellidos,carrera',
            '202104821,7928194,Kevin,Alvarado Claros,Ingeniería de Sistemas',
            '201901349,6492819,Diego,Camacho Zeballos,Ingeniería Informática',
        ])
            ->assertOk()
            ->assertJsonPath('creados', 2)
            ->assertJsonPath('rechazados', 0);

        $this->assertSame(2, Student::query()->count());
    }

    public function test_a_file_without_a_header_row_also_loads(): void
    {
        $this->cargar([
            '202104821,7928194,Kevin,Alvarado Claros,Ingeniería de Sistemas',
        ])->assertOk()->assertJsonPath('creados', 1);
    }

    public function test_an_existing_student_is_updated_instead_of_duplicated(): void
    {
        StudentFactory::new()->create([
            'codigo_universitario' => '202104821',
            'ci' => '7928194',
            'carrera' => 'Ingeniería Informática',
        ]);

        $this->cargar([
            'codigo_universitario,documento_identidad,nombres,apellidos,carrera',
            '202104821,7928194,Kevin,Alvarado Claros,Ingeniería de Sistemas',
        ])
            ->assertOk()
            ->assertJsonPath('creados', 0)
            ->assertJsonPath('actualizados', 1);

        $this->assertSame(1, Student::query()->count());
        $this->assertDatabaseHas('students', [
            'codigo_universitario' => '202104821',
            'carrera' => 'Ingeniería de Sistemas',
        ]);
    }

    public function test_a_row_with_missing_data_is_rejected_with_its_line_and_reason(): void
    {
        $respuesta = $this->cargar([
            'codigo_universitario,documento_identidad,nombres,apellidos,carrera',
            '202104821,7928194,Kevin,Alvarado Claros,Ingeniería de Sistemas',
            '201901349,,Diego,Camacho Zeballos,Ingeniería Informática',
        ])->assertOk();

        // La fila mala no detiene la carga: la buena entra igual.
        $respuesta->assertJsonPath('creados', 1);
        $respuesta->assertJsonPath('rechazados', 1);
        $respuesta->assertJsonPath('detalles.0.fila', 3);
        $respuesta->assertJsonPath(
            'detalles.0.motivo',
            'Falta el dato obligatorio: documento de identidad.'
        );
    }

    public function test_a_code_repeated_inside_the_file_is_rejected(): void
    {
        $respuesta = $this->cargar([
            'codigo_universitario,documento_identidad,nombres,apellidos,carrera',
            '202104821,7928194,Kevin,Alvarado Claros,Ingeniería de Sistemas',
            '202104821,6492819,Diego,Camacho Zeballos,Ingeniería Informática',
        ])->assertOk();

        $respuesta->assertJsonPath('creados', 1);
        $respuesta->assertJsonPath('rechazados', 1);

        $motivo = $respuesta->json('detalles.0.motivo');

        $this->assertIsString($motivo);
        $this->assertStringContainsString('se repite en el archivo', $motivo);
    }

    public function test_a_row_with_fewer_columns_is_rejected(): void
    {
        $this->cargar([
            'codigo_universitario,documento_identidad,nombres,apellidos,carrera',
            '202104821,7928194,Kevin',
        ])
            ->assertOk()
            ->assertJsonPath('rechazados', 1)
            ->assertJsonPath(
                'detalles.0.motivo',
                'La fila no tiene las cinco columnas esperadas.'
            );
    }

    public function test_a_guest_cannot_upload_the_padron(): void
    {
        $this->postJson('/api/students/import', [
            'archivo' => UploadedFile::fake()->createWithContent('padron.csv', 'x'),
        ])->assertUnauthorized();
    }

    public function test_two_thousand_rows_are_processed(): void
    {
        $filas = ['codigo_universitario,documento_identidad,nombres,apellidos,carrera'];

        for ($i = 1; $i <= 2000; $i++) {
            $codigo = 202100000 + $i;
            $documento = 7000000 + $i;
            $filas[] = "{$codigo},{$documento},Nombre {$i},Apellido {$i},Ingeniería de Sistemas";
        }

        $this->cargar($filas)
            ->assertOk()
            ->assertJsonPath('creados', 2000)
            ->assertJsonPath('rechazados', 0);
    }

    /**
     * @param  list<string>  $lineas
     * @return TestResponse<JsonResponse>
     */
    private function cargar(array $lineas): TestResponse
    {
        return $this->actingAs(UserFactory::new()->createOne())
            ->postJson('/api/students/import', [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'padron.csv',
                    implode("\n", $lineas)."\n",
                ),
            ]);
    }
}
