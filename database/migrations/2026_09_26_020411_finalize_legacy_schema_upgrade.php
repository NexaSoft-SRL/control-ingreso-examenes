<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuarios')) {
            throw new RuntimeException(
                'No existe la tabla canonical usuarios.'
            );
        }

        if (! Schema::hasColumn('usuarios', 'role_id')) {
            throw new RuntimeException(
                'usuarios.role_id debe existir antes de finalizar '
                .'la transición legacy.'
            );
        }

        $this->copyLegacyRoles();
        $this->repairAsignaturasSchema();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->repairPostgreSqlSessions();
            $this->normalizeLegacyUsersCompatibilityTable();
            $this->repointUserForeignKeys();
        }
    }

    private function copyLegacyRoles(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'role_id')
        ) {
            return;
        }

        foreach (
            DB::table('users')
                ->whereNotNull('role_id')
                ->orderBy('id')
                ->get(['id', 'role_id']) as $legacyRole
        ) {
            /** @var array<string, mixed> $legacy */
            $legacy = (array) $legacyRole;

            $legacyId = $legacy['id'] ?? null;
            $roleId = $legacy['role_id'] ?? null;

            if (! is_int($legacyId) && ! is_string($legacyId)) {
                throw new RuntimeException(
                    'El usuario legacy contiene un id invalido.'
                );
            }

            if (! is_int($roleId) && ! is_string($roleId)) {
                throw new RuntimeException(
                    'El usuario legacy contiene un role_id invalido.'
                );
            }

            $updated = DB::table('usuarios')
                ->where('id', $legacyId)
                ->update([
                    'role_id' => $roleId,
                ]);

            if ($updated === 0) {
                $exists = DB::table('usuarios')
                    ->where('id', $legacyId)
                    ->exists();

                if (! $exists) {
                    throw new RuntimeException(
                        sprintf(
                            'No existe usuarios.id=%s requerido '
                            .'para conservar role_id.',
                            (string) $legacyId
                        )
                    );
                }
            }
        }
    }

    private function repairAsignaturasSchema(): void
    {
        if (
            Schema::hasTable('asignaturas')
            && ! Schema::hasColumn('asignaturas', 'carrera_id')
        ) {
            Schema::table(
                'asignaturas',
                function (Blueprint $table): void {
                    $table->unsignedBigInteger('carrera_id')
                        ->nullable();
                }
            );
        }
    }

    private function repairPostgreSqlSessions(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::statement(
            'ALTER TABLE sessions
             ALTER COLUMN user_id
             TYPE varchar(255)
             USING user_id::varchar'
        );
    }

    private function normalizeLegacyUsersCompatibilityTable(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::statement(
            'ALTER TABLE users
             ALTER COLUMN name DROP NOT NULL'
        );

        DB::statement(
            'ALTER TABLE users
             ALTER COLUMN email DROP NOT NULL'
        );

        DB::statement(
            'ALTER TABLE users
             DROP CONSTRAINT IF EXISTS
             users_failed_login_attempts_non_negative'
        );
    }

    private function repointUserForeignKeys(): void
    {
        if (Schema::hasTable('docentes')) {
            DB::statement(
                'ALTER TABLE docentes
                 DROP CONSTRAINT IF EXISTS
                 docentes_user_id_foreign'
            );

            DB::statement(
                'ALTER TABLE docentes
                 ADD CONSTRAINT docentes_user_id_foreign
                 FOREIGN KEY (user_id)
                 REFERENCES usuarios(id)
                 ON UPDATE CASCADE
                 ON DELETE SET NULL'
            );
        }

        if (Schema::hasTable('login_attempts')) {
            DB::statement(
                'ALTER TABLE login_attempts
                 DROP CONSTRAINT IF EXISTS
                 login_attempts_user_id_foreign'
            );

            DB::statement(
                'ALTER TABLE login_attempts
                 ADD CONSTRAINT login_attempts_user_id_foreign
                 FOREIGN KEY (user_id)
                 REFERENCES usuarios(id)
                 ON DELETE SET NULL'
            );
        }

        if (Schema::hasTable('bitacora_operaciones')) {
            DB::statement(
                'ALTER TABLE bitacora_operaciones
                 DROP CONSTRAINT IF EXISTS
                 bitacora_operaciones_usuario_id_foreign'
            );

            DB::statement(
                'ALTER TABLE bitacora_operaciones
                 ADD CONSTRAINT bitacora_operaciones_usuario_id_foreign
                 FOREIGN KEY (usuario_id)
                 REFERENCES usuarios(id)
                 ON UPDATE CASCADE
                 ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'La normalización del esquema legacy es forward-only '
            .'y no admite rollback automático.'
        );
    }
};
