(() => {
    const boton = document.getElementById('abrir-calculo');
    const dialogo = document.getElementById('dialogo-calculo');

    if (!boton || !dialogo || typeof dialogo.showModal !== 'function') return;

    boton.addEventListener('click', () => dialogo.showModal());
    dialogo.addEventListener('close', () => boton.focus());
})();
