<?php

namespace Tests\Feature\Pacientes;

use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU08 – Gestionar pacientes. Incluye las pruebas mínimas del enunciado
 * «registrar un paciente» y «rechazar un CI duplicado».
 */
class GestionarPacientesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'ci' => '6543210', 'nombres' => 'Andrea', 'apellidos' => 'Salvatierra Rojas', 'fecha_nacimiento' => '1992-07-15',
            'sexo' => 'F', 'telefono' => '71234567', 'correo' => 'andrea@correo.com', 'direccion' => 'Av. Cañoto',
        ];
    }

    public function test_lista_los_pacientes(): void
    {
        $this->get('/pacientes')->assertOk()->assertSee('P-0001')->assertSee('López Vargas')->assertSee('Nuevo paciente');
    }

    public function test_recepcionista_y_administrador_acceden_pero_no_el_paciente(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('admin'))->get('/pacientes')->assertOk();
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))->get('/pacientes')->assertForbidden();
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'))->get('/pacientes')->assertForbidden();
    }

    public function test_registra_un_paciente_con_codigo(): void
    {
        $this->post('/pacientes', $this->datos())->assertSessionHasNoErrors()->assertRedirect('/pacientes');

        $paciente = Paciente::buscarPorCI('6543210');
        $this->assertMatchesRegularExpression('/^P-\d{4}$/', $paciente->codigo);
        $this->assertSame('Salvatierra Rojas', $paciente->apellidos);
        $this->assertTrue($paciente->estado);
        $this->assertSame("Paciente {$paciente->codigo} registrado.", session('exito'));
        $this->assertDatabaseHas('bitacora', ['accion' => 'CREAR', 'modulo' => 'Pacientes', 'registro_id' => $paciente->id]);
    }

    public function test_rechaza_un_ci_duplicado_y_muestra_el_paciente_existente(): void
    {
        $this->post('/pacientes', $this->datos(['ci' => '1234567']))
            ->assertSessionHasErrors(['ci' => 'Ya existe el paciente P-0001 – María López Vargas con ese CI.']);
        $this->assertSame(10, Paciente::count());
    }

    public function test_fecha_de_nacimiento_futura(): void
    {
        $this->post('/pacientes', $this->datos(['fecha_nacimiento' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['fecha_nacimiento' => 'La fecha de nacimiento no puede ser futura.']);
    }

    public function test_campos_obligatorios_vacios_y_formatos(): void
    {
        $this->post('/pacientes', ['ci' => '', 'nombres' => '', 'apellidos' => '', 'fecha_nacimiento' => '', 'sexo' => '', 'telefono' => ''])
            ->assertSessionHasErrors(['ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono']);
        $this->post('/pacientes', $this->datos(['ci' => 'ABC', 'correo' => 'mal', 'telefono' => 'x']))
            ->assertSessionHasErrors(['ci', 'correo', 'telefono']);
    }

    public function test_menor_de_edad_requiere_contacto_de_padre_madre_o_tutor(): void
    {
        $menor = ['fecha_nacimiento' => now()->subYears(10)->toDateString()];

        $this->post('/pacientes', $this->datos($menor))
            ->assertSessionHasErrors(['contacto_emerg_nombre' => 'El paciente es menor de 18 años: indique el contacto de su padre, madre o tutor.']);
        $this->post('/pacientes', $this->datos($menor + ['contacto_emerg_nombre' => 'Luis Salvatierra', 'contacto_emerg_parent' => 'Hermano', 'contacto_emerg_tel' => '70000001']))
            ->assertSessionHasErrors('contacto_emerg_parent');
        $this->post('/pacientes', $this->datos($menor + ['contacto_emerg_nombre' => 'Luis Salvatierra', 'contacto_emerg_parent' => 'Padre', 'contacto_emerg_tel' => '70000001']))
            ->assertSessionHasNoErrors();
    }

    public function test_edita_un_paciente(): void
    {
        $maria = Paciente::buscarPorCI('1234567');
        $this->get("/pacientes?editar={$maria->id}")->assertOk()->assertSee('Editar paciente P-0001');

        $this->put("/pacientes/{$maria->id}", $this->datos(['ci' => '1234567', 'nombres' => 'María José', 'apellidos' => 'López Vargas']))
            ->assertSessionHasNoErrors();
        $this->assertSame('María José', $maria->fresh()->nombres);

        // no puede tomar el CI de otro paciente
        $this->put("/pacientes/{$maria->id}", $this->datos(['ci' => '7654321']))->assertSessionHasErrors('ci');
    }

    public function test_desactiva_sin_eliminar_su_historial(): void
    {
        $maria = Paciente::buscarPorCI('1234567');
        $citas = $maria->citas()->count();

        $this->delete("/pacientes/{$maria->id}")->assertRedirect('/pacientes');

        $this->assertFalse($maria->fresh()->estado);
        $this->assertSame($citas, $maria->citas()->count());
        $this->assertDatabaseHas('paciente', ['id' => $maria->id]);
        $this->assertDatabaseHas('bitacora', ['accion' => 'DESACTIVAR', 'modulo' => 'Pacientes', 'registro_id' => $maria->id]);
    }

    public function test_reactiva_un_paciente_desactivado_y_lo_registra(): void
    {
        $maria = Paciente::buscarPorCI('1234567');
        $maria->desactivar();
        $this->get('/pacientes')->assertSee('Reactivar');

        $this->patch("/pacientes/{$maria->id}/estado", ['accion' => 'activar'])
            ->assertRedirect('/pacientes')->assertSessionHas('exito', 'Paciente P-0001 reactivado.');

        $this->assertTrue($maria->fresh()->estado);
        $this->assertDatabaseHas('bitacora', ['accion' => 'ACTIVAR', 'modulo' => 'Pacientes', 'registro_id' => $maria->id]);

        // la desactivación sigue siendo destroy()
        $this->patch("/pacientes/{$maria->id}/estado", ['accion' => 'desactivar'])->assertSessionHasErrors('accion');
        $this->assertTrue($maria->fresh()->estado);
    }

    public function test_recepcionista_y_administrador_corrigen_el_tipo_de_sangre(): void
    {
        $maria = Paciente::buscarPorCI('1234567');   // O+ en la población
        $datosMaria = $this->datos(['ci' => '1234567', 'nombres' => 'María', 'apellidos' => 'López Vargas', 'fecha_nacimiento' => '1990-05-14']);

        $this->get("/pacientes?editar={$maria->id}")->assertSee('<option value="O+" selected', false);
        $this->put("/pacientes/{$maria->id}", $datosMaria + ['tipo_sangre' => 'A+'])->assertSessionHasNoErrors();
        $this->assertSame('A+', $maria->historia()->first()->tipo_sangre);
        $this->assertDatabaseHas('bitacora', ['accion' => 'MODIFICAR', 'registro_id' => $maria->id, 'descripcion' => 'Modificación del paciente P-0001; tipo de sangre: O+ → A+']);

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('admin'));
        $this->put("/pacientes/{$maria->id}", $datosMaria + ['tipo_sangre' => 'AB-'])->assertSessionHasNoErrors();
        $this->assertSame('AB-', $maria->historia()->first()->tipo_sangre);

        $this->put("/pacientes/{$maria->id}", $datosMaria + ['tipo_sangre' => 'Z+'])->assertSessionHasErrors('tipo_sangre');
    }

    public function test_tipo_de_sangre_al_registrar_crea_la_historia(): void
    {
        $this->post('/pacientes', $this->datos(['tipo_sangre' => 'B-']))->assertSessionHasNoErrors();
        $this->assertSame('B-', Paciente::buscarPorCI('6543210')->historia()->first()->tipo_sangre);
    }

    public function test_pagina_interna_sin_sesion(): void
    {
        $this->flushSession();
        auth()->logout();
        $this->get('/pacientes')->assertRedirect('/login');
    }
}
