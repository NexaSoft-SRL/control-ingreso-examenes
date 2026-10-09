<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La revision del contenido del archivo de una lista (HU-14, criterios 8 a
 * 11; HU-13, criterio 6): columnas faltantes, columnas en otro orden,
 * filas vacias y archivo que no corresponde. El archivo se rechaza entero,
 * con 422 y sin guardar nada.
 */
final class ValidacionDeArchivoTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const COLUMNAS = ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'];

    /** Un PNG de 1 x 1. */
    private const IMAGEN = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    // --- Criterio 8: faltan columnas ---

    public function test_a_header_without_a_column_is_rejected_and_names_the_missing_one(): void
    {
        $respuesta = $this->cargar([
            'codigo_universitario,nombres,apellidos',
            '202104821,Kevin René,Alvarado Claros',
        ]);

        $this->assertRechazado($respuesta, 'FALTAN_COLUMNAS')
            ->assertJsonPath('columnas', ['documento_identidad'])
            ->assertJsonPath('orden_esperado', self::COLUMNAS)
            ->assertJsonPath('message', 'Faltan columnas: documento_identidad.')
            ->assertJsonValidationErrors(['archivo' => 'Faltan columnas: documento_identidad.']);
    }

    public function test_several_missing_columns_are_all_named(): void
    {
        $respuesta = $this->cargar([
            'Código;Nombres',
            '202104821;Kevin René',
        ]);

        $this->assertRechazado($respuesta, 'FALTAN_COLUMNAS')
            ->assertJsonPath('columnas', ['documento_identidad', 'apellidos']);
    }

    public function test_a_file_without_header_and_with_fewer_columns_is_rejected(): void
    {
        $respuesta = $this->cargar([
            '202104821,7928194,Kevin René',
            '201901349,6492819,Diego Andrés',
        ]);

        $this->assertRechazado($respuesta, 'FALTAN_COLUMNAS')
            ->assertJsonPath('columnas', ['apellidos']);
    }

    public function test_an_xlsx_without_a_column_is_rejected(): void
    {
        $respuesta = $this->cargar($this->xlsx([
            ['codigo_universitario', 'documento_identidad', 'apellidos'],
            [202104821, 7928194, 'Alvarado Claros'],
        ]));

        $this->assertRechazado($respuesta, 'FALTAN_COLUMNAS')
            ->assertJsonPath('columnas', ['nombres']);
    }

    public function test_the_faculty_load_also_requires_subject_and_group(): void
    {
        $this->ofertaDeFacultad();

        $respuesta = $this->cargarFacultad([
            'codigo_universitario,documento_identidad,nombres,apellidos',
            '202104821,7928194,Kevin René,Alvarado Claros',
        ]);

        $this->assertRechazado($respuesta, 'FALTAN_COLUMNAS')
            ->assertJsonPath('columnas', ['codigo_asignatura', 'grupo'])
            ->assertJsonPath('orden_esperado', [...self::COLUMNAS, 'codigo_asignatura', 'grupo']);
    }

    // --- Criterio 9: columnas en otro orden ---

    public function test_a_header_in_another_order_is_rejected_with_the_expected_order(): void
    {
        $respuesta = $this->cargar([
            'apellidos,nombres,codigo_universitario,documento_identidad',
            'Alvarado Claros,Kevin René,202104821,7928194',
        ]);

        $this->assertRechazado($respuesta, 'ORDEN_DE_COLUMNAS')
            ->assertJsonPath('orden_esperado', self::COLUMNAS)
            ->assertJsonMissingPath('columnas');
    }

    public function test_the_header_is_recognized_with_accents_capitals_and_short_names(): void
    {
        $respuesta = $this->cargar([
            'CI;Código SIS;Nombres;Apellidos',
            '7928194;202104821;Kevin René;Alvarado Claros',
        ]);

        $this->assertRechazado($respuesta, 'ORDEN_DE_COLUMNAS');
    }

    public function test_without_header_a_code_out_of_the_first_column_is_another_order(): void
    {
        $respuesta = $this->cargar([
            'Alvarado Claros,Kevin René,202104821,7928194',
            'Camacho Zeballos,Diego Andrés,201901349,6492819',
        ]);

        $this->assertRechazado($respuesta, 'ORDEN_DE_COLUMNAS')
            ->assertJsonPath('orden_esperado', self::COLUMNAS);
    }

    public function test_without_header_names_first_and_code_second_is_another_order(): void
    {
        $respuesta = $this->cargar([
            'Kevin René,202104821,7928194,Alvarado Claros',
            'Diego Andrés,201901349,6492819,Camacho Zeballos',
        ]);

        $this->assertRechazado($respuesta, 'ORDEN_DE_COLUMNAS');
    }

    public function test_the_faculty_load_checks_the_order_of_its_columns(): void
    {
        $this->ofertaDeFacultad();

        $respuesta = $this->cargarFacultad([
            'codigo_universitario,documento_identidad,nombres,apellidos,grupo,codigo_asignatura',
            '202104821,7928194,Kevin René,Alvarado Claros,1,2008019',
        ]);

        $this->assertRechazado($respuesta, 'ORDEN_DE_COLUMNAS');
    }

    public function test_the_expected_order_with_other_header_names_and_extra_columns_loads(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), [
            'Código SIS;Documento;Nombre;Apellido;Observación',
            '202104821;7928194;Kevin René;Alvarado Claros;repite',
        ])
            ->assertOk()
            ->assertJsonPath('resumen.filas', 1)
            ->assertJsonPath('resumen.nuevos', 1);
    }

    // --- Criterio 10: filas vacias ---

    public function test_empty_rows_are_skipped_and_the_row_numbers_are_those_of_the_file(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), [
            '',
            'codigo_universitario,documento_identidad,nombres,apellidos',
            ',,,',
            '202104821,7928194,Kevin René,Alvarado Claros',
            '',
            ' , , , ',
            '201901349,,Diego Andrés,Camacho Zeballos',
            '',
            '12,6492819,Sofía,Mamani Quispe',
        ])
            ->assertOk()
            ->assertJsonPath('resumen.filas', 3)
            ->assertJsonPath('resumen.nuevos', 1)
            ->assertJsonPath('rechazos', [
                ['fila' => 7, 'motivo' => 'Sin documento de identidad'],
                ['fila' => 9, 'motivo' => 'Código universitario no válido'],
            ]);
    }

    public function test_empty_rows_of_an_xlsx_are_skipped_too(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), $this->xlsx([
            ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'],
            ['', '', '', ''],
            [202104821, 7928194, 'Kevin René', 'Alvarado Claros'],
            ['', '', '', ''],
            [201901349, '', 'Diego Andrés', 'Camacho Zeballos'],
        ]))
            ->assertOk()
            ->assertJsonPath('resumen.filas', 2)
            ->assertJsonPath('rechazos.0.fila', 5);
    }

    public function test_a_file_with_only_empty_rows_is_rejected(): void
    {
        $this->assertRechazado($this->cargar(['', ',,,', ' , , ']), 'ARCHIVO_VACIO')
            ->assertJsonPath('message', 'El archivo no tiene filas.');
    }

    public function test_a_file_with_only_the_header_is_rejected(): void
    {
        $this->assertRechazado(
            $this->cargar(['codigo_universitario,documento_identidad,nombres,apellidos', ',,,']),
            'ARCHIVO_VACIO',
        );
    }

    public function test_an_xlsx_without_rows_is_rejected(): void
    {
        $this->assertRechazado($this->cargar($this->xlsx([])), 'ARCHIVO_VACIO');
    }

    // --- Criterio 11: el archivo no corresponde ---

    public function test_an_image_renamed_to_xlsx_is_rejected(): void
    {
        $respuesta = $this->cargar(
            UploadedFile::fake()->createWithContent('lista.xlsx', (string) base64_decode(self::IMAGEN, true)),
        );

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE')
            ->assertJsonPath('message', 'El archivo no es una lista de inscritos.')
            ->assertJsonPath('orden_esperado', self::COLUMNAS);
    }

    public function test_an_image_renamed_to_csv_is_rejected(): void
    {
        $respuesta = $this->cargar(
            UploadedFile::fake()->createWithContent('lista.csv', (string) base64_decode(self::IMAGEN, true)),
        );

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE');
    }

    public function test_a_damaged_xlsx_is_rejected(): void
    {
        // Empieza como un zip, pero esta cortado.
        $entero = (string) file_get_contents((string) $this->xlsx([[202104821, 7928194, 'Kevin René', 'Alvarado Claros']])->getRealPath());

        $respuesta = $this->cargar(
            UploadedFile::fake()->createWithContent('lista.xlsx', substr($entero, 0, 600)),
        );

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE');
    }

    public function test_another_office_document_renamed_to_xlsx_is_rejected(): void
    {
        // Un zip valido que no es un libro de hoja de calculo.
        $ruta = (string) tempnam(sys_get_temp_dir(), 'documento');
        $zip = new \ZipArchive;
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<document/>');
        $zip->close();

        $respuesta = $this->cargar(
            UploadedFile::fake()->createWithContent('lista.xlsx', (string) file_get_contents($ruta)),
        );

        unlink($ruta);

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE');
    }

    public function test_an_xlsx_renamed_to_csv_is_rejected(): void
    {
        $entero = (string) file_get_contents((string) $this->xlsx([[202104821, 7928194, 'Kevin René', 'Alvarado Claros']])->getRealPath());

        $respuesta = $this->cargar(UploadedFile::fake()->createWithContent('lista.csv', $entero));

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE');
    }

    public function test_a_text_that_is_not_a_list_is_rejected(): void
    {
        $respuesta = $this->cargar([
            'Acta de la reunión del consejo de carrera',
            'Se aprueba el calendario, con dos observaciones.',
        ]);

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE');
    }

    public function test_another_table_with_header_is_rejected(): void
    {
        $respuesta = $this->cargar([
            'Producto,Precio,Cantidad,Total',
            'Cuaderno,12.50,3,37.50',
            'Lápiz,1.50,10,15.00',
        ]);

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE');
    }

    public function test_the_faculty_load_rejects_a_file_that_does_not_correspond(): void
    {
        $this->ofertaDeFacultad();

        $respuesta = $this->cargarFacultad(
            UploadedFile::fake()->createWithContent('inscripciones.csv', (string) base64_decode(self::IMAGEN, true)),
        );

        $this->assertRechazado($respuesta, 'ARCHIVO_NO_CORRESPONDE');
    }

    public function test_a_csv_in_latin1_is_read_with_its_accents(): void
    {
        $docente = $this->docente();

        $contenido = mb_convert_encoding(
            "codigo_universitario;documento_identidad;nombres;apellidos\n202104821;7928194;Kevin René;Peñaranda Cossío\n",
            'ISO-8859-1',
            'UTF-8',
        );

        $this->cargarLista(
            $docente,
            $this->grupo($docente),
            UploadedFile::fake()->createWithContent('lista.csv', $contenido),
        )->assertOk();

        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '202104821',
            'nombres' => 'Kevin René',
            'apellidos' => 'Peñaranda Cossío',
        ]);
    }

    public function test_the_scope_is_checked_before_the_file(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->cargarLista($this->docente(), $grupo, ['Producto,Precio'])
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
    }

    /**
     * La facultad y el periodo vigente que pide la carga de facultad.
     */
    private function ofertaDeFacultad(): void
    {
        $this->facultad();
        $this->periodoVigente();
    }

    /**
     * Carga la lista en un grupo propio, como su docente.
     *
     * @param  list<string>|UploadedFile  $archivo
     * @return TestResponse<JsonResponse>
     */
    private function cargar(array|UploadedFile $archivo): TestResponse
    {
        $docente = $this->docente();

        return $this->cargarLista($docente, $this->grupo($docente), $archivo);
    }

    /**
     * El archivo se rechazo entero: 422 con su codigo y nada guardado, ni
     * la fila de la carga ni su asiento en la bitacora.
     *
     * @param  TestResponse<JsonResponse>  $respuesta
     * @return TestResponse<JsonResponse>
     */
    private function assertRechazado(TestResponse $respuesta, string $codigo): TestResponse
    {
        $respuesta->assertStatus(422)->assertJsonPath('codigo', $codigo);

        $this->assertDatabaseCount('cargas_inscritos', 0);
        $this->assertDatabaseCount('estudiantes', 0);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('conflictos_padron', 0);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'inscritos.cargar']);

        return $respuesta;
    }
}
