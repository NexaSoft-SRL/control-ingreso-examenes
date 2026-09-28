<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bitacora_operaciones') || ! Schema::hasTable('usuarios')) {
            return;
        }

        DB::statement(
            'ALTER TABLE bitacora_operaciones '
            .'DROP CONSTRAINT IF EXISTS bitacora_operaciones_usuario_id_foreign'
        );

        DB::table('bitacora_operaciones')
            ->whereNotNull('usuario_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw('1'))
                    ->from('usuarios')
                    ->whereColumn('usuarios.id', 'bitacora_operaciones.usuario_id');
            })
            ->update(['usuario_id' => null]);

        DB::statement(
            'ALTER TABLE bitacora_operaciones '
            .'ADD CONSTRAINT bitacora_operaciones_usuario_id_foreign '
            .'FOREIGN KEY (usuario_id) REFERENCES usuarios (id) '
            .'ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('bitacora_operaciones') || ! Schema::hasTable('users')) {
            return;
        }

        DB::statement(
            'ALTER TABLE bitacora_operaciones '
            .'DROP CONSTRAINT IF EXISTS bitacora_operaciones_usuario_id_foreign'
        );

        DB::table('bitacora_operaciones')
            ->whereNotNull('usuario_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw('1'))
                    ->from('users')
                    ->whereColumn('users.id', 'bitacora_operaciones.usuario_id');
            })
            ->update(['usuario_id' => null]);

        DB::statement(
            'ALTER TABLE bitacora_operaciones '
            .'ADD CONSTRAINT bitacora_operaciones_usuario_id_foreign '
            .'FOREIGN KEY (usuario_id) REFERENCES users (id) '
            .'ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }
};
