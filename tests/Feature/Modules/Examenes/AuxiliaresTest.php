<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use App\Modules\Examenes\Domain\Models\Auxiliar;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Paso 4 del asistente (rutas 50 y 51): los auxiliares del docente, la
 * busqueda entre todas las cuentas de auxiliar y el alta de uno nuevo.
 */
final class AuxiliaresTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function cuentaAuxiliar(array $atributos, ?User $registradoPor = null, ?string $codigo = null): User
    {
        $cuenta = $this->usuarioConRol('Auxiliar', $atributos);

        Auxiliar::create([
            'user_id' => $cuenta->id,
            'codigo_universitario' => $codigo,
            'registrado_por' => $registradoPor?->id,
        ]);

        return $cuenta;
    }

    public function test_without_search_the_teacher_gets_the_assistants_registered_or_already_used(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $otro = $this->cuenta($this->docente());

        $registrado = $this->cuentaAuxiliar(['name' => 'Vargas Arce Sergio', 'usuario' => 'sergio vargas'], $cuenta);
        $usadoACargo = $this->cuentaAuxiliar(['name' => 'Rojas Ledezma Andrea', 'usuario' => 'andrea.rojas'], $otro);
        $usadoDeApoyo = $this->cuentaAuxiliar(['name' => 'Quispe Mamani Luis', 'usuario' => 'luis.quispe'], $otro);
        $this->cuentaAuxiliar(['name' => 'Ajeno Sin Usar Pedro', 'usuario' => 'pedro.ajeno'], $otro);

        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $this->ponerACargo($examen, $aula, $usadoACargo);
        $this->ponerDeApoyo($examen, $aula, $usadoDeApoyo);

        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $usadoDeApoyo->id, 'nombre' => 'Quispe Mamani Luis', 'usuario' => 'luis.quispe', 'mio' => true],
                ['id' => $usadoACargo->id, 'nombre' => 'Rojas Ledezma Andrea', 'usuario' => 'andrea.rojas', 'mio' => true],
                ['id' => $registrado->id, 'nombre' => 'Vargas Arce Sergio', 'usuario' => 'sergio vargas', 'mio' => true],
            ]]);
    }

    public function test_searching_reaches_any_active_assistant_account_with_own_ones_first(): void
    {
        $cuenta = $this->cuenta($this->docente());
        $otro = $this->cuenta($this->docente());

        $ajeno = $this->cuentaAuxiliar(['name' => 'Peña Álvarez María', 'usuario' => 'maria.pena'], $otro);
        $mio = $this->cuentaAuxiliar(['name' => 'Peñaranda Soto Iván', 'usuario' => 'ivan.penaranda'], $cuenta);
        // Cuenta de rol Auxiliar creada por el administrador, sin fila propia.
        $sinFila = $this->usuarioConRol('Auxiliar', ['name' => 'Penaloza Ríos Eva', 'usuario' => 'eva.penaloza']);
        $this->cuentaAuxiliar(['name' => 'Peña Bloqueada Ana', 'usuario' => 'ana.pena', 'is_active' => false], $otro);
        // Un docente con el mismo apellido no es auxiliar.
        $this->docente('Peña Docente Raúl');
        UserFactory::new()->createOne(['name' => 'Peña Sin Rol']);

        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=PENA')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$mio->id, $sinFila->id, $ajeno->id])
            ->assertJsonPath('data.*.mio', [true, false, false]);

        // Tambien por usuario y por codigo universitario.
        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=maria.p')
            ->assertJsonPath('data.*.id', [$ajeno->id]);

        $conCodigo = $this->cuentaAuxiliar(['name' => 'Flores Corina'], $otro, '202000123');

        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=2020001')
            ->assertJsonPath('data.*.id', [$conCodigo->id]);
    }

    public function test_a_search_shorter_than_three_letters_only_shows_own_assistants(): void
    {
        $cuenta = $this->cuenta($this->docente());
        $otro = $this->cuenta($this->docente());
        $mio = $this->cuentaAuxiliar(['name' => 'Vargas Arce Sergio'], $cuenta);
        $this->cuentaAuxiliar(['name' => 'Vaca Díez Julia'], $otro);

        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=va')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$mio->id]);
    }

    public function test_a_search_returns_twenty_accounts_at_most(): void
    {
        $cuenta = $this->cuenta($this->docente());

        for ($i = 1; $i <= 23; $i++) {
            $this->usuarioConRol('Auxiliar', ['name' => sprintf('Mamani Auxiliar %02d', $i)]);
        }

        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=mamani')
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }

    public function test_the_search_is_validated(): void
    {
        $this->actingAs($this->cuenta($this->docente()))
            ->getJson('/api/auxiliares?buscar='.str_repeat('a', 61))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buscar' => 'La búsqueda admite hasta 60 caracteres.']);
    }

    public function test_a_new_assistant_gets_an_account_with_the_student_mail_and_a_temporary_password(): void
    {
        Mail::fake();
        (new RolePermissionSeeder)->run();
        $cuenta = $this->cuenta($this->docente());

        $respuesta = $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', [
                'nombre' => '  Vargas  Arce Sergio ',
                'codigo_universitario' => '202000123',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Auxiliar agregado.')
            ->assertJsonPath('data.nombre', 'Vargas Arce Sergio')
            ->assertJsonPath('data.usuario', 'sergio vargas')
            ->assertJsonPath('data.mio', true);

        $nueva = User::where('usuario', 'sergio vargas')->firstOrFail();

        $respuesta->assertJsonPath('data.id', $nueva->id);

        $this->assertSame('202000123@est.umss.edu', $nueva->email);
        $this->assertSame('Auxiliar', $nueva->role?->name);
        // Contrasena temporal: tendra que definir la suya al entrar.
        $this->assertNull($nueva->password_changed_at);
        $this->assertNotNull($nueva->getAttribute('password_temporal_expira_en'));

        $this->assertDatabaseHas('auxiliares', [
            'user_id' => $nueva->id,
            'codigo_universitario' => '202000123',
            'registrado_por' => $cuenta->id,
        ]);

        Mail::assertSent(
            CredencialesInicialesMail::class,
            static fn (CredencialesInicialesMail $correo): bool => $correo->hasTo('202000123@est.umss.edu'),
        );

        // Ya sale entre los suyos y puede atender un aula.
        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares')
            ->assertJsonPath('data.*.id', [$nueva->id]);
    }

    public function test_the_proposed_username_gets_a_suffix_when_taken(): void
    {
        Mail::fake();
        (new RolePermissionSeeder)->run();
        UserFactory::new()->createOne(['usuario' => 'sergio vargas']);

        $this->actingAs($this->cuenta($this->docente()))
            ->postJson('/api/auxiliares', ['nombre' => 'Vargas Arce Sergio', 'codigo_universitario' => '202000123'])
            ->assertCreated()
            ->assertJsonPath('data.usuario', 'sergio vargas2');
    }

    public function test_an_assistant_with_that_code_is_offered_instead_of_creating_another(): void
    {
        Mail::fake();
        $cuenta = $this->cuenta($this->docente());
        $otro = $this->cuenta($this->docente());
        $existente = $this->cuentaAuxiliar(['name' => 'Vargas Arce Sergio', 'usuario' => 'sergio vargas'], $otro, '202000123');
        $cuentas = User::count();

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', ['nombre' => 'Sergio Vargas', 'codigo_universitario' => '202000123'])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Auxiliar ya registrado.',
                'data' => ['id' => $existente->id, 'nombre' => 'Vargas Arce Sergio', 'usuario' => 'sergio vargas', 'mio' => false],
            ]);

        $this->assertSame($cuentas, User::count());
        $this->assertDatabaseCount('auxiliares', 1);
        Mail::assertNothingSent();
    }

    public function test_a_code_whose_mail_already_belongs_to_another_account_is_rejected(): void
    {
        Mail::fake();
        (new RolePermissionSeeder)->run();
        UserFactory::new()->createOne(['email' => '202000123@est.umss.edu']);

        $this->actingAs($this->cuenta($this->docente()))
            ->postJson('/api/auxiliares', ['nombre' => 'Vargas Arce Sergio', 'codigo_universitario' => '202000123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_universitario' => 'Ya existe']);

        $this->assertDatabaseCount('auxiliares', 0);
    }

    public function test_the_new_assistant_form_is_validated(): void
    {
        $cuenta = $this->cuenta($this->docente());

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', ['nombre' => 'Al', 'codigo_universitario' => '1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nombre' => 'Entre 3 y 120 caracteres',
                'codigo_universitario' => 'Entre 5 y 10 dígitos',
            ]);

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', ['nombre' => str_repeat('a', 121), 'codigo_universitario' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre' => 'Entre 3 y 120 caracteres']);

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', ['nombre' => '123 <b>', 'codigo_universitario' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre' => 'Solo letras']);

        foreach (['12345678901', '2020-0012', 'abcdef'] as $codigo) {
            $this->actingAs($cuenta)
                ->postJson('/api/auxiliares', ['nombre' => 'Vargas Arce Sergio', 'codigo_universitario' => $codigo])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('codigo_universitario');
        }

        $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre' => 'Obligatorio', 'codigo_universitario' => 'Obligatorio']);

        $this->assertDatabaseCount('auxiliares', 0);
    }

    public function test_registering_an_assistant_is_recorded_in_the_audit_log(): void
    {
        Mail::fake();
        (new RolePermissionSeeder)->run();
        $cuenta = $this->cuenta($this->docente());

        $id = $this->actingAs($cuenta)
            ->postJson('/api/auxiliares', ['nombre' => 'Vargas Arce Sergio', 'codigo_universitario' => '202000123'])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'auxiliar.registrar',
            'tabla_afectada' => 'auxiliares',
            'registro_id' => Auxiliar::where('user_id', $id)->value('id'),
            'usuario_id' => $cuenta->id,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.crear',
            'registro_id' => $id,
            'usuario_id' => $cuenta->id,
        ]);
    }

    public function test_an_assistant_used_only_in_exams_already_taken_is_no_longer_listed(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $otro = $this->cuenta($this->docente());
        $ajeno = $this->cuentaAuxiliar(['name' => 'Rojas Ledezma Andrea'], $otro);
        $propio = $this->cuentaAuxiliar(['name' => 'Vargas Arce Sergio'], $cuenta);
        $aula = $this->aula();
        $rendido = $this->examen($docente, [$this->grupo($docente)], [$aula], [
            'fecha' => now()->subDays(2)->toDateString(),
        ]);
        $this->ponerACargo($rendido, $aula, $ajeno);
        $this->ponerDeApoyo($rendido, $aula, $propio);

        // El que registro sigue siendo suyo; el prestado se vuelve a buscar.
        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$propio->id]);

        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=rojas')
            ->assertJsonPath('data.*.id', [$ajeno->id])
            ->assertJsonPath('data.0.mio', false);
    }

    public function test_the_teacher_removes_an_assistant_from_the_list_without_deleting_the_account(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $auxiliar = $this->cuentaAuxiliar(['name' => 'Vargas Arce Sergio'], $cuenta, '202000123');
        $queda = $this->cuentaAuxiliar(['name' => 'Rojas Ledezma Andrea'], $cuenta);
        // Atendio un examen que ya se rindio: eso no impide quitarlo.
        $aula = $this->aula();
        $rendido = $this->examen($docente, [$this->grupo($docente)], [$aula], [
            'fecha' => now()->subDays(2)->toDateString(),
        ]);
        $this->ponerACargo($rendido, $aula, $auxiliar);
        $filaId = Auxiliar::where('user_id', $auxiliar->id)->value('id');

        $this->actingAs($cuenta)
            ->deleteJson("/api/auxiliares/{$auxiliar->id}")
            ->assertNoContent();

        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares')
            ->assertJsonPath('data.*.id', [$queda->id]);

        // La cuenta, su fila y lo que atendio siguen ahi.
        $this->assertDatabaseHas('users', ['id' => $auxiliar->id, 'is_active' => true]);
        $this->assertDatabaseHas('auxiliares', [
            'id' => $filaId,
            'user_id' => $auxiliar->id,
            'codigo_universitario' => '202000123',
            'registrado_por' => null,
        ]);
        $this->assertDatabaseHas('examen_aula', ['examen_id' => $rendido->id, 'encargado_id' => $auxiliar->id]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'auxiliar.quitar',
            'tabla_afectada' => 'auxiliares',
            'registro_id' => $filaId,
            'usuario_id' => $cuenta->id,
        ]);

        // Se lo sigue encontrando al buscar, ya como ajeno.
        $this->actingAs($cuenta)
            ->getJson('/api/auxiliares?buscar=vargas')
            ->assertJsonPath('data.*.id', [$auxiliar->id])
            ->assertJsonPath('data.0.mio', false);

        // Ya no esta en la lista: quitarlo otra vez no lo encuentra.
        $this->actingAs($cuenta)
            ->deleteJson("/api/auxiliares/{$auxiliar->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Auxiliar no encontrado.']);
    }

    public function test_an_assistant_attending_a_pending_exam_is_not_removed(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $aCargo = $this->cuentaAuxiliar(['name' => 'Vargas Arce Sergio'], $cuenta);
        $deApoyo = $this->cuentaAuxiliar(['name' => 'Rojas Ledezma Andrea'], $cuenta);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula], [
            'fecha' => now()->addDays(3)->toDateString(),
        ]);
        $this->ponerACargo($examen, $aula, $aCargo);
        $this->ponerDeApoyo($examen, $aula, $deApoyo);

        foreach ([$aCargo, $deApoyo] as $auxiliar) {
            $this->actingAs($cuenta)
                ->deleteJson("/api/auxiliares/{$auxiliar->id}")
                ->assertConflict()
                ->assertExactJson(['message' => 'Atiende un examen pendiente.', 'codigo' => 'AUXILIAR_ASIGNADO']);
        }

        $this->assertDatabaseHas('auxiliares', ['user_id' => $aCargo->id, 'registrado_por' => $cuenta->id]);
        $this->assertDatabaseHas('auxiliares', ['user_id' => $deApoyo->id, 'registrado_por' => $cuenta->id]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'auxiliar.quitar']);
    }

    public function test_only_who_registered_the_assistant_removes_it_from_the_list(): void
    {
        $cuenta = $this->cuenta($this->docente());
        $otro = $this->cuenta($this->docente());
        $ajeno = $this->cuentaAuxiliar(['name' => 'Rojas Ledezma Andrea'], $otro);
        $sinFila = $this->usuarioConRol('Auxiliar');

        foreach ([$ajeno->id, $sinFila->id, $otro->id, 999999] as $id) {
            $this->actingAs($cuenta)
                ->deleteJson("/api/auxiliares/{$id}")
                ->assertNotFound()
                ->assertJsonPath('message', 'Auxiliar no encontrado.');
        }

        $this->actingAs($cuenta)->deleteJson('/api/auxiliares/abc')->assertNotFound();

        $this->assertDatabaseHas('auxiliares', ['user_id' => $ajeno->id, 'registrado_por' => $otro->id]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'auxiliar.quitar']);
    }

    public function test_assistants_need_a_session_and_the_permission(): void
    {
        $this->getJson('/api/auxiliares')->assertUnauthorized();
        $this->postJson('/api/auxiliares', [])->assertUnauthorized();
        $this->deleteJson('/api/auxiliares/1')->assertUnauthorized();

        $auxiliar = $this->usuarioConRol('Auxiliar');

        $this->actingAs($auxiliar)
            ->getJson('/api/auxiliares')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'examenes');

        $this->actingAs($auxiliar)
            ->postJson('/api/auxiliares', ['nombre' => 'Vargas Arce Sergio', 'codigo_universitario' => '202000123'])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'examenes');

        $this->actingAs($auxiliar)
            ->deleteJson('/api/auxiliares/1')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'examenes');

        $this->assertDatabaseCount('auxiliares', 0);
    }
}
