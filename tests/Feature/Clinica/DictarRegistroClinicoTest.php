<?php

namespace Tests\Feature\Clinica;

use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\RegistroAtencion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CU21 – Dictar registro clínico por voz. El reconocimiento corre en el navegador (Web Speech API,
 * resources/js/dictado.js; se prueba a mano). Aquí se prueba el servidor: el formulario preparado para dictar,
 * la marca dictado_voz, que el texto dictado se valide igual que el escrito y la bitácora.
 */
class DictarRegistroClinicoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Paciente $maria;
    private Cita $atendida;   // atendida por Pérez, sin registro

    protected function setUp(): void
    {
        parent::setUp();
        $this->maria = Paciente::buscarPorCI('1234567');
        $this->atendida = Cita::where('paciente_id', $this->maria->id)->where('estado', 'Programada')->first();
        DB::table('cita')->where('id', $this->atendida->id)->update(['estado' => 'Atendida']);
        $this->actingAs(Usuario::buscarPorLogin('dr.perez'));
    }

    private function guardar(array $cambios = [])
    {
        return $this->post("/historia-clinica/{$this->maria->id}/atenciones", $cambios + [
            'cita_id' => $this->atendida->id, 'diagnostico' => 'Faringitis aguda', 'dictado_voz' => '1',
        ]);
    }

    public function test_el_formulario_de_atencion_permite_dictar(): void
    {
        $this->get("/historia-clinica/{$this->maria->id}")->assertOk()
            ->assertSee('data-dictado', false)->assertSee('name="dictado_voz"', false)
            ->assertSee('data-dictable', false)->assertSee('data-dictado-aviso', false);
    }

    public function test_el_registro_queda_marcado_como_dictado_por_voz(): void
    {
        $this->guardar()->assertSessionHas('exito');

        $registro = RegistroAtencion::obtenerPorCita($this->atendida->id);
        $this->assertTrue($registro->dictado_voz);
        $this->assertTrue(Bitacora::where('accion', 'DICTADO_VOZ')->where('registro_id', $registro->id)->exists());
        $this->get("/historia-clinica/{$this->maria->id}")->assertSee('Dictado por voz');
    }

    public function test_sin_dictado_no_se_marca(): void
    {
        $this->guardar(['dictado_voz' => '0']);
        $this->assertFalse(RegistroAtencion::obtenerPorCita($this->atendida->id)->dictado_voz);
    }

    public function test_el_texto_dictado_se_valida_como_el_escrito(): void
    {
        // Excepción 5: texto más largo que el campo
        $this->guardar(['diagnostico' => str_repeat('a', 301)])
            ->assertSessionHasErrors(['diagnostico' => 'El diagnóstico no puede superar los 300 caracteres.']);
        $this->guardar(['diagnostico' => 'Ok', 'indicaciones' => str_repeat('b', 501)])->assertSessionHasErrors('indicaciones');
        $this->guardar(['diagnostico' => ''])->assertSessionHasErrors('diagnostico');
        $this->assertNull(RegistroAtencion::obtenerPorCita($this->atendida->id));
    }

    public function test_al_modificar_se_conserva_la_marca_de_dictado(): void
    {
        $this->guardar();
        $registro = RegistroAtencion::obtenerPorCita($this->atendida->id);

        $this->put("/atenciones/{$registro->id}", ['diagnostico' => 'Faringitis bacteriana', 'dictado_voz' => '0']);
        $this->assertTrue($registro->refresh()->dictado_voz);
    }

    public function test_el_paciente_no_tiene_formulario_de_dictado(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));
        $this->get('/mi-historia-clinica')->assertOk()->assertDontSee('data-dictado', false);
    }
}
