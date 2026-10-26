<?php

namespace Tests\Feature\Pacientes;

use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU09 – Buscar paciente.
 */
class BuscarPacienteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    public function test_muestra_el_buscador_sin_criterio(): void
    {
        $this->get('/pacientes/buscar')->assertOk()->assertSee('CI, nombre o apellido')->assertDontSee('resultado(s)');
    }

    public function test_busca_por_apellido_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $this->get('/pacientes/buscar?criterio=LOPEZ')->assertOk()
            ->assertSee('P-0001')->assertSee('López Vargas')->assertSee('71111111')->assertSee('Activo')
            ->assertDontSee('Gómez Áñez');
        $this->get('/pacientes/buscar?criterio=gomez anez')->assertOk()->assertSee('Gómez Áñez');
    }

    public function test_busca_por_ci_y_por_nombre(): void
    {
        $this->get('/pacientes/buscar?criterio=4567890')->assertOk()->assertSee('Fernández Cuéllar');
        $this->get('/pacientes/buscar?criterio=valeria')->assertOk()->assertSee('Chávez Melgar');
    }

    public function test_criterio_con_menos_de_tres_caracteres(): void
    {
        $this->from('/pacientes/buscar')->get('/pacientes/buscar?criterio=lo')
            ->assertRedirect('/pacientes/buscar')
            ->assertSessionHasErrors(['criterio' => 'Escriba al menos 3 caracteres para buscar.']);
    }

    public function test_sin_resultados_ofrece_registrar_un_paciente(): void
    {
        $this->get('/pacientes/buscar?criterio=zzzzz')->assertOk()
            ->assertSee('No se encontraron pacientes')->assertSee('Registrar nuevo paciente');
    }

    public function test_mas_de_50_coincidencias_pide_precisar(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            Paciente::crear([
                'ci' => (string) (8000000 + $i), 'nombres' => "Persona $i", 'apellidos' => 'Repetido Común',
                'fecha_nacimiento' => '1990-01-01', 'telefono' => '70000000',
            ]);
        }

        $this->get('/pacientes/buscar?criterio=repetido')->assertOk()
            ->assertSee('Hay más de 50 coincidencias')->assertDontSee('Persona 1<');
        // con 50 o menos se listan
        Paciente::where('nombres', 'Persona 51')->delete();
        $this->get('/pacientes/buscar?criterio=repetido')->assertOk()->assertSee('50 resultado(s).');
    }

    public function test_seleccionar_muestra_al_paciente_para_continuar(): void
    {
        $maria = Paciente::buscarPorCI('1234567');

        $this->get("/pacientes/{$maria->id}")->assertOk()
            ->assertSee('López Vargas, María')->assertSee('P-0001')->assertSee('Jorge López (Hermano)')->assertSee('Editar datos');
    }

    public function test_el_administrador_tambien_busca_y_el_paciente_no(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('admin'))->get('/pacientes/buscar?criterio=lopez')->assertOk();
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))->get('/pacientes/buscar?criterio=lopez')->assertForbidden();
    }
}
