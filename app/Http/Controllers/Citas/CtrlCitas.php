<?php

namespace App\Http\Controllers\Citas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Citas\CambiarEstadoCitaRequest;
use App\Http\Requests\Citas\CancelarCitaRequest;
use App\Http\Requests\Citas\CreateCitaRequest;
use App\Http\Requests\Citas\EditCitaRequest;
use App\Http\Requests\Citas\StoreCitaRequest;
use App\Http\Requests\Citas\UpdateCitaRequest;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HistorialCita;
use App\Models\Horario;
use App\Models\Medico;
use App\Models\Notificacion;
use App\Models\Paciente;
use App\Services\ServicioBitacora;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlCitas: CU14 – Programar cita, CU15 – Reprogramar cita, CU16 – Cancelar cita
 * y CU17 – Registrar asistencia.
 */
class CtrlCitas extends Controller
{
    /**
     * CU14 – Programar cita (pasos 2 y 3, mostrar): resumen de la franja elegida en la disponibilidad
     * con la modalidad y el motivo. Con ?cita={id} muestra el comprobante de la cita registrada (paso 9).
     */
    public function create(CreateCitaRequest $request): View
    {
        if ($request->has('cita')) {
            $cita = Cita::obtener((int) $request->validated('cita'));
            // RN18: el paciente solo ve sus propias citas
            abort_if($request->user()->paciente_id !== null && $cita->paciente_id !== $request->user()->paciente_id, 403);

            return view('programarCita', ['cita' => $cita]);
        }

        $datos = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();
        $paciente = Paciente::obtener((int) $datos['paciente_id']);
        $medico = Medico::obtener((int) $datos['medico_id']);

        return view('programarCita', [
            'cita'         => null,
            'paciente'     => $paciente,
            'especialidad' => Especialidad::listarPorEdad((int) $paciente->fecha_nacimiento->diffInYears($fecha))
                ->firstWhere('id', (int) $datos['especialidad_id']),
            'medico'       => $medico,
            'fecha'        => $fecha,
            'franja'       => Horario::obtenerDelDia($medico->id, $fecha)
                ->flatMap(fn (Horario $horario) => $horario->generarFranjas())
                ->firstWhere('inicio', $datos['hora_inicio']),
        ]);
    }

