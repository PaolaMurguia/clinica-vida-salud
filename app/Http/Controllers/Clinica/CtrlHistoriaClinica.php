<?php

namespace App\Http\Controllers\Clinica;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinica\StoreAntecedenteRequest;
use App\Http\Requests\Clinica\StoreAtencionRequest;
use App\Http\Requests\Clinica\UpdateAtencionRequest;
use App\Models\Antecedente;
use App\Models\Cita;
use App\Models\ExamenLaboratorio;
use App\Models\Historia;
use App\Models\Paciente;
use App\Models\RegistroAtencion;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlHistoriaClinica: CU20 – Gestionar historia clínica (Médico y Paciente) y
 * CU21 – Dictar registro clínico por voz. La recepción no accede a los diagnósticos (RN18).
 */
class CtrlHistoriaClinica extends Controller
{
    /**
     * CU20 – Gestionar historia clínica (pasos 1, 2 y 9, mostrar): tipo de sangre, antecedentes, atenciones y
     * exámenes; crea la historia si no existe (B7). El médico solo abre la de un paciente que atiende o atendió;
     * el paciente solo la suya y sin poder modificarla (RN18).
     */
    public function show(Request $request, ?Paciente $paciente = null): View
    {
        $usuario = $request->user();
        if ($usuario->paciente_id !== null) {
            abort_if($paciente && $paciente->id !== $usuario->paciente_id, 403);
            $paciente = Paciente::obtener($usuario->paciente_id);
        } else {
            abort_if(! $paciente, 404);
            $atiende = Cita::where('paciente_id', $paciente->id)->where('medico_id', $usuario->medico_id)
                ->where('estado', '<>', 'Cancelada')->exists();
            abort_unless($atiende, 403);
            $paciente = Paciente::obtener($paciente->id);
        }
        $esMedico = $usuario->medico_id !== null;
        $historia = Historia::obtener($paciente->id);

        // Citas atendidas por este médico sin registro (paso 3) y registro a modificar (?editar=, B1)
        $pendientes = $esMedico
            ? Cita::with('especialidad')->where('paciente_id', $paciente->id)->where('medico_id', $usuario->medico_id)
                ->where('estado', 'Atendida')->whereDoesntHave('registroAtencion')
                ->orderByDesc('fecha')->get()
            : collect();
        $editar = $esMedico && $request->filled('editar')
            ? RegistroAtencion::with('cita')->whereKey((int) $request->query('editar'))
                ->whereHas('cita', fn ($q) => $q->where('medico_id', $usuario->medico_id)->where('paciente_id', $paciente->id))->first()
            : null;

        return view('historiaClinica', [
            'paciente'     => $paciente,
            'historia'     => $historia,
            'antecedentes' => Antecedente::listar($historia->id),
            'atenciones'   => RegistroAtencion::listarPorPaciente($paciente->id),
            'examenes'     => ExamenLaboratorio::listarPorPaciente($paciente->id, ! $esMedico),
            'esMedico'     => $esMedico,
            'pendientes'   => $pendientes,
            'citaElegida'  => (int) $request->query('cita'),
            'editar'       => $editar,
        ]);
    }

    /**
     * CU20 – Gestionar historia clínica (pasos 3 a 5 y 8, registrarAtencion): guarda el único registro de
     * atención de la cita atendida y actualiza la fecha de modificación de la historia clínica.
     * CU21 – Dictar registro clínico por voz (paso 5): el texto dictado se valida igual que el escrito y el
     * registro queda marcado como dictado por voz (el audio no se guarda).
     */
    public function storeAtencion(StoreAtencionRequest $request, Paciente $paciente): RedirectResponse
    {
        $datos = $request->validated();

        $registro = DB::transaction(function () use ($datos, $paciente) {
            $registro = RegistroAtencion::crear($datos);
            Historia::obtener($paciente->id)->update(['fecha_actualizacion' => now()]);

            return $registro;
        });

        $codigo = Cita::obtener($registro->cita_id)->codigo;
        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Historia clínica', $registro->id,
            "Registro de atención de la cita $codigo del paciente {$paciente->codigo}");
        if ($registro->dictado_voz) {
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'DICTADO_VOZ', 'Historia clínica', $registro->id,
                "Registro de atención de la cita $codigo dictado por voz");
        }

        return redirect()->route('historiaClinica.show', $paciente)->with('exito', 'Atención registrada.');
    }

    /**
     * CU20 – Gestionar historia clínica (excepción 3, modificar la atención): el médico modifica su propio
     * registro; el cambio queda en la bitácora (D7).
     */
    public function updateAtencion(UpdateAtencionRequest $request, RegistroAtencion $registro): RedirectResponse
    {
        $cita = Cita::obtener($registro->cita_id);

        DB::transaction(function () use ($registro, $request, $cita) {
            // CU21: si alguna vez se dictó, el registro sigue marcado como dictado por voz
            $datos = $request->validated();
            $datos['dictado_voz'] = $registro->dictado_voz || ! empty($datos['dictado_voz']);
            $registro->actualizar($datos);
            Historia::obtener($cita->paciente_id)->update(['fecha_actualizacion' => now()]);
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'MODIFICAR', 'Historia clínica', $registro->id,
            "Modificación del registro de atención de la cita {$cita->codigo}");

        return redirect()->route('historiaClinica.show', $cita->paciente_id)->with('exito', 'Atención modificada.');
    }

    /**
     * CU20 – Gestionar historia clínica (paso 6, agregarAntecedente): el médico agrega un antecedente; no se
     * eliminan (A5). El trigger trg_tocar_historia actualiza la fecha de la historia.
     */
    public function storeAntecedente(StoreAntecedenteRequest $request, Paciente $paciente): RedirectResponse
    {
        $historia = Historia::obtener($paciente->id);
        $antecedente = Antecedente::crear($request->validated() + [
            'historia_clinica_id' => $historia->id,
            'origen'              => Antecedente::ORIGEN_MEDICO,
            'usuario_id'          => $request->user()->id,
        ]);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Historia clínica', $antecedente->id,
            "Antecedente ({$antecedente->tipo}) del paciente {$paciente->codigo}");

        return redirect()->route('historiaClinica.show', $paciente)->with('exito', 'Antecedente agregado.');
    }
}
