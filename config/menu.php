<?php

/*
|--------------------------------------------------------------------------
| Opciones del menú según el rol (RNF03, RNF09)
|--------------------------------------------------------------------------
| Las usan la barra superior (plantilla), V.menu y el portal del paciente.
| Solo se muestran las opciones cuya ruta ya existe (Route::has).
*/

return [
    'opciones' => [
        'Administrador' => [
            ['texto' => 'Usuarios',       'ruta' => 'usuarios.index',       'descripcion' => 'Cuentas, roles y estados (CU03)'],
            ['texto' => 'Bitácora',       'ruta' => 'bitacora.index',       'descripcion' => 'Accesos y acciones del sistema (CU04)'],
            ['texto' => 'Especialidades', 'ruta' => 'especialidades.index', 'descripcion' => 'Costo y rango de edad (CU05)'],
            ['texto' => 'Médicos',        'ruta' => 'medicos.index',        'descripcion' => 'Médicos y sus especialidades (CU06)'],
            ['texto' => 'Horarios',       'ruta' => 'horarios.index',       'descripcion' => 'Horarios de atención (CU07)'],
            ['texto' => 'Pacientes',      'ruta' => 'pacientes.index',      'descripcion' => 'Registro de pacientes (CU08)'],
            ['texto' => 'Buscar paciente', 'ruta' => 'pacientes.buscar',    'descripcion' => 'Por CI, nombre o apellido (CU09)'],
            ['texto' => 'Agenda',         'ruta' => 'agenda.index',         'descripcion' => 'Citas de cada médico por día o semana (CU18)'],
            ['texto' => 'Bloqueos',       'ruta' => 'bloqueos.index',       'descripcion' => 'Feriados y bloqueos de agenda (CU24)'],
        ],
        'Recepcionista' => [
            ['texto' => 'Pacientes',       'ruta' => 'pacientes.index',               'descripcion' => 'Registro de pacientes (CU08)'],
            ['texto' => 'Buscar paciente', 'ruta' => 'pacientes.buscar',              'descripcion' => 'Por CI, nombre o apellido (CU09)'],
            ['texto' => 'Disponibilidad',  'ruta' => 'disponibilidad.especialidades', 'descripcion' => 'Franjas libres y programar cita (CU13, CU14)'],
            ['texto' => 'Agenda',          'ruta' => 'agenda.index',                  'descripcion' => 'Citas del día y registro de asistencia (CU17, CU18)'],
            ['texto' => 'Pagos',           'ruta' => 'pagos.create',                  'descripcion' => 'Cobro de consultas y pagos pendientes (CU25)'],
        ],
        'Médico' => [
            ['texto' => 'Agenda',          'ruta' => 'agenda.index',                  'descripcion' => 'Mis citas del día o la semana (CU17, CU18)'],
        ],
        'Paciente' => [
            ['texto' => 'Mi perfil',      'ruta' => 'perfil.show',                   'descripcion' => 'Datos, antecedentes y preferencias (CU11)'],
            ['texto' => 'Mis citas',      'ruta' => 'historialCitas.index',          'descripcion' => 'Historial, reprogramar y cancelar (CU19)'],
            ['texto' => 'Disponibilidad', 'ruta' => 'disponibilidad.especialidades', 'descripcion' => 'Franjas libres y reservar una cita (CU13, CU14)'],
        ],
    ],
];
