<?php

namespace Tests\Feature\Acceso;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU02 – Cerrar sesión.
 */
class CerrarSesionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_el_menu_muestra_la_opcion_salir(): void
    {
        $this->actingAs(Usuario::buscarPorLogin('admin'))->get('/menu')->assertOk()->assertSee('Salir');
    }

    public function test_cerrar_sesion_registra_invalida_y_lleva_al_login(): void
    {
        $admin = Usuario::buscarPorLogin('admin');
        $this->post('/login', ['login' => 'admin', 'clave' => 'Clinica#2026']);
        $tokenAnterior = session()->token();

        $this->post('/logout')->assertRedirect('/login')->assertSessionHas('exito', 'Sesión cerrada correctamente.');

        $this->assertGuest();
        $this->assertNotSame($tokenAnterior, session()->token());
        $this->assertDatabaseHas('bitacora', ['accion' => 'LOGOUT', 'usuario_id' => $admin->id]);
    }

    public function test_paginas_internas_dejan_de_ser_accesibles(): void
    {
        $this->post('/login', ['login' => 'admin', 'clave' => 'Clinica#2026']);
        $this->post('/logout');

        $this->get('/menu')->assertRedirect('/login');
    }

    public function test_sesion_expirada_redirige_al_login(): void
    {
        // sin usuario en la sesión, «Salir» lleva directamente al inicio de sesión
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_paginas_internas_no_se_guardan_en_cache(): void
    {
        $respuesta = $this->actingAs(Usuario::buscarPorLogin('admin'))->get('/menu');

        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $respuesta->headers->get('Pragma'));
    }
}
