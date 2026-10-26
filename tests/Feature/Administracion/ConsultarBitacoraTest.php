<?php

namespace Tests\Feature\Administracion;

use App\Models\Bitacora;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU04 – Consultar bitácora.
 */
class ConsultarBitacoraTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::buscarPorLogin('admin'));
    }

    public function test_muestra_los_registros_recientes(): void
    {
        $this->get('/bitacora')->assertOk()
            ->assertSee('Fecha y hora')->assertSee('LOGIN_FALLIDO')->assertSee('recep1')->assertSee('192.168.1.21');
    }

    public function test_solo_el_administrador_accede(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'))->get('/bitacora')->assertForbidden();
    }

    public function test_filtra_por_usuario_fechas_y_modulo(): void
    {
        $recep1 = Usuario::buscarPorLogin('recep1');
        $hoy = today()->toDateString();

        $this->get("/bitacora/filtrar?usuario_id={$recep1->id}&modulo=Pacientes&desde=$hoy&hasta=$hoy")
            ->assertOk()
            ->assertSee('Registro del paciente P-0010')
            ->assertDontSee('Registro de la cita CM-0011')
            ->assertSee('Registros del usuario');
    }

    public function test_resultados_paginados(): void
    {
        $admin = Usuario::buscarPorLogin('admin');
        for ($i = 1; $i <= 25; $i++) {
            Bitacora::registrar(['usuario_id' => $admin->id, 'tipo' => 'Accion', 'accion' => 'PRUEBA', 'modulo' => 'Pruebas', 'descripcion' => "Evento $i"]);
        }

        $this->get('/bitacora/filtrar?modulo=Pruebas')->assertOk()->assertSee('Evento 25')->assertDontSee('Evento 5<');
        $this->get('/bitacora/filtrar?modulo=Pruebas&page=2')->assertOk()->assertSee('Evento 5');
    }

    public function test_rango_invalido_y_mayor_a_90_dias(): void
    {
        $this->from('/bitacora')->get('/bitacora/filtrar?desde=2026-10-20&hasta=2026-10-01')
            ->assertRedirect('/bitacora')
            ->assertSessionHasErrors(['hasta' => 'Rango de fechas inválido: la fecha inicial es posterior a la final.']);

        $this->from('/bitacora')->get('/bitacora/filtrar?desde=2026-01-01&hasta=2026-06-30')
            ->assertSessionHasErrors(['desde' => 'El rango es mayor a 90 días: acote el período.']);

        $this->from('/bitacora')->get('/bitacora/filtrar?desde=2026-01-01')->assertSessionHasErrors('desde');
    }

    public function test_sin_registros_para_los_filtros(): void
    {
        $this->get('/bitacora/filtrar?modulo=Inexistente')->assertOk()->assertSee('No hay registros para los filtros indicados.');
    }

    public function test_exporta_el_resultado_a_csv(): void
    {
        $respuesta = $this->get('/bitacora/exportar?modulo=Citas');

        $respuesta->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Registro de la cita CM-0011', $csv);
        $this->assertStringNotContainsString('Registro del paciente P-0010', $csv);
        $this->assertDatabaseHas('bitacora', ['accion' => 'EXPORTAR', 'modulo' => 'Bitácora']);
    }

    public function test_la_bitacora_no_se_edita_ni_se_elimina(): void
    {
        // no existe ninguna ruta para modificar o borrar registros
        $id = Bitacora::first()->id;
        $this->put("/bitacora/$id")->assertNotFound();
        $this->delete("/bitacora/$id")->assertNotFound();
        $this->assertDatabaseHas('bitacora', ['id' => $id]);
    }
}
