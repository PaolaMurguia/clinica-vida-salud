<?php

namespace App\Http\Requests\Clinica;

use App\Models\RegistroAtencion;

/**
 * CU20 – Gestionar historia clínica (modificar la atención): el médico modifica solo su propio registro (D7)
 * con las mismas reglas clínicas.
 */
class UpdateAtencionRequest extends StoreAtencionRequest
{
    public function authorize(): bool
    {
        /** @var RegistroAtencion $registro */
        $registro = $this->route('registro');

        return $this->user()?->medico_id !== null && $registro->cita->medico_id === $this->user()->medico_id;
    }

    public function rules(): array
    {
        return self::reglasClinicas();
    }

    public function after(): array
    {
        return [
            fn ($validator) => $this->validarPresion($validator),
        ];
    }
}
