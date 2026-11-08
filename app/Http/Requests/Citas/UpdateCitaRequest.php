<?php

namespace App\Http\Requests\Citas;

use App\Models\Cita;
use Illuminate\Validation\Validator;

/**
 * CU15 – Reprogramar cita (reprogramar): nueva fecha y franja del mismo médico. Verifica RN18, el estado,
 * el plazo del paciente (RN17) y que haya cambios; luego aplica las reglas de la franja de CU14
 * (CreateCitaRequest::validarFranja) sin contar la propia cita.
 */
class UpdateCitaRequest extends CreateCitaRequest
{
    public function rules(): array
    {
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
            'fecha.required'       => 'Elija la nueva fecha.',
            'hora_inicio.required' => 'Elija una nueva franja libre.',
        ];
    }

    /** Paso 7: el paciente, el médico y la especialidad no cambian; se toman de la cita. */
    protected function prepareForValidation(): void
    {
        /** @var Cita $cita */
        $cita = $this->route('cita');
        $this->merge([
            'paciente_id'     => $cita->paciente_id,
            'medico_id'       => $cita->medico_id,
            'especialidad_id' => $cita->especialidad_id,
        ]);
    }

    protected function citaExcluida(): ?int
    {
        return $this->route('cita')->id;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                /** @var Cita $cita */
                $cita = $this->route('cita');
                $propio = $this->user()->paciente_id;

                if ($propio !== null && $cita->paciente_id !== $propio) {
                    $validator->errors()->add('cita', 'Solo puede reprogramar sus propias citas (RN18).');
                    return;
                }
                if (! in_array($cita->estado, Cita::VIGENTES, true)) {
                    $validator->errors()->add('cita', "La cita está en estado {$cita->estado}: no se reprograma; registre una nueva cita (CU14).");
                    return;
                }
                $inicioActual = $cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio);
                if ($propio !== null && now()->addHours(Cita::PLAZO_PACIENTE_HORAS)->gt($inicioActual)) {
                    $validator->errors()->add('cita', 'Faltan menos de ' . Cita::PLAZO_PACIENTE_HORAS
                        . ' horas para la cita: para reprogramarla comuníquese con la recepción (RN17).');
                    return;
                }
                if ($cita->fecha->toDateString() === $this->input('fecha') && substr($cita->hora_inicio, 0, 5) === $this->input('hora_inicio')) {
                    $validator->errors()->add('hora_inicio', 'La nueva fecha y hora son iguales a las actuales: no hay cambios.');
                    return;
                }

                $this->validarFranja($validator);
            },
        ];
    }
}
