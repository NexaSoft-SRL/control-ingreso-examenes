<?php
namespace App\Modules\Examenes\Http\Controllers;

class QRController
{
    /**
     * @param int $examen_id
     * @return array<string, mixed>
     */
    public function generarLote(int $examen_id): array
    {
        $estudiantes_demo = [
            ['id' => 1, 'nombres' => 'Alvarado Claros', 'apellidos' => 'Kevin René', 'aula' => 'Aula 251', 'piso' => '2do Piso', 'edificio' => 'Edificio Académico 1'],
            ['id' => 2, 'nombres' => 'Camacho Zeballos', 'apellidos' => 'Diego', 'aula' => 'Aula 252', 'piso' => '2do Piso', 'edificio' => 'Edificio Académico 1'],
            ['id' => 3, 'nombres' => 'Orellana', 'apellidos' => 'Maria', 'aula' => 'Auditorio Principal', 'piso' => 'Planta Baja', 'edificio' => 'Edificio Antiguo'],
        ];

        $qrs_generados = [];

        foreach ($estudiantes_demo as $estudiante) {
            $token = bin2hex(random_bytes(16)); 
            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . $token;

            $qrs_generados[] = [
                'estudiante' => $estudiante['nombres'] . ' ' . $estudiante['apellidos'],
                'token' => $token,
                'qr_base64' => $qr_url,
                'aula' => $estudiante['aula'],
                'piso' => $estudiante['piso'],
                'edificio' => $estudiante['edificio'],
            ];
        }

        return [
            'success' => true,
            'mensaje' => 'Lote de ' . count($qrs_generados) . ' credenciales generado exitosamente.',
            'datos' => $qrs_generados,
            'examen_info' => 'Examen #' . $examen_id . ' - Fecha Demo: ' . date('Y-m-d')
        ];
    }
}