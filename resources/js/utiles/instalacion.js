import { useSyncExternalStore } from 'react';

// Instalar la aplicación en el teléfono o la computadora. Chrome y Edge
// avisan que se puede instalar con `beforeinstallprompt`: el aviso se guarda
// para mostrarlo cuando la persona toque «Instalar». Llega apenas carga la
// página, por eso se escucha desde el arranque (`escucharInstalacion` en
// app.jsx). Safari de iPhone no tiene ese aviso: allí se instala desde
// Compartir → «Agregar a inicio».

let aviso = null;
const oyentes = new Set();
const avisar = () => oyentes.forEach((oyente) => oyente());

let escuchando = false;

export function escucharInstalacion() {
    if (typeof window === 'undefined' || escuchando) return;
    escuchando = true;
    window.addEventListener('beforeinstallprompt', (evento) => {
        // Sin esto Chrome muestra su propia barra; la sugerencia es nuestra.
        evento.preventDefault();
        aviso = evento;
        avisar();
    });
    window.addEventListener('appinstalled', () => {
        aviso = null;
        avisar();
    });
}

// Ya abierta como aplicación instalada (sin la barra del navegador).
export function estaInstalada() {
    if (typeof window === 'undefined') return false;
    return (
        window.matchMedia?.('(display-mode: standalone)').matches === true ||
        window.navigator.standalone === true
    );
}

// iPhone o iPad (el iPad nuevo se presenta como Mac con pantalla táctil).
export function esIos() {
    if (typeof navigator === 'undefined') return false;
    return (
        /iphone|ipad|ipod/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

const suscribir = (oyente) => {
    oyentes.add(oyente);
    return () => oyentes.delete(oyente);
};

// { forma: 'aviso' | 'ios' | null, instalar } — `aviso` cuando el navegador
// ofrece instalarla, `ios` en Safari de iPhone, null si ya está instalada o
// el navegador no lo permite.
export function useInstalacion() {
    const pendiente = useSyncExternalStore(
        suscribir,
        () => aviso,
        () => null
    );
    const forma = estaInstalada() ? null : pendiente ? 'aviso' : esIos() ? 'ios' : null;
    const instalar = async () => {
        if (!aviso) return null;
        const elegido = aviso;
        await elegido.prompt();
        const { outcome } = await elegido.userChoice;
        // El aviso sirve una sola vez.
        aviso = null;
        avisar();
        return outcome;
    };
    return { forma, instalar };
}

// Para las pruebas: vuelve al estado inicial.
export function olvidarInstalacion() {
    aviso = null;
    avisar();
}
