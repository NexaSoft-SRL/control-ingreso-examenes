<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los roles pasan a ser creables: `es_sistema` distingue a los tres de inicio
 * (Administrador, Docente, Auxiliar). El catalogo de permisos se alinea con
 * las pantallas nuevas sin perder el reparto que ya estuviera editado: las
 * claves se renombran, no se quitan. Sobre una base vacia no toca datos (los
 * siembra `RolePermissionSeeder`).
 */
return new class extends Migration
{
    /**
     * Clave anterior => clave nueva.
     *
     * @var array<string, string>
     */
    private const RENOMBRES = [
        'asignaturas_ambientes' => 'periodo_oferta',
        'examenes_normas' => 'examenes',
        'monitoreo_tiempo_real' => 'seguimiento_vivo',
        'reportes_consolidados' => 'reportes_universidad',
        'reportes_asignatura' => 'reportes_examenes',
    ];

    /**
     * Permiso nuevo => permiso (ya renombrado) cuyos roles lo reciben.
     *
     * @var array<string, string>
     */
    private const DESDOBLES = [
        'aulas_docentes' => 'periodo_oferta',
        'mis_grupos' => 'examenes',
    ];

    /**
     * Clave => pantalla, en el orden de la matriz.
     *
     * @var array<string, string>
     */
    private const CATALOGO = [
        'periodo_oferta' => 'Período y oferta académica',
        'aulas_docentes' => 'Aulas y docentes',
        'padron_estudiantes' => 'Padrón e inscripciones',
        'mis_grupos' => 'Padrón e inscripciones (sus grupos)',
        'examenes' => 'Exámenes',
        'habilitacion' => 'Habilitación',
        'codigos_qr' => 'Códigos QR',
        'punto_control' => 'Punto de control',
        'seguimiento_vivo' => 'Seguimiento en vivo',
        'reportes_universidad' => 'Reportes de la universidad',
        'reportes_examenes' => 'Reportes de sus exámenes',
        'usuarios_roles' => 'Usuarios y roles',
        'bitacora' => 'Bitácora',
        'respaldo_restauracion' => 'Respaldo',
    ];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('es_sistema')
                ->default(false);
        });

        $this->quitarRepartosDuplicados();

        Schema::table('permission_role', function (Blueprint $table): void {
            $table->unique(
                ['role_id', 'permission_id'],
                'uq_permission_role'
            );
        });

        if (! DB::table('roles')->exists() && ! DB::table('permissions')->exists()) {
            return;
        }

        $this->alinearPermisos();
        $this->alinearRoles();
    }

    public function down(): void
    {
        // Los renombres de datos no se deshacen: solo la estructura.
        Schema::table('permission_role', function (Blueprint $table): void {
            $table->dropUnique('uq_permission_role');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('es_sistema');
        });
    }

    private function quitarRepartosDuplicados(): void
    {
        $conservar = DB::table('permission_role')
            ->selectRaw('MIN(id)')
            ->groupBy('role_id', 'permission_id');

        DB::table('permission_role')
            ->whereNotIn('id', $conservar)
            ->delete();
    }

    private function alinearPermisos(): void
    {
        $ahora = now();

        foreach (self::RENOMBRES as $anterior => $nueva) {
            $idAnterior = $this->idDe('permissions', $anterior);

            if ($idAnterior === null) {
                continue;
            }

            $idNueva = $this->idDe('permissions', $nueva);

            if ($idNueva === null) {
                DB::table('permissions')
                    ->where('id', $idAnterior)
                    ->update(['name' => $nueva, 'updated_at' => $ahora]);

                continue;
            }

            // Las dos claves conviven: el reparto de la anterior pasa a la nueva.
            $this->copiarReparto($idAnterior, $idNueva);

            DB::table('permissions')
                ->where('id', $idAnterior)
                ->delete();
        }

        foreach (self::CATALOGO as $clave => $pantalla) {
            $existente = $this->idDe('permissions', $clave);

            if ($existente !== null) {
                DB::table('permissions')
                    ->where('id', $existente)
                    ->update(['screen_name' => $pantalla, 'updated_at' => $ahora]);

                continue;
            }

            $nuevo = DB::table('permissions')->insertGetId([
                'name' => $clave,
                'screen_name' => $pantalla,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            // Un permiso que nace de desdoblar otro lo reciben los roles
            // que ya tenian el de origen.
            $origen = self::DESDOBLES[$clave] ?? null;
            $idOrigen = $origen === null ? null : $this->idDe('permissions', $origen);

            if ($idOrigen !== null) {
                $this->copiarReparto($idOrigen, $nuevo);
            }
        }
    }

    private function alinearRoles(): void
    {
        $ahora = now();

        $personal = $this->idDe('roles', 'Personal');
        $auxiliar = $this->idDe('roles', 'Auxiliar');

        if ($personal !== null && $auxiliar === null) {
            // Misma funcion con otro nombre: las cuentas conservan el rol.
            DB::table('roles')
                ->where('id', $personal)
                ->update(['name' => 'Auxiliar', 'updated_at' => $ahora]);
        } elseif ($personal !== null) {
            foreach (['usuarios', 'users'] as $tabla) {
                DB::table($tabla)
                    ->where('role_id', $personal)
                    ->update(['role_id' => $auxiliar]);
            }

            DB::table('roles')
                ->where('id', $personal)
                ->delete();
        }

        // «Responsable» no es un rol de inicio: sigue como rol creado si
        // alguna cuenta lo usa y se retira si no.
        $responsable = $this->idDe('roles', 'Responsable');

        if (
            $responsable !== null
            && ! DB::table('usuarios')->where('role_id', $responsable)->exists()
        ) {
            DB::table('roles')
                ->where('id', $responsable)
                ->delete();
        }

        DB::table('roles')
            ->whereIn('name', ['Administrador', 'Docente', 'Auxiliar'])
            ->update(['es_sistema' => true, 'updated_at' => $ahora]);
    }

    /**
     * Da a los roles que tienen el permiso `$desde` tambien el permiso `$hacia`.
     */
    private function copiarReparto(int $desde, int $hacia): void
    {
        $roles = DB::table('permission_role')
            ->where('permission_id', $desde)
            ->pluck('role_id');

        foreach ($roles as $rol) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $rol,
                'permission_id' => $hacia,
            ]);
        }
    }

    private function idDe(string $tabla, string $nombre): ?int
    {
        $id = DB::table($tabla)
            ->where('name', $nombre)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
};
