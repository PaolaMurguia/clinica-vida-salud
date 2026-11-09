<?php

namespace App\Http\Requests\Clinica;

use App\Models\Antecedente;
use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU20 – Gestionar historia clínica (agregarAntecedente): el médico que atiende o atendió al paciente
 * agrega antecedentes (alergia, enfermedad crónica, medicamento, cirugía, familiar u otro). No se eliminan (A5).
 */
class StoreAntecedenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicoId = $this->user()?->medico_id;

        return $medicoId !== null && Cita::where('paciente_id', $this->route('paciente')->id)->where('medico_id', $medicoId)
            ->where('estado', '<>', 'Cancelada')->exists();
    }

    public function rules(): array
    {
        return [
            'tipo'        => ['required', Rule::in(Antecedente::TIPOS)],
            'descripcion' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'        => 'Elija el tipo de antecedente.',
            'descripcion.required' => 'Describa el antecedente.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['descripcion' => trim((string) $this->input('descripcion'))]);
    }
}
