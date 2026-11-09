<?php

namespace Tests\Feature\Pagos;

use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU25 – Registrar pago de consulta. Cobertura del seguro vigente (RN19), pago pendiente y su
 * completado, comprobante y las 5 excepciones del documento.
 */
class RegistrarPagoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Cita $deMaria;    // medicina general (Bs 150); seguro vigente al 70 %
    private Cita $deCarlos;   // medicina general (Bs 150); su seguro venció hace 35 días
    private Cita $deDiego;    // odontología (Bs 120); sin seguro

    protected function setUp(): void
    {
        parent::setUp();
        $futura = fn (string $ci) => Cita::where('paciente_id', Paciente::buscarPorCI($ci)->id)->whereIn('estado', Cita::VIGENTES)->first();
        $this->deMaria = $futura('1234567');
        $this->deCarlos = $futura('7654321');
        $this->deDiego = $futura('8901234');
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function cobrar(Cita $cita, ?string $metodo = 'QR', string $accion = 'cobrar')
    {
        return $this->post('/pagos', array_filter(['cita_id' => $cita->id, 'accion' => $accion, 'metodo_pago' => $metodo]));
    }

    public function test_cobra_con_la_cobertura_del_seguro_vigente_y_emite_el_comprobante(): void
    {
        $this->get("/pagos?cita={$this->deMaria->id}")->assertOk()
            ->assertSee('Cobrar cita ' . $this->deMaria->codigo)->assertSee('Seguro vigente')->assertSee('cubre el 70 %')
            ->assertSee('Bs 150,00')->assertSee('Bs 105,00')->assertSee('Bs 45,00');

        $respuesta = $this->cobrar($this->deMaria, 'QR');

        $pago = Pago::obtenerPorCita($this->deMaria->id);
        $respuesta->assertRedirect(route('pagos.show', $pago));
        $this->assertMatchesRegularExpression('/^PG-\d{5,}$/', $pago->nro_comprobante);
        $this->assertSame(['Pagado', 'QR', '70.00', '150.00', '45.00'],
            [$pago->estado, $pago->metodo_pago, $pago->porcentaje_cobertura, $pago->monto_consulta, $pago->monto_paciente]);
        $this->assertNotNull($pago->seguro_id);
        $this->assertNotNull($pago->fecha_pago);
        $this->assertTrue(Bitacora::where('accion', 'COBRAR')->where('registro_id', $pago->id)->exists());

        $this->get(route('pagos.show', $pago))->assertOk()
            ->assertSee('Comprobante de pago')->assertSee($pago->nro_comprobante)->assertSee('Bs 45,00')->assertSee('Imprimir');
    }

    public function test_seguro_vencido_se_cobra_sin_cobertura_y_se_avisa(): void
    {
        $this->get("/pagos?cita={$this->deCarlos->id}")->assertOk()
            ->assertSee('no está vigente en la fecha de la cita: se cobra sin cobertura (RN19)');

        $this->cobrar($this->deCarlos, 'Efectivo');
        $pago = Pago::obtenerPorCita($this->deCarlos->id);
        $this->assertSame(['0.00', '150.00'], [$pago->porcentaje_cobertura, $pago->monto_paciente]);
        $this->assertNull($pago->seguro_id);
    }

    public function test_sin_seguro_paga_el_cien_por_ciento(): void
    {
        $this->get("/pagos?cita={$this->deDiego->id}")->assertOk()->assertSee('paga el 100 %');
        $this->cobrar($this->deDiego, 'Efectivo');
        $this->assertSame('120.00', Pago::obtenerPorCita($this->deDiego->id)->monto_paciente);
    }

    public function test_deja_el_pago_pendiente_y_lo_completa_despues(): void
    {
        $this->cobrar($this->deDiego, null, 'pendiente')->assertSessionHas('exito');
        $pago = Pago::obtenerPorCita($this->deDiego->id);
        $this->assertSame('Pendiente', $pago->estado);
        $this->assertNull($pago->metodo_pago);
        $this->assertNull($pago->fecha_pago);

        $this->get('/pagos')->assertOk()->assertSee('Pagos pendientes')->assertSee($pago->nro_comprobante);

        $this->patch("/pagos/{$pago->id}", ['metodo_pago' => 'Efectivo'])->assertRedirect(route('pagos.show', $pago));
        $pago->refresh();
        $this->assertSame(['Pagado', 'Efectivo'], [$pago->estado, $pago->metodo_pago]);
        $this->assertNotNull($pago->fecha_pago);
        $this->assertTrue(Bitacora::where('accion', 'COMPLETAR_PAGO')->where('registro_id', $pago->id)->exists());

        $this->patch("/pagos/{$pago->id}", ['metodo_pago' => 'QR'])
            ->assertSessionHasErrors(['metodo_pago' => 'El pago ya está registrado como pagado.']);
    }

    public function test_abre_la_cita_por_su_codigo(): void
    {
        $this->get('/pagos?codigo=' . strtolower($this->deDiego->codigo))->assertOk()->assertSee('Cobrar cita ' . $this->deDiego->codigo);
    }

    // ---------- Excepciones ----------

    public function test_cita_cancelada_no_se_cobra(): void
    {
        $cancelada = Cita::where('estado', 'Cancelada')->first();

        $this->get("/pagos?cita={$cancelada->id}")->assertOk()->assertSee('La cita está cancelada: no se cobra.')->assertDontSee('Dejar pendiente');
        $this->cobrar($cancelada)->assertSessionHasErrors(['cita_id' => 'La cita está cancelada: no se cobra.']);
        $this->assertNull(Pago::obtenerPorCita($cancelada->id));
    }

    public function test_la_cita_ya_tiene_un_pago_y_se_muestra_el_existente(): void
    {
        $pagada = Pago::where('estado', 'Pagado')->first();

        $this->get("/pagos?cita={$pagada->cita_id}")
            ->assertRedirect(route('pagos.show', $pagada))->assertSessionHas('aviso', 'La cita ya tiene un pago registrado.');
        $this->post('/pagos', ['cita_id' => $pagada->cita_id, 'accion' => 'cobrar', 'metodo_pago' => 'QR'])
            ->assertSessionHasErrors(['cita_id' => 'La cita ya tiene un pago registrado.']);
        $this->assertSame(1, Pago::where('cita_id', $pagada->cita_id)->count());
    }

    public function test_falta_el_metodo_de_pago_al_cobrar(): void
    {
        $this->cobrar($this->deMaria, null)->assertSessionHasErrors(['metodo_pago' => 'Elija el método de pago (efectivo o QR) para cobrar.']);
        $this->cobrar($this->deMaria, 'Tarjeta')->assertSessionHasErrors('metodo_pago');
        $this->assertNull(Pago::obtenerPorCita($this->deMaria->id));
    }

    public function test_cita_inexistente(): void
    {
        $this->get('/pagos?codigo=CM-9999')->assertSessionHasErrors(['codigo' => 'La cita CM-9999 no existe.']);
        $this->get('/pagos?cita=999999')->assertSessionHasErrors(['cita' => 'La cita no existe.']);
        $this->post('/pagos', ['cita_id' => 999999, 'accion' => 'cobrar', 'metodo_pago' => 'QR'])->assertSessionHasErrors('cita_id');
    }

    public function test_solo_la_recepcionista_registra_pagos(): void
    {
        foreach (['admin', 'dr.perez', '1234567'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login));
            $this->get('/pagos')->assertForbidden();
            $this->cobrar($this->deMaria)->assertForbidden();
        }
    }
}
