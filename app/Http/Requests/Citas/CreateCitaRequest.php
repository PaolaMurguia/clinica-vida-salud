<?php

namespace App\Http\Requests\Citas;

use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Medico;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * CU14 – Programar cita (mostrar): franja elegida en la disponibilidad (CU13).
 * Verifica en el servidor las reglas del paso 5 (RN03 a RN09, RN13, RN15, RN18); la transacción del
 * controlador vuelve a comprobar el conflicto y la base de datos lo respalda (trg_validar_cita, ex_cita_no_solapada).
 * CU15 reutiliza estas reglas (UpdateCitaRequest) excluyendo la propia cita.
 */
class CreateCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta; RN18 se valida abajo
    }

    public function rules(): array
    {
        // Comprobante de una cita ya registrada (paso 9)
        if ($this->has('cita')) {
            return ['cita' => ['required', 'integer']];
        }

        return [
            'paciente_id'     => ['required', 'integer'],
            'especialidad_id' => ['required', 'integer'],
            'medico_id'       => ['required', 'integer'],
            'fecha'           => ['required', 'date_format:Y-m-d'],
            'hora_inicio'     => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Seleccione primero un paciente.',
            'hora_inicio.required' => 'Elija una franja libre.',
        ];
    }

    /** RN18: el paciente siempre programa para sí mismo. */
    protected function prepareForValidation(): void
    {
        $propio = $this->user()?->paciente_id;
        if ($propio !== null && ! $this->filled('paciente_id')) {
            $this->merge(['paciente_id' => $propio]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->has('cita')) {
                    return;
                }
                $this->validarFranja($validator);
            },
        ];
    }

    /** Id de la cita que se reprograma (CU15); null al programar una nueva. */
    protected function citaExcluida(): ?int
    {
        return null;
    }

    /** Reglas del paso 5 de CU14 (y paso 6 de CU15), en el orden del flujo. */
    protected function validarFranja(Validator $validator): void
    {
        $propio = $this->user()?->paciente_id;
        $pacienteId = (int) $this->input('paciente_id');
        if ($propio !== null && $pacienteId !== $propio) {
            $validator->errors()->add('paciente_id', 'Solo puede programar citas para usted (RN18).');
            return;
        }

        $paciente = Paciente::find($pacienteId);
        if (! $paciente || ! $paciente->estado) {
            $validator->errors()->add('paciente_id', 'El paciente no existe o está inactivo (RN03).');
            return;
        }

        $medico = Medico::with('especialidades')->find((int) $this->input('medico_id'));
        if (! $medico || ! $medico->estado) {
            $validator->errors()->add('medico_id', 'El médico no existe o está inactivo y no recibe nuevas citas (RN04).');
            return;
        }
        $especialidadId = (int) $this->input('especialidad_id');
        if (! $medico->especialidades->contains('id', $especialidadId)) {
            $validator->errors()->add('especialidad_id', 'El médico no atiende la especialidad seleccionada.');
            return;
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $this->input('fecha'))->startOfDay();
        $edad = (int) $paciente->fecha_nacimiento->diffInYears($fecha);
        if (! Especialidad::listarPorEdad($edad)->contains('id', $especialidadId)) {
            $validator->errors()->add('especialidad_id', "La especialidad no está disponible o no corresponde a la edad del paciente en esa fecha ($edad años) (RN15).");
            return;
        }

        $horaInicio = $this->input('hora_inicio');
        if ($fecha->copy()->setTimeFromTimeString($horaInicio)->lte(now())) {
            $validator->errors()->add('fecha', 'No se pueden programar citas en fechas u horas pasadas (RN06).');
            return;
        }

        $bloqueo = Bloqueo::estaBloqueada($medico->id, $fecha);
        if ($bloqueo) {
            $validator->errors()->add('fecha', "La fecha está bloqueada: {$bloqueo->motivo}.");
            return;
        }

        $franja = Horario::obtenerDelDia($medico->id, $fecha)
            ->flatMap(fn (Horario $horario) => $horario->generarFranjas())
            ->firstWhere('inicio', $horaInicio);
        if (! $franja) {
            $validator->errors()->add('hora_inicio', 'La franja no pertenece al horario del médico o no respeta la duración configurada (RN05, RN08).');
            return;
        }

        if (Cita::existeConflicto($medico->id, $fecha, $franja['inicio'], $franja['fin'], $this->citaExcluida())) {
            $validator->errors()->add('hora_inicio', 'La franja ya fue ocupada por otra cita. Elija otra franja libre (RN07).');
        }
    }
}
