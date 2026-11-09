<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatasetCompletoSeeder extends Seeder
{
    /**
     * Genera un conjunto de datos realista para la clínica, cubriendo citas,
     * historias clínicas, pagos, registros de atención y laboratorio.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $existingCitas = (int) DB::table('cita')->count();
            if ($existingCitas >= 120) {
                return;
            }

            $recepcionistas = DB::table('usuario')->whereIn('rol_id', function ($query) {
                $query->select('id')->from('rol')->whereIn('nombre', ['Recepcionista', 'Administrador']);
            })->pluck('id')->all();

            $medicos = DB::table('medico')->select('id', 'nro_registro_prof')->get()->all();
            $pacientes = $this->crearPacientesAdicionales();
            $this->crearSeguros($pacientes);
            $this->crearHistoriasClinicas($pacientes);
            $this->crearAntecedentes($pacientes);

            $especialidadesPorMedico = [];
            foreach ($medicos as $medico) {
                $especialidadesPorMedico[$medico->id] = DB::table('medico_especialidad as me')
                    ->join('especialidad as e', 'e.id', '=', 'me.especialidad_id')
                    ->where('me.medico_id', $medico->id)
                    ->pluck('e.id')
                    ->all();
            }

            $agendados = [];
            $pacientesAdultos = collect($pacientes)->filter(fn ($paciente) => $this->edadPaciente($paciente['id']) >= 18)->values()->all();
            $period = CarbonPeriod::create(Carbon::create(2026, 8, 1), Carbon::create(2026, 11, 30));
            $ocupadosPorMedico = [];

            $bloqueadas = DB::table('bloqueo_agenda')->pluck('fecha')->map(fn ($fecha) => Carbon::parse($fecha)->toDateString())->all();

            foreach ($period as $date) {
                if ($date->lt(Carbon::today()) || $date->isWeekend() || in_array($date->toDateString(), $bloqueadas, true)) {
                    continue;
                }

                foreach ($medicos as $medico) {
                    $slots = array_values(array_filter(
                        $this->obtenerSlotsDisponibles($medico->id, $date),
                        fn ($slot) => ! $this->slotOcupado($ocupadosPorMedico, $medico->id, $date->toDateString(), $slot)
                    ));
                    if (empty($slots)) {
                        continue;
                    }

                    $cantidad = min(random_int(4, 8), count($slots));
                    shuffle($slots);
                    $slotsSeleccionados = array_slice($slots, 0, $cantidad);

                    foreach ($slotsSeleccionados as $slot) {
                        if (empty($pacientesAdultos)) {
                            continue 2;
                        }

                        $paciente = $pacientesAdultos[array_rand($pacientesAdultos)];
                        $especialidadesValidas = $this->especialidadesValidasParaPaciente($medico->id, $paciente['id']);
                        if (empty($especialidadesValidas)) {
                            continue;
                        }

                        $especialidadId = $especialidadesValidas[array_rand($especialidadesValidas)];
                        $estado = $this->estadoRealista($date);
                        $modalidad = (random_int(1, 10) <= 8) ? 'Presencial' : 'Virtual';
                        $canal = (random_int(1, 10) <= 6) ? 'Recepcion' : 'Web';

                        $cita = [
                            'paciente_id' => $paciente['id'],
                            'medico_id' => $medico->id,
                            'especialidad_id' => $especialidadId,
                            'usuario_registro_id' => $recepcionistas[array_rand($recepcionistas)],
                            'fecha' => $date->toDateString(),
                            'hora_inicio' => $slot['inicio'],
                            'hora_fin' => $slot['fin'],
                            'modalidad' => $modalidad,
                            'canal' => $canal,
                            'motivo' => $this->motivoAleatorio(),
                            'estado' => $estado,
                            'motivo_cancelacion' => ($estado === 'Cancelada') ? $this->motivoCancelacion() : null,
                            'fecha_creacion' => now(),
                        ];

                        $citaId = DB::table('cita')->insertGetId($cita);
                        $agendados[] = $citaId;
                        $ocupadosPorMedico[$medico->id][$date->toDateString()][] = [
                            'inicio' => $slot['inicio'],
                            'fin' => $slot['fin'],
                        ];

                        $this->registrarHistorialCita($citaId, $cita['usuario_registro_id'], $estado);

                        if (in_array($estado, ['Atendida', 'Confirmada'], true)) {
                            $this->crearPago($citaId, $paciente['id'], $especialidadId, $cita['usuario_registro_id'], $date);
                        }

                        if ($estado === 'Atendida') {
                            $this->crearRegistroAtencion($citaId, $paciente['id']);
                            if (random_int(1, 3) !== 1) {
                                $this->crearExamenLaboratorio($citaId, $paciente['id'], $medico->id, $cita['usuario_registro_id']);
                            }
                        }

                        if (random_int(1, 6) === 1) {
                            $this->crearNotificacion($citaId, $cita['usuario_registro_id'], $paciente['id'], $date);
                        }
                    }
                }
            }

            $this->generarResultadosLaboratorio();
        });
    }

    private function crearPacientesAdicionales(): array
    {
        $nombres = [
            ['María', 'López'], ['Carlos', 'Gómez'], ['Ana', 'Pérez'], ['Diego', 'Mendoza'], ['Sofía', 'Rojas'],
            ['Martín', 'Quispe'], ['Lucía', 'Torrez'], ['Javier', 'Ramos'], ['Valentina', 'Siles'], ['Luis', 'Cabrera'],
            ['Camila', 'Villarroel'], ['Tomás', 'Bravo'], ['Karina', 'Yupanqui'], ['Renzo', 'Pinto'], ['Nadia', 'García'],
            ['Eduardo', 'Cárdenas'], ['Andrea', 'Ticona'], ['Felipe', 'Sánchez'], ['Paula', 'Mamani'], ['Patricia', 'Aguirre'],
            ['Oscar', 'Mora'], ['Diana', 'Salazar'], ['Ricardo', 'Velasco'], ['Elena', 'Paredes'], ['Gabriel', 'Renzoni'],
        ];

        $pacientes = DB::table('paciente')->select('id', 'ci')->get()->all();
        $ciBase = 9000000;

        foreach ($nombres as $indice => [$nombre, $apellido]) {
            $ci = (string) ($ciBase + $indice + 1);
            $existe = collect($pacientes)->contains(fn ($paciente) => $paciente->ci === $ci);
            if ($existe) {
                continue;
            }

            $id = DB::table('paciente')->insertGetId([
                'codigo' => 'P-' . str_pad((string) ((count($pacientes) + $indice) + 1), 4, '0', STR_PAD_LEFT),
                'ci' => $ci,
                'nombres' => $nombre,
                'apellidos' => $apellido,
                'fecha_nacimiento' => $this->fechaAleatoriaNacimiento(),
                'sexo' => (random_int(0, 1) === 0) ? 'F' : 'M',
                'telefono' => '7' . random_int(1000000, 9999999),
                'correo' => strtolower($nombre) . '.' . strtolower($apellido) . random_int(10, 99) . '@correo.com',
                'direccion' => $this->direccionAleatoria(),
                'contacto_emerg_nombre' => 'Contacto ' . $nombre,
                'contacto_emerg_parent' => 'Familiar',
                'contacto_emerg_tel' => '7' . random_int(1000000, 9999999),
                'notif_correo' => true,
                'notif_alerta' => true,
                'acepta_privacidad' => true,
                'estado' => true,
                'fecha_registro' => now(),
            ]);

            $pacientes[] = (object) ['id' => $id, 'ci' => $ci];
        }

        return collect($pacientes)->map(fn ($p) => ['id' => $p->id, 'ci' => $p->ci])->all();
    }

    private function crearSeguros(array $pacientes): void
    {
        foreach ($pacientes as $paciente) {
            $yaExiste = DB::table('seguro_paciente')->where('paciente_id', $paciente['id'])->exists();
            if ($yaExiste) {
                continue;
            }

            $inicio = Carbon::now()->subYears(1);
            $fin = Carbon::now()->addYears(1);

            DB::table('seguro_paciente')->insert([
                'paciente_id' => $paciente['id'],
                'aseguradora' => ['Aseguradora Andina S.A.', 'Previsión Salud S.A.', 'Seguros Vida Salud'][random_int(0, 2)],
                'nro_poliza' => 'SE-' . random_int(1000, 9999) . '-' . random_int(10, 99),
                'tipo_cobertura' => ['Ambulatoria', 'Integral', 'Básica'][random_int(0, 2)],
                'porcentaje_cobertura' => random_int(50, 80),
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'estado' => true,
            ]);
        }
    }

    private function crearHistoriasClinicas(array $pacientes): void
    {
        foreach ($pacientes as $paciente) {
            $existe = DB::table('historia_clinica')->where('paciente_id', $paciente['id'])->exists();
            if ($existe) {
                continue;
            }

            DB::table('historia_clinica')->insert([
                'paciente_id' => $paciente['id'],
                'tipo_sangre' => ['O+', 'A+', 'B+', 'AB+', 'O-'][random_int(0, 4)],
                'observaciones' => 'Paciente con seguimiento y control rutinario del sistema.',
                'fecha_creacion' => now(),
                'fecha_actualizacion' => now(),
            ]);
        }
    }

    private function crearAntecedentes(array $pacientes): void
    {
        $usuarios = DB::table('usuario')->pluck('id')->all();

        foreach ($pacientes as $paciente) {
            $historia = DB::table('historia_clinica')->where('paciente_id', $paciente['id'])->value('id');
            if (!$historia) {
                continue;
            }

            $existen = DB::table('antecedente')->where('historia_clinica_id', $historia)->count();
            if ($existen >= 2) {
                continue;
            }

            $tipos = [
                ['Alergia', 'Penicilina'],
                ['Enfermedad cronica', 'Hipertensión arterial'],
                ['Medicamento', 'Metformina 850 mg'],
                ['Cirugia', 'Apendicectomía'],
                ['Familiar', 'Antecedente de diabetes materna'],
            ];

            foreach (array_slice($tipos, 0, random_int(2, 3)) as [$tipo, $descripcion]) {
                DB::table('antecedente')->insert([
                    'historia_clinica_id' => $historia,
                    'tipo' => $tipo,
                    'descripcion' => $descripcion,
                    'origen' => (random_int(0, 1) === 0) ? 'Paciente' : 'Médico',
                    'usuario_id' => $usuarios[array_rand($usuarios)],
                    'fecha_registro' => now()->subDays(random_int(5, 200)),
                ]);
            }
        }
    }

    private function obtenerSlotsDisponibles(int $medicoId, Carbon $fecha): array
    {
        $dia = $fecha->dayOfWeekIso;
        $horarios = DB::table('horario_atencion')
            ->where('medico_id', $medicoId)
            ->where('estado', true)
            ->where('dia_semana', $dia)
            ->get();

        $slots = [];
        $tope = Carbon::createFromFormat('H:i:s', '16:00:00');

        foreach ($horarios as $horario) {
            $inicio = Carbon::createFromFormat('H:i:s', $horario->hora_inicio);
            $fin = Carbon::createFromFormat('H:i:s', $horario->hora_fin);
            $duracion = (int) $horario->duracion_cita_min;

            while ($inicio->copy()->addMinutes($duracion) <= $fin && $inicio->copy()->addMinutes($duracion) <= $tope) {
                $finSlot = $inicio->copy()->addMinutes($duracion);
                $slots[] = [
                    'inicio' => $inicio->format('H:i:s'),
                    'fin' => $finSlot->format('H:i:s'),
                ];
                $inicio->addMinutes($duracion);
            }
        }

        return $slots;
    }

    private function estadoRealista(Carbon $fecha): string
    {
        if ($fecha->lt(Carbon::today())) {
            return ['Atendida', 'Confirmada', 'Cancelada', 'Inasistencia'][random_int(0, 3)];
        }

        $estadisticos = ['Programada', 'Confirmada', 'Programada', 'Confirmada', 'Cancelada'];
        return $estadisticos[random_int(0, count($estadisticos) - 1)];
    }

    private function edadPaciente(int $pacienteId): int
    {
        $paciente = DB::table('paciente')->where('id', $pacienteId)->first();

        return $paciente ? Carbon::parse($paciente->fecha_nacimiento)->age : 0;
    }

    private function especialidadesValidasParaPaciente(int $medicoId, int $pacienteId): array
    {
        $paciente = DB::table('paciente')->where('id', $pacienteId)->first();
        $edad = Carbon::parse($paciente->fecha_nacimiento)->age;

        $especialidades = DB::table('medico_especialidad as me')
            ->join('especialidad as e', 'e.id', '=', 'me.especialidad_id')
            ->where('me.medico_id', $medicoId)
            ->select('e.id', 'e.edad_minima', 'e.edad_maxima')
            ->get();

        return $especialidades
            ->filter(function ($especialidad) use ($edad) {
                if ($especialidad->edad_minima !== null && $edad < (int) $especialidad->edad_minima) {
                    return false;
                }

                if ($especialidad->edad_maxima !== null && $edad > (int) $especialidad->edad_maxima) {
                    return false;
                }

                return true;
            })
            ->pluck('id')
            ->all();
    }

    private function motivoAleatorio(): string
    {
        $motivos = [
            'Control general',
            'Dolor de cabeza',
            'Control de presión',
            'Revisión de resultados',
            'Seguimiento clínico',
            'Dolor abdominal',
            'Control de glucosa',
            'Consulta de rutina',
            'Revisión de fiebre',
            'Chequeo general',
        ];

        return $motivos[random_int(0, count($motivos) - 1)];
    }

    private function motivoCancelacion(): string
    {
        $motivos = [
            'El paciente solicitó cambio de horario',
            'Paciente no pudo asistir',
            'Conflicto de agenda',
            'Consulta reprogramada por atención prioritaria',
        ];

        return $motivos[random_int(0, count($motivos) - 1)];
    }

    private function slotOcupado(array $ocupadosPorMedico, int $medicoId, string $fecha, array $slot): bool
    {
        $ocupados = $ocupadosPorMedico[$medicoId][$fecha] ?? [];

        foreach ($ocupados as $ocupado) {
            $inicioNuevo = Carbon::createFromFormat('H:i:s', $slot['inicio']);
            $finNuevo = Carbon::createFromFormat('H:i:s', $slot['fin']);
            $inicioExistente = Carbon::createFromFormat('H:i:s', $ocupado['inicio']);
            $finExistente = Carbon::createFromFormat('H:i:s', $ocupado['fin']);

            if ($inicioNuevo->lt($finExistente) && $inicioExistente->lt($finNuevo)) {
                return true;
            }
        }

        return false;
    }

    private function registrarHistorialCita(int $citaId, int $usuarioId, string $estado): void
    {
        $transiciones = [
            'Programada' => ['Confirmada', 'Cancelada'],
            'Confirmada' => ['Atendida', 'Inasistencia'],
            'Atendida' => ['Atendida'],
            'Cancelada' => ['Cancelada'],
            'Inasistencia' => ['Inasistencia'],
        ];

        $anterior = 'Programada';
        $nuevo = $estado;

        if ($estado === 'Atendida') {
            $anterior = 'Confirmada';
            $nuevo = 'Atendida';
        }

        if ($estado === 'Cancelada') {
            $anterior = 'Programada';
            $nuevo = 'Cancelada';
        }

        if ($estado === 'Inasistencia') {
            $anterior = 'Confirmada';
            $nuevo = 'Inasistencia';
        }

        DB::table('historial_estado_cita')->insert([
            'cita_id' => $citaId,
            'usuario_id' => $usuarioId,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'fecha_cambio' => now(),
            'observacion' => 'Cambio generado por dataset de prueba',
        ]);
    }

    private function crearPago(int $citaId, int $pacienteId, int $especialidadId, int $usuarioRegistroId, Carbon $fechaCita): void
    {
        $seguro = DB::table('seguro_paciente')
            ->where('paciente_id', $pacienteId)
            ->where('estado', true)
            ->whereDate('fecha_inicio', '<=', $fechaCita->toDateString())
            ->whereDate('fecha_fin', '>=', $fechaCita->toDateString())
            ->first();

        $monto = (float) DB::table('especialidad')->where('id', $especialidadId)->value('costo_consulta');
        $porcentaje = $seguro ? (float) $seguro->porcentaje_cobertura : 0;

        DB::table('pago')->updateOrInsert(
            ['cita_id' => $citaId],
            [
                'seguro_id' => $seguro->id ?? null,
                'usuario_id' => $usuarioRegistroId,
                'monto_consulta' => $monto,
                'porcentaje_cobertura' => $porcentaje,
                'metodo_pago' => ['Efectivo', 'QR'][random_int(0, 1)],
                'estado' => (random_int(0, 1) === 0) ? 'Pagado' : 'Pendiente',
                'fecha_pago' => now(),
                'fecha_registro' => now(),
            ]
        );
    }

    private function crearRegistroAtencion(int $citaId, int $pacienteId): void
    {
        $existe = DB::table('registro_atencion')->where('cita_id', $citaId)->exists();
        if ($existe) {
            return;
        }

        $diagnosticos = [
            'Hipertensión controlada',
            'Fiebre viral leve',
            'Control general sin hallazgos relevantes',
            'Dolor de espalda por tensión muscular',
            'Control metabólico en rango',
            'Consulta de seguimiento de salud preventiva',
        ];

        DB::table('registro_atencion')->insert([
            'cita_id' => $citaId,
            'diagnostico' => $diagnosticos[random_int(0, count($diagnosticos) - 1)],
            'tratamiento' => 'Tratamiento de apoyo con seguimiento y control clínico.',
            'indicaciones' => 'Mantener hidratación, revisión en 7 días y control de signos de alarma.',
            'peso_kg' => round(random_int(55, 90) + (random_int(0, 99) / 10), 1),
            'talla_cm' => round(random_int(150, 185) + (random_int(0, 9) / 10), 1),
            'presion_arterial' => random_int(100, 140) . '/' . random_int(60, 90),
            'temperatura_c' => round(random_int(36, 38) + (random_int(0, 9) / 10), 1),
            'dictado_voz' => false,
            'fecha_registro' => now(),
        ]);
    }

    private function crearExamenLaboratorio(int $citaId, int $pacienteId, int $medicoId, int $usuarioRegistroId): void
    {
        $tipos = ['Hemograma', 'Perfil metabólico', 'Glucosa', 'Perfil lipídico'];
        $tipo = $tipos[random_int(0, count($tipos) - 1)];

        $id = DB::table('examen_laboratorio')->insertGetId([
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'cita_id' => $citaId,
            'tipo_examen' => $tipo,
            'fecha_solicitud' => now()->subDays(random_int(1, 5)),
            'fecha_muestra' => now()->subDays(random_int(1, 4))->toDateString(),
            'observacion' => 'Solicitud generada por dataset de prueba',
            'estado' => 'Revisado',
            'usuario_registro_id' => $usuarioRegistroId,
            'fecha_registro' => now(),
        ]);

        DB::table('resultado_laboratorio')->where('examen_id', $id)->delete();
        $parametros = DB::table('parametro_laboratorio')->where('estado', true)->get();

        foreach ($parametros->take(random_int(2, 4)) as $parametro) {
            $rango = DB::table('rango_referencia')
                ->where('parametro_id', $parametro->id)
                ->orderBy('edad_minima')
                ->first();

            if (!$rango) {
                continue;
            }

            $valor = random_int((int) $rango->valor_minimo, (int) $rango->valor_maximo);
            $interpretacion = ($valor < $rango->valor_minimo) ? 'Bajo' : (($valor > $rango->valor_maximo) ? 'Alto' : 'Normal');

            DB::table('resultado_laboratorio')->insert([
                'examen_id' => $id,
                'parametro_id' => $parametro->id,
                'valor' => $valor,
                'valor_minimo_ref' => $rango->valor_minimo,
                'valor_maximo_ref' => $rango->valor_maximo,
                'interpretacion' => $interpretacion,
            ]);
        }
    }

    private function generarResultadosLaboratorio(): void
    {
        $examenes = DB::table('examen_laboratorio')->where('estado', 'Revisado')->get();
        foreach ($examenes as $examen) {
            $parametros = DB::table('parametro_laboratorio')->where('estado', true)->get();
            foreach ($parametros->take(random_int(2, 3)) as $parametro) {
                $rango = DB::table('rango_referencia')
                    ->where('parametro_id', $parametro->id)
                    ->first();

                if (!$rango) {
                    continue;
                }

                $valor = random_int((int) $rango->valor_minimo, (int) $rango->valor_maximo);
                $interpretacion = ($valor < $rango->valor_minimo) ? 'Bajo' : (($valor > $rango->valor_maximo) ? 'Alto' : 'Normal');

                DB::table('resultado_laboratorio')->updateOrInsert(
                    ['examen_id' => $examen->id, 'parametro_id' => $parametro->id],
                    [
                        'valor' => $valor,
                        'valor_minimo_ref' => $rango->valor_minimo,
                        'valor_maximo_ref' => $rango->valor_maximo,
                        'interpretacion' => $interpretacion,
                    ]
                );
            }
        }
    }

    private function crearNotificacion(int $citaId, int $usuarioId, int $pacienteId, Carbon $fecha): void
    {
        $tipo = ['Recordatorio', 'Confirmacion', 'Resultado'][random_int(0, 2)];
        DB::table('notificacion')->insert([
            'usuario_id' => $usuarioId,
            'cita_id' => $citaId,
            'canal' => 'Sistema',
            'tipo' => $tipo,
            'mensaje' => 'Recordatorio automático del sistema para la cita programada.',
            'fecha_programada' => $fecha->copy()->setTime(8, 0, 0),
            'fecha_envio' => now(),
            'estado' => 'Enviado',
            'intentos' => 1,
        ]);
    }

    private function fechaAleatoriaNacimiento(): string
    {
        $year = random_int(1950, 2015);
        $month = random_int(1, 12);
        $day = random_int(1, 28);

        return Carbon::create($year, $month, $day)->toDateString();
    }

    private function direccionAleatoria(): string
    {
        $calles = [
            'Av. Banzer',
            'Calle 1ro de Mayo',
            'Av. Murillo',
            'Barrio San Jorge',
            'Urb. Florida',
            'Santa Cruz',
            'Plan 3000',
            'Villa Fátima',
        ];

        return $calles[random_int(0, count($calles) - 1)] . ' ' . random_int(100, 999);
    }
}
