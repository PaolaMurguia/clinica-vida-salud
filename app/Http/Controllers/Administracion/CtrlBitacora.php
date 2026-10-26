<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\FiltrarBitacoraRequest;
use App\Models\Bitacora;
use App\Models\Usuario;
use App\Services\ServicioBitacora;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Control CtrlBitacora: CU04 – Consultar bitácora. Solo consulta: los registros no se editan ni se eliminan.
 */
class CtrlBitacora extends Controller
{
    /** Registros por página. */
    private const POR_PAGINA = 20;

    /**
     * CU04 – Consultar bitácora (paso 1): registros más recientes con fecha, usuario, tipo,
     * módulo, descripción e IP.
     */
    public function index(): View
    {
        return view('bitacora', [
            'registros' => Bitacora::listar(self::POR_PAGINA),
            'usuarios'  => Usuario::listar(),
            'modulos'   => Bitacora::whereNotNull('modulo')->distinct()->orderBy('modulo')->pluck('modulo'),
            'filtros'   => [],
            'filtrado'  => null,
        ]);
    }

    /**
     * CU04 – Consultar bitácora (pasos 2 y 3): valida los filtros y muestra los resultados paginados.
     */
    public function filtrar(FiltrarBitacoraRequest $request): View
    {
        $filtros = array_filter($request->validated(), fn ($valor) => $valor !== null && $valor !== '');

        return view('bitacora', [
            'registros' => Bitacora::buscar($filtros)->paginate(self::POR_PAGINA)->withQueryString(),
            'usuarios'  => Usuario::listar(),
            'modulos'   => Bitacora::whereNotNull('modulo')->distinct()->orderBy('modulo')->pluck('modulo'),
            'filtros'   => $filtros,
            'filtrado'  => isset($filtros['usuario_id']) ? Usuario::obtener((int) $filtros['usuario_id']) : null,
        ]);
    }

    /**
     * CU04 – Consultar bitácora (paso 4): exporta a CSV el resultado de los filtros.
     */
    public function exportar(FiltrarBitacoraRequest $request): StreamedResponse
    {
        $filtros = array_filter($request->validated(), fn ($valor) => $valor !== null && $valor !== '');
        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'EXPORTAR', 'Bitácora', null,
            'Exportación a CSV' . ($filtros ? ' con filtros: ' . json_encode($filtros, JSON_UNESCAPED_UNICODE) : ''));

        return response()->streamDownload(function () use ($filtros) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");   // BOM: Excel abre el archivo en UTF-8
            fputcsv($salida, ['Fecha y hora', 'Usuario', 'Tipo', 'Acción', 'Módulo', 'Descripción', 'IP'], ';');
            // lazy(): recorre por bloques y carga el usuario de cada bloque de una vez
            foreach (Bitacora::buscar($filtros)->lazy(500) as $registro) {
                fputcsv($salida, [
                    $registro->fecha_hora->format('d/m/Y H:i:s'),
                    $registro->usuario?->nombre_usuario ?? '(sin identificar)',
                    $registro->tipo,
                    $registro->accion,
                    $registro->modulo,
                    $registro->descripcion,
                    $registro->ip,
                ], ';');
            }
            fclose($salida);
        }, 'bitacora_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
