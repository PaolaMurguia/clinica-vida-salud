<?php

namespace Tests\Feature\Acceso;

use App\Models\Bitacora;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU01 – Iniciar sesión.
 */
class IniciarSesionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const CLAVE = 'Clinica#2026';

    public function test_muestra_el_formulario_de_inicio_de_sesion(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Usuario o correo')->assertSee('Contraseña');
    }

    public function test_ingreso_correcto_regenera_la_sesion_y_lleva_al_menu(): void
    {
        $this->get('/login');
        $sesionAnterior = session()->getId();

        $this->post('/login', ['login' => 'admin', 'clave' => self::CLAVE])->assertRedirect('/menu');

        $this->assertAuthenticatedAs(Usuario::buscarPorLogin('admin'));
        $this->assertNotSame($sesionAnterior, session()->getId());
        $this->assertNotNull(Usuario::buscarPorLogin('admin')->ultimo_acceso);
        $this->assertDatabaseHas('bitacora', ['accion' => 'LOGIN', 'usuario_id' => Usuario::buscarPorLogin('admin')->id]);

        $this->get('/menu')->assertOk()->assertSee('Bienvenido, admin');
    }

    public function test_ingreso_con_correo_y_sin_distinguir_mayusculas(): void
    {
        $this->post('/login', ['login' => '  RECEP1@vidasalud.com ', 'clave' => self::CLAVE])->assertRedirect('/menu');
        $this->assertAuthenticated();
    }

    public function test_paciente_va_al_portal_con_su_ci(): void
    {
        $this->post('/login', ['login' => '1234567', 'clave' => self::CLAVE])->assertRedirect('/portal');
        $this->get('/portal')->assertOk()->assertSee('Portal del paciente')->assertSee('María');
        $this->get('/menu')->assertForbidden();
    }

    public function test_credenciales_incorrectas_mensaje_generico_y_suma_intento(): void
    {
        $fallosPrevios = Bitacora::where('accion', 'LOGIN_FALLIDO')->count();   // la población ya trae uno

        foreach (['admin', 'no-existe'] as $login) {
            $this->from('/login')->post('/login', ['login' => $login, 'clave' => 'Incorrecta#1'])
                ->assertRedirect('/login')
                ->assertSessionHas('error', 'Usuario o contraseña incorrectos.')
                ->assertSessionHasInput('login', $login)
                ->assertSessionMissing('_old_input.clave');
        }

        $this->assertGuest();
        $this->assertSame(1, Usuario::buscarPorLogin('admin')->intentos_fallidos);
        $this->assertSame($fallosPrevios + 2, Bitacora::where('accion', 'LOGIN_FALLIDO')->count());
    }

    public function test_cinco_fallos_bloquean_la_cuenta_cinco_minutos(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->post('/login', ['login' => 'recep1', 'clave' => 'Incorrecta#1']);
        }
        $this->post('/login', ['login' => 'recep1', 'clave' => 'Incorrecta#1'])
            ->assertSessionHas('error', 'Demasiados intentos fallidos. La cuenta quedó bloqueada por 5 minutos.');

        $usuario = Usuario::buscarPorLogin('recep1');
        $this->assertSame(1, $usuario->nivel_bloqueo);
        $this->assertDatabaseHas('bitacora', ['accion' => 'BLOQUEO_CUENTA', 'usuario_id' => $usuario->id]);
    }

    public function test_cuenta_bloqueada_informa_el_tiempo_sin_verificar_la_clave(): void
    {
        Usuario::buscarPorLogin('recep1')->forceFill(['bloqueado_hasta' => now()->addMinutes(15), 'nivel_bloqueo' => 2])->save();

        // aun con la contraseña correcta, no ingresa y se informa el tiempo restante
        $this->post('/login', ['login' => 'recep1', 'clave' => self::CLAVE])
            ->assertSessionHas('error', 'La cuenta está bloqueada temporalmente. Intente nuevamente en 15 minuto(s).');
        $this->assertGuest();
        $this->assertSame(0, Usuario::buscarPorLogin('recep1')->intentos_fallidos);
    }

    public function test_bloqueo_vencido_permite_ingresar_y_reinicia_el_nivel(): void
    {
        Usuario::buscarPorLogin('recep1')->forceFill(['bloqueado_hasta' => now()->subMinute(), 'nivel_bloqueo' => 2, 'intentos_fallidos' => 3])->save();

        $this->post('/login', ['login' => 'recep1', 'clave' => self::CLAVE])->assertRedirect('/menu');

        $usuario = Usuario::buscarPorLogin('recep1');
        $this->assertSame([0, 0, null], [$usuario->intentos_fallidos, $usuario->nivel_bloqueo, $usuario->bloqueado_hasta]);
    }

    public function test_usuario_inactivo_acceso_denegado(): void
    {
        Usuario::buscarPorLogin('recep1')->update(['estado' => false]);

        $this->post('/login', ['login' => 'recep1', 'clave' => self::CLAVE])
            ->assertSessionHas('error', 'Acceso denegado: la cuenta está inactiva. Comuníquese con la clínica.');
        $this->assertGuest();
    }

    public function test_campos_vacios_rechazados_en_el_servidor(): void
    {
        $this->post('/login', ['login' => '   ', 'clave' => ''])->assertSessionHasErrors(['login', 'clave']);
        $this->assertGuest();
    }

    public function test_sesion_abierta_redirige_al_menu_o_al_portal(): void
    {
        $this->actingAs(Usuario::buscarPorLogin('admin'))->get('/login')->assertRedirect('/menu');
        $this->actingAs(Usuario::buscarPorLogin('1234567'))->get('/login')->assertRedirect('/portal');
    }

    public function test_pagina_interna_sin_sesion_lleva_al_login(): void
    {
        $this->get('/menu')->assertRedirect('/login');
        $this->get('/portal')->assertRedirect('/login');
    }
}
