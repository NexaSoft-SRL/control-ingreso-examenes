<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Actions\ProponerUsuario;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Decision D-02, revisada: `nombreapellido` sin punto, a partir de un nombre escrito como en
 * la oferta («Paterno Materno Nombres»).
 */
final class ProponerUsuarioTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('nombres')]
    public function test_it_proposes_first_name_and_paternal_surname_without_a_dot(
        string $nombre,
        string $esperado,
    ): void {
        $this->assertSame(
            $esperado,
            $this->app->make(ProponerUsuario::class)->execute($nombre)
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function nombres(): array
    {
        return [
            'paterno materno nombre' => ['Blanco Coca Leticia', 'leticiablanco'],
            'dos nombres de pila' => ['Flores Villarroel Corina Justina', 'corinaflores'],
            'solo paterno y nombre' => ['Mamani Diego', 'diegomamani'],
            'tildes y enie' => ['Montaño Peñaranda Víctor Hugo', 'victormontano'],
            'mayusculas y espacios de mas' => ['  MOLINA   ZURITA  JUAN  ', 'juanmolina'],
            'signos' => ["O'Connor Pérez María-José", 'mariajoseoconnor'],
            'una sola palabra' => ['Administración', 'administracion'],
            'vacio' => ['   ', 'usuario'],
        ];
    }

    public function test_a_taken_user_name_gets_a_numeric_suffix(): void
    {
        UserFactory::new()->createOne(['usuario' => 'leticiablanco']);

        $accion = $this->app->make(ProponerUsuario::class);

        $this->assertSame('leticiablanco2', $accion->execute('Blanco Coca Leticia'));

        UserFactory::new()->createOne(['usuario' => 'leticiablanco2']);

        $this->assertSame('leticiablanco3', $accion->execute('Blanco Coca Leticia'));
    }

    public function test_the_proposal_always_fits_the_column_and_the_format(): void
    {
        $largo = str_repeat('Largo', 20);

        UserFactory::new()->createOne([
            'usuario' => substr(mb_strtolower($largo.$largo), 0, 60),
        ]);

        $propuesta = $this->app->make(ProponerUsuario::class)
            ->execute("{$largo} Materno {$largo}");

        $this->assertSame(60, strlen($propuesta));
        $this->assertStringEndsWith('2', $propuesta);
        $this->assertMatchesRegularExpression('/^[a-z0-9._-]+$/', $propuesta);
    }

    public function test_other_modules_reach_the_proposal_through_its_contract(): void
    {
        $this->assertSame(
            'diegomamani',
            $this->app->make(ProponedorUsuario::class)->proponer('Mamani Torrez Diego')
        );
    }
}
