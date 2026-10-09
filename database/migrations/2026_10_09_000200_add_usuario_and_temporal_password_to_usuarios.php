<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada cuenta tiene un nombre de usuario unico, ademas del correo con el que
 * entra. `password_changed_at` nulo marca una contrasena temporal y
 * `password_temporal_expira_en` guarda hasta cuando sirve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->string('usuario', 60)
                ->nullable();

            $table->timestamp('password_changed_at')
                ->nullable();

            $table->timestamp('password_temporal_expira_en')
                ->nullable();
        });

        $this->rellenarCuentasExistentes();

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->string('usuario', 60)
                ->nullable(false)
                ->change();

            $table->unique('usuario', 'uq_usuarios_usuario');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropUnique('uq_usuarios_usuario');

            $table->dropColumn([
                'usuario',
                'password_changed_at',
                'password_temporal_expira_en',
            ]);
        });
    }

    /**
     * Las cuentas que ya existen reciben como usuario la parte local de su
     * correo (en minusculas y saneada; con sufijo 2, 3... si se repite) y se
     * dan por titulares de su contrasena: no son temporales.
     */
    private function rellenarCuentasExistentes(): void
    {
        $ahora = now();

        /** @var array<string, true> $usados */
        $usados = [];

        $cuentas = DB::table('usuarios')
            ->orderBy('id')
            ->get(['id', 'correo']);

        foreach ($cuentas as $cuenta) {
            $id = is_numeric($cuenta->id) ? (int) $cuenta->id : 0;
            $correo = is_string($cuenta->correo) ? $cuenta->correo : '';

            $local = mb_strtolower(explode('@', $correo, 2)[0]);
            $base = substr(
                (string) preg_replace('/[^a-z0-9._-]+/', '', $local),
                0,
                50
            );

            if ($base === '') {
                $base = 'usuario'.$id;
            }

            $usuario = $base;

            for ($sufijo = 2; isset($usados[$usuario]); $sufijo++) {
                $usuario = $base.$sufijo;
            }

            $usados[$usuario] = true;

            DB::table('usuarios')
                ->where('id', $id)
                ->update([
                    'usuario' => $usuario,
                    'password_changed_at' => $ahora,
                ]);
        }
    }
};
