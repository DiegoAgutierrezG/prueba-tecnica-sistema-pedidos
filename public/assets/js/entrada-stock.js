(() => {
    const formulario = document.getElementById('formulario-entrada-stock');
    const contenedor = document.getElementById('lineas-entrada');
    const plantilla = document.getElementById('plantilla-linea-entrada');
    const agregar = document.querySelector('[data-agregar-linea]');
    if (!formulario || !contenedor || !plantilla || !agregar) return;

    const dialogo = document.getElementById('dialogo-confirmar-entrada');
    const descripcion = document.getElementById('descripcion-confirmar-entrada');
    const resumen = document.getElementById('resumen-entrada');
    const botonCancelar = dialogo?.querySelector('[data-cancelar-entrada]');
    const botonConfirmar = dialogo?.querySelector('[data-confirmar-entrada]');
    const botonEnviar = formulario.querySelector('button[type="submit"]');
    const mostrarConfirmacion = dialogo && typeof dialogo.showModal === 'function'
        && descripcion && resumen && botonCancelar && botonConfirmar && botonEnviar;
    let confirmado = false;

    let siguienteFila = contenedor.querySelectorAll('.linea-entrada').length;
    const buscadores = new Set();

    function productosElegidos(excepto) {
        return new Set([...contenedor.querySelectorAll('[data-id-seleccionado]')]
            .filter((campo) => campo !== excepto && campo.value)
            .map((campo) => campo.value));
    }

    function limpiarErrores() {
        contenedor.querySelectorAll('[data-entrada-busqueda], [name="cantidad[]"]')
            .forEach((entrada) => entrada.setCustomValidity(''));
    }

    function prepararBuscador(fila) {
        const campo = fila.querySelector('[data-buscador-producto]');
        const entrada = campo.querySelector('[data-entrada-busqueda]');
        const idSeleccionado = campo.querySelector('[data-id-seleccionado]');
        const stockActual = fila.querySelector('[data-stock-actual]');
        const lista = campo.querySelector('[data-resultados-busqueda]');
        let temporizador;
        let peticionActual;
        let secuencia = 0;
        let opciones = [];
        let indiceActivo = -1;

        function cerrar() {
            lista.hidden = true;
            entrada.setAttribute('aria-expanded', 'false');
            entrada.removeAttribute('aria-activedescendant');
            indiceActivo = -1;
        }

        function cancelar() {
            clearTimeout(temporizador);
            peticionActual?.abort();
            secuencia++;
        }

        function mensaje(texto) {
            lista.replaceChildren();
            const aviso = document.createElement('div');
            aviso.className = 'resultado-vacio';
            aviso.textContent = texto;
            lista.append(aviso);
            opciones = [];
            lista.hidden = false;
            entrada.setAttribute('aria-expanded', 'true');
        }

        function seleccionar(resultado) {
            if (productosElegidos(idSeleccionado).has(resultado.id)) {
                programarBusqueda();
                return;
            }
            cancelar();
            entrada.value = resultado.nombre;
            idSeleccionado.value = resultado.id;
            stockActual.value = String(resultado.stock);
            limpiarErrores();
            cerrar();
            buscadores.forEach((buscador) => {
                if (buscador.campo !== campo) {
                    buscador.cancelar();
                    buscador.cerrar();
                }
            });
            entrada.focus();
        }

        function mostrar(resultados) {
            const elegidos = productosElegidos(idSeleccionado);
            resultados = resultados.filter((producto) => !elegidos.has(producto.id));
            lista.replaceChildren();
            opciones = [];
            indiceActivo = -1;
            if (resultados.length === 0) {
                mensaje('No hay productos disponibles para esta búsqueda.');
                return;
            }
            resultados.forEach((resultado, indice) => {
                const opcion = document.createElement('button');
                opcion.type = 'button';
                opcion.className = 'opcion-busqueda';
                opcion.id = `${lista.id}-opcion-${indice}`;
                opcion.setAttribute('role', 'option');
                opcion.setAttribute('aria-selected', 'false');
                opcion.tabIndex = -1;

                const nombre = document.createElement('span');
                nombre.className = 'nombre-resultado';
                nombre.textContent = resultado.nombre;
                const detalle = document.createElement('small');
                detalle.textContent = resultado.detalle;
                opcion.append(nombre, detalle);
                opcion.addEventListener('click', () => seleccionar(resultado));
                lista.append(opcion);
                opciones.push({ elemento: opcion, resultado });
            });
            lista.hidden = false;
            entrada.setAttribute('aria-expanded', 'true');
        }

        async function buscar(termino, numero) {
            const controlador = new AbortController();
            peticionActual = controlador;
            try {
                const parametros = new URLSearchParams({ q: termino });
                productosElegidos(idSeleccionado).forEach((id) => parametros.append('excluir[]', id));
                const respuesta = await fetch(`/productos/buscar.php?${parametros}`, {
                    signal: controlador.signal,
                    cache: 'no-store',
                });
                if (respuesta.redirected && respuesta.url.includes('/login.php')) {
                    window.location.assign(respuesta.url);
                    return;
                }
                if (!respuesta.ok) throw new Error('No se pudo buscar');
                const datos = await respuesta.json();
                if (numero === secuencia && campo.isConnected && entrada.value.trim() === termino) {
                    mostrar(datos.resultados);
                }
            } catch (error) {
                if (error.name !== 'AbortError' && numero === secuencia && campo.isConnected) {
                    mensaje('No se pudo buscar. Intenta de nuevo.');
                }
            }
        }

        function programarBusqueda() {
            cancelar();
            cerrar();
            const termino = entrada.value.trim();
            if (!termino || idSeleccionado.value) return;
            const numero = secuencia;
            temporizador = setTimeout(() => buscar(termino, numero), 220);
        }

        entrada.addEventListener('input', () => {
            idSeleccionado.value = '';
            stockActual.value = '';
            limpiarErrores();
            programarBusqueda();
        });
        entrada.addEventListener('focus', programarBusqueda);
        entrada.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape') {
                cerrar();
                return;
            }
            if (lista.hidden || opciones.length === 0) return;
            if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
                evento.preventDefault();
                indiceActivo = evento.key === 'ArrowDown'
                    ? (indiceActivo + 1) % opciones.length
                    : (indiceActivo - 1 + opciones.length) % opciones.length;
                opciones.forEach(({ elemento }, indice) => {
                    elemento.setAttribute('aria-selected', indice === indiceActivo ? 'true' : 'false');
                });
                entrada.setAttribute('aria-activedescendant', opciones[indiceActivo].elemento.id);
                opciones[indiceActivo].elemento.scrollIntoView({ block: 'nearest' });
            } else if (evento.key === 'Enter' && indiceActivo >= 0) {
                evento.preventDefault();
                seleccionar(opciones[indiceActivo].resultado);
            }
        });
        campo.addEventListener('focusout', () => {
            setTimeout(() => {
                if (!campo.contains(document.activeElement)) cerrar();
            }, 0);
        });

        const buscador = { campo, entrada, idSeleccionado, stockActual, cerrar, cancelar };
        buscadores.add(buscador);
    }

    contenedor.querySelectorAll('.linea-entrada').forEach(prepararBuscador);
    document.addEventListener('pointerdown', (evento) => {
        buscadores.forEach((buscador) => {
            if (!buscador.campo.contains(evento.target)) buscador.cerrar();
        });
    });

    function actualizarLimite() {
        agregar.disabled = contenedor.querySelectorAll('.linea-entrada').length >= 30;
    }
    actualizarLimite();

    agregar.addEventListener('click', () => {
        if (agregar.disabled) return;
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        const numero = siguienteFila++;
        const entrada = fila.querySelector('[data-entrada-busqueda]');
        const lista = fila.querySelector('[data-resultados-busqueda]');
        const stockActual = fila.querySelector('[data-stock-actual]');
        const cantidad = fila.querySelector('[name="cantidad[]"]');
        entrada.id = `producto-entrada-${numero}`;
        lista.id = `resultados-entrada-${numero}`;
        entrada.setAttribute('aria-controls', lista.id);
        fila.querySelector('[data-etiqueta-producto]').htmlFor = entrada.id;
        stockActual.id = `stock-actual-${numero}`;
        fila.querySelector('[data-etiqueta-stock]').htmlFor = stockActual.id;
        cantidad.id = `cantidad-entrada-${numero}`;
        fila.querySelector('[data-etiqueta-cantidad]').htmlFor = cantidad.id;
        contenedor.append(fila);
        prepararBuscador(fila);
        limpiarErrores();
        actualizarLimite();
        entrada.focus();
    });

    contenedor.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-quitar-linea]');
        if (!boton) return;
        const fila = boton.closest('.linea-entrada');
        const buscador = [...buscadores].find((item) => fila.contains(item.campo));
        buscador.cancelar();
        if (contenedor.querySelectorAll('.linea-entrada').length === 1) {
            buscador.entrada.value = '';
            buscador.idSeleccionado.value = '';
            buscador.stockActual.value = '';
            fila.querySelector('[name="cantidad[]"]').value = '';
            buscador.cerrar();
        } else {
            buscadores.delete(buscador);
            fila.remove();
        }
        limpiarErrores();
        actualizarLimite();
    });

    formulario.addEventListener('submit', (evento) => {
        if (confirmado) return;
        const elegidos = new Set();
        const filasResumen = [];
        for (const fila of contenedor.querySelectorAll('.linea-entrada')) {
            const entrada = fila.querySelector('[data-entrada-busqueda]');
            const id = fila.querySelector('[data-id-seleccionado]');
            const stockActual = fila.querySelector('[data-stock-actual]');
            const cantidad = fila.querySelector('[name="cantidad[]"]');
            if (!entrada.value.trim() && !cantidad.value.trim()) continue;
            if (!id.value) {
                evento.preventDefault();
                entrada.setCustomValidity('Elige un producto de los resultados.');
                entrada.reportValidity();
                return;
            }
            if (elegidos.has(id.value)) {
                evento.preventDefault();
                entrada.setCustomValidity('Este producto ya está en la lista.');
                entrada.reportValidity();
                return;
            }
            if (!cantidad.value.trim()) {
                evento.preventDefault();
                cantidad.setCustomValidity('Indica la cantidad a añadir.');
                cantidad.reportValidity();
                return;
            }
            elegidos.add(id.value);
            filasResumen.push({ nombre: entrada.value.trim(), stock: stockActual.value, cantidad: cantidad.value.trim() });
        }
        if (filasResumen.length === 0) {
            evento.preventDefault();
            const entrada = contenedor.querySelector('[data-entrada-busqueda]');
            entrada.setCustomValidity('Agrega al menos un producto.');
            entrada.reportValidity();
            return;
        }

        if (!mostrarConfirmacion) return;
        evento.preventDefault();
        resumen.replaceChildren();
        const unidades = filasResumen.reduce((total, fila) => total + Number(fila.cantidad), 0);
        descripcion.textContent = `${filasResumen.length} producto${filasResumen.length === 1 ? '' : 's'} · ${unidades.toLocaleString('es-CO')} unidad${unidades === 1 ? '' : 'es'} a añadir`;

        filasResumen.forEach((fila) => {
            const linea = document.createElement('div');
            linea.className = 'producto-resumen-stock';
            const nombre = document.createElement('strong');
            nombre.textContent = fila.nombre;
            const calculo = document.createElement('span');
            const actual = Number(fila.stock);
            const entrada = Number(fila.cantidad);
            calculo.textContent = `Actual: ${actual.toLocaleString('es-CO')} + Entrada: ${entrada.toLocaleString('es-CO')} = Final: ${(actual + entrada).toLocaleString('es-CO')}`;
            linea.append(nombre, calculo);
            resumen.append(linea);
        });

        dialogo.showModal();
        botonCancelar.focus();
    });

    if (mostrarConfirmacion) {
        botonCancelar.addEventListener('click', () => dialogo.close());
        botonConfirmar.addEventListener('click', () => {
            confirmado = true;
            botonConfirmar.disabled = true;
            dialogo.close();
            if (typeof formulario.requestSubmit === 'function') {
                formulario.requestSubmit(botonEnviar);
            } else {
                formulario.submit();
            }
        });
        dialogo.addEventListener('close', () => {
            if (!confirmado) botonEnviar.focus();
            botonConfirmar.disabled = false;
        });
    }

    contenedor.addEventListener('input', (evento) => {
        if (evento.target.matches('[name="cantidad[]"]')) limpiarErrores();
    });
})();
