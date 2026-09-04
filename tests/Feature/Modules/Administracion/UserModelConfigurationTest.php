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
}
