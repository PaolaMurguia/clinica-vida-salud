<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Población inicial de la base de datos.
     */
    public function run(): void
    {
        $this->call(PoblacionSeeder::class);
    }
}
