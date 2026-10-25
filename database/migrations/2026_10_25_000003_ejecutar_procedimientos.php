<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/03_procedimientos.sql: procedimientos y funciones almacenadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/03_procedimientos.sql')));
    }

    public function down(): void
    {
        DB::unprepared('
            DROP PROCEDURE IF EXISTS sp_registrar_bitacora, sp_registrar_paciente, sp_programar_cita,
                sp_cancelar_cita, sp_reprogramar_cita, sp_registrar_pago, sp_solicitar_examen,
                sp_registrar_resultado, sp_restablecer_clave;
            DROP FUNCTION IF EXISTS fn_franjas_disponibles(INT, DATE), fn_ingresos(DATE, DATE);
        ');
    }
};
