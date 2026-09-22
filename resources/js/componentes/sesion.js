// Solo recuerda en el navegador quién inició sesión, para decidir a qué
// pantalla mandar. La seguridad real está en el backend, que exige sesión en
// cada ruta /api; cualquier 401 borra este registro.
const CLAVE = 'punku.usuario';

export function guardarSesion(usuario) {
    try {
        sessionStorage.setItem(CLAVE, JSON.stringify(usuario ?? {}));
    } catch {
        // Sin almacenamiento disponible: la sesión dura lo que dure la pestaña.
    }
}

export function obtenerSesion() {
    try {
        const valor = sessionStorage.getItem(CLAVE);
        return valor ? JSON.parse(valor) : null;
    } catch {
        return null;
    }
}

export function limpiarSesion() {
    try {
        sessionStorage.removeItem(CLAVE);
    } catch {
        // Nada que limpiar.
    }
}
