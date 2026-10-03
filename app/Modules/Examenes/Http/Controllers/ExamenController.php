<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\GestionarAsignacionAmbiente;
use App\Modules\Examenes\Application\Actions\ListarEstudiantesExamen;
use App\Modules\Examenes\Application\Actions\ListarExamenes;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\GrupoAsignatura;
use App\Modules\Examenes\Http\Requests\AsignarEstudiantesAmbienteRequest;
use App\Modules\Examenes\Http\Requests\QuitarEstudianteAmbienteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function asignaciones(int $examen, GestionarAsignacionAmbiente $gestionar): JsonResponse
    {
        $this->asegurarExamenExiste($examen, $gestionar);

        return response()->json(['data' => $gestionar->listar($examen)]);
    }

    public function asignarEstudiantes(
        AsignarEstudiantesAmbienteRequest $request,
        int $examen,
        GestionarAsignacionAmbiente $gestionar,
    ): JsonResponse {
        $this->asegurarExamenExiste($examen, $gestionar);
        $datos = $request->toData();

        $rechazo = $gestionar->asignar(
            $examen,
            $datos['ambiente_id'],
            $datos['estudiante_ids'],
            $this->usuarioId(),
        );

        if ($rechazo !== null) {
            return response()->json(['message' => $rechazo], 422);
        }

        return response()->json([
            'message' => 'Estudiantes asignados al ambiente correctamente.',
            'data' => $gestionar->detalleAmbiente($examen, $datos['ambiente_id']),
        ]);
    }

    public function quitarEstudiante(
        QuitarEstudianteAmbienteRequest $request,
        int $examen,
        GestionarAsignacionAmbiente $gestionar,
    ): JsonResponse {
        $this->asegurarExamenExiste($examen, $gestionar);
        $datos = $request->toData();

        if (! $gestionar->quitar($examen, $datos['ambiente_id'], $datos['estudiante_id'])) {
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

    // --- NUEVOS MÉTODOS HU-08 ---
    public function store(Request $request): JsonResponse
    {
    /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'grupo_id' => 'required|integer|exists:grupos_asignatura,id',
            'nombre' => 'required|string|in:Primer parcial,Segundo parcial,Examen final,Instancia',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'duracion_minutos' => 'required|integer|min:15|max:480',
        ], [
            'grupo_id.exists' => 'El ID del grupo no existe en la base de datos.',
            'nombre.in' => 'El tipo de examen no es uno de los conocidos.',
            'duracion_minutos.min' => 'La duración no puede ser menor a quince minutos.',
            'duracion_minutos.max' => 'La duración no puede ser mayor a ocho horas.',
        ]);
        $conflicto = Examen::where('grupo_id', $validated['grupo_id'])
            ->where('fecha', $validated['fecha'])
            ->where('hora_inicio', $validated['hora_inicio'])
            ->exists();

        if ($conflicto) {
            return response()->json(['message' => 'El grupo ya tiene un examen en esa fecha y a esa hora.'], 422);
        }

        $examen = Examen::create($validated);

        return response()->json([
            'message' => 'Examen registrado correctamente.',
            'data' => $this->serializar($examen),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $examen = Examen::findOrFail($id);

        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'nombre' => 'required|string|in:Primer parcial,Segundo parcial,Examen final,Instancia',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'duracion_minutos' => 'required|integer|min:15|max:480',
        ], [
            'nombre.in' => 'El tipo de examen no es uno de los conocidos.',
            'duracion_minutos.min' => 'La duración no puede ser menor a quince minutos.',
            'duracion_minutos.max' => 'La duración no puede ser mayor a ocho horas.',
        ]);

        $examen->update($validated);

        return response()->json(['message' => 'Examen actualizado.', 'data' => $this->serializar($examen)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $examen = Examen::findOrFail($id);

        $examen->delete();

        return response()->json(['message' => 'Examen eliminado de la lista.']);
    }
    // ----------------------------

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

    private function asegurarExamenExiste(int $examen, GestionarAsignacionAmbiente $gestionar): void
    {
        abort_unless($gestionar->existeExamen($examen), 404, 'El examen no existe.');
    }

    private function usuarioId(): ?int
    {
        $id = Auth::guard('web')->id();

        if (is_int($id)) {
            return $id;
        }

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }
}
