(() => {
    'use strict';

    const cuerpo = document.body;
    const botonMenu = document.querySelector('[data-boton-menu]');
    const capaMenu = document.querySelector('[data-capa-menu]');
    const barraLateral = document.querySelector('[data-barra-lateral]');

    const cerrarMenu = () => {
        cuerpo.classList.remove('menu-abierto');
        botonMenu?.setAttribute('aria-expanded', 'false');
    };

    const abrirMenu = () => {
        cuerpo.classList.add('menu-abierto');
        botonMenu?.setAttribute('aria-expanded', 'true');
    };

    botonMenu?.addEventListener('click', () => {
        if (cuerpo.classList.contains('menu-abierto')) {
            cerrarMenu();
        } else {
            abrirMenu();
        }
    });

    capaMenu?.addEventListener('click', cerrarMenu);

    barraLateral?.querySelectorAll('a').forEach((enlace) => {
        enlace.addEventListener('click', () => {
            if (window.innerWidth <= 1000) {
                cerrarMenu();
            }
        });
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            cerrarMenu();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1000) {
            cerrarMenu();
        }
    });

    const botonClave = document.querySelector('[data-alternar-clave]');
    const entradaClave = document.getElementById('contrasena');

    botonClave?.addEventListener('click', () => {
        if (!entradaClave) return;

        const mostrar = entradaClave.type === 'password';
        entradaClave.type = mostrar ? 'text' : 'password';
        botonClave.setAttribute(
            'aria-label',
            mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
        );
    });

    document.addEventListener('click', (evento) => {
        document.querySelectorAll('.perfil-menu[open]').forEach((menu) => {
            if (!menu.contains(evento.target)) {
                menu.removeAttribute('open');
            }
        });
    });
})();

(() => {
    'use strict';

    const actualizarSwitch = (entrada) => {
        const contenedor = entrada.closest('.gm-switch');
        const texto = contenedor?.querySelector('[data-texto-estado]');
        if (!texto) return;

        const activo = texto.dataset.textoActivo || 'Activo';
        const inactivo = texto.dataset.textoInactivo || 'Inactivo';
        texto.textContent = entrada.checked ? activo : inactivo;
    };

    document.querySelectorAll('[data-switch-estado]').forEach((entrada) => {
        actualizarSwitch(entrada);
        entrada.addEventListener('change', () => actualizarSwitch(entrada));
    });
})();
