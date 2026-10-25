/**
 * Validación en el navegador (RNF07). El servidor vuelve a validar todo con Form Requests.
 *
 * Formularios con el atributo data-validar:
 *  - los campos required no pueden quedar vacíos (ni con solo espacios);
 *  - un campo con data-coincide="id" debe ser igual al campo con ese id (confirmación de contraseña).
 */
function validarFormulario(formulario) {
    let valido = true;

    formulario.querySelectorAll('input, select, textarea').forEach((campo) => {
        let mensaje = '';

        if (campo.required && campo.value.trim() === '') {
            mensaje = 'Este campo es obligatorio.';
        } else if (campo.dataset.coincide) {
            const original = document.getElementById(campo.dataset.coincide);
            if (original && campo.value !== original.value) {
                mensaje = 'La confirmación no coincide.';
            }
        }

        campo.setCustomValidity(mensaje);
        if (mensaje && campo.nextElementSibling?.classList.contains('invalid-feedback') && campo.dataset.coincide) {
            campo.nextElementSibling.textContent = mensaje;
        }
        if (!campo.checkValidity()) {
            valido = false;
        }
    });

    formulario.classList.add('was-validated');
    return valido;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validar]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            if (!validarFormulario(formulario)) {
                evento.preventDefault();
                evento.stopPropagation();
            }
        });
        // vuelve a validar mientras el usuario corrige, una vez intentado el envío
        formulario.addEventListener('input', () => {
            if (formulario.classList.contains('was-validated')) {
                validarFormulario(formulario);
            }
        });
    });
});
