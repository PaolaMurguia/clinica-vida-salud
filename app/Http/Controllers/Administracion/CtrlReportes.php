<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\GenerarReporteRequest;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Pago;
use App\Services\ServicioBitacora;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Control CtrlReportes: CU23 – Generar reportes (Administrador). Consultas de solo lectura sobre cita, paciente
 * y pago; los reportes no incluyen diagnósticos ni datos clínicos (RN18).
 */
class CtrlReportes extends Controller
{
    /**
     * CU23 – Generar reportes (paso 1 y 2, elegirReporte): tipos de reporte y filtros que corresponden a cada uno.
     */
    public function index(Request $request): View
    {
        return view('reportes', [
            'medicos'        => Medico::listar(),
            'especialidades' => Especialidad::listar(),
            'filtros'        => ['tipo' => $request->query('tipo')],
            'reporte'        => null,
        ]);
    }

    /**
     * CU23 – Generar reportes (pasos 3 a 5 y 7, generar): consulta los datos con consultas parametrizadas y muestra
     * el reporte con sus totales; registra la generación en la bitácora.
     */
    public function generar(GenerarReporteRequest $request): View
    {
        $filtros = $request->validated();
        $tipo = $filtros['tipo'];
        $fecha = $filtros['fecha'] ?? null;
        $desde = $fecha ?? $filtros['desde'];
        $hasta = $fecha ?? $filtros['hasta'];
        $periodo = $desde === $hasta
            ? Carbon::parse($desde)->format('d/m/Y')
            : Carbon::parse($desde)->format('d/m/Y') . ' al ' . Carbon::parse($hasta)->format('d/m/Y');

        // Filas de citas comunes a varios reportes (sin diagnósticos)
        $filasCitas = fn ($citas, bool $conMedico = true) => $citas->map(fn (Cita $cita) => array_values(array_filter([
            $cita->fecha->format('d/m/Y'),
            substr($cita->hora_inicio, 0, 5),
            $cita->codigo,
            "{$cita->paciente->apellidos}, {$cita->paciente->nombres}",
            $conMedico ? "{$cita->medico->apellidos}, {$cita->medico->nombres}" : null,
            $cita->especialidad->nombre,
            $cita->estado,
        ], fn ($valor) => $valor !== null)))->all();
        $columnasCitas = ['Fecha', 'Hora', 'Código', 'Paciente', 'Médico', 'Especialidad', 'Estado'];
        $base = ['desde' => $desde, 'hasta' => $hasta, 'estado' => $filtros['estado'] ?? null];

        $reporte = match ($tipo) {
            'citas_fecha', 'citas_estado' => (function () use ($base, $filasCitas, $columnasCitas) {
                $citas = Cita::listarReporte($base);
                return ['columnas' => $columnasCitas, 'filas' => $filasCitas($citas),
                    'totales' => Cita::contarPorEstado($base)->filter()->all() + ['Total de citas' => $citas->count()]];
            })(),
            'citas_medico' => (function () use ($base, $filtros, $filasCitas, $columnasCitas) {
                $medico = Medico::listar()->firstWhere('id', (int) $filtros['medico_id']);
                $citas = Cita::listarReporte($base + ['medico_id' => $medico->id]);
                return ['subtitulo' => "Médico: {$medico->apellidos}, {$medico->nombres}", 'columnas' => $columnasCitas,
                    'filas' => $filasCitas($citas), 'totales' => Cita::contarPorEstado($base + ['medico_id' => $medico->id])->filter()->all()
                        + ['Total de citas' => $citas->count()]];
            })(),
            'citas_especialidad' => (function () use ($base, $filtros, $filasCitas, $columnasCitas) {
                $especialidad = Especialidad::listar()->firstWhere('id', (int) $filtros['especialidad_id']);
                $citas = Cita::listarReporte($base + ['especialidad_id' => $especialidad->id]);
                return ['subtitulo' => "Especialidad: {$especialidad->nombre}", 'columnas' => $columnasCitas,
                    'filas' => $filasCitas($citas), 'totales' => Cita::contarPorEstado($base + ['especialidad_id' => $especialidad->id])->filter()->all()
                        + ['Total de citas' => $citas->count()]];
            })(),
            'atendidas_canceladas' => (function () use ($base) {
                $conteo = Cita::contarPorEstado(['desde' => $base['desde'], 'hasta' => $base['hasta'], 'estado' => ['Atendida', 'Cancelada', 'Inasistencia']]);
                return ['columnas' => ['Estado', 'Cantidad'], 'filas' => $conteo->sum() > 0 ? $conteo->map(fn ($n, $estado) => [$estado, $n])->values()->all() : [],
                    'totales' => ['Total' => $conteo->sum()]];
            })(),
            'agenda_diaria' => (function () use ($base, $filasCitas) {
                $citas = Cita::listarReporte($base)->sortBy(fn (Cita $cita) => $cita->medico->apellidos . ' ' . $cita->hora_inicio)->values();
                return ['columnas' => ['Médico', 'Hora', 'Código', 'Paciente', 'Especialidad', 'Estado'],
                    'filas' => $citas->map(fn (Cita $cita) => ["{$cita->medico->apellidos}, {$cita->medico->nombres}", substr($cita->hora_inicio, 0, 5),
                        $cita->codigo, "{$cita->paciente->apellidos}, {$cita->paciente->nombres}", $cita->especialidad->nombre, $cita->estado])->all(),
                    'totales' => $citas->countBy(fn (Cita $cita) => "{$cita->medico->apellidos}, {$cita->medico->nombres}")->all()
                        + ['Total de citas' => $citas->count()]];
            })(),
            'pacientes_registrados' => (function () use ($desde, $hasta) {
                $pacientes = Paciente::listarRegistrados($desde, $hasta);
                return ['columnas' => ['Registro', 'Código', 'Paciente', 'CI', 'Edad', 'Estado'],
                    'filas' => $pacientes->map(fn (Paciente $paciente) => [$paciente->fecha_registro->format('d/m/Y'), $paciente->codigo,
                        "{$paciente->apellidos}, {$paciente->nombres}", $paciente->ci ?? '—', $paciente->fecha_nacimiento->age,
                        $paciente->estado ? 'Activo' : 'Inactivo'])->all(),
                    'totales' => ['Total de pacientes' => $pacientes->count()]];
            })(),
            'ingresos_metodo' => (function () use ($desde, $hasta) {
                $ingresos = Pago::ingresosPorMetodo($desde, $hasta);
                $bs = fn ($monto) => 'Bs ' . number_format($monto, 2, ',', '.');
                return ['columnas' => ['Método de pago', 'Cantidad de pagos', 'Total cobrado'],
                    'filas' => $ingresos->map(fn ($fila, $metodo) => [$metodo, $fila['cantidad'], $bs($fila['total'])])->values()->all(),
                    'totales' => ['Pagos' => $ingresos->sum('cantidad'), 'Total cobrado' => $bs($ingresos->sum('total'))]];
            })(),
        };
        $reporte += ['titulo' => GenerarReporteRequest::TIPOS[$tipo], 'periodo' => $periodo, 'subtitulo' => null];

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'GENERAR_REPORTE', 'Reportes', null,
            "{$reporte['titulo']} ($periodo): " . count($reporte['filas']) . ' fila(s)');

