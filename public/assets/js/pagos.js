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

    const bancos = [...document.querySelectorAll('#bancos-pago option')].map((opcion) => opcion.value);
    const buscadoresBanco = [];

    function normalizarNombre(texto) {
        return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es-CO');
    }

    formularioPago.querySelectorAll('[data-buscador-banco]').forEach((campo) => {
        const entrada = campo.querySelector('input');
        const lista = campo.querySelector('[data-resultados-banco]');
        let opciones = [];
        let indiceActivo = -1;

        // El datalist queda disponible si JavaScript no se ejecuta.
        entrada.removeAttribute('list');

        function cerrar() {
            lista.hidden = true;
            entrada.setAttribute('aria-expanded', 'false');
            entrada.removeAttribute('aria-activedescendant');
            indiceActivo = -1;
        }

        function seleccionar(nombre) {
            entrada.value = nombre;
            entrada.focus();
            cerrar();
        }

        function mostrar() {
            const termino = normalizarNombre(entrada.value.trim());
            const coincidencias = bancos.filter((nombre) => normalizarNombre(nombre).includes(termino));
            lista.replaceChildren();
            opciones = [];
            indiceActivo = -1;
            entrada.removeAttribute('aria-activedescendant');

            if (coincidencias.length === 0) {
                const aviso = document.createElement('div');
                aviso.className = 'resultado-vacio';
                aviso.textContent = 'No hay bancos con ese nombre. Prueba con otra búsqueda.';
                lista.append(aviso);
            } else {
                coincidencias.forEach((nombre, indice) => {
                    const opcion = document.createElement('button');
                    opcion.type = 'button';
                    opcion.className = 'opcion-busqueda';
                    opcion.id = `${lista.id}-opcion-${indice}`;
                    opcion.setAttribute('role', 'option');
                    opcion.setAttribute('aria-selected', 'false');
                    opcion.tabIndex = -1;
                    const rotulo = document.createElement('span');
                    rotulo.className = 'nombre-resultado';
                    rotulo.textContent = nombre;
                    opcion.append(rotulo);
                    opcion.addEventListener('click', () => seleccionar(nombre));
                    lista.append(opcion);
                    opciones.push({ elemento: opcion, nombre });
                });
            }

            lista.hidden = false;
            entrada.setAttribute('aria-expanded', 'true');
        }

        entrada.addEventListener('focus', mostrar);
        entrada.addEventListener('input', mostrar);
        entrada.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape') {
                cerrar();
                return;
            }
            if (evento.key !== 'ArrowDown' && evento.key !== 'ArrowUp' && evento.key !== 'Enter') return;
            if (lista.hidden && evento.key !== 'Enter') mostrar();
            if (opciones.length === 0 || lista.hidden) return;

            if (evento.key === 'Enter' && indiceActivo >= 0) {
                evento.preventDefault();
                seleccionar(opciones[indiceActivo].nombre);
                return;
            }
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
            }
        });
        campo.addEventListener('focusout', () => {
            setTimeout(() => {
                if (!campo.contains(document.activeElement)) cerrar();
            }, 0);
        });
        buscadoresBanco.push({ campo, cerrar });
    });

    selectorMetodo.addEventListener('change', () => buscadoresBanco.forEach(({ cerrar }) => cerrar()));
    document.addEventListener('pointerdown', (evento) => {
        buscadoresBanco.forEach(({ campo, cerrar }) => {
            if (!campo.contains(evento.target)) cerrar();
        });
    });
}
