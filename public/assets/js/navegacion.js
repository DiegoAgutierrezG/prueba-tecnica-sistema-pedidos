(() => {
    const cuerpo = document.body;
    const boton = document.querySelector('[data-alternar-menu]');
    const menu = document.getElementById('menu-lateral');
    const velo = document.querySelector('[data-cerrar-menu]');
    const formularioSalida = document.querySelector('.formulario-salida');
    const dialogoSalida = document.getElementById('dialogo-confirmar-salida');

    if (!boton || !menu || !velo) return;

    const pantallaPequena = window.matchMedia('(max-width: 900px)');
    const clavePreferencia = 'andina_menu_reducido';
    let reducido = false;
    let abierto = false;

    try {
        reducido = localStorage.getItem(clavePreferencia) === '1';
    } catch (_) {
        // El menú funciona aunque el navegador bloquee el almacenamiento local.
    }

    function actualizarMenu() {
        const movil = pantallaPequena.matches;
        cuerpo.classList.toggle('menu-reducido', !movil && reducido);
        cuerpo.classList.toggle('menu-abierto', movil && abierto);

        const expandido = movil ? abierto : !reducido;
        const accion = movil
            ? (abierto ? 'Cerrar menú' : 'Abrir menú')
            : (reducido ? 'Expandir menú' : 'Contraer menú');

        boton.setAttribute('aria-expanded', String(expandido));
        boton.setAttribute('aria-label', accion);
        boton.title = accion;
    }

    boton.addEventListener('click', () => {
        if (pantallaPequena.matches) {
            abierto = !abierto;
            actualizarMenu();
            if (abierto) menu.querySelector('a')?.focus();
            return;
        }

        reducido = !reducido;
        try {
            localStorage.setItem(clavePreferencia, reducido ? '1' : '0');
        } catch (_) {
            // La preferencia solo se conserva si el navegador lo permite.
        }
        actualizarMenu();
    });

    function cerrarMenu() {
        if (!abierto) return;
        abierto = false;
        actualizarMenu();
        boton.focus();
    }

    velo.addEventListener('click', cerrarMenu);
    document.addEventListener('keydown', (evento) => {
        if (dialogoSalida?.open) return;
        if (!pantallaPequena.matches || !abierto) return;
        if (evento.key === 'Escape') {
            cerrarMenu();
            return;
        }

        if (evento.key === 'Tab') {
            const elementos = menu.querySelectorAll('a, button');
            const primero = elementos[0];
            const ultimo = elementos[elementos.length - 1];
            if (evento.shiftKey && document.activeElement === primero) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && document.activeElement === ultimo) {
                evento.preventDefault();
                primero.focus();
            }
        }
    });

    pantallaPequena.addEventListener('change', () => {
        abierto = false;
        actualizarMenu();
    });

    cuerpo.classList.add('menu-listo');
    actualizarMenu();

    if (formularioSalida) {
        let salidaConfirmada = false;
        const admiteDialogo = typeof HTMLDialogElement !== 'undefined'
            && dialogoSalida instanceof HTMLDialogElement;

        formularioSalida.addEventListener('submit', (evento) => {
            if (salidaConfirmada) return;
            evento.preventDefault();

            if (admiteDialogo) {
                if (!dialogoSalida.open) dialogoSalida.showModal();
            } else if (window.confirm('¿Seguro que quieres cerrar sesión?')) {
                salidaConfirmada = true;
                formularioSalida.requestSubmit();
            }
        });

        if (admiteDialogo) {
            dialogoSalida.querySelector('[data-cancelar-salida]').addEventListener('click', () => {
                dialogoSalida.close();
            });
            dialogoSalida.querySelector('[data-confirmar-salida]').addEventListener('click', () => {
                salidaConfirmada = true;
                formularioSalida.requestSubmit();
            });
            dialogoSalida.addEventListener('close', () => {
                if (!salidaConfirmada) formularioSalida.querySelector('.boton-salida')?.focus();
            });
        }
    }
})();