        return view('reportes', [
            'medicos'        => Medico::listar(),
            'especialidades' => Especialidad::listar(),
            'filtros'        => $filtros,
            'reporte'        => $reporte,
        ]);
    }

    /**
     * CU23 – Generar reportes (pasos 6 y 7, exportar): genera el reporte con los mismos filtros y lo descarga en
     * CSV (separador punto y coma, se abre en Excel) o PDF. Sin datos no se genera un archivo vacío; si falla la
     * exportación, el reporte en pantalla sigue disponible.
     */
    public function exportar(GenerarReporteRequest $request): Response|RedirectResponse
    {
        $reporte = $this->generar($request)->getData()['reporte'];
        $formato = $request->validated('formato') ?? 'csv';
        $volver = redirect()->route('reportes.generar', $request->safe()->except('formato'));

        if ($reporte['filas'] === []) {
            return $volver->with('aviso', 'No hay datos para los filtros elegidos: no se genera un archivo vacío.');
        }

        try {
            $nombre = 'reporte_' . $request->validated('tipo') . '_' . now()->format('Ymd_His');
            $respuesta = $formato === 'pdf'
                ? Pdf::loadHTML(
                    // D9: tabla simple con el título, el período y los totales (sin vista nueva; los textos se escapan con e())
                    '<html><head><meta charset="utf-8"><style>'
                    . 'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#222}h1{font-size:15px;margin:0}'
                    . 'p{margin:2px 0 8px;color:#555}table{width:100%;border-collapse:collapse;margin-top:6px}'
                    . 'th,td{border:1px solid #bbb;padding:3px 5px;text-align:left}th{background:#e9eef6}'
                    . '.totales{width:auto;margin-top:10px}</style></head><body>'
                    . '<h1>' . e(config('app.name') . ' – ' . $reporte['titulo']) . '</h1>'
                    . '<p>' . e($reporte['periodo'] . ($reporte['subtitulo'] ? ' · ' . $reporte['subtitulo'] : '')
                        . ' · generado el ' . now()->format('d/m/Y H:i')) . '</p>'
                    . '<table><thead><tr>' . collect($reporte['columnas'])->map(fn ($c) => '<th>' . e($c) . '</th>')->join('') . '</tr></thead><tbody>'
                    . collect($reporte['filas'])->map(fn ($fila) => '<tr>' . collect($fila)->map(fn ($c) => '<td>' . e((string) $c) . '</td>')->join('') . '</tr>')->join('')
                    . '</tbody></table><table class="totales">'
                    . collect($reporte['totales'])->map(fn ($v, $k) => '<tr><th>' . e($k) . '</th><td>' . e((string) $v) . '</td></tr>')->join('')
                    . '</table></body></html>'
                )->setPaper('a4', count($reporte['columnas']) > 5 ? 'landscape' : 'portrait')->download("$nombre.pdf")
                : response()->streamDownload(function () use ($reporte) {
                $salida = fopen('php://output', 'w');
                fwrite($salida, "\xEF\xBB\xBF");   // BOM: Excel abre el archivo en UTF-8
                fputcsv($salida, [$reporte['titulo'] . ' – ' . $reporte['periodo']], ';');
                if ($reporte['subtitulo']) {
                    fputcsv($salida, [$reporte['subtitulo']], ';');
                }
                fputcsv($salida, $reporte['columnas'], ';');
                foreach ($reporte['filas'] as $fila) {
                    fputcsv($salida, $fila, ';');
                }
                foreach ($reporte['totales'] as $etiqueta => $valor) {
                    fputcsv($salida, [$etiqueta, $valor], ';');
                }
                fclose($salida);
            }, "$nombre.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        } catch (Throwable $e) {
            report($e);
            return $volver->with('error', 'No se pudo exportar el reporte. Intente de nuevo; el reporte en pantalla sigue disponible.');
        }

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'EXPORTAR_REPORTE', 'Reportes', null,
            "{$reporte['titulo']} ({$reporte['periodo']}) exportado a " . strtoupper($formato));

        return $respuesta;
    }
}
