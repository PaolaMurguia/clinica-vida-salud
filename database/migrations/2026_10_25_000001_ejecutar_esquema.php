<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta database/sql/01_esquema.sql: extensiones, secuencias, las 22 tablas e índices.
 */
return new class extends Migration
{
    /** Secuencias de los códigos (P-0001, M-0001, CM-0001, PG-00001, EX-00001). */
    private const SECUENCIAS = 'seq_paciente_codigo, seq_medico_codigo, seq_cita_codigo, seq_pago_comprobante, seq_examen_codigo';

    public function up(): void
    {
        // migrate:fresh borra las tablas pero no las secuencias (no pertenecen a una columna);
        // se eliminan aquí para que el script se ejecute igual que en una base vacía
        DB::unprepared('DROP SEQUENCE IF EXISTS ' . self::SECUENCIAS . ' CASCADE');
        DB::unprepared(file_get_contents(database_path('sql/01_esquema.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS bitacora, notificacion, resultado_laboratorio, examen_laboratorio,
            rango_referencia, parametro_laboratorio, registro_atencion, antecedente, historia_clinica, pago,
            historial_estado_cita, cita, seguro_paciente, recuperacion_clave, usuario, paciente, bloqueo_agenda,
            horario_atencion, medico_especialidad, medico, especialidad, rol CASCADE');
        DB::unprepared('DROP SEQUENCE IF EXISTS ' . self::SECUENCIAS . ' CASCADE');
    }
};
