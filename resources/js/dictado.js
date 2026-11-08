/**
 * CU21 – Dictar registro clínico por voz (UI_HistoriaClinica: dictar() y detener()).
 *
 * Usa el reconocimiento de voz del navegador (Web Speech API); el audio no se envía al servidor ni se guarda.
 * Formulario con data-dictado: cada textarea con data-dictable recibe un botón «Dictar» / «Detener».
 * Al usar el dictado se marca el campo oculto dictado_voz=1 (el servidor valida el texto igual que si se escribiera).
 */
const Reconocimiento = window.SpeechRecognition || window.webkitSpeechRecognition;

const MENSAJES = {
    'not-allowed': 'Permiso de micrófono denegado: habilítelo en el navegador o escriba con el teclado.',
    'service-not-allowed': 'Permiso de micrófono denegado: habilítelo en el navegador o escriba con el teclado.',
    'no-speech': 'No se reconoció la voz. Pulse «Dictar» para intentarlo de nuevo.',
    'audio-capture': 'No se encontró un micrófono. Escriba con el teclado.',
    'network': 'Se perdió la conexión con el reconocimiento de voz. Puede seguir escribiendo con el teclado.',
};

function iniciarDictado(formulario) {
    const aviso = formulario.querySelector('[data-dictado-aviso]');
    const marca = formulario.querySelector('input[name="dictado_voz"]');
    const mostrar = (texto, tipo = 'warning') => {
        if (!aviso) return;
        aviso.className = `alert alert-${tipo} py-2 small`;
        aviso.textContent = texto;
        aviso.hidden = !texto;
    };

    // Excepción 1: navegador sin soporte → se ocultan los botones y se indica usar el teclado
    if (!Reconocimiento) {
        mostrar('Su navegador no permite el dictado por voz: escriba con el teclado.', 'secondary');
        return;
    }

    let activo = null;   // { reconocimiento, boton, campo }

    const detener = () => {
        if (activo) {
            activo.reconocimiento.stop();
        }
    };

    formulario.querySelectorAll('textarea[data-dictable]').forEach((campo) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-sm btn-outline-secondary mb-1';
        boton.textContent = 'Dictar';
        boton.setAttribute('aria-label', `Dictar ${campo.labels?.[0]?.textContent ?? 'texto'}`);
        campo.closest('.mb-3')?.querySelector('.d-flex')?.append(boton);

        boton.addEventListener('click', () => {
            if (activo?.campo === campo) {
                detener();           // detener(): paso 4
                return;
            }
            detener();

            // dictar(): pasos 1 a 3
            const reconocimiento = new Reconocimiento();
            reconocimiento.lang = 'es-BO';
            reconocimiento.continuous = true;
            reconocimiento.interimResults = true;
            const base = campo.value ? `${campo.value.trimEnd()} ` : '';

            reconocimiento.onresult = (evento) => {
                let texto = '';
                for (let i = 0; i < evento.results.length; i++) {
                    texto += evento.results[i][0].transcript;
                }
                campo.value = base + texto.trim();
                campo.dispatchEvent(new Event('input', { bubbles: true }));
                if (marca) marca.value = '1';
                const maximo = Number(campo.getAttribute('maxlength'));
                if (maximo && campo.value.length > maximo) {
                    mostrar(`El texto supera los ${maximo} caracteres permitidos: acórtelo antes de guardar.`);
                }
            };
            reconocimiento.onerror = (evento) => {
                mostrar(MENSAJES[evento.error] ?? 'Falló el reconocimiento de voz. Puede seguir escribiendo con el teclado.');
            };
            reconocimiento.onend = () => {
                boton.textContent = 'Dictar';
                boton.classList.replace('btn-danger', 'btn-outline-secondary');
                activo = null;
            };

            try {
                reconocimiento.start();   // el navegador pide permiso del micrófono (paso 2)
                activo = { reconocimiento, boton, campo };
                boton.textContent = 'Detener';
                boton.classList.replace('btn-outline-secondary', 'btn-danger');
                mostrar('');
            } catch (error) {
                mostrar('No se pudo iniciar el dictado. Escriba con el teclado.');
            }
        });
    });

    // Al guardar se detiene el dictado; el texto se envía como si se hubiera escrito (paso 5)
    formulario.addEventListener('submit', detener);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-dictado]').forEach(iniciarDictado);
});
