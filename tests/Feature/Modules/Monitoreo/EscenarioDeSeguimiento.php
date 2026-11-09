<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Monitoreo;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Ingreso\Domain\Models\Ingreso;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;

/**
 * Lo que comparten las pruebas del seguimiento: el reloj detenido a media
 * manana (el examen de `DatosDeExamen` empieza cinco minutos antes de
 * «ahora» y no debe cruzar la medianoche), cuentas con el permiso de la
 * pantalla e incidencias armadas directamente sobre la tabla.
 */
trait EscenarioDeSeguimiento
{
    use DatosAcademicos;
    use DatosDeExamen;
    use UsuarioConPermisos;

    protected function detenerReloj(string $hora = '10:00:00'): void
    {
        $this->travelTo(now()->setTimeFromTimeString($hora));
    }

    protected function docenteQueSigue(?string $nombre = null): Docente
    {
        return $this->docenteConCuenta(
            $this->usuarioConPermisos(['seguimiento_vivo']),
            $nombre,
        );
    }

    protected function cuentaDe(Docente $docente): User
    {
        return User::findOrFail($docente->user_id);
    }

    protected function auxiliarQueSigue(?string $nombre = null): User
    {
        $cuenta = $this->usuarioConPermisos(['seguimiento_vivo']);

        if ($nombre !== null) {
            $cuenta->forceFill(['name' => $nombre])->save();
        }

        return $this->auxiliar($cuenta);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function incidencia(Examen $examen, ?Aula $aula, array $atributos = []): int
    {
        return DB::table('incidencias')->insertGetId(array_merge([
            'examen_id' => $examen->id,
            'aula_id' => $aula?->id,
            'tipo' => 'MATERIAL_NO_PERMITIDO',
            'origen' => 'PERSONA',
            'detalle' => 'Calculadora programable',
            'ocurrida_en' => now(),
            'reportada_por' => $examen->creado_por,
            'created_at' => now(),
            'updated_at' => now(),
        ], $atributos));
    }

    protected function expulsar(Ingreso $ingreso): int
    {
        return DB::table('incidencias')->insertGetId([
            'examen_id' => $ingreso->examen_id,
            'aula_id' => $ingreso->aula_id,
            'estudiante_id' => $ingreso->estudiante_id,
            'ingreso_id' => $ingreso->id,
            'tipo' => 'EXPULSION',
            'origen' => 'PERSONA',
            'detalle' => 'Usó el celular',
            'ocurrida_en' => now(),
            'reportada_por' => $ingreso->registrado_por,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
