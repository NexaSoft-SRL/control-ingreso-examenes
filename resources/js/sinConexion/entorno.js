// Lo que el navegador permite guardar: sin esto el modo sin conexión no
// sirve o se pierde al cerrar la ventana.
const CUOTA_PRIVADA = 150 * 1024 * 1024;

// Falso si no se puede escribir (bloqueado o lleno).
export function almacenamientoDisponible() {
    try {
        const clave = 'sinconexion:prueba';
        window.localStorage.setItem(clave, '1');
        window.localStorage.removeItem(clave);
        return true;
    } catch {
        return false;
    }
}

// Estimación: las ventanas privadas dan una cuota de almacenamiento muy
// chica. No es exacta en todos los navegadores; ante la duda dice que no.
export async function esNavegacionPrivada() {
    try {
        const { quota } = await navigator.storage.estimate();
        return typeof quota === 'number' && quota > 0 && quota < CUOTA_PRIVADA;
    } catch {
        return false;
    }
}

// Pide al navegador que no borre lo guardado por falta de espacio.
export async function pedirPersistencia() {
    try {
        if (!navigator.storage?.persist) return null;
        return (await navigator.storage.persisted()) || (await navigator.storage.persist());
    } catch {
        return null;
    }
}
