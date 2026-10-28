(() => {
    const avisoStock = document.getElementById('dialogo-stock-confirmacion');
    if (avisoStock && typeof avisoStock.showModal === 'function') avisoStock.showModal();

    const formularioAnulacion = document.querySelector('.formulario-anular-pedido');
    const dialogoAnulacion = document.getElementById('dialogo-anular-pedido');
    if (formularioAnulacion && dialogoAnulacion && typeof dialogoAnulacion.showModal === 'function') {
        const botonAnular = formularioAnulacion.querySelector('button[name="accion"]');
        const botonCancelar = dialogoAnulacion.querySelector('[data-cancelar-anulacion]');
        const botonConfirmar = dialogoAnulacion.querySelector('[data-confirmar-anulacion]');
        let confirmada = false;

        formularioAnulacion.addEventListener('submit', (evento) => {
            if (confirmada) return;
            evento.preventDefault();
            dialogoAnulacion.showModal();
            botonCancelar.focus();
        });
        botonCancelar.addEventListener('click', () => dialogoAnulacion.close());
        botonConfirmar.addEventListener('click', () => {
            if (confirmada) return;
            confirmada = true;
            botonConfirmar.disabled = true;
            dialogoAnulacion.close();
            if (typeof formularioAnulacion.requestSubmit === 'function') {
                formularioAnulacion.requestSubmit(botonAnular);
            } else {
                botonAnular.click();
            }
        });
        dialogoAnulacion.addEventListener('close', () => {
            if (!confirmada) botonAnular.focus();
        });
        window.addEventListener('pageshow', (evento) => {
            if (!evento.persisted) return;
            confirmada = false;
            botonConfirmar.disabled = false;
            if (dialogoAnulacion.open) dialogoAnulacion.close();
        });
    }

    const boton = document.getElementById('abrir-calculo');
    const dialogo = document.getElementById('dialogo-calculo');

    if (!boton || !dialogo || typeof dialogo.showModal !== 'function') return;

    boton.addEventListener('click', () => dialogo.showModal());
    dialogo.addEventListener('close', () => boton.focus());
})();
