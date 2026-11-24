<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La importación de la oferta fija un límite de 120 s con `set_time_limit`,
        // que en la suite queda activo para todas las pruebas que siguen y corta
        // la corrida entera apenas el equipo va lento. Cada prueba parte sin límite.
        set_time_limit(0);
    }
}