    /**
     * CU14 – Programar cita (pasos 5 a 9, programar): dentro de una transacción vuelve a verificar la
     * franja, guarda la cita Programada, su estado inicial, el recordatorio y los avisos, y muestra el
     * comprobante. Si otro usuario ocupó la franja, vuelve a mostrar la disponibilidad (D2).
     */
    public function store(StoreCitaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $usuario = $request->user();
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();
        $paciente = Paciente::obtener((int) $datos['paciente_id']);
        $medico = Medico::obtener((int) $datos['medico_id']);

        try {
            $cita = DB::transaction(function () use ($datos, $usuario, $fecha, $paciente, $medico) {
                // paso 5: la franja del horario del médico (RN05, RN08) y sin superposición (RN07, RN13)
                $franja = Horario::obtenerDelDia($medico->id, $fecha)
                    ->flatMap(fn (Horario $horario) => $horario->generarFranjas())
                    ->firstWhere('inicio', $datos['hora_inicio']);
                if (! $franja || Cita::existeConflicto($medico->id, $fecha, $franja['inicio'], $franja['fin'])) {
                    return null;   // la franja dejó de estar libre entre la validación y la transacción
                }

                $cita = Cita::crear([
                    'paciente_id'         => $paciente->id,
                    'medico_id'           => $medico->id,
                    'especialidad_id'     => (int) $datos['especialidad_id'],
                    'usuario_registro_id' => $usuario->id,
                    'fecha'               => $fecha->toDateString(),
                    'hora_inicio'         => $franja['inicio'],
                    'hora_fin'            => $franja['fin'],
                    'modalidad'           => $datos['modalidad'],
                    'canal'               => $usuario->paciente_id !== null ? 'Web' : 'Recepcion',
                    'motivo'              => $datos['motivo'] ?? null,
                ]);

                // paso 7: estado inicial
                HistorialCita::registrar($cita, null, 'Programada', 'Cita registrada');

                // paso 8: confirmación y recordatorio al paciente, aviso en la agenda del médico (RN21, RF21)
                $cuando = $cita->fecha->format('d/m/Y') . ' a las ' . substr($cita->hora_inicio, 0, 5);
                $conQuien = "con {$medico->nombres} {$medico->apellidos} ({$cita->especialidad->nombre})";
                if ($paciente->usuario) {
                    Notificacion::programar($paciente->usuario, 'Confirmacion',
                        "Su cita {$cita->codigo} $conQuien quedó programada para el $cuando.", $cita);
                    Notificacion::programar($paciente->usuario, 'Recordatorio',
                        "Recuerde su cita {$cita->codigo} $conQuien el $cuando.", $cita,
                        $fecha->copy()->setTimeFromTimeString($cita->hora_inicio)->subDay());
                }
                if ($medico->usuario) {
                    Notificacion::programar($medico->usuario, 'Agenda',
                        "Nueva cita {$cita->codigo} el $cuando: {$paciente->nombres} {$paciente->apellidos} ({$cita->especialidad->nombre}).", $cita);
                }

                return $cita;
            });
        } catch (QueryException $e) {
            // D2: la restricción ex_cita_no_solapada rechazó la inserción (otro usuario ganó la franja)
            if (($e->errorInfo[0] ?? null) !== '23P01') {
                throw $e;
            }
            report($e);
            $cita = null;
        }

        if ($cita === null) {
            return redirect()->route('disponibilidad.franjas', [
                'paciente_id'     => $paciente->id,
                'especialidad_id' => $datos['especialidad_id'],
                'medico_id'       => $medico->id,
                'fecha'           => $fecha->toDateString(),
            ])->with('error', 'La franja ya fue ocupada por otro usuario. Elija otra franja libre.');
        }

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Citas', $cita->id,
            "Cita {$cita->codigo} del paciente {$paciente->codigo} con el médico {$medico->codigo} el {$cita->fecha->format('d/m/Y')} " . substr($cita->hora_inicio, 0, 5));

