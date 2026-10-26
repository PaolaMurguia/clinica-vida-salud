<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Seguro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CU12 – Registrar seguro del paciente: póliza no registrada en la misma aseguradora,
 * porcentaje entre 0 y 100 y fechas coherentes. El paciente debe estar activo.
 */
class StoreSeguroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aseguradora'          => ['required', 'string', 'max:80'],
            'nro_poliza'           => ['required', 'string', 'max:40'],
            'tipo_cobertura'       => ['required', 'string', 'max:40'],
            'porcentaje_cobertura' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'fecha_inicio'         => ['required', 'date_format:Y-m-d'],
            'fecha_fin'            => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'porcentaje_cobertura.between' => 'El porcentaje de cobertura debe estar entre 0 y 100.',
            'fecha_fin.after_or_equal'     => 'La fecha de fin no puede ser anterior a la de inicio.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Precondición: el paciente está registrado y activo
                if (! $this->route('paciente')->estado) {
                    $validator->errors()->add('aseguradora', 'El paciente está inactivo: no se le puede registrar un seguro.');
                    return;
                }
                // CU12, excepción 1: la póliza no puede repetirse en la misma aseguradora (tampoco al renovar)
                $duplicada = $this->filled(['aseguradora', 'nro_poliza']) && Seguro::whereRaw('lower(aseguradora) = lower(?)', [$this->input('aseguradora')])
                    ->whereRaw('lower(nro_poliza) = lower(?)', [$this->input('nro_poliza')])
                    ->exists();
                if ($duplicada) {
                    $validator->errors()->add('nro_poliza', 'La póliza ya está registrada en esa aseguradora. Para renovarla, registre el número de la nueva póliza.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'aseguradora' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('aseguradora'))),
            'nro_poliza'  => strtoupper(trim((string) $this->input('nro_poliza'))),
        ]);
    }
}
