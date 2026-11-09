<?php

namespace Tests\Feature\Administracion;

use App\Models\Bitacora;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Medico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CU24 – Gestionar feriados y bloqueos. Agregar (clínica o médico), confirmación con citas existentes,
 * eliminar, filtros y las 5 excepciones del documento. La fecha bloqueada sale de la disponibilidad.
 */
class GestionarBloqueosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Carbon $lunes;     // «proxima»: Pérez tiene la cita de María a las 08:00
    private Carbon $libre;     // sin citas
    private Medico $perez;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lunes = now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
        $this->libre = $this->lunes->copy()->addWeeks(3);
        $this->perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $this->actingAs(Usuario::buscarPorLogin('admin'));
    }

    private function agregar(array $cambios = [])
    {
        return $this->from('/bloqueos')->post('/bloqueos', $cambios + [
            'fecha' => $this->libre->toDateString(), 'ambito' => 'clinica', 'motivo' => 'Feriado departamental',
        ]);
    }

    public function test_lista_los_bloqueos_con_fecha_ambito_y_motivo(): void
    {
        $this->get('/menu')->assertSee('Bloqueos');
        $this->get('/bloqueos')->assertOk()
            ->assertSee('Navidad')->assertSee('Toda la clínica')->assertSee('Capacitación del médico')->assertSee('Rojas Suárez');
    }

    public function test_agrega_un_feriado_y_la_fecha_sale_de_la_disponibilidad(): void
    {
        $this->agregar()->assertRedirect(route('bloqueos.index'))->assertSessionHas('exito');

        $bloqueo = Bloqueo::whereDate('fecha', $this->libre)->first();
        $this->assertNull($bloqueo->medico_id);
        $this->assertSame('Feriado departamental', $bloqueo->motivo);
        $this->assertNotNull(Bloqueo::estaBloqueada($this->perez->id, $this->libre));
        $this->assertTrue(Bitacora::where('modulo', 'Bloqueos')->where('accion', 'CREAR')->where('registro_id', $bloqueo->id)->exists());

        // CU13 muestra el motivo
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'));
        $this->get('/disponibilidad/franjas?' . http_build_query([
            'paciente_id' => \App\Models\Paciente::buscarPorCI('8901234')->id,
            'especialidad_id' => \App\Models\Especialidad::where('nombre', 'Medicina general')->value('id'),
            'medico_id' => $this->perez->id, 'fecha' => $this->libre->toDateString(),
        ]))->assertSee('Fecha bloqueada: Feriado departamental.');
    }

    public function test_con_citas_en_la_fecha_las_muestra_y_exige_confirmar(): void
    {
        $datos = ['fecha' => $this->lunes->toDateString(), 'ambito' => 'medico', 'medico_id' => $this->perez->id, 'motivo' => 'Vacaciones'];
        $cita = Cita::where('medico_id', $this->perez->id)->whereDate('fecha', $this->lunes)->first();

        $this->agregar($datos)->assertRedirect('/bloqueos')->assertSessionHas('citasEnFecha');
        $this->assertFalse(Bloqueo::existe($this->perez->id, $this->lunes));
        $this->get('/bloqueos')->assertSee($cita->codigo)->assertSee('Confirmar el bloqueo');

        $this->agregar($datos + ['confirmar' => 1])->assertSessionHas('exito')
            ->assertSessionHas('aviso', 'Hay 1 cita(s) en esa fecha: la recepción debe reprogramarlas o cancelarlas (CU15, CU16).');
        $this->assertTrue(Bloqueo::existe($this->perez->id, $this->lunes));
        // las citas existentes no se cancelan solas
        $this->assertSame('Programada', $cita->refresh()->estado);
    }

    public function test_elimina_el_bloqueo_y_la_fecha_vuelve_a_estar_disponible(): void
    {
        $bloqueo = Bloqueo::crear(['medico_id' => $this->perez->id, 'fecha' => $this->libre->toDateString(), 'motivo' => 'Permiso']);

        $this->delete("/bloqueos/{$bloqueo->id}")->assertRedirect(route('bloqueos.index'))->assertSessionHas('exito');
        $this->assertNull(Bloqueo::estaBloqueada($this->perez->id, $this->libre));
        $this->assertTrue(Bitacora::where('modulo', 'Bloqueos')->where('accion', 'ELIMINAR')->where('registro_id', $bloqueo->id)->exists());
    }

    public function test_filtra_por_medico_y_por_rango(): void
    {
        $this->get("/bloqueos?medico_id={$this->perez->id}")->assertOk()->assertSee('No hay bloqueos con esos filtros.');
        $this->get('/bloqueos?medico_id=clinica')->assertOk()->assertSee('Navidad')->assertDontSee('Capacitación del médico');
        $this->get('/bloqueos?desde=2026-05-10&hasta=2026-05-01')
            ->assertSessionHasErrors(['hasta' => 'Rango de fechas inválido: la fecha final es anterior a la inicial.']);
    }

    // ---------- Excepciones ----------

    public function test_bloqueo_duplicado_para_la_misma_fecha_y_ambito(): void
    {
        $this->agregar();
        $this->agregar(['motivo' => 'Otro'])->assertSessionHasErrors(['fecha' => 'Ya existe un bloqueo para esa fecha y ese ámbito.']);

        // otro ámbito (un médico) en la misma fecha sí se admite
        $this->agregar(['ambito' => 'medico', 'medico_id' => $this->perez->id, 'motivo' => 'Permiso'])->assertSessionHasNoErrors();
        $this->assertSame(2, Bloqueo::whereDate('fecha', $this->libre)->count());
    }

    public function test_fecha_vacia_o_pasada(): void
    {
        $this->agregar(['fecha' => ''])->assertSessionHasErrors(['fecha' => 'Indique la fecha.']);
        $this->agregar(['fecha' => today()->subDay()->toDateString()])->assertSessionHasErrors(['fecha' => 'No se puede bloquear una fecha pasada.']);
    }

    public function test_motivo_vacio_o_largo(): void
    {
        $this->agregar(['motivo' => '  '])->assertSessionHasErrors(['motivo' => 'Indique el motivo.']);
        $this->agregar(['motivo' => str_repeat('x', 121)])->assertSessionHasErrors(['motivo' => 'El motivo no puede superar los 120 caracteres.']);
        $this->agregar(['motivo' => str_repeat('x', 120)])->assertSessionHasNoErrors();
    }

    public function test_medico_inexistente_o_sin_elegir(): void
    {
        $this->agregar(['ambito' => 'medico', 'medico_id' => 999999])->assertSessionHasErrors(['medico_id' => 'El médico no existe.']);
        $this->agregar(['ambito' => 'medico'])->assertSessionHasErrors(['medico_id' => 'Elija el médico.']);
        $this->assertFalse(Bloqueo::whereDate('fecha', $this->libre)->exists());
    }

    public function test_solo_el_administrador_gestiona_bloqueos(): void
    {
        foreach (['recep1', 'dr.perez', '1234567'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login));
            $this->get('/bloqueos')->assertForbidden();
            $this->agregar()->assertForbidden();
        }
    }
}
