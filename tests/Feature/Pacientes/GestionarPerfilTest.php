<?php

namespace Tests\Feature\Pacientes;

use App\Models\Antecedente;
use App\Models\Historia;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU11 – Gestionar perfil del paciente.
 */
class GestionarPerfilTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $maria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maria = Usuario::buscarPorLogin('1234567');
        $this->actingAs($this->maria);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'telefono' => '71119999', 'correo' => 'maria.lopez@correo.com', 'direccion' => 'Calle Nueva 123',
            'contacto_emerg_nombre' => 'Jorge López', 'contacto_emerg_parent' => 'Hermano', 'contacto_emerg_tel' => '71111112',
            'notif_correo' => '1', 'acepta_privacidad' => '1',
        ];
    }

    public function test_muestra_datos_antecedentes_seguro_y_preferencias(): void
    {
        $this->get('/perfil')->assertOk()
            ->assertSee('P-0001')->assertSee('María López Vargas')->assertSee('O+')
            ->assertSee('Penicilina')->assertSee('Declarado por usted')
            ->assertSee('Aseguradora Andina S.A.')->assertSee('Recibir recordatorios por correo')
            ->assertSee('Cambiar contraseña');
    }

    public function test_solo_el_paciente_accede_a_su_perfil(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'))->get('/perfil')->assertForbidden();
    }

    public function test_actualiza_contacto_y_preferencias_y_el_correo_de_la_cuenta(): void
    {
        $this->put('/perfil', $this->datos(['correo' => 'maria.nueva@correo.com']))->assertSessionHasNoErrors()->assertRedirect('/perfil');

        $paciente = $this->maria->paciente->fresh();
        $this->assertSame('71119999', $paciente->telefono);
        $this->assertSame('Calle Nueva 123', $paciente->direccion);
        $this->assertTrue($paciente->notif_correo);
        $this->assertFalse($paciente->notif_alerta);
        $this->assertSame('maria.nueva@correo.com', $paciente->correo);
        $this->assertSame('maria.nueva@correo.com', $this->maria->fresh()->correo);
        $this->assertDatabaseHas('bitacora', ['accion' => 'MODIFICAR', 'modulo' => 'Perfil']);
    }

    public function test_correo_en_uso_por_otra_cuenta(): void
    {
        $this->put('/perfil', $this->datos(['correo' => 'admin@vidasalud.com']))
            ->assertSessionHasErrors(['correo' => 'El correo ya está en uso por otra cuenta.']);
    }

    public function test_no_puede_modificar_ci_nombres_ni_fecha_de_nacimiento(): void
    {
        $this->put('/perfil', $this->datos(['ci' => '9999999', 'nombres' => 'Otra', 'fecha_nacimiento' => '2000-01-01']))
            ->assertSessionHasErrors(['ci' => 'Solo la recepción puede cambiar el CI, los nombres o la fecha de nacimiento.', 'nombres', 'fecha_nacimiento']);

        $paciente = $this->maria->paciente->fresh();
        $this->assertSame('1234567', $paciente->ci);
        $this->assertSame('María', $paciente->nombres);
    }

    public function test_declara_un_antecedente_como_paciente(): void
    {
        $this->post('/perfil/antecedentes', ['tipo' => 'Medicamento', 'descripcion' => 'Paracetamol 500 mg'])
            ->assertSessionHasNoErrors()->assertRedirect('/perfil');

        $antecedente = Antecedente::where('descripcion', 'Paracetamol 500 mg')->first();
        $this->assertSame(Antecedente::ORIGEN_PACIENTE, $antecedente->origen);
        $this->assertSame($this->maria->id, $antecedente->usuario_id);
        $this->assertDatabaseHas('bitacora', ['accion' => 'DECLARAR_ANTECEDENTE']);
    }

    public function test_declara_tipo_de_sangre_una_vez_y_la_historia_se_crea_si_no_existe(): void
    {
        $valeria = Paciente::buscarPorCI('9012345');   // sin historia clínica en la población
        $cuenta = Usuario::crear([
            'nombre_usuario' => '9012345', 'correo' => 'valeria.chavez@correo.com', 'clave' => 'Valeria#2026',
            'rol_id' => Rol::obtener(Rol::PACIENTE)->id, 'paciente_id' => $valeria->id,
        ]);
        $this->flushSession()->actingAs($cuenta);

        $this->get('/perfil')->assertOk()->assertSee('sin registrar');
        $this->assertNotNull($valeria->historia()->first());                       // B7

        $this->post('/perfil/antecedentes', ['tipo_sangre' => 'A-'])->assertSessionHasNoErrors();
        $this->assertSame('A-', Historia::obtener($valeria->id)->tipo_sangre);

        $this->post('/perfil/antecedentes', ['tipo_sangre' => 'O+'])
            ->assertSessionHasErrors(['tipo_sangre' => 'El tipo de sangre ya está registrado; solo la clínica puede cambiarlo.']);
    }

    public function test_antecedente_incompleto_o_tipo_no_permitido(): void
    {
        $this->post('/perfil/antecedentes', [])->assertSessionHasErrors(['tipo_sangre' => 'Indique su tipo de sangre o un antecedente.']);
        $this->post('/perfil/antecedentes', ['tipo' => 'Alergia'])->assertSessionHasErrors('descripcion');
        $this->post('/perfil/antecedentes', ['tipo' => 'Cirugia', 'descripcion' => 'Apendicectomía'])->assertSessionHasErrors('tipo');
    }

    public function test_antecedentes_del_medico_se_ven_pero_no_se_modifican_ni_quitan(): void
    {
        $luis = Usuario::buscarPorLogin('4567890');
        $this->flushSession()->actingAs($luis)->get('/perfil')->assertOk()
            ->assertSee('Hipertensión arterial')->assertSee('Registrado por su médico');

        $antecedente = Antecedente::where('descripcion', 'Hipertensión arterial')->first();
        $this->put("/perfil/antecedentes/{$antecedente->id}", ['descripcion' => 'Nada'])->assertNotFound();
        $this->delete("/perfil/antecedentes/{$antecedente->id}")->assertNotFound();
        $this->assertDatabaseHas('antecedente', ['id' => $antecedente->id, 'descripcion' => 'Hipertensión arterial']);
    }
}
