<?php

namespace Tests\Feature\Administracion;

use App\Models\Cita;
use App\Models\Horario;
use App\Models\Medico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B4 (aplazada del ciclo 1 al ciclo 2): al desactivar un médico (CU06) o un horario (CU07) con citas futuras
 * se muestran las citas y se exige confirmar, como en CU24. Las citas no se cancelan solas.
 */
class AdvertenciaCitasFuturasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Medico $perez;   // cita futura de María el lunes a las 08:00
    private Cita $futura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $this->futura = Cita::where('medico_id', $this->perez->id)->where('estado', 'Programada')->first();
        $this->actingAs(Usuario::buscarPorLogin('admin'));
    }

    public function test_desactivar_un_medico_con_citas_futuras_pide_confirmar(): void
    {
        $this->patch("/medicos/{$this->perez->id}/estado", ['accion' => 'desactivar'])
            ->assertRedirect(route('medicos.index'))->assertSessionHas('citasFuturas');
        $this->assertTrue($this->perez->fresh()->estado);
        $this->get('/medicos')->assertSee($this->futura->codigo)->assertSee('Confirmar la desactivación');

        $this->patch("/medicos/{$this->perez->id}/estado", ['accion' => 'desactivar', 'confirmar' => 1])
            ->assertSessionHas('exito')->assertSessionHas('aviso');
        $this->assertFalse($this->perez->fresh()->estado);
        $this->assertSame('Programada', $this->futura->refresh()->estado);
    }

    public function test_desactivar_un_medico_sin_citas_futuras_no_pide_confirmar(): void
    {
        $sinCitas = Medico::where('nro_registro_prof', 'RP-1004')->first();   // su única cita futura se cancela
        Cita::where('medico_id', $sinCitas->id)->whereIn('estado', Cita::VIGENTES)->get()->each->cancelar('Prueba');

        $this->patch("/medicos/{$sinCitas->id}/estado", ['accion' => 'desactivar'])
            ->assertSessionHas('exito')->assertSessionMissing('citasFuturas');
        $this->assertFalse($sinCitas->fresh()->estado);
    }

    public function test_desactivar_un_horario_con_citas_futuras_pide_confirmar(): void
    {
        $lunes = Horario::where('medico_id', $this->perez->id)->where('dia_semana', 1)->first();
        $miercoles = Horario::where('medico_id', $this->perez->id)->where('dia_semana', 3)->first();

        $this->patch("/horarios/{$lunes->id}/estado", ['accion' => 'desactivar'])->assertSessionHas('citasFuturas');
        $this->assertTrue($lunes->fresh()->estado);

        // el horario del miércoles no tiene citas futuras: se desactiva directamente
        $this->patch("/horarios/{$miercoles->id}/estado", ['accion' => 'desactivar'])
            ->assertSessionHas('exito', 'Horario desactivado.')->assertSessionMissing('citasFuturas');

        $this->patch("/horarios/{$lunes->id}/estado", ['accion' => 'desactivar', 'confirmar' => 1])->assertSessionHas('aviso');
        $this->assertFalse($lunes->fresh()->estado);
    }

    public function test_activar_no_pide_confirmar(): void
    {
        $this->perez->cambiarEstado(false);
        $this->patch("/medicos/{$this->perez->id}/estado", ['accion' => 'activar'])->assertSessionHas('exito')->assertSessionMissing('citasFuturas');
        $this->assertTrue($this->perez->fresh()->estado);
    }
}
