(() => {
    const dialogo = document.getElementById('dialogo-aviso-pedido');
    if (dialogo && typeof dialogo.showModal === 'function') dialogo.showModal();
})();
