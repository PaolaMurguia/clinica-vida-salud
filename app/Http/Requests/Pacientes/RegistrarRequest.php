<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * CU10 – Registrarse como paciente: datos de la ficha, correo no usado, política RNF05,
 * aceptación de la política de privacidad y contacto de madre, padre o tutor si es menor (B10).
 */
class RegistrarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $menor = $this->esMenor();

        return [
            'ci'                    => ['required', 'string', 'max:20', 'regex:/^[0-9]{5,12}(-[0-9A-Za-z]{1,3})?$/'],
            'nombres'               => ['required', 'string', 'max:80'],
            'apellidos'             => ['required', 'string', 'max:80'],
            'fecha_nacimiento'      => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
            'sexo'                  => ['required', Rule::in(['M', 'F'])],
            'telefono'              => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'correo'                => ['required', 'string', 'email', 'max:120'],
            'clave'                 => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
            'acepta_privacidad'     => ['accepted'],
            'contacto_emerg_nombre' => [Rule::requiredIf($menor), 'nullable', 'string', 'max:100'],
            'contacto_emerg_parent' => array_filter([Rule::requiredIf($menor), 'nullable', 'string', 'max:40',
                $menor ? Rule::in(StorePacienteRequest::PARENTESCOS_MENOR) : null]),
            'contacto_emerg_tel'    => [Rule::requiredIf($menor), 'nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
        ];
    }

    public function messages(): array
    {
        $menor = 'Es menor de 18 años: indique el contacto de su padre, madre o tutor.';

        return [
            'acepta_privacidad.accepted'       => 'Debe aceptar la política de privacidad.',
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'contacto_emerg_nombre.required'   => $menor,
            'contacto_emerg_parent.required'   => $menor,
            'contacto_emerg_tel.required'      => $menor,
            'contacto_emerg_parent.in'         => 'Para un menor de 18 años el contacto debe ser su madre, padre o tutor.',
            'ci.regex'                         => 'El CI debe tener solo números (y, si corresponde, una extensión como 1234567-1B).',
        ];
    }

    /** CU10, excepción 3: correo ya registrado en otra cuenta. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('correo') && Usuario::buscarPorLogin($this->input('correo'))) {
                    $validator->errors()->add('correo', 'El correo ya está registrado en otra cuenta.');
                }
            },
        ];
    }

    protected function esMenor(): bool
    {
        try {
            return $this->filled('fecha_nacimiento')
                && Carbon::createFromFormat('Y-m-d', $this->input('fecha_nacimiento'))->age < 18;
        } catch (Throwable) {
            return false;
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ci'        => strtoupper(trim((string) $this->input('ci'))),
            'nombres'   => trim((string) $this->input('nombres')),
            'apellidos' => trim((string) $this->input('apellidos')),
            'correo'    => mb_strtolower(trim((string) $this->input('correo'))),
        ]);
    }
}
