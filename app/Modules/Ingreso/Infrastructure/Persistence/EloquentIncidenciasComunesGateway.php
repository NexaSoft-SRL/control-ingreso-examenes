<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\IncidenciasComunesGateway;
use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Support\Facades\DB;

final class EloquentIncidenciasComunesGateway implements IncidenciasComunesGateway
{
    use LeeFilas;

    public function deUsuario(int $usuarioId): array
    {
        $preferidas = [];
        $propias = [];

        foreach (DB::table('incidencias_comunes')->where('usuario_id', $usuarioId)->orderBy('nombre')->get() as $fila) {
            $c = $this->columnas($fila);
            $tipo = $this->texto($c['tipo'] ?? null);
            $gravedad = $this->texto($c['gravedad'] ?? null) ?? Gravedad::Leve->value;

            if ($tipo !== null) {
                $preferidas[$tipo] = $gravedad;
            } else {
                $propias[] = [
                    'id' => $this->entero($c['id'] ?? null),
                    'tipo' => TipoIncidencia::Otra->value,
                    'nombre' => $this->texto($c['nombre'] ?? null) ?? '',
                    'gravedad' => $gravedad,
                    'propia' => true,
                    'predeterminada' => null,
                ];
            }
        }

        $lista = [];

        foreach (TipoIncidencia::reportables() as $valor) {
            $tipo = TipoIncidencia::from($valor);

            // «Otra» no va en la lista: es la puerta a una no prevista.
            if ($tipo === TipoIncidencia::Otra) {
                continue;
            }

            $lista[] = [
                'id' => null,
                'tipo' => $valor,
                'nombre' => $tipo->etiqueta(),
                'gravedad' => $preferidas[$valor] ?? $tipo->gravedad()->value,
                'propia' => false,
                'predeterminada' => $tipo->gravedad()->value,
            ];
        }

        return [...$lista, ...$propias];
    }

    public function duenoParaExamen(int $examenId, ?int $usuarioId): ?int
    {
        if ($usuarioId !== null) {
            $esDocente = DB::table('examenes')->where('id', $examenId)->where('creado_por', $usuarioId)->exists()
                || DB::table('examen_grupo as incluido')
                    ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
                    ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                    ->where('incluido.examen_id', $examenId)
                    ->where('docente.user_id', $usuarioId)
                    ->exists();

            if ($esDocente) {
                return $usuarioId;
            }
        }

        return $this->enteroONulo(DB::table('examenes')->where('id', $examenId)->value('creado_por'));
    }

    public function gravedadDeTipo(?int $usuarioId, TipoIncidencia $tipo): Gravedad
    {
        $preferida = $usuarioId === null ? null : DB::table('incidencias_comunes')
            ->where('usuario_id', $usuarioId)
            ->where('tipo', $tipo->value)
            ->value('gravedad');

        return Gravedad::tryFrom(is_string($preferida) ? $preferida : '') ?? $tipo->gravedad();
    }

    public function gravedadDeNombre(?int $usuarioId, string $nombre): ?Gravedad
    {
        if ($usuarioId === null) {
            return null;
        }

        $gravedad = DB::table('incidencias_comunes')
            ->where('usuario_id', $usuarioId)
            ->whereNotNull('nombre')
            ->whereRaw('lower(nombre) = lower(?)', [trim($nombre)])
            ->value('gravedad');

        return Gravedad::tryFrom(is_string($gravedad) ? $gravedad : '');
    }

    public function guardarTipo(int $usuarioId, TipoIncidencia $tipo, Gravedad $gravedad): void
    {
        $consulta = DB::table('incidencias_comunes')->where('usuario_id', $usuarioId)->where('tipo', $tipo->value);

        if ($gravedad === $tipo->gravedad()) {
            $consulta->delete();

            return;
        }

        $ahora = now();

        if ((clone $consulta)->exists()) {
            $consulta->update(['gravedad' => $gravedad->value, 'updated_at' => $ahora]);

            return;
        }

        DB::table('incidencias_comunes')->insert([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo->value,
            'nombre' => null,
            'gravedad' => $gravedad->value,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function guardarPropia(int $usuarioId, string $nombre, Gravedad $gravedad): void
    {
        $nombre = trim($nombre);
        $ahora = now();
        $consulta = DB::table('incidencias_comunes')
            ->where('usuario_id', $usuarioId)
            ->whereNotNull('nombre')
            ->whereRaw('lower(nombre) = lower(?)', [$nombre]);

        if ((clone $consulta)->exists()) {
            $consulta->update(['gravedad' => $gravedad->value, 'updated_at' => $ahora]);

            return;
        }

        DB::table('incidencias_comunes')->insert([
            'usuario_id' => $usuarioId,
            'tipo' => null,
            'nombre' => $nombre,
            'gravedad' => $gravedad->value,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function quitarPropia(int $usuarioId, int $id): bool
    {
        return DB::table('incidencias_comunes')
            ->where('id', $id)
            ->where('usuario_id', $usuarioId)
            ->whereNotNull('nombre')
            ->delete() > 0;
    }
}