        return redirect()->route('citas.create', ['cita' => $cita->id])
            ->with('exito', "Cita {$cita->codigo} programada.");
    }

    /**
     * CU15 – Reprogramar cita (pasos 1 a 3, mostrar): datos actuales de la cita y franjas del mismo médico
     * en la fecha elegida (bloqueo, horario del día, franjas y ocupadas, como en CU13).
     */
    public function edit(EditCitaRequest $request, Cita $cita): View
    {
        $cita = Cita::obtener($cita->id);
        $propio = $request->user()->paciente_id;
        abort_if($propio !== null && $cita->paciente_id !== $propio, 403);   // RN18

        $fecha = $request->validated('fecha') ? Carbon::createFromFormat('Y-m-d', $request->validated('fecha'))->startOfDay() : null;
        $medico = Medico::obtener($cita->medico_id);
        $resultado = null;
        if ($fecha && $medico->estado) {
            $bloqueo = Bloqueo::estaBloqueada($medico->id, $fecha);
            $horarios = $bloqueo ? collect() : Horario::obtenerDelDia($medico->id, $fecha);
            $ocupadas = Cita::listarOcupadas($medico->id, $fecha);
            $esActual = fn (array $franja) => $cita->fecha->isSameDay($fecha) && substr($cita->hora_inicio, 0, 5) === $franja['inicio'];
            $resultado = [
                'bloqueo' => $bloqueo,
                'atiende' => $bloqueo || $horarios->isNotEmpty(),
                'franjas' => $horarios
                    ->flatMap(fn (Horario $horario) => $horario->generarFranjas())
                    ->reject(fn (array $franja) => $fecha->copy()->setTimeFromTimeString($franja['inicio'])->lte(now()))
                    ->map(fn (array $franja) => $franja + [
                        'estado' => match (true) {
                            $esActual($franja) => 'actual',
                            $ocupadas->contains(fn ($otra) => substr($otra->hora_inicio, 0, 5) < $franja['fin'] && substr($otra->hora_fin, 0, 5) > $franja['inicio']) => 'ocupada',
                            default => 'libre',
                        },
                    ])->values()->all(),
            ];
        }

        return view('reprogramarCita', [
            'cita'      => $cita,
            'medico'    => $medico,
            'fecha'     => $fecha,
            'resultado' => $resultado,
            'enPlazo'   => $propio === null
                || now()->addHours(Cita::PLAZO_PACIENTE_HORAS)->lte($cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio)),
        ]);
    }

    /**
     * CU15 – Reprogramar cita (pasos 5 a 10, reprogramar): dentro de una transacción verifica de nuevo la
     * franja sin contar la propia cita, cambia la fecha y el horario, registra el historial, renueva el
     * recordatorio, avisa al paciente y al médico y muestra el comprobante actualizado.
     */
    public function update(UpdateCitaRequest $request, Cita $cita): RedirectResponse
    {
        $datos = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();
        $anterior = $cita->fecha->format('d/m/Y') . ' ' . substr($cita->hora_inicio, 0, 5);

        try {
            $reprogramada = DB::transaction(function () use ($cita, $datos, $fecha, $anterior) {
                $cita = Cita::whereKey($cita->id)->lockForUpdate()->first();
                $franja = Horario::obtenerDelDia($cita->medico_id, $fecha)
                    ->flatMap(fn (Horario $horario) => $horario->generarFranjas())
                    ->firstWhere('inicio', $datos['hora_inicio']);
                if (! in_array($cita->estado, Cita::VIGENTES, true) || ! $franja
                    || Cita::existeConflicto($cita->medico_id, $fecha, $franja['inicio'], $franja['fin'], $cita->id)) {
                    return null;
                }

                $cita->reprogramar($fecha, $franja['inicio'], $franja['fin']);                // paso 7
                $nueva = $fecha->format('d/m/Y') . ' ' . $franja['inicio'];
                HistorialCita::registrar($cita, $cita->estado, $cita->estado, "Reprogramada del $anterior al $nueva"); // paso 8

                // paso 9: recordatorio nuevo (RN21) y aviso de reprogramación (RF21)
                Notificacion::anularPendientes($cita);
                $cita = Cita::obtener($cita->id);
                $detalle = "({$cita->especialidad->nombre}, {$cita->paciente->nombres} {$cita->paciente->apellidos})";
                if ($cita->paciente->usuario) {
                    Notificacion::programar($cita->paciente->usuario, 'Recordatorio',
                        "Recuerde su cita {$cita->codigo} el $nueva $detalle.", $cita,
                        $fecha->copy()->setTimeFromTimeString($franja['inicio'])->subDay());
                }
                foreach ([$cita->paciente->usuario, $cita->medico->usuario] as $destinatario) {
                    if ($destinatario) {
                        Notificacion::programar($destinatario, 'Reprogramacion',
                            "La cita {$cita->codigo} $detalle se reprogramó del $anterior al $nueva.", $cita);
                    }
                }

                return $cita;
            });
        } catch (QueryException $e) {
            // la restricción ex_cita_no_solapada rechazó la nueva franja (otro usuario la ocupó)
            if (($e->errorInfo[0] ?? null) !== '23P01') {
                throw $e;
            }
            report($e);
            $reprogramada = null;
        }

        if ($reprogramada === null) {
            return redirect()->route('citas.edit', ['cita' => $cita->id, 'fecha' => $fecha->toDateString()])
                ->with('error', 'La franja ya fue ocupada o la cita cambió mientras tanto. Elija otra franja libre.');
        }

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'REPROGRAMAR', 'Citas', $reprogramada->id,
            "Reprogramación de la cita {$reprogramada->codigo} del $anterior al {$fecha->format('d/m/Y')} " . substr($reprogramada->hora_inicio, 0, 5));

        // paso 10: comprobante actualizado
        return redirect()->route('citas.create', ['cita' => $reprogramada->id])
            ->with('exito', "Cita {$reprogramada->codigo} reprogramada.");
    }

    /**
     * CU17 – Registrar asistencia (confirmar, atender, registrarInasistencia): cambia el estado de la cita
     * desde la agenda, lo registra en el historial y, si la cita terminó (Atendida o Inasistencia), anula
     * los recordatorios pendientes (RN21). Si otro usuario la cambió antes, se conserva el primer cambio.
     */
    public function cambiarEstado(CambiarEstadoCitaRequest $request, Cita $cita): RedirectResponse
    {
        $nuevo = CambiarEstadoCitaRequest::ACCIONES[$request->validated('accion')];

        $cambio = DB::transaction(function () use ($cita, $nuevo) {
            $cita = Cita::whereKey($cita->id)->lockForUpdate()->first();
            $anterior = $cita->estado;
            if (! in_array($nuevo, Cita::TRANSICIONES[$anterior] ?? [], true)) {
                return null;   // excepción 6: otro usuario cambió la cita primero
            }

            $cita->cambiarEstado($nuevo);
            HistorialCita::registrar($cita, $anterior, $nuevo);
            if (in_array($nuevo, ['Atendida', 'Inasistencia'], true)) {
                Notificacion::anularPendientes($cita);
            }

            return [Cita::obtener($cita->id), $anterior];
        });

        if ($cambio === null) {
            return back()->with('error', 'Transición no permitida: la cita ya cambió de estado (otro usuario la actualizó primero).');
        }
        [$cita, $anterior] = $cambio;

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CAMBIAR_ESTADO', 'Citas', $cita->id,
            "Cita {$cita->codigo}: $anterior → $nuevo");

        return back()->with('exito', "Cita {$cita->codigo}: $nuevo.");
    }

    /**
     * CU16 – Cancelar cita (pasos 1 y 2, mostrar): datos de la cita y formulario del motivo y la
     * confirmación. Si la cita no se puede cancelar (estado o plazo RN17), se muestra el motivo.
     */
    public function show(Request $request, Cita $cita): View
    {
        $cita = Cita::obtener($cita->id);
        $propio = $request->user()->paciente_id;
        abort_if($propio !== null && $cita->paciente_id !== $propio, 403);   // RN18

        return view('cancelarCita', [
            'cita'    => $cita,
            'enPlazo' => $propio === null
                || now()->addHours(Cita::PLAZO_PACIENTE_HORAS)->lte($cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio)),
        ]);
    }

    /**
     * CU16 – Cancelar cita (pasos 4 a 10, cancelar): dentro de una transacción verifica el estado, cancela
     * la cita conservando el registro, libera la franja, registra el historial, anula los recordatorios,
     * avisa al paciente y al médico y lo registra en la bitácora.
     */
    public function cancelar(CancelarCitaRequest $request, Cita $cita): RedirectResponse
    {
        $motivo = $request->validated('motivo_cancelacion');

        $cancelada = DB::transaction(function () use ($cita, $motivo) {
            // paso 5: se vuelve a leer la cita bloqueándola, por si otro usuario la cambió mientras tanto
            $cita = Cita::whereKey($cita->id)->lockForUpdate()->first();
            if (! in_array($cita->estado, Cita::VIGENTES, true)) {
                return null;
            }

            $anterior = $cita->estado;
            $cita->cancelar($motivo);                                         // pasos 6 y 7
            HistorialCita::registrar($cita, $anterior, 'Cancelada', $motivo); // paso 8

            // paso 9: anula los recordatorios y avisa al paciente y al médico (RF21)
            Notificacion::anularPendientes($cita);
            $cita = Cita::obtener($cita->id);
            $mensaje = "La cita {$cita->codigo} del {$cita->fecha->format('d/m/Y')} a las " . substr($cita->hora_inicio, 0, 5)
                . " ({$cita->especialidad->nombre}, {$cita->paciente->nombres} {$cita->paciente->apellidos}) fue cancelada. Motivo: $motivo";
            foreach ([$cita->paciente->usuario, $cita->medico->usuario] as $destinatario) {
                if ($destinatario) {
                    Notificacion::programar($destinatario, 'Cancelacion', $mensaje, $cita);
                }
            }

            return $cita;
        });

        if ($cancelada === null) {
            return back()->with('error', 'La cita cambió de estado mientras tanto y ya no se puede cancelar.');
        }

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CANCELAR', 'Citas', $cancelada->id,
            "Cancelación de la cita {$cancelada->codigo}. Motivo: $motivo");

        return redirect()->route('historialCitas.show', $cancelada)
            ->with('exito', "Cita {$cancelada->codigo} cancelada. La franja quedó libre.");
    }
}
