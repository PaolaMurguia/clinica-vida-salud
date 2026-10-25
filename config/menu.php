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
        ],
        'Recepcionista' => [
            ['texto' => 'Pacientes',       'ruta' => 'pacientes.index',               'descripcion' => 'Registro de pacientes (CU08)'],
            ['texto' => 'Buscar paciente', 'ruta' => 'pacientes.buscar',              'descripcion' => 'Por CI, nombre o apellido (CU09)'],
            ['texto' => 'Disponibilidad',  'ruta' => 'disponibilidad.especialidades', 'descripcion' => 'Franjas libres de los médicos (CU13)'],
        ],
        'Médico' => [],
        'Paciente' => [
            ['texto' => 'Mi perfil',      'ruta' => 'perfil.show',                   'descripcion' => 'Datos, antecedentes y preferencias (CU11)'],
            ['texto' => 'Disponibilidad', 'ruta' => 'disponibilidad.especialidades', 'descripcion' => 'Franjas libres para reservar (CU13)'],
        ],
    ],
];
