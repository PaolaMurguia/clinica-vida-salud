<?php

namespace Tests\Feature\Administracion;

use App\Models\Especialidad;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU05 – Gestionar especialidades.
 */
class GestionarEspecialidadesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::buscarPorLogin('admin'));
    }

    public function test_lista_las_especialidades(): void
    {
        $this->get('/especialidades')->assertOk()
            ->assertSee('Pediatría')->assertSee('0 a 14 años')->assertSee('Todas las edades')->assertSee('250,00');
    }

    public function test_solo_el_administrador_accede(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'))->get('/especialidades')->assertForbidden();
    }

    public function test_registra_especialidad_con_nombre_normalizado(): void
    {
        $this->post('/especialidades', [
            'nombre' => '  Neurología   infantil ', 'descripcion' => 'Sistema nervioso', 'costo_consulta' => '220.50',
            'edad_minima' => '0', 'edad_maxima' => '14',
        ])->assertSessionHasNoErrors()->assertRedirect('/especialidades');

        $nueva = Especialidad::where('nombre', 'Neurología infantil')->first();
        $this->assertSame('neurología infantil', $nueva->nombre_normalizado);
        $this->assertSame('220.50', $nueva->costo_consulta);
        $this->assertSame([0, 14], [$nueva->edad_minima, $nueva->edad_maxima]);
        $this->assertDatabaseHas('bitacora', ['accion' => 'CREAR', 'modulo' => 'Especialidades', 'registro_id' => $nueva->id]);
    }

    public function test_registra_sin_rango_de_edad(): void
    {
        $this->post('/especialidades', ['nombre' => 'Dermatología', 'costo_consulta' => '180'])->assertSessionHasNoErrors();
        $nueva = Especialidad::where('nombre', 'Dermatología')->first();
        $this->assertNull($nueva->edad_minima);
        $this->assertNull($nueva->edad_maxima);

        // solo edad máxima, o solo edad mínima
        $this->post('/especialidades', ['nombre' => 'Hebiatría', 'costo_consulta' => '150', 'edad_maxima' => '18'])->assertSessionHasNoErrors();
        $this->post('/especialidades', ['nombre' => 'Gerontología', 'costo_consulta' => '150', 'edad_minima' => '65'])->assertSessionHasNoErrors();
        $this->assertSame(18, Especialidad::where('nombre', 'Hebiatría')->first()->edad_maxima);
    }

    public function test_nombre_duplicado_normalizado(): void
    {
        $this->post('/especialidades', ['nombre' => 'MEDICINA   general', 'costo_consulta' => '100'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe una especialidad con ese nombre.']);
    }

    public function test_rango_de_edad_invalido_o_costo_negativo(): void
    {
        $this->post('/especialidades', ['nombre' => 'Nueva', 'costo_consulta' => '100', 'edad_minima' => '30', 'edad_maxima' => '10'])
            ->assertSessionHasErrors(['edad_maxima' => 'Rango de edad inválido: la edad mínima es mayor que la máxima.']);
        $this->post('/especialidades', ['nombre' => 'Nueva', 'costo_consulta' => '-5'])
            ->assertSessionHasErrors(['costo_consulta' => 'El costo de la consulta no puede ser negativo.']);
        $this->post('/especialidades', ['nombre' => '', 'costo_consulta' => ''])->assertSessionHasErrors(['nombre', 'costo_consulta']);
    }

    public function test_edita_especialidad_y_conserva_su_propio_nombre(): void
    {
        $pediatria = Especialidad::where('nombre', 'Pediatría')->first();
        $this->get("/especialidades?editar={$pediatria->id}")->assertOk()->assertSee('Editar Pediatría');

        $this->put("/especialidades/{$pediatria->id}", [
            'nombre' => 'Pediatría', 'descripcion' => 'Niños hasta 14 años', 'costo_consulta' => '160', 'edad_minima' => '0', 'edad_maxima' => '14',
        ])->assertSessionHasNoErrors();
        $this->assertSame('160.00', $pediatria->fresh()->costo_consulta);

        // no puede tomar el nombre de otra especialidad
        $this->put("/especialidades/{$pediatria->id}", ['nombre' => 'Geriatría', 'costo_consulta' => '160'])->assertSessionHasErrors('nombre');
    }

    public function test_desactiva_y_activa(): void
    {
        $odontologia = Especialidad::where('nombre', 'Odontología')->first();

        $this->patch("/especialidades/{$odontologia->id}/estado", ['accion' => 'desactivar'])->assertSessionHasNoErrors();
        $this->assertFalse($odontologia->fresh()->estado);
        $this->assertNotContains('Odontología', Especialidad::listarPorEdad(30)->pluck('nombre'));

        $this->patch("/especialidades/{$odontologia->id}/estado", ['accion' => 'activar'])->assertSessionHasNoErrors();
        $this->assertTrue($odontologia->fresh()->estado);
        $this->assertDatabaseHas('bitacora', ['accion' => 'DESACTIVAR', 'modulo' => 'Especialidades']);
    }
}
