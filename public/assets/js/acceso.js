const botonVisibilidad = document.querySelector('[data-alternar-clave]');
const campoClave = document.getElementById('clave');

if (botonVisibilidad && campoClave) {
    botonVisibilidad.addEventListener('click', () => {
        const mostrarClave = campoClave.type === 'password';

        campoClave.type = mostrarClave ? 'text' : 'password';
        botonVisibilidad.setAttribute('aria-pressed', String(mostrarClave));
        botonVisibilidad.setAttribute('aria-label', mostrarClave ? 'Ocultar contraseña' : 'Mostrar contraseña');
        campoClave.focus();
    });
}

const dialogoAviso = document.getElementById('dialogo-aviso');

if (dialogoAviso instanceof HTMLDialogElement) {
    dialogoAviso.showModal();
    dialogoAviso.addEventListener('close', () => {
        document.getElementById('correo')?.focus();
    });
}
