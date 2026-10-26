<?php

namespace App\Http\Requests\Administracion;

use App\Models\Especialidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CU05 – Gestionar especialidades (nueva y editar): nombre único normalizado, costo no negativo
 * y rango de edad válido (RN15).
 */
class StoreEspecialidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'         => ['required', 'string', 'max:60'],
            'descripcion'    => ['nullable', 'string', 'max:150'],
            'costo_consulta' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'edad_minima'    => ['nullable', 'integer', 'between:0,120'],
            // se compara solo si hay edad mínima, como el CHECK ck_especialidad_edades
            'edad_maxima'    => array_filter(['nullable', 'integer', 'between:0,120', $this->filled('edad_minima') ? 'gte:edad_minima' : null]),
        ];
    }

    public function messages(): array
    {
        return [
            'edad_maxima.gte'    => 'Rango de edad inválido: la edad mínima es mayor que la máxima.',
            'costo_consulta.min' => 'El costo de la consulta no puede ser negativo.',
        ];
    }

    /** CU05, paso 4: no puede existir otra especialidad con el mismo nombre normalizado. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('nombre') && Especialidad::existeNombre($this->input('nombre'), $this->route('especialidad')?->id)) {
                    $validator->errors()->add('nombre', 'Ya existe una especialidad con ese nombre.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre'      => trim(preg_replace('/\s+/u', ' ', (string) $this->input('nombre'))),
            'edad_minima' => $this->filled('edad_minima') ? $this->input('edad_minima') : null,
            'edad_maxima' => $this->filled('edad_maxima') ? $this->input('edad_maxima') : null,
        ]);
    }
}
