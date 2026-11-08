<?php

namespace App\Http\Requests\Clinica;

use App\Models\Cita;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CU20 – Gestionar historia clínica (registrarAtencion): diagnóstico obligatorio, signos vitales dentro de
 * rangos permitidos y una sola atención por cita, para una cita Atendida del propio médico (D7). Los CHECK
 * de peso, talla y temperatura de la base de datos respaldan los rangos.
 */
class StoreAtencionRequest extends FormRequest
{
    /** Rangos permitidos de los signos vitales (más estrictos que los CHECK de la tabla). */
    public const RANGOS = [
        'peso_kg'       => [0.5, 400],
        'talla_cm'      => [20, 250],
        'temperatura_c' => [30, 45],
        'sistolica'     => [50, 260],
        'diastolica'    => [30, 160],
    ];

    /** RN18: solo un médico que atiende o atendió al paciente. */
    public function authorize(): bool
    {
        $medicoId = $this->user()?->medico_id;
        /** @var Paciente $paciente */
        $paciente = $this->route('paciente');

        return $medicoId !== null && Cita::where('paciente_id', $paciente->id)->where('medico_id', $medicoId)
            ->where('estado', '<>', 'Cancelada')->exists();
    }

    public function rules(): array
    {
        return [
            'cita_id' => ['required', 'integer'],
        ] + self::reglasClinicas();
    }

    /** Reglas del registro clínico (también las usa UpdateAtencionRequest). */
    public static function reglasClinicas(): array
    {
        [$pesoMin, $pesoMax] = self::RANGOS['peso_kg'];
        [$tallaMin, $tallaMax] = self::RANGOS['talla_cm'];
        [$tempMin, $tempMax] = self::RANGOS['temperatura_c'];

        return [
            'diagnostico'      => ['required', 'string', 'max:300'],
            'tratamiento'      => ['nullable', 'string', 'max:500'],
            'indicaciones'     => ['nullable', 'string', 'max:500'],
            'peso_kg'          => ['nullable', 'numeric', "between:$pesoMin,$pesoMax"],
            'talla_cm'         => ['nullable', 'numeric', "between:$tallaMin,$tallaMax"],
            'presion_arterial' => ['nullable', 'regex:/^\d{2,3}\/\d{2,3}$/'],
            'temperatura_c'    => ['nullable', 'numeric', "between:$tempMin,$tempMax"],
        ];
    }

    public function messages(): array
    {
        return [
            'diagnostico.required'   => 'El diagnóstico es obligatorio.',
            'diagnostico.max'        => 'El diagnóstico no puede superar los 300 caracteres.',
            'tratamiento.max'        => 'El tratamiento no puede superar los 500 caracteres.',
            'indicaciones.max'       => 'Las indicaciones no pueden superar los 500 caracteres.',
            'peso_kg.between'        => 'El peso debe estar entre :min y :max kg.',
            'talla_cm.between'       => 'La talla debe estar entre :min y :max cm.',
            'temperatura_c.between'  => 'La temperatura debe estar entre :min y :max °C.',
            'presion_arterial.regex' => 'La presión arterial debe tener el formato sistólica/diastólica, por ejemplo 120/80.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['diagnostico', 'tratamiento', 'indicaciones', 'presion_arterial'] as $campo) {
            $valor = trim((string) $this->input($campo));
            $this->merge([$campo => $valor === '' ? null : $valor]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->validarPresion($validator);
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $cita = Cita::find((int) $this->input('cita_id'));
                if (! $cita || $cita->paciente_id !== $this->route('paciente')->id || $cita->medico_id !== $this->user()->medico_id) {
                    $validator->errors()->add('cita_id', 'La cita no es de este paciente o no la atendió usted.');
                } elseif ($cita->estado !== 'Atendida') {
                    $validator->errors()->add('cita_id', "La cita está en estado {$cita->estado}: solo se registra la atención de una cita Atendida.");
                } elseif (Cita::existeAtencion($cita->id)) {
                    $validator->errors()->add('cita_id', 'La cita ya tiene un registro de atención: modifique el existente.');
                }
            },
        ];
    }

    /** La presión sistólica y diastólica deben estar en rango y la sistólica ser mayor. */
    protected function validarPresion(Validator $validator): void
    {
        if (! $this->filled('presion_arterial') || $validator->errors()->has('presion_arterial')) {
            return;
        }
        [$sistolica, $diastolica] = array_map('intval', explode('/', $this->input('presion_arterial')));
        if ($sistolica < self::RANGOS['sistolica'][0] || $sistolica > self::RANGOS['sistolica'][1]
            || $diastolica < self::RANGOS['diastolica'][0] || $diastolica > self::RANGOS['diastolica'][1]
            || $sistolica <= $diastolica) {
            $validator->errors()->add('presion_arterial', 'La presión arterial está fuera de los rangos permitidos.');
        }
    }
}
