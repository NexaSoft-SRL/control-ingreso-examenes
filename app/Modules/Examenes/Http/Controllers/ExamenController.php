<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Administracion\Domain\Models\Ambiente;
use App\Modules\Examenes\Application\Actions\ListarEstudiantesExamen;
use App\Modules\Examenes\Application\Actions\ListarExamenes;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\GrupoAsignatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ExamenController
{
    public function index(ListarExamenes $listar): JsonResponse
    {
        $data = array_map(
            fn (Examen $examen): array => $this->serializar($examen),
            $listar->execute(),
        );

        return response()->json(['data' => $data]);
    }

    public function estudiantes(ListarEstudiantesExamen $listar): JsonResponse
    {
        return response()->json(['data' => $listar->execute()]);
    }

    public function asignaciones(int $examen): JsonResponse
    {
        $this->asegurarExamenExiste($examen);

        $candidatos = DB::table('students as s')
            ->select([
                's.id',
                's.codigo_universitario',
                's.nombre',
                's.apellido',
                's.carrera',
                's.ci',
                's.activo',
            ])
            ->join('habilitaciones_examen as h', 'h.estudiante_id', '=', 's.id')
            ->leftJoin('asignaciones_ambiente as a', function ($join) use ($examen): void {
                $join->on('a.estudiante_id', '=', 's.id')
                    ->where('a.examen_id', '=', $examen);
            })
            ->where('h.examen_id', $examen)
            ->where('h.estado', 'HABILITADO')
            ->where('s.activo', true)
            ->whereNull('a.id')
            ->orderBy('s.apellido')
            ->orderBy('s.nombre')
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'codigo_universitario' => $fila->codigo_universitario,
                'nombre' => $fila->nombre,
                'apellido' => $fila->apellido,
                'carrera' => $fila->carrera,
                'ci' => $fila->ci,
            ])
            ->all();

        $ambientes = DB::table('ambientes as a')
            ->leftJoin('asignaciones_ambiente as asig', function ($join) use ($examen): void {
                $join->on('asig.ambiente_id', '=', 'a.id')
                    ->where('asig.examen_id', '=', $examen);
            })
            ->select([
                'a.id',
                'a.nombre',
                'a.ubicacion',
                'a.capacidad',
                'a.estado',
                DB::raw('COUNT(asig.id) as ocupados'),
            ])
            ->groupBy(['a.id', 'a.nombre', 'a.ubicacion', 'a.capacidad', 'a.estado'])
            ->orderBy('a.nombre')
            ->get()
            ->map(function ($fila) use ($examen): array {
                $ocupados = (int) $fila->ocupados;

                return [
                    'id' => (int) $fila->id,
                    'nombre' => $fila->nombre,
                    'ubicacion' => $fila->ubicacion,
                    'capacidad' => (int) $fila->capacidad,
                    'estado' => $fila->estado,
                    'ocupados' => $ocupados,
                    'disponible' => max((int) $fila->capacidad - $ocupados, 0),
                    'estudiantes' => $this->estudiantesAsignadosAmbientales((int) $fila->id, $examen),
                ];
            })
            ->all();

        return response()->json(['data' => [
            'candidatos' => $candidatos,
            'ambientes' => $ambientes,
        ]]);
    }

    public function asignarEstudiantes(Request $request, int $examen): JsonResponse
    {
        $this->asegurarExamenExiste($examen);

        $data = $request->validate([
            'ambiente_id' => ['required', 'integer', 'exists:ambientes,id'],
            'estudiante_ids' => ['required', 'array', 'min:1'],
            'estudiante_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ]);

        $ambiente = Ambiente::query()->findOrFail((int) $data['ambiente_id']);
        $ids = array_values(array_unique(array_map('intval', $data['estudiante_ids'])));

        $idsHabilitados = DB::table('habilitaciones_examen as h')
            ->join('students as s', 's.id', '=', 'h.estudiante_id')
            ->where('h.examen_id', $examen)
            ->where('h.estado', 'HABILITADO')
            ->where('s.activo', true)
            ->pluck('h.estudiante_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidos = array_values(array_diff($ids, $idsHabilitados));

        if ($invalidos !== []) {
            return response()->json([
                'message' => 'Solo pueden asignarse estudiantes habilitados y activos.',
            ], 422);
        }

        $yaAsignados = DB::table('asignaciones_ambiente')
            ->where('examen_id', $examen)
            ->whereIn('estudiante_id', $ids)
            ->pluck('estudiante_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($yaAsignados !== []) {
            return response()->json([
                'message' => 'Uno o más estudiantes ya están asignados a este examen.',
            ], 422);
        }

        $ocupados = (int) DB::table('asignaciones_ambiente')
            ->where('examen_id', $examen)
            ->where('ambiente_id', $ambiente->getKey())
            ->count();

        if ($ocupados + count($ids) > (int) $ambiente->capacidad) {
            return response()->json([
                'message' => 'El ambiente no tiene cupo suficiente para la cantidad solicitada.',
            ], 422);
        }

        DB::transaction(function () use ($examen, $ambiente, $ids): void {
            $usuarioId = Auth::guard('web')->id();

            foreach ($ids as $estudianteId) {
                DB::table('asignaciones_ambiente')->insert([
                    'examen_id' => $examen,
                    'ambiente_id' => $ambiente->getKey(),
                    'estudiante_id' => $estudianteId,
                    'usuario_id' => $usuarioId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Estudiantes asignados al ambiente correctamente.',
            'data' => $this->detallesAmbiente($examen, $ambiente->getKey()),
        ]);
    }

    public function quitarEstudiante(Request $request, int $examen): JsonResponse
    {
        $this->asegurarExamenExiste($examen);

        $data = $request->validate([
            'ambiente_id' => ['required', 'integer', 'exists:ambientes,id'],
            'estudiante_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $eliminadas = DB::table('asignaciones_ambiente')
            ->where('examen_id', $examen)
            ->where('ambiente_id', (int) $data['ambiente_id'])
            ->where('estudiante_id', (int) $data['estudiante_id'])
            ->delete();

        if ($eliminadas === 0) {
            return response()->json([
                'message' => 'El estudiante no está asignado a este ambiente.',
            ], 404);
        }

        return response()->json([
            'message' => 'Estudiante quitado del ambiente correctamente.',
        ]);
    }

    public function controlIndex(ListarExamenes $listar): JsonResponse
    {
        return $this->index($listar);
    }

    public function controlEstudiantes(ListarEstudiantesExamen $listar): JsonResponse
    {
        return $this->estudiantes($listar);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Examen $examen): array
    {
        $grupo = $examen->grupo;

        if (! $grupo instanceof GrupoAsignatura) {
            throw new LogicException('El examen no tiene un grupo válido.');
        }

        $asignatura = $grupo->asignatura;
        $docente = $grupo->docente;

        if (! $asignatura instanceof Asignatura || ! $docente instanceof Docente) {
            throw new LogicException('El grupo del examen no tiene asignatura y docente válidos.');
        }

        return [
            'id' => $examen->getKey(),
            'nombre' => $examen->nombre,
            'fecha' => $examen->fecha,
            'hora_inicio' => $examen->hora_inicio,
            'duracion_minutos' => $examen->duracion_minutos,
            'grupo' => [
                'id' => $grupo->getKey(),
                'codigo_grupo' => $grupo->codigo_grupo,
                'asignatura' => [
                    'id' => $asignatura->getKey(),
                    'codigo' => $asignatura->codigo,
                    'nombre' => $asignatura->nombre,
                ],
                'docente' => [
                    'id' => $docente->getKey(),
                    'nombres' => $docente->nombres,
                    'apellidos' => $docente->apellidos,
                ],
            ],
        ];
    }

    private function asegurarExamenExiste(int $examen): void
    {
        abort_unless(Examen::query()->whereKey($examen)->exists(), 404, 'El examen no existe.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function estudiantesAsignadosAmbientales(int $ambienteId, int $examenId): array
    {
        return DB::table('asignaciones_ambiente as a')
            ->join('students as s', 's.id', '=', 'a.estudiante_id')
            ->where('a.ambiente_id', $ambienteId)
            ->where('a.examen_id', $examenId)
            ->orderBy('s.apellido')
            ->orderBy('s.nombre')
            ->get(['s.id', 's.codigo_universitario', 's.nombre', 's.apellido', 's.carrera'])
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'codigo_universitario' => $fila->codigo_universitario,
                'nombre' => $fila->nombre,
                'apellido' => $fila->apellido,
                'carrera' => $fila->carrera,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function detallesAmbiente(int $examenId, int $ambienteId): array
    {
        $ambiente = DB::table('ambientes')
            ->where('id', $ambienteId)
            ->first();

        if (! $ambiente) {
            return [];
        }

        $ocupados = (int) DB::table('asignaciones_ambiente')
            ->where('examen_id', $examenId)
            ->where('ambiente_id', $ambienteId)
            ->count();

        return [
            'id' => (int) $ambiente->id,
            'nombre' => $ambiente->nombre,
            'ubicacion' => $ambiente->ubicacion,
            'capacidad' => (int) $ambiente->capacidad,
            'estado' => $ambiente->estado,
            'ocupados' => $ocupados,
            'disponible' => max((int) $ambiente->capacidad - $ocupados, 0),
            'estudiantes' => $this->estudiantesAsignadosAmbientales($ambienteId, $examenId),
        ];
    }
}
