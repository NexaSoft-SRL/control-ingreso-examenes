<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Apoyo de las pruebas del modulo: cuentas, archivos y las dos cargas.
 * Se combina con `UsuarioConPermisos` y `DatosAcademicos`.
 */
trait ApoyoDeCargas
{
    /**
     * Las cuentas llevan el reparto real de permisos: `usuarioConPermisos`
     * comparte un solo rol de prueba y mezclaria los de ambos.
     */
    protected function administrador(): User
    {
        return $this->usuarioConRol('Administrador');
    }

    /**
     * Un docente con cuenta y el permiso de sus grupos.
     */
    protected function docente(): Docente
    {
        return $this->docenteConCuenta($this->usuarioConRol('Docente'));
    }

    protected function cuentaDe(Docente $docente): User
    {
        return User::findOrFail($docente->user_id);
    }

    /**
     * @param  list<string>  $lineas
     */
    protected function csv(array $lineas, string $nombre = 'lista.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, implode("\n", $lineas)."\n");
    }

    /**
     * @param  list<list<int|string>>  $filas
     */
    protected function xlsx(array $filas, string $nombre = 'lista.xlsx'): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas);

        $ruta = (string) tempnam(sys_get_temp_dir(), 'lista');
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    /**
     * La carga de la lista de un grupo, como su docente.
     *
     * @param  list<string>|UploadedFile  $archivo
     * @return TestResponse<JsonResponse>
     */
    protected function cargarLista(Docente $docente, Grupo $grupo, array|UploadedFile $archivo): TestResponse
    {
        return $this->actingAs($this->cuentaDe($docente))->post(
            "/api/docente/grupos/{$grupo->id}/inscritos/carga",
            ['archivo' => is_array($archivo) ? $this->csv($archivo) : $archivo],
            ['Accept' => 'application/json'],
        );
    }

    /**
     * La carga de inscripciones de una facultad, como administracion.
     *
     * @param  list<string>|UploadedFile  $archivo
     * @return TestResponse<JsonResponse>
     */
    protected function cargarFacultad(array|UploadedFile $archivo, string $facultad = 'fcyt', ?User $cuenta = null): TestResponse
    {
        return $this->actingAs($cuenta ?? $this->administrador())->post(
            '/api/estudiantes/cargas',
            [
                'facultad' => $facultad,
                'archivo' => is_array($archivo) ? $this->csv($archivo, 'inscripciones.csv') : $archivo,
            ],
            ['Accept' => 'application/json'],
        );
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function estudiante(array $atributos = []): Estudiante
    {
        return Estudiante::create(array_merge([
            'codigo_universitario' => '202104821',
            'documento_identidad' => '7928194',
            'nombres' => 'Kevin René',
            'apellidos' => 'Alvarado Claros',
            'facultad_id' => $this->facultad()->id,
            'origen' => OrigenEstudiante::Administracion,
            'verificado' => true,
            'activo' => true,
        ], $atributos));
    }
}
