(() => {
    const dialogo = document.getElementById('dialogo-estado-producto');
    const formularios = document.querySelectorAll('.formulario-estado-producto');
    if (!dialogo || typeof dialogo.showModal !== 'function' || formularios.length === 0) return;

    const titulo = document.getElementById('titulo-estado-producto');
    const descripcion = document.getElementById('descripcion-estado-producto');
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
            const nombre = formulario.dataset.nombreProducto;

            titulo.textContent = desactivar ? '¿Desactivar producto?' : '¿Reactivar producto?';
            descripcion.textContent = desactivar
                ? `«${nombre}» dejará de estar disponible para pedidos nuevos. Su historial y existencias se conservarán.`
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
