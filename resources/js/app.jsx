/**
 * Punto de entrada del cliente: monta React sobre el <div id="app"> de la
 * vista Blade.
 *
 * Aviso: macOS no distingue mayusculas en los nombres de archivo, pero Linux
 * si. No crear archivos que solo difieran en la caja (app.jsx / App.jsx): en
 * un Mac se pisan entre si y en el servidor son dos archivos distintos.
 */
import './bootstrap';
import { createRoot } from 'react-dom/client';
import Aplicacion from './componentes/Aplicacion';

const contenedor = document.getElementById('app');
if (contenedor) {
    createRoot(contenedor).render(<Aplicacion />);
}
