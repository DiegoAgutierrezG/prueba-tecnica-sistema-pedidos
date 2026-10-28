(() => {
    const cuerpo = document.body;
    const boton = document.querySelector('[data-alternar-menu]');
    const menu = document.getElementById('menu-lateral');
    const velo = document.querySelector('[data-cerrar-menu]');
    const formularioSalida = document.querySelector('.formulario-salida');
    const dialogoSalida = document.getElementById('dialogo-confirmar-salida');

    if (!boton || !menu || !velo) return;

    const pantallaPequena = window.matchMedia('(max-width: 900px)');
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
    const clavePreferencia = 'andina_menu_reducido';
    let reducido = false;
    let abierto = false;
    let submenuAbierto = null;
    let cierrePendiente;

    function cerrarSubmenu() {
        clearTimeout(cierrePendiente);
        if (!submenuAbierto) return;
        const submenu = submenuAbierto;
        submenuAbierto = null;
        submenu.boton.setAttribute('aria-expanded', 'false');
        submenu.panel.inert = true;
        submenu.panel.classList.remove('abierto');
        clearTimeout(submenu.temporizador);
        if (movimientoReducido.matches) {
            submenu.panel.hidden = true;
        } else {
            submenu.temporizador = setTimeout(() => {
                if (!submenu.panel.classList.contains('abierto')) submenu.panel.hidden = true;
            }, 400);
        }
    }

    function previsualizarMenu() {
        cuerpo.classList.toggle('menu-previsualizado', !pantallaPequena.matches && reducido);
    }

    function cerrarPrevisualizacion() {
        if (menu.contains(document.activeElement)) return;
        cerrarSubmenu();
        cuerpo.classList.remove('menu-previsualizado');
    }

    function abrirSubmenu(submenu) {
        clearTimeout(cierrePendiente);
        if (submenuAbierto === submenu) return;
        cerrarSubmenu();
        submenuAbierto = submenu;
        clearTimeout(submenu.temporizador);
        previsualizarMenu();
        submenu.panel.hidden = false;
        submenu.panel.inert = false;
        submenu.panel.style.setProperty('--alto-submenu', `${submenu.panel.scrollHeight}px`);
        submenu.panel.getBoundingClientRect();
        submenu.panel.classList.add('abierto');
        submenu.boton.setAttribute('aria-expanded', 'true');
    }

    menu.querySelectorAll('[data-submenu]').forEach((grupo) => {
        const submenu = {
            grupo,
            boton: grupo.querySelector('[data-alternar-submenu]'),
            panel: grupo.querySelector('.submenu-menu'),
            temporizador: null,
            esperarSalida: false,
        };
        const enlacePrincipal = grupo.querySelector('.enlace-menu');
        // Abrir por movimiento evita que el cambio de altura active otra sección por sí solo.
        grupo.addEventListener('pointermove', (evento) => {
            if (evento.pointerType === 'touch' || pantallaPequena.matches || submenu.esperarSalida) return;
            abrirSubmenu(submenu);
        });
        grupo.addEventListener('pointerleave', () => { submenu.esperarSalida = false; });
        submenu.boton.addEventListener('click', () => {
            if (submenuAbierto === submenu) {
                submenu.esperarSalida = true;
                cerrarSubmenu();
            } else {
                submenu.esperarSalida = false;
                abrirSubmenu(submenu);
            }
        });
        grupo.addEventListener('focusin', (evento) => {
            if (evento.target === enlacePrincipal && !pantallaPequena.matches) abrirSubmenu(submenu);
        });
        grupo.addEventListener('focusout', () => {
            setTimeout(() => {
                if (submenuAbierto === submenu && !grupo.contains(document.activeElement)
                    && (pantallaPequena.matches || !grupo.matches(':hover'))) cerrarSubmenu();
            }, 0);
        });
        grupo.addEventListener('keydown', (evento) => {
            if (evento.key === 'ArrowRight') {
                evento.preventDefault();
                abrirSubmenu(submenu);
                submenu.panel.querySelector('a')?.focus();
            } else if ((evento.key === 'Escape' || evento.key === 'ArrowLeft') && submenuAbierto === submenu) {
                evento.preventDefault();
                evento.stopPropagation();
                submenu.esperarSalida = true;
                cerrarSubmenu();
                submenu.boton.focus();
            }
        });
    });

    document.addEventListener('pointerdown', (evento) => {
        if (submenuAbierto && !submenuAbierto.grupo.contains(evento.target)) cerrarSubmenu();
    });
    document.addEventListener('pointermove', (evento) => {
        if (evento.pointerType === 'touch' || pantallaPequena.matches || !submenuAbierto) return;
        clearTimeout(cierrePendiente);
        const submenu = submenuAbierto;
        if (submenu.grupo.contains(evento.target)) return;
        cierrePendiente = setTimeout(() => {
            if (submenuAbierto === submenu && !submenu.grupo.contains(document.activeElement)) cerrarSubmenu();
        }, 180);
    });
    menu.addEventListener('pointerenter', (evento) => {
        if (evento.pointerType !== 'touch') previsualizarMenu();
    });
    menu.addEventListener('pointerleave', cerrarPrevisualizacion);
    menu.addEventListener('focusin', previsualizarMenu);
    menu.addEventListener('focusout', () => {
        setTimeout(() => {
            if (!menu.matches(':hover')) cerrarPrevisualizacion();
        }, 0);
    });

    try {
        reducido = localStorage.getItem(clavePreferencia) === '1';
    } catch (_) {
        // El menú funciona aunque el navegador bloquee el almacenamiento local.
    }

    function actualizarMenu() {
        cerrarSubmenu();
        cuerpo.classList.remove('menu-previsualizado');
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
        if (evento.key === 'Escape' && submenuAbierto) {
            evento.preventDefault();
            const botonSubmenu = submenuAbierto.boton;
            submenuAbierto.esperarSalida = true;
            cerrarSubmenu();
            botonSubmenu.focus();
            return;
        }
        if (!pantallaPequena.matches || !abierto) return;
        if (evento.key === 'Escape') {
            cerrarMenu();
            return;
        }

        if (evento.key === 'Tab') {
            const elementos = [...menu.querySelectorAll('a, button')]
                .filter((elemento) => elemento.getClientRects().length > 0);
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

    if (formularioSalida && dialogoSalida && typeof dialogoSalida.showModal === 'function') {
        let salidaConfirmada = false;

        formularioSalida.addEventListener('submit', (evento) => {
            if (salidaConfirmada) return;
            evento.preventDefault();
            if (!dialogoSalida.open) dialogoSalida.showModal();
        });

        dialogoSalida.querySelector('[data-cancelar-salida]').addEventListener('click', () => {
            dialogoSalida.close();
        });
        dialogoSalida.querySelector('[data-confirmar-salida]').addEventListener('click', () => {
            salidaConfirmada = true;
            if (typeof formularioSalida.requestSubmit === 'function') {
                formularioSalida.requestSubmit();
            } else {
                formularioSalida.submit();
            }
        });
        dialogoSalida.addEventListener('close', () => {
            if (!salidaConfirmada) formularioSalida.querySelector('.boton-salida')?.focus();
        });
        window.addEventListener('pageshow', (evento) => {
            if (!evento.persisted) return;
            salidaConfirmada = false;
            if (dialogoSalida.open) dialogoSalida.close();
        });
    }
})();
