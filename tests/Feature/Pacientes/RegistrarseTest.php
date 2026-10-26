<?php

namespace Tests\Feature\Pacientes;

use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU10 – Registrarse como paciente.
 */
class RegistrarseTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const CLAVE = 'MiClave#2026';

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'ci' => '9876543', 'nombres' => 'Lucía', 'apellidos' => 'Vargas Peña', 'fecha_nacimiento' => '1998-03-10',
            'sexo' => 'F', 'telefono' => '76543210', 'correo' => 'lucia@correo.com',
            'clave' => self::CLAVE, 'clave_confirmation' => self::CLAVE, 'acepta_privacidad' => '1',
        ];
    }

    public function test_el_login_enlaza_al_registro(): void
    {
        $this->get('/login')->assertSee('Crear cuenta de paciente');
        $this->get('/registro')->assertOk()->assertSee('Acepto la política de privacidad');
    }

    public function test_crea_ficha_y_cuenta_inicia_sesion_y_muestra_el_portal(): void
    {
        $this->post('/registro', $this->datos())->assertSessionHasNoErrors()->assertRedirect('/portal');

        $paciente = Paciente::buscarPorCI('9876543');
        $this->assertMatchesRegularExpression('/^P-\d{4}$/', $paciente->codigo);
        $this->assertTrue($paciente->acepta_privacidad);

        $usuario = $paciente->usuario;
        $this->assertSame('9876543', $usuario->nombre_usuario);                 // A7: el usuario es el CI
        $this->assertSame(Rol::PACIENTE, $usuario->rol->nombre);
        $this->assertTrue($usuario->verificarClave(self::CLAVE));
        $this->assertFalse($usuario->debe_cambiar_clave);
        $this->assertAuthenticatedAs($usuario);
        $this->assertDatabaseHas('bitacora', ['accion' => 'REGISTRO_PACIENTE', 'usuario_id' => $usuario->id]);

        $this->get('/portal')->assertOk()->assertSee('Vargas Peña');
    }

    public function test_vincula_la_ficha_existente_si_coinciden_fecha_y_apellidos(): void
    {
        $carlos = Paciente::buscarPorCI('7654321');   // Carlos Gómez Áñez, sin cuenta

        $this->post('/registro', $this->datos([
            'ci' => '7654321', 'nombres' => 'Carlos', 'apellidos' => 'gomez  añez', 'fecha_nacimiento' => '1985-11-02',
            'correo' => 'carlos.gomez@correo.com',
        ]))->assertSessionHasNoErrors()->assertRedirect('/portal');

        $this->assertSame(10, Paciente::count());                                  // no se creó otra ficha
        $this->assertSame($carlos->id, Usuario::buscarPorLogin('7654321')->paciente_id);
        $this->assertSame('carlos.gomez@correo.com', $carlos->fresh()->correo);
    }

    public function test_ci_con_cuenta_indica_como_recuperar_el_acceso(): void
    {
        $this->post('/registro', $this->datos(['ci' => '1234567', 'apellidos' => 'López Vargas', 'fecha_nacimiento' => '1990-05-14']))
            ->assertSessionHas('error', 'El CI ya tiene una cuenta. Si olvidó su contraseña, use «¿Olvidó su contraseña?» en el inicio de sesión.');
        $this->assertGuest();
    }

    public function test_ci_existente_con_datos_distintos_pide_acudir_a_recepcion(): void
    {
        $this->post('/registro', $this->datos(['ci' => '7654321', 'apellidos' => 'Otro Apellido', 'fecha_nacimiento' => '1985-11-02']))
            ->assertSessionHas('error', 'Los datos no coinciden con la ficha registrada en la clínica. Acuda a recepción para crear su cuenta.')
            ->assertSessionMissing('_old_input.clave');
        $this->post('/registro', $this->datos(['ci' => '7654321', 'apellidos' => 'Gómez Áñez', 'fecha_nacimiento' => '1985-11-03']))
            ->assertSessionHas('error');

        $this->assertNull(Usuario::buscarPorLogin('7654321'));
        $this->assertGuest();
    }

    public function test_correo_ya_registrado(): void
    {
        $this->post('/registro', $this->datos(['correo' => 'RECEP1@vidasalud.com']))
            ->assertSessionHasErrors(['correo' => 'El correo ya está registrado en otra cuenta.']);
    }

    public function test_no_acepta_la_politica_de_privacidad(): void
    {
        $this->post('/registro', $this->datos(['acepta_privacidad' => null]))
            ->assertSessionHasErrors(['acepta_privacidad' => 'Debe aceptar la política de privacidad.']);
        $this->assertNull(Paciente::buscarPorCI('9876543'));
    }

    public function test_contrasena_que_no_cumple_la_politica_o_no_coincide(): void
    {
        $this->post('/registro', $this->datos(['clave' => 'simple', 'clave_confirmation' => 'simple']))->assertSessionHasErrors('clave');
        $this->post('/registro', $this->datos(['clave_confirmation' => 'Otra#2026x']))->assertSessionHasErrors('clave');
    }

    public function test_menor_de_edad_indica_el_contacto_de_su_padre_madre_o_tutor(): void
    {
        $menor = ['fecha_nacimiento' => now()->subYears(16)->toDateString()];

        $this->post('/registro', $this->datos($menor))->assertSessionHasErrors('contacto_emerg_nombre');
        $this->post('/registro', $this->datos($menor + [
            'contacto_emerg_nombre' => 'Rosa Peña', 'contacto_emerg_parent' => 'Madre', 'contacto_emerg_tel' => '70000002',
        ]))->assertSessionHasNoErrors()->assertRedirect('/portal');
    }

    public function test_con_sesion_iniciada_no_se_registra(): void
    {
        $this->actingAs(Usuario::buscarPorLogin('1234567'))->get('/registro')->assertRedirect('/portal');
    }
}
