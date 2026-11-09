<?php

namespace App\Http\Requests\Pagos;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CU25 – Registrar pago de consulta (mostrar): la cita se abre por su id (desde la agenda o el historial)
 * o por su código (CM-0001). Excepción: cita inexistente.
 */
class CreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'cita'   => ['nullable', 'integer'],
            'codigo' => ['nullable', 'string', 'max:12'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('codigo')) {
            $this->merge(['codigo' => strtoupper(trim((string) $this->input('codigo')))]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if ($this->filled('codigo') && ! Cita::where('codigo', $this->input('codigo'))->exists()) {
                    $validator->errors()->add('codigo', "La cita {$this->input('codigo')} no existe.");
                } elseif ($this->filled('cita') && ! Cita::whereKey((int) $this->input('cita'))->exists()) {
                    $validator->errors()->add('cita', 'La cita no existe.');
                }
            },
        ];
    }
}
