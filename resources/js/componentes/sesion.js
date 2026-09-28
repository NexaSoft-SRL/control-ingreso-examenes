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

// Las secciones que el rol puede abrir. El backend vuelve a comprobarlo en
// cada peticion: esto solo evita ofrecer puertas cerradas.
export function permisosDeSesion() {
    return obtenerSesion()?.permisos ?? [];
}

export function rolDeSesion() {
    return obtenerSesion()?.rol ?? null;
}

export function tienePermiso(permiso) {
    return permisosDeSesion().includes(permiso);
}

export function limpiarSesion() {
    try {
        sessionStorage.removeItem(CLAVE);
    } catch {
        // Nada que limpiar.
    }
}
