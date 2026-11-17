'use strict';

for (const boton of document.querySelectorAll('[data-munichis-ver-clave]')) {
    const campo = document.getElementById(boton.getAttribute('aria-controls'));

    boton.hidden = false;
    boton.addEventListener('click', () => {
        const visible = campo.type === 'password';
        campo.type = visible ? 'text' : 'password';
        boton.setAttribute('aria-pressed', String(visible));
        boton.title = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';
    });
}

const claveNueva = document.querySelector('[data-munichis-clave-nueva]');
const medidor = document.querySelector('[data-munichis-medidor]');
if (claveNueva && medidor) {
    const barra = medidor.querySelector('progress');
    const texto = medidor.querySelector('[data-munichis-nivel-texto]');
    const etiquetas = ['Sin evaluar', 'Débil', 'Regular', 'Buena', 'Fuerte'];
    const patrones = [/\p{Ll}/u, /\p{Lu}/u, /\p{N}/u, /[\p{S}\p{P}]/u];
    const codificador = new TextEncoder();

    function nivelDeClave(clave) {
        if (!clave) return 0;
        if (codificador.encode(clave).length > 72 || clave.includes('\0')) return 1;

        const longitud = Array.from(clave).length;
        const variedad = patrones.filter(patron => patron.test(clave)).length;
        const predecible = /password|contrase(?:ñ|n)a|qwerty|munichis|123456/i.test(clave)
            || /^(.{1,4})\1{2,}$/u.test(clave);
        if (predecible || longitud < 8 || variedad < 2) return 1;
        if (longitud < 12 || variedad < 4) return 2;

        return longitud >= 16 ? 4 : 3;
    }

    function mostrarNivel() {
        const nivel = nivelDeClave(claveNueva.value);
        if (barra.value === nivel) return;

        barra.value = nivel;
        barra.setAttribute('aria-valuetext', etiquetas[nivel]);
        texto.textContent = etiquetas[nivel];
        if (nivel) {
            medidor.dataset.nivel = String(nivel);
        } else {
            delete medidor.dataset.nivel;
        }
    }

    medidor.hidden = false;
    claveNueva.addEventListener('input', mostrarNivel);
    mostrarNivel();
}

const lienzo = document.querySelector('[data-munichis-qr]');
if (lienzo && typeof window.qrcode === 'function') {
    const contexto = lienzo.getContext('2d');
    if (contexto) {
        const qr = window.qrcode(0, 'M');
        qr.addData(lienzo.dataset.munichisQr, 'Byte');
        qr.make();

        const modulo = 6;
        const margen = 4 * modulo;
        const lado = qr.getModuleCount() * modulo + 2 * margen;
        lienzo.width = lienzo.height = lado;
        contexto.fillStyle = '#fff';
        contexto.fillRect(0, 0, lado, lado);
        contexto.translate(margen, margen);
        qr.renderTo2dContext(contexto, modulo);
        lienzo.removeAttribute('data-munichis-qr');
        lienzo.hidden = false;
    }
}
