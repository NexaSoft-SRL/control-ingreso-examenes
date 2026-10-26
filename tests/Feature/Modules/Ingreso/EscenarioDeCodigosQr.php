<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;

/**
 * Un examen de la docente con un grupo y un aula ubicada, para las pruebas
 * de los codigos QR (HU-20).
 */
trait EscenarioDeCodigosQr
{
    use DatosAcademicos;
    use DatosDeExamen;
    use UsuarioConPermisos;

    private User $cuentaDocente;

    private Docente $docente;

    private Grupo $grupo;

    private Aula $aula;

    private Examen $examen;

    private function armarExamen(): void
    {
        $this->cuentaDocente = $this->usuarioConPermisos(['codigos_qr']);
        $this->docente = $this->docenteConCuenta($this->cuentaDocente);
        $this->grupo = $this->grupo(
            $this->docente,
            $this->asignatura('Introducción a la Programación'),
            '2',
        );
        $this->aula = $this->aula(
            '691A',
            $this->edificio($this->facultad(), 'Edificio Académico 2'),
            '1° Piso',
        );
        $this->examen = $this->examen($this->docente, [$this->grupo], [$this->aula], [
            'fecha' => '2026-10-30',
            'hora_inicio' => '08:15:00',
            'duracion_minutos' => 90,
        ]);
    }

    private function ruta(string $resto = ''): string
    {
        return "/api/examenes/{$this->examen->id}/codigos-qr{$resto}";
    }

    /**
     * Inscribe y habilita estudiantes nuevos, repartidos en el aula.
     *
     * @return list<Estudiante>
     */
    private function habilitados(int $cantidad, ?Aula $aula = null, ?Grupo $grupo = null): array
    {
        $estudiantes = $this->estudiantesInscritos($grupo ?? $this->grupo, $cantidad);
        $this->habilitar($this->examen, $estudiantes, $aula ?? $this->aula);

        return $estudiantes;
    }

    private function sumarAula(Aula $aula): void
    {
        ExamenAula::create([
            'examen_id' => $this->examen->id,
            'aula_id' => $aula->id,
        ]);
    }

    /**
     * Deja emitido el codigo del estudiante, con el formato de la puerta.
     */
    private function emitirCodigo(Estudiante $estudiante): string
    {
        $contenido = app(CodigoQrGateway::class)->generar($this->examen->id, $estudiante->codigo_universitario);

        DB::table('codigos_qr')->insert([
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudiante->id,
            'contenido' => $contenido,
            'emitido_en' => now(),
            'emitido_por' => $this->cuentaDocente->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $contenido;
    }

    /**
     * Una cuenta con el permiso, docente de otro examen.
     */
    private function docenteAjeno(): User
    {
        $cuenta = $this->usuarioConPermisos(['codigos_qr']);
        $this->docenteConCuenta($cuenta);

        return $cuenta;
    }
}
