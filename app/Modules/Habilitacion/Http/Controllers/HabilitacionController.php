<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Controllers;

use App\Modules\Habilitacion\Application\Actions\GestionarHabilitacion;
use App\Modules\Habilitacion\Application\DTOs\EstudianteHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ExamenHabilitacionData;
use App\Modules\Habilitacion\Http\Requests\RegistrarCondicionesRequest;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class HabilitacionController
{
    public function examenes(GestionarHabilitacion $gestionar): JsonResponse
    {
        $data = array_map(
            static fn (ExamenHabilitacionData $examen): array => $examen->toArray(),
            $gestionar->listarExamenes(),
        );

        return response()->json(['data' => $data]);
    }

    public function estudiantes(int $examen, GestionarHabilitacion $gestionar): JsonResponse
    {
        $this->asegurarExamenExiste($examen, $gestionar);

        $estudiantes = $gestionar->listarEstudiantes($examen);
        $habilitados = count(array_filter(
            $estudiantes,
            static fn (EstudianteHabilitacionData $estudiante): bool => $estudiante->condicion === 'HABILITADO',
        ));

        return response()->json([
            'data' => array_map(
                static fn (EstudianteHabilitacionData $estudiante): array => $estudiante->toArray(),
                $estudiantes,
            ),
            'totales' => [
                'total' => count($estudiantes),
                'habilitados' => $habilitados,
                'no_habilitados' => count($estudiantes) - $habilitados,
            ],
        ]);
    }

    public function registrarCondiciones(
        RegistrarCondicionesRequest $request,
        int $examen,
        GestionarHabilitacion $gestionar,
    ): JsonResponse {
        $this->asegurarExamenExiste($examen, $gestionar);
        $datos = $request->toData();
        $gestionar->registrarCondiciones(
            $examen,
            $datos['estudiante_ids'],
            $datos['condicion'],
            $datos['motivo'],
            $this->authenticatedUserId(),
        );

        $estudiantes = $gestionar->listarEstudiantes($examen);
        $habilitados = count(array_filter(
            $estudiantes,
            static fn (EstudianteHabilitacionData $estudiante): bool => $estudiante->condicion === 'HABILITADO',
        ));

        return response()->json([
            'message' => 'Condición registrada para los estudiantes seleccionados.',
            'data' => array_map(
                static fn (EstudianteHabilitacionData $estudiante): array => $estudiante->toArray(),
                $estudiantes,
            ),
            'totales' => [
                'total' => count($estudiantes),
                'habilitados' => $habilitados,
                'no_habilitados' => count($estudiantes) - $habilitados,
            ],
        ]);
    }

    public function exportar(int $examen, GestionarHabilitacion $gestionar): Response
    {
        $this->asegurarExamenExiste($examen, $gestionar);
        $estudiantes = $gestionar->listarEstudiantes($examen);

        return response()->streamDownload(static function () use ($estudiantes): void {
            $salida = fopen('php://output', 'wb');

            if ($salida === false) {
                return;
            }

            fputcsv($salida, [
                'Código', 'Documento', 'Nombre', 'Carrera', 'Condición', 'Motivo',
                'Registrado por', 'Fecha de registro',
            ]);

            foreach ($estudiantes as $estudiante) {
                fputcsv($salida, [
                    $estudiante->codigoUniversitario,
                    $estudiante->ci,
                    trim($estudiante->nombre.' '.$estudiante->apellido),
                    $estudiante->carrera,
                    $estudiante->condicion,
                    $estudiante->motivo,
                    $estudiante->registradoPor,
                    self::fechaLocal($estudiante->fechaHabilitacion),
                ]);
            }

            fclose($salida);
        }, "habilitacion-examen-{$examen}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * El listado exportado lo lee una persona: la fecha de registro va en la
     * hora de Bolivia y no en el formato técnico de la API.
     */
    private static function fechaLocal(?string $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        return (new DateTimeImmutable($fecha))
            ->setTimezone(new DateTimeZone('America/La_Paz'))
            ->format('d/m/Y H:i');
    }

    private function asegurarExamenExiste(int $examen, GestionarHabilitacion $gestionar): void
    {
        abort_unless($gestionar->existeExamen($examen), 404, 'El examen no existe.');
    }

    private function authenticatedUserId(): int
    {
        $id = Auth::guard('web')->id();

        if (! is_int($id) && ! is_string($id)) {
            throw new LogicException('No existe un usuario autenticado válido.');
        }

        return (int) $id;
    }
}
