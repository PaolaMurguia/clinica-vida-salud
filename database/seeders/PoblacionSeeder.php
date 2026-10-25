<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/04_poblacion.sql con los datos de prueba.
 */
class PoblacionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            DB::unprepared(file_get_contents(database_path('sql/04_poblacion.sql')));

            // Decisión A7: el nombre de usuario de un paciente es su CI
            // (el script crea maria.lopez y luis.fernandez; no se modifica el .sql)
            DB::update('UPDATE usuario u SET nombre_usuario = p.ci
                        FROM paciente p
                        WHERE u.paciente_id = p.id AND p.ci IS NOT NULL');
        });
    }
}
