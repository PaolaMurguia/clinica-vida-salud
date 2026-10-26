<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU11 – Gestionar perfil del paciente (guardar): contacto, contacto de emergencia y preferencias.
 * CI, nombres, apellidos y fecha de nacimiento solo los cambia la recepción.
 */
class UpdatePerfilRequest extends FormRequest
{
    private const SOLO_RECEPCION = 'Solo la recepción puede cambiar el CI, los nombres o la fecha de nacimiento.';

    public function authorize(): bool
    {
        return $this->user()?->paciente_id !== null;
    }

    public function rules(): array
    {
        $menor = $this->user()->paciente->fecha_nacimiento->age < 18;

        return [
            // CU11, excepción 2
            'ci'                    => ['prohibited'],
            'nombres'               => ['prohibited'],
            'apellidos'             => ['prohibited'],
            'fecha_nacimiento'      => ['prohibited'],
            'telefono'              => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'correo'                => ['required', 'string', 'email', 'max:120'],
            'direccion'             => ['nullable', 'string', 'max:150'],
            'contacto_emerg_nombre' => [Rule::requiredIf($menor), 'nullable', 'string', 'max:100'],
            'contacto_emerg_parent' => array_filter([Rule::requiredIf($menor), 'nullable', 'string', 'max:40',
                $menor ? Rule::in(StorePacienteRequest::PARENTESCOS_MENOR) : null]),
            'contacto_emerg_tel'    => [Rule::requiredIf($menor), 'nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'notif_correo'          => ['boolean'],
            'notif_alerta'          => ['boolean'],
            'acepta_privacidad'     => ['boolean'],
        ];
    }

    public function messages(): array
    {
        $menor = 'Es menor de 18 años: indique el contacto de su padre, madre o tutor.';

        return [
            'ci.prohibited'                  => self::SOLO_RECEPCION,
            'nombres.prohibited'             => self::SOLO_RECEPCION,
            'apellidos.prohibited'           => self::SOLO_RECEPCION,
            'fecha_nacimiento.prohibited'    => self::SOLO_RECEPCION,
            'contacto_emerg_nombre.required' => $menor,
            'contacto_emerg_parent.required' => $menor,
            'contacto_emerg_tel.required'    => $menor,
            'contacto_emerg_parent.in'       => 'Para un menor de 18 años el contacto debe ser su madre, padre o tutor.',
        ];
    }

    /** CU11, excepción 1: correo en uso por otra cuenta. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $existente = $this->filled('correo') ? Usuario::buscarPorLogin($this->input('correo')) : null;
                if ($existente && $existente->id !== $this->user()->id) {
                    $validator->errors()->add('correo', 'El correo ya está en uso por otra cuenta.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'correo'            => mb_strtolower(trim((string) $this->input('correo'))),
            'notif_correo'      => $this->boolean('notif_correo'),
            'notif_alerta'      => $this->boolean('notif_alerta'),
            'acepta_privacidad' => $this->boolean('acepta_privacidad'),
        ]);
    }
}
