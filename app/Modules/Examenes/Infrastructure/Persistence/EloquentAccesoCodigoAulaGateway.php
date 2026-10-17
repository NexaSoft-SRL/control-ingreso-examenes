<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\AccesoCodigoAulaGateway;
use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAccesoCodigoAulaGateway implements AccesoCodigoAulaGateway
{
    public function __construct(
        private BitacoraGateway $bitacora,
    ) {}

    public function ingresar(string $codigo, string $nombreColaborador): ?array
    {
        /** @var object{id: int, examen_id: int, aula_id: int, codigo: string}|null $codigoRegistro */
        $codigoRegistro = DB::table('examen_aula_codigos')
            ->where('codigo', $codigo)
            ->first();

        if ($codigoRegistro === null) {
            return null;
        }

        /** @var object{id: int, fecha: string, hora_inicio: string, duracion_minutos: int}|null $examen */
        $examen = DB::table('examenes')
            ->where('id', $codigoRegistro->examen_id)
            ->first();

        if ($examen === null) {
            return null;
        }

        $fecha = substr((string) $examen->fecha, 0, 10);
        $horaInicio = substr((string) $examen->hora_inicio, 0, 5);
        $duracion = (int) $examen->duracion_minutos;

        $inicioTs = strtotime("{$fecha} {$horaInicio}:00");
        if ($inicioTs === false) {
            return null;
        }

        $ventanaInicio = $inicioTs - (60 * 60); // 60 minutos previos al inicio
        $ventanaFin = $inicioTs + ($duracion * 60); // fin del examen
        $ahora = now()->timestamp;

        if ($ahora < $ventanaInicio || $ahora > $ventanaFin) {
            return null;
        }

        /** @var object{id: int, nombre: string}|null $aula */
        $aula = DB::table('aulas')->where('id', $codigoRegistro->aula_id)->first();
        $aulaNombre = $aula !== null ? (string) $aula->nombre : '';

        $token = bin2hex(random_bytes(32));

        $acceso = AccesoCodigoAula::create([
            'examen_id' => $codigoRegistro->examen_id,
            'aula_id' => $codigoRegistro->aula_id,
            'codigo' => $codigo,
            'nombre_colaborador' => $nombreColaborador,
            'token' => $token,
            'ultimo_acceso_en' => now(),
        ]);

        $this->bitacora->registrar(
            null,
            'acceso_codigo_aula.ingreso',
            'accesos_codigo_aula',
            $acceso->id,
            "Ingreso con código de aula: {$nombreColaborador} en aula {$aulaNombre}",
        );

        return [
            'token' => $token,
            'examen_id' => $codigoRegistro->examen_id,
            'aula_id' => $codigoRegistro->aula_id,
            'aula_nombre' => $aulaNombre,
            'nombre_colaborador' => $nombreColaborador,
            'expira_en' => date('Y-m-d H:i:s', $ventanaFin),
        ];
    }

    public function salir(string $token): void
    {
        AccesoCodigoAula::where('token', $token)
            ->whereNull('revocado_en')
            ->update(['revocado_en' => now()]);
    }

    public function sesion(string $token): array
    {
        /** @var AccesoCodigoAula|null $acceso */
        $acceso = AccesoCodigoAula::where('token', $token)
            ->whereNull('revocado_en')
            ->first();

        if ($acceso === null) {
            return ['estado' => 'no_valido'];
        }

        /** @var object{id: int, fecha: string, hora_inicio: string, duracion_minutos: int}|null $examen */
        $examen = DB::table('examenes')->where('id', $acceso->examen_id)->first();
        if ($examen === null) {
            return ['estado' => 'no_valido'];
        }

        $fecha = substr((string) $examen->fecha, 0, 10);
        $horaInicio = substr((string) $examen->hora_inicio, 0, 5);
        $duracion = (int) $examen->duracion_minutos;

        $inicioTs = strtotime("{$fecha} {$horaInicio}:00");
        $ventanaFin = ($inicioTs ?: 0) + ($duracion * 60);

        if (now()->timestamp > $ventanaFin) {
            $acceso->revocado_en = now();
            $acceso->save();

            return ['estado' => 'finalizado'];
        }

        $acceso->ultimo_acceso_en = now();
        $acceso->save();

        /** @var object{id: int, nombre: string}|null $aula */
        $aula = DB::table('aulas')->where('id', $acceso->aula_id)->first();

        return [
            'estado' => 'ok',
            'data' => [
                'token' => $acceso->token,
                'examen_id' => $acceso->examen_id,
                'aula_id' => $acceso->aula_id,
                'aula_nombre' => $aula !== null ? (string) $aula->nombre : '',
                'nombre_colaborador' => $acceso->nombre_colaborador,
                'expira_en' => date('Y-m-d H:i:s', $ventanaFin),
            ],
        ];
    }
}
