<?php

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use Database\Factories\UserFactory;
use Tests\TestCase;

class UserModelConfigurationTest extends TestCase
{
    public function test_authentication_provider_uses_the_administration_user_model(): void
    {
        $this->assertSame(
            User::class,
            config('auth.providers.users.model')
        );
    }

    public function test_user_factory_creates_the_modular_user_model(): void
    {
        $user = UserFactory::new()->make();

        $this->assertInstanceOf(User::class, $user);
    }

    public function test_user_factory_gives_every_account_a_valid_user_name(): void
    {
        $user = UserFactory::new()->makeOne();

        $this->assertMatchesRegularExpression(
            '/^[a-z0-9._-]+$/',
            $user->usuario
        );
    }

    public function test_role_state_and_temporary_password_are_not_mass_assignable(): void
    {
        $user = new User([
            'name' => 'Prueba',
            'usuario' => 'prueba',
            'role_id' => 1,
            'is_active' => false,
            'password_temporal_expira_en' => now(),
        ]);

        $this->assertNull($user->getAttribute('role_id'));
        $this->assertNull($user->getAttribute('is_active'));
        $this->assertNull($user->getAttribute('password_temporal_expira_en'));
        $this->assertSame('prueba', $user->usuario);
    }
}
