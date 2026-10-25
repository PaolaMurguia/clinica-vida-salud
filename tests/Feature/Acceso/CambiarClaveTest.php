<?php

namespace Tests\Feature\Acceso;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * CU26 – Cambiar contraseña.
 */
class CambiarClaveTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const CLAVE = 'Clinica#2026';
    private const NUEVA = 'NuevaClave#2026';

    private function cambiar(array $datos)
    {
        return $this->from('/cambiar-clave')->put('/cambiar-clave', $datos + [
            'clave_actual' => self::CLAVE,
            'clave_nueva' => self::NUEVA,
            'clave_nueva_confirmation' => self::NUEVA,
        ]);
    }

    public function test_muestra_el_formulario(): void
    {
        $this->actingAs(Usuario::buscarPorLogin('recep1'))->get('/cambiar-clave')
            ->assertOk()->assertSee('Contraseña actual')->assertSee('Confirmación');
    }

    public function test_cambio_correcto_quita_la_marca_temporal_y_registra(): void
    {
        $usuario = Usuario::buscarPorLogin('recep2');   // contraseña temporal en la población
        $this->actingAs($usuario);

        $this->cambiar([])->assertRedirect('/menu')->assertSessionHas('exito', 'Contraseña actualizada correctamente.');

        $usuario->refresh();
        $this->assertTrue($usuario->verificarClave(self::NUEVA));
        $this->assertFalse($usuario->debe_cambiar_clave);
        $this->assertDatabaseHas('bitacora', ['accion' => 'CAMBIO_CLAVE', 'usuario_id' => $usuario->id]);
        $this->assertAuthenticatedAs($usuario);
        $this->get('/menu')->assertOk();
    }

    public function test_paciente_vuelve_al_portal(): void
    {
        $this->actingAs(Usuario::buscarPorLogin('1234567'));
        $this->cambiar([])->assertRedirect('/portal');
    }

    public function test_ingreso_con_clave_temporal_lleva_a_cambiar_y_bloquea_otras_opciones(): void
    {
        $this->post('/login', ['login' => 'recep2', 'clave' => self::CLAVE])->assertRedirect('/cambiar-clave');

        // RN23: no puede usar otras opciones hasta cambiarla, pero sí salir
        $this->get('/menu')->assertRedirect('/cambiar-clave');
        $this->get('/cambiar-clave')->assertOk()->assertSee('Su contraseña es temporal');
    }

    public function test_clave_actual_incorrecta_rechaza_y_suma_un_intento(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');
        $this->actingAs($usuario);

        $this->cambiar(['clave_actual' => 'Incorrecta#1'])
            ->assertRedirect('/cambiar-clave')->assertSessionHasErrors('clave_actual');

        $this->assertSame(1, $usuario->fresh()->intentos_fallidos);
        $this->assertTrue($usuario->fresh()->verificarClave(self::CLAVE));
    }

    public function test_cinco_fallos_bloquean_la_cuenta_y_cierran_la_sesion(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');
        $this->actingAs($usuario);

        for ($i = 1; $i <= 4; $i++) {
            $this->cambiar(['clave_actual' => 'Incorrecta#1']);
        }
        $this->cambiar(['clave_actual' => 'Incorrecta#1'])
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Demasiados intentos fallidos. La cuenta quedó bloqueada por 5 minutos.');

        $this->assertGuest();
        $this->assertTrue($usuario->fresh()->bloqueado_hasta->isFuture());
    }

    public function test_nueva_igual_a_la_actual_o_debil_o_sin_confirmar_se_rechaza(): void
    {
        $this->actingAs(Usuario::buscarPorLogin('recep1'));

        $this->cambiar(['clave_nueva' => self::CLAVE, 'clave_nueva_confirmation' => self::CLAVE])
            ->assertSessionHasErrors('clave_nueva');
        $this->cambiar(['clave_nueva' => 'debil', 'clave_nueva_confirmation' => 'debil'])
            ->assertSessionHasErrors('clave_nueva');
        $this->cambiar(['clave_nueva' => 'sinsimbolo1A', 'clave_nueva_confirmation' => 'sinsimbolo1A'])
            ->assertSessionHasErrors('clave_nueva');
        $this->cambiar(['clave_nueva_confirmation' => 'OtraClave#2026'])
            ->assertSessionHasErrors('clave_nueva');

        $this->assertTrue(Usuario::buscarPorLogin('recep1')->verificarClave(self::CLAVE));
    }

    public function test_las_demas_sesiones_abiertas_se_cierran(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');
        // otra sesión del mismo usuario guardó el hash de la contraseña anterior
        $hashSesionVieja = Auth::guard()->hashPasswordForCookie($usuario->password_hash);

        $usuario->actualizarClave(self::NUEVA);

        $this->actingAs($usuario->fresh())
            ->withSession(['password_hash_web' => $hashSesionVieja])
            ->get('/menu')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_sin_sesion_no_se_puede_cambiar(): void
    {
        $this->get('/cambiar-clave')->assertRedirect('/login');
        $this->put('/cambiar-clave', [])->assertRedirect('/login');
    }
}
