<?php

namespace App\Http\Requests\Citas;

/**
 * CU14 – Programar cita (programar): las reglas de la franja (CreateCitaRequest) más la modalidad
 * obligatoria y el motivo breve opcional (paso 3).
 */
class StoreCitaRequest extends CreateCitaRequest
{
    public function rules(): array
    {
        return [
            'paciente_id'     => ['required', 'integer'],
            'especialidad_id' => ['required', 'integer'],
            'medico_id'       => ['required', 'integer'],
            'fecha'           => ['required', 'date_format:Y-m-d'],
            'hora_inicio'     => ['required', 'date_format:H:i'],
            'modalidad'       => ['required', 'in:Presencial,Virtual'],
            'motivo'          => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'modalidad.required' => 'Elija la modalidad de la cita.',
            'modalidad.in'       => 'La modalidad debe ser presencial o virtual.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->isEmpty()) {
                    $this->validarFranja($validator);
                }
            },
        ];
    }
}
