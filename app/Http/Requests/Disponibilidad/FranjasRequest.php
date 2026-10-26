<?php

namespace App\Http\Requests\Disponibilidad;

use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU13 – Consultar disponibilidad (consultar): médico activo de la especialidad, fecha no pasada
 * y especialidad acorde a la edad del paciente en esa fecha (RN15).
 */
class FranjasRequest extends MedicosRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'medico_id' => ['required', 'integer'],
            'fecha'     => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'fecha.after_or_equal' => 'No se puede consultar una fecha pasada.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $paciente = Paciente::obtener((int) $this->input('paciente_id'));
                $edadEnLaFecha = $paciente->fecha_nacimiento->diffInYears(Carbon::createFromFormat('Y-m-d', $this->input('fecha')));
                $especialidadId = (int) $this->input('especialidad_id');

                if (! Especialidad::listarPorEdad((int) $edadEnLaFecha)->contains('id', $especialidadId)) {
                    $validator->errors()->add('especialidad_id', 'La especialidad no corresponde a la edad del paciente en esa fecha (RN15).');
                    return;
                }
                if (! Medico::listarPorEspecialidad($especialidadId)->contains('id', (int) $this->input('medico_id'))) {
                    $validator->errors()->add('medico_id', 'El médico no está activo o no atiende esa especialidad.');
                }
            },
        ];
    }
}
