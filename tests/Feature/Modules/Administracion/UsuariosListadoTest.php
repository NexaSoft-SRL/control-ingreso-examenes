<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-15, criterios 1 a 4: el listado de cuentas, paginado en el servidor, con busqueda y
 * las cifras de sus filtros.
 */
final class UsuariosListadoTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrador = $this->usuarioConRol('Administrador', [
            'nombre' => 'Rojas Vargas Ana',
            'usuario' => 'ana.rojas',
            'correo' => 'ana.rojas@umss.edu.bo',
            'created_at' => '2026-09-01 10:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function cuenta(string $rol, array $atributos = [], bool $activa = true): User
    {
        $cuenta = $this->usuarioConRol($rol, $atributos);

        $cuenta->forceFill(['is_active' => $activa])->save();

        return $cuenta;
    }

    public function test_the_accounts_are_listed_newest_first_with_their_role_and_state(): void
    {
        $this->cuenta('Docente', [
            'nombre' => 'Blanco Coca Leticia',
            'usuario' => 'leticia.blanco',
            'correo' => null,
            'created_at' => '2026-09-02 10:00:00',
        ]);

        $auxiliar = $this->cuenta('Auxiliar', [
            'nombre' => 'Mamani Torrez Diego',
            'usuario' => 'diego.mamani',
            'correo' => null,
            'created_at' => '2026-09-03 10:00:00',
        ], activa: false);

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $auxiliar->getKey(),
                        'nombre' => 'Mamani Torrez Diego',
                        'usuario' => 'diego.mamani',
                        'correo' => null,
                        'rol' => 'Auxiliar',
                        'estado' => 'bloqueado',
                    ],
                    [
                        'id' => User::where('usuario', 'leticia.blanco')->value('id'),
                        'nombre' => 'Blanco Coca Leticia',
                        'usuario' => 'leticia.blanco',
                        'correo' => null,
                        'rol' => 'Docente',
                        'estado' => 'activo',
                    ],
                    [
                        'id' => $this->administrador->getKey(),
                        'nombre' => 'Rojas Vargas Ana',
                        'usuario' => 'ana.rojas',
                        'correo' => 'ana.rojas@umss.edu.bo',
                        'rol' => 'Administrador',
                        'estado' => 'activo',
                    ],
                ],
                'meta' => [
                    'total' => 3,
                    'pagina' => 1,
                    'por_pagina' => 20,
                    'cuentas' => 3,
                    'conteos' => [
                        'Administrador' => 1,
                        'Docente' => 1,
                        'Auxiliar' => 1,
                        'bloqueadas' => 1,
                    ],
                ],
            ]);
    }

    public function test_the_search_ignores_case_and_accents_in_the_name(): void
    {
        $this->cuenta('Docente', ['nombre' => 'Núñez Peña Óscar', 'usuario' => 'oscar.nunez']);
        $this->cuenta('Docente', ['nombre' => 'Flores Villarroel Corina', 'usuario' => 'corina.flores']);

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?buscar='.urlencode('NUNEZ pena'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'oscar.nunez')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_the_search_also_looks_at_the_username_and_the_email(): void
    {
        $this->cuenta('Docente', ['usuario' => 'corina.flores', 'correo' => 'cflores@umss.edu.bo']);
        $this->cuenta('Auxiliar', ['usuario' => 'diego.mamani', 'correo' => null]);

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?buscar=diego.m')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'diego.mamani');

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?buscar=CFLORES@')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'corina.flores');
    }

    public function test_the_wildcards_of_the_search_are_taken_literally(): void
    {
        $this->cuenta('Docente', ['usuario' => 'corina.flores']);

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?buscar='.urlencode('%'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_list_is_filtered_by_role(): void
    {
        $this->cuenta('Docente', ['usuario' => 'corina.flores']);
        $this->cuenta('Auxiliar', ['usuario' => 'diego.mamani']);

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?rol=Auxiliar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'diego.mamani')
            ->assertJsonPath('meta.total', 1)
            // Las cifras de los filtros no cambian al filtrar.
            ->assertJsonPath('meta.conteos.Docente', 1)
            ->assertJsonPath('meta.conteos.Administrador', 1);
    }

    public function test_a_created_role_has_its_own_count_and_filter(): void
    {
        $coordinador = $this->usuarioConPermisos(['periodo_oferta']);
        $this->cuenta('Docente', ['usuario' => 'corina.flores']);

        $respuesta = $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?rol='.urlencode('Rol de prueba'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $coordinador->getKey())
            ->assertJsonPath('data.0.rol', 'Rol de prueba')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.cuentas', 3);

        // Una clave por rol existente, los de inicio primero, y `bloqueadas`.
        $this->assertSame(
            [
                'Administrador' => 1,
                'Docente' => 1,
                'Auxiliar' => 0,
                'Rol de prueba' => 1,
                'bloqueadas' => 0,
            ],
            $respuesta->json('meta.conteos'),
        );
    }

    public function test_the_filters_combine(): void
    {
        $this->cuenta('Docente', ['nombre' => 'Flores Villarroel Corina', 'usuario' => 'corina.flores']);
        $this->cuenta('Docente', ['nombre' => 'Flores Rojas Marta', 'usuario' => 'marta.flores'], activa: false);
        $this->cuenta('Auxiliar', ['nombre' => 'Flores Vega Luis', 'usuario' => 'luis.flores'], activa: false);
        $this->cuenta('Docente', ['nombre' => 'Montaño Víctor', 'usuario' => 'victor.montano'], activa: false);

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?buscar=flores&rol=Docente&bloqueadas=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'marta.flores');

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?buscar=nadie')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.cuentas', 5);
    }

    public function test_the_list_is_filtered_by_blocked_accounts(): void
    {
        $this->cuenta('Docente', ['usuario' => 'corina.flores']);
        $this->cuenta('Docente', ['usuario' => 'victor.montano'], activa: false);

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?bloqueadas=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'victor.montano')
            ->assertJsonPath('data.0.estado', 'bloqueado')
            ->assertJsonPath('meta.conteos.bloqueadas', 1);
    }

    public function test_the_list_is_paginated_on_the_server(): void
    {
        foreach (range(1, 5) as $numero) {
            $this->cuenta('Docente', [
                'usuario' => 'docente.'.$numero,
                'created_at' => sprintf('2026-09-1%d 10:00:00', $numero),
            ]);
        }

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?pagina=2&por_pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.usuario', 'docente.3')
            ->assertJsonPath('data.1.usuario', 'docente.2')
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 2);
    }

    public function test_the_filters_are_validated(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?rol=Responsable&por_pagina=101&pagina=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rol', 'por_pagina', 'pagina'])
            ->assertJsonPath('errors.rol.0', 'El rol no existe.');
    }

    public function test_a_guest_cannot_list_the_accounts(): void
    {
        $this->getJson('/api/usuarios')->assertUnauthorized();
    }

    public function test_the_permission_is_required(): void
    {
        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson('/api/usuarios')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'usuarios_roles');
    }
}
