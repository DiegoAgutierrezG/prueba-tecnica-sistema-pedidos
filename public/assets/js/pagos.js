const formularioPago = document.querySelector('[data-formulario-pago]');

if (formularioPago) {
    const selectorMetodo = formularioPago.querySelector('#metodo');
    const detalles = formularioPago.querySelectorAll('[data-detalle-metodo]');

    function actualizarDetallePago() {
        detalles.forEach((detalle) => {
            const visible = detalle.dataset.detalleMetodo === selectorMetodo.value;
            detalle.hidden = !visible;
            detalle.querySelectorAll('input').forEach((campo) => {
                campo.disabled = !visible;
                campo.required = visible;
            });
        });
    }

    selectorMetodo.addEventListener('change', actualizarDetallePago);
    actualizarDetallePago();
}
