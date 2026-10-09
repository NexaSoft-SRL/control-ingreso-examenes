<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\TestCase;

final class AlcanceDocenteGatewayTest extends TestCase
{
    use DatosAcademicos;
    use RefreshDatabase;

    public function test_account_resolves_to_its_teacher(): void
    {
        $docente = $this->docenteConCuenta();
        $this->docenteSinCuenta();
        $sinDocente = UserFactory::new()->createOne();

        $alcance = $this->app->make(AlcanceDocenteGateway::class);

        $this->assertSame($docente->id, $alcance->docenteDeUsuario((int) $docente->user_id));
        $this->assertNull($alcance->docenteDeUsuario($sinDocente->id));
    }

    public function test_group_belongs_only_to_its_teacher(): void
    {
        $docente = $this->docenteConCuenta();
        $otro = $this->docenteConCuenta();

        $propio = $this->grupo($docente);
        $ajeno = $this->grupo($otro);
        $porDesignar = $this->grupo();

        $alcance = $this->app->make(AlcanceDocenteGateway::class);
        $usuarioId = (int) $docente->user_id;

        $this->assertTrue($alcance->esGrupoDelDocente($usuarioId, $propio->id));
        $this->assertFalse($alcance->esGrupoDelDocente($usuarioId, $ajeno->id));
        $this->assertFalse($alcance->esGrupoDelDocente($usuarioId, $porDesignar->id));
        $this->assertFalse($alcance->esGrupoDelDocente((int) $otro->user_id, $propio->id));
    }
}
