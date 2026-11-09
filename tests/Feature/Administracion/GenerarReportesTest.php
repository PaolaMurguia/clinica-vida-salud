<?php

namespace Tests\Feature\Administracion;

use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Medico;
use App\Models\RegistroAtencion;
use App\Models\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CU23 – Generar reportes. Los 8 tipos de reporte con sus totales, exportación a CSV y PDF, impresión,
 * bitácora y las 4 excepciones del documento. Sin diagnósticos (RN18).
 */
class GenerarReportesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Carbon $pasada;   // semana con 8 citas pasadas: 6 atendidas, 1 cancelada y 1 inasistencia

    protected function setUp(): void
    {
        parent::setUp();
        $this->pasada = now()->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay();
        $this->actingAs(Usuario::buscarPorLogin('admin'));
    }

    private function periodo(): array
    {
        return ['desde' => $this->pasada->toDateString(), 'hasta' => $this->pasada->copy()->addDays(6)->toDateString()];
    }

    private function generar(array $filtros)
    {
        return $this->get('/reportes/generar?' . http_build_query($filtros));
    }

    public function test_el_administrador_elige_el_reporte(): void
    {
        $this->get('/menu')->assertSee('Reportes');
        $this->get('/reportes')->assertOk()
            ->assertSee('Citas programadas para una fecha')->assertSee('Agenda diaria de cada médico')->assertSee('Ingresos por método de pago');
    }

    public function test_atendidas_canceladas_e_inasistencias_del_periodo(): void
    {
        $this->generar(['tipo' => 'atendidas_canceladas'] + $this->periodo())->assertOk()
            ->assertSeeInOrder(['Atendida', '6', 'Cancelada', '1', 'Inasistencia', '1'])->assertSeeInOrder(['Total', '8']);
        $this->assertTrue(Bitacora::where('accion', 'GENERAR_REPORTE')->where('modulo', 'Reportes')->exists());
    }

    public function test_citas_por_estado_sin_diagnosticos(): void
    {
        $diagnosticos = RegistroAtencion::pluck('diagnostico');
        $inasistencia = Cita::where('estado', 'Inasistencia')->value('codigo');

        $respuesta = $this->generar(['tipo' => 'citas_estado', 'estado' => 'Atendida'] + $this->periodo())->assertOk()
            ->assertSee('Citas por estado')->assertSeeInOrder(['Total de citas', '6'])->assertDontSee($inasistencia);
        $diagnosticos->each(fn ($diagnostico) => $respuesta->assertDontSee($diagnostico));
    }

    public function test_citas_por_medico_y_por_especialidad(): void
    {
        $perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $esperadas = Cita::where('medico_id', $perez->id)->whereBetween('fecha', array_values($this->periodo()))->pluck('codigo');
        $ajena = Cita::where('medico_id', '<>', $perez->id)->whereBetween('fecha', array_values($this->periodo()))->value('codigo');

        $respuesta = $this->generar(['tipo' => 'citas_medico', 'medico_id' => $perez->id] + $this->periodo())->assertOk()
            ->assertSee('Médico: Pérez Rojas, Juan')->assertDontSee($ajena);
        $esperadas->each(fn ($codigo) => $respuesta->assertSee($codigo));

        $odontologia = \App\Models\Especialidad::where('nombre', 'Odontología')->first();
        $this->generar(['tipo' => 'citas_especialidad', 'especialidad_id' => $odontologia->id] + $this->periodo())->assertOk()
            ->assertSee('Especialidad: Odontología')->assertSee('Gómez Áñez');
    }

    public function test_citas_de_una_fecha_y_agenda_diaria(): void
    {
        $fecha = $this->pasada->toDateString();   // lunes: Pérez (2 citas) y Rojas (1)
        $this->generar(['tipo' => 'citas_fecha', 'fecha' => $fecha])->assertOk()->assertSee('López Vargas')->assertSeeInOrder(['Total de citas', '3']);
        $this->generar(['tipo' => 'agenda_diaria', 'fecha' => $fecha])->assertOk()
            ->assertSee('Agenda diaria de cada médico')->assertSeeInOrder(['Pérez Rojas, Juan', '2'])->assertSeeInOrder(['Rojas Suárez, Carlos', '1']);
    }

    public function test_pacientes_registrados_e_ingresos_por_metodo(): void
    {
        $hoy = today()->toDateString();
        $this->generar(['tipo' => 'pacientes_registrados', 'desde' => $hoy, 'hasta' => $hoy])->assertOk()
            ->assertSee('Paz Mercado')->assertSeeInOrder(['Total de pacientes', '10']);

        $this->generar(['tipo' => 'ingresos_metodo'] + $this->periodo())->assertOk()
            ->assertSee('Efectivo')->assertSee('QR')->assertSeeInOrder(['Pagos', '5'])->assertSee('Total cobrado');
    }

    public function test_exporta_a_csv_con_punto_y_coma(): void
    {
        $respuesta = $this->get('/reportes/exportar?' . http_build_query(['tipo' => 'citas_estado', 'formato' => 'csv'] + $this->periodo()));

        $respuesta->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $contenido = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('Fecha;Hora;Código;Paciente;Médico;Especialidad;Estado', $contenido);
        $this->assertStringContainsString('"Total de citas";8', $contenido);
        $this->assertTrue(Bitacora::where('accion', 'EXPORTAR_REPORTE')->where('descripcion', 'like', '%CSV')->exists());
    }

    public function test_exporta_a_pdf(): void
    {
        $respuesta = $this->get('/reportes/exportar?' . http_build_query(['tipo' => 'citas_estado', 'formato' => 'pdf'] + $this->periodo()));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertTrue(Bitacora::where('accion', 'EXPORTAR_REPORTE')->where('descripcion', 'like', '%PDF')->exists());
    }

    public function test_el_reporte_en_pantalla_se_puede_imprimir(): void
    {
        $this->generar(['tipo' => 'citas_estado'] + $this->periodo())->assertSee('window.print()', false)->assertSee('@media print', false);
    }

    // ---------- Excepciones ----------

    public function test_rango_invalido_o_filtro_obligatorio_faltante(): void
    {
        $this->generar(['tipo' => 'citas_estado', 'desde' => '2026-05-10', 'hasta' => '2026-05-01'])
            ->assertSessionHasErrors(['hasta' => 'Rango de fechas inválido: la fecha final es anterior a la inicial.']);
        $this->generar(['tipo' => 'citas_fecha'])->assertSessionHasErrors(['fecha' => 'Este reporte exige la fecha.']);
        $this->generar(['tipo' => 'citas_medico'] + $this->periodo())->assertSessionHasErrors(['medico_id' => 'Elija el médico.']);
        $this->generar(['tipo' => 'citas_estado'])->assertSessionHasErrors(['desde', 'hasta']);
        $this->generar(['tipo' => 'inventado'])->assertSessionHasErrors('tipo');
    }

    public function test_sin_datos_lo_informa_y_no_genera_un_archivo_vacio(): void
    {
        $vacio = ['tipo' => 'citas_estado', 'desde' => '2020-01-01', 'hasta' => '2020-01-31'];

        $this->generar($vacio)->assertOk()->assertSee('No hay datos para los filtros elegidos.')->assertDontSee('Excel / CSV');
        $this->get('/reportes/exportar?' . http_build_query($vacio + ['formato' => 'csv']))
            ->assertRedirect()->assertSessionHas('aviso', 'No hay datos para los filtros elegidos: no se genera un archivo vacío.');
    }

    public function test_falla_la_exportacion_y_el_reporte_sigue_disponible(): void
    {
        Pdf::shouldReceive('loadHTML')->andThrow(new Exception('Fallo del motor PDF'));
        $filtros = ['tipo' => 'citas_estado'] + $this->periodo();

        $this->get('/reportes/exportar?' . http_build_query($filtros + ['formato' => 'pdf']))
            ->assertRedirect(route('reportes.generar', $filtros))
            ->assertSessionHas('error', 'No se pudo exportar el reporte. Intente de nuevo; el reporte en pantalla sigue disponible.');
    }

    public function test_solo_el_administrador_genera_reportes(): void
    {
        foreach (['recep1', 'dr.perez', '1234567'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login));
            $this->get('/reportes')->assertForbidden();
            $this->generar(['tipo' => 'citas_estado'] + $this->periodo())->assertForbidden();
        }
    }
}
