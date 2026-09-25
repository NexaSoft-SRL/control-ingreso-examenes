<?php

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Domain\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class EstudianteController
{
    public function cargaMasiva(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        /** @var UploadedFile|null $file */
        $file = $request->file('archivo');

        if (!$file) {
            return response()->json(['message' => 'No se subió ningún archivo'], 400);
        }

        $path = $file->getRealPath();
        $data = [];

        $handle = fopen($path, 'r');
        if ($handle !== false) {
            // Saltar la primera línea (encabezados del CSV)
            fgetcsv($handle, 1000, ',');

            // Leer fila por fila
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (is_array($row) && count($row) >= 5) {
                    $data[] = [
                        'codigo_sis' => (string) $row[0],
                        'ci'         => (string) $row[1],
                        'nombres'    => (string) $row[2],
                        'apellidos'  => (string) $row[3],
                        'correo'     => (string) $row[4],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            fclose($handle);
        }

        // Inserción masiva ignorando los códigos SIS o CI duplicados
        if (!empty($data)) {
            Estudiante::insertOrIgnore($data);
        }

        return response()->json([
            'message' => 'Carga masiva procesada exitosamente',
            'registros_leidos' => count($data)
        ]);
    }
}