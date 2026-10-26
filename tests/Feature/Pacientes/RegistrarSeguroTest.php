<?php

namespace Tests\Feature\Pacientes;

use App\Models\Paciente;
use App\Models\Seguro;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU12 – Registrar seguro del paciente.
 */
class RegistrarSeguroTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Paciente $maria;   // seguro activo AA-2026-0154 en la población

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
        $this->maria = Paciente::buscarPorCI('1234567');
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'aseguradora' => 'Nacional Seguros', 'nro_poliza' => 'NS-2026-0001', 'tipo_cobertura' => 'Integral',
            'porcentaje_cobertura' => '80', 'fecha_inicio' => today()->toDateString(), 'fecha_fin' => today()->addYear()->toDateString(),
        ];
    }

    private function registrar(Paciente $paciente, array $datos)
    {
        return $this->from("/pacientes/{$paciente->id}/seguro")->post("/pacientes/{$paciente->id}/seguro", $datos);
    }

    public function test_el_paciente_seleccionado_enlaza_a_su_seguro_activo(): void
    {
        $this->get("/pacientes/{$this->maria->id}")->assertSee('Seguro');
        $this->get("/pacientes/{$this->maria->id}/seguro")->assertOk()
            ->assertSee('Aseguradora Andina S.A.')->assertSee('AA-2026-0154')->assertSee('vigente')->assertSee('Registrar un seguro nuevo');
    }

    public function test_solo_la_recepcionista_registra_seguros(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('admin'))->get("/pacientes/{$this->maria->id}/seguro")->assertForbidden();
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))->post("/pacientes/{$this->maria->id}/seguro", $this->datos())->assertForbidden();
    }

    public function test_registra_y_da_de_baja_el_seguro_activo_anterior(): void
    {
        $anterior = Seguro::obtenerActivo($this->maria->id);

        $this->registrar($this->maria, $this->datos())->assertSessionHasNoErrors()
            ->assertRedirect("/pacientes/{$this->maria->id}/seguro")
            ->assertSessionHas('exito', 'Seguro registrado como seguro activo del paciente.');

        $this->assertFalse($anterior->fresh()->estado);
        $nuevo = Seguro::obtenerActivo($this->maria->id);
        $this->assertSame('NS-2026-0001', $nuevo->nro_poliza);
        $this->assertSame(1, Seguro::where('paciente_id', $this->maria->id)->where('estado', true)->count());
        $this->assertDatabaseHas('bitacora', ['accion' => 'CREAR', 'modulo' => 'Seguros', 'registro_id' => $nuevo->id]);

        $this->get("/pacientes/{$this->maria->id}/seguro")->assertSee('Seguros anteriores')->assertSee('AA-2026-0154');
    }

    public function test_poliza_duplicada_en_la_misma_aseguradora_incluso_al_renovar(): void
    {
        $this->registrar($this->maria, $this->datos(['aseguradora' => 'aseguradora andina s.a.', 'nro_poliza' => 'aa-2026-0154']))
            ->assertSessionHasErrors(['nro_poliza' => 'La póliza ya está registrada en esa aseguradora. Para renovarla, registre el número de la nueva póliza.']);

        // la misma póliza en otra aseguradora sí se permite
        $this->registrar($this->maria, $this->datos(['nro_poliza' => 'AA-2026-0154']))->assertSessionHasNoErrors();
    }

    public function test_fechas_incoherentes_y_porcentaje_fuera_de_rango(): void
    {
        $this->registrar($this->maria, $this->datos(['fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-05-01']))
            ->assertSessionHasErrors(['fecha_fin' => 'La fecha de fin no puede ser anterior a la de inicio.']);
        foreach (['-1', '100.5'] as $porcentaje) {
            $this->registrar($this->maria, $this->datos(['porcentaje_cobertura' => $porcentaje]))
                ->assertSessionHasErrors(['porcentaje_cobertura' => 'El porcentaje de cobertura debe estar entre 0 y 100.']);
        }
    }

    public function test_seguro_vencido_se_guarda_e_informa(): void
    {
        $carlos = Paciente::buscarPorCI('7654321');

        $this->registrar($carlos, $this->datos(['fecha_inicio' => '2025-01-01', 'fecha_fin' => today()->subDay()->toDateString()]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('aviso', 'El seguro registrado ya está vencido: no se aplicará a las consultas (RN19).');
        $this->assertNotNull(Seguro::obtenerActivo($carlos->id));
    }

    public function test_paciente_inactivo_no_recibe_seguro(): void
    {
        $this->maria->desactivar();
        $this->registrar($this->maria, $this->datos())->assertSessionHasErrors('aseguradora');
    }

    public function test_dar_de_baja_el_seguro_activo(): void
    {
        $activo = Seguro::obtenerActivo($this->maria->id);

        $this->delete("/pacientes/{$this->maria->id}/seguro/{$activo->id}")->assertRedirect("/pacientes/{$this->maria->id}/seguro");

        $this->assertNull(Seguro::obtenerActivo($this->maria->id));
        $this->assertDatabaseHas('seguro_paciente', ['id' => $activo->id, 'estado' => false]);
        $this->assertDatabaseHas('bitacora', ['accion' => 'DAR_DE_BAJA', 'registro_id' => $activo->id]);
    }

    public function test_no_da_de_baja_el_seguro_de_otro_paciente(): void
    {
        $seguroDeLuis = Seguro::obtenerActivo(Paciente::buscarPorCI('4567890')->id);

        $this->delete("/pacientes/{$this->maria->id}/seguro/{$seguroDeLuis->id}")->assertNotFound();
        $this->assertTrue($seguroDeLuis->fresh()->estado);
    }
}
