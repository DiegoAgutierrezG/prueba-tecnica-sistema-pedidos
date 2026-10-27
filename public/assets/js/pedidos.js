(() => {
    const formulario = document.querySelector('form[action="/pedidos/nuevo.php"]');
    const contenedor = document.getElementById('lineas-pedido');
    const plantilla = document.getElementById('plantilla-linea');
    const agregar = document.querySelector('[data-agregar-linea]');
    if (!formulario || !contenedor || !plantilla || !agregar) return;

    let siguienteFila = contenedor.querySelectorAll('.linea-pedido').length;
    const buscadores = new Set();

    function prepararBuscador(campo) {
        const entrada = campo.querySelector('[data-entrada-busqueda]');
        const idSeleccionado = campo.querySelector('[data-id-seleccionado]');
        const lista = campo.querySelector('[data-resultados-busqueda]');
        const tipo = campo.dataset.buscador;
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
            cancelar();
            entrada.value = resultado.nombre;
            entrada.setCustomValidity('');
            idSeleccionado.value = resultado.id;
            cerrar();
            entrada.focus();
        }

        function mostrar(resultados) {
            lista.replaceChildren();
            opciones = [];
            indiceActivo = -1;
            if (resultados.length === 0) {
                mensaje('Sin resultados. Prueba con otro término.');
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
                const parametros = new URLSearchParams({ tipo, q: termino });
                const respuesta = await fetch(`/pedidos/buscar.php?${parametros}`, {
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
            entrada.setCustomValidity('');
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

        const buscador = { campo, entrada, idSeleccionado, cerrar, cancelar };
        buscadores.add(buscador);
        return buscador;
    }

    document.querySelectorAll('[data-buscador]').forEach(prepararBuscador);

    document.addEventListener('pointerdown', (evento) => {
        buscadores.forEach((buscador) => {
            if (!buscador.campo.contains(evento.target)) buscador.cerrar();
        });
    });

    agregar.addEventListener('click', () => {
        if (contenedor.querySelectorAll('.linea-pedido').length >= 30) return;
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        const numero = siguienteFila++;
        const entrada = fila.querySelector('[data-entrada-busqueda]');
        const lista = fila.querySelector('[data-resultados-busqueda]');
        const cantidad = fila.querySelector('[name="cantidad[]"]');
        entrada.id = `producto-${numero}`;
        lista.id = `resultados-producto-${numero}`;
        entrada.setAttribute('aria-controls', lista.id);
        fila.querySelector('[data-etiqueta-producto]').htmlFor = entrada.id;
        cantidad.id = `cantidad-${numero}`;
        fila.querySelector('[data-etiqueta-cantidad]').htmlFor = cantidad.id;
        contenedor.append(fila);
        prepararBuscador(fila.querySelector('[data-buscador]'));
        entrada.focus();
    });

    contenedor.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-quitar-linea]');
        if (!boton) return;
        const fila = boton.closest('.linea-pedido');
        const buscador = [...buscadores].find((item) => fila.contains(item.campo));
        if (contenedor.querySelectorAll('.linea-pedido').length === 1) {
            buscador.cancelar();
            buscador.cerrar();
            buscador.entrada.value = '';
            buscador.entrada.setCustomValidity('');
            buscador.idSeleccionado.value = '';
            fila.querySelector('[name="cantidad[]"]').value = '';
        } else {
            buscador.cancelar();
            buscadores.delete(buscador);
            fila.remove();
        }
    });

    formulario.addEventListener('submit', (evento) => {
        const cliente = formulario.querySelector('[data-buscador="cliente"]');
        const clienteEntrada = cliente.querySelector('[data-entrada-busqueda]');
        if (!cliente.querySelector('[data-id-seleccionado]').value) {
            evento.preventDefault();
            clienteEntrada.setCustomValidity('Elige un cliente de los resultados.');
            clienteEntrada.reportValidity();
            return;
        }
        for (const fila of contenedor.querySelectorAll('.linea-pedido')) {
            const entrada = fila.querySelector('[data-entrada-busqueda]');
            const id = fila.querySelector('[data-id-seleccionado]');
            const cantidad = fila.querySelector('[name="cantidad[]"]');
            if (!entrada.value.trim() && !cantidad.value.trim()) continue;
            if (!id.value) {
                evento.preventDefault();
                entrada.setCustomValidity('Elige un producto de los resultados.');
                entrada.reportValidity();
                return;
            }
        }
    });
})();
