<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/02_funciones.sql: funciones y disparadores (reglas RN03-RN08, RN15, RN16, RN19, RN22).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/02_funciones.sql')));
    }

    public function down(): void
    {
        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_normalizar_especialidad ON especialidad;
            DROP TRIGGER IF EXISTS trg_validar_cita ON cita;
            DROP TRIGGER IF EXISTS trg_validar_transicion ON cita;
            DROP TRIGGER IF EXISTS trg_interpretar_resultado ON resultado_laboratorio;
            DROP TRIGGER IF EXISTS trg_aplicar_cobertura ON pago;
            DROP TRIGGER IF EXISTS trg_tocar_historia ON antecedente;
            DROP TRIGGER IF EXISTS trg_limitar_recuperaciones ON recuperacion_clave;
            DROP FUNCTION IF EXISTS fn_edad(DATE, DATE), fn_normalizar_especialidad(), fn_validar_cita(),
                fn_validar_transicion(), fn_interpretar_resultado(), fn_aplicar_cobertura(),
                fn_tocar_historia(), fn_limitar_recuperaciones();
        ');
    }
};
