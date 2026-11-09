/**
 * Punto de entrada del cliente: monta React sobre el <div id="app"> de la
 * vista Blade.
 *
 * Aviso: macOS no distingue mayusculas en los nombres de archivo, pero Linux
 * si. No crear archivos que solo difieran en la caja (app.jsx / App.jsx): en
 * un Mac se pisan entre si y en el servidor son dos archivos distintos.
 */
import { createRoot } from 'react-dom/client';
import Aplicacion from './navegacion/Aplicacion';
import { escucharInstalacion } from './utiles/instalacion';

// El aviso de instalación llega apenas carga la página: se escucha antes de
// dibujar.
escucharInstalacion();

const contenedor = document.getElementById('app');
if (contenedor) {
    createRoot(contenedor).render(<Aplicacion />);
}

// Aplicación instalable: el service worker solo guarda los estáticos.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
