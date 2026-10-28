(() => {
    const formulario = document.getElementById('formulario-devolucion');
    const dialogo = document.getElementById('dialogo-confirmar-devolucion');
    if (!formulario || !dialogo || typeof dialogo.showModal !== 'function') return;

    const botonEnviar = document.getElementById('boton-registrar-devolucion');
    const botonCancelar = dialogo.querySelector('[data-cancelar-devolucion]');
    const botonConfirmar = dialogo.querySelector('[data-confirmar-devolucion]');
    const metodoResumen = dialogo.querySelector('[data-metodo-devolucion]');
    let confirmada = false;

    formulario.addEventListener('submit', (evento) => {
        if (confirmada) return;
        evento.preventDefault();
        const metodo = formulario.querySelector('#metodo');
        metodoResumen.textContent = metodo.options[metodo.selectedIndex]?.textContent.trim() || '';
        dialogo.showModal();
        botonCancelar.focus();
    });
    botonCancelar.addEventListener('click', () => dialogo.close());
    botonConfirmar.addEventListener('click', () => {
        if (confirmada) return;
        confirmada = true;
        botonConfirmar.disabled = true;
        dialogo.close();
        if (typeof formulario.requestSubmit === 'function') {
            formulario.requestSubmit(botonEnviar);
        } else {
            botonEnviar.click();
        }
    });
    dialogo.addEventListener('close', () => {
        if (!confirmada) botonEnviar.focus();
    });
    window.addEventListener('pageshow', (evento) => {
        if (!evento.persisted) return;
        confirmada = false;
        botonConfirmar.disabled = false;
        if (dialogo.open) dialogo.close();
    });
})();
