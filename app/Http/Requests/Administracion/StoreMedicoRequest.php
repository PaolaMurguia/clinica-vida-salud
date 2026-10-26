<?php

namespace App\Http\Requests\Administracion;

use App\Models\Medico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU06 – Gestionar médicos (nuevo y editar): CI y registro profesional únicos (A6),
 * correo válido y al menos una especialidad activa.
 */
class StoreMedicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $medico = $this->route('medico');
        // al editar se aceptan también las especialidades ya asignadas aunque hoy estén inactivas
        $asignadas = $medico ? $medico->especialidades()->pluck('especialidad.id')->all() : [];

        return [
            'ci'                => ['required', 'string', 'max:20', 'regex:/^[0-9]{5,12}(-[0-9A-Za-z]{1,3})?$/'],
            'nro_registro_prof' => ['required', 'string', 'max:30', Rule::unique('medico', 'nro_registro_prof')->ignore($medico?->id)],
            'nombres'           => ['required', 'string', 'max:80'],
            'apellidos'         => ['required', 'string', 'max:80'],
            'telefono'          => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'correo'            => ['nullable', 'string', 'email', 'max:120'],
            'especialidades'    => ['required', 'array', 'min:1'],
            // agrupado: id = ? AND (estado OR id IN asignadas)
            'especialidades.*'  => ['integer', 'distinct', Rule::exists('especialidad', 'id')->where(
                fn ($q) => $q->where(fn ($w) => $w->where('estado', true)->orWhereIn('id', $asignadas))
            )],
        ];
    }

    public function messages(): array
    {
        return [
            'especialidades.required'   => 'Seleccione al menos una especialidad.',
            'especialidades.min'        => 'Seleccione al menos una especialidad.',
            'nro_registro_prof.unique'  => 'El número de registro profesional ya está registrado.',
            'ci.regex'                  => 'El CI debe tener solo números (y, si corresponde, una extensión como 1234567-1B).',
        ];
    }

    /** CU06, paso 4: el CI no puede estar registrado en otro médico. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('ci') && Medico::existeCI($this->input('ci'), $this->route('medico')?->id)) {
                    $validator->errors()->add('ci', 'El CI ya está registrado en otro médico.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ci'                => strtoupper(trim((string) $this->input('ci'))),
            'nro_registro_prof' => strtoupper(trim((string) $this->input('nro_registro_prof'))),
            'nombres'           => trim((string) $this->input('nombres')),
            'apellidos'         => trim((string) $this->input('apellidos')),
            'correo'            => $this->filled('correo') ? mb_strtolower(trim($this->input('correo'))) : null,
        ]);
    }
}
