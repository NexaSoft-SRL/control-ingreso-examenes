<?php

declare(strict_types=1);

use App\Modules\Administracion\Application\Actions\ProponerUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El usuario pasa a llevar nombre y apellido separados por un espacio
 * («josesoruco» → «jose soruco»). Se cambian solo las cuentas cuyo usuario
 * es justo el nombre y el apellido de su titular, juntos (con su numero, si
 * lo tenia): las demas («admin», las escritas a mano) quedan como estan.
 * Las cuentas se comparan sin espacios, asi que un indice unico sobre esa
 * forma impide dos usuarios que difieran solo en el espacio.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cuentas = DB::table('users')
            ->where('usuario', 'not like', '% %')
            ->get(['id', 'name', 'usuario']);

        foreach ($cuentas as $cuenta) {
            $usuario = is_string($cuenta->usuario) ? $cuenta->usuario : '';
            $nombre = is_string($cuenta->name) ? $cuenta->name : '';
            $separado = ProponerUsuario::formar($nombre);

            if (! str_contains($separado, ' ')) {
                continue;
            }

            // «josesoruco2»: la base y su numero.
            preg_match('/^(.*?)(\d*)$/', $usuario, $partes);
            $base = $partes[1] ?? $usuario;
            $numero = $partes[2] ?? '';

            if (str_replace(' ', '', $separado) === $base) {
                DB::table('users')->where('id', $cuenta->id)->update(['usuario' => $separado.$numero]);
            }
        }

        DB::statement("create unique index uq_users_usuario_compacto on users ((replace(usuario, ' ', '')))");
    }

    public function down(): void
    {
        DB::statement('drop index if exists uq_users_usuario_compacto');
        DB::statement("update users set usuario = replace(usuario, ' ', '') where usuario like '% %'");
    }
};
