<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Historia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU11 – Gestionar perfil del paciente (declarar antecedente): tipo de sangre, alergias,
 * enfermedades crónicas, medicamentos y antecedentes familiares.
 */
class StoreAntecedenteRequest extends FormRequest
{
    /** Tipos que el paciente puede declarar (CU11, paso 4). */
    public const TIPOS_PACIENTE = ['Alergia', 'Enfermedad cronica', 'Medicamento', 'Familiar'];

    public function authorize(): bool
    {
        return $this->user()?->paciente_id !== null;
    }

    public function rules(): array
    {
        return [
            'tipo_sangre' => ['nullable', 'required_without:tipo', Rule::in(Historia::TIPOS_SANGRE)],
            'tipo'        => ['nullable', 'required_with:descripcion', Rule::in(self::TIPOS_PACIENTE)],
            'descripcion' => ['nullable', 'required_with:tipo', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_sangre.required_without' => 'Indique su tipo de sangre o un antecedente.',
        ];
    }

    /** El tipo de sangre se declara una vez; si ya está registrado, solo la clínica lo cambia. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('tipo_sangre') && Historia::obtener($this->user()->paciente_id)->tipo_sangre !== null) {
                    $validator->errors()->add('tipo_sangre', 'El tipo de sangre ya está registrado; solo la clínica puede cambiarlo.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['descripcion' => $this->filled('descripcion') ? trim($this->input('descripcion')) : null]);
    }
}
