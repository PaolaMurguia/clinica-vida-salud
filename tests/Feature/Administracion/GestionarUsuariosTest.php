<?php

namespace Tests\Feature\Administracion;

use App\Models\Medico;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU03 – Gestionar usuarios y roles.
 */
class GestionarUsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Usuario::buscarPorLogin('admin');
        $this->actingAs($this->admin);
    }

    private function datosNuevo(array $cambios = []): array
    {
        return $cambios + [
            'nombre_usuario' => 'recep3',
            'correo'         => 'recep3@vidasalud.com',
            'clave'          => 'Inicial#2026',
            'rol_id'         => Rol::obtener(Rol::RECEPCIONISTA)->id,
        ];
    }

    public function test_lista_usuarios_con_rol_y_estado(): void
    {
        $this->get('/usuarios')->assertOk()
            ->assertSee('dr.perez')->assertSee('Recepcionista')->assertSee('Activo')->assertSee('Nuevo usuario');
    }

    public function test_solo_el_administrador_accede(): void
    {
        // flushSession: cada usuario con su propia sesión (auth.session compara el hash de la contraseña)
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'))->get('/usuarios')->assertForbidden();
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))->post('/usuarios', $this->datosNuevo())->assertForbidden();
    }

    public function test_registra_usuario_con_clave_cifrada_y_temporal(): void
    {
        $this->post('/usuarios', $this->datosNuevo())->assertRedirect('/usuarios')->assertSessionHas('exito');

        $nuevo = Usuario::buscarPorLogin('recep3');
        $this->assertNotSame('Inicial#2026', $nuevo->password_hash);
        $this->assertTrue($nuevo->verificarClave('Inicial#2026'));
        $this->assertTrue($nuevo->debe_cambiar_clave);
        $this->assertDatabaseHas('bitacora', ['accion' => 'CREAR', 'modulo' => 'Usuarios', 'registro_id' => $nuevo->id]);
    }

    public function test_registra_medico_vinculado_con_permiso_de_atender(): void
    {
        $medico = Medico::where('nro_registro_prof', 'RP-1003')->first();   // sin cuenta en la población
        $this->post('/usuarios', $this->datosNuevo([
            'nombre_usuario' => 'dr.rojas', 'correo' => 'crojas@vidasalud.com',
            'rol_id' => Rol::obtener(Rol::MEDICO)->id, 'medico_id' => $medico->id, 'puede_marcar_atendida' => '1',
        ]))->assertSessionHasNoErrors();

        $nuevo = Usuario::buscarPorLogin('dr.rojas');
        $this->assertSame($medico->id, $nuevo->medico_id);
        $this->assertTrue($nuevo->puede_marcar_atendida);
    }

    public function test_usuario_o_correo_duplicado_sin_distinguir_mayusculas(): void
    {
        $this->post('/usuarios', $this->datosNuevo(['nombre_usuario' => 'RECEP1']))->assertSessionHasErrors('nombre_usuario');
        $this->post('/usuarios', $this->datosNuevo(['correo' => 'Admin@VidaSalud.com']))->assertSessionHasErrors('correo');
    }

    public function test_clave_que_no_cumple_la_politica(): void
    {
        $this->post('/usuarios', $this->datosNuevo(['clave' => 'sencilla']))->assertSessionHasErrors('clave');
    }

    public function test_medico_inexistente_inactivo_o_con_cuenta(): void
    {
        $rolMedico = Rol::obtener(Rol::MEDICO)->id;
        $conCuenta = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $inactivo = Medico::where('nro_registro_prof', 'RP-1004')->first();
        $inactivo->cambiarEstado(false);

        foreach ([null, 9999, $conCuenta->id, $inactivo->id] as $medicoId) {
            $this->post('/usuarios', $this->datosNuevo(['rol_id' => $rolMedico, 'medico_id' => $medicoId]))
                ->assertSessionHasErrors('medico_id');
        }
    }

    public function test_no_se_crea_una_cuenta_con_rol_paciente(): void
    {
        $this->post('/usuarios', $this->datosNuevo(['rol_id' => Rol::obtener(Rol::PACIENTE)->id]))->assertSessionHasErrors('rol_id');
    }

    public function test_edita_usuario(): void
    {
        $recep = Usuario::buscarPorLogin('recep1');
        $this->get("/usuarios?editar={$recep->id}")->assertOk()->assertSee('Editar usuario recep1');

        $this->put("/usuarios/{$recep->id}", [
            'nombre_usuario' => 'recep1', 'correo' => 'recepcion1@vidasalud.com', 'rol_id' => $recep->rol_id,
        ])->assertSessionHasNoErrors()->assertRedirect('/usuarios');

        $this->assertSame('recepcion1@vidasalud.com', $recep->fresh()->correo);
        $this->assertDatabaseHas('bitacora', ['accion' => 'MODIFICAR', 'registro_id' => $recep->id]);
    }

    public function test_no_cambia_el_rol_del_unico_administrador_activo(): void
    {
        $this->put("/usuarios/{$this->admin->id}", [
            'nombre_usuario' => 'admin', 'correo' => 'admin@vidasalud.com', 'rol_id' => Rol::obtener(Rol::RECEPCIONISTA)->id,
        ])->assertSessionHasErrors('rol_id');

        $this->assertSame(Rol::ADMINISTRADOR, $this->admin->fresh()->rol->nombre);
    }

    public function test_restablecer_clave_temporal_se_muestra_una_vez_y_desbloquea(): void
    {
        $recep = Usuario::buscarPorLogin('recep1');
        $recep->forceFill(['bloqueado_hasta' => now()->addHour(), 'nivel_bloqueo' => 3])->save();

        $respuesta = $this->post("/usuarios/{$recep->id}/restablecer-clave")->assertRedirect('/usuarios');
        $temporal = $respuesta->getSession()->get('claveTemporal')['clave'];

        $recep->refresh();
        $this->assertTrue($recep->verificarClave($temporal));
        $this->assertTrue($recep->debe_cambiar_clave);
        $this->assertNull($recep->bloqueado_hasta);
        $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12}$/', $temporal);
        $this->assertDatabaseHas('bitacora', ['accion' => 'RESTABLECER_CLAVE', 'registro_id' => $recep->id]);

        $this->get('/usuarios')->assertSee($temporal);
        $this->get('/usuarios')->assertDontSee($temporal);
    }

    public function test_desbloquear_desactivar_y_activar(): void
    {
        $recep = Usuario::buscarPorLogin('recep1');
        $recep->forceFill(['bloqueado_hasta' => now()->addHour(), 'nivel_bloqueo' => 2, 'intentos_fallidos' => 3])->save();

        $this->patch("/usuarios/{$recep->id}/estado", ['accion' => 'desbloquear'])->assertSessionHasNoErrors();
        $this->assertSame([0, null], [$recep->fresh()->nivel_bloqueo, $recep->fresh()->bloqueado_hasta]);

        $this->patch("/usuarios/{$recep->id}/estado", ['accion' => 'desactivar'])->assertSessionHasNoErrors();
        $this->assertFalse($recep->fresh()->estado);

        $this->patch("/usuarios/{$recep->id}/estado", ['accion' => 'activar'])->assertSessionHasNoErrors();
        $this->assertTrue($recep->fresh()->estado);

        $this->patch("/usuarios/{$recep->id}/estado", ['accion' => 'borrar'])->assertSessionHasErrors('accion');
        $this->assertDatabaseHas('bitacora', ['accion' => 'DESACTIVAR', 'registro_id' => $recep->id]);
    }

    public function test_no_desactiva_su_propia_cuenta_ni_al_unico_administrador(): void
    {
        $this->patch("/usuarios/{$this->admin->id}/estado", ['accion' => 'desactivar'])
            ->assertSessionHasErrors(['accion' => 'No puede desactivar su propia cuenta mientras tiene la sesión abierta.']);

        // otra cuenta de administrador (inactiva, para que admin siga siendo el único activo) intenta desactivarlo
        $otroAdmin = Usuario::crear([
            'nombre_usuario' => 'admin2', 'correo' => 'admin2@vidasalud.com', 'clave' => 'Admin2#2026',
            'rol_id' => Rol::obtener(Rol::ADMINISTRADOR)->id, 'estado' => false,
        ]);
        $this->flushSession()->actingAs($otroAdmin)->patch("/usuarios/{$this->admin->id}/estado", ['accion' => 'desactivar'])
            ->assertSessionHasErrors(['accion' => 'No se puede desactivar al único administrador activo.']);

        $this->assertTrue($this->admin->fresh()->estado);
    }
}
