{{-- V.formHorario (CU07 – Definir horarios de atención): operación agregar() → CtrlHorarios@store. Se incluye en V.horarios. --}}
@use('App\Models\Horario')
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h2 class="h5">Agregar horario</h2>
        <form method="POST" action="{{ route('horarios.store') }}" data-validar novalidate>
            @csrf
            <input type="hidden" name="medico_id" value="{{ $medico->id }}">
            <div class="mb-2">
                <label for="dia_semana" class="form-label">Día de la semana</label>
                <select id="dia_semana" name="dia_semana" class="form-select @error('dia_semana') is-invalid @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach (Horario::DIAS as $numero => $dia)
                        <option value="{{ $numero }}" @selected((int) old('dia_semana') === $numero)>{{ $dia }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback">Seleccione el día.</div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col">
                    <label for="hora_inicio" class="form-label">Hora inicial</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" value="{{ old('hora_inicio') }}" required
                           class="form-control @error('hora_inicio') is-invalid @enderror">
                </div>
                <div class="col">
                    <label for="hora_fin" class="form-label">Hora final</label>
                    <input type="time" id="hora_fin" name="hora_fin" value="{{ old('hora_fin') }}" required
                           class="form-control @error('hora_fin') is-invalid @enderror">
                </div>
            </div>
            <div class="mb-3">
                <label for="duracion_cita_min" class="form-label">Duración de la franja (minutos)</label>
                <input type="number" id="duracion_cita_min" name="duracion_cita_min" min="10" max="120" required
                       value="{{ old('duracion_cita_min', 30) }}" class="form-control @error('duracion_cita_min') is-invalid @enderror">
                <div class="form-text">Entre 10 y 120 minutos; el rango debe dividirse en franjas completas.</div>
            </div>
            <button type="submit" class="btn btn-primary">Agregar horario</button>
        </form>
    </div>
</div>
