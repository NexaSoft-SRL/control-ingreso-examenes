<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Administracion\Domain\Models\Ambiente;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\Student;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración del sprint 1, iguales para todo el equipo.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Deja una cuenta por rol, el padrón con un estudiante dado de baja, los
 * docentes, los ambientes y las asignaturas con las que se ven las
 * pantallas de HU-02 a HU-06, más unos asientos de bitácora para HU-07.
 * Se puede volver a ejecutar: no duplica nada.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $usuarios = $this->usuarios();
        $docentes = $this->docentes($usuarios['Docente']);

        $this->estudiantes();
        $this->ambientes();
        $this->asignaturas($docentes);
        $this->bitacora($usuarios['Administrador']);

        $this->command->info('Datos de demostración cargados.');
    }

    /**
     * Una cuenta por rol. La contraseña de una cuenta que ya existe no se
     * toca: puede ser la que alguien viene usando.
     *
     * @return array<string, User>
     */
    private function usuarios(): array
    {
        $cuentas = [
            'Administrador' => ['Administrador del sistema', 'admin@umss.edu.bo', 'Admin12345'],
            'Docente' => ['Marcela Quiroga Vargas', 'docente@umss.edu.bo', 'Docente12345'],
            'Personal' => ['Personal de control', 'control@umss.edu.bo', 'Control12345'],
            'Responsable' => ['Responsable académico', 'responsable@umss.edu.bo', 'Responsable12345'],
        ];

        $creados = [];

        foreach ($cuentas as $rol => [$nombre, $correo, $clave]) {
            $role = Role::where('name', $rol)->first();
            $roleId = $role?->getKey();

            $creados[$rol] = User::firstOrCreate(
                ['correo' => $correo],
                [
                    'nombre' => $nombre,
                    'password' => Hash::make($clave),
                    'role_id' => is_int($roleId) ? $roleId : null,
                    'is_active' => true,
                    'failed_login_attempts' => 0,
                ],
            );

            // Una cuenta sin rol no puede abrir ninguna pantalla (HU-02).
            if ($creados[$rol]->role_id === null && is_int($roleId)) {
                $creados[$rol]->update(['role_id' => $roleId]);
            }
        }

        return $creados;
    }

    /**
     * @return array<string, Docente>
     */
    private function docentes(User $cuentaDocente): array
    {
        $docentes = [
            ['DOC-101', 'Marcela', 'Quiroga Vargas', 'm.quiroga@fcyt.umss.edu.bo', $cuentaDocente->getKey()],
            ['DOC-102', 'Iván', 'Camacho Rojas', 'i.camacho@fcyt.umss.edu.bo', null],
            ['DOC-103', 'Rosa', 'Peredo Antezana', 'r.peredo@fcyt.umss.edu.bo', null],
        ];

        $creados = [];

        foreach ($docentes as [$codigo, $nombres, $apellidos, $correo, $userId]) {
            $creados[$codigo] = Docente::firstOrCreate(
                ['codigo_docente' => $codigo],
                [
                    'user_id' => $userId,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'correo' => $correo,
                    'estado' => true,
                ],
            );
        }

        return $creados;
    }

    private function estudiantes(): void
    {
        // Los mismos de docs/ejemplos/padron_ejemplo.csv, para que la carga
        // masiva actualice en vez de duplicar cuando se pruebe el archivo.
        $estudiantes = [
            ['202104821', '7928194', 'Kevin René', 'Alvarado Claros', 'Ingeniería de Sistemas', true],
            ['202008472', '8839210', 'Valeria', 'Bustamante Torrico', 'Ingeniería Informática', true],
            ['201901349', '6492819', 'Diego Andrés', 'Camacho Zeballos', 'Ingeniería de Sistemas', true],
            ['202201994', '9348122', 'Mariana Lucía', 'Fernández Rojas', 'Ingeniería Electrónica', true],
            ['202105533', '7712045', 'Luis Alberto', 'Mamani Quispe', 'Ingeniería Industrial', true],
            ['202207781', '9120388', 'Andrea', 'Rojas Ledezma', 'Ingeniería Informática', true],
            ['202302256', '10045871', 'Daniela', 'Peredo Vargas', 'Ingeniería Civil', true],
            // Dado de baja: la pantalla tiene que mostrar los dos estados.
            ['201904412', '6633190', 'Jhonny', 'Choque Mamani', 'Ingeniería de Sistemas', false],
        ];

        foreach ($estudiantes as [$codigo, $ci, $nombre, $apellido, $carrera, $activo]) {
            Student::firstOrCreate(
                ['codigo_universitario' => $codigo],
                [
                    'ci' => $ci,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'carrera' => $carrera,
                    'activo' => $activo,
                ],
            );
        }
    }

    private function ambientes(): void
    {
        $ambientes = [
            ['Aula Magna FCyT', 'Edificio Central', 120, 'DISPONIBLE'],
            ['Aula 691B', 'Edificio Nuevo', 88, 'DISPONIBLE'],
            ['Auditorio Nuevo', 'Edificio Nuevo', 150, 'DISPONIBLE'],
            ['Laboratorio de Sistemas 1', 'Edificio de Laboratorios', 45, 'MANTENIMIENTO'],
        ];

        foreach ($ambientes as [$nombre, $ubicacion, $capacidad, $estado]) {
            Ambiente::firstOrCreate(
                ['nombre' => $nombre],
                [
                    'ubicacion' => $ubicacion,
                    'capacidad' => $capacidad,
                    'estado' => $estado,
                ],
            );
        }
    }

    /**
     * @param  array<string, Docente>  $docentes
     */
    private function asignaturas(array $docentes): void
    {
        $asignaturas = [
            ['INF-342', 'Redes de Computadoras', '6', 'DOC-101', 40],
            ['INF-271', 'Base de Datos I', '4', 'DOC-102', 45],
            ['MAT-207', 'Cálculo III', '3', 'DOC-103', 60],
        ];

        foreach ($asignaturas as [$codigo, $nombre, $semestre, $codigoDocente, $cupo]) {
            $asignatura = Asignatura::firstOrCreate(
                ['codigo' => $codigo],
                [
                    'carrera_id' => 1,
                    'nombre' => $nombre,
                    'semestre' => $semestre,
                    'descripcion' => null,
                    'estado' => true,
                ],
            );

            $docente = $docentes[$codigoDocente] ?? null;

            if (! $docente instanceof Docente) {
                continue;
            }

            $asignatura->grupos()->firstOrCreate(
                ['codigo_grupo' => 'A'],
                [
                    'docente_id' => $docente->getKey(),
                    'cupo' => $cupo,
                ],
            );
        }
    }

    /**
     * Unos asientos para que la bitácora no se vea vacía. Lo que el equipo
     * haga después se registra solo, sin pasar por aquí.
     */
    private function bitacora(User $administrador): void
    {
        if (DB::table('bitacora_operaciones')->count() > 0) {
            return;
        }

        $id = $administrador->getKey();
        $usuarioId = is_int($id) ? $id : null;

        DB::table('bitacora_operaciones')->insert([
            [
                'usuario_id' => $usuarioId,
                'operacion' => 'sesion.iniciar',
                'tabla_afectada' => 'usuarios',
                'registro_id' => $usuarioId,
                'descripcion' => null,
                'fecha_operacion' => now()->subDays(2),
            ],
            [
                'usuario_id' => $usuarioId,
                'operacion' => 'padron.importar',
                'tabla_afectada' => 'students',
                'registro_id' => null,
                'descripcion' => 'Carga masiva de padron_ejemplo.csv: 8 nuevos, 0 actualizados, 0 rechazados.',
                'fecha_operacion' => now()->subDays(2),
            ],
            [
                'usuario_id' => $usuarioId,
                'operacion' => 'ambiente.registrar',
                'tabla_afectada' => 'ambientes',
                'registro_id' => null,
                'descripcion' => null,
                'fecha_operacion' => now()->subDay(),
            ],
            [
                'usuario_id' => $usuarioId,
                'operacion' => 'asignatura.registrar',
                'tabla_afectada' => 'asignaturas',
                'registro_id' => null,
                'descripcion' => null,
                'fecha_operacion' => now()->subDay(),
            ],
            [
                'usuario_id' => $usuarioId,
                'operacion' => 'estudiante.baja',
                'tabla_afectada' => 'students',
                'registro_id' => null,
                'descripcion' => null,
                'fecha_operacion' => now(),
            ],
        ]);
    }
}
