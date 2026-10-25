<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Base de datos exclusiva de las pruebas; nunca se toca clinica_vida_salud.
     */
    private const BD_PRUEBAS = 'clinica_vida_salud_test';

    protected function setUp(): void
    {
        parent::setUp();

        // Seguro adicional: si la configuración en caché u otro .env apunta a otra base, se detiene todo
        $conexion = config('database.default');
        $base = config("database.connections.$conexion.database");
        if ($conexion !== 'pgsql' || $base !== self::BD_PRUEBAS) {
            fwrite(STDERR, "\nPruebas detenidas: la conexión apunta a [$conexion/$base] y no a " . self::BD_PRUEBAS . ".\n"
                . "Ejecute php artisan config:clear y vuelva a intentarlo.\n");
            exit(1);
        }
    }
}
