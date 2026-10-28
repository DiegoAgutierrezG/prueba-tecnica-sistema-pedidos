(() => {
    const admiteDialogos = typeof HTMLDialogElement !== 'undefined'
        && typeof HTMLDialogElement.prototype.showModal === 'function';
    const errorPagina = document.querySelector('[data-error-pagina]');
    if (errorPagina && admiteDialogos) {
        errorPagina.addEventListener('close', () => {
            window.location.replace(errorPagina.dataset.rutaVolver || '/index.php');
        });
        errorPagina.showModal();
        document.getElementById('error-respaldo')?.setAttribute('hidden', '');
    }

    const rutasAccion = new Set([
        '/pedidos/accion.php',
        '/clientes/estado.php',
        '/productos/estado.php',
        '/logout.php',
    ]);
    let dialogoAccion = null;

    function mostrarErrorAccion(titulo, mensaje) {
        if (!dialogoAccion) {
            dialogoAccion = document.createElement('dialog');
            dialogoAccion.className = 'dialogo-confirmacion aviso-error';
            dialogoAccion.setAttribute('aria-labelledby', 'titulo-error-accion');
            dialogoAccion.setAttribute('aria-describedby', 'mensaje-error-accion');
            dialogoAccion.innerHTML = `
                <span class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M24 5a19 19 0 1 0 0 38 19 19 0 0 0 0-38Zm0 11v12m0 5v1"/></svg>
                </span>
                <h2 id="titulo-error-accion"></h2>
                <p id="mensaje-error-accion"></p>
                <div class="acciones-confirmacion">
                    <button type="button" class="btn btn-primary" data-actualizar-error>Actualizar página</button>
                </div>`;
            document.body.append(dialogoAccion);
            dialogoAccion.querySelector('[data-actualizar-error]').addEventListener('click', () => dialogoAccion.close());
            dialogoAccion.addEventListener('close', () => window.location.reload());
        }

        document.querySelectorAll('dialog[open]').forEach((dialogo) => dialogo.close());
        dialogoAccion.querySelector('#titulo-error-accion').textContent = titulo;
        dialogoAccion.querySelector('#mensaje-error-accion').textContent = mensaje;
        dialogoAccion.showModal();
        dialogoAccion.querySelector('[data-actualizar-error]').focus();
    }

    document.addEventListener('submit', async (evento) => {
        if (!admiteDialogos) return;
        if (evento.defaultPrevented || !(evento.target instanceof HTMLFormElement)) return;
        const formulario = evento.target;
        if (formulario.method.toUpperCase() !== 'POST') return;

        const destino = new URL(formulario.action, window.location.href);
        if (destino.origin !== window.location.origin || !rutasAccion.has(destino.pathname)) return;
        evento.preventDefault();
        if (formulario.dataset.enviandoSolicitud === '1') return;
        formulario.dataset.enviandoSolicitud = '1';

        const datos = new FormData(formulario);
        const boton = evento.submitter;
        if (boton?.name) datos.set(boton.name, boton.value);

        try {
            const respuesta = await fetch(destino.href, {
                method: 'POST',
                body: datos,
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (respuesta.redirected) {
                window.location.assign(respuesta.url);
                return;
            }

            if (!respuesta.ok) {
                const documento = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                const titulo = documento.querySelector('[data-error-titulo]')?.textContent.trim()
                    || 'No se pudo completar la acción';
                const mensaje = documento.querySelector('[data-error-mensaje]')?.textContent.trim()
                    || 'Actualiza la página y revisa el estado antes de intentarlo de nuevo.';
                mostrarErrorAccion(titulo, mensaje);
                return;
            }

            mostrarErrorAccion('No se pudo confirmar el resultado',
                'Actualiza la página y comprueba si la acción se realizó antes de repetirla.');
        } catch (_) {
            mostrarErrorAccion('No se pudo comprobar la acción',
                'Comprueba la conexión y actualiza la página para ver si la acción se realizó antes de repetirla.');
        }
    });
})();
