<?php

namespace Tests\Feature\Administracion;

use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU06 – Gestionar médicos.
 */
class GestionarMedicosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::buscarPorLogin('admin'));
    }

    private function idEsp(string $nombre): int
    {
        return Especialidad::where('nombre', $nombre)->value('id');
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'ci' => '5009001', 'nro_registro_prof' => 'RP-2001', 'nombres' => 'Óscar', 'apellidos' => 'Núñez Paz',
            'telefono' => '70099999', 'correo' => 'onunez@vidasalud.com', 'especialidades' => [$this->idEsp('Cardiología')],
        ];
    }

    public function test_lista_y_busca_por_nombre_ci_o_especialidad(): void
    {
        $this->get('/medicos')->assertOk()->assertSee('M-0001')->assertSee('Pérez Rojas')->assertSee('Pediatría');

        $this->get('/medicos/buscar?criterio=perez')->assertOk()->assertSee('Pérez Rojas')->assertDontSee('Mendoza Vaca');
        $this->get('/medicos/buscar?criterio=4001002')->assertOk()->assertSee('Mendoza Vaca');
        $this->get('/medicos/buscar?criterio=geriatria')->assertOk()->assertSee('Justiniano Paz')->assertDontSee('Pérez Rojas');
        $this->get('/medicos/buscar?criterio=zzzz')->assertOk()->assertSee('No se encontraron médicos');
        $this->from('/medicos')->get('/medicos/buscar?criterio=a')->assertSessionHasErrors('criterio');
    }

    public function test_solo_el_administrador_accede(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'))->get('/medicos')->assertForbidden();
    }

    public function test_registra_medico_con_codigo_y_especialidades(): void
    {
        $this->post('/medicos', $this->datos(['especialidades' => [$this->idEsp('Cardiología'), $this->idEsp('Medicina general')]]))
            ->assertSessionHasNoErrors()->assertRedirect('/medicos');

        // la secuencia no se revierte entre pruebas: se comprueba el formato, no el número
        $medico = Medico::where('ci', '5009001')->first();
        $this->assertMatchesRegularExpression('/^M-\d{4}$/', $medico->codigo);
        $this->assertGreaterThan('M-0006', $medico->codigo);
        $this->assertSame("Médico {$medico->codigo} registrado.", session('exito'));
        $this->assertEqualsCanonicalizing(['Cardiología', 'Medicina general'], $medico->especialidades->pluck('nombre')->all());
        $this->assertDatabaseHas('bitacora', ['accion' => 'CREAR', 'modulo' => 'Médicos', 'registro_id' => $medico->id]);
    }

    public function test_ci_o_registro_duplicado(): void
    {
        $this->post('/medicos', $this->datos(['ci' => '4001001']))->assertSessionHasErrors(['ci' => 'El CI ya está registrado en otro médico.']);
        $this->post('/medicos', $this->datos(['nro_registro_prof' => 'rp-1001']))->assertSessionHasErrors('nro_registro_prof');
        $this->assertSame(6, Medico::count());
    }

    public function test_correo_invalido_y_sin_especialidad(): void
    {
        $this->post('/medicos', $this->datos(['correo' => 'no-es-correo']))->assertSessionHasErrors('correo');
        $this->post('/medicos', $this->datos(['especialidades' => []]))
            ->assertSessionHasErrors(['especialidades' => 'Seleccione al menos una especialidad.']);
    }

    public function test_no_asigna_una_especialidad_inactiva(): void
    {
        Especialidad::where('nombre', 'Cardiología')->first()->cambiarEstado(false);
        $this->post('/medicos', $this->datos())->assertSessionHasErrors('especialidades.0');
    }

    public function test_edita_y_sincroniza_especialidades(): void
    {
        $vaca = Medico::where('nro_registro_prof', 'RP-1005')->first();   // cardiología y medicina general
        $this->get("/medicos?editar={$vaca->id}")->assertOk()->assertSee('Editar médico M-0005');

        $this->put("/medicos/{$vaca->id}", [
            'ci' => $vaca->ci, 'nro_registro_prof' => $vaca->nro_registro_prof, 'nombres' => 'Jorge Luis',
            'apellidos' => $vaca->apellidos, 'especialidades' => [$this->idEsp('Cardiología')],
        ])->assertSessionHasNoErrors();

        $vaca->refresh();
        $this->assertSame('Jorge Luis', $vaca->nombres);
        $this->assertSame(['Cardiología'], $vaca->especialidades->pluck('nombre')->all());
    }

    public function test_conserva_una_especialidad_asignada_aunque_este_inactiva(): void
    {
        $perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        Especialidad::where('nombre', 'Medicina general')->first()->cambiarEstado(false);

        $this->put("/medicos/{$perez->id}", [
            'ci' => $perez->ci, 'nro_registro_prof' => $perez->nro_registro_prof, 'nombres' => $perez->nombres,
            'apellidos' => $perez->apellidos, 'especialidades' => [$this->idEsp('Medicina general')],
        ])->assertSessionHasNoErrors();

        // pero no puede agregar otra especialidad inactiva que no tenía
        Especialidad::where('nombre', 'Odontología')->first()->cambiarEstado(false);
        $this->put("/medicos/{$perez->id}", [
            'ci' => $perez->ci, 'nro_registro_prof' => $perez->nro_registro_prof, 'nombres' => $perez->nombres,
            'apellidos' => $perez->apellidos, 'especialidades' => [$this->idEsp('Odontología')],
        ])->assertSessionHasErrors('especialidades.0');
    }

    public function test_desactiva_conserva_historial_y_reactiva(): void
    {
        $perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $citas = $perez->citas()->count();

        $this->patch("/medicos/{$perez->id}/estado", ['accion' => 'desactivar'])->assertSessionHasNoErrors();
        $this->assertFalse($perez->fresh()->estado);
        $this->assertSame($citas, $perez->citas()->count());
        $this->assertNotContains($perez->id, Medico::listarPorEspecialidad($this->idEsp('Medicina general'))->pluck('id'));

        $this->patch("/medicos/{$perez->id}/estado", ['accion' => 'activar'])->assertSessionHasNoErrors();
        $this->assertTrue($perez->fresh()->estado);
    }
}
