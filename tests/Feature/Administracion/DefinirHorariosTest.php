<?php

namespace Tests\Feature\Administracion;

use App\Models\Horario;
use App\Models\Medico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU07 – Definir horarios de atención.
 */
class DefinirHorariosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Medico $perez;   // lunes 08-12, miércoles 14-18 y viernes 08-12, franjas de 30 min

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::buscarPorLogin('admin'));
        $this->perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
    }

    private function agregar(array $cambios = [])
    {
        return $this->from("/horarios?medico_id={$this->perez->id}")->post('/horarios', $cambios + [
            'medico_id' => $this->perez->id, 'dia_semana' => 2, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'duracion_cita_min' => 20,
        ]);
    }

    public function test_muestra_los_horarios_actuales_del_medico(): void
    {
        $this->get('/horarios')->assertOk()->assertSee('Seleccione un médico');
        $this->get("/horarios?medico_id={$this->perez->id}")->assertOk()
            ->assertSee('Lunes')->assertSee('08:00 – 12:00')->assertSee('8 franjas')->assertSee('Agregar horario');
    }

    public function test_solo_el_administrador_accede(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'))->get('/horarios')->assertForbidden();
    }

    public function test_agrega_horario_y_muestra_las_franjas_que_genera(): void
    {
        $this->agregar()->assertSessionHasNoErrors()
            ->assertRedirect("/horarios?medico_id={$this->perez->id}")
            ->assertSessionHas('exito', 'Horario registrado: genera 6 franjas de 20 minutos.');

        $franjas = session('franjas');
        $this->assertSame(['inicio' => '08:00', 'fin' => '08:20'], $franjas[0]);
        $this->assertSame(['inicio' => '09:40', 'fin' => '10:00'], $franjas[5]);
        $this->assertSame(4, Horario::where('medico_id', $this->perez->id)->count());
        $this->assertDatabaseHas('bitacora', ['accion' => 'CREAR', 'modulo' => 'Horarios']);

        $this->get("/horarios?medico_id={$this->perez->id}")->assertSee('Martes')->assertSee('08:00–08:20');
    }

    public function test_hora_final_menor_o_igual_a_la_inicial(): void
    {
        $this->agregar(['hora_inicio' => '10:00', 'hora_fin' => '10:00'])
            ->assertSessionHasErrors(['hora_fin' => 'La hora final debe ser posterior a la hora inicial.']);
        $this->agregar(['hora_inicio' => '10:00', 'hora_fin' => '09:00'])->assertSessionHasErrors('hora_fin');
    }

    public function test_rango_que_no_forma_franjas_completas(): void
    {
        $this->agregar(['hora_inicio' => '08:00', 'hora_fin' => '09:10', 'duracion_cita_min' => 30])
            ->assertSessionHasErrors(['hora_fin' => 'El rango de 70 minutos no se divide en franjas completas de 30 minutos.']);
    }

    public function test_horario_superpuesto_con_otro_del_mismo_dia(): void
    {
        $this->agregar(['dia_semana' => 1, 'hora_inicio' => '11:30', 'hora_fin' => '13:30', 'duracion_cita_min' => 30])
            ->assertSessionHasErrors(['hora_inicio' => 'El horario se superpone con otro horario activo del médico ese día.']);

        // contiguo (empieza cuando termina el otro) sí se permite
        $this->agregar(['dia_semana' => 1, 'hora_inicio' => '12:00', 'hora_fin' => '13:00', 'duracion_cita_min' => 30])
            ->assertSessionHasNoErrors();
    }

    public function test_duracion_fuera_de_rango(): void
    {
        foreach ([5, 121] as $duracion) {
            $this->agregar(['duracion_cita_min' => $duracion])
                ->assertSessionHasErrors(['duracion_cita_min' => 'La duración de la franja debe estar entre 10 y 120 minutos.']);
        }
    }

    public function test_medico_inactivo_no_recibe_horarios(): void
    {
        $this->perez->cambiarEstado(false);
        $this->agregar()->assertSessionHasErrors(['medico_id' => 'El médico no existe o está inactivo.']);
        $this->get("/horarios?medico_id={$this->perez->id}")->assertSee('El médico está inactivo');
    }

    public function test_desactiva_libera_el_dia_y_reactiva_solo_sin_superposicion(): void
    {
        $lunes = Horario::where('medico_id', $this->perez->id)->where('dia_semana', 1)->first();

        $this->patch("/horarios/{$lunes->id}/estado", ['accion' => 'desactivar'])->assertSessionHas('exito', 'Horario desactivado.');
        $this->assertFalse($lunes->fresh()->estado);

        // para «cambiarlo», se agrega otro en el mismo día
        $this->agregar(['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'duracion_cita_min' => 30])->assertSessionHasNoErrors();

        // el anterior ya no se puede reactivar porque se superpone
        $this->patch("/horarios/{$lunes->id}/estado", ['accion' => 'activar'])
            ->assertSessionHas('error', 'No se puede activar: se superpone con otro horario activo del médico ese día.');
        $this->assertFalse($lunes->fresh()->estado);
        $this->assertDatabaseHas('bitacora', ['accion' => 'DESACTIVAR', 'modulo' => 'Horarios']);
    }

    public function test_no_existe_edicion_de_horarios(): void
    {
        $lunes = Horario::where('medico_id', $this->perez->id)->first();
        // B3: no hay ruta de edición
        $this->put("/horarios/{$lunes->id}", ['hora_inicio' => '07:00'])->assertNotFound();
        $this->assertSame($lunes->hora_inicio, $lunes->fresh()->hora_inicio);
    }
}
