<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use App\Modules\Administracion\Domain\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Los datos de demostracion mas carga alta, para probar los reportes del
 * administrador y los listados con volumen:
 *
 *   composer datos:volumen     (migrate:fresh + este seeder)
 *
 * Agrega 150 primeros parciales de otros docentes de FCyT, repartidos en
 * los diez dias anteriores y los diez siguientes a hoy, cada uno con su
 * lista, su habilitacion y, los ya rendidos, sus ingresos. Las cuentas son
 * las mismas de `DemoSeeder`. Se puede volver a ejecutar sin duplicar.
 */
class VolumenSeeder extends DemoSeeder
{
    private const EXAMENES = 150;

    private const HORAS = ['06:45:00', '08:15:00', '09:45:00', '11:15:00', '14:15:00', '15:45:00', '17:15:00'];

    public function run(DetectarPeriodos $detectar, ImportarOferta $importar, FuenteOfertaGateway $fuente): void
    {
        parent::run($detectar, $importar, $fuente);

        $proponedor = app(ProponedorUsuario::class);
        $rolId = Role::where('name', 'Docente')->firstOrFail()->id;
        $clave = Hash::make(Str::random(40));

        $aulas = $this->enteros(
            DB::table('aulas')
                ->where('facultad_id', $this->fcytId)
                ->whereNotNull('edificio_id')
                ->orderBy('nombre')
                ->pluck('id')
        );

        // Un grupo por asignatura sin examenes; si ya se sembro, los
        // mismos grupos de la vez anterior.
        $sembrados = DB::table('cargas_inscritos')->where('archivo', 'like', 'volumen_%')->pluck('grupo_id')->all();

        $consulta = DB::table('grupos')
            ->join('docentes', 'docentes.id', '=', 'grupos.docente_id')
            ->where('grupos.facultad_id', $this->fcytId)
            ->where('grupos.periodo_id', $this->periodoId);

        if ($sembrados === []) {
            $consulta->whereNotIn('grupos.asignatura_id', DB::table('examenes')->select('asignatura_id'));
        } else {
            $consulta->whereIn('grupos.id', $sembrados);
        }

        $grupos = $consulta
            ->orderBy('grupos.asignatura_id')
            ->orderBy('grupos.id')
            ->get(['grupos.id', 'grupos.asignatura_id', 'docentes.id as docente_id', 'docentes.user_id', 'docentes.nombre_completo']);

        $vistas = [];
        // El mismo docente puede dar varias de las asignaturas elegidas.
        $cuentasCreadas = [];
        $numero = 0;

        foreach ($grupos as $grupo) {
            $asignaturaId = $this->entero($grupo->asignatura_id);

            if (isset($vistas[$asignaturaId]) || $aulas === []) {
                continue;
            }

            if ($numero >= self::EXAMENES) {
                break;
            }

            $vistas[$asignaturaId] = true;
            $docenteId = $this->entero($grupo->docente_id);
            $cuenta = $cuentasCreadas[$docenteId]
                ?? ($grupo->user_id === null ? null : $this->entero($grupo->user_id));

            if ($cuenta === null) {
                $nombre = $this->texto($grupo->nombre_completo);

                $cuenta = DB::table('users')->insertGetId([
                    'name' => $nombre,
                    'usuario' => $proponedor->proponer($nombre),
                    'password' => $clave,
                    'password_changed_at' => now(),
                    'is_active' => true,
                    'role_id' => $rolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('docentes')->where('id', $docenteId)->update(['user_id' => $cuenta]);
                $cuentasCreadas[$docenteId] = $cuenta;
            }

            $grupoId = $this->entero($grupo->id);
            $inscritos = $this->inscribir($grupoId, 40 + $numero % 25, 'DOCENTE', $cuenta);

            DB::table('cargas_inscritos')
                ->where('grupo_id', $grupoId)
                ->update(['archivo' => 'volumen_grupo_'.$grupoId.'.csv']);

            $dias = $numero % 21 - 10;
            $aula = [$aulas[$numero % count($aulas)] => $cuenta];

            $examen = $this->examen(
                $asignaturaId,
                'PRIMER_PARCIAL',
                $cuenta,
                $this->ahoraLocal->copy()->addDays($dias)->toDateString(),
                self::HORAS[$numero % count(self::HORAS)],
                90,
                [$grupoId],
                $aula,
                null,
            );

            if ($examen['nuevo']) {
                $reparto = $this->habilitar(
                    $examen['id'],
                    $inscritos,
                    array_keys($aula),
                    $cuenta,
                    [$numero % 9 + 2 => 'No cumplió los requisitos de la materia.'],
                );

                $this->emitirQr($examen['id'], array_keys($reparto), $cuenta, false);

                if ($dias < 0) {
                    $this->ingresar($examen['id'], $reparto, $aula, 80 + $numero % 15, 25 * 60, -$dias * 86400);
                }
            }

            $numero++;
        }
    }
}
