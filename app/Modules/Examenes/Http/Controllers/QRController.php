<?php
namespace App\Modules\Examenes\Http\Controllers;

use App\Models\Examen;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class QRController extends Controller
{
    // Método rápido para la demostración de hoy
    public function generarLote($examen_id)
    {
        // Para la demo, vamos a simular 3 estudiantes si no encontramos las relaciones reales
        // ya que no tenemos a la vista el modelo Examen.php
        $estudiantes_demo = [
            ['id' => 1, 'nombres' => 'Alvarado Claros', 'apellidos' => 'Kevin René', 'aula' => 'Aula 251', 'piso' => '2do Piso', 'edificio' => 'Edificio Académico 1'],
            ['id' => 2, 'nombres' => 'Camacho Zeballos', 'apellidos' => 'Diego', 'aula' => 'Aula 252', 'piso' => '2do Piso', 'edificio' => 'Edificio Académico 1'],
            ['id' => 3, 'nombres' => 'Orellana', 'apellidos' => 'Maria', 'aula' => 'Auditorio Principal', 'piso' => 'Planta Baja', 'edificio' => 'Edificio Antiguo'],
        ];

        $qrs_generados = [];

        foreach ($estudiantes_demo as $estudiante) {
            // Generamos un token aleatorio y seguro para cada uno (no predecible)
            $token = Str::uuid()->toString(); 
            
            // Generamos la imagen del QR en base64 para mostrarla fácil en React
            $qr_image = base64_encode(QrCode::format('png')->size(200)->generate($token));

            $qrs_generados[] = [
                'estudiante' => $estudiante['nombres'] . ' ' . $estudiante['apellidos'],
                'token' => $token,
                'qr_base64' => 'data:image/png;base64,' . $qr_image,
                'aula' => $estudiante['aula'],
                'piso' => $estudiante['piso'],
                'edificio' => $estudiante['edificio'],
            ];
        }

        return response()->json([
            'success' => true,
            'mensaje' => 'Lote de ' . count($qrs_generados) . ' credenciales generado exitosamente.',
            'datos' => $qrs_generados,
            'examen_info' => 'Examen #'.$examen_id.' - Fecha Demo: '. date('Y-m-d')
        ]);
    }
}