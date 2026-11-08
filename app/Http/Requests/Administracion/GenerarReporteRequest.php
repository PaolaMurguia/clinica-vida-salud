<?php

namespace App\Http\Requests\Administracion;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU23 – Generar reportes (generar y exportar): tipo de reporte y sus filtros. La fecha es obligatoria en los
 * reportes de un día; el rango, en los de un período (la fecha final no puede ser anterior a la inicial).
 */
class GenerarReporteRequest extends FormRequest
{
    public const TIPOS = [
        'citas_fecha'           => 'Citas programadas para una fecha',
        'citas_medico'          => 'Citas por médico',
        'citas_especialidad'    => 'Citas por especialidad',
        'citas_estado'          => 'Citas por estado',
        'pacientes_registrados' => 'Pacientes registrados',
        'atendidas_canceladas'  => 'Atendidas, canceladas e inasistencias',
        'agenda_diaria'         => 'Agenda diaria de cada médico',
        'ingresos_metodo'       => 'Ingresos por método de pago',
    ];

    /** Reportes de un solo día; los demás piden un período. */
    public const DE_UN_DIA = ['citas_fecha', 'agenda_diaria'];

    /** Rango máximo de un período, en días. */
    public const MAX_DIAS = 366;

    public function authorize(): bool
    {
        return true;   // la ruta exige el rol Administrador (RN12)
    }

    public function rules(): array
    {
        $deUnDia = in_array($this->input('tipo'), self::DE_UN_DIA, true);

        return [
            'tipo'            => ['required', Rule::in(array_keys(self::TIPOS))],
            'fecha'           => [$deUnDia ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'desde'           => [$deUnDia ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'hasta'           => [$deUnDia ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'medico_id'       => ['nullable', 'required_if:tipo,citas_medico', 'integer', Rule::exists('medico', 'id')],
            'especialidad_id' => ['nullable', 'required_if:tipo,citas_especialidad', 'integer', Rule::exists('especialidad', 'id')],
            'estado'          => ['nullable', Rule::in(Cita::ESTADOS)],
            'formato'         => ['nullable', 'in:csv,pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'               => 'Elija el tipo de reporte.',
            'tipo.in'                     => 'El tipo de reporte no es válido.',
            'fecha.required'              => 'Este reporte exige la fecha.',
            'desde.required'              => 'Indique la fecha inicial del período.',
            'hasta.required'              => 'Indique la fecha final del período.',
            'medico_id.required_if'       => 'Elija el médico.',
            'especialidad_id.required_if' => 'Elija la especialidad.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('desde') || ! $this->filled('hasta') || $validator->errors()->hasAny(['desde', 'hasta'])) {
                    return;
                }
                if ($this->input('hasta') < $this->input('desde')) {
                    $validator->errors()->add('hasta', 'Rango de fechas inválido: la fecha final es anterior a la inicial.');
                } elseif (now()->parse($this->input('desde'))->diffInDays(now()->parse($this->input('hasta'))) > self::MAX_DIAS) {
                    $validator->errors()->add('hasta', 'El período no puede superar ' . self::MAX_DIAS . ' días.');
                }
            },
        ];
    }
}
