<?php

namespace App\Http\Requests\Disponibilidad;

use App\Models\Especialidad;
use App\Models\Paciente;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU13 – Consultar disponibilidad (elegir médico): la especialidad debe corresponder a la edad del paciente (RN15).
 */
class MedicosRequest extends EspecialidadesRequest
{
    public function rules(): array
    {
        return [
            'paciente_id'     => ['required', 'integer', Rule::exists('paciente', 'id')->where('estado', true)],
            'especialidad_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'paciente_id.required' => 'Seleccione primero un paciente.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $edad = Paciente::obtener((int) $this->input('paciente_id'))->fecha_nacimiento->age;
                if (! Especialidad::listarPorEdad($edad)->contains('id', (int) $this->input('especialidad_id'))) {
                    $validator->errors()->add('especialidad_id', 'La especialidad no está disponible para la edad del paciente.');
                }
            },
        ];
    }
}
