<?php

namespace App\Http\Requests\Clinica;

use App\Models\ExamenLaboratorio;
use App\Models\ParametroLaboratorio;
use App\Models\RangoReferencia;
use App\Models\ResultadoLaboratorio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * CU22 – Gestionar resultados de laboratorio (registrarResultados, paso 5): examen no revisado, fecha de la
 * muestra no futura ni anterior a la solicitud, parámetro existente y activo, valor numérico, parámetro no
 * repetido en el examen y rango de referencia para la edad del paciente en la fecha de la muestra (RN16).
 * Los valores llegan como valores[nombre del parámetro] = valor.
 */
class RegistrarResultadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta (Recepcionista)
    }

    public function rules(): array
    {
        return [
            'fecha_muestra' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'valores'       => ['required', 'array'],
            'valores.*'     => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_muestra.required'        => 'Indique la fecha de la muestra.',
            'fecha_muestra.before_or_equal' => 'La fecha de la muestra no puede ser futura.',
            'valores.required'              => 'Ingrese el valor de al menos un parámetro.',
        ];
    }

    /** Solo los parámetros con valor; la coma decimal se acepta. */
    protected function prepareForValidation(): void
    {
        $valores = collect((array) $this->input('valores', []))
            ->map(fn ($valor) => str_replace(',', '.', trim((string) $valor)))
            ->filter(fn ($valor) => $valor !== '')
            ->all();
        $this->merge(['valores' => $valores]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var ExamenLaboratorio $examen */
                $examen = $this->route('examen');
                if ($examen->estado === 'Revisado') {
                    $validator->errors()->add('examen', 'El examen ya está Revisado: no se modifica.');
                    return;
                }
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fecha = Carbon::createFromFormat('Y-m-d', $this->input('fecha_muestra'))->startOfDay();
                if ($fecha->lt($examen->fecha_solicitud->copy()->startOfDay())) {
                    $validator->errors()->add('fecha_muestra', 'La fecha de la muestra no puede ser anterior a la solicitud ('
                        . $examen->fecha_solicitud->format('d/m/Y') . ').');
                    return;
                }
                if ($examen->fecha_muestra && ! $examen->fecha_muestra->isSameDay($fecha)) {
                    $validator->errors()->add('fecha_muestra', 'El examen ya tiene la fecha de muestra ' . $examen->fecha_muestra->format('d/m/Y') . '.');
                    return;
                }

                $edad = (int) $examen->paciente->fecha_nacimiento->diffInYears($fecha);
                foreach ($this->input('valores') as $nombre => $valor) {
                    $parametro = ParametroLaboratorio::obtenerPorNombre((string) $nombre);
                    if (! $parametro || ! $parametro->estado) {
                        $validator->errors()->add('valores', "El parámetro $nombre no existe o está inactivo.");
                    } elseif (! is_numeric($valor)) {
                        $validator->errors()->add('valores', "El valor de $nombre debe ser numérico.");
                    } elseif (ResultadoLaboratorio::where('examen_id', $examen->id)->where('parametro_id', $parametro->id)->exists()) {
                        $validator->errors()->add('valores', "El parámetro $nombre ya está registrado en este examen.");
                    } elseif (! RangoReferencia::obtenerPorEdad($parametro->id, $edad)) {
                        $validator->errors()->add('valores', "No existe rango de referencia de $nombre para $edad años: no se registra el valor.");
                    }
                }
            },
        ];
    }
}
