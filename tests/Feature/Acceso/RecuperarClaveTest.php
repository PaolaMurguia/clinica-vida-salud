<?php

namespace Tests\Feature\Acceso;

use App\Models\Recuperacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * CU27 – Recuperar contraseña. El mailer «array» de phpunit.xml guarda los correos enviados.
 */
class RecuperarClaveTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const MENSAJE = 'Si la cuenta existe, recibirá en su correo un enlace para restablecer la contraseña.';
    private const NUEVA = 'Recuperada#2026';

    /** Correos enviados en la prueba. */
    private function correos(): Collection
    {
        return app('mail.manager')->mailer()->getSymfonyTransport()->messages();
    }

    /** Token del enlace del último correo enviado. */
    private function tokenDelCorreo(): string
    {
        $cuerpo = $this->correos()->last()->getOriginalMessage()->getTextBody();
        preg_match('#/recuperar-clave/([A-Za-z0-9]{64})#', $cuerpo, $coincidencia);

        return $coincidencia[1];
    }

    private function solicitar(string $login)
    {
        return $this->from('/recuperar-clave')->post('/recuperar-clave', ['login' => $login]);
    }

    public function test_el_login_enlaza_a_recuperar_y_muestra_el_formulario(): void
    {
        $this->get('/login')->assertSee('¿Olvidó su contraseña?');
        $this->get('/recuperar-clave')->assertOk()->assertSee('Correo o nombre de usuario');
    }

    public function test_cuenta_existente_recibe_un_enlace_y_solo_se_guarda_el_hash(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');

        $this->solicitar('recep1@vidasalud.com')->assertRedirect('/recuperar-clave')->assertSessionHas('exito', self::MENSAJE);

        $this->assertCount(1, $this->correos());
        $this->assertSame('recep1@vidasalud.com', $this->correos()->first()->getEnvelope()->getRecipients()[0]->getAddress());
        $token = $this->tokenDelCorreo();
        $this->assertDatabaseHas('recuperacion_clave', ['usuario_id' => $usuario->id, 'token_hash' => hash('sha256', $token)]);
        $this->assertDatabaseMissing('recuperacion_clave', ['token_hash' => $token]);
        $this->assertDatabaseHas('bitacora', ['accion' => 'SOLICITUD_RECUPERACION', 'usuario_id' => $usuario->id]);
    }

    public function test_cuenta_inexistente_o_inactiva_mismo_mensaje_sin_correo(): void
    {
        Usuario::buscarPorLogin('recep2')->update(['estado' => false]);

        $this->solicitar('nadie@correo.com')->assertSessionHas('exito', self::MENSAJE);
        $this->solicitar('recep2')->assertSessionHas('exito', self::MENSAJE);

        $this->assertCount(0, $this->correos());
        $this->assertSame(0, Recuperacion::where('usuario_id', Usuario::buscarPorLogin('recep2')->id)->count());
    }

    public function test_mas_de_tres_solicitudes_por_hora_no_generan_enlace(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->solicitar('recep1')->assertSessionHas('exito', self::MENSAJE);
        }

        $this->assertCount(3, $this->correos());
        $this->assertSame(3, Recuperacion::contarRecientes(Usuario::buscarPorLogin('recep1')->id));
        $this->assertDatabaseHas('bitacora', ['accion' => 'LIMITE_RECUPERACION']);
    }

    public function test_falla_del_correo_se_registra_y_se_muestra_el_mismo_mensaje(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
        app('mail.manager')->purge();

        $this->solicitar('recep1')->assertSessionHas('exito', self::MENSAJE);
        $this->assertDatabaseHas('bitacora', ['accion' => 'ENVIO_CORREO_FALLIDO']);
    }

    public function test_al_abrir_un_enlace_valido_se_muestra_el_formulario(): void
    {
        $this->solicitar('recep1');

        $this->get('/recuperar-clave/' . $this->tokenDelCorreo())
            ->assertOk()->assertSee('Contraseña nueva')->assertDontSee('El enlace venció');
    }

    public function test_al_abrir_un_enlace_vencido_usado_o_alterado_se_muestra_el_aviso(): void
    {
        // la población trae un enlace usado (enlace-demo-1) y uno vencido (enlace-demo-2)
        foreach (['enlace-demo-1', 'enlace-demo-2', 'alterado123'] as $token) {
            $this->get("/recuperar-clave/$token")
                ->assertOk()
                ->assertSee('El enlace venció, ya se usó o no es válido.')
                ->assertSee('Solicitar otro enlace')
                ->assertDontSee('Contraseña nueva');
        }
    }

    public function test_restablecer_cambia_la_clave_desbloquea_y_el_enlace_no_se_reutiliza(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');
        $usuario->forceFill(['bloqueado_hasta' => now()->addHour(), 'nivel_bloqueo' => 3, 'intentos_fallidos' => 2])->save();
        $usuario->marcarTemporal('Temporal#2026');   // reinicia el bloqueo; se vuelve a bloquear abajo
        $usuario->forceFill(['bloqueado_hasta' => now()->addHour(), 'nivel_bloqueo' => 3])->save();
        $this->solicitar('recep1');
        $token = $this->tokenDelCorreo();

        $this->post('/restablecer-clave', ['token' => $token, 'clave' => self::NUEVA, 'clave_confirmation' => self::NUEVA])
            ->assertRedirect('/login')->assertSessionHas('exito');

        $usuario->refresh();
        $this->assertTrue($usuario->verificarClave(self::NUEVA));
        $this->assertFalse($usuario->debe_cambiar_clave);
        $this->assertSame([0, 0, null], [$usuario->intentos_fallidos, $usuario->nivel_bloqueo, $usuario->bloqueado_hasta]);
        $this->assertDatabaseHas('bitacora', ['accion' => 'RESTABLECER_CLAVE', 'usuario_id' => $usuario->id]);

        // el enlace ya no sirve: ni al abrirlo ni al enviarlo otra vez
        $this->get("/recuperar-clave/$token")->assertSee('El enlace venció, ya se usó o no es válido.');
        $this->post('/restablecer-clave', ['token' => $token, 'clave' => 'Otra#Clave2026', 'clave_confirmation' => 'Otra#Clave2026'])
            ->assertRedirect("/recuperar-clave/$token");
        $this->assertTrue($usuario->fresh()->verificarClave(self::NUEVA));

        // y con la contraseña nueva se puede iniciar sesión
        $this->post('/login', ['login' => 'recep1', 'clave' => self::NUEVA])->assertRedirect('/menu');
    }

    public function test_al_enviar_se_vuelve_a_validar_el_enlace(): void
    {
        $this->solicitar('recep1');
        $token = $this->tokenDelCorreo();
        // vence entre la apertura del formulario y el envío
        Recuperacion::where('token_hash', hash('sha256', $token))->update(['fecha_expira' => now()->subMinute(), 'fecha_solicitud' => now()->subHours(2)]);

        $this->post('/restablecer-clave', ['token' => $token, 'clave' => self::NUEVA, 'clave_confirmation' => self::NUEVA])
            ->assertRedirect("/recuperar-clave/$token");
        $this->assertFalse(Usuario::buscarPorLogin('recep1')->verificarClave(self::NUEVA));
    }

    public function test_la_nueva_clave_debe_cumplir_la_politica_y_coincidir(): void
    {
        $this->solicitar('recep1');
        $token = $this->tokenDelCorreo();

        $this->post('/restablecer-clave', ['token' => $token, 'clave' => 'debil', 'clave_confirmation' => 'debil'])
            ->assertSessionHasErrors('clave');
        $this->post('/restablecer-clave', ['token' => $token, 'clave' => self::NUEVA, 'clave_confirmation' => 'Distinta#2026'])
            ->assertSessionHasErrors('clave');
        $this->assertNotNull(Recuperacion::validar($token));
    }

    public function test_con_sesion_iniciada_no_se_puede_recuperar(): void
    {
        $this->actingAs(Usuario::buscarPorLogin('admin'))->get('/recuperar-clave')->assertRedirect('/menu');
    }
}
