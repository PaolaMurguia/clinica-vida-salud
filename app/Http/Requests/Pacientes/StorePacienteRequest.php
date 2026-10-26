<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Historia;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * CU08 – Gestionar pacientes (nuevo y editar): campos obligatorios, formatos, CI no registrado,
 * fecha de nacimiento no futura y contacto de padre, madre o tutor si es menor de 18 años (B10).
 */
class StorePacienteRequest extends FormRequest
{
    /** Parentescos válidos para el contacto de emergencia de un menor (CU08, excepción 4). */
    public const PARENTESCOS_MENOR = ['Madre', 'Padre', 'Tutor'];

    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
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
            'correo'                => ['nullable', 'string', 'email', 'max:120'],
            'direccion'             => ['nullable', 'string', 'max:150'],
            'contacto_emerg_nombre' => [Rule::requiredIf($menor), 'nullable', 'string', 'max:100'],
            'contacto_emerg_parent' => array_filter([Rule::requiredIf($menor), 'nullable', 'string', 'max:40',
                $menor ? Rule::in(self::PARENTESCOS_MENOR) : null]),
            'contacto_emerg_tel'    => [Rule::requiredIf($menor), 'nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            // se guarda en la historia clínica (B7); la recepción corrige el declarado por el paciente
            'tipo_sangre'           => ['nullable', Rule::in(Historia::TIPOS_SANGRE)],
        ];
    }

    public function messages(): array
    {
        $menor = 'El paciente es menor de 18 años: indique el contacto de su padre, madre o tutor.';

        return [
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'contacto_emerg_nombre.required'   => $menor,
            'contacto_emerg_parent.required'   => $menor,
            'contacto_emerg_tel.required'      => $menor,
            'contacto_emerg_parent.in'         => 'Para un menor de 18 años el contacto debe ser su madre, padre o tutor.',
            'ci.regex'                         => 'El CI debe tener solo números (y, si corresponde, una extensión como 1234567-1B).',
        ];
    }

    /** CU08, excepción 1: CI duplicado; se rechaza y se muestra el paciente existente. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('ci') || ! Paciente::existeCI($this->input('ci'), $this->route('paciente')?->id)) {
                    return;
                }
                $existente = Paciente::buscarPorCI($this->input('ci'));
                $validator->errors()->add('ci', "Ya existe el paciente {$existente->codigo} – {$existente->nombres} {$existente->apellidos} con ese CI.");
            },
        ];
    }

    /** Edad en años cumplidos a hoy; falso si la fecha todavía no es válida. */
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
            'correo'    => $this->filled('correo') ? mb_strtolower(trim($this->input('correo'))) : null,
        ]);
    }
}
