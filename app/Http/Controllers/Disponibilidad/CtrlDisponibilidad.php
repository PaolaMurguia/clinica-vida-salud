<?php

namespace App\Http\Controllers\Disponibilidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Disponibilidad\EspecialidadesRequest;
use App\Http\Requests\Disponibilidad\FranjasRequest;
use App\Http\Requests\Disponibilidad\MedicosRequest;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Medico;
use App\Models\Paciente;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Control CtrlDisponibilidad: CU13 – Consultar disponibilidad (Recepcionista y Paciente).
 * Las franjas se calculan con los modelos, no con una función de la base de datos (A3).
 */
class CtrlDisponibilidad extends Controller
{
    /** Días hacia adelante en los que se busca la siguiente fecha con franjas libres. */
    private const DIAS_BUSQUEDA = 60;

    /**
     * CU13 – Consultar disponibilidad (paso 1, elegirEspecialidad): solo las especialidades
     * que corresponden a la edad del paciente (RN15).
     */
    public function especialidades(EspecialidadesRequest $request): View
    {
        $pacienteId = $request->validated('paciente_id');
        $paciente = $pacienteId ? Paciente::obtener((int) $pacienteId) : null;

        return view('disponibilidad', [
            'paso'           => 1,
            'paciente'       => $paciente,
            'especialidades' => $paciente ? Especialidad::listarPorEdad($paciente->fecha_nacimiento->age) : collect(),
        ]);
    }

    /**
     * CU13 – Consultar disponibilidad (pasos 2 y 3, elegirMedico): médicos activos de la especialidad.
     */
    public function medicos(MedicosRequest $request): View
    {
        $datos = $request->validated();
        $paciente = Paciente::obtener((int) $datos['paciente_id']);

        return view('disponibilidad', [
            'paso'         => 2,
            'paciente'     => $paciente,
            'especialidad' => Especialidad::listarPorEdad($paciente->fecha_nacimiento->age)->firstWhere('id', (int) $datos['especialidad_id']),
            'medicos'      => Medico::listarPorEspecialidad((int) $datos['especialidad_id']),
        ]);
    }

    /**
     * CU13 – Consultar disponibilidad (pasos 4 a 6, consultar): franjas libres y ocupadas del médico
     * en la fecha, sin datos de otros pacientes. Si la fecha está bloqueada se muestra el motivo; si el
     * médico no atiende ese día o no tiene franjas libres, se sugiere la siguiente fecha con disponibilidad.
     */
    public function franjas(FranjasRequest $request): View
    {
        $datos = $request->validated();
        $paciente = Paciente::obtener((int) $datos['paciente_id']);
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();
        $especialidad = Especialidad::listarPorEdad((int) $paciente->fecha_nacimiento->diffInYears($fecha))
            ->firstWhere('id', (int) $datos['especialidad_id']);
        $medico = Medico::listarPorEspecialidad($especialidad->id)->firstWhere('id', (int) $datos['medico_id']);

        // Cálculo de un día con los modelos, en el orden del diagrama:
        // estaBloqueada → obtenerDelDia → generarFranjas → listarOcupadas
        $calcularDia = function (Carbon $dia) use ($medico): array {
            $bloqueo = Bloqueo::estaBloqueada($medico->id, $dia);
            if ($bloqueo) {
                return ['bloqueo' => $bloqueo, 'atiende' => true, 'franjas' => []];
            }

            $horarios = Horario::obtenerDelDia($medico->id, $dia);
            if ($horarios->isEmpty()) {
                return ['bloqueo' => null, 'atiende' => false, 'franjas' => []];
            }

            $ocupadas = Cita::listarOcupadas($medico->id, $dia);   // solo horas, sin datos de pacientes
            $franjas = [];
            foreach ($horarios as $horario) {
                foreach ($horario->generarFranjas() as $franja) {
                    // paso 5: se descartan las franjas pasadas
                    if ($dia->copy()->setTimeFromTimeString($franja['inicio'])->lte(now())) {
                        continue;
                    }
                    // RN09: listarOcupadas ya excluye las canceladas
                    $ocupada = $ocupadas->contains(fn ($cita) => substr($cita->hora_inicio, 0, 5) < $franja['fin']
                        && substr($cita->hora_fin, 0, 5) > $franja['inicio']);
                    $franjas[] = $franja + ['estado' => $ocupada ? 'ocupada' : 'libre'];
                }
            }

            return ['bloqueo' => null, 'atiende' => true, 'franjas' => $franjas];
        };

        $resultado = $calcularDia($fecha);
        $hayLibres = fn (array $dia) => collect($dia['franjas'])->contains('estado', 'libre');

        // Excepciones 2 y 3: siguiente fecha con disponibilidad
        $siguiente = null;
        if (! $hayLibres($resultado)) {
            for ($i = 1; $i <= self::DIAS_BUSQUEDA && ! $siguiente; $i++) {
                $dia = $fecha->copy()->addDays($i);
                if ($hayLibres($calcularDia($dia))) {
                    $siguiente = $dia;
                }
            }
        }

        return view('disponibilidad', [
            'paso'         => 3,
            'paciente'     => $paciente,
            'especialidad' => $especialidad,
            'medicos'      => Medico::listarPorEspecialidad($especialidad->id),
            'medico'       => $medico,
            'fecha'        => $fecha,
            'resultado'    => $resultado,
            'siguiente'    => $siguiente,
        ]);
    }
}
