<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Entidad EXAMEN_LABORATORIO: análisis solicitado por un médico (RN20). El código EX-00001 lo genera la base de datos.
 */
class ExamenLaboratorio extends Model
{
    protected $table = 'examen_laboratorio';
    public $timestamps = false;

    protected $fillable = [
        'paciente_id', 'medico_id', 'cita_id', 'tipo_examen', 'fecha_muestra', 'observacion',
        'estado', 'usuario_registro_id', 'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_muestra'   => 'date',
            'fecha_registro'  => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_registro_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoLaboratorio::class, 'examen_id');
    }

    /** CU22: registra la solicitud en estado Solicitado y recupera el código EX-00001 que asignó la base de datos. */
    public static function crear(array $datos): self
    {
        return self::create($datos + ['estado' => 'Solicitado'])->refresh();
    }

    /**
     * CU22: busca exámenes por código o por CI, nombre o apellido del paciente, con filtro opcional de estado;
     * sin criterio, devuelve todos los del estado indicado.
     */
    public static function buscar(string $criterio = '', ?string $estado = null): Collection
    {
        $patron = '%' . trim($criterio) . '%';

        return self::with(['paciente', 'medico'])
            ->when(trim($criterio) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('codigo', 'ILIKE', $patron)
                ->orWhereHas('paciente', fn ($p) => $p->where('ci', 'ILIKE', $patron)
                    ->orWhereRaw("unaccent(nombres || ' ' || apellidos) ILIKE unaccent(?)", [$patron])
                    ->orWhereRaw("unaccent(apellidos || ' ' || nombres) ILIKE unaccent(?)", [$patron]))))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha_solicitud')
            ->limit(50)
            ->get();
    }

    /** CU22: examen con su paciente y su médico solicitante. */
    public static function obtener(int $id): self
    {
        return self::with(['paciente', 'medico'])->findOrFail($id);
    }

    /** CU22: guarda la fecha de la muestra y quién registró; el examen pasa a Registrado (ck_examen_registrado). */
    public function registrarMuestra(Carbon $fechaMuestra, int $usuarioId): void
    {
        $this->update([
            'fecha_muestra'       => $fechaMuestra->toDateString(),
            'usuario_registro_id' => $usuarioId,
            'fecha_registro'      => now(),
            'estado'              => 'Registrado',
        ]);
    }

    /** CU22: el médico revisó los resultados; un examen Revisado ya no se modifica. */
    public function marcarRevisado(): void
    {
        $this->update(['estado' => 'Revisado']);
    }

    /**
     * CU20, CU22: exámenes del paciente, del más reciente al más antiguo. Con $soloConResultados, solo los
     * Registrados o Revisados (lo que ve el paciente, D8).
     */
    public static function listarPorPaciente(int $pacienteId, bool $soloConResultados = false): Collection
    {
        return self::with(['medico', 'resultados.parametro'])
            ->where('paciente_id', $pacienteId)
            ->when($soloConResultados, fn ($q) => $q->whereIn('estado', ['Registrado', 'Revisado']))
            ->orderByDesc('fecha_solicitud')
            ->get();
    }
}
