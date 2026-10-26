<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * La importacion de la oferta fija `set_time_limit(120)` para la peticion;
     * en la suite ese limite queda vigente para todas las pruebas siguientes
     * del mismo proceso y la corta a los dos minutos.
     */
    protected function tearDown(): void
    {
        set_time_limit(0);

        parent::tearDown();
    }
}
