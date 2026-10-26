<?php

namespace App\Http\Requests\Administracion;

use App\Models\Horario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU07 – Definir horarios de atención (agregar): médico activo, hora final posterior a la inicial,
 * franjas completas de 10 a 120 minutos y sin superposición con otro horario del mismo día.
 */
class StoreHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medico_id'         => ['required', 'integer', Rule::exists('medico', 'id')->where('estado', true)],
            'dia_semana'        => ['required', 'integer', 'between:1,7'],
            'hora_inicio'       => ['required', 'date_format:H:i'],
            'hora_fin'          => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'duracion_cita_min' => ['required', 'integer', 'between:10,120'],
        ];
    }

    public function messages(): array
    {
        return [
            'medico_id.exists'          => 'El médico no existe o está inactivo.',
            'hora_fin.after'            => 'La hora final debe ser posterior a la hora inicial.',
            'duracion_cita_min.between' => 'La duración de la franja debe estar entre 10 y 120 minutos.',
        ];
    }

    /** CU07, paso 3: franjas completas y sin superposición (Horario::seSuperpone). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $minutos = Carbon::createFromFormat('H:i', $this->input('hora_inicio'))
                    ->diffInMinutes(Carbon::createFromFormat('H:i', $this->input('hora_fin')));

                if ($minutos % (int) $this->input('duracion_cita_min') !== 0) {
                    $validator->errors()->add('hora_fin', "El rango de $minutos minutos no se divide en franjas completas de {$this->input('duracion_cita_min')} minutos.");
                    return;
                }
                if (Horario::seSuperpone((int) $this->input('medico_id'), (int) $this->input('dia_semana'), $this->input('hora_inicio'), $this->input('hora_fin'))) {
                    $validator->errors()->add('hora_inicio', 'El horario se superpone con otro horario activo del médico ese día.');
                }
            },
        ];
    }
}
