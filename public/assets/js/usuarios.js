(() => {
    const formulario = document.getElementById('formulario-usuario');
    const dialogoConfirmacion = document.getElementById('dialogo-confirmar-usuario');

    if (formulario && dialogoConfirmacion && typeof dialogoConfirmacion.showModal === 'function') {
        const botonGuardar = document.getElementById('boton-guardar-usuario');
        const botonCancelar = dialogoConfirmacion.querySelector('[data-cancelar-usuario]');
        const botonConfirmar = dialogoConfirmacion.querySelector('[data-confirmar-usuario]');
        const esEdicion = formulario.dataset.accionUsuario === 'editar';
        let confirmado = false;

        formulario.addEventListener('submit', (evento) => {
            if (confirmado) return;
            evento.preventDefault();

            const nombre = formulario.elements.namedItem('nombre');
            const correo = formulario.elements.namedItem('correo');
            const rol = formulario.querySelector('#rol_id');
            const estado = formulario.querySelector('#activo');
            const clave = formulario.elements.namedItem('clave');

            dialogoConfirmacion.querySelector('#titulo-confirmar-usuario').textContent =
                esEdicion ? '¿Guardar cambios?' : '¿Crear usuario?';
            dialogoConfirmacion.querySelector('[data-resumen-nombre]').textContent = nombre.value.trim();
            dialogoConfirmacion.querySelector('[data-resumen-correo]').textContent = correo.value.trim();
            dialogoConfirmacion.querySelector('[data-resumen-rol]').textContent =
                rol.options[rol.selectedIndex]?.textContent.trim() || '';
            dialogoConfirmacion.querySelector('[data-resumen-estado]').textContent =
                estado.options[estado.selectedIndex]?.textContent.trim() || '';
            dialogoConfirmacion.querySelector('[data-resumen-clave]').textContent =
                clave.value === '' ? 'Sin cambios' : 'Se asignará una nueva';
            botonConfirmar.textContent = esEdicion ? 'Sí, guardar cambios' : 'Sí, crear usuario';

            dialogoConfirmacion.showModal();
            botonCancelar.focus();
        });

        botonCancelar.addEventListener('click', () => dialogoConfirmacion.close());
        botonConfirmar.addEventListener('click', () => {
            if (confirmado) return;
            confirmado = true;
            botonConfirmar.disabled = true;
            dialogoConfirmacion.close();
            if (typeof formulario.requestSubmit === 'function') {
                formulario.requestSubmit(botonGuardar);
            } else {
                formulario.submit();
            }
        });
        dialogoConfirmacion.addEventListener('close', () => {
            if (!confirmado) botonGuardar.focus();
        });
        window.addEventListener('pageshow', (evento) => {
            if (!evento.persisted) return;
            confirmado = false;
            botonConfirmar.disabled = false;
            if (dialogoConfirmacion.open) dialogoConfirmacion.close();
        });
    }

    const dialogoResultado = document.getElementById('dialogo-resultado-usuario');
    const avisoRespaldo = document.getElementById('aviso-usuario-respaldo');
    if (dialogoResultado && avisoRespaldo && typeof dialogoResultado.showModal === 'function') {
        dialogoResultado.showModal();
        avisoRespaldo.hidden = true;
        const botonCerrar = dialogoResultado.querySelector('[data-cerrar-resultado-usuario]');
        botonCerrar.focus();
        botonCerrar.addEventListener('click', () => dialogoResultado.close());
    }
})();
