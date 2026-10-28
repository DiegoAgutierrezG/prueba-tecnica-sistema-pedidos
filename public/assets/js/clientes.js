(() => {
    const dialogo = document.getElementById('dialogo-estado-cliente');
    const formularios = document.querySelectorAll('.formulario-estado-cliente');
    if (!dialogo || typeof dialogo.showModal !== 'function' || formularios.length === 0) return;

    const titulo = document.getElementById('titulo-estado-cliente');
    const descripcion = document.getElementById('descripcion-estado-cliente');
    const botonCancelar = dialogo.querySelector('[data-cancelar-estado]');
    const botonConfirmar = dialogo.querySelector('[data-confirmar-estado]');
    let formularioPendiente = null;
    let botonPendiente = null;
    let formularioConfirmado = null;

    formularios.forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            if (formularioConfirmado === formulario) return;
            evento.preventDefault();

            const boton = evento.submitter || formulario.querySelector('button[name="accion"]');
            if (!boton) return;

            formularioPendiente = formulario;
            botonPendiente = boton;
            const desactivar = boton.value === 'desactivar';
            const nombre = formulario.dataset.nombreCliente;

            titulo.textContent = desactivar ? '¿Desactivar cliente?' : '¿Reactivar cliente?';
            descripcion.textContent = desactivar
                ? `«${nombre}» dejará de estar disponible para pedidos nuevos. Su historial se conservará.`
                : `«${nombre}» volverá a estar disponible para pedidos nuevos.`;
            botonConfirmar.textContent = desactivar ? 'Sí, desactivar' : 'Sí, reactivar';
            botonConfirmar.classList.toggle('btn-danger', desactivar);
            botonConfirmar.classList.toggle('btn-primary', !desactivar);
            dialogo.classList.toggle('es-desactivacion', desactivar);

            if (!dialogo.open) dialogo.showModal();
            botonCancelar.focus();
        });
    });

    botonCancelar.addEventListener('click', () => dialogo.close());
    botonConfirmar.addEventListener('click', () => {
        if (!formularioPendiente || !botonPendiente) return;
        formularioConfirmado = formularioPendiente;
        botonConfirmar.disabled = true;
        if (typeof formularioPendiente.requestSubmit === 'function') {
            formularioPendiente.requestSubmit(botonPendiente);
        } else {
            botonPendiente.click();
        }
    });

    dialogo.addEventListener('close', () => {
        if (!formularioConfirmado) botonPendiente?.focus();
        formularioPendiente = null;
        botonPendiente = null;
        botonConfirmar.disabled = false;
    });
})();

(() => {
    const dialogo = document.getElementById('dialogo-error-cliente');
    if (!dialogo || typeof dialogo.showModal !== 'function') return;

    dialogo.addEventListener('close', () => {
        document.querySelector('#contenido-principal .is-invalid')?.focus();
    });
    dialogo.showModal();
    document.querySelector('[data-error-cliente-respaldo]')?.setAttribute('hidden', '');
})();
